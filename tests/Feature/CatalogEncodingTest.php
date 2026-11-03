<?php

use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\Office;
use App\Models\User;
use App\Services\CatalogRevision;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['role' => 'researcher']));
});

function encodingChannel(array $overrides = []): array
{
    return array_replace(['label' => 'Online copy', 'channel' => 'online', 'provider' => 'Official issuer',
        'portal_url' => 'https://example.gov.ph/request', 'fulfillment' => ['download'], 'appointment' => 'not_required',
        'processing' => '20 minutes after complete submission', 'office_ids' => [], 'fee_ids' => [],
        'troubleshooting' => [['title' => 'No record found', 'body' => 'Contact the issuer before submitting another request.', 'url' => 'https://example.gov.ph/help']]], $overrides);
}

it('round trips six representative workflows through saved wizard steps and typed document API', function ($name, $transaction, $mode) {
    $key = $this->get(route('admin.documents.create'))->assertOk()->viewData('creationKey');
    $research = ['status' => 'reviewed', 'sources' => [['title' => 'Fixture official reference', 'url' => 'https://example.gov.ph/reference', 'checked_on' => now()->toDateString(), 'scope' => 'Fixture channel']], 'validity_note' => 'Check the receiving agency recency rule.'];
    $this->post(route('admin.documents.wizard.store'), ['creation_key' => $key, 'name' => $name, 'guidance_mode' => $mode, 'research_metadata' => $research])->assertSessionHasNoErrors();
    $document = Document::where('name', $name)->sole();
    $set = $document->requirementSets()->sole();
    $channel = encodingChannel(match ($transaction) {
        'copy' => ['label' => 'Outlet certified copy', 'channel' => 'outlet', 'fulfillment' => ['pickup']],
        'renewal' => ['label' => 'Online renewal appointment', 'appointment' => 'required', 'fulfillment' => ['pickup']],
        'new' => ['label' => 'Local counter application', 'channel' => 'hybrid', 'fulfillment' => ['pickup']],
        'authentication' => ['label' => 'Authentication appointment', 'appointment' => 'required', 'fulfillment' => ['pickup'], 'processing' => 'Fixture: five working days after acceptance'],
        'retrieval' => ['label' => 'Member portal retrieval', 'fulfillment' => ['view_online'], 'processing' => 'Fixture: after account verification'],
        default => ['label' => 'Retained informational research', 'fulfillment' => []],
    });
    if ($transaction === 'new') {
        $fee = $document->fees()->create(['label' => 'Fixture local assessment', 'type' => 'fixed', 'amount_min' => 250]);
        $office = Office::create(['name' => 'Fixture local counter', 'address' => 'Synthetic test address', 'status' => 'active']);
        $document->offices()->attach($office);
        $channel['fee_ids'] = [$fee->id];
        $channel['office_ids'] = [$office->id];
    }
    $this->post(route('admin.documents.channels', $document), ['path_id' => $set->id, 'application_type' => $transaction, 'applicant_type' => 'all',
        'fee_ids_scope' => 'selected', 'office_ids_scope' => 'selected', 'request_channel' => $channel])->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.documents.edit-wizard.show', [$document, 'channels', 'path' => $set->id]));
    $this->putJson(route('admin.documents.checklists.update', [$document, $set]), ['application_type' => $transaction, 'applicant_type' => 'all', 'groups' => [[
        'rule' => 'all', 'condition_type' => 'representative', 'ways' => [['required_count' => 1, 'qualification_type' => 'none', 'qualification_scope' => 'every',
            'items' => [['type' => 'custom', 'custom_name' => 'Proof of authority', 'submission_format' => 'original']]]],
    ]]])->assertOk();
    $this->post(route('admin.documents.edit-wizard.save', [$document, 'guide']), ['application_guide_payload' => json_encode([[
        'id' => $set->id, 'application_type' => $transaction, 'applicant_type' => 'all',
        'steps' => [['title' => 'Retrieve the record through the official channel', 'type' => 'general', 'blocks' => []]],
    ]])])->assertSessionHasNoErrors();
    foreach (['channels', 'requirements', 'fees', 'guide', 'offices', 'review', 'basic'] as $step) {
        $this->get(route('admin.documents.edit-wizard.show', [$document, $step]))->assertOk()->assertSee($name);
    }
    $result = $this->getJson('/api/documents/'.$document->id)->assertOk()->assertJsonPath('data.record_type', 'document')
        ->assertJsonPath('data.guidance_mode', $mode)->assertJsonPath('data.request_paths.0.label', $channel['label'])
        ->assertJsonPath('data.request_paths.0.channel', $channel['channel'])
        ->assertJsonPath('data.request_paths.0.fulfillment', $channel['fulfillment'])
        ->assertJsonCount($transaction === 'new' ? 1 : 0, 'data.request_paths.0.offices')
        ->assertJsonCount($transaction === 'new' ? 1 : 0, 'data.request_paths.0.fees')
        ->assertJsonPath('data.request_paths.0.guide.0.title', 'Retrieve the record through the official channel')
        ->assertJsonPath('data.request_paths.0.troubleshooting.0.title', 'No record found')
        ->assertJsonPath('data.request_paths.0.requirement_sets.0.groups.0.condition_type', 'representative');
    expect($result->json('data.research_metadata.sources.0.checked_on'))->toBe(now()->toDateString());
    // Opt-in contract capture from isolated CMS HTTP tests; never touches the real catalog.
    if (getenv('IDIREKSYON_CAPTURE_CONTRACTS') === '1') {
        $directory = dirname(base_path()).'/IDireksyon-frontend/test/fixtures/catalog';
        if (! is_dir($directory)) mkdir($directory, 0755, true);
        file_put_contents($directory.'/'.$transaction.'.json', json_encode($result->json('data'), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
    }
    $this->getJson('/api/documents')->assertOk()->assertJsonPath('data.0.name', $name);
})->with([
    ['PSA Birth Certificate — fixture', 'copy', 'guided'], ['NBI Clearance — fixture', 'renewal', 'guided'],
    ['Business Permit — fixture', 'new', 'guided'], ['DFA Apostille — fixture', 'authentication', 'guided'],
    ['PhilHealth MDR — fixture', 'retrieval', 'guided'], ['SRB — fixture', 'replacement', 'informational_only'],
]);

it('isolates channels and rejects foreign fee office and scenario references', function () {
    $document = Document::create(['name' => 'Owner']);
    $other = Document::create(['name' => 'Other']);
    $fee = $document->fees()->create(['label' => 'Owner fee', 'type' => 'free']);
    $foreignFee = $other->fees()->create(['label' => 'Foreign', 'type' => 'free']);
    $office = Office::create(['name' => 'Owner office', 'status' => 'active']); $document->offices()->attach($office);
    $set = $document->requirementSets()->create(['application_type' => 'copy', 'applicant_type' => 'all']);
    $base = ['path_id' => $set->id, 'application_type' => 'copy', 'applicant_type' => 'all', 'fee_ids_scope' => 'selected', 'office_ids_scope' => 'selected'];
    $this->postJson(route('admin.documents.channels', $document), [...$base, 'request_channel' => encodingChannel(['fee_ids' => [$foreignFee->id]])])->assertUnprocessable();
    $this->post(route('admin.documents.channels', $document), [...$base, 'request_channel' => encodingChannel(['fee_ids' => [$fee->id], 'office_ids' => [$office->id]])])->assertSessionHasNoErrors();
    $this->post(route('admin.documents.channels', $document), ['application_type' => 'copy', 'applicant_type' => 'all', 'fee_ids_scope' => 'selected', 'office_ids_scope' => 'selected', 'request_channel' => encodingChannel(['label' => 'Download only'])])->assertSessionHasNoErrors();
    $this->getJson('/api/documents/'.$document->id)->assertJsonCount(2, 'data.request_paths')->assertJsonCount(1, 'data.request_paths.0.fees')
        ->assertJsonCount(1, 'data.request_paths.0.offices')->assertJsonPath('data.request_paths.1.fees', [])->assertJsonPath('data.request_paths.1.offices', []);
    $this->postJson(route('admin.documents.channels', $other), [...$base, 'request_channel' => encodingChannel()])->assertNotFound();
    $this->postJson(route('admin.documents.edit-wizard.save', [$document, 'fees']), ['fees' => []])->assertUnprocessable();
    expect($document->fees()->count())->toBe(1);
});

it('keeps channel management in Step 2 and preserves selected context across saves', function () {
    $document = Document::create(['name' => 'Navigation fixture']);
    $set = $document->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'all']);
    foreach (['requirements', 'fees', 'guide'] as $step) {
        $this->get(route('admin.documents.edit-wizard.show', [$document, $step, 'path' => $set->id]))
            ->assertOk()->assertSee('Create a request method in Step 2 first.')->assertSee('Manage Request Methods')->assertDontSee('+ Add Request Method')
            ->assertSee('name="path" value="'.$set->id.'"', false);
    }
    $this->get(route('admin.documents.edit-wizard.show', [$document, 'offices']))
        ->assertOk()->assertDontSee('Create a request method in Step 2 first.')
        ->assertDontSee('Manage Request Methods')->assertDontSee('name="path"', false)
        ->assertSee('No Request Method is required.');
    $this->get(route('admin.documents.edit-wizard.show', [$document, 'channels']))
        ->assertOk()->assertSee('+ Add Request Method')->assertSee('Save Method')->assertDontSee('path=0');
    $this->post(route('admin.documents.edit-wizard.save', [$document, 'requirements']), ['path' => $set->id])
        ->assertSessionHasNoErrors()->assertRedirect(route('admin.documents.edit-wizard.show', [$document, 'fees', 'path' => $set->id]));
    $this->post(route('admin.documents.edit-wizard.save', [$document, 'guide']), ['path' => $set->id,
        'application_guide_payload' => json_encode([['id' => $set->id, 'application_type' => 'new', 'applicant_type' => 'all', 'steps' => []]])])
        ->assertSessionHasNoErrors()->assertRedirect(route('admin.documents.edit-wizard.show', [$document, 'offices', 'path' => $set->id]));
});

