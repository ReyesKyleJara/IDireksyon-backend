<?php

use App\Models\Agency;
use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\Office;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['role' => 'researcher']));
});

it('keeps existing duplicate guide references until the researcher intentionally removes them', function () {
    $id = GovernmentId::create(['name' => 'Duplicate reference ID']);
    $set = $id->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'all']);
    $step = $set->applicationSteps()->create(['title' => 'Prepare requirements']);
    $content = ['intro' => ['type' => 'doc', 'content' => [['type' => 'paragraph']]]];
    $first = $step->blocks()->create(['type' => 'requirements', 'section_title' => 'Required Documents', 'content' => $content, 'sort_order' => 0]);
    $second = $step->blocks()->create(['type' => 'requirements', 'section_title' => 'Existing extra reference', 'content' => $content, 'sort_order' => 1]);
    $this->get(wizardUrl($id, 'guide'))->assertOk()->assertSee('Duplicate references:')
        ->assertSee('referenceEntries')->assertSee('Required Documents')->assertSee('Existing extra reference');
    expect($step->blocks()->pluck('id')->all())->toBe([$first->id, $second->id]);
    $payload = [[
        'id' => $set->id, 'application_type' => 'new', 'applicant_type' => 'all',
        'steps' => [['id' => $step->id, 'title' => $step->title, 'type' => 'general', 'blocks' => [
            ['id' => $first->id, 'type' => 'requirements', 'section_title' => $first->section_title, 'content' => $content],
            ['id' => $second->id, 'type' => 'requirements', 'section_title' => $second->section_title, 'content' => $content],
        ]]],
    ]];
    $this->post(wizardSaveUrl($id, 'guide'), ['application_guide_payload' => json_encode($payload)])
        ->assertSessionHasNoErrors()->assertRedirect(wizardUrl($id, 'offices'));
    expect($step->blocks()->pluck('id')->all())->toBe([$first->id, $second->id]);
    array_pop($payload[0]['steps'][0]['blocks']);
    $this->post(wizardSaveUrl($id, 'guide'), ['application_guide_payload' => json_encode($payload)])
        ->assertSessionHasNoErrors()->assertRedirect(wizardUrl($id, 'offices'));
    expect($step->blocks()->sole()->id)->toBe($first->id);
});

it('reads current saved requirements for guide references without duplicating guide content', function () {
    $agency = Agency::create(['name' => 'Department of Foreign Affairs', 'acronym' => 'DFA']);
    $id = GovernmentId::create(['name' => 'Reference preview ID', 'agency_id' => $agency->id]);
    $set = $id->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'all']);
    $step = $set->applicationSteps()->create(['title' => 'Prepare Your Requirements']);
    $reference = $step->blocks()->create([
        'type' => 'requirements',
        'content' => ['intro' => ['type' => 'doc', 'content' => [['type' => 'paragraph']]]],
    ]);
    $originalContent = $reference->content;
    $set->groups()->create(['title' => 'Proof of identity', 'rule' => 'all', 'condition_type' => 'always']);

    $page = $this->get(wizardUrl($id, 'guide'))->assertOk()
        ->assertSee('Use existing ID information')->assertSee('View requirements')
        ->assertSee('View applicable fees')->assertSee('Find a DFA office');
    expect($page->viewData('counts')['groups'])->toBe(1);

    // A later Requirements save changes its own records, not the guide reference.
    $set->groups()->create(['title' => 'Proof of citizenship', 'rule' => 'all', 'condition_type' => 'always']);
    $page = $this->get(wizardUrl($id, 'guide'))->assertOk();
    expect($page->viewData('counts')['groups'])->toBe(2)
        ->and($step->blocks()->count())->toBe(1)
        ->and($reference->fresh()->content)->toBe($originalContent)
        ->and($reference->fresh()->type)->toBe('requirements');

    $this->post(wizardSaveUrl($id, 'guide'), ['application_guide_payload' => json_encode([[
        'id' => $set->id, 'application_type' => 'new', 'applicant_type' => 'all', 'steps' => [[
            'id' => $step->id, 'title' => $step->title, 'type' => 'general', 'blocks' => [[
                'id' => $reference->id, 'type' => 'requirements', 'content' => $originalContent,
            ]],
        ]],
    ]])])->assertSessionHasNoErrors()->assertRedirect(wizardUrl($id, 'offices'));
    expect($set->groups()->count())->toBe(2)->and($step->blocks()->sole()->id)->toBe($reference->id)
        ->and($reference->fresh()->content)->toBe($originalContent);
});

