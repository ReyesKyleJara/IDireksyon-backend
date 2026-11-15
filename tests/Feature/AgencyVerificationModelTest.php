<?php

require_once __DIR__.'/../Support/agency_verification.php';

use App\Models\CatalogAgencyVerification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

it('can only create rows as unapproved drafts', function () {
    $record = avRecord();
    $user = avResearcher();
    $row = new CatalogAgencyVerification(avInput());
    $row->verifiable()->associate($record);
    $row->forceFill(['recorded_by' => $user->id, 'recorded_by_name' => 'fixture', 'review_state' => 'approved', 'approved_by' => $user->id]);

    expect(fn () => $row->save())->toThrow(DomainException::class, 'unapproved draft')
        ->and(DB::table('catalog_agency_verifications')->count())->toBe(0);
});

it('never fills workflow fields from input', function () {
    $row = new CatalogAgencyVerification([...avInput(), 'review_state' => 'approved', 'approved_by' => 1,
        'recorded_by' => 1, 'content_fingerprint' => str_repeat('a', 64), 'revoked_at' => now()]);

    expect($row->review_state)->toBe('draft')
        ->and($row->approved_by)->toBeNull()
        ->and($row->recorded_by)->toBeNull()
        ->and($row->content_fingerprint)->toBeNull()
        ->and($row->revoked_at)->toBeNull();
});

it('freezes content once a row is submitted', function () {
    $recorder = avResearcher();
    $row = avSubmitted(avRecord(), [], $recorder);

    expect(fn () => $row->forceFill(['scope' => 'Changed after submission for testing'])->save())->toThrow(DomainException::class)
        ->and(fn () => avService()->updateDraft($recorder, $row->fresh(), avInput(['scope' => 'Edited after submission'])))
        ->toThrow(AuthorizationException::class)
        ->and($row->fresh()->scope)->toBe(avInput()['scope']);
});

it('never lets approved content or approval details change', function (array $change) {
    $row = avApproved(avRecord());

    expect(fn () => $row->fresh()->forceFill($change)->save())->toThrow(DomainException::class);
})->with([
    'scope' => [['scope' => 'Rewritten after approval for testing']],
    'aspects' => [['confirmed_aspects' => ['requirements', 'fees', 'validity']]],
    'evidence' => [['evidence_reference' => 'Different evidence']],
    'fingerprint' => [['content_fingerprint' => str_repeat('b', 64)]],
    'approver' => [['approved_by_name' => 'someone else']],
    'state' => [['review_state' => 'draft']],
]);

it('records corrections and revocation once each, and revocation is final', function () {
    $admin = avSuperAdmin();
    $row = avApproved(avRecord(), ['outcome' => 'confirmed_with_corrections', 'corrections_summary' => 'Fee amount corrected (test).']);
    avService()->markCorrectionsApplied($admin, $row);
    avService()->revoke($admin, $row, 'Revoked in a test to check finality.');
    $revoked = $row->fresh();

    expect($revoked->corrections_applied_at)->not->toBeNull()
        ->and($revoked->revoked_at)->not->toBeNull()
        ->and(fn () => $revoked->forceFill(['revocation_reason' => 'A different reason for testing'])->save())->toThrow(DomainException::class)
        ->and(fn () => $revoked->forceFill(['corrections_applied_at' => now()->addDay()])->save())->toThrow(DomainException::class)
        ->and(fn () => avService()->revoke($admin, $revoked, 'Revoking again should be refused.'))->toThrow(AuthorizationException::class);
});

it('blocks deleting verification history in every state', function () {
    $draft = avDraft(avRecord());
    $approved = avApproved(avRecord());

    expect(fn () => $draft->delete())->toThrow(DomainException::class, 'withdrawn')
        ->and(fn () => $approved->delete())->toThrow(DomainException::class)
        ->and(DB::table('catalog_agency_verifications')->count())->toBe(2);
});

it('withdraws an unneeded draft instead of deleting it', function () {
    $recorder = avResearcher();
    $row = avDraft(avRecord(), [], $recorder);

    avService()->updateDraft($recorder, $row, avInput(['outcome' => 'withdrawn', 'confirmed_aspects' => [], 'responded_on' => null]));

    expect($row->fresh()->outcome)->toBe('withdrawn')
        ->and(CatalogAgencyVerification::count())->toBe(1);
});
