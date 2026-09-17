<?php

use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\Requirement;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['role' => 'researcher']));
});

function catalogModel(string $resource): string
{
    return $resource === 'documents' ? Document::class : GovernmentId::class;
}

it('creates, displays, edits and deletes both catalog types through shared pages', function (string $resource) {
    $model = catalogModel($resource);
    $this->get('/admin/'.$resource)->assertOk()->assertSee('No '.($resource === 'documents' ? 'documents' : 'government ids').' added yet');
    $this->get('/admin/'.$resource.'/create')->assertOk()->assertSee($resource === 'documents' ? 'Document name' : 'ID name');
    $payload = [
        'name' => 'Test research entry', 'agency' => 'Test issuing office',
        'application_steps' => [['title' => 'Second step', 'details' => 'Test instructions'], ['title' => 'First step', 'details' => null]],
        'source_url' => 'https://example.org/test-source', 'source_checked_at' => now()->subDay()->toDateString(),
    ];
    $this->post('/admin/'.$resource, $payload)->assertSessionHasNoErrors();
    $record = $model::firstOrFail();
    $this->get('/admin/'.$resource.'/'.$record->id)->assertOk()->assertSee('Test research entry')->assertSeeInOrder(['Second step', 'First step']);
    $this->get('/admin/'.$resource.'/'.$record->id.'/edit')->assertOk()->assertSee('Test research entry');
    $this->put('/admin/'.$resource.'/'.$record->id, ['name' => 'Revised entry', 'agency' => 'Test issuing office'])->assertSessionHasNoErrors();
    expect($record->fresh()->application_steps)->toBe([]);
    $this->delete('/admin/'.$resource.'/'.$record->id)->assertRedirect('/admin/'.$resource);
    expect($model::count())->toBe(0);
})->with(['government-ids', 'documents']);

it('allows a document with unknown issuing office and validity', function () {
    $this->post('/admin/documents', ['name' => 'Unresearched test document'])->assertSessionHasNoErrors();
    $document = Document::firstOrFail();
    expect($document->agency)->toBeNull()->and($document->validity)->toBeNull();
    $this->get('/admin/documents/'.$document->id)->assertOk()->assertSee('Request process')->assertSee('No requirements recorded yet');
});

it('validates sources and structured steps without silently losing input', function (string $resource) {
    $this->from('/admin/'.$resource.'/create')->post('/admin/'.$resource, [
        'name' => 'Keep this name', 'agency' => 'Test office',
        'source_url' => 'javascript:alert(1)', 'source_checked_at' => now()->addDay()->toDateString(),
        'application_steps' => [['title' => '', 'details' => 'Keep this instruction']],
    ])->assertSessionHasErrors(['source_url', 'source_checked_at', 'application_steps.0.title']);
    $this->get('/admin/'.$resource.'/create')->assertOk()->assertSee('Keep this name')->assertSee('Keep this instruction');
    expect(catalogModel($resource)::count())->toBe(0);
})->with(['government-ids', 'documents']);

it('requires an ID agency and a source for a source-check date', function () {
    $this->post('/admin/government-ids', ['name' => 'Test ID', 'source_checked_at' => now()->toDateString()])
        ->assertSessionHasErrors(['agency', 'source_url']);
});

it('searches and paginates the directory in either view', function (string $resource) {
    $model = catalogModel($resource);
    foreach (range(1, 14) as $i) {
        $model::create(['name' => sprintf('Test entry %02d', $i), 'agency' => 'Example office']);
    }
    $this->get('/admin/'.$resource.'?q=Example&view=grid')->assertOk()->assertSee('Test entry 01')->assertDontSee('Test entry 14')->assertSee('page=2');
    $this->get('/admin/'.$resource.'?q=Example&view=grid&page=2')->assertOk()->assertSee('Test entry 14');
    $this->get('/admin/'.$resource.'?q=Absent')->assertOk()->assertSee('No matching entries');
})->with(['government-ids', 'documents']);

