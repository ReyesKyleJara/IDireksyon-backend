<?php

use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\Office;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['role' => 'researcher']));
});

function documentWizardUrl(Document $document, string $step, string $action = 'show'): string
{
    return route('admin.documents.wizard.'.$action, [$document, $step]);
}

function startDocumentWizard($test): Document
{
    $key = $test->get(route('admin.documents.create'))->assertOk()->viewData('creationKey');
    $test->post(route('admin.documents.wizard.store'), [
        'creation_key' => $key, 'name' => 'Guided certificate',
        'issued_by' => 'Civil Registry', 'category' => 'Certification',
        'validity_type' => 'fixed', 'validity_value' => 6, 'validity_unit' => 'month',
        'processing_time_type' => 'same_day',
    ])->assertSessionHasNoErrors();

    return Document::where('name', 'Guided certificate')->sole();
}

it('opens six document steps and creates one resumable document from each start', function () {
    $page = $this->get(route('admin.documents.create'))->assertOk()
        ->assertSeeInOrder(['Basic Info', 'Requirements', 'Fees', 'Application Guide', 'Offices', 'Review'])
        ->assertSee('Document Name')->assertSee('About this Document')->assertSee('Civil Registry')
        ->assertSee('Issuing office / department')->assertDontSee('Travel Document')
        ->assertDontSee('Add checklist')->assertDontSee('Document form sections');
    $key = $page->viewData('creationKey');
    $data = ['creation_key' => $key, 'name' => 'Once only'];
    $this->post(route('admin.documents.wizard.store'), $data)->assertSessionHasNoErrors();
    $document = Document::sole();
    $this->post(route('admin.documents.wizard.store'), $data)
        ->assertRedirect(documentWizardUrl($document, 'requirements'));
    expect(Document::count())->toBe(1)->and($document->requirementSets()->count())->toBe(1)
        ->and($document->requirementSets()->sole()->applicant_type)->toBe('all')
        ->and(GovernmentId::count())->toBe(0);
    $this->get(route('admin.documents.create'))->assertSee('Resume')->assertSee('Once only')
        ->assertSee(documentWizardUrl($document, 'requirements'), false);
    $this->post(route('admin.documents.wizard.store'), ['creation_key' => fake()->uuid(), 'name' => 'Invalid start'])
        ->assertStatus(419);
});

it('saves each document section independently and finishes at document details', function () {
    $document = startDocumentWizard($this);
    expect($document->validity)->toBe('6 months')->and($document->processing_time)->toBe('Same day');
    $set = $document->requirementSets()->sole();
    $requirements = ['application_type' => 'new', 'applicant_type' => 'all', 'groups' => [[
        'title' => 'Proof', 'rule' => 'all', 'condition_type' => 'always',
        'ways' => [['required_count' => 1, 'qualification_type' => 'none', 'qualification_scope' => 'every',
            'items' => [['type' => 'custom', 'custom_name' => 'Signed application', 'submission_format' => 'original']],
        ]],
    ]]];
    $this->get(documentWizardUrl($document, 'requirements'))->assertOk()
        ->assertViewHas('recordKind', 'document')->assertSee('Continue →');
    $this->putJson(route('admin.documents.checklists.update', [$document, $set]), $requirements)->assertOk();
    $this->post(documentWizardUrl($document, 'requirements', 'save'))->assertRedirect(documentWizardUrl($document, 'fees'));
    $this->post(documentWizardUrl($document, 'fees', 'save'), ['fees' => [
        ['label' => 'Certification', 'type' => 'fixed', 'amount_min' => 50],
    ]])->assertSessionHasNoErrors()->assertRedirect(documentWizardUrl($document, 'guide'));
    $this->get(documentWizardUrl($document, 'guide'))->assertOk()->assertSee('Use existing Document information')
        ->assertSee('App preview')->assertSee('Continue')->assertSee(documentWizardUrl($document, 'fees'), false);
    $guide = [['id' => $set->id, 'application_type' => 'new', 'applicant_type' => 'all', 'steps' => [
        ['title' => 'Submit the application', 'type' => 'general', 'blocks' => []],
    ]]];
    $this->post(documentWizardUrl($document, 'guide', 'save'), ['application_guide_payload' => json_encode($guide)])
        ->assertSessionHasNoErrors()->assertRedirect(documentWizardUrl($document, 'offices'));
    $office = Office::create(['name' => 'Registry branch']);
    $this->post(documentWizardUrl($document, 'offices', 'save'), ['office_links_present' => 1, 'office_links' => [[
        'office_id' => $office->id, 'new_application_status' => 'available',
        'renewal_status' => 'unknown', 'replacement_status' => 'unknown',
    ]]])->assertSessionHasNoErrors()->assertRedirect(documentWizardUrl($document, 'review'));
    $page = $this->get(documentWizardUrl($document, 'review'))->assertOk()->assertSee('Finish')
        ->assertSee('1 group / 1 item')->assertSee('1 fee')->assertSee('1 step')->assertSee('1 linked office')
        ->assertSee(documentWizardUrl($document, 'offices'), false);
    expect($page->viewData('counts'))->toBe(['groups' => 1, 'items' => 1, 'fees' => 1, 'guide' => 1, 'offices' => 1]);
    $this->post(documentWizardUrl($document, 'basic', 'save'), ['name' => 'Updated certificate', 'description' => 'Saved separately'])
        ->assertSessionHasNoErrors()->assertRedirect(documentWizardUrl($document, 'requirements'));
    expect($document->fresh()->issued_by)->toBe('Civil Registry')->and($document->fees()->count())->toBe(1)
        ->and($document->offices()->count())->toBe(1)->and($set->applicationSteps()->count())->toBe(1)
        ->and($set->groups()->count())->toBe(1);
    foreach (['basic' => 'Saved separately', 'requirements' => 'Signed application', 'fees' => 'Certification',
        'guide' => 'Submit the application', 'offices' => 'Registry branch'] as $step => $text) {
        $this->get(documentWizardUrl($document, $step))->assertOk()->assertSee($text);
    }
    $this->post(documentWizardUrl($document, 'review', 'save'))->assertRedirect(route('admin.documents.show', $document));
    expect(session('document_creation_resume'))->toBeNull();
});

