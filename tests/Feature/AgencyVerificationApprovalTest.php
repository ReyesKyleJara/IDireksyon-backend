<?php

require_once __DIR__.'/../Support/agency_verification.php';

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

it('approves only when a different active super_admin approves', function () {
    $recorder = avResearcher();
    $approver = avSuperAdmin();
    $row = avService()->approve($approver, avSubmitted(avRecord(), [], $recorder))->fresh();

    expect($row->review_state)->toBe('approved')
        ->and($row->recorded_by)->toBe($recorder->id)
        ->and($row->approved_by)->toBe($approver->id)
        ->and($row->approved_by)->not->toBe($row->recorded_by);
});

it('never allows self-approval, even by a super_admin', function () {
    $admin = avSuperAdmin();
    $row = avSubmitted(avRecord(), [], $admin);

    expect(fn () => avService()->approve($admin, $row))->toThrow(AuthorizationException::class)
        ->and($row->fresh()->review_state)->toBe('submitted');
});

it('refuses approval by anyone who is not an active super_admin', function (Closure $makeApprover) {
    $row = avSubmitted(avRecord());

    expect(fn () => avService()->approve($makeApprover(), $row))->toThrow(AuthorizationException::class)
        ->and($row->fresh()->review_state)->toBe('submitted');
})->with([
    'researcher' => [fn () => avResearcher()],
    'deactivated super_admin' => [fn () => avSuperAdmin(['is_active' => false])],
    'resident' => [fn () => \App\Models\User::factory()->create(['role' => 'resident'])],
]);

it('re-checks the approver against the database inside the transaction', function () {
    $row = avSubmitted(avRecord());
    $approver = avSuperAdmin();
    $stale = clone $approver;
    DB::table('users')->where('id', $approver->id)->update(['is_active' => false]);

    expect(fn () => avService()->approve($stale, $row))->toThrow(AuthorizationException::class)
        ->and($row->fresh()->review_state)->toBe('submitted');
});

it('cannot approve a pending request, a draft, or an already approved row', function () {
    $admin = avSuperAdmin();
    $pending = avSubmitted(avRecord(), ['outcome' => 'pending', 'confirmed_aspects' => [], 'responded_on' => null]);
    $draft = avDraft(avRecord());
    $approved = avApproved(avRecord());

    expect(fn () => avService()->approve($admin, $pending))->toThrow(AuthorizationException::class)
        ->and(fn () => avService()->approve($admin, $draft))->toThrow(AuthorizationException::class)
        ->and(fn () => avService()->approve($admin, $approved))->toThrow(AuthorizationException::class);
});

it('lets only the recorder edit and submit their draft', function () {
    $recorder = avResearcher();
    $row = avDraft(avRecord(), [], $recorder);

    expect(fn () => avService()->updateDraft(avResearcher(), $row, avInput()))->toThrow(AuthorizationException::class)
        ->and(fn () => avService()->submit(avSuperAdmin(), $row))->toThrow(AuthorizationException::class)
        ->and(avService()->submit($recorder, $row)->review_state)->toBe('submitted');
});

it('returns a submission to draft only with a super_admin and a reason', function () {
    $recorder = avResearcher();
    $row = avSubmitted(avRecord(), [], $recorder);

    expect(fn () => avService()->returnToDraft($recorder, $row, 'Please check the evidence reference.'))->toThrow(AuthorizationException::class)
        ->and(fn () => avService()->returnToDraft(avSuperAdmin(), $row, 'short'))->toThrow(ValidationException::class);

    $returned = avService()->returnToDraft(avSuperAdmin(), $row, 'Please check the evidence reference.');
    expect($returned->review_state)->toBe('draft')->and($returned->returned_reason)->toBe('Please check the evidence reference.');
});

it('lets the recorder reopen their submitted pending request to add the response', function () {
    $recorder = avResearcher();
    $row = avSubmitted(avRecord(), ['outcome' => 'pending', 'confirmed_aspects' => [], 'responded_on' => null], $recorder);

    expect(fn () => avService()->reopen(avResearcher(), $row))->toThrow(AuthorizationException::class);
    $reopened = avService()->reopen($recorder, $row);
    avService()->updateDraft($recorder, $reopened, avInput());

    expect($reopened->fresh()->outcome)->toBe('confirmed')->and($reopened->fresh()->review_state)->toBe('draft');
});

it('only reopens pending requests, not submitted responses', function () {
    $recorder = avResearcher();
    $row = avSubmitted(avRecord(), [], $recorder);

    expect(fn () => avService()->reopen($recorder, $row))->toThrow(AuthorizationException::class);
});

it('revokes only with a super_admin and a reason, keeping the row', function () {
    $row = avApproved(avRecord());

    expect(fn () => avService()->revoke(avResearcher(), $row, 'Evidence was found to be incomplete.'))->toThrow(AuthorizationException::class)
        ->and(fn () => avService()->revoke(avSuperAdmin(), $row, 'too short'))->toThrow(ValidationException::class);

    $revoked = avService()->revoke(avSuperAdmin(), $row, 'Evidence was found to be incomplete.');
    expect($revoked->revoked_at)->not->toBeNull()
        ->and($revoked->fresh()->review_state)->toBe('approved')
        ->and(DB::table('catalog_agency_verifications')->count())->toBe(1);
});

it('marks corrections applied only for approved confirmations with corrections', function () {
    $plain = avApproved(avRecord());
    $corrections = avApproved(avRecord(), ['outcome' => 'confirmed_with_corrections', 'corrections_summary' => 'Fee corrected (test).']);

    expect(fn () => avService()->markCorrectionsApplied(avSuperAdmin(), $plain))->toThrow(AuthorizationException::class)
        ->and(fn () => avService()->markCorrectionsApplied(avResearcher(), $corrections))->toThrow(AuthorizationException::class);

    expect(avService()->markCorrectionsApplied(avSuperAdmin(), $corrections)->corrections_applied_at)->not->toBeNull();
});