it('uses six wizard steps and retires wizard case actions', function () {
    $steps = [
        'basic' => 'Basic Info', 'requirements' => 'Requirements', 'fees' => 'Fees',
        'guide' => 'Application Guide', 'offices' => 'Offices', 'review' => 'Review',
    ];
    $page = $this->get(route('admin.government-ids.create'))->assertOk()
        ->assertSeeInOrder(array_values($steps))->assertDontSee('Applicant Cases')->assertDontSee('Application Types');
    expect($page->viewData('steps'))->toBe($steps);
    $id = GovernmentId::create(['name' => 'Retired cases']);
    $set = $id->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'all']);
    $this->get(wizardUrl($id, 'cases'))->assertNotFound();
    $this->post(wizardSaveUrl($id, 'cases'))->assertNotFound();
    $this->post(route('admin.government-ids.wizard.cases', $id))->assertNotFound();
    $this->delete(route('admin.government-ids.wizard.cases.destroy', [$id, $set]))->assertNotFound();
    expect($id->requirementSets()->count())->toBe(1);
});

it('shows four visible basic cards and preserves hidden legacy fields when saving About this ID', function () {
    $this->get(route('admin.government-ids.create'))->assertOk()
        ->assertSee('wizard-basic-heading')->assertSee('wizard-validity-heading')->assertSee('wizard-processing-heading')
        ->assertSee('aria-label="Eligibility"', false)->assertSee('About this ID')
        ->assertSee('Age Requirement')->assertSee('Citizenship Requirement')->assertSee('Residency Requirement')->assertSee('Other Eligibility Conditions')
        ->assertDontSee('<details', false)->assertDontSee('Purpose / use')
        ->assertDontSee('name="purpose"', false)->assertDontSee('name="official_link"', false)
        ->assertDontSee('name="official_sources"', false)->assertDontSee('Official sources');

    $id = GovernmentId::create([
        'name' => 'Compatible ID', 'purpose' => 'Keep existing purpose', 'description' => 'Previous description',
        'official_link' => 'https://example.gov.ph', 'official_sources' => 'Keep existing source',
    ]);
    $this->from(wizardUrl($id, 'basic'))->post(wizardSaveUrl($id, 'basic'), [
        'name' => $id->name, 'description' => 'An ID used to prove identity.',
        'validity_type' => 'until_age', 'validity_value' => 60,
        'processing_time_type' => 'range', 'processing_time_min' => 3, 'processing_time_max' => 5,
        'processing_time_unit' => 'working_day',
        'eligibility_age_type' => 'minimum', 'eligibility_min_age' => 18,
        'eligibility_citizenship' => 'filipino', 'eligibility_residency' => 'custom',
        'eligibility_residency_custom' => 'Example municipality', 'eligibility_other_conditions' => 'General eligibility note',
    ])->assertSessionHasNoErrors()->assertRedirect(wizardUrl($id, 'requirements'));
    $id->refresh();
    expect($id->description)->toBe('An ID used to prove identity.')
        ->and($id->purpose)->toBe('Keep existing purpose')
        ->and($id->official_link)->toBe('https://example.gov.ph')->and($id->official_sources)->toBe('Keep existing source')
        ->and($id->validity)->toBe('Until age 60')->and($id->processing_time)->toBe('3–5 working days')
        ->and($id->eligibility_min_age)->toBe(18)->and($id->eligibility_residency_custom)->toBe('Example municipality');
    $this->get(wizardUrl($id, 'basic'))->assertOk()->assertSee('An ID used to prove identity.')->assertSee('General eligibility note');
    $this->get(route('admin.government-ids.edit', $id))->assertOk()->assertSee('Keep existing purpose')->assertDontSee('About this ID');
});