it('rejects stale nested saves without losing either saved content or submitted input', function () {
    $document = Document::create(['name' => 'Original']);
    $revision = CatalogRevision::token($document);
    $set = $document->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'all']);
    $set->groups()->create(['title' => 'Another editor saved this', 'rule' => 'all']);
    $url = route('admin.documents.edit-wizard.save', [$document, 'basic']);
    $this->from(route('admin.documents.edit-wizard.show', [$document, 'basic']))->post($url, ['name' => 'Stale draft', '_catalog_revision' => $revision])->assertSessionHasErrors('_catalog_revision')->assertSessionHasInput('name', 'Stale draft');
    expect($document->fresh()->name)->toBe('Original')->and($set->groups()->count())->toBe(1);
    $this->postJson($url, ['name' => 'Fresh draft', '_catalog_revision' => CatalogRevision::token($document)])->assertRedirect();
    expect($document->fresh()->name)->toBe('Fresh draft');
});

it('saves prerequisites and the checklist atomically and refreshes the editor revision', function () {
    $document = Document::create(['name' => 'Target']);
    $set = $document->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'all']);
    $base = ['application_type' => 'new', 'applicant_type' => 'all', 'groups' => [], 'prerequisites_present' => 1,
        'prerequisites' => [['type' => 'document', 'document_id' => $document->id]]];
    $this->putJson(route('admin.documents.checklists.update', [$document, $set]), $base)->assertUnprocessable();
    expect($document->prerequisites()->count())->toBe(0)->and($set->groups()->count())->toBe(0);
    $response = $this->putJson(route('admin.documents.checklists.update', [$document, $set]), [...$base,
        'prerequisites' => [['type' => 'custom', 'custom_name' => 'Manual action']], '_catalog_revision' => CatalogRevision::token($document)])->assertOk();
    expect($response->json('_catalog_revision'))->toBe(CatalogRevision::token($document));
});

