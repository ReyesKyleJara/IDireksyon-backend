<?php

require_once __DIR__.'/../Support/agency_verification.php';

use App\Models\Agency;
use Illuminate\Validation\ValidationException;

function avSubmitErrors(array $overrides): array
{
    $recorder = avResearcher();
    $row = avDraft(avRecord(), $overrides, $recorder);
    try {
        avService()->submit($recorder, $row);
    } catch (ValidationException $exception) {
        return array_keys($exception->errors());
    }

    return [];
}

it('lets a draft be saved incomplete but requires documented evidence to submit a response', function () {
    expect(avSubmitErrors(['responded_on' => null, 'evidence_reference' => null]))
        ->toEqualCanonicalizing(['responded_on', 'evidence_reference']);
});

it('requires what each outcome needs before submission', function (array $overrides, array $missing) {
    expect(avSubmitErrors($overrides))->toEqualCanonicalizing($missing);
})->with([
    'confirmed is complete' => [[], []],
    'partial needs not-covered aspects and note' => [['outcome' => 'partially_confirmed'], ['not_covered_aspects', 'not_covered_note']],
    'partial with both is complete' => [['outcome' => 'partially_confirmed', 'not_covered_aspects' => ['processing_time'],
        'not_covered_note' => 'Processing time was not discussed.'], []],
    'corrections need a summary' => [['outcome' => 'confirmed_with_corrections'], ['corrections_summary']],
    'not confirmed needs declined aspects' => [['outcome' => 'not_confirmed', 'confirmed_aspects' => []], ['not_covered_aspects']],
    'pending needs a request date' => [['outcome' => 'pending', 'confirmed_aspects' => [], 'requested_on' => null, 'responded_on' => null], ['requested_on']],
    'no response needs a request date' => [['outcome' => 'no_response', 'confirmed_aspects' => [], 'requested_on' => null, 'responded_on' => null], ['requested_on']],
]);

it('only lists aspects the agency actually verified', function (array $overrides, string $field) {
    expect(fn () => avDraft(avRecord(), $overrides))->toThrow(ValidationException::class)
        ->and(rescue(fn () => avDraft(avRecord(), $overrides), fn ($e) => array_keys($e->errors()), false))->toContain($field);
})->with([
    'confirming outcome with nothing confirmed' => [['confirmed_aspects' => []], 'confirmed_aspects'],
    'pending cannot claim confirmed aspects' => [['outcome' => 'pending', 'responded_on' => null], 'confirmed_aspects'],
    'not confirmed cannot claim confirmed aspects' => [['outcome' => 'not_confirmed', 'not_covered_aspects' => ['fees']], 'confirmed_aspects'],
    'aspect both confirmed and not covered' => [['outcome' => 'partially_confirmed', 'not_covered_aspects' => ['fees']], 'not_covered_aspects'],
    'full confirmation with a not-covered aspect' => [['not_covered_aspects' => ['validity']], 'not_covered_aspects'],
    'unknown aspect' => [['confirmed_aspects' => ['everything']], 'confirmed_aspects.0'],
    'repeated aspect' => [['confirmed_aspects' => ['fees', 'fees']], 'confirmed_aspects.0'],
]);

it('limits request paths strictly to this record', function () {
    $record = avRecord();
    $other = avRecord();

    expect(fn () => avDraft($record, ['scope_request_paths' => [avFirstPath($other)]]))
        ->toThrow(ValidationException::class, 'Only request paths of this record')
        ->and(fn () => avDraft($record, ['scope_request_paths' => []]))->toThrow(ValidationException::class);

    $row = avDraft($record, ['scope_request_paths' => [avRenewalPath($record), avFirstPath($record)]]);
    expect($row->scope_request_paths)->toBe(collect([avFirstPath($record), avRenewalPath($record)])->sort()->values()->all());
});

it('treats a missing path scope as every request path', function () {
    expect(avDraft(avRecord())->scope_request_paths)->toBeNull();
});

it('rejects endorsement wording in public verification text', function (string $field, string $text) {
    expect(fn () => avDraft(avRecord(), [$field => $text]))->toThrow(ValidationException::class, 'endorsement or approval wording');
})->with([
    ['scope', 'Requirements approved by the agency for residents'],
    ['scope', 'Government-approved requirements list for testing'],
    ['scope', 'This guide is endorsed by the issuing office'],
    ['scope', 'Fees certified by the regional office for testing'],
    ['not_covered_note', 'Everything else is accredited separately'],
    ['office_label', 'Official partner office'],
]);

it('keeps personal names out of the contact role', function (string $role) {
    expect(fn () => avDraft(avRecord(), ['contact_role' => $role]))->toThrow(ValidationException::class, 'role only');
})->with(['Mr. Juan Dela Cruz', 'Atty. Santos', "Ma'am Reyes", 'Dr Lim']);

it('requires a description when the method is other', function () {
    expect(fn () => avDraft(avRecord(), ['method' => 'other']))->toThrow(ValidationException::class);
    expect(avDraft(avRecord(), ['method' => 'other', 'notes' => 'Asked at a public information desk (test).'])->method)->toBe('other');
});

it('copies the agency name from the chosen agency', function () {
    $agency = Agency::create(['name' => 'Fixture Department', 'acronym' => 'FXD']);

    expect(avDraft(avRecord(), ['agency_id' => $agency->id, 'agency_name' => 'Typed name'])->agency_name)->toBe('Fixture Department');
});

it('requires a recorded contact method to submit a pending request', function () {
    $recorder = avResearcher();
    $row = avDraft(avRecord(), ['outcome' => 'pending', 'confirmed_aspects' => [], 'responded_on' => null], $recorder);
    \Illuminate\Support\Facades\DB::table('catalog_agency_verifications')->where('id', $row->id)->update(['method' => 'not-a-method']);

    expect(fn () => avService()->submit($recorder, $row->fresh()))->toThrow(ValidationException::class, 'how the agency was contacted');
});

it('rejects contact dates in the future or out of order', function (array $dates, string $message) {
    expect(fn () => avDraft(avRecord(), $dates))->toThrow(ValidationException::class, $message);
})->with([
    'future request' => [['requested_on' => '2999-01-01', 'responded_on' => null], 'future'],
    'future response' => [['responded_on' => '2999-01-01'], 'future'],
    'response before request' => [['requested_on' => '2026-03-01', 'responded_on' => '2026-02-01'], 'before the request date'],
]);

it('lets anyone in the CMS record a draft, with no clearance setting at all', function () {
    expect(\Illuminate\Support\Facades\Gate::forUser(avResearcher())->allows('create', \App\Models\CatalogAgencyVerification::class))->toBeTrue()
        ->and(config('research'))->toBeNull()
        ->and(class_exists('App\\Support\\ResearchClearances'))->toBeFalse();
});

it('shows pending only for a submitted request that has a contact date and method', function () {
    $record = avRecord();
    $row = avSubmitted($record, ['outcome' => 'pending', 'confirmed_aspects' => [], 'responded_on' => null]);
    expect(avService()->evaluate($record)['status'])->toBe('agency_confirmation_pending');

    \Illuminate\Support\Facades\DB::table('catalog_agency_verifications')->where('id', $row->id)->update(['requested_on' => null]);
    expect(avService()->evaluate($record)['status'])->toBe('needs_review');
});