function wizardUrl(GovernmentId $id, string $step): string
{
    return route('admin.government-ids.wizard.show', [$id, $step]);
}

function wizardSaveUrl(GovernmentId $id, string $step): string
{
    return route('admin.government-ids.wizard.save', [$id, $step]);
}

it('creates one ID from basics and reuses it on repeated submission and resume', function () {
    $agency = Agency::create(['name' => 'Issuing agency']);
    $key = $this->get(route('admin.government-ids.create'))->assertOk()->viewData('creationKey');
    $data = ['creation_key' => $key, 'name' => 'Guided ID', 'agency_id' => $agency->id,
        'validity_type' => 'fixed', 'validity_value' => 5, 'validity_unit' => 'year',
        'processing_time_type' => 'same_day', 'description' => 'Saved basic description'];
    $this->post(route('admin.government-ids.wizard.store'), $data)->assertSessionHasNoErrors()
        ->assertRedirect(wizardUrl(GovernmentId::sole(), 'requirements'));
    $id = GovernmentId::sole();
    expect($id->validity)->toBe('5 years')->and($id->processing_time)->toBe('Same day')
        ->and($id->fees()->count())->toBe(0)->and($id->requirementSets()->count())->toBe(1);
    $this->post(route('admin.government-ids.wizard.store'), $data)->assertRedirect(wizardUrl($id, 'requirements'));
    expect(GovernmentId::count())->toBe(1)
        ->and($id->requirementSets()->count())->toBe(1);
    $set = $id->requirementSets()->sole();
    expect($set->application_type)->toBe('new')->and($set->applicant_type)->toBe('all')
        ->and($set->application_type_custom)->toBeNull()->and($set->applicant_type_custom)->toBeNull()
        ->and($set->min_age)->toBeNull()->and($set->max_age)->toBeNull();
    expect(session('id_creation_resume'))->toBe(['id' => $id->id, 'step' => 'requirements']);
    $this->get(route('admin.government-ids.create'))->assertSee('Resume')->assertSee('Guided ID');
    $this->get(wizardUrl($id, 'basic'))->assertSee('Saved basic description');
});

