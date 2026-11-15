<?php

require_once __DIR__.'/../Support/agency_verification.php';

use App\Services\CatalogVerificationFingerprint;
use Illuminate\Support\Facades\DB;

function avIsCurrent($row): bool
{
    return app(CatalogVerificationFingerprint::class)->isCurrent($row->fresh());
}

it('stores a private snapshot and fingerprint of the confirmed scope on approval', function () {
    $row = avApproved(avRecord())->fresh();

    expect($row->content_fingerprint)->toHaveLength(64)
        ->and($row->fingerprint_version)->toBe(CatalogVerificationFingerprint::VERSION)
        ->and(array_keys($row->content_snapshot['aspects']))->toEqualCanonicalizing(['fees', 'requirements'])
        ->and(avIsCurrent($row))->toBeTrue();
});

it('becomes outdated when confirmed information changes', function (Closure $change) {
    $record = avRecord();
    $row = avApproved($record, ['confirmed_aspects' => ['requirements', 'fees']]);
    $change($record);

    expect(avIsCurrent($row))->toBeFalse()
        ->and(avService()->evaluate($record)['status'])->toBe('needs_review');
})->with([
    'fee amount' => [fn ($record) => $record->fees()->first()->update(['amount_min' => 1200])],
    'new fee' => [fn ($record) => $record->fees()->create(['label' => 'Courier', 'type' => 'varies'])],
    'requirement wording' => [fn ($record) => DB::table('government_id_requirement_items')->where('custom_name', 'Birth certificate')
        ->update(['custom_name' => 'PSA birth certificate'])],
    'legacy fee text' => [fn ($record) => $record->update(['fee' => 'PHP 1,200'])],
    'hiding a confirmed section' => [fn ($record) => $record->update(['research_metadata' => ['hidden_sections' => ['fees']]])],
    'record renamed' => [fn ($record) => $record->update(['name' => 'Renamed Passport'])],
]);

it('ignores changes outside the confirmed aspects', function () {
    $record = avRecord();
    $row = avApproved($record, ['confirmed_aspects' => ['requirements', 'fees']]);
    $record->update(['validity' => '5 years', 'processing_time' => '20 working days', 'description' => 'New text',
        'research_metadata' => ['sources' => [['title' => 'Public reference', 'url' => 'https://example.gov.ph/']]]]);

    expect(avIsCurrent($row))->toBeTrue()
        ->and(avService()->evaluate($record)['status'])->toBe('agency_confirmed');
});

it('only watches the request paths in scope', function () {
    $record = avRecord();
    $row = avApproved($record, ['confirmed_aspects' => ['requirements'], 'scope_request_paths' => [avFirstPath($record)]]);

    DB::table('government_id_requirement_items')->where('custom_name', 'Old passport')->update(['custom_name' => 'Expired passport']);
    expect(avIsCurrent($row))->toBeTrue();

    DB::table('government_id_requirement_items')->where('custom_name', 'Birth certificate')->update(['custom_name' => 'Late birth certificate']);
    expect(avIsCurrent($row))->toBeFalse();
});

it('becomes outdated when a scoped request path is removed', function () {
    $record = avRecord();
    $row = avApproved($record, ['confirmed_aspects' => ['requirements'], 'scope_request_paths' => [avRenewalPath($record)]]);
    $renewal = $record->requirementSets()->findOrFail(avRenewalPath($record));
    $renewal->groups->each(fn ($group) => $group->items()->delete());
    $renewal->groups()->delete();
    $renewal->delete();

    expect(avIsCurrent($row))->toBeFalse();
});

it('stays current when identical content is saved again with new row ids', function () {
    $record = avRecord();
    $row = avApproved($record, ['confirmed_aspects' => ['fees']]);
    $fee = $record->fees()->first();
    $copy = $fee->only(['label', 'type', 'amount_min', 'amount_max', 'currency', 'description', 'notes', 'is_optional']);
    $fee->delete();
    $record->fees()->create($copy);

    expect(avIsCurrent($row))->toBeTrue();
});

it('treats an older fingerprint version as outdated, never as valid', function () {
    $row = avApproved(avRecord());
    DB::table('catalog_agency_verifications')->where('id', $row->id)->update(['fingerprint_version' => 0]);

    expect(avIsCurrent($row))->toBeFalse();
});

it('keeps the outdated verification in history', function () {
    $record = avRecord();
    $row = avApproved($record);
    $record->fees()->first()->update(['amount_min' => 1300]);
    $result = avService()->evaluate($record);

    expect($result['outdated']->pluck('id')->all())->toBe([$row->id])
        ->and($result['current'])->toBeEmpty()
        ->and($row->fresh()->content_snapshot)->not->toBeEmpty()
        ->and($row->fresh()->review_state)->toBe('approved');
});