it('preserves hidden content and refuses unverified reviewed metadata unsafe links and destructive rollback', function () {
    $document = Document::create(['name' => 'Visibility']);
    $set = $document->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'all', 'request_channel' => encodingChannel()]);
    $set->applicationSteps()->create(['title' => 'Keep guide', 'type' => 'general']);
    $url = route('admin.documents.edit-wizard.save', [$document, 'basic']);
    $this->postJson($url, ['name' => 'Visibility', 'research_metadata' => ['status' => 'reviewed', 'sources' => []]])->assertUnprocessable();
    $this->postJson($url, ['name' => 'Visibility', 'research_metadata' => ['status' => 'draft', 'sources' => [['title' => 'Bad', 'url' => 'javascript:alert(1)']]]])->assertUnprocessable();
    $this->post($url, ['name' => 'Visibility', 'research_metadata' => ['status' => 'needs_research', 'hidden_sections' => ['guide']]])->assertSessionHasNoErrors();
    $this->getJson('/api/documents/'.$document->id)->assertJsonPath('data.request_paths.0.guide', []);
    expect($set->applicationSteps()->sole()->title)->toBe('Keep guide');
    $migration = require database_path('migrations/2026_10_29_000000_add_catalog_encoding_metadata.php');
    expect(fn () => $migration->down())->toThrow(RuntimeException::class);
    expect($document->fresh()->research_metadata['status'])->toBe('needs_research');
});

it('keeps document and Government ID identities separate and protects channel writes', function () {
    $doc = Document::forceCreate(['id' => 51, 'name' => 'Actual document']);
    $id = GovernmentId::forceCreate(['id' => 51, 'name' => 'Actual ID']);
    $this->getJson('/api/documents/51')->assertJsonPath('data.name', 'Actual document');
    $this->getJson('/api/government-ids/51')->assertJsonPath('data.name', 'Actual ID');
    $this->actingAs(User::factory()->create(['role' => 'resident']));
    $this->postJson(route('admin.documents.channels', $doc), ['request_channel' => encodingChannel()])->assertForbidden();
});
