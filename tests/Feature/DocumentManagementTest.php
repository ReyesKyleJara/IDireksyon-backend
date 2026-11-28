<?php

use App\Models\Document;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['role' => 'researcher']));
});

it('uses the same six-step layout for Add and Edit in both modules', function () {
    foreach ([['documents', Document::class, 'Document'], ['government-ids', \App\Models\GovernmentId::class, 'Government ID']] as [$resource, $model, $label]) {
        $record = $model::create(['name' => 'Saved entry']);
        foreach (['/admin/'.$resource.'/create' => 'creation', '/admin/'.$resource.'/'.$record->id.'/edit' => 'editing'] as $url => $mode) {
            $this->get($url)->assertOk()->assertSee($label.' '.$mode.' steps')
                ->assertSeeInOrder(['Basic Info', 'Requirements', 'Fees', 'Application Guide', 'Offices', 'Review'])
                ->assertSeeText('Save & Continue', false)->assertDontSee('Document form sections');
        }
    }
});

it('creates basic document information and opens read only details with no empty sections', function () {
    $this->get('/admin/documents/create')->assertOk()->assertSee('Basic Information')->assertSee('Document creation steps')->assertSeeText('Save & Continue', false)->assertDontSee('Add checklist')->assertDontSee('Edit fees');
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

it('renders document details with a partially configured request method', function () {
    $document = Document::create(['name' => 'Document with legacy method']);
    $set = $document->requirementSets()->create([
        'application_type' => 'new',
        'applicant_type' => 'all',
        'request_channel' => ['provider' => 'Example Agency'],
    ]);

    $this->get(route('admin.documents.show', $document))->assertOk()
        ->assertSee('Example Agency')
        ->assertSee('Appointment: not specified');
    expect($set->fresh()->request_channel)->toBe(['provider' => 'Example Agency']);
});

it('opens read-only document details from a bookmarked view URL', function () {
    $document = Document::create(['name' => 'Bookmarked document']);
    $url = '/admin/documents/'.$document->id.'/view';

    $this->get($url)->assertRedirect(route('admin.documents.show', $document));
    $this->followingRedirects()->get($url)->assertOk()->assertSee('Bookmarked document');
    $this->get('/admin/documents/999999/view')->assertNotFound();

    auth()->logout();
    $this->get($url)->assertRedirect('/login');
});

it('edits the same document and preserves optional details omitted by a basic only update', function () {
    $document = Document::create(['name' => 'Existing document', 'requirements' => 'Bring the original.', 'fee' => '0']);
    $this->get(route('admin.documents.edit', $document))->assertOk()->assertSee('Edit Document');
    expect($document->fresh()->requirements)->toBe('Bring the original.');
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
        ->assertSee('Please check the highlighted information.');
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


it('groups existing basic fields into three shared cards in Government ID and Document Add and Edit', function () {
    foreach ([['documents', Document::class], ['government-ids', \App\Models\GovernmentId::class]] as [$resource, $model]) {
        $record = $model::create(['name' => 'Saved layout', 'application_availability' => 'limited_conditional',
            'acquisition_type' => 'profession_based', 'target_group' => 'Maritime professionals',
            'guidance_mode' => 'informational_only', 'include_in_smart_sequencing' => false]);
        foreach ([route('admin.'.$resource.'.create'), route('admin.'.$resource.'.edit', $record)] as $url) {
            $page = $this->get($url)->assertOk()->assertSeeInOrder(['General Information', 'Availability &amp; Audience', 'IDireksyon Behavior'], false);
            $document = new \DOMDocument();
            $previous = libxml_use_internal_errors(true);
            $document->loadHTML($page->getContent());
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            $xpath = new \DOMXPath($document);
            $sections = [
                'wizard-basic-heading' => ['name', 'level', 'category', 'agency_id', 'description', 'validity_type', 'processing_time_type', 'eligibility_age_type'],
                'wizard-audience-heading' => ['application_availability', 'acquisition_type', 'target_group'],
                'wizard-behavior-heading' => ['guidance_mode', 'include_in_smart_sequencing'],
            ];
            foreach ($sections as $heading => $fields) {
                $card = $xpath->query('//section[@aria-labelledby="'.$heading.'"]');
                expect($card->length)->toBe(1);
                foreach ($fields as $field) {
                    expect($xpath->query('.//*[@name="'.$field.'"]', $card->item(0))->length)->toBeGreaterThan(0);
                    foreach (array_diff(array_keys($sections), [$heading]) as $other) {
                        expect($xpath->query('//section[@aria-labelledby="'.$other.'"]//*[@name="'.$field.'"]')->length)->toBe(0);
                    }
                }
            }
            expect($xpath->query('//*[@name="prerequisites_present"]')->length)->toBe(0);
        }
    }
});

it('shows saved preparation, guide and research details in the read-only record', function (string $model, string $resource) {
    $record = $model::create([
        'name' => 'Saved guide details',
        'processing_time_note' => 'Allow extra time during peak season.',
        'research_metadata' => [
            'validity_note' => 'Check the printed date.',
            'sources' => [[
                'title' => 'Agency guide',
                'url' => 'https://example.gov.ph/guide',
                'section' => 'Application requirements',
                'scope' => 'Supports the walk-in checklist.',
            ]],
        ],
    ]);
    $record->requirementSets()->create([
        'application_type' => 'new',
        'applicant_type' => 'all',
        'request_channel' => [
            'provider' => 'Example office',
            'preparation' => 'Bring the completed form.',
            'application_guide_text' => 'Ask for a queue number.',
        ],
    ]);

    $this->get(route("admin.{$resource}.show", $record))->assertOk()
        ->assertSee('Allow extra time during peak season.')
        ->assertSee('Check the printed date.')
        ->assertSee('Application requirements')
        ->assertSee('Supports the walk-in checklist.')
        ->assertSee('Bring the completed form.')
        ->assertSee('Ask for a queue number.');
})->with([
    'government ID' => [\App\Models\GovernmentId::class, 'government-ids'],
    'document' => [Document::class, 'documents'],
]);
