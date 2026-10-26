<?php

use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\Office;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['role' => 'researcher']));
});

function editWizardRoute(string $resource, $record, string $step, string $action = 'show'): string
{
    return route('admin.'.$resource.'.edit-wizard.'.$action, [$record, $step]);
}

dataset('catalog entities', [
    ['government-ids', GovernmentId::class, 'Government ID'],
    ['documents', Document::class, 'Document'],
]);

it('edits all saved sections on record 55 without replacing identities or unrelated research', function ($resource, $model, $label) {
    $record = $model::forceCreate([
        'id' => 55, 'name' => $model === GovernmentId::class ? 'Philippine Passport' : 'PSA Birth Certificate',
        'description' => 'Saved description', 'purpose' => 'Keep hidden research',
        'validity' => 'Legacy researched validity', 'processing_time' => 'Legacy processing estimate',
    ]);
    $referenceId = GovernmentId::create(['name' => 'Accepted identity card']);
    $referenceDocument = Document::create(['name' => 'Supporting certificate']);
    $checklist = ['application_type' => 'new', 'applicant_type' => 'all', 'groups' => [[
        'title' => 'Identity proof', 'rule' => 'all', 'condition_type' => 'custom', 'condition_custom' => 'When requested',
        'ways' => [['required_count' => 1, 'qualification_type' => 'unexpired', 'qualification_scope' => 'every', 'items' => [
            ['type' => 'government_id', 'government_id_id' => $referenceId->id, 'submission_format' => 'original'],
            ['type' => 'document', 'document_id' => $referenceDocument->id, 'submission_format' => 'photocopy', 'copies' => 2],
        ]]],
    ]]];
    $saved = $this->postJson(route('admin.'.$resource.'.checklists.store', $record), $checklist)->assertCreated()->json('checklist');
    $set = $record->requirementSets()->findOrFail($saved['id']);
    $legacy = $record->requirementSets()->create(['application_type' => 'renewal', 'applicant_type' => 'adult']);
    $legacyStep = $legacy->applicationSteps()->create(['title' => 'Retain renewal research']);
    $first = $set->applicationSteps()->create(['title' => 'Prepare', 'type' => 'general', 'sort_order' => 0]);
    $second = $set->applicationSteps()->create(['title' => 'Submit', 'type' => 'office_visit', 'sort_order' => 1]);
    $richText = ['type' => 'doc', 'content' => [['type' => 'paragraph']]];
    $block = $first->blocks()->create(['type' => 'requirements', 'content' => ['intro' => $richText], 'sort_order' => 0]);
    $fee = $record->fees()->create(['label' => 'Processing', 'type' => 'fixed', 'amount_min' => 50, 'sort_order' => 0]);
    $optional = $record->fees()->create(['label' => 'Delivery', 'type' => 'varies', 'is_optional' => true, 'sort_order' => 1]);
    $office = Office::create(['name' => 'Shared registry office']);
    $record->offices()->attach($office->id, ['new_application_status' => 'available']);
    $officeLinkId = $record->offices()->sole()->pivot->id;
    $countBefore = $model::count();
    $apiPath = '/api/government-ids/'.($model === GovernmentId::class ? $record->id : $referenceId->id);
    $apiBefore = $this->getJson($apiPath)->assertOk()->json();

    $this->get(route('admin.'.$resource.'.edit', $record))->assertOk()->assertSee('Edit '.$label)
        ->assertSee('Saved description')->assertSee($label.' editing steps')->assertDontSee('name="creation_key"', false);
    expect($this->getJson($apiPath)->assertOk()->json())->toBe($apiBefore);
    expect($model::count())->toBe($countBefore)->and($record->requirementSets()->count())->toBe(2);
    $this->post(editWizardRoute($resource, $record, 'basic', 'save'), ['name' => 'Updated '.$label, 'description' => 'Updated description'])
        ->assertSessionHasNoErrors()->assertRedirect(editWizardRoute($resource, $record, 'requirements'));
    expect($record->fresh()->id)->toBe(55)->and($record->fresh()->purpose)->toBe('Keep hidden research')
        ->and($record->fresh()->validity)->toBe('Legacy researched validity')
        ->and($record->fresh()->processing_time)->toBe('Legacy processing estimate');
    $requirementsPage = $this->get(editWizardRoute($resource, $record, 'requirements'))->assertOk()
        ->assertSee('Application checklist')->assertSee('Identity proof')->assertSee('Requirements draft preview');
    expect(array_column($requirementsPage->viewData('checklists'), 'id'))->toBe([$set->id, $legacy->id]);
    $checklist['groups'][0]['id'] = $saved['groups'][0]['id'];
    $checklist['groups'][0]['ways'][0]['id'] = $saved['groups'][0]['ways'][0]['id'];
    foreach ($saved['groups'][0]['ways'][0]['items'] as $index => $item) {
        $checklist['groups'][0]['ways'][0]['items'][$index]['id'] = $item['id'];
    }
    $checklist['groups'][0]['title'] = 'Updated identity proof';
    $updated = $this->putJson(route('admin.'.$resource.'.checklists.update', [$record, $set]), $checklist)->assertOk()->json('checklist');
    expect($updated['groups'][0]['id'])->toBe($saved['groups'][0]['id'])
        ->and($updated['groups'][0]['ways'][0]['id'])->toBe($saved['groups'][0]['ways'][0]['id'])
        ->and(array_column($updated['groups'][0]['ways'][0]['items'], 'id'))->toBe(array_column($saved['groups'][0]['ways'][0]['items'], 'id'));
    $this->post(editWizardRoute($resource, $record, 'requirements', 'save'))->assertRedirect(editWizardRoute($resource, $record, 'fees'));
    $fees = ['fees' => [
        ['id' => $optional->id, 'label' => 'Delivery', 'type' => 'range', 'amount_min' => 30, 'amount_max' => 60, 'is_optional' => true, 'notes' => 'Updated delivery'],
        ['id' => $fee->id, 'label' => 'Processing', 'type' => 'free'],
    ]];
    foreach ([1, 2] as $_) {
        $this->post(editWizardRoute($resource, $record, 'fees', 'save'), $fees)->assertSessionHasNoErrors()
            ->assertRedirect(editWizardRoute($resource, $record, 'guide'));
    }
    expect($record->fees()->orderBy('sort_order')->pluck('id')->all())->toBe([$optional->id, $fee->id])
        ->and($optional->fresh()->notes)->toBe('Updated delivery')->and($fee->fresh()->type)->toBe('free');
    $guide = [['id' => $set->id, 'application_type' => 'new', 'applicant_type' => 'all', 'steps' => [
        ['id' => $second->id, 'title' => 'Submit updated documents', 'type' => 'office_visit', 'blocks' => []],
        ['id' => $first->id, 'title' => 'Prepare updated documents', 'type' => 'general', 'short_description' => 'Keep references', 'blocks' => [
            ['id' => $block->id, 'type' => 'requirements', 'content' => ['intro' => $richText]],
        ]],
    ]]];
    $this->get(editWizardRoute($resource, $record, 'guide'))->assertOk()->assertSee('Retain renewal research')
        ->assertSee('App preview')->assertSee('Application checklist')->assertSee('Optional Link');
    $this->post(editWizardRoute($resource, $record, 'guide', 'save'), ['application_guide_payload' => json_encode($guide)])
        ->assertSessionHasNoErrors()->assertRedirect(editWizardRoute($resource, $record, 'offices'));
    expect($set->applicationSteps()->pluck('id')->all())->toBe([$second->id, $first->id])
        ->and($first->blocks()->sole()->id)->toBe($block->id)->and($legacyStep->fresh()->title)->toBe('Retain renewal research');
    $this->post(editWizardRoute($resource, $record, 'offices', 'save'), ['office_links_present' => 1, 'office_links' => [[
        'office_id' => $office->id, 'new_application_status' => 'available', 'renewal_status' => 'available',
        'replacement_status' => 'unknown', 'service_notes' => 'Updated service notes',
    ]]])->assertSessionHasNoErrors()->assertRedirect(editWizardRoute($resource, $record, 'review'));
    expect(Office::count())->toBe(1)->and($record->offices()->sole()->pivot->id)->toBe($officeLinkId);
    $this->get(editWizardRoute($resource, $record, 'review'))->assertOk()->assertSee('Review changes')
        ->assertSee('Finish Editing')->assertSee('1 group / 2 items')->assertSee('2 fees')->assertSee('3 steps')->assertSee('1 linked office');
    foreach (['basic' => 'Updated description', 'requirements' => 'Updated identity proof', 'fees' => 'Updated delivery',
        'guide' => 'Submit updated documents', 'offices' => 'Updated service notes'] as $step => $text) {
        $this->get(editWizardRoute($resource, $record, $step))->assertOk()->assertSee($text);
    }
    foreach ([1, 2] as $_) {
        $this->post(editWizardRoute($resource, $record, 'review', 'save'))->assertRedirect(route('admin.'.$resource.'.show', $record));
    }
    expect($model::count())->toBe($countBefore)->and($model::findOrFail(55)->name)->toBe('Updated '.$label)
        ->and($record->requirementSets()->count())->toBe(2)->and($record->fees()->count())->toBe(2);
    $this->getJson($apiPath)->assertOk();
})->with('catalog entities');

