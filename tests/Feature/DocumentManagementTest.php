<?php

use App\Models\Document;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['role' => 'researcher']));
});

it('creates basic document information and opens read only details with no empty sections', function () {
    $this->get('/admin/documents/create')->assertOk()->assertSee('Basic Information')->assertSee('Add checklist')->assertSee('Apply step')->assertSee('Edit fees')->assertSee('Linked Offices');
    $this->post('/admin/documents', [
        'name' => 'PSA Birth Certificate', 'level' => 'National', 'category' => 'Civil Registry',
        'issued_by' => 'Philippine Statistics Authority', 'description' => 'A civil registry document.',
    ])->assertSessionHasNoErrors()->assertRedirect(route('admin.documents.show', Document::sole()));

    $document = Document::sole();
    expect($document->requirements)->toBeNull()->and($document->fee)->toBeNull()->and($document->eligibility)->toBeNull();
    $this->get(route('admin.documents.show', $document))->assertOk()
        ->assertSee('Philippine Statistics Authority')->assertSee('A civil registry document.')
        ->assertSee('Edit details')->assertDontSee('Application details')->assertDontSee('<textarea', false);
    $this->get('/admin/documents')->assertOk()->assertSee(route('admin.documents.show', $document), false);
});

it('edits the same document and preserves optional details omitted by a basic only update', function () {
    $document = Document::create(['name' => 'Existing document', 'requirements' => 'Bring the original.', 'fee' => '0']);
    $this->get(route('admin.documents.edit', $document))->assertOk()->assertSee('Bring the original.');
    $this->put(route('admin.documents.update', $document), ['name' => 'Updated document', 'description' => 'Updated description'])
        ->assertSessionHasNoErrors()->assertRedirect(route('admin.documents.show', $document));
    expect($document->fresh()->requirements)->toBe('Bring the original.')->and($document->fresh()->fee)->toBe('0');
    $this->get(route('admin.documents.show', $document))->assertOk()->assertSee('Bring the original.');
});

it('redisplays valid values and shows errors when validation fails', function () {
    $this->from('/admin/documents/create')->post('/admin/documents', [
        'name' => 'Keep this entry', 'description' => 'Keep this description', 'fee' => ['invalid'],
    ])->assertRedirect('/admin/documents/create')->assertSessionHasErrors('fee');
    $this->get('/admin/documents/create')->assertOk()->assertSee('Keep this entry')->assertSee('Keep this description')
        ->assertSee('Please check the highlighted fields.');
    expect(Document::count())->toBe(0);
});

it('escapes saved document content on the view page', function () {
    $document = Document::create(['name' => 'Safe display', 'description' => '<script>alert("unsafe")</script>']);
    $this->get(route('admin.documents.show', $document))->assertOk()
        ->assertSee($document->description)->assertDontSee($document->description, false);
});

it('protects document details from guests residents and inactive staff', function () {
    $document = Document::create(['name' => 'Staff details']);
    auth()->logout();
    $this->get(route('admin.documents.show', $document))->assertRedirect('/login');
    foreach ([['role' => 'resident'], ['role' => 'researcher', 'is_active' => false]] as $attributes) {
        $this->actingAs(User::factory()->create($attributes));
        $this->get(route('admin.documents.show', $document))->assertForbidden();
    }
});


it('displays and searches the agency selected in the shared document form', function () {
    $agency = \App\Models\Agency::create(['name' => 'Example Civil Registry', 'acronym' => 'ECR']);
    $this->post('/admin/documents', ['name' => 'Issued certificate', 'agency_id' => $agency->id])->assertSessionHasNoErrors();
    Document::create(['name' => 'Other document']);
    $this->get('/admin/documents?q=ECR')->assertOk()->assertSee('Issued certificate')->assertSee('Example Civil Registry')->assertDontSee('Other document');
});
