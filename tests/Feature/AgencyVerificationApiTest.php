<?php

require_once __DIR__.'/../Support/agency_verification.php';

use App\Models\CatalogAgencyVerification;
use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
 * The resident API's agency_verification field (Feature 2, Step 4).
 * Fake data only: fixture agencies, in-memory SQLite.
 */

/** Text that exists only in private verification fields and must never reach residents. */
const API_PRIVATE = ['PRIVATE-EVIDENCE-REF', 'PRIVATE-EVIDENCE-PLACE', 'PRIVATE-NOTE', 'PRIVATE-NOT-COVERED-NOTE',
    'PRIVATE-CORRECTIONS', 'Records officer', 'TEST-ONLY', 'fixture-recorder', 'fixture-approver'];

/**
 * Keys from verification rows, users or audit logs that must never appear in a resident response.
 * ("notes" is left out only because fees already publish their own public notes; the private
 * verification note text is still checked through API_PRIVATE.)
 */
const API_PRIVATE_KEYS = ['evidence_reference', 'evidence_location', 'contact_role',
    'not_covered_note', 'corrections_summary', 'content_snapshot', 'content_fingerprint', 'fingerprint_version',
    'recorded_by', 'recorded_by_name', 'approved_by', 'approved_by_name', 'review_state', 'returned_reason',
    'revocation_reason', 'verifiable_type', 'verifiable_id', 'agency_validation_status', 'changed_fields'];

const API_CONFIRMATION_KEYS = ['agency_name', 'office_label', 'outcome', 'scope', 'confirmed_aspects', 'not_covered_aspects',
    'applies_to_all_request_paths', 'request_paths', 'confirmed_on'];

function apiPrivate(array $overrides = []): array
{
    return array_replace(['evidence_reference' => 'PRIVATE-EVIDENCE-REF', 'evidence_location' => 'PRIVATE-EVIDENCE-PLACE',
        'notes' => 'PRIVATE-NOTE', 'contact_role' => 'Records officer'], $overrides);
}

function apiApproved(GovernmentId|Document $record, array $overrides = []): CatalogAgencyVerification
{
    $recorder = avResearcher(['username' => 'fixture-recorder-'.Str::lower(Str::random(6))]);
    $approver = avSuperAdmin(['username' => 'fixture-approver-'.Str::lower(Str::random(6))]);
    $row = avService()->createDraft($recorder, $record, avInput(apiPrivate($overrides)));

    return avService()->approve($approver, avService()->submit($recorder, $row));
}

function apiDetail(GovernmentId|Document $record): array
{
    $resource = $record instanceof Document ? 'documents' : 'government-ids';

    return test()->getJson('/api/'.$resource.'/'.$record->id)->assertOk()->json('data');
}

function apiKeysDeep(mixed $value): array
{
    if (! is_array($value)) {
        return [];
    }
    $keys = [];
    foreach ($value as $key => $child) {
        if (is_string($key)) {
            $keys[] = $key;
        }
        array_push($keys, ...apiKeysDeep($child));
    }

    return $keys;
}

function apiAssertPrivate(string $label, string $json): void
{
    $decoded = json_decode($json, true) ?? [];
    expect(array_values(array_intersect(API_PRIVATE_KEYS, apiKeysDeep($decoded))))->toBe([], $label);
    foreach (API_PRIVATE as $secret) {
        expect($json)->not->toContain($secret, $label.': '.$secret);
    }
}

