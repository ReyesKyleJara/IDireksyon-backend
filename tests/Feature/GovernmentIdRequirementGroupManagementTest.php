<?php

use App\Models\ContentChangeLog;
use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\GovernmentIdRequirementGroup;
use App\Models\GovernmentIdRequirementItem;
use App\Models\User;
use Illuminate\Database\QueryException;

function requirementGroupRow(array $overrides = []): array
{
    return array_replace([
        'type' => 'custom', 'custom_name' => 'Passport-size photo',
        'submission_format' => 'not_specified',
    ], $overrides);
}

function requirementGroupPayload(array $overrides = []): array
{
    return array_replace([
        'rule' => 'all', 'condition_type' => 'always',
        'items' => [requirementGroupRow()],
    ], $overrides);
}

beforeEach(function () {
    $this->actingAs(User::factory()->create(['role' => 'researcher', 'name' => 'Checklist Researcher']));
    $this->targetId = GovernmentId::create(['name' => 'Target ID', 'requirements' => 'Legacy requirements.']);
    $this->set = $this->targetId->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'adult']);
    $this->groupsUrl = '/admin/government-ids/'.$this->targetId->id.'/requirement-sets/'.$this->set->id.'/groups';
});

it('creates and renders all three item types without changing existing APIs', function () {
    $document = Document::create(['name' => 'Example Birth Certificate']);
    $acceptedId = GovernmentId::create(['name' => 'Example Accepted ID']);
    $beforeDetail = \Illuminate\Support\Arr::except($this->getJson('/api/government-ids/'.$this->targetId->id)->assertOk()->json('data'), ['requirement_sets']);
    $beforeList = $this->getJson('/api/government-ids')->assertOk()->json();
    $this->get($this->groupsUrl)->assertOk()->assertSee('No requirement groups yet.');
    $this->get($this->groupsUrl.'/create')->assertOk()->assertSee('Requirement Items')->assertSee('Custom Condition Question');
    $this->post($this->groupsUrl, requirementGroupPayload([
        'title' => 'Core documents',
        'items' => [
            requirementGroupRow(['type' => 'document', 'document_id' => $document->id, 'submission_format' => 'original_photocopy', 'copies' => 1]),
            requirementGroupRow(['type' => 'government_id', 'government_id_id' => $acceptedId->id, 'submission_format' => 'photocopy', 'copies' => 2, 'instructions' => 'Front and back.']),
            requirementGroupRow(['submission_format' => 'custom', 'submission_format_custom' => 'Printed photo', 'copies' => 2]),
        ],
    ]))->assertSessionHasNoErrors()->assertRedirect($this->groupsUrl);
    $group = $this->set->groups()->sole();
    expect($group->items)->toHaveCount(3)
        ->and($group->items[0]->document->is($document))->toBeTrue()
        ->and($group->items[0]->custom_name)->toBeNull()
        ->and($group->items[1]->governmentId->is($acceptedId))->toBeTrue()
        ->and($group->items[2]->government_id_id)->toBeNull()
        ->and($group->requirementSet->is($this->set))->toBeTrue()
        ->and($group->items[0]->group->is($group))->toBeTrue();
    $this->get($this->groupsUrl)->assertOk()->assertSee('Core documents')
        ->assertSee('Example Birth Certificate')->assertSee('Example Accepted ID')
        ->assertSee('Original + Photocopy')->assertSee('Front and back.')->assertSee('Printed photo');
    $this->get($this->groupsUrl.'/'.$group->id.'/edit')->assertOk()->assertSee('Edit Requirement Group');
    expect(\Illuminate\Support\Arr::except($this->getJson('/api/government-ids/'.$this->targetId->id)->assertOk()->json('data'), ['requirement_sets']))->toBe($beforeDetail)
        ->and($this->getJson('/api/government-ids')->assertOk()->json())->toBe($beforeList);
});

