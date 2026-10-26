<?php

use App\Models\ContentChangeLog;
use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\GovernmentIdRequirementGroup;
use App\Models\GovernmentIdRequirementItem;
use App\Models\GovernmentIdRequirementSet;
use App\Models\User;

function modalChecklistPayload(array $overrides = []): array
{
    return array_replace([
        'application_type' => 'new', 'applicant_type' => 'adult',
        'groups' => [[
            'rule' => 'all', 'condition_type' => 'always',
            'items' => [['type' => 'custom', 'custom_name' => 'Example photo', 'submission_format' => 'photocopy', 'copies' => 1]],
        ]],
    ], $overrides);
}

function modalChecklistEditable(array $checklist): array
{
    unset($checklist['id'], $checklist['label']);
    foreach ($checklist['groups'] as &$group) {
        unset($group['condition_label']);
        foreach ($group['items'] as &$item) {
            unset($item['name']);
        }
        unset($item);
    }
    unset($group);
    return $checklist;
}

beforeEach(function () {
    $this->actingAs(User::factory()->create(['role' => 'researcher', 'name' => 'Modal Editor']));
    $this->target = GovernmentId::create(['name' => 'Example ID', 'requirements' => 'Legacy guidance']);
    $this->url = '/admin/government-ids/'.$this->target->id.'/checklists';
});

it('renders editing controls on Edit and keeps View read only', function () {
    $this->get(route('admin.government-ids.edit-wizard.show', [$this->target, 'requirements']))->assertOk()
        ->assertSee('Inline requirements editor')->assertSee('Requirements draft preview')->assertSee('Continue →')
        ->assertDontSee('Save checklist')->assertDontSee('Who is applying?')->assertDontSee('checklist-dialog');
    $this->get('/admin/government-ids/'.$this->target->id)->assertOk()
        ->assertDontSee('Add checklist')->assertDontSee('checklist-dialog')->assertSee('Legacy guidance');
});

it('creates the complete checklist atomically and preserves existing resident API fields', function () {
    $doc = Document::create(['name' => 'Selected Document']);
    $before = \Illuminate\Support\Arr::except($this->getJson('/api/government-ids/'.$this->target->id)->assertOk()->json('data'), ['requirement_sets']);
    $list = $this->getJson('/api/government-ids')->assertOk()->json();
    $payload = modalChecklistPayload();
    $payload['groups'][0]['items'][] = ['type' => 'document', 'document_id' => $doc->id, 'submission_format' => 'original_photocopy', 'copies' => 2, 'instructions' => 'Bring both.'];
    $result = $this->postJson($this->url, $payload)->assertCreated()
        ->assertJsonPath('checklist.label', 'Adult • First-Time Application')
        ->assertJsonPath('checklist.groups.0.items.1.name', 'Selected Document')
        ->assertJsonPath('history.editor', 'Modal Editor');
    $set = $this->target->requirementSets()->sole();
    expect($set->groups->sole()->items)->toHaveCount(2);
    expect(\Illuminate\Support\Arr::except($this->getJson('/api/government-ids/'.$this->target->id)->assertOk()->json('data'), ['requirement_sets']))->toBe($before)
        ->and($this->getJson('/api/government-ids')->assertOk()->json())->toBe($list);
    $this->get('/admin/government-ids/'.$this->target->id)->assertOk()->assertSee('Selected Document');
});

it('supplies empty and saved scenarios to the editor and reloads unchanged identities', function () {
    $editUrl = route('admin.government-ids.edit-wizard.show', [$this->target, 'requirements']);
    $this->get($editUrl)->assertOk()->assertViewHas('checklists', [])
        ->assertSee('No requirements added yet.')->assertSee('openEmptyWizard()', false);
    $saved = $this->postJson($this->url, modalChecklistPayload())->assertCreated()->json('checklist');
    $this->get($editUrl)->assertOk()->assertViewHas('checklists', fn ($rows) => $rows === [$saved]);
    $this->putJson($this->url.'/'.$saved['id'], modalChecklistEditable($saved))
        ->assertOk()->assertJsonPath('checklist', $saved);
    $this->get($editUrl)->assertOk()->assertViewHas('checklists', fn ($rows) => $rows === [$saved]);
    expect($this->target->requirementSets()->count())->toBe(1);
});

it('preserves existing multi-item group semantics and IDs on unchanged modal saves', function () {
    $payload = modalChecklistPayload();
    $payload['groups'][0]['title'] = 'Both required';
    $payload['groups'][0]['condition_type'] = 'custom';
    $payload['groups'][0]['condition_custom'] = 'Does this apply?';
    $payload['groups'][0]['items'][] = ['type' => 'government_id', 'government_id_id' => $this->target->id, 'submission_format' => 'original'];
    $saved = $this->postJson($this->url, $payload)->assertCreated()->json('checklist');
    $before = ContentChangeLog::count();
    $this->putJson($this->url.'/'.$saved['id'], modalChecklistEditable($saved))->assertOk()->assertJsonPath('checklist', $saved);
    expect(ContentChangeLog::count())->toBe($before);
});

