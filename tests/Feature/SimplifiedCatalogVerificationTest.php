<?php

require_once __DIR__.'/../Support/agency_verification.php';

use App\Models\Document;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

it('publishes a saved verification without an evidence reference or approval and keeps private details out of the API', function (string $type) {
    $record = $type === 'documents' ? Document::create(['name' => 'Fixture Document']) : avRecord();
    $record->update(['research_metadata' => ['status' => 'draft', 'sources' => [
        ['title' => 'Official site', 'url' => 'https://agency.gov.ph', 'checked_on' => '2026-11-10', 'scope' => 'Private source scope'],
        ['title' => 'Official FAQs', 'url' => 'https://agency.gov.ph/faqs', 'checked_on' => '2026-11-12'],
    ]]]);
    $route = "/api/{$type}/{$record->id}";
    $recorder = avSuperAdmin();
    $reviewer = avSuperAdmin();
    $input = ['outcome' => 'documented', 'office_label' => 'Santa Maria Branch', 'method' => 'phone_interview',
        'responded_on' => '2026-02-10', 'scope' => 'Private verification summary',
        'evidence_location' => 'Private storage', 'notes' => 'Private notes'];
    $service = avService();
    expect(fn () => $service->createDraft($recorder, $record, [...$input, 'responded_on' => null]))
        ->toThrow(ValidationException::class);

    $published = $service->createDraft($recorder, $record, $input);
    expect(fn () => $service->submit($recorder, $published))->toThrow(AuthorizationException::class);
    expect($published->review_state)->toBe('published')
        ->and($published->evidence_reference)->toBeNull()
        ->and($published->approved_by)->toBeNull();

    $this->getJson($route)->assertOk()->assertJsonPath('data.verified_information.verification', [
        'office' => 'Santa Maria Branch', 'method' => 'Phone Verification', 'date' => '2026-02-10',
    ])->assertJsonPath('data.verified_information.sources.0.url', 'https://agency.gov.ph')
        ->assertDontSee('Private source scope')->assertDontSee('Private verification summary')
        ->assertDontSee('Private storage')->assertDontSee('Private notes');

    $published = $service->updateDraft($recorder, $published, [...$input, 'office_label' => 'Updated Branch']);
    $this->getJson($route)->assertOk()->assertJsonPath('data.verified_information.verification.office', 'Updated Branch');
    $service->revoke($reviewer, $published, 'The recorded verification was superseded.');
    $this->getJson($route)->assertOk()->assertJsonPath('data.verified_information.verification', null);
})->with(['government-ids', 'documents']);

it('renders the unified panel for IDs and documents', function (string $type) {
    $record = $type === 'documents' ? Document::create(['name' => 'Fixture Document']) : avRecord();
    $this->actingAs(avResearcher())
        ->get(route("admin.{$type}.edit-wizard.show", [$record, 'basic']))
        ->assertOk()
        ->assertSeeText('Data Sources & Verification')
        ->assertSeeText('Live resident app preview')
        ->assertDontSeeText('Supporting evidence reference')
        ->assertSeeText('Save verification')
        ->assertDontSeeText('Save and submit for review')
        ->assertDontSeeText('Which ways to apply');
})->with(['government-ids', 'documents']);

it('requires a checked date for a draft catalog source', function () {
    $record = avRecord();
    $this->actingAs(avResearcher())->post(route('admin.government-ids.edit-wizard.save', [$record, 'basic']), [
        'name' => $record->name,
        'research_sources_present' => '1',
        'research_metadata' => ['status' => 'draft', 'sources' => [
            ['title' => 'Official site', 'url' => 'https://agency.gov.ph'],
        ]],
    ])->assertSessionHasErrors('research_metadata.sources');
});