it('supports choose one and preset or custom conditional groups', function () {
    $this->post($this->groupsUrl, requirementGroupPayload([
        'rule' => 'choose_one', 'condition_type' => 'spouse_surname',
        'items' => [requirementGroupRow(), requirementGroupRow(['custom_name' => 'Alternative photo'])],
    ]))->assertSessionHasNoErrors();
    $group = $this->set->groups()->sole();
    expect($group->rule)->toBe('choose_one')->and($group->condition_type)->toBe('spouse_surname');
    $this->get($this->groupsUrl)->assertOk()->assertSee('Choose 1')->assertSee('Only applies when:')->assertSee('Married applicant');
    $this->put($this->groupsUrl.'/'.$group->id, requirementGroupPayload([
        'condition_type' => 'custom', 'condition_custom' => 'Is a legal guardian accompanying you?',
    ]))->assertSessionHasNoErrors();
    $this->get($this->groupsUrl)->assertOk()->assertSee('Is a legal guardian accompanying you?');
});

it('rejects invalid groups and items without partial writes or audit entries', function (array $payload, string $error) {
    $before = ContentChangeLog::count();
    $this->post($this->groupsUrl, requirementGroupPayload($payload))->assertSessionHasErrors($error);
    expect(GovernmentIdRequirementGroup::count())->toBe(0)
        ->and(GovernmentIdRequirementItem::count())->toBe(0)
        ->and(ContentChangeLog::count())->toBe($before);
})->with([
    [['rule' => 'choose_two'], 'rule'],
    [['rule' => 'choose_one'], 'items'],
    [['items' => []], 'items'],
    [['items' => 'bad'], 'items'],
    [['items' => ['bad']], 'items.0'],
    [['condition_type' => 'unknown'], 'condition_type'],
    [['condition_type' => 'custom', 'condition_custom' => ' '], 'condition_custom'],
    [['items' => [requirementGroupRow(['type' => 'anything'])]], 'items.0.type'],
    [['items' => [requirementGroupRow(['type' => 'document'])]], 'items.0.document_id'],
    [['items' => [requirementGroupRow(['type' => 'document', 'document_id' => 999999])]], 'items.0.document_id'],
    [['items' => [requirementGroupRow(['type' => 'government_id'])]], 'items.0.government_id_id'],
    [['items' => [requirementGroupRow(['custom_name' => ' '])]], 'items.0.custom_name'],
    [['items' => [requirementGroupRow(['submission_format' => 'invalid'])]], 'items.0.submission_format'],
    [['items' => [requirementGroupRow(['submission_format' => 'custom'])]], 'items.0.submission_format_custom'],
    [['items' => [requirementGroupRow(['submission_format' => 'photocopy', 'copies' => 0])]], 'items.0.copies'],
    [['items' => [requirementGroupRow(['submission_format' => 'photocopy', 'copies' => 1.5])]], 'items.0.copies'],
    [['items' => [requirementGroupRow(['id' => 999999])]], 'items.0.id'],
    [['items' => [requirementGroupRow(['sort_order' => 999])]], 'items.0'],
    [['requirement_set_id' => 999999], 'requirement_set_id'],
]);

it('preserves item identities while editing removing and reordering rows', function () {
    $document = Document::create(['name' => 'Supporting Document']);
    $this->post($this->groupsUrl, requirementGroupPayload([
        'condition_type' => 'custom', 'condition_custom' => 'Does this apply?',
        'items' => [
            requirementGroupRow(['type' => 'document', 'document_id' => $document->id]),
            requirementGroupRow(['custom_name' => 'Second item']),
            requirementGroupRow(['custom_name' => 'Third item']),
        ],
    ]))->assertSessionHasNoErrors();
    $group = $this->set->groups()->sole();
    [$first, $second, $third] = $group->items->all();
    $this->put($this->groupsUrl.'/'.$group->id, requirementGroupPayload([
        'items' => [
            requirementGroupRow(['id' => $third->id, 'custom_name' => 'Third edited']),
            requirementGroupRow(['id' => $second->id, 'custom_name' => 'Second item']),
        ],
    ]))->assertSessionHasNoErrors();
    expect($group->fresh()->condition_custom)->toBeNull()
        ->and($group->items()->pluck('id')->all())->toBe([$third->id, $second->id]);
    $this->assertModelMissing($first);
    $this->assertModelExists($document);
});

