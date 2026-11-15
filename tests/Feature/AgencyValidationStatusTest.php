<?php

require_once __DIR__.'/../Support/agency_verification.php';

use App\Models\ContentChangeLog;
use App\Models\Document;
use App\Models\GovernmentId;
use Illuminate\Support\Facades\DB;

const AV_PUBLIC_SOURCE = ['sources' => [['title' => 'Public reference: Fixture page', 'url' => 'https://example.gov.ph/']]];

function avStatus(GovernmentId $record): string
{
    return avService()->evaluate($record)['status'];
}

it('is needs_review with nothing, and public_sources with only public references', function () {
    $record = avRecord();
    expect(avStatus($record))->toBe('needs_review');

    $record->update(['research_metadata' => AV_PUBLIC_SOURCE]);
    expect(avStatus($record))->toBe('public_sources');
});

it('shows pending only for a submitted request, never for a draft', function () {
    $record = avRecord();
    $record->update(['research_metadata' => AV_PUBLIC_SOURCE]);
    $recorder = avResearcher();
    $pending = ['outcome' => 'pending', 'confirmed_aspects' => [], 'responded_on' => null];

    $draft = avDraft($record, $pending, $recorder);
    expect(avStatus($record))->toBe('public_sources');

    avService()->submit($recorder, $draft);
    expect(avStatus($record))->toBe('agency_confirmation_pending');
});

it('counts only approved, current, unrevoked confirmations', function () {
    $record = avRecord();
    $submitted = avSubmitted($record);
    expect(avStatus($record))->toBe('needs_review');

    $approved = avService()->approve(avSuperAdmin(), $submitted);
    expect(avStatus($record))->toBe('agency_confirmed');

    avService()->revoke(avSuperAdmin(), $approved, 'Revoked in a test of status calculation.');
    expect(avStatus($record))->toBe('needs_review');
});

it('keeps a partial confirmation limited to what was confirmed', function () {
    $record = avRecord();
    avApproved($record, ['outcome' => 'partially_confirmed', 'confirmed_aspects' => ['requirements'],
        'not_covered_aspects' => ['fees'], 'not_covered_note' => 'Fees were not confirmed (test).']);
    $current = avService()->evaluate($record)['current'];

    expect($current)->toHaveCount(1)
        ->and($current->first()->confirmed_aspects)->toBe(['requirements'])
        ->and($current->first()->not_covered_aspects)->toBe(['fees']);
});

it('needs review while agency corrections are not applied', function () {
    $record = avRecord();
    $row = avApproved($record, ['outcome' => 'confirmed_with_corrections', 'corrections_summary' => 'Fee corrected (test).']);
    expect(avStatus($record))->toBe('needs_review');

    avService()->markCorrectionsApplied(avSuperAdmin(), $row);
    expect(avStatus($record))->toBe('agency_confirmed');
});

it('needs review when the latest agency response for an aspect is not confirmed', function () {
    $record = avRecord();
    avApproved($record, ['confirmed_aspects' => ['fees'], 'responded_on' => '2026-02-10']);
    avApproved($record, ['outcome' => 'not_confirmed', 'confirmed_aspects' => [], 'not_covered_aspects' => ['fees'],
        'responded_on' => '2026-03-10']);
    expect(avService()->evaluate($record))->status->toBe('needs_review')->not_confirmed_aspects->toBe(['fees']);

    avApproved($record, ['confirmed_aspects' => ['fees'], 'responded_on' => '2026-04-10']);
    expect(avStatus($record))->toBe('agency_confirmed');
});

it('needs review when every confirmation is outdated', function () {
    $record = avRecord();
    avApproved($record);
    $record->fees()->first()->update(['amount_min' => 1500]);

    expect(avService()->evaluate($record))->status->toBe('needs_review')
        ->and(avService()->evaluate($record)['reasons'][0])->toContain('changed after approval');
});

it('refreshes only the cached status column without touching anything else', function () {
    $record = avRecord();
    DB::table('government_ids')->where('id', $record->id)->update(['updated_at' => '2026-01-01 00:00:00']);
    $before = (array) DB::table('government_ids')->find($record->id);
    $logs = ContentChangeLog::count();

    avApproved($record);
    $after = (array) DB::table('government_ids')->find($record->id);

    // Only the intended verification audit entries are added; the status cache itself logs nothing.
    expect($after['agency_validation_status'])->toBe('agency_confirmed')
        ->and(array_diff_key($after, ['agency_validation_status' => 1]))->toBe(array_diff_key($before, ['agency_validation_status' => 1]))
        ->and(ContentChangeLog::where('action', 'not like', 'verification_%')->count())->toBe($logs)
        ->and(ContentChangeLog::where('action', 'like', 'verification_%')->pluck('action')->all())
        ->toBe(['verification_drafted', 'verification_submitted', 'verification_approved']);
});

it('previews changes by default and writes only the status column with --apply', function () {
    $record = avRecord();
    $record->update(['research_metadata' => AV_PUBLIC_SOURCE]);
    $document = Document::create(['name' => 'Fixture document']);
    $before = (array) DB::table('government_ids')->find($record->id);

    $this->artisan('catalog:refresh-agency-validation')->expectsOutputToContain('Preview only')->assertSuccessful();
    expect(DB::table('government_ids')->where('id', $record->id)->value('agency_validation_status'))->toBe('needs_review');

    $this->artisan('catalog:refresh-agency-validation', ['--apply' => true])->assertSuccessful();
    $after = (array) DB::table('government_ids')->find($record->id);
    expect($after['agency_validation_status'])->toBe('public_sources')
        ->and(array_diff_key($after, ['agency_validation_status' => 1]))->toBe(array_diff_key($before, ['agency_validation_status' => 1]))
        ->and($document->fresh()->agency_validation_status)->toBe('needs_review')
        ->and(DB::table('catalog_agency_verifications')->count())->toBe(0);
});

it('never marks an existing record agency-confirmed without an approved verification', function () {
    GovernmentId::create(['name' => 'Philippine Passport', 'research_metadata' => AV_PUBLIC_SOURCE]);
    Document::create(['name' => 'NBI Clearance']);
    $this->artisan('catalog:refresh-agency-validation', ['--apply' => true])->assertSuccessful();

    expect(GovernmentId::where('agency_validation_status', 'agency_confirmed')->count())->toBe(0)
        ->and(Document::where('agency_validation_status', 'agency_confirmed')->count())->toBe(0);
});
