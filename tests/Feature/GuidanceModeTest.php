<?php

use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\User;
use App\Support\GuidanceMode;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['role' => 'researcher']));
});

dataset('guidance catalogs', [
    'government IDs' => [GovernmentId::class, 'government-ids', 'government_ids'],
    'documents' => [Document::class, 'documents', 'documents'],
]);

it('defaults omitted guidance mode in database inserts and models', function ($model, $resource, $table) {
    DB::table($table)->insert(['name' => 'Legacy-style insert']);
    expect($model::sole()->guidance_mode)->toBe('guided')
        ->and((new $model)->guidance_mode)->toBe('guided');
    $this->get(route('admin.'.$resource.'.edit', $model::sole()))->assertOk()
        ->assertSee('value="guided" selected', false);
})->with('guidance catalogs');

it('backfills existing rows without losing content and rolls back only the added metadata', function () {
    $migration = require database_path('migrations/2026_10_28_000200_add_guidance_mode_to_catalog_tables.php');
    $migration->down();
    foreach (['government_ids', 'documents'] as $table) {
        DB::table($table)->insert(['name' => 'Before migration', 'description' => 'Preserve research', 'application_availability' => 'discontinued']);
    }
    $migration->up();
    foreach (['government_ids', 'documents'] as $table) {
        $row = DB::table($table)->sole();
        expect($row->guidance_mode)->toBe('guided')->and($row->description)->toBe('Preserve research')
            ->and($row->application_availability)->toBe('discontinued');
    }
    $migration->down();
    foreach (['government_ids', 'documents'] as $table) {
        expect(DB::table($table)->sole()->description)->toBe('Preserve research')
            ->and(DB::table($table)->sole()->application_availability)->toBe('discontinued');
    }
    $migration->up();
});