it('saves requirements fees guide and offices to the same ID and reviews actual counts', function () {
    $key = $this->get(route('admin.government-ids.create'))->viewData('creationKey');
    $this->post(route('admin.government-ids.wizard.store'), ['creation_key' => $key, 'name' => 'Complete wizard'])->assertSessionHasNoErrors();
    $id = GovernmentId::sole();
    $case = ['application_type' => 'new', 'applicant_type' => 'all'];
    $set = $id->requirementSets()->sole();
    $this->get(wizardUrl($id, 'requirements'))->assertOk()->assertSee('governmentIdChecklists', false)
        ->assertDontSee('Save Requirements')->assertSee('Requirements draft preview')->assertSee('Requirement cards')->assertSee('xl:grid-cols-2')
        ->assertDontSee('Edit Requirements')->assertSee('Continue →')->assertSee('if (checklists.length) open(checklists[0])', false)
        ->assertDontSee('Who is applying?')->assertDontSee('What kind of application?')
        ->assertDontSee('Applicant label')->assertDontSee('Minimum age')->assertDontSee('Maximum age')
        ->assertDontSee('+ Add checklist')->assertDontSee('Choose an existing checklist')
        ->assertDontSee('deleteChecklist(checklist)', false)->assertDontSee('Edit applicant and application details');

    $document = Document::create(['name' => 'Proof of birth']);
    $reference = GovernmentId::create(['name' => 'Accepted identity card']);
    $checklist = [...$case, 'groups' => [[
        'rule' => 'all', 'condition_type' => 'always', 'title' => 'Proof',
        'ways' => [['required_count' => 1, 'qualification_type' => 'none', 'qualification_scope' => 'every', 'items' => [
            ['type' => 'document', 'document_id' => $document->id, 'submission_format' => 'original'],
            ['type' => 'government_id', 'government_id_id' => $reference->id, 'submission_format' => 'original'],
        ]]],
    ]]];
    $this->putJson(route('admin.government-ids.checklists.update', [$id, $set->id]), $checklist)->assertOk();
    expect($set->groups()->sole()->items()->count())->toBe(2)
        ->and($id->requirementSets()->count())->toBe(1)
        ->and($set->fresh()->applicant_type)->toBe('all')
        ->and($set->fresh()->min_age)->toBeNull()->and($set->fresh()->max_age)->toBeNull();
    $this->post(wizardSaveUrl($id, 'requirements'))->assertRedirect(wizardUrl($id, 'fees'));
    $this->get(wizardUrl($id, 'fees'))->assertOk()->assertSee('governmentIdFeeEditor', false);
    $this->post(wizardSaveUrl($id, 'fees'), ['fees' => [
        ['label' => 'Application', 'type' => 'fixed', 'amount_min' => 100],
        ['label' => 'Delivery', 'type' => 'range', 'amount_min' => 50, 'amount_max' => 80],
    ]])->assertSessionHasNoErrors()->assertRedirect(wizardUrl($id, 'guide'));
    $this->get(wizardUrl($id, 'guide'))->assertOk()->assertSee('governmentIdGuide', false)
        ->assertSee('Optional Link')->assertSee('Link Label')->assertSee('Reminder / Note')->assertSee('Done')
        ->assertSee('Use existing ID information')->assertSee('These blocks use the information already saved for this Government ID.')
        ->assertSee('App preview')->assertSee('Intended resident presentation. The mobile app does not read this guide yet.')
        ->assertSee('Resident guide preview')->assertSee('Your application steps will appear here as you add them.')
        ->assertDontSee('Apply step')->assertDontSee('Save &amp; Continue', false)->assertDontSee('save the main entry')
        ->assertDontSee('+ Add scenario')->assertDontSee('Application type')->assertDontSee('Applicant type');
    $guide = [[...$case, 'id' => $set->id, 'steps' => [
        ['title' => 'Submit application', 'type' => 'general', 'blocks' => [
            ['type' => 'official_link', 'section_title' => 'Apply online', 'content' => ['label' => 'Official portal', 'url' => 'https://example.gov.ph']],
        ]],
    ]]];
    $this->post(wizardSaveUrl($id, 'guide'), ['application_guide_payload' => json_encode($guide)])
        ->assertSessionHasNoErrors()->assertRedirect(wizardUrl($id, 'offices'));
    $office = Office::create(['name' => 'Existing branch']);
    $this->get(wizardUrl($id, 'offices'))->assertOk()->assertSee('Existing branch');
    $this->post(wizardSaveUrl($id, 'offices'), ['office_links_present' => 1, 'office_links' => [[
        'office_id' => $office->id, 'new_application_status' => 'available',
        'renewal_status' => 'unknown', 'replacement_status' => 'unavailable', 'service_notes' => 'Appointment required',
    ]]])->assertSessionHasNoErrors()->assertRedirect(wizardUrl($id, 'review'));
    $review = $this->get(wizardUrl($id, 'review'))->assertOk()->assertDontSee('Applicant Cases')->assertDontSee('Application Types')->assertSee('1 group / 2 items')
        ->assertSee('2 fees')->assertSee('1 step')->assertSee('1 linked office');
    expect($review->viewData('counts'))->toBe(['groups' => 1, 'items' => 2, 'fees' => 2, 'guide' => 1, 'offices' => 1]);
    $this->get(wizardUrl($id, 'fees'))->assertSee('Application')->assertSee('Delivery');
    $this->get(wizardUrl($id, 'requirements'))->assertSee('Proof');
    $this->get(wizardUrl($id, 'guide'))->assertSee('Submit application')->assertSee('Official portal');
    $this->get(wizardUrl($id, 'offices'))->assertSee('Appointment required');
    // A basic section save must preserve every other section.
    $this->post(wizardSaveUrl($id, 'basic'), ['name' => 'Updated basics'])->assertSessionHasNoErrors();
    expect($id->fees()->count())->toBe(2)->and($id->offices()->sole()->pivot->new_application_status)->toBe('available')
        ->and($set->applicationSteps()->count())->toBe(1)->and($set->groups()->sole()->items()->count())->toBe(2);
    $this->post(wizardSaveUrl($id, 'review'))->assertRedirect(route('admin.government-ids.show', $id))
        ->assertSessionMissing('id_creation_resume');
    $this->get(route('admin.government-ids.show', $id))->assertOk();
    $this->get(route('admin.government-ids.edit', $id))->assertOk()->assertSee('Save Changes')
        ->assertSee('governmentIdChecklists', false)->assertSee('governmentIdGuide', false)
        ->assertSee('Who is applying?')->assertSee('+ Add checklist')->assertSee('+ Add scenario')
        ->assertDontSee('App preview')
        ->assertDontSee('Optional Link')->assertDontSee('Use existing ID information')
        ->assertSee('Choose an existing checklist')->assertSee('deleteChecklist(checklist)', false)
        ->assertDontSee('Government ID creation steps')->assertDontSee('Save &amp; Continue', false);
});

