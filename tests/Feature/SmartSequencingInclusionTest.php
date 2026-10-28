<?php

use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['role' => 'researcher']));
});

dataset('inclusion catalogs', [
    [GovernmentId::class, 'government-ids', 'government_ids'],
    [Document::class, 'documents', 'documents'],
]);

it('defaults existing and omitted values to true and rolls back only the inclusion column', function () {
    $migration = require database_path('migrations/2026_10_28_000300_add_smart_sequencing_inclusion_to_catalog_tables.php');
    $migration->down();
    $original = ['name' => 'Preserve research', 'guidance_mode' => 'informational_only',
        'application_availability' => 'limited_conditional', 'acquisition_type' => 'profession_based', 'target_group' => 'Seafarers'];
    foreach (['government_ids', 'documents'] as $table) DB::table($table)->insert($original);
    $migration->up();
    foreach ([GovernmentId::class, Document::class] as $model) {
        expect($model::sole()->include_in_smart_sequencing)->toBeTrue()->and((new $model)->include_in_smart_sequencing)->toBeTrue();
    }
    $migration->down();
    foreach (['government_ids', 'documents'] as $table) {
        $row = (array) DB::table($table)->sole();
        foreach ($original as $field => $value) expect($row[$field])->toBe($value);
        expect($row)->not->toHaveKey('include_in_smart_sequencing');
    }
    $migration->up();
});

it('creates both values and saves checked unchecked and omitted values through the wizard', function ($model, $resource) {
    $page = $this->get(route('admin.'.$resource.'.create'))->assertOk()
        ->assertSee('name="include_in_smart_sequencing" value="0"', false)
        ->assertSee('name="include_in_smart_sequencing" value="1" checked', false)
        ->assertSeeInOrder(['Guidance Mode', 'Include in Smart Sequencing']);
    foreach ([true, false] as $value) {
        $label = $value ? 'Yes' : 'No';
        $this->post(route('admin.'.$resource.'.store'), ['name' => 'Direct '.$label, 'include_in_smart_sequencing' => $value])
            ->assertSessionHasNoErrors();
        expect($model::where('name', 'Direct '.$label)->sole()->include_in_smart_sequencing)->toBe($value);
        $key = $this->get(route('admin.'.$resource.'.create'))->viewData('creationKey');
        // An unchecked browser checkbox submits only the hidden 0 input.
        $this->post(route('admin.'.$resource.'.wizard.store'), [
            'creation_key' => $key, 'name' => 'Wizard '.$label, 'include_in_smart_sequencing' => $value ? '1' : '0',
        ])->assertSessionHasNoErrors();
        $record = $model::where('name', 'Wizard '.$label)->sole();
        expect($record->include_in_smart_sequencing)->toBe($value);
        foreach (['wizard', 'edit-wizard'] as $mode) {
            $this->get(route('admin.'.$resource.'.'.$mode.'.show', [$record, 'review']))->assertOk()
                ->assertSee('Include in Smart Sequencing: '.$label);
        }
        $details = $this->get(route('admin.'.$resource.'.show', $record))->assertOk()->assertSee('Include in Smart Sequencing');
        expect($details->getContent())->toMatch('/<dt>Include in Smart Sequencing<\/dt>\s*<dd>'.$label.'<\/dd>/');
        $this->post(route('admin.'.$resource.'.edit-wizard.save', [$record, 'basic']), ['name' => $record->name])
            ->assertSessionHasNoErrors();
        expect($record->fresh()->include_in_smart_sequencing)->toBe($value);
        $this->post(route('admin.'.$resource.'.edit-wizard.save', [$record, 'basic']), [
            'name' => $record->name, 'include_in_smart_sequencing' => $value ? '0' : '1',
        ])->assertSessionHasNoErrors();
        expect($record->fresh()->include_in_smart_sequencing)->toBe(!$value);
        $this->post(route('admin.'.$resource.'.edit-wizard.save', [$record, 'fees']), ['fees' => []])->assertSessionHasNoErrors();
        expect($record->fresh()->include_in_smart_sequencing)->toBe(!$value);
        $page = $this->get(route('admin.'.$resource.'.edit-wizard.show', [$record, 'basic']))->assertOk();
        if (!$value) $page->assertSee('name="include_in_smart_sequencing" value="1" checked', false);
        else $page->assertDontSee('name="include_in_smart_sequencing" value="1" checked', false);
        $this->put(route('admin.'.$resource.'.update', $record), ['name' => $record->name, 'include_in_smart_sequencing' => $value])
            ->assertSessionHasNoErrors();
        expect($record->fresh()->include_in_smart_sequencing)->toBe($value);
    }
    $key = $this->get(route('admin.'.$resource.'.create'))->viewData('creationKey');
    $this->post(route('admin.'.$resource.'.wizard.store'), ['creation_key' => $key, 'name' => 'Omitted'])->assertSessionHasNoErrors();
    expect($model::where('name', 'Omitted')->sole()->include_in_smart_sequencing)->toBeTrue();
})->with('inclusion catalogs');

