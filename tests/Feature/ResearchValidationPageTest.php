<?php

require_once __DIR__.'/../Support/agency_verification.php';

use App\Models\Document;
use App\Models\GovernmentId;
use Illuminate\Support\Facades\DB;

/* What Data Sources (inside the Basic Information step) shows. Fake data only, in-memory SQLite. */

function rvPage(GovernmentId|Document $record, $user = null): string
{
    $route = $record instanceof Document ? 'admin.documents.edit-wizard.show' : 'admin.government-ids.edit-wizard.show';

    return test()->actingAs($user ?? avSuperAdmin())->get(route($route, [$record, 'basic']))->assertOk()->getContent();
}

it('shows the unified panel inside Basic Information for both record types', function (Closure $makeRecord) {
    $record = $makeRecord();
    $record->update(['research_metadata' => ['sources' => [['title' => 'Public reference: Fixture page',
        'url' => 'https://example.gov.ph/page', 'checked_on' => '2026-11-13']]]]);
    $html = rvPage($record);

    expect($html)->toContain('id="data-sources"')->toContain('Official Government Sources')->toContain('Agency Verification')->toContain('Live resident app preview')
        ->toContain('Public reference: Fixture page')->toContain('name="research_sources_present"')
        ->toContain('+ Add agency verification')
        ->not->toContain('Sources and research review')->not->toContain('research_metadata[status]')
        ->not->toContain('Open Data Sources');
})->with([
    'government id' => [fn () => avRecord()],
    'document' => [fn () => Document::create(['name' => 'Fixture document'])],
]);

it('shows a plain form with no UREC field, label or message anywhere', function () {
    $record = avRecord();
    avDraft($record);
    $html = rvPage($record, avResearcher());

    expect($html)->toContain('+ Add agency verification')->toContain('data-agency-form="agency-form-new"')
        ->toContain('Save verification')->not->toContain('Supporting evidence reference')
        ->not->toContain('Recording is locked')->not->toContain('data-lock-notice')
        ->and(preg_match('/\\b(urec|ethics|clearance)\\b/i', strip_tags(substr($html, strpos($html, 'id="data-sources"')))))->toBe(0)
        ->and($html)->not->toContain('ethics_clearance_reference');
});

it('asks to save a new record before adding agency confirmation', function () {
    $html = test()->actingAs(avResearcher())->get(route('admin.government-ids.create'))->assertOk()->getContent();

    expect($html)->toContain('id="data-sources"')->toContain('Save this ID first')
        ->not->toContain('data-agency-form');
});

it('labels a draft as private and never as pending', function () {
    $record = avRecord();
    avDraft($record, ['outcome' => 'pending', 'confirmed_aspects' => [], 'responded_on' => null]);
    $html = rvPage($record);

    expect($html)->toContain('Draft: private, not submitted, never shown to residents.')
        ->not->toContain('Waiting for agency reply')->not->toContain('Pending review</span>');
});

it('separates the CMS submission from the actual agency contact', function () {
    $record = avRecord();
    avSubmitted($record, ['outcome' => 'pending', 'confirmed_aspects' => [], 'responded_on' => null,
        'requested_on' => '2026-02-01', 'method' => 'email']);
    $html = rvPage($record);

    expect($html)->toContain('Waiting for agency reply')
        ->toContain('Agency contacted on 1 Feb 2026 by email')
        ->toContain('Submitted on');
});

it('shows confirmed and not-covered aspects side by side for a partial confirmation', function () {
    $record = avRecord();
    avApproved($record, ['outcome' => 'partially_confirmed', 'confirmed_aspects' => ['requirements'],
        'not_covered_aspects' => ['fees'], 'not_covered_note' => 'Fees not discussed (test).',
        'scope_request_paths' => [avFirstPath($record)]]);
    $html = rvPage($record);

    expect($html)->toContain('data-resident-preview')->toContain('Confirmed: shown to residents')->toContain('Partially confirmed on 10 Feb 2026')
        ->toContain('<span class="font-medium">Confirmed:</span> Requirements')
        ->toContain('Not covered:</span> Eligibility, Fees, Processing time, Validity, Application process, Offices');
});