it('clears obsolete references and copy details when selections change', function () {
    $id = GovernmentId::create(['name' => 'Accepted ID']);
    $this->post($this->groupsUrl, requirementGroupPayload([
        'items' => [requirementGroupRow(['type' => 'government_id', 'government_id_id' => $id->id, 'submission_format' => 'custom', 'submission_format_custom' => 'Printed', 'copies' => 3])],
    ]))->assertSessionHasNoErrors();
    $group = $this->set->groups()->sole();
    $item = $group->items->sole();
    $this->put($this->groupsUrl.'/'.$group->id, requirementGroupPayload([
        'items' => [requirementGroupRow(['id' => $item->id, 'government_id_id' => $id->id, 'submission_format' => 'digital_copy', 'submission_format_custom' => 'Stale', 'copies' => 'stale'])],
    ]))->assertSessionHasNoErrors();
    $item->refresh();
    expect($item->government_id_id)->toBeNull()->and($item->document_id)->toBeNull()
        ->and($item->submission_format_custom)->toBeNull()->and($item->copies)->toBeNull();
});

it('preserves removal intent and handles malformed old input safely', function () {
    $this->post($this->groupsUrl, requirementGroupPayload(['items' => [requirementGroupRow(['custom_name' => 'Removed row must stay removed'])]]))->assertSessionHasNoErrors();
    $group = $this->set->groups()->sole();
    $edit = $this->groupsUrl.'/'.$group->id.'/edit';
    $this->from($edit)->put($this->groupsUrl.'/'.$group->id, [
        'items_present' => 1, 'title' => ['bad'], 'rule' => 'all', 'condition_type' => 'always',
    ])->assertSessionHasErrors(['title', 'items']);
    $this->get($edit)->assertOk()->assertSee('Please check')->assertDontSee('Removed row must stay removed');
    expect($group->items()->count())->toBe(1);
    $this->from($edit)->put($this->groupsUrl.'/'.$group->id, [
        'items_present' => 1, 'rule' => ['bad'], 'condition_type' => ['bad'], 'items' => ['bad', ['type' => ['bad']]],
    ])->assertSessionHasErrors();
    $this->get($edit)->assertOk()->assertSee('Please check');
});

it('prevents cross-ID cross-set and cross-group writes', function () {
    $other = GovernmentId::create(['name' => 'Other ID']);
    $otherSet = $other->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'all']);
    $otherGroup = $otherSet->groups()->create(['rule' => 'all']);
    $otherItem = $otherGroup->items()->create(requirementGroupRow());
    $wrongSet = '/admin/government-ids/'.$this->targetId->id.'/requirement-sets/'.$otherSet->id.'/groups';
    $this->get($wrongSet)->assertNotFound();
    $this->get($wrongSet.'/create')->assertNotFound();
    $this->post($wrongSet, requirementGroupPayload())->assertNotFound();
    $this->get($this->groupsUrl.'/'.$otherGroup->id.'/edit')->assertNotFound();
    $this->put($this->groupsUrl.'/'.$otherGroup->id, requirementGroupPayload())->assertNotFound();
    $this->delete($this->groupsUrl.'/'.$otherGroup->id)->assertNotFound();
    $this->post($this->groupsUrl, requirementGroupPayload())->assertSessionHasNoErrors();
    $group = $this->set->groups()->sole();
    $this->put($this->groupsUrl.'/'.$group->id, requirementGroupPayload([
        'items' => [requirementGroupRow(['id' => $otherItem->id])],
    ]))->assertSessionHasErrors('items.0.id');
    $this->assertModelExists($otherItem);
});

it('rejects duplicate item row IDs on update', function () {
    $this->post($this->groupsUrl, requirementGroupPayload())->assertSessionHasNoErrors();
    $group = $this->set->groups()->sole();
    $item = $group->items->sole();
    $row = requirementGroupRow(['id' => $item->id]);
    $this->put($this->groupsUrl.'/'.$group->id, requirementGroupPayload(['items' => [$row, $row]]))
        ->assertSessionHasErrors('items.0.id');
    expect($group->items()->count())->toBe(1);
});

