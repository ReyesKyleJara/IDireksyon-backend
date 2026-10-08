<?php

use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\GovernmentIdRequirementItem;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['role' => 'researcher']));
});

function catalogReferencePayload(string $type, int $id, string $instructions = ''): array
{
    return [
        'application_type' => 'new', 'applicant_type' => 'adult',
        'groups' => [[
            'title' => 'Supporting evidence', 'rule' => 'all', 'condition_type' => 'always',
            'ways' => [[
                'required_count' => 1, 'qualification_type' => 'none', 'qualification_scope' => 'every',
                'items' => [[
                    'type' => $type, ($type === 'document' ? 'document_id' : 'government_id_id') => $id,
                    'submission_format' => 'original', 'instructions' => $instructions,
                ]],
            ]],
        ]],
    ];
}

it('blocks deletion of referenced records until the checklist is removed', function ($type, $model, $resource) {
    $target = GovernmentId::create(['name' => 'Target credential']);
    $reference = $model::create(['name' => 'Referenced evidence']);
    $url = '/admin/government-ids/'.$target->id.'/checklists';
    $saved = $this->postJson($url, catalogReferencePayload($type, $reference->id))->assertCreated()->json('checklist.id');
    $this->delete('/admin/'.$resource.'/'.$reference->id)->assertRedirect()->assertSessionHas('error');
    $this->assertModelExists($reference);
    $this->deleteJson($url.'/'.$saved)->assertOk();
    $this->delete('/admin/'.$resource.'/'.$reference->id)->assertRedirect('/admin/'.$resource);
    $this->assertModelMissing($reference);
    $this->assertModelExists($target);
})->with([['document', Document::class, 'documents'], ['government_id', GovernmentId::class, 'government-ids']]);

it('keeps submission instructions local to each checklist while sharing a directory reference', function () {
    $doc = Document::create(['name' => 'Shared certificate']);
    foreach (['First instructions', 'Second instructions'] as $instructions) {
        $target = GovernmentId::create(['name' => $instructions]);
        $this->postJson('/admin/government-ids/'.$target->id.'/checklists', catalogReferencePayload('document', $doc->id, $instructions))
            ->assertCreated();
    }
    expect(GovernmentIdRequirementItem::orderBy('id')->pluck('instructions')->all())->toBe(['First instructions', 'Second instructions'])
        ->and(GovernmentIdRequirementItem::pluck('document_id')->unique()->all())->toBe([$doc->id])
        ->and(Document::count())->toBe(1);
    $doc->update(['name' => 'Renamed certificate']);
    foreach (GovernmentId::all() as $target) {
        $this->getJson('/api/government-ids/'.$target->id)->assertOk()
            ->assertJsonPath('data.requirement_sets.0.groups.0.ways.0.items.0.name', 'Renamed certificate');
    }
});

it('exposes a real prerequisite reference and a conditional requirement without inventing readiness', function () {
    $target = GovernmentId::create(['name' => 'License']);
    $permit = GovernmentId::create(['name' => 'Student permit']);
    $payload = catalogReferencePayload('government_id', $permit->id);
    $payload['groups'][0]['condition_type'] = 'representative';
    $this->postJson('/admin/government-ids/'.$target->id.'/checklists', $payload)->assertCreated();
    $this->getJson('/api/government-ids/'.$target->id)->assertOk()
        ->assertJsonPath('data.requirement_sets.0.groups.0.condition_type', 'representative')
        ->assertJsonPath('data.requirement_sets.0.groups.0.ways.0.items.0.government_id_id', $permit->id);
    // This checks stored references, not eligibility, readiness, or a sequencing algorithm.
});

it('keeps zero fixed fees free fees and variable fees distinct', function () {
    $this->post('/admin/government-ids', [
        'name' => 'Fee example',
        'fees' => [
            ['label' => 'Zero fixed', 'type' => 'fixed', 'amount_min' => 0],
            ['label' => 'Free service', 'type' => 'free'],
            ['label' => 'Optional delivery', 'type' => 'varies', 'is_optional' => true],
        ],
    ])->assertSessionHasNoErrors();
    $id = GovernmentId::sole();
    $fees = $id->fees()->orderBy('sort_order')->get();
    expect($fees->pluck('type')->all())->toBe(['fixed', 'free', 'varies'])
        ->and((float) $fees[0]->amount_min)->toBe(0.0)
        ->and($fees[1]->amount_min)->toBeNull()->and($fees[2]->amount_min)->toBeNull()
        ->and((bool) $fees[2]->is_optional)->toBeTrue();
    expect($id->fee)->toContain('Zero fixed: ₱0.00', 'Free service: Free', 'Optional delivery: Varies');
});

it('rejects invalid fee ranges without replacing saved fees or changing the ID', function () {
    $id = GovernmentId::create(['name' => 'Keep ID']);
    $fee = $id->fees()->create(['label' => 'Existing fee', 'type' => 'fixed', 'amount_min' => 100, 'currency' => 'PHP', 'sort_order' => 0]);
    $this->putJson('/admin/government-ids/'.$id->id, [
        'name' => 'Do not save',
        'fees' => [['label' => 'Invalid range', 'type' => 'range', 'amount_min' => 200, 'amount_max' => 100]],
    ])->assertUnprocessable()->assertJsonValidationErrors('fees.0.amount_max');
    expect($id->fresh()->name)->toBe('Keep ID')->and($id->fees()->count())->toBe(1);
    $this->assertModelExists($fee);
});
