<?php

use App\Models\Agency;
use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['role' => 'researcher']));
});

it('creates edits and deletes an unreferenced directory entry', function ($resource, $model) {
    $this->get('/admin/'.$resource.'/create')->assertOk();
    $this->post('/admin/'.$resource, ['name' => 'Research entry', 'description' => 'Initial description'])
        ->assertSessionHasNoErrors()->assertRedirect();
    $record = $model::where('name', 'Research entry')->sole();
    $this->get('/admin/'.$resource.'/'.$record->id.'/edit')->assertOk()->assertSee('Initial description');
    $this->put('/admin/'.$resource.'/'.$record->id, ['name' => 'Updated entry', 'description' => 'Revised description'])
        ->assertSessionHasNoErrors()->assertRedirect();
    expect($record->fresh()->name)->toBe('Updated entry')->and($record->fresh()->description)->toBe('Revised description');
    $this->get('/admin/'.$resource)->assertOk()->assertSee('Updated entry');
    if ($resource === 'government-ids') {
        $this->get('/admin/'.$resource.'/'.$record->id)->assertOk()->assertSee('Revised description');
    }
    $this->delete('/admin/'.$resource.'/'.$record->id)->assertRedirect('/admin/'.$resource);
    $this->assertModelMissing($record);
})->with([['documents', Document::class], ['government-ids', GovernmentId::class]]);

it('keeps unresearched descriptions and eligibility blank', function ($resource, $model) {
    $this->post('/admin/'.$resource, ['name' => 'Name only'])->assertSessionHasNoErrors();
    $record = $model::where('name', 'Name only')->sole();
    expect($record->description)->toBeNull()->and($record->eligibility)->toBeNull();
})->with([['documents', Document::class], ['government-ids', GovernmentId::class]]);

it('rejects invalid directory edits without changing the saved record', function ($resource, $model) {
    $record = $model::create(['name' => 'Keep this name']);
    foreach ([['name' => ''], ['name' => str_repeat('x', 256)], ['name' => ['invalid']]] as $payload) {
        $this->putJson('/admin/'.$resource.'/'.$record->id, $payload)->assertUnprocessable()->assertJsonValidationErrors('name');
        expect($record->fresh()->name)->toBe('Keep this name');
    }
})->with([['documents', Document::class], ['government-ids', GovernmentId::class]]);

it('searches the ID agency and document issuer while filtering current classifications', function () {
    $agency = Agency::create(['name' => 'Example Authority', 'acronym' => 'EXA']);
    GovernmentId::create(['name' => 'Matching credential', 'agency_id' => $agency->id, 'level' => 'National', 'category' => 'Identity']);
    GovernmentId::create(['name' => 'Excluded credential', 'level' => 'Local']);
    $this->get('/admin/government-ids?q=EXA&level=National&category=Identity')->assertOk()
        ->assertViewHas('governmentIds', fn ($rows) => $rows->pluck('name')->all() === ['Matching credential']);
    Document::create(['name' => 'Matching certificate', 'issued_by' => 'Example Authority', 'level' => 'National']);
    Document::create(['name' => 'Excluded certificate', 'issued_by' => 'Other Authority', 'level' => 'National']);
    $this->get('/admin/documents?q=Example&level=National')->assertOk()
        ->assertViewHas('documents', fn ($rows) => $rows->pluck('name')->all() === ['Matching certificate']);
});

it('sorts directory entries by name in either direction', function ($resource, $model, $viewKey) {
    $model::create(['name' => 'Zulu entry']);
    $model::create(['name' => 'Alpha entry']);
    $this->get('/admin/'.$resource.'?sort=name_asc')->assertOk()
        ->assertViewHas($viewKey, fn ($rows) => $rows->pluck('name')->all() === ['Alpha entry', 'Zulu entry']);
    $this->get('/admin/'.$resource.'?sort=name_desc')->assertOk()
        ->assertViewHas($viewKey, fn ($rows) => $rows->pluck('name')->all() === ['Zulu entry', 'Alpha entry']);
})->with([['documents', Document::class, 'documents'], ['government-ids', GovernmentId::class, 'governmentIds']]);