it('returns only safe public fields for a current approved confirmation', function () {
    $record = avRecord();
    apiApproved($record, ['outcome' => 'partially_confirmed', 'confirmed_aspects' => ['requirements'],
        'not_covered_aspects' => ['fees'], 'not_covered_note' => 'PRIVATE-NOT-COVERED-NOTE',
        'scope_request_paths' => [avFirstPath($record)], 'office_label' => 'Fixture office']);
    $field = apiDetail($record)['agency_verification'];

    expect($field['status'])->toBe('agency_confirmed')
        ->and(array_keys($field))->toBe(['status', 'confirmations'])
        ->and($field['confirmations'])->toHaveCount(1)
        ->and(array_keys($field['confirmations'][0]))->toBe(API_CONFIRMATION_KEYS)
        ->and($field['confirmations'][0])->toMatchArray([
            'agency_name' => 'Fixture Agency', 'office_label' => 'Fixture office', 'outcome' => 'partially_confirmed',
            'scope' => avInput()['scope'], 'confirmed_aspects' => ['requirements'],
            'not_covered_aspects' => ['eligibility', 'fees', 'processing_time', 'validity', 'application_process', 'offices'],
            'applies_to_all_request_paths' => false, 'confirmed_on' => '2026-02-10',
        ])
        ->and($field['confirmations'][0]['request_paths'])->toBe([['id' => avFirstPath($record), 'label' => $record->requirementSets()->find(avFirstPath($record))->display_label]]);
});

it('serves the field on documents too', function () {
    $document = Document::create(['name' => 'Fixture document']);
    $document->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'all']);
    apiApproved($document, ['confirmed_aspects' => ['requirements']]);

    expect(apiDetail($document)['agency_verification']['status'])->toBe('agency_confirmed')
        ->and(apiDetail($document)['agency_verification']['confirmations'][0]['applies_to_all_request_paths'])->toBeTrue();
});

it('returns an empty field when nothing is confirmed', function () {
    $record = avRecord();
    $record->update(['research_metadata' => ['sources' => [['title' => 'Public reference', 'url' => 'https://example.gov.ph/']]]]);

    expect(apiDetail($record)['agency_verification'])->toBe(['status' => null, 'confirmations' => []]);
});

it('never shows drafts, submitted, pending, returned or revoked rows as confirmations', function (Closure $make) {
    $record = avRecord();
    $make($record);

    expect(apiDetail($record)['agency_verification'])->toBe(['status' => null, 'confirmations' => []]);
})->with([
    'draft' => [fn ($record) => avDraft($record, apiPrivate())],
    'submitted response' => [fn ($record) => avSubmitted($record, apiPrivate())],
    'submitted pending request' => [fn ($record) => avSubmitted($record, apiPrivate(['outcome' => 'pending', 'confirmed_aspects' => [], 'responded_on' => null]))],
    'returned' => [fn ($record) => avService()->returnToDraft(avSuperAdmin(), avSubmitted($record, apiPrivate()), 'Please check the evidence reference.')],
    'revoked' => [fn ($record) => avService()->revoke(avSuperAdmin(), apiApproved($record), 'Revoked in an API test of visibility.')],
    'corrections not applied' => [fn ($record) => apiApproved($record, ['outcome' => 'confirmed_with_corrections', 'corrections_summary' => 'PRIVATE-CORRECTIONS'])],
    'withdrawn and approved' => [fn ($record) => apiApproved($record, ['outcome' => 'withdrawn', 'confirmed_aspects' => []])],
]);

it('hides a confirmation immediately when information inside its scope changes', function () {
    $record = avRecord();
    apiApproved($record, ['confirmed_aspects' => ['fees']]);
    expect(apiDetail($record)['agency_verification']['status'])->toBe('agency_confirmed');

    $record->fees()->first()->update(['amount_min' => 1200]);
    expect(apiDetail($record)['agency_verification'])->toBe(['status' => null, 'confirmations' => []]);
});

it('keeps a confirmation when only information outside its scope changes', function () {
    $record = avRecord();
    apiApproved($record, ['confirmed_aspects' => ['requirements'], 'scope_request_paths' => [avFirstPath($record)]]);
    $record->update(['validity' => '5 years', 'description' => 'Changed outside the confirmed scope']);
    $record->fees()->first()->update(['amount_min' => 1200]);
    DB::table('government_id_requirement_items')->where('custom_name', 'Old passport')->update(['custom_name' => 'Expired passport']);

    expect(apiDetail($record)['agency_verification']['status'])->toBe('agency_confirmed');
});