it('updates a checklist with alternatives and removes only omitted rows', function () {
    $saved = $this->postJson($this->url, modalChecklistPayload())->assertCreated()->json('checklist');
    $payload = modalChecklistEditable($saved);
    $payload['application_type'] = 'renewal';
    $payload['groups'][0]['rule'] = 'choose_one';
    $payload['groups'][0]['items'][] = ['type' => 'custom', 'custom_name' => 'Another option', 'submission_format' => 'digital_copy'];
    $edited = $this->putJson($this->url.'/'.$saved['id'], $payload)->assertOk()
        ->assertJsonPath('checklist.groups.0.rule', 'choose_one')->json('checklist');
    expect($edited['groups'][0]['items'][0]['id'])->toBe($saved['groups'][0]['items'][0]['id']);
    $payload = modalChecklistEditable($edited);
    $removed = array_shift($payload['groups'][0]['items']);
    $payload['groups'][0]['rule'] = 'all';
    $this->putJson($this->url.'/'.$saved['id'], $payload)->assertOk();
    $this->assertDatabaseMissing('government_id_requirement_items', ['id' => $removed['id']]);
    expect($this->target->requirementSets()->count())->toBe(1);
});

it('requires an explicit groups field and allows deliberate clearing', function () {
    $saved = $this->postJson($this->url, modalChecklistPayload())->assertCreated()->json('checklist');
    $this->putJson($this->url.'/'.$saved['id'], ['application_type' => 'new', 'applicant_type' => 'adult'])
        ->assertUnprocessable()->assertJsonValidationErrors('groups');
    expect(GovernmentIdRequirementItem::count())->toBe(1);
    $this->putJson($this->url.'/'.$saved['id'], modalChecklistPayload(['groups' => []]))->assertOk();
    expect(GovernmentIdRequirementGroup::count())->toBe(0)->and(GovernmentIdRequirementItem::count())->toBe(0);
});

it('rejects invalid nested data without any partial changes', function () {
    $saved = $this->postJson($this->url, modalChecklistPayload())->assertCreated()->json('checklist');
    $before = ContentChangeLog::count();
    $payload = modalChecklistEditable($saved);
    $payload['application_type'] = 'renewal';
    $payload['groups'][0]['rule'] = 'choose_one';
    $this->putJson($this->url.'/'.$saved['id'], $payload)->assertUnprocessable()->assertJsonValidationErrors('groups.0.items');
    $payload['groups'][0]['rule'] = 'all';
    $payload['groups'][0]['items'][0]['copies'] = 0;
    $this->putJson($this->url.'/'.$saved['id'], $payload)->assertUnprocessable()->assertJsonValidationErrors('groups.0.items.0.copies');
    expect(GovernmentIdRequirementSet::find($saved['id'])->application_type)->toBe('new')
        ->and(ContentChangeLog::count())->toBe($before);
});

it('rejects missing directory references and malformed nested inputs safely', function () {
    $payload = modalChecklistPayload();
    $payload['groups'][0]['items'] = [['type' => 'document', 'submission_format' => 'original']];
    $this->postJson($this->url, $payload)->assertUnprocessable()->assertJsonValidationErrors('groups.0.items.0.document_id');
    $payload['groups'][0]['items'][0]['document_id'] = 999999;
    $this->postJson($this->url, $payload)->assertUnprocessable();
    foreach (['bad', ['bad'], [['items' => 'bad']]] as $groups) {
        $this->postJson($this->url, modalChecklistPayload(['groups' => $groups]))->assertUnprocessable();
    }
    expect(GovernmentIdRequirementSet::count())->toBe(0);
});

it('blocks adoption of other checklists groups and items with full rollback', function () {
    $first = $this->postJson($this->url, modalChecklistPayload())->assertCreated()->json('checklist');
    $second = $this->postJson($this->url, modalChecklistPayload())->assertCreated()->json('checklist');
    $payload = modalChecklistEditable($first);
    $payload['application_type'] = 'renewal';
    $payload['groups'][0]['id'] = $second['groups'][0]['id'];
    $this->putJson($this->url.'/'.$first['id'], $payload)->assertNotFound();
    $payload['groups'][0]['id'] = $first['groups'][0]['id'];
    $payload['groups'][0]['items'][0]['id'] = $second['groups'][0]['items'][0]['id'];
    $this->putJson($this->url.'/'.$first['id'], $payload)->assertNotFound();
    $this->postJson($this->url, modalChecklistEditable($first))->assertNotFound();
    expect(GovernmentIdRequirementSet::count())->toBe(2)
        ->and(GovernmentIdRequirementSet::find($first['id'])->application_type)->toBe('new');
    $other = GovernmentId::create(['name' => 'Other ID']);
    $wrong = '/admin/government-ids/'.$other->id.'/checklists/'.$first['id'];
    $this->putJson($wrong, modalChecklistPayload())->assertNotFound();
    $this->deleteJson($wrong)->assertNotFound();
});