it('keeps errors on their step and rejects another records fees and guide rows atomically', function ($resource, $model) {
    $record = $model::create(['name' => 'Keep name']);
    $other = $model::create(['name' => 'Other record']);
    $ownFee = $record->fees()->create(['label' => 'Keep fee', 'type' => 'free']);
    $foreignFee = $other->fees()->create(['label' => 'Other fee', 'type' => 'free']);
    $basic = editWizardRoute($resource, $record, 'basic');
    $this->from($basic)->post(editWizardRoute($resource, $record, 'basic', 'save'), ['name' => '', 'description' => 'Retain entered text'])
        ->assertRedirect($basic)->assertSessionHasErrors('name')->assertSessionHasInput('description', 'Retain entered text');
    $this->get($basic)->assertOk()->assertSee('Retain entered text')->assertSee('Please check');
    $fees = editWizardRoute($resource, $record, 'fees');
    $this->from($fees)->post(editWizardRoute($resource, $record, 'fees', 'save'), ['fees' => [['label' => 'Incomplete fee', 'type' => 'fixed']]])
        ->assertRedirect($fees)->assertSessionHasErrors('fees.0.amount_min');
    $this->postJson(editWizardRoute($resource, $record, 'fees', 'save'), ['fees' => [['id' => $foreignFee->id, 'label' => 'Stolen', 'type' => 'free']]])
        ->assertUnprocessable();
    $foreignSet = $other->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'all']);
    $this->postJson(editWizardRoute($resource, $record, 'guide', 'save'), ['application_guide_payload' => json_encode([[
        'id' => $foreignSet->id, 'application_type' => 'new', 'applicant_type' => 'all', 'steps' => [],
    ]])])->assertUnprocessable();
    expect($record->fresh()->name)->toBe('Keep name')->and($record->fees()->sole()->id)->toBe($ownFee->id)
        ->and($foreignFee->fresh()->label)->toBe('Other fee');
    $this->get(editWizardRoute($resource, $record, 'missing'))->assertNotFound();
    $this->post(editWizardRoute($resource, $record, 'missing', 'save'))->assertNotFound();
})->with('catalog entities');