it('allows every optional section to be empty and guide creation without requirements', function () {
    $key = $this->get(route('admin.government-ids.create'))->viewData('creationKey');
    $this->post(route('admin.government-ids.wizard.store'), ['creation_key' => $key, 'name' => 'Minimal ID'])
        ->assertSessionHasNoErrors();
    $id = GovernmentId::sole();
    $set = $id->requirementSets()->sole();
    foreach (['requirements' => 'fees', 'fees' => 'guide', 'guide' => 'offices', 'offices' => 'review'] as $step => $next) {
        $this->get(wizardUrl($id, $step))->assertOk();
        $this->post(wizardSaveUrl($id, $step))->assertSessionHasNoErrors()->assertRedirect(wizardUrl($id, $next));
    }
    $this->get(wizardUrl($id, 'review'))->assertSee('Not added yet');
    $this->get(wizardUrl($id, 'guide'))->assertSee('Requirements are not complete yet');
    $this->post(wizardSaveUrl($id, 'guide'), ['application_guide_payload' => json_encode([[
        'id' => $set->id, 'application_type' => 'new', 'applicant_type' => 'all',
        'steps' => [['title' => 'Start here', 'type' => 'general', 'blocks' => []]],
    ]])])->assertSessionHasNoErrors();
    expect($id->requirementSets()->sole()->groups()->count())->toBe(0)
        ->and($id->requirementSets()->sole()->applicationSteps()->sole()->title)->toBe('Start here');
    $this->post(wizardSaveUrl($id, 'review'))->assertRedirect(route('admin.government-ids.show', $id));
});

it('keeps legacy sets intact while showing only the neutral set in wizard editors', function () {
    $key = $this->get(route('admin.government-ids.create'))->viewData('creationKey');
    $this->post(route('admin.government-ids.wizard.store'), ['creation_key' => $key, 'name' => 'Legacy compatible ID']);
    $id = GovernmentId::sole();
    $default = $id->requirementSets()->sole();
    $legacy = $id->requirementSets()->create(['application_type' => 'renewal', 'applicant_type' => 'minor']);
    $legacy->applicationSteps()->create(['title' => 'Legacy renewal step', 'type' => 'general', 'sort_order' => 0]);
    $page = $this->get(wizardUrl($id, 'requirements'))->assertOk();
    expect(array_column($page->viewData('checklists'), 'id'))->toBe([$default->id]);
    $this->get(wizardUrl($id, 'guide'))->assertOk()->assertDontSee('Legacy renewal step');
    $this->post(wizardSaveUrl($id, 'guide'), ['application_guide_payload' => json_encode([[
        'id' => $default->id, 'application_type' => 'new', 'applicant_type' => 'all', 'steps' => [],
    ]])])->assertSessionHasNoErrors();
    expect($id->requirementSets()->count())->toBe(2)
        ->and($legacy->fresh()->max_age)->toBe(17)
        ->and($legacy->applicationSteps()->sole()->title)->toBe('Legacy renewal step');
    $this->get(route('admin.government-ids.edit', $id))->assertOk()->assertSee('Legacy renewal step');
});