it('links all requirement types to each catalog type and scopes removal', function (string $resource) {
    $parent = catalogModel($resource)::create(['name' => 'Parent test entry', 'agency' => 'Test office']);
    $document = Document::create(['name' => 'Referenced test document']);
    $id = GovernmentId::create(['name' => 'Referenced test ID', 'agency' => 'Test office']);
    $url = '/admin/'.$resource.'/'.$parent->id;
    foreach ([
        ['type' => 'document', 'referenced_document_id' => $document->id],
        ['type' => 'government_id', 'referenced_government_id_id' => $id->id],
        ['type' => 'custom', 'name' => 'Test custom item'],
    ] as $payload) {
        $this->post($url.'/requirements', $payload)->assertSessionHasNoErrors()->assertRedirect($url.'/edit');
    }
    expect($parent->requirements()->count())->toBe(3);
    $this->get($url)->assertOk()->assertSee('Referenced test document')->assertSee('Referenced test ID')->assertSee('Test custom item');
    $document->update(['name' => 'Renamed test document']);
    $this->get($url)->assertSee('Renamed test document');
    $other = Requirement::create(['name' => 'Unattached item', 'type' => 'custom']);
    $this->delete($url.'/requirements/'.$other->id)->assertNotFound();
    $requirement = $parent->requirements()->first();
    $this->delete($url.'/requirements/'.$requirement->id)->assertRedirect($url.'/edit');
    expect($parent->requirements()->count())->toBe(2);
})->with(['government-ids', 'documents']);

it('rejects incomplete and self-referencing requirements without changing the main form', function (string $resource) {
    $parent = catalogModel($resource)::create(['name' => 'Original parent name', 'agency' => 'Test office']);
    $url = '/admin/'.$resource.'/'.$parent->id;
    $this->from($url.'/edit')->post($url.'/requirements', ['type' => 'custom', 'name' => ''])->assertSessionHasErrorsIn('requirement', ['name']);
    $this->get($url.'/edit')->assertOk()->assertSee('Original parent name');
    $type = $resource === 'documents' ? 'document' : 'government_id';
    $column = $resource === 'documents' ? 'referenced_document_id' : 'referenced_government_id_id';
    $this->post($url.'/requirements', ['type' => $type])->assertSessionHasErrorsIn('requirement', [$column]);
    $this->post($url.'/requirements', ['type' => $type, $column => $parent->id])->assertSessionHasErrorsIn('requirement', [$column]);
    expect($parent->requirements()->count())->toBe(0);
})->with(['government-ids', 'documents']);

it('blocks deletion while referenced but allows deletion after detaching', function (string $resource) {
    $target = catalogModel($resource)::create(['name' => 'Referenced item', 'agency' => 'Test office']);
    $parent = Document::create(['name' => 'Parent document']);
    $type = $resource === 'documents' ? 'document' : 'government_id';
    $column = $resource === 'documents' ? 'referenced_document_id' : 'referenced_government_id_id';
    $this->post('/admin/documents/'.$parent->id.'/requirements', ['type' => $type, $column => $target->id])->assertSessionHasNoErrors();
    $url = '/admin/'.$resource.'/'.$target->id;
    $this->from($url)->delete($url)->assertRedirect($url)->assertSessionHas('error');
    expect($target->fresh())->not->toBeNull();
    $this->delete('/admin/documents/'.$parent->id.'/requirements/'.$parent->requirements()->first()->id)->assertSessionHasNoErrors();
    $this->delete($url)->assertRedirect('/admin/'.$resource);
    expect($target->fresh())->toBeNull();
})->with(['government-ids', 'documents']);

it('keeps requirement notes separate between applications', function () {
    $document = Document::create(['name' => 'Shared reference']);
    $one = GovernmentId::create(['name' => 'One', 'agency' => 'Test office']);
    $two = GovernmentId::create(['name' => 'Two', 'agency' => 'Test office']);
    foreach ([$one, $two] as $index => $parent) {
        $this->post('/admin/government-ids/'.$parent->id.'/requirements', [
            'type' => 'document', 'referenced_document_id' => $document->id, 'description' => 'Note '.$index,
        ])->assertSessionHasNoErrors();
    }
    expect($one->requirements->first()->id)->not->toBe($two->requirements->first()->id);
    expect($one->requirements->first()->description)->toBe('Note 0');
    expect($two->requirements->first()->description)->toBe('Note 1');
});

it('can redisplay malformed input safely after validation rejects it', function () {
    $this->from('/admin/documents/create')->post('/admin/documents', [
        'name' => ['invalid'], 'source_checked_at' => ['invalid'], 'application_steps' => 'not-an-array',
    ])->assertSessionHasErrors(['name', 'source_checked_at', 'application_steps']);
    $this->get('/admin/documents/create')->assertOk()->assertSee('Please check');
});
