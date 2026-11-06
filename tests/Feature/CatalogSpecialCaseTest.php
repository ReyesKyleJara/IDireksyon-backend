<?php

use App\Models\{Document, GovernmentId, Office, User};
use App\Services\{CatalogRevision, CatalogSpecialCases};

beforeEach(fn () => $this->actingAs(User::factory()->create(['role' => 'researcher'])));

function specialGuideInput(array $overrides = []): array
{
    return array_replace([
        'title' => 'Delayed Registration of Birth', 'description' => 'Guidance for individuals whose birth was never officially registered.',
        'action_label' => 'Learn about delayed registration', 'assistance_title' => "Don't have a Birth Certificate yet?",
        'assistance_description' => 'If you do not have any PSA, NSO, or Local Civil Registry birth record, you may need to apply for Delayed Registration of Birth first.',
        'introduction' => 'SYNTHETIC FIXTURE — not government guidance.',
        'before_you_begin' => 'FIXTURE: No physical copy does not prove an unregistered birth. Distinguish an existing registration without a copy, a local registration without a PSA record, and a birth never registered. Verify existing records or endorsement before registration.',
        'status' => 'draft', 'notes' => 'FIXTURE: confirm local instructions with the relevant civil registrar.',
        'sources' => [['title' => 'Fixture official source', 'url' => 'https://example.gov.ph/fixture', 'checked_on' => '2026-10-29']],
        'requirements_payload' => json_encode([['client_key' => 'special-case', 'application_type' => 'new', 'applicant_type' => 'all', 'groups' => [[
            'rule' => 'all', 'condition_type' => 'always', 'ways' => [['required_count' => 1, 'qualification_type' => 'none', 'qualification_scope' => 'every',
                'items' => [['type' => 'custom', 'custom_name' => 'Fixture case-only evidence', 'submission_format' => 'original']]]],
        ]]]]),
        'application_guide_payload' => json_encode([['application_type' => 'new', 'applicant_type' => 'all', 'steps' => [
            ['title' => 'Fixture: verify whether the birth was registered', 'type' => 'general', 'blocks' => [['type' => 'instructions', 'content' => ['items' => [['body' => ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => str_repeat('Long fixture instruction. ', 80)]]]]]]]]]]],
            ['title' => 'Fixture: ask the appropriate office for verified next steps', 'type' => 'general', 'blocks' => []],
        ]]]),
    ], $overrides);
}