it('deletes a checklist without deleting its directory references', function () {
    $doc = Document::create(['name' => 'Keep this']);
    $payload = modalChecklistPayload();
    $payload['groups'][0]['items'] = [['type' => 'document', 'document_id' => $doc->id, 'submission_format' => 'original']];
    $saved = $this->postJson($this->url, $payload)->assertCreated()->json('checklist');
    $this->deleteJson($this->url.'/'.$saved['id'])->assertOk()->assertJsonPath('history.editor', 'Modal Editor');
    expect(GovernmentIdRequirementGroup::count())->toBe(0)->and(GovernmentIdRequirementItem::count())->toBe(0);
    $this->assertModelExists($doc);
    $this->assertModelExists($this->target);
});

it('protects checklist mutations from guests residents and inactive researchers', function () {
    $saved = $this->postJson($this->url, modalChecklistPayload())->assertCreated()->json('checklist');
    auth()->logout();
    $this->postJson($this->url, modalChecklistPayload())->assertUnauthorized();
    $this->putJson($this->url.'/'.$saved['id'], modalChecklistPayload())->assertUnauthorized();
    $this->deleteJson($this->url.'/'.$saved['id'])->assertUnauthorized();
    foreach ([['role' => 'resident'], ['role' => 'researcher', 'is_active' => false]] as $attributes) {
        $this->actingAs(User::factory()->create($attributes));
        $this->postJson($this->url, modalChecklistPayload())->assertForbidden();
        $this->putJson($this->url.'/'.$saved['id'], modalChecklistPayload())->assertForbidden();
        $this->deleteJson($this->url.'/'.$saved['id'])->assertForbidden();
    }
    expect(GovernmentIdRequirementSet::count())->toBe(1);
});

it('rolls back all nested writes when audit logging fails', function () {
    $this->withoutExceptionHandling();
    $dispatcher = ContentChangeLog::getEventDispatcher();
    ContentChangeLog::setEventDispatcher(clone $dispatcher);
    ContentChangeLog::creating(function () { throw new RuntimeException('Audit failure'); });
    try {
        expect(fn () => $this->postJson($this->url, modalChecklistPayload()))->toThrow(RuntimeException::class, 'Audit failure');
        expect(GovernmentIdRequirementSet::count())->toBe(0)
            ->and(GovernmentIdRequirementGroup::count())->toBe(0)
            ->and(GovernmentIdRequirementItem::count())->toBe(0);
    } finally {
        ContentChangeLog::setEventDispatcher($dispatcher);
    }
});


it('shares the inline requirements editor with Documents and preserves read only views', function () {
    $key = $this->get(route('admin.government-ids.create'))->assertOk()->viewData('creationKey');
    $this->post(route('admin.government-ids.wizard.store'), [
        'creation_key' => $key, 'name' => 'Wizard requirements ID',
    ])->assertSessionHasNoErrors();
    $wizardId = GovernmentId::where('name', 'Wizard requirements ID')->sole();
    $set = $wizardId->requirementSets()->sole();
    expect($set->application_type)->toBe('new')->and($set->applicant_type)->toBe('all')
        ->and($set->min_age)->toBeNull()->and($set->max_age)->toBeNull();

    $this->get(route('admin.government-ids.wizard.show', [$wizardId, 'requirements']))->assertOk()
        ->assertViewHas('checklists', fn ($rows) => count($rows) === 1 && $rows[0]['id'] === $set->id && $rows[0]['groups'] === [])
        ->assertSee('Inline requirements editor')->assertSee('Requirements draft preview')->assertSee('Requirement cards')->assertSee('xl:grid-cols-2')
        ->assertSee('if (checklists.length) open(checklists[0])', false)
        ->assertSee('+ Add requirement')->assertSee('editRequirement(groupIndex)', false)
        ->assertDontSee('Save Requirements')->assertSee('Continue →')->assertDontSee('checklist-dialog')
        ->assertDontSee('Applicant Cases')->assertDontSee('Application Types')
        ->assertDontSee('Who is applying?')->assertDontSee('What kind of application?')
        ->assertDontSee('Choose an existing checklist')->assertDontSee('+ Add checklist')
        ->assertDontSee('Edit applicant and application details')
        ->assertDontSee('x-model="draft.applicant_type"', false)
        ->assertDontSee('x-model="draft.application_type"', false)
        ->assertDontSee('<option value="adult">', false)->assertDontSee('<option value="minor">', false)
        ->assertDontSee('<option value="renewal">', false)->assertDontSee('<option value="replacement">', false);

    $document = Document::create(['name' => 'Example certificate']);
    foreach ([route('admin.government-ids.edit-wizard.show', [$wizardId, 'requirements']), route('admin.documents.edit-wizard.show', [$document, 'requirements'])] as $url) {
        $this->get($url)->assertOk()->assertSee('Inline requirements editor')
            ->assertSee('Requirements draft preview')->assertSee('Continue →')
            ->assertDontSee('Who is applying?')->assertDontSee('+ Add checklist')->assertDontSee('checklist-dialog');
    }
    $this->get('/admin/documents/create')->assertOk()->assertSee('Document creation steps')
        ->assertDontSee('Inline requirements editor');
    $this->get('/admin/documents/'.$document->id)->assertOk()->assertDontSee('Inline requirements editor');
});