it('keeps saved sections intact on validation errors and rejects guide references from another document', function () {
    $document = startDocumentWizard($this);
    $fee = $document->fees()->create(['label' => 'Keep fee', 'type' => 'fixed', 'amount_min' => 25]);
    $this->from(documentWizardUrl($document, 'basic'))->post(documentWizardUrl($document, 'basic', 'save'), [
        'name' => '', 'description' => 'Keep entered description',
    ])->assertSessionHasErrors('name')->assertSessionHasInput('description', 'Keep entered description');
    $this->get(documentWizardUrl($document, 'basic'))->assertSee('Keep entered description')->assertSee('Please check');
    $this->postJson(documentWizardUrl($document, 'fees', 'save'), ['fees' => [
        ['label' => 'Invalid', 'type' => 'range', 'amount_min' => 100, 'amount_max' => 10],
    ]])->assertUnprocessable();
    $this->postJson(documentWizardUrl($document, 'offices', 'save'), ['office_links' => [['office_id' => 999999]]])
        ->assertUnprocessable();
    $other = Document::create(['name' => 'Other document']);
    $otherSet = $other->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'all']);
    $this->postJson(documentWizardUrl($document, 'guide', 'save'), ['application_guide_payload' => json_encode([[
        'id' => $otherSet->id, 'application_type' => 'new', 'applicant_type' => 'all',
        'steps' => [['title' => 'Wrong owner', 'type' => 'general', 'blocks' => []]],
    ]])])->assertUnprocessable();
    expect($document->fresh()->name)->toBe('Guided certificate')->and($document->fees()->sole()->id)->toBe($fee->id)
        ->and($otherSet->applicationSteps()->count())->toBe(0);
});

it('allows empty optional sections and scopes resume state away from Government IDs', function () {
    session(['id_creation_resume' => ['id' => 123, 'step' => 'fees']]);
    $document = startDocumentWizard($this);
    foreach (['requirements' => 'fees', 'fees' => 'guide', 'guide' => 'offices', 'offices' => 'review'] as $step => $next) {
        $this->post(documentWizardUrl($document, $step, 'save'))->assertSessionHasNoErrors()
            ->assertRedirect(documentWizardUrl($document, $next));
    }
    $this->get(documentWizardUrl($document, 'guide'))->assertSee('Requirements are not complete yet');
    $this->post(documentWizardUrl($document, 'review', 'save'))->assertRedirect(route('admin.documents.show', $document));
    expect(session('id_creation_resume'))->toBe(['id' => 123, 'step' => 'fees']);
    $this->get(documentWizardUrl($document, 'unknown'))->assertNotFound();
    $this->post(documentWizardUrl($document, 'unknown', 'save'))->assertNotFound();
});

it('protects document wizard routes from guests residents and inactive staff', function () {
    $document = Document::create(['name' => 'Protected']);
    auth()->logout();
    $this->get(route('admin.documents.create'))->assertRedirect('/login');
    $this->getJson(documentWizardUrl($document, 'review'))->assertUnauthorized();
    $this->postJson(route('admin.documents.wizard.store'), [])->assertUnauthorized();
    $this->postJson(documentWizardUrl($document, 'basic', 'save'), [])->assertUnauthorized();
    foreach ([['role' => 'resident'], ['role' => 'researcher', 'is_active' => false]] as $attributes) {
        $this->actingAs(User::factory()->create($attributes));
        $this->get(route('admin.documents.create'))->assertForbidden();
        $this->get(documentWizardUrl($document, 'review'))->assertForbidden();
        $this->postJson(route('admin.documents.wizard.store'), [])->assertForbidden();
        $this->postJson(documentWizardUrl($document, 'basic', 'save'), [])->assertForbidden();
    }
});