it('audits meaningful changes and leaves no-op saves out of edit history', function () {
    $before = ContentChangeLog::count();
    $this->post($this->groupsUrl, requirementGroupPayload())->assertSessionHasNoErrors();
    $group = $this->set->groups()->sole();
    $item = $group->items->sole();
    expect(ContentChangeLog::count())->toBe($before + 1);
    $this->put($this->groupsUrl.'/'.$group->id, requirementGroupPayload([
        'items' => [requirementGroupRow(['id' => $item->id])],
    ]))->assertSessionHasNoErrors();
    expect(ContentChangeLog::count())->toBe($before + 1);
    $this->put($this->groupsUrl.'/'.$group->id, requirementGroupPayload([
        'items' => [requirementGroupRow(['id' => $item->id, 'instructions' => 'New instruction'])],
    ]))->assertSessionHasNoErrors();
    expect(ContentChangeLog::count())->toBe($before + 2)
        ->and(ContentChangeLog::latest('id')->first()->changed_fields)->toBe(['requirement_groups']);
    $this->get('/admin/government-ids/'.$this->targetId->id)->assertOk()->assertSee('Checklist Researcher')->assertSee('Last edited by');
    $this->delete($this->groupsUrl.'/'.$group->id)->assertRedirect($this->groupsUrl);
    $this->assertModelMissing($group);
    $this->assertModelMissing($item);
    expect(ContentChangeLog::count())->toBe($before + 3);
});

it('protects referenced IDs and Documents from CMS and direct database deletion', function () {
    $document = Document::create(['name' => 'Referenced Document']);
    $accepted = GovernmentId::create(['name' => 'Referenced ID']);
    $this->post($this->groupsUrl, requirementGroupPayload([
        'items' => [
            requirementGroupRow(['type' => 'document', 'document_id' => $document->id]),
            requirementGroupRow(['type' => 'government_id', 'government_id_id' => $accepted->id]),
        ],
    ]))->assertSessionHasNoErrors();
    $this->delete('/admin/documents/'.$document->id)->assertRedirect('/admin/documents')->assertSessionHas('error');
    $this->get('/admin/documents')->assertOk()->assertSee('used in requirement items');
    $this->delete('/admin/government-ids/'.$accepted->id)->assertRedirect('/admin/government-ids/'.$accepted->id)->assertSessionHas('error');
    $this->get('/admin/government-ids/'.$accepted->id)->assertOk()->assertSee('used in requirement items');
    expect(fn () => $document->delete())->toThrow(QueryException::class);
    expect(fn () => $accepted->delete())->toThrow(QueryException::class);
    $group = $this->set->groups()->sole();
    $this->delete($this->groupsUrl.'/'.$group->id)->assertRedirect($this->groupsUrl);
    $this->assertModelExists($document);
    $this->assertModelExists($accepted);
    $this->delete('/admin/documents/'.$document->id)->assertSessionHas('success');
    $this->delete('/admin/government-ids/'.$accepted->id)->assertSessionHas('success');
});

it('allows the same ID as a renewal item without evaluating a dependency', function () {
    $this->set->update(['application_type' => 'renewal']);
    $this->post($this->groupsUrl, requirementGroupPayload([
        'items' => [requirementGroupRow(['type' => 'government_id', 'government_id_id' => $this->targetId->id])],
    ]))->assertSessionHasNoErrors();
    $this->get($this->groupsUrl)->assertOk()->assertSee('Target ID');
    $this->delete('/admin/government-ids/'.$this->targetId->id)->assertSessionHas('error');
    $this->assertModelExists($this->targetId);
});