it('preserves saved source data when the simplified ID form omits it', function () {
    $id = GovernmentId::create(['name' => 'Existing ID', 'official_link' => 'https://example.gov.ph', 'official_sources' => 'Earlier research']);
    $this->put('/admin/government-ids/'.$id->id, ['name' => 'Renamed ID'])->assertSessionHasNoErrors();
    expect($id->fresh()->official_link)->toBe('https://example.gov.ph')->and($id->fresh()->official_sources)->toBe('Earlier research');
});

it('opens the updated ID creation form without legacy requirement text fields', function () {
    $this->get('/admin/government-ids/create')->assertOk()
        ->assertSee('Back to directory')->assertSee('Create ID')
        ->assertSee('Eligibility')->assertSee('Validity')->assertSee('Processing Time')
        ->assertSee('Application Guide')->assertSee('Linked Offices')
        ->assertSee('fees-dialog')->assertSee('offices-dialog')->assertSee('Add checklist')
        ->assertSee('requirements_payload')->assertSee('Apply checklist')
        ->assertDontSee('name="requirements"', false)
        ->assertDontSee('name="prerequisite_notes"', false);
});

it('creates the ID form with its researched fields and opens its details', function () {
    $agency = Agency::create(['name' => 'Example Authority']);
    $office = \App\Models\Office::create(['name' => 'Example branch']);
    $response = $this->post('/admin/government-ids', [
        'name' => 'New credential', 'agency_id' => $agency->id,
        'description' => 'Description entered before creating', 'purpose' => 'Identification',
        'level' => 'National', 'category' => 'Identity ID',
        'eligibility_age_type' => 'minimum', 'eligibility_min_age' => 18,
        'eligibility_citizenship' => 'filipino', 'eligibility_residency' => 'none',
        'validity_type' => 'fixed', 'validity_value' => 5, 'validity_unit' => 'year',
        'processing_time_type' => 'fixed', 'processing_time_min' => 7,
        'processing_time_unit' => 'working_day',
        'fees' => [['label' => 'Application', 'type' => 'fixed', 'amount_min' => 100, 'is_optional' => false]],
        'office_links_present' => 1,
        'office_links' => [[
            'office_id' => $office->id, 'new_application_status' => 'available',
            'renewal_status' => 'unknown', 'replacement_status' => 'unknown',
        ]],
    ])->assertSessionHasNoErrors();
    $id = GovernmentId::where('name', 'New credential')->sole();
    $response->assertRedirect(route('admin.government-ids.show', $id));
    expect($id->description)->toBe('Description entered before creating')
        ->and($id->agency_id)->toBe($agency->id)
        ->and((int) $id->eligibility_min_age)->toBe(18)
        ->and((int) $id->validity_value)->toBe(5)
        ->and((int) $id->processing_time_min)->toBe(7)
        ->and((float) $id->fees()->sole()->amount_min)->toBe(100.0)
        ->and($id->offices()->sole()->id)->toBe($office->id)
        ->and($id->requirementSets()->count())->toBe(0)
        ->and($id->requirements)->toBeNull();
    $this->get(route('admin.government-ids.edit', $id))->assertOk()
        ->assertSee('Add checklist')->assertSee('Description entered before creating');
});

it('keeps creation errors on the form and preserves the entered details', function () {
    $this->from('/admin/government-ids/create')->post('/admin/government-ids', [
        'name' => '', 'description' => 'Keep this description',
    ])->assertRedirect('/admin/government-ids/create')
        ->assertSessionHasErrors('name')
        ->assertSessionHasInput('description', 'Keep this description');
    expect(GovernmentId::count())->toBe(0);
    $this->get('/admin/government-ids/create')->assertOk()->assertSee('Keep this description');
});

it('keeps the details redirect for ID creation without the continue option', function () {
    $response = $this->post('/admin/government-ids', ['name' => 'Standard creation'])
        ->assertSessionHasNoErrors();
    $id = GovernmentId::where('name', 'Standard creation')->sole();
    $response->assertRedirect(route('admin.government-ids.show', $id));
});