it('maps an old cases resume to requirements without exposing the removed step', function () {
    $id = GovernmentId::create(['name' => 'Saved creation']);
    $response = $this->withSession(['id_creation_resume' => ['id' => $id->id, 'step' => 'cases']])
        ->get(route('admin.government-ids.create'))->assertOk()
        ->assertSee(wizardUrl($id, 'requirements'), false)->assertDontSee(wizardUrl($id, 'cases'), false);
    expect($response->viewData('resume')['step'])->toBe('requirements');
});

it('keeps validation on the current step preserves input and does not write partial data', function () {
    $key = $this->get(route('admin.government-ids.create'))->viewData('creationKey');
    $this->from(route('admin.government-ids.create'))->post(route('admin.government-ids.wizard.store'), [
        'creation_key' => $key, 'name' => '', 'description' => 'Keep my description',
    ])->assertRedirect(route('admin.government-ids.create'))->assertSessionHasErrors('name')->assertSessionHasInput('description', 'Keep my description');
    expect(GovernmentId::count())->toBe(0);
    $id = GovernmentId::create(['name' => 'Validation ID']);
    $this->from(wizardUrl($id, 'fees'))->post(wizardSaveUrl($id, 'fees'), ['fees' => [['label' => 'Keep fee', 'type' => 'fixed']]])
        ->assertRedirect(wizardUrl($id, 'fees'))->assertSessionHasErrors('fees.0.amount_min')->assertSessionHasInput('fees.0.label', 'Keep fee');
    $this->get(wizardUrl($id, 'fees'))->assertOk()->assertSee('Keep fee')->assertSee('Enter the fee amount.');
    $raw = '[broken';
    $this->from(wizardUrl($id, 'guide'))->post(wizardSaveUrl($id, 'guide'), ['application_guide_payload' => $raw])
        ->assertRedirect(wizardUrl($id, 'guide'))->assertSessionHasErrors('application_guide_payload')->assertSessionHasInput('application_guide_payload', $raw);
    $this->from(wizardUrl($id, 'offices'))->post(wizardSaveUrl($id, 'offices'), ['office_links_present' => 1, 'office_links' => [['office_id' => 99999]]])
        ->assertRedirect(wizardUrl($id, 'offices'))->assertSessionHasErrors('office_links.0.office_id');
    expect($id->fees()->count())->toBe(0)->and($id->requirementSets()->count())->toBe(0)->and($id->offices()->count())->toBe(0);
});

it('rejects foreign guide scenarios and protects wizard routes with existing CMS authorization', function () {
    $id = GovernmentId::create(['name' => 'Owned ID']);
    $other = GovernmentId::create(['name' => 'Other ID']);
    $set = $other->requirementSets()->create(['applicant_type' => 'all', 'application_type' => 'new']);
    $this->postJson(wizardSaveUrl($id, 'guide'), ['application_guide_payload' => json_encode([[
        'id' => $set->id, 'applicant_type' => 'all', 'application_type' => 'new', 'steps' => [],
    ]])])->assertUnprocessable();
    $this->get(wizardUrl($id, 'invalid'))->assertNotFound();
    auth()->logout();
    $this->get(wizardUrl($id, 'review'))->assertRedirect(route('login'));
    foreach ([['role' => 'resident'], ['role' => 'researcher', 'is_active' => false]] as $attributes) {
        $this->actingAs(User::factory()->create($attributes));
        $this->get(wizardUrl($id, 'review'))->assertForbidden();
        $this->postJson(wizardSaveUrl($id, 'fees'))->assertForbidden();
        $this->postJson(route('admin.government-ids.wizard.cases', $id))->assertForbidden();
    }
});