it('cascades set and parent deletion without deleting selected documents', function () {
    $document = Document::create(['name' => 'Keep me']);
    foreach (range(1, 2) as $i) {
        $this->post($this->groupsUrl, requirementGroupPayload([
            'items' => [requirementGroupRow(['type' => 'document', 'document_id' => $document->id])],
        ]))->assertSessionHasNoErrors();
    }
    $this->delete('/admin/government-ids/'.$this->targetId->id.'/requirement-sets/'.$this->set->id)->assertSessionHas('success');
    expect(GovernmentIdRequirementGroup::count())->toBe(0)->and(GovernmentIdRequirementItem::count())->toBe(0);
    $this->assertModelExists($document);
    $set = $this->targetId->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'all']);
    $group = $set->groups()->create(['rule' => 'all']);
    $group->items()->create(requirementGroupRow());
    $this->delete('/admin/government-ids/'.$this->targetId->id)->assertSessionHas('success');
    expect(GovernmentIdRequirementGroup::count())->toBe(0)->and(GovernmentIdRequirementItem::count())->toBe(0);
});

it('protects all group actions from guests residents and inactive researchers', function () {
    $group = $this->set->groups()->create(['rule' => 'all']);
    $actions = [
        ['GET', $this->groupsUrl], ['GET', $this->groupsUrl.'/create'],
        ['GET', $this->groupsUrl.'/'.$group->id.'/edit'], ['POST', $this->groupsUrl],
        ['PUT', $this->groupsUrl.'/'.$group->id], ['DELETE', $this->groupsUrl.'/'.$group->id],
    ];
    auth()->logout();
    foreach ($actions as [$method, $url]) {
        $this->call($method, $url)->assertRedirect('/login');
    }
    foreach ([['role' => 'resident'], ['role' => 'researcher', 'is_active' => false]] as $attributes) {
        $this->actingAs(User::factory()->create($attributes));
        foreach ($actions as [$method, $url]) {
            $this->call($method, $url)->assertForbidden();
        }
    }
    $this->assertModelExists($group);
});

it('rolls back group item and audit changes when persistence fails', function () {
    $this->post($this->groupsUrl, requirementGroupPayload())->assertSessionHasNoErrors();
    $group = $this->set->groups()->sole();
    $item = $group->items->sole();
    $before = ContentChangeLog::count();
    $this->withoutExceptionHandling();
    $dispatcher = ContentChangeLog::getEventDispatcher();
    ContentChangeLog::setEventDispatcher(clone $dispatcher);
    ContentChangeLog::creating(function () { throw new RuntimeException('Audit failure'); });
    try {
        expect(fn () => $this->put($this->groupsUrl.'/'.$group->id, requirementGroupPayload([
            'title' => 'Must roll back',
            'items' => [requirementGroupRow(['id' => $item->id, 'custom_name' => 'Changed']), requirementGroupRow()],
        ])))->toThrow(RuntimeException::class, 'Audit failure');
        expect($group->fresh()->title)->toBeNull()
            ->and($item->fresh()->custom_name)->toBe('Passport-size photo')
            ->and($group->items()->count())->toBe(1)
            ->and(ContentChangeLog::count())->toBe($before);
    } finally {
        ContentChangeLog::setEventDispatcher($dispatcher);
    }
});


it('redirects advanced requirements to Edit and rejects older editor overwrites', function () {
    $group = $this->set->groups()->create(['title' => 'Proof of identity', 'rule' => 'all']);
    $way = $group->ways()->create(['required_count' => 1]);
    $item = $group->items()->make(requirementGroupRow());
    $item->requirement_way_id = $way->id;
    $item->save();
    $this->get($this->groupsUrl)->assertRedirect('/admin/government-ids/'.$this->targetId->id.'/edit');
    $this->get($this->groupsUrl.'/'.$group->id.'/edit')
        ->assertRedirect('/admin/government-ids/'.$this->targetId->id.'/edit');
    $this->putJson($this->groupsUrl.'/'.$group->id, requirementGroupPayload())
        ->assertUnprocessable()->assertJsonValidationErrors('items');
    expect($group->fresh()->title)->toBe('Proof of identity')
        ->and($group->items()->sole()->id)->toBe($item->id);
    $this->assertModelExists($way);
});
