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