it('saves reopens edits and projects an independent JSON guide without planner or inventory records', function ($model, $resource) {
    $owner = $model::create(['name' => 'PSA Birth Certificate — fixture']);
    $normal = $owner->requirementSets()->create(['application_type' => 'copy', 'applicant_type' => 'all', 'request_channel' => ['label' => 'In Person', 'channel' => 'outlet', 'troubleshooting' => [['title' => 'Legacy note', 'body' => 'Keep this note']]]]);
    $normal->applicationSteps()->create(['title' => 'Normal copy step', 'type' => 'general']);
    $owner->requirementSets()->create(['application_type' => 'copy', 'applicant_type' => 'all', 'request_channel' => ['label' => 'Online', 'channel' => 'online']]);
    $office = Office::create(['name' => 'SYNTHETIC civil registry counter', 'status' => 'active']); $owner->offices()->attach($office);
    $fee = $owner->fees()->create(['label' => 'SYNTHETIC assessment', 'type' => 'fixed', 'amount_min' => 12]);
    $snapshot = ['documents' => Document::count(), 'ids' => GovernmentId::count(), 'paths' => $owner->requirementSets()->count(), 'prerequisites' => $owner->prerequisites()->count()];
    $url = route('admin.'.$resource.'.special-cases.save', $owner);
    $this->get(route('admin.'.$resource.'.special-cases.create', $owner))->assertOk()->assertSee('Save special case guide');
    $this->post($url, specialGuideInput(['fee_ids' => [$fee->id], 'office_ids' => [$office->id], '_catalog_revision' => CatalogRevision::token($owner)]))->assertSessionHasNoErrors();
    $case = CatalogSpecialCases::all($owner->fresh())[0];
    $this->get(route('admin.'.$resource.'.special-cases.edit', [$owner, $case['id']]))->assertOk()->assertSee('Delayed Registration of Birth')->assertSee('Fixture case-only evidence');
    $this->post($url, specialGuideInput(['case_id' => $case['id'], 'description' => 'Edited fixture description', 'fee_ids' => [$fee->id], 'office_ids' => [$office->id]]))->assertSessionHasNoErrors();
    $api = '/api/'.$resource.'/'.$owner->id;
    $response = $this->getJson($api)->assertOk()->assertJsonPath('data.special_cases.0.description', 'Edited fixture description')
        ->assertJsonCount(2, 'data.special_cases.0.steps')->assertJsonPath('data.special_cases.0.requirement_sets.0.groups.0.ways.0.items.0.name', 'Fixture case-only evidence')
        ->assertJsonPath('data.special_cases.0.offices.0.name', 'SYNTHETIC civil registry counter')->assertJsonPath('data.request_paths.0.guide.0.title', 'Normal copy step')
        ->assertJsonPath('data.request_paths.0.troubleshooting.0.title', 'Legacy note');
    $this->post(route('admin.'.$resource.'.edit-wizard.save', [$owner, 'basic']), ['name' => $owner->name, 'research_metadata' => ['status' => 'draft', 'sources' => []]])->assertSessionHasNoErrors();
    expect(CatalogSpecialCases::all($owner->fresh())[0]['id'])->toBe($case['id']);
    expect(['documents' => Document::count(), 'ids' => GovernmentId::count(), 'paths' => $owner->requirementSets()->count(), 'prerequisites' => $owner->prerequisites()->count()])->toBe($snapshot);
    expect($normal->fresh()->request_channel['troubleshooting'][0]['title'])->toBe('Legacy note');
    if (getenv('IDIREKSYON_CAPTURE_SPECIAL_CASE') === '1' && $resource === 'documents') {
        file_put_contents(dirname(base_path()).'/IDireksyon-frontend/test/fixtures/catalog/special-case.json', json_encode($response->json('data'), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
    }
})->with([[Document::class, 'documents'], [GovernmentId::class, 'government-ids']]);

it('keeps empty sections multiple cases ordering and removal local to the assistance list', function () {
    $owner = Document::create(['name' => 'Fixture']);
    $url = route('admin.documents.special-cases.save', $owner);
    $empty = ['requirements_payload' => json_encode([['client_key' => 'special-case', 'application_type' => 'new', 'applicant_type' => 'all', 'groups' => []]]),
        'application_guide_payload' => json_encode([['application_type' => 'new', 'applicant_type' => 'all', 'steps' => []]]), 'sources' => [], 'before_you_begin' => '', 'notes' => ''];
    $this->post($url, specialGuideInput($empty))->assertSessionHasNoErrors();
    $this->post($url, specialGuideInput([...$empty, 'title' => 'Second fixture']))->assertSessionHasNoErrors();
    $cases = CatalogSpecialCases::all($owner->fresh());
    $action = route('admin.documents.special-cases.action', $owner);
    $this->post($action, ['case_id' => $cases[1]['id'], 'action' => 'up'])->assertSessionHasNoErrors();
    expect(CatalogSpecialCases::all($owner->fresh())[0]['title'])->toBe('Second fixture');
    $this->getJson('/api/documents/'.$owner->id)->assertJsonPath('data.special_cases.0.requirement_sets', [])->assertJsonPath('data.special_cases.0.fees', [])->assertJsonPath('data.special_cases.0.offices', []);
    $this->post($action, ['case_id' => $cases[1]['id'], 'action' => 'delete'])->assertSessionHasNoErrors();
    expect(Document::count())->toBe(1)->and($owner->requirementSets()->count())->toBe(0)->and(CatalogSpecialCases::all($owner->fresh()))->toHaveCount(1);
});

it('rejects incomplete unsafe foreign stale and unauthorised changes without losing saved content', function () {
    $owner = Document::create(['name' => 'Fixture']); $other = Document::create(['name' => 'Other']);
    $fee = $other->fees()->create(['label' => 'Foreign', 'type' => 'free']);
    $url = route('admin.documents.special-cases.save', $owner);
    foreach ([['title' => ''], ['action_label' => ''], ['fee_ids' => [$fee->id]], ['status' => 'reviewed', 'sources' => []], ['sources' => [['title' => 'Unsafe', 'url' => 'javascript:alert(1)']]], ['requirements_payload' => 'bad JSON']] as $invalid) {
        $this->postJson($url, specialGuideInput($invalid))->assertUnprocessable();
    }
    $this->post($url, specialGuideInput())->assertSessionHasNoErrors();
    $case = CatalogSpecialCases::all($owner->fresh())[0];
    $this->postJson(route('admin.documents.special-cases.save', $other), specialGuideInput(['case_id' => $case['id']]))->assertNotFound();
    $old = CatalogRevision::token($owner);
    $owner->update(['description' => 'Another editor']);
    $this->from(route('admin.documents.special-cases.edit', [$owner, $case['id']]))->post($url, specialGuideInput(['case_id' => $case['id'], '_catalog_revision' => $old]))->assertSessionHasErrors('_catalog_revision')->assertSessionHasInput('title', 'Delayed Registration of Birth');
    expect(CatalogSpecialCases::all($owner->fresh()))->toHaveCount(1);
    $this->actingAs(User::factory()->create(['role' => 'resident']));
    $this->postJson($url, specialGuideInput())->assertForbidden();
});

it('retains draft office links without exposing unpublished office details', function () {
    $owner = Document::create(['name' => 'Office visibility fixture']);
    $office = Office::create(['name' => 'Unpublished fixture counter']);
    $owner->offices()->attach($office);
    $this->post(route('admin.documents.special-cases.save', $owner), specialGuideInput(['office_ids' => [$office->id]]))->assertSessionHasNoErrors();
    $this->getJson('/api/documents/'.$owner->id)->assertJsonPath('data.special_cases.0.offices', []);
    expect(CatalogSpecialCases::all($owner->fresh())[0]['office_ids'])->toBe([$office->id]);
});

it('reuses linked requirement identities and preserves explicit step order without creating dependency edges', function () {
    $owner = Document::create(['name' => 'Parent fixture']);
    $reference = Document::create(['name' => 'Linked evidence fixture']);
    $data = specialGuideInput();
    $requirements = json_decode($data['requirements_payload'], true);
    $requirements[0]['groups'][0]['ways'][0]['items'][] = ['type' => 'document', 'document_id' => $reference->id, 'submission_format' => 'original'];
    $data['requirements_payload'] = json_encode($requirements);
    $guide = json_decode($data['application_guide_payload'], true);
    $guide[0]['steps'] = array_reverse($guide[0]['steps']);
    $data['application_guide_payload'] = json_encode($guide);
    $this->post(route('admin.documents.special-cases.save', $owner), $data)->assertSessionHasNoErrors();
    $this->getJson('/api/documents/'.$owner->id)->assertJsonPath('data.special_cases.0.requirement_sets.0.groups.0.ways.0.items.1.name', 'Linked evidence fixture')
        ->assertJsonPath('data.special_cases.0.steps.0.title', 'Fixture: ask the appropriate office for verified next steps');
    expect(Document::count())->toBe(2)->and($owner->requirementSets()->count())->toBe(0)->and($owner->prerequisites()->count())->toBe(0);
});


it('creates a simple universal Draft guide with an automatic action label and no normal catalog tasks', function ($model, $resource) {
    $owner = $model::create(['name' => $resource === 'documents' ? 'Clearance service fixture' : 'Membership ID fixture']);
    $create = $this->get(route('admin.'.$resource.'.special-cases.create', $owner))->assertOk()
        ->assertSee('When Does This Apply?')->assertSee('What Should the Applicant Do?')->assertSee('Additional Information')->assertSee('Official Source')
        ->assertDontSee('Button label')->assertDontSee('Customize the assistance card')->assertDontSee('Introductory explanation')->assertDontSee('Before You Begin')->assertDontSee('Delayed Registration of Birth');
    $dom = new DOMDocument(); @$dom->loadHTML($create->getContent()); $xpath = new DOMXPath($dom);
    expect($xpath->query('//section[@aria-label="Special case guide details"]//input[@required]')->length)->toBe(1)
        ->and($xpath->query('//section[@aria-label="Special case guide details"]//input[@required and @name="title"]')->length)->toBe(1)
        ->and($xpath->query('//input[@name="status" and @value="draft" and @checked]')->length)->toBe(1);
    $url = route('admin.'.$resource.'.special-cases.save', $owner);
    $this->post($url, ['simplified_editor' => 1, 'title' => 'Applying through a representative'])->assertSessionHasNoErrors();
    $case = CatalogSpecialCases::all($owner->fresh())[0];
    expect($case['action_label'])->toBe('View guide')->and($case['status'])->toBe('draft')->and($case['description'])->toBe('')
        ->and($case['requirements'])->toBe([])->and($case['steps'])->toBe([])->and($owner->requirementSets()->count())->toBe(0)->and($owner->prerequisites()->count())->toBe(0);
    $this->post($url, ['simplified_editor' => 1, 'case_id' => $case['id'], 'title' => 'Updated title', 'description' => 'For applicants unable to attend personally.', 'introduction' => 'Contact the issuing office and confirm the required authorization.', 'notes' => 'Check the accepted identification.', 'official_source' => 'https://example.gov.ph/reference'])->assertSessionHasNoErrors();
    $this->getJson('/api/'.$resource.'/'.$owner->id)->assertOk()->assertJsonPath('data.special_cases.0.title', 'Updated title')
        ->assertJsonPath('data.special_cases.0.introduction', 'Contact the issuing office and confirm the required authorization.')
        ->assertJsonPath('data.special_cases.0.notes', 'Check the accepted identification.')->assertJsonPath('data.special_cases.0.sources.0.url', 'https://example.gov.ph/reference');
    $edit = $this->get(route('admin.'.$resource.'.special-cases.edit', [$owner, $case['id']]))->assertOk()->assertSee('Updated title')->assertSee('Check the accepted identification.');
    if (getenv('SPECIAL_CASE_BROWSER_DIR')) {
        file_put_contents(getenv('SPECIAL_CASE_BROWSER_DIR').'/'.$resource.'-create.html', $create->getContent());
        file_put_contents(getenv('SPECIAL_CASE_BROWSER_DIR').'/'.$resource.'-edit.html', $edit->getContent());
    }
})->with([[Document::class, 'documents'], [GovernmentId::class, 'government-ids']]);

it('retains legacy advanced content when only the simplified guide text is edited', function ($model, $resource) {
    $owner = $model::create(['name' => 'Advanced legacy fixture']);
    $fee = $owner->fees()->create(['label' => 'Saved fee', 'type' => 'free']);
    $office = Office::create(['name' => 'Saved office', 'status' => 'active']); $owner->offices()->attach($office);
    $url = route('admin.'.$resource.'.special-cases.save', $owner);
    $this->post($url, specialGuideInput(['fee_ids' => [$fee->id], 'office_ids' => [$office->id]]))->assertSessionHasNoErrors();
    $metadata = $owner->fresh()->research_metadata;
    $metadata['special_cases'][0]['historical_extra'] = 'Keep previously saved metadata';
    $owner->update(['research_metadata' => $metadata]);
    $before = CatalogSpecialCases::all($owner->fresh())[0];
    $this->post($url, ['simplified_editor' => 1, 'case_id' => $before['id'], 'title' => 'Updated legacy title', 'description' => 'Updated applicability'])->assertSessionHasNoErrors();
    $after = CatalogSpecialCases::all($owner->fresh())[0];
    foreach (['requirements', 'steps', 'before_you_begin', 'notes', 'sources', 'fee_ids', 'office_ids', 'action_label', 'assistance_title', 'assistance_description', 'historical_extra'] as $field) expect($after[$field])->toBe($before[$field]);
    $page = $this->get(route('admin.'.$resource.'.special-cases.edit', [$owner, $before['id']]))->assertOk()->assertSee('Edit additional saved content')->assertSee('Saved preparation notes')->assertSee('Existing resident card content')->assertSee('Fixture case-only evidence');
    if (getenv('SPECIAL_CASE_BROWSER_DIR')) file_put_contents(getenv('SPECIAL_CASE_BROWSER_DIR').'/'.$resource.'-advanced.html', $page->getContent());
    // Explicitly clearing the checkbox groups should unlink them without deleting reusable records.
    $this->post($url, ['simplified_editor' => 1, 'advanced_content_present' => 1, 'case_id' => $before['id'], 'title' => 'Updated legacy title'])->assertSessionHasNoErrors();
    $saved = CatalogSpecialCases::all($owner->fresh())[0];
    expect($saved['fee_ids'])->toBe([])->and($saved['office_ids'])->toBe([])->and($saved['requirements'])->toBe($before['requirements'])
        ->and($owner->fees()->count())->toBe(1)->and($owner->offices()->count())->toBe(1);
})->with([[Document::class, 'documents'], [GovernmentId::class, 'government-ids']]);

it('keeps research verification and source metadata validation in the simplified editor', function ($model, $resource) {
    $owner = $model::create(['name' => 'Source fixture']); $url = route('admin.'.$resource.'.special-cases.save', $owner);
    $base = ['simplified_editor' => 1, 'title' => 'Service exception', 'status' => 'reviewed', 'official_source' => 'https://example.gov.ph/reference'];
    foreach ([[], ['source_checked_on' => null], ['official_source' => 'javascript:alert(1)'], ['sources' => ['invalid']], ['title' => '']] as $invalid) $this->postJson($url, [...$base, ...$invalid])->assertUnprocessable();
    expect(CatalogSpecialCases::all($owner->fresh()))->toBe([]);
    $this->post($url, [...$base, 'source_checked_on' => now('Asia/Manila')->toDateString(), 'sources' => [['title' => 'Agency source', 'scope' => 'Service guidance', 'section' => 'Exceptions']]])->assertSessionHasNoErrors();
    $case = CatalogSpecialCases::all($owner->fresh())[0];
    expect($case['sources'][0]['title'])->toBe('Agency source')->and($case['sources'][0]['scope'])->toBe('Service guidance')->and($case['status'])->toBe('reviewed');
    $this->postJson($url, [...$base, 'case_id' => $case['id'], 'source_checked_on' => null])->assertUnprocessable();
    $this->post($url, ['simplified_editor' => 1, 'case_id' => $case['id'], 'title' => $case['title'], 'status' => 'draft', 'official_source' => ''])->assertSessionHasNoErrors();
    expect(CatalogSpecialCases::all($owner->fresh())[0]['sources'])->toBe([]);
})->with([[Document::class, 'documents'], [GovernmentId::class, 'government-ids']]);


it('accepts the compact browser form payload and retains extra references when replacing or clearing its main source', function ($model, $resource) {
    $owner = $model::create(['name' => 'Compact payload fixture']); $url = route('admin.'.$resource.'.special-cases.save', $owner);
    $scenario = ['application_type' => 'new', 'applicant_type' => 'all'];
    $payload = ['simplified_editor' => 1, 'advanced_content_present' => 1, 'title' => 'Service exception', 'description' => '', 'introduction' => '', 'notes' => '', 'official_source' => '', 'source_checked_on' => '', 'status' => 'draft', 'sources' => [['title' => 'Official reference', 'scope' => '', 'published_on' => '', 'section' => '']], 'requirements_payload' => json_encode([['client_key' => 'special-case', ...$scenario, 'groups' => []]]), 'application_guide_payload' => json_encode([[...$scenario, 'steps' => []]])];
    $this->post($url, $payload)->assertSessionHasNoErrors();
    $case = CatalogSpecialCases::all($owner->fresh())[0];
    expect($case['sources'])->toBe([])->and($case['status'])->toBe('draft');
    $this->post($url, ['simplified_editor' => 1, 'case_id' => $case['id'], 'title' => $case['title'], 'official_source' => 'https://example.gov.ph/primary', 'source_checked_on' => '2026-10-29', 'sources' => [['title' => 'Original source title', 'scope' => 'Original context'], ['title' => 'Extra reference', 'url' => 'https://example.gov.ph/extra', 'checked_on' => '2026-10-29']]])->assertSessionHasNoErrors();
    $this->post($url, ['simplified_editor' => 1, 'case_id' => $case['id'], 'title' => $case['title'], 'official_source' => 'https://example.gov.ph/replacement'])->assertSessionHasNoErrors();
    $sources = CatalogSpecialCases::all($owner->fresh())[0]['sources'];
    expect($sources[0]['title'])->toBe('Original source title')->and($sources[0]['scope'])->toBe('Original context')->and($sources[0]['checked_on'])->toBe('2026-10-29')->and($sources[1]['url'])->toBe('https://example.gov.ph/extra');
    $this->post($url, ['simplified_editor' => 1, 'case_id' => $case['id'], 'title' => $case['title'], 'official_source' => ''])->assertSessionHasNoErrors();
    expect(CatalogSpecialCases::all($owner->fresh())[0]['sources'])->toBe([$sources[1]]);
})->with([[Document::class, 'documents'], [GovernmentId::class, 'government-ids']]);