it('hides confirmations when a newer agency response did not confirm the aspect', function () {
    $record = avRecord();
    apiApproved($record, ['confirmed_aspects' => ['fees'], 'responded_on' => '2026-02-10']);
    apiApproved($record, ['outcome' => 'not_confirmed', 'confirmed_aspects' => [], 'not_covered_aspects' => ['fees'], 'responded_on' => '2026-03-10']);

    expect(apiDetail($record)['agency_verification']['confirmations'])->toBe([]);
});

it('leaves every existing detail field unchanged', function (Closure $makeRecord) {
    $record = $makeRecord();
    $before = apiDetail($record);
    apiApproved($record, ['confirmed_aspects' => ['requirements']]);
    $after = apiDetail($record);

    expect(array_diff_key($after, ['agency_verification' => 1, 'verified_information' => 1]))->toBe(array_diff_key($before, ['agency_verification' => 1, 'verified_information' => 1]))
        ->and($after['agency_verification']['status'])->toBe('agency_confirmed');
})->with([
    'government id' => [fn () => avRecord()],
    'document' => [fn () => tap(Document::create(['name' => 'Fixture document']), fn ($document) => $document->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'all']))],
]);

it('does not add verification data to the catalog lists', function () {
    $record = avRecord();
    apiApproved($record);

    foreach (['/api/government-ids', '/api/documents', '/api/offices'] as $url) {
        $json = $this->getJson($url)->assertOk()->getContent();
        expect($json)->not->toContain('agency_verification');
        apiAssertPrivate($url, $json);
    }
});

it('never exposes private verification data through public or resident endpoints', function () {
    $record = avRecord();
    $document = Document::create(['name' => 'Fixture document']);
    $document->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'all']);
    apiApproved($record, ['outcome' => 'confirmed_with_corrections', 'corrections_summary' => 'PRIVATE-CORRECTIONS']);
    avService()->markCorrectionsApplied(avSuperAdmin(), CatalogAgencyVerification::sole());
    apiApproved($document, ['confirmed_aspects' => ['requirements'], 'outcome' => 'partially_confirmed',
        'not_covered_aspects' => ['fees'], 'not_covered_note' => 'PRIVATE-NOT-COVERED-NOTE']);
    avDraft($record, apiPrivate());
    // A journey target without manual requirements, carrying its own approved confirmation.
    $journeyTarget = GovernmentId::create(['name' => 'Fixture journey target', 'eligibility' => 'Fixture eligibility']);
    apiApproved($journeyTarget, ['confirmed_aspects' => ['eligibility']]);
    $responses = [
        'id detail' => $this->getJson('/api/government-ids/'.$record->id),
        'document detail' => $this->getJson('/api/documents/'.$document->id),
    ];
    expect($responses['id detail']->json('data.agency_verification.status'))->toBe('agency_confirmed')
        ->and($responses['document detail']->json('data.agency_verification.status'))->toBe('agency_confirmed');

    $resident = User::factory()->create(['date_of_birth' => now()->subYears(25)->format('Y-m-d')]);
    $this->actingAs($resident);
    // The journey is created before the inventory update: a target the resident already owns is refused.
    $responses += [
        'journey create' => $this->postJson('/api/auth/journeys', ['target_government_id_ids' => [$journeyTarget->id]]),
        'journeys' => $this->getJson('/api/auth/journeys'),
        'roadmap plan' => $this->postJson('/api/auth/roadmap-plan', ['target_government_id_ids' => [$record->id],
            'government_id_ids' => [], 'document_ids' => [$document->id]]),
        'inventory update' => $this->putJson('/api/auth/inventory', ['government_id_ids' => [$record->id], 'document_ids' => [$document->id]]),
        'inventory' => $this->getJson('/api/auth/inventory'),
        'preparation' => $this->getJson('/api/auth/requirement-preparation/government_id/'.$record->id),
        'me' => $this->getJson('/api/auth/me'),
    ];

    foreach ($responses as $label => $response) {
        expect($response->status())->toBeLessThan(300, $label);
        apiAssertPrivate($label, $response->getContent());
    }
});