it('flags outdated confirmations and corrections that are not applied yet', function () {
    $record = avRecord();
    $old = avApproved($record);
    $corrections = avApproved($record, ['outcome' => 'confirmed_with_corrections', 'corrections_summary' => 'Fee corrected (test).']);
    $record->fees()->first()->update(['amount_min' => 1400]);
    $html = rvPage($record);

    expect($html)->toContain('aria-label="Needs your attention"')
        ->toContain('data-outdated="'.$old->id.'"')->toContain('Outdated')
        ->toContain('corrections in #'.$corrections->id.' are not applied yet');
});

it('tells the recording super_admin that someone else must approve', function () {
    $record = avRecord();
    $admin = avSuperAdmin();
    avSubmitted($record, [], $admin);

    expect(rvPage($record, $admin))->toContain('Another super admin must approve this')->not->toContain('Review and approve');
    expect(rvPage($record, avSuperAdmin()))->toContain('Review and approve')
        ->toContain('Confirmation date:</span> 10 Feb 2026');
});

it('never implies government endorsement', function () {
    $record = avRecord();
    avApproved($record);
    $start = strpos($html = rvPage($record), 'id="data-sources"');
    $text = strip_tags(substr($html, $start));

    expect(preg_match('/\\b(endorsed|endorsement|government[\\s-]+approved|approved\\s+by|official\\s+partner)\\b/i', $text))->toBe(0);
});

it('keeps the record page free of a Data Sources card or separate page button', function () {
    $record = avRecord();
    $html = $this->actingAs(avSuperAdmin())->get(route('admin.government-ids.show', $record))->assertOk()->getContent();

    expect($html)->not->toContain('aria-label="Data Sources summary"')->not->toContain('Basic Information → Data Sources')
        ->not->toContain(route('admin.government-ids.research-validation.show', $record));
});

it('never creates agency records when Basic Information is saved', function () {
    $record = avRecord();
    $this->actingAs(avSuperAdmin())->post(route('admin.government-ids.edit-wizard.save', [$record, 'basic']), [
        'name' => $record->name, 'research_sources_present' => '1',
    ]);

    expect(DB::table('catalog_agency_verifications')->count())->toBe(0);
});

it('never words a draft as if the agency was contacted', function () {
    $record = avRecord();
    avDraft($record, ['outcome' => 'pending', 'confirmed_aspects' => [], 'responded_on' => null, 'requested_on' => '2026-02-01']);
    $html = rvPage($record);

    expect($html)->toContain('Entered so far: contact date 1 Feb 2026')->not->toContain('Agency contacted on');
});

it('shows one live resident app preview, for both record types', function (Closure $makeRecord) {
    $record = $makeRecord();
    $record->update(['research_metadata' => ['sources' => [['title' => 'Public reference: DFA – Passport requirements',
        'url' => 'https://passport.gov.ph/requirements', 'checked_on' => '2026-11-10', 'scope' => 'Requirements for first-time applicants']]]]);
    $html = rvPage($record);

    // The card is drawn in the browser from the fields being edited, so it needs no save.
    expect($html)->toContain('x-data="sourcePreview(')->toContain('data-resident-preview')->toContain('Live resident app preview')
        ->toContain('function sourcePreview(')->toContain('Public reference')->toContain('Official References')
        ->toContain('passport.gov.ph')
        // Display only: the preview adds no form fields of its own (5 fields + the kept publication date).
        ->and(substr_count($html, 'research_metadata[sources][${index}]['))->toBe(6)
        ->and(substr_count($html, 'data-resident-preview'))->toBe(1)
        ->and(substr_count($html, 'function sourcePreview('))->toBe(1);
})->with([
    'government id' => [fn () => avRecord()],
    'document' => [fn () => Document::create(['name' => 'Fixture document'])],
]);