it('opens empty records without writes and creates a guide scenario only once on save', function ($resource, $model) {
    $record = $model::create(['name' => 'Empty existing entry']);
    foreach (['basic', 'requirements', 'fees', 'guide', 'offices', 'review'] as $step) {
        $this->get(editWizardRoute($resource, $record, $step))->assertOk();
    }
    expect($record->requirementSets()->count())->toBe(0)->and($model::count())->toBe(1);
    $payload = ['application_guide_payload' => json_encode([[
        'application_type' => 'new', 'applicant_type' => 'all',
        'steps' => [['title' => 'First saved guide', 'type' => 'general', 'blocks' => []]],
    ]])];
    foreach ([1, 2] as $_) {
        $this->post(editWizardRoute($resource, $record, 'guide', 'save'), $payload)->assertSessionHasNoErrors();
    }
    expect($record->requirementSets()->count())->toBe(1)
        ->and($record->requirementSets()->sole()->applicationSteps()->count())->toBe(1)->and($model::count())->toBe(1);
})->with('catalog entities');

it('protects Edit steps and keeps Add resume state separate', function ($resource, $model) {
    $record = $model::create(['name' => 'Protected entry']);
    session(['id_creation_resume' => ['id' => 123, 'step' => 'fees'], 'document_creation_resume' => ['id' => 456, 'step' => 'guide']]);
    $this->post(editWizardRoute($resource, $record, 'basic', 'save'), ['name' => 'Same record'])->assertSessionHasNoErrors();
    $this->post(editWizardRoute($resource, $record, 'review', 'save'))->assertRedirect();
    expect(session('id_creation_resume'))->toBe(['id' => 123, 'step' => 'fees'])
        ->and(session('document_creation_resume'))->toBe(['id' => 456, 'step' => 'guide']);
    auth()->logout();
    $this->get(editWizardRoute($resource, $record, 'basic'))->assertRedirect('/login');
    $this->postJson(editWizardRoute($resource, $record, 'basic', 'save'), [])->assertUnauthorized();
    foreach ([['role' => 'resident'], ['role' => 'researcher', 'is_active' => false]] as $attributes) {
        $this->actingAs(User::factory()->create($attributes));
        $this->get(editWizardRoute($resource, $record, 'review'))->assertForbidden();
        $this->postJson(editWizardRoute($resource, $record, 'review', 'save'), [])->assertForbidden();
    }
})->with('catalog entities');