it('rejects malformed booleans through all save routes without converting truthy strings', function ($model, $resource) {
    $record = $model::create(['name' => 'Keep false', 'include_in_smart_sequencing' => false]);
    foreach (['false', 'true', 'yes', 'no', 'on', 'unknown', 2, -1, '', null, [1]] as $invalid) {
        $data = ['name' => $record->name, 'include_in_smart_sequencing' => $invalid];
        $this->postJson(route('admin.'.$resource.'.store'), $data)->assertUnprocessable()->assertJsonValidationErrors('include_in_smart_sequencing');
        $this->putJson(route('admin.'.$resource.'.update', $record), $data)->assertUnprocessable()->assertJsonValidationErrors('include_in_smart_sequencing');
        $key = $this->get(route('admin.'.$resource.'.create'))->viewData('creationKey');
        $this->postJson(route('admin.'.$resource.'.wizard.store'), $data + ['creation_key' => $key])
            ->assertUnprocessable()->assertJsonValidationErrors('include_in_smart_sequencing');
        foreach (['wizard', 'edit-wizard'] as $mode) {
            $this->postJson(route('admin.'.$resource.'.'.$mode.'.save', [$record, 'basic']), $data)
                ->assertUnprocessable()->assertJsonValidationErrors('include_in_smart_sequencing');
        }
        expect($record->fresh()->include_in_smart_sequencing)->toBeFalse();
    }
    foreach ([0, 1, '0', '1', false, true] as $valid) {
        $this->putJson(route('admin.'.$resource.'.update', $record), ['name' => $record->name, 'include_in_smart_sequencing' => $valid])
            ->assertRedirect()->assertSessionHasNoErrors();
        expect($record->fresh()->include_in_smart_sequencing)->toBe((bool) $valid);
    }
})->with('inclusion catalogs');

it('exposes actual booleans without filtering or changing guidance combinations', function () {
    foreach (['guided', 'informational_only'] as $guidance) {
        foreach ([true, false] as $include) {
            foreach ([GovernmentId::class, Document::class] as $model) {
                $model::create(['name' => $guidance.($include ? ' Yes' : ' No'), 'guidance_mode' => $guidance, 'include_in_smart_sequencing' => $include]);
            }
        }
    }
    $list = $this->getJson('/api/government-ids')->assertOk()->assertJsonCount(4, 'data')->json('data');
    foreach ($list as $row) {
        $record = GovernmentId::findOrFail($row['id']);
        expect($row['include_in_smart_sequencing'])->toBe($record->include_in_smart_sequencing)->and($row['guidance_mode'])->toBe($record->guidance_mode);
        $this->getJson('/api/government-ids/'.$record->id)->assertOk()->assertJsonPath('data.include_in_smart_sequencing', $record->include_in_smart_sequencing);
    }
    $resident = User::factory()->create(['role' => 'resident', 'is_active' => true]);
    $data = $this->actingAs($resident)->getJson('/api/auth/inventory')->assertOk()->assertJsonCount(4, 'data.government_ids')->assertJsonCount(4, 'data.documents')->json('data');
    foreach (['government_ids', 'documents'] as $kind) {
        expect(array_column($data[$kind], 'include_in_smart_sequencing'))->toBe([false, true, false, true]);
    }
});

it('toggles inclusion without modifying metadata or structured acquisition content', function ($model, $resource) {
    $owner = $model::create([
        'name' => 'Complete catalog record', 'guidance_mode' => 'informational_only', 'application_availability' => 'limited_conditional',
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
            $fresh->only(['guidance_mode', 'application_availability', 'acquisition_type', 'target_group', 'description', 'requirements', 'fee', 'application_process']),
            $fresh->getRelations(),
        ];
    };
    $before = serialize($snapshot());
    foreach ([false, true] as $mode) {
        $this->post(route('admin.'.$resource.'.edit-wizard.save', [$owner, 'basic']), ['name' => $owner->name, 'include_in_smart_sequencing' => $mode])
            ->assertSessionHasNoErrors();
        expect($owner->fresh()->include_in_smart_sequencing)->toBe($mode)->and(serialize($snapshot()))->toBe($before);
    }
})->with('inclusion catalogs');

it('keeps records linkable as requirements prerequisites and inventory items regardless of candidacy metadata', function (string $availability, bool $include) {
    $id = GovernmentId::create(['name' => 'Reference ID', 'guidance_mode' => 'informational_only', 'include_in_smart_sequencing' => $include, 'application_availability' => $availability]);
    $document = Document::create(['name' => 'Reference document', 'guidance_mode' => 'informational_only', 'include_in_smart_sequencing' => $include, 'application_availability' => $availability]);
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
    expect($rows[$id->id]['owned'])->toBeTrue()->and($rows[$id->id]['include_in_smart_sequencing'])->toBe($include)
        ->and($rows[$id->id]['application_availability'])->toBe($availability);
    $this->getJson('/api/government-ids')->assertOk()->assertJsonCount(2, 'data');
})->with(['available', 'limited_conditional', 'temporarily_unavailable', 'discontinued'])->with([false, true]);
