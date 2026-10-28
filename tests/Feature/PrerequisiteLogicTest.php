<?php

use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\User;
use App\Services\CatalogPrerequisites;
use App\Services\PrerequisiteStatus;

function linkedPrerequisite(GovernmentId|Document $target): array
{
    $type = $target instanceof Document ? 'document' : 'government_id';
    return ['type' => $type, $type.'_id' => $target->id];
}

it('computes current exact ID and type ownership without evaluating conditions', function (string $model) {
    $owner = $model::create(['name' => 'Owner']);
    $id = GovernmentId::create(['name' => 'Same name']);
    $sameName = GovernmentId::create(['name' => 'Same name']);
    $document = Document::create(['name' => 'Same name']);
    $otherDocument = Document::create(['name' => 'Other document']);
    $condition = 'Only if another primary ID cannot be provided';
    app(CatalogPrerequisites::class)->save($owner, [
        linkedPrerequisite($id) + ['condition' => $condition],
        linkedPrerequisite($sameName), linkedPrerequisite($document), linkedPrerequisite($otherDocument),
        ['type' => 'custom', 'custom_name' => 'Complete the required course', 'condition' => 'If applicable'],
    ]);
    $resident = User::factory()->create();
    $otherResident = User::factory()->create();
    $otherResident->ownedGovernmentIds()->attach($id);
    $resident->load(['ownedGovernmentIds', 'ownedDocuments']);
    $service = app(PrerequisiteStatus::class);
    expect(array_column($service->forResident($owner, $resident), 'status'))
        ->toBe(['missing', 'missing', 'missing', 'missing', 'manual']);
    $before = $owner->prerequisites()->get()->toArray();
    $this->actingAs($resident)->putJson('/api/auth/inventory', [
        'government_id_ids' => [$id->id], 'document_ids' => [$document->id],
    ])->assertOk();
    $rows = $service->forResident($owner, $resident);
    expect(array_column($rows, 'status'))->toBe(['satisfied', 'missing', 'satisfied', 'missing', 'manual'])
        ->and($rows[0]['condition'])->toBe($condition)
        ->and($rows[4]['condition'])->toBe('If applicable')
        ->and($owner->prerequisites()->get()->toArray())->toBe($before);
    $this->putJson('/api/auth/inventory', ['government_id_ids' => [], 'document_ids' => []])->assertOk();
    expect(array_column($service->forResident($owner, $resident), 'status'))
        ->toBe(['missing', 'missing', 'missing', 'missing', 'manual']);
})->with([[GovernmentId::class], [Document::class]]);

it('returns empty status arrays and distinguishes equal numeric IDs across catalog types', function () {
    $owner = GovernmentId::create(['name' => 'Owner']);
    $id = GovernmentId::forceCreate(['name' => 'ID', 'id' => 42]);
    // IDs are intentionally equal; ownership in one catalog must not satisfy the other.
    $document = Document::forceCreate(['name' => 'Document', 'id' => $id->id]);
    $resident = User::factory()->create();
    expect(app(PrerequisiteStatus::class)->forResident($owner, $resident))->toBe([]);
    app(CatalogPrerequisites::class)->save($owner, [linkedPrerequisite($id), linkedPrerequisite($document)]);
    $resident->ownedGovernmentIds()->attach($id);
    expect(array_column(app(PrerequisiteStatus::class)->forResident($owner, $resident), 'status'))
        ->toBe(['satisfied', 'missing']);
});

it('rejects closing short long and cross-type chains through Add and Edit without changing saved rows', function (string $mode, int $length, bool $crossType) {
    $this->actingAs(User::factory()->create(['role' => 'researcher']));
    $chain = [];
    for ($i = 0; $i < $length; $i++) {
        $model = $crossType && $i % 2 ? Document::class : GovernmentId::class;
        $chain[] = $model::create(['name' => 'Node '.$i]);
    }
    $service = app(CatalogPrerequisites::class);
    for ($i = 0; $i < $length - 1; $i++) $service->save($chain[$i], [linkedPrerequisite($chain[$i + 1])]);
    $last = $chain[$length - 1];
    $service->save($last, [['type' => 'custom', 'custom_name' => 'Keep existing']]);
    $before = $last->prerequisites()->get()->toArray();
    $resource = $last instanceof Document ? 'documents' : 'government-ids';
    $url = route('admin.'.$resource.'.'.$mode.'.save', [$last, 'requirements']);
    $this->postJson($url, ['prerequisites_present' => 1, 'prerequisites' => [linkedPrerequisite($chain[0])]])
        ->assertUnprocessable()->assertJsonValidationErrors('prerequisites.0.type')
        ->assertJsonFragment(['prerequisites.0.type' => ['This prerequisite would create a circular dependency.']]);
    expect($last->prerequisites()->get()->toArray())->toBe($before);
})->with(['wizard', 'edit-wizard'])->with([2, 3, 12])->with([false, true]);

it('validates changed targets while allowing reordered independent and custom prerequisites', function (string $model, string $resource) {
    $this->actingAs(User::factory()->create(['role' => 'researcher']));
    $owner = $model::create(['name' => 'Owner']);
    $dependent = GovernmentId::create(['name' => 'Dependent']);
    $first = Document::create(['name' => 'Independent first']);
    $second = GovernmentId::create(['name' => 'Independent second']);
    $service = app(CatalogPrerequisites::class);
    $service->save($dependent, [linkedPrerequisite($owner)]);
    $service->save($owner, [linkedPrerequisite($first), linkedPrerequisite($second),
        ['type' => 'custom', 'custom_name' => $owner->name]]);
    $before = $owner->prerequisites()->get()->toArray();
    $rowId = $before[0]['id'];
    $this->postJson(route('admin.'.$resource.'.edit-wizard.save', [$owner, 'requirements']), [
        'prerequisites_present' => 1,
        'prerequisites' => [linkedPrerequisite($dependent) + ['id' => $rowId]],
    ])->assertUnprocessable()->assertJsonValidationErrors('prerequisites.0.type');
    expect($owner->prerequisites()->get()->toArray())->toBe($before);
    $service->save($owner, [linkedPrerequisite($second), linkedPrerequisite($first),
        ['type' => 'custom', 'custom_name' => $owner->name]]);
    expect($owner->prerequisites()->count())->toBe(3);
    // Removing an edge must not be rejected because of the old graph.
    $service->save($dependent, []);
    $service->save($owner, [linkedPrerequisite($dependent)]);
    expect($owner->prerequisites()->sole()->government_id_id)->toBe($dependent->id);
})->with([[GovernmentId::class, 'government-ids'], [Document::class, 'documents']]);