it('creates and updates each supported value through normal forms and wizards', function ($model, $resource) {
    foreach (GuidanceMode::LABELS as $value => $label) {
        $this->post(route('admin.'.$resource.'.store'), ['name' => 'Direct '.$label, 'guidance_mode' => $value, 'application_availability' => 'limited_conditional'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $record = $model::where('name', 'Direct '.$label)->sole();
        expect($record->guidance_mode)->toBe($value);
        $this->put(route('admin.'.$resource.'.update', $record), ['name' => $record->name, 'guidance_mode' => 'guided'])
            ->assertSessionHasNoErrors();
        expect($record->fresh()->guidance_mode)->toBe('guided');

        $key = $this->get(route('admin.'.$resource.'.create'))->assertOk()
            ->assertSee('Guidance Mode')->assertSee($label)->viewData('creationKey');
        $this->post(route('admin.'.$resource.'.wizard.store'), [
            'creation_key' => $key, 'name' => 'Wizard '.$label, 'guidance_mode' => $value,
            'application_availability' => 'limited_conditional',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $record = $model::where('name', 'Wizard '.$label)->sole();
        expect($record->guidance_mode)->toBe($value);
        $this->get(route('admin.'.$resource.'.edit-wizard.show', [$record, 'basic']))
            ->assertOk()->assertSee('value="'.$value.'" selected', false);
        foreach (['wizard', 'edit-wizard'] as $mode) {
            $this->get(route('admin.'.$resource.'.'.$mode.'.show', [$record, 'review']))
                ->assertOk()->assertSee('Guidance Mode: '.$label)->assertDontSee('informational_only');
        }
        $this->get(route('admin.'.$resource.'.show', $record))->assertOk()
            ->assertSee('Guidance Mode')->assertSee($label)->assertDontSee('informational_only');
        $this->post(route('admin.'.$resource.'.edit-wizard.save', [$record, 'basic']), ['name' => $record->name])
            ->assertSessionHasNoErrors();
        expect($record->fresh()->guidance_mode)->toBe($value);
        $next = $value === 'guided' ? 'informational_only' : 'guided';
        $this->post(route('admin.'.$resource.'.edit-wizard.save', [$record, 'basic']), [
            'name' => $record->name, 'guidance_mode' => $next,
        ])->assertSessionHasNoErrors();
        expect($record->fresh()->guidance_mode)->toBe($next)
            ->and($record->fresh()->application_availability)->toBe('limited_conditional');
        $this->post(route('admin.'.$resource.'.edit-wizard.save', [$record, 'fees']), ['fees' => []])
            ->assertSessionHasNoErrors();
        expect($record->fresh()->guidance_mode)->toBe($next)
            ->and($record->fresh()->application_availability)->toBe('limited_conditional');
        $this->get(route('admin.'.$resource.'.edit-wizard.show', [$record, 'basic']))
            ->assertOk()->assertSee('value="'.$next.'" selected', false);
    }
})->with('guidance catalogs');

it('rejects invalid guidance mode in full forms and both wizard flows', function ($model, $resource) {
    $record = $model::create(['name' => 'Keep value', 'guidance_mode' => 'informational_only']);
    foreach (['reference_only', 'unknown', '', null, ['guided']] as $invalid) {
        $data = ['name' => 'Keep value', 'guidance_mode' => $invalid];
        $this->postJson(route('admin.'.$resource.'.store'), $data)->assertUnprocessable()->assertJsonValidationErrors('guidance_mode');
        $this->putJson(route('admin.'.$resource.'.update', $record), $data)->assertUnprocessable()->assertJsonValidationErrors('guidance_mode');
        $key = $this->get(route('admin.'.$resource.'.create'))->viewData('creationKey');
        $this->postJson(route('admin.'.$resource.'.wizard.store'), $data + ['creation_key' => $key])
            ->assertUnprocessable()->assertJsonValidationErrors('guidance_mode');
        foreach (['wizard', 'edit-wizard'] as $mode) {
            $this->postJson(route('admin.'.$resource.'.'.$mode.'.save', [$record, 'basic']), $data)
                ->assertUnprocessable()->assertJsonValidationErrors('guidance_mode');
        }
    }
    expect($record->fresh()->guidance_mode)->toBe('informational_only')->and($model::count())->toBe(1);
})->with('guidance catalogs');

it('exposes guidance mode without filtering public and resident catalogs', function () {
    $ids = [];
    foreach (array_keys(GuidanceMode::LABELS) as $value) {
        $ids[] = GovernmentId::create(['name' => $value, 'guidance_mode' => $value, 'application_availability' => 'limited_conditional']);
        Document::create(['name' => $value, 'guidance_mode' => $value, 'application_availability' => 'limited_conditional']);
    }
    $this->getJson('/api/government-ids')->assertOk()->assertJsonCount(2, 'data');
    foreach ($ids as $id) {
        $this->getJson('/api/government-ids/'.$id->id)->assertOk()
            ->assertJsonPath('data.guidance_mode', $id->guidance_mode);
    }
    $resident = User::factory()->create(['role' => 'resident', 'is_active' => true]);
    $data = $this->actingAs($resident)->getJson('/api/auth/inventory')->assertOk()
        ->assertJsonCount(2, 'data.government_ids')->assertJsonCount(2, 'data.documents')->json('data');
    foreach (['government_ids', 'documents'] as $key) {
        expect(array_column($data[$key], 'guidance_mode'))->toEqualCanonicalizing(array_keys(GuidanceMode::LABELS));
    }
    $public = $this->getJson('/api/government-ids')->json('data');
    expect(array_column($public, 'guidance_mode'))->toEqualCanonicalizing(array_keys(GuidanceMode::LABELS));
});

it('changes classification without modifying metadata or structured acquisition content', function ($model, $resource) {
    $owner = $model::create([
        'name' => 'Complete catalog record', 'application_availability' => 'limited_conditional',
        'acquisition_type' => 'profession_based', 'target_group' => 'Maritime professionals',
        'description' => 'Preserve research', 'requirements' => 'Legacy submission', 'fee' => 'Legacy fee',
        'application_process' => 'Legacy instructions',
    ]);
    $set = $owner->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'all']);
    $group = $set->groups()->create(['title' => 'Submission', 'rule' => 'all', 'condition_type' => 'always']);
    $way = $group->ways()->create(['required_count' => 1, 'qualification_type' => 'none', 'qualification_scope' => 'every']);
    $group->items()->create(['requirement_way_id' => $way->id, 'type' => 'custom', 'custom_name' => 'Original certificate', 'submission_format' => 'original']);
    $fee = $owner->fees()->create(['label' => 'Issuance', 'type' => 'options', 'currency' => 'PHP']);
    $fee->options()->create(['label' => 'Regular', 'type' => 'fixed', 'amount_min' => 50, 'currency' => 'PHP']);
    $step = $set->applicationSteps()->create(['title' => 'Visit office', 'type' => 'office_visit']);
    $step->blocks()->create(['type' => 'reminder', 'content' => ['body' => ['type' => 'doc', 'content' => [['type' => 'paragraph']]]]]);
    $office = \App\Models\Office::create(['name' => 'Keep branch']);
    $owner->offices()->attach($office->id, ['new_application_status' => 'available', 'renewal_status' => 'unknown', 'replacement_status' => 'unknown']);
    app(\App\Services\CatalogPrerequisites::class)->save($owner, [['type' => 'custom', 'custom_name' => 'Complete training', 'condition' => 'If required']]);
    $snapshot = function () use ($owner) {
        $fresh = $owner->fresh(['prerequisites', 'requirementSets.groups.items', 'requirementSets.groups.ways', 'requirementSets.applicationSteps.blocks', 'fees.options', 'offices']);
        return [
            $fresh->only(['application_availability', 'acquisition_type', 'target_group', 'description', 'requirements', 'fee', 'application_process']),
            $fresh->getRelations(),
        ];
    };
    $before = serialize($snapshot());
    foreach (['informational_only', 'guided'] as $mode) {
        $this->post(route('admin.'.$resource.'.edit-wizard.save', [$owner, 'basic']), ['name' => $owner->name, 'guidance_mode' => $mode])
            ->assertSessionHasNoErrors();
        expect($owner->fresh()->guidance_mode)->toBe($mode)->and(serialize($snapshot()))->toBe($before);
    }
})->with('guidance catalogs');

it('keeps informational records linkable as requirements prerequisites and inventory items', function () {
    $id = GovernmentId::create(['name' => 'Reference ID', 'guidance_mode' => 'informational_only']);
    $document = Document::create(['name' => 'Reference document', 'guidance_mode' => 'informational_only']);
    $owner = GovernmentId::create(['name' => 'Guided owner']);
    $this->get(route('admin.government-ids.edit-wizard.show', [$owner, 'requirements']))->assertOk()
        ->assertSee('Reference ID')->assertSee('Reference document');
    $payload = ['application_type' => 'new', 'applicant_type' => 'all', 'groups' => [[
        'title' => 'Accepted references', 'rule' => 'all', 'condition_type' => 'always',
        'ways' => [['required_count' => 2, 'qualification_type' => 'none', 'qualification_scope' => 'every', 'items' => [
            ['type' => 'government_id', 'government_id_id' => $id->id, 'submission_format' => 'original'],
            ['type' => 'document', 'document_id' => $document->id, 'submission_format' => 'original'],
        ]]],
    ]]];
    $this->postJson(route('admin.government-ids.checklists.store', $owner), $payload)->assertCreated();
    app(\App\Services\CatalogPrerequisites::class)->save($owner, [
        ['type' => 'government_id', 'government_id_id' => $id->id], ['type' => 'document', 'document_id' => $document->id],
    ]);
    expect($owner->requirementSets()->sole()->groups()->sole()->items()->count())->toBe(2)
        ->and($owner->fresh()->prerequisites)->toHaveCount(2);
    $resident = User::factory()->create(['role' => 'resident', 'is_active' => true]);
    $this->actingAs($resident)->putJson('/api/auth/inventory', ['government_id_ids' => [$id->id], 'document_ids' => [$document->id]])->assertOk();
    expect($resident->ownedGovernmentIds()->sole()->id)->toBe($id->id)->and($resident->ownedDocuments()->sole()->id)->toBe($document->id);
    $response = $this->getJson('/api/auth/inventory')->assertOk();
    $rows = collect($response->json('data.government_ids'))->keyBy('id');
    expect($rows[$id->id]['owned'])->toBeTrue()->and($rows[$id->id]['guidance_mode'])->toBe('informational_only');
    $this->getJson('/api/government-ids')->assertOk()->assertJsonCount(2, 'data');
});
