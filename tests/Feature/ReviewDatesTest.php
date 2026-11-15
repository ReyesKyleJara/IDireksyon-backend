<?php

require_once __DIR__.'/../Support/agency_verification.php';

use App\Models\ContentChangeLog;
use App\Models\Document;
use App\Models\GovernmentId;
use App\Services\CatalogRevision;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/* Last Updated (automatic) and Last Reviewed ("Mark as reviewed"). Fake data, in-memory SQLite. */

function rdSource(array $overrides = []): array
{
    return array_replace(['title' => 'Public reference: DFA – Passport', 'url' => 'https://passport.gov.ph/requirements',
        'checked_on' => '2026-10-20', 'scope' => 'Requirements', 'section' => ''], $overrides);
}

function rdBasic(GovernmentId|Document $record, array $fields, $user = null)
{
    $route = $record instanceof Document ? 'admin.documents.edit-wizard.save' : 'admin.government-ids.edit-wizard.save';

    return test()->actingAs($user ?? avResearcher())->post(route($route, [$record, 'basic']), ['name' => $record->name, ...$fields]);
}

function rdReview(GovernmentId|Document $record, $user = null, ?string $revision = null)
{
    $route = $record instanceof Document ? 'admin.documents.review' : 'admin.government-ids.review';

    return test()->actingAs($user ?? avResearcher())->from('/admin')
        ->post(route($route, $record), ['_catalog_revision' => $revision ?? CatalogRevision::token($record)]);
}

function rdWithSource(): GovernmentId
{
    $record = avRecord();
    $record->update(['research_metadata' => ['status' => 'draft', 'sources' => [rdSource()]]]);

    return $record->fresh();
}

afterEach(fn () => Carbon::setTestNow());

it('sets Last Updated when record information changes, and not for sources, images or reviews', function () {
    Carbon::setTestNow('2026-11-01 08:00:00');
    $record = rdWithSource();
    expect($record->content_updated_at->toDateTimeString())->toBe('2026-11-01 08:00:00');

    Carbon::setTestNow('2026-11-02 08:00:00');
    rdBasic($record, ['research_metadata' => ['sources' => [rdSource(['title' => 'Public reference: DFA – New title'])]],
        'research_sources_present' => '1'])->assertSessionHasNoErrors();
    expect($record->fresh()->research_metadata['sources'][0]['title'])->toContain('New title')
        ->and($record->fresh()->content_updated_at->toDateTimeString())->toBe('2026-11-01 08:00:00');

    $record->forceFill(['image_path' => 'catalog/x.png'])->save();
    ContentChangeLog::record($record, 'updated', ['image']);
    expect($record->fresh()->content_updated_at->toDateTimeString())->toBe('2026-11-01 08:00:00');

    Carbon::setTestNow('2026-11-03 08:00:00');
    rdBasic($record, ['name' => 'Philippine Passport (renamed)'])->assertSessionHasNoErrors();
    expect($record->fresh()->content_updated_at->toDateTimeString())->toBe('2026-11-03 08:00:00');

    Carbon::setTestNow('2026-11-04 08:00:00');
    $record->fees()->first()->update(['amount_min' => 1200]);
    ContentChangeLog::record($record, 'updated', ['fees']); // what the fee editor logs
    expect($record->fresh()->content_updated_at->toDateTimeString())->toBe('2026-11-04 08:00:00');
});

it('counts a changed validity note as information, but not the encoding status', function () {
    Carbon::setTestNow('2026-11-01 08:00:00');
    $record = rdWithSource();

    Carbon::setTestNow('2026-11-02 08:00:00');
    $record->update(['research_metadata' => [...$record->research_metadata, 'status' => 'needs_research']]);
    expect($record->fresh()->content_updated_at->toDateTimeString())->toBe('2026-11-01 08:00:00');

    $record->update(['research_metadata' => [...$record->research_metadata, 'validity_note' => 'Valid for 10 years for adults.']]);
    expect($record->fresh()->content_updated_at->toDateTimeString())->toBe('2026-11-02 08:00:00');
});

it('marks a record as reviewed without changing Last Updated, and records who did it', function () {
    Carbon::setTestNow('2026-11-01 08:00:00');
    $record = rdWithSource();
    $reviewer = avResearcher();

    Carbon::setTestNow('2026-11-05 09:30:00');
    rdReview($record, $reviewer)->assertRedirect('/admin')->assertSessionHasNoErrors();

    $fresh = $record->fresh();
    expect($fresh->last_reviewed_at->toDateTimeString())->toBe('2026-11-05 09:30:00')
        ->and($fresh->last_reviewed_by)->toBe($reviewer->id)
        ->and($fresh->content_updated_at->toDateTimeString())->toBe('2026-11-01 08:00:00')
        ->and(ContentChangeLog::where('action', 'reviewed')->where('entity_id', $record->id)->exists())->toBeTrue()
        // A review is never an agency confirmation.
        ->and($fresh->agencyVerifications()->count())->toBe(0);
});

it('refuses Mark as reviewed without an online source checked date, a page revision, or CMS access', function () {
    $record = avRecord();
    rdReview($record)->assertSessionHasErrors('review');

    $record->update(['research_metadata' => ['sources' => [rdSource(['checked_on' => ''])]]]);
    rdReview($record->fresh())->assertSessionHasErrors('review');

    $ready = rdWithSource();
    test()->actingAs(avResearcher())->post(route('admin.government-ids.review', $ready))->assertSessionHasErrors('_catalog_revision');
    rdReview($ready, null, 'stale-revision')->assertSessionHasErrors('_catalog_revision');

    $resident = avResearcher();
    $resident->forceFill(['role' => 'resident'])->save();
    rdReview($ready, $resident)->assertForbidden();

    expect(GovernmentId::whereNotNull('last_reviewed_at')->count())->toBe(0);
});

it('keeps the review date when information changes later and shows it may need reviewing again', function () {
    Carbon::setTestNow('2026-11-01 08:00:00');
    $record = rdWithSource();
    Carbon::setTestNow('2026-11-05 08:00:00');
    rdReview($record);
    Carbon::setTestNow('2026-11-07 08:00:00');
    rdBasic($record->fresh(), ['name' => 'Philippine Passport (changed)']);

    $fresh = $record->fresh();
    expect($fresh->last_reviewed_at->toDateTimeString())->toBe('2026-11-05 08:00:00')
        ->and(\App\Services\CatalogReviewDates::state($fresh)['updated_since_review'])->toBeTrue();

    $html = test()->actingAs(avSuperAdmin())->get(route('admin.government-ids.show', $fresh))->assertOk()->getContent();
    expect($html)->toContain('Last updated:')->toContain('7 Nov 2026')->toContain('Last reviewed:')->toContain('5 Nov 2026')
        ->toContain('data-updated-since-review')->toContain('Mark as reviewed');

    Carbon::setTestNow('2026-11-08 08:00:00');
    rdReview($fresh);
    expect(\App\Services\CatalogReviewDates::state($record->fresh())['updated_since_review'])->toBeFalse();
});

it('shows both dates on the record page, with a hint instead of the button when there is no checked source', function () {
    $record = avRecord();
    $html = test()->actingAs(avSuperAdmin())->get(route('admin.government-ids.show', $record))->assertOk()->getContent();

    expect($html)->toContain('data-review-dates')->toContain('Not reviewed yet')
        ->toContain('Add an online source with its checked date to mark this as reviewed.')
        ->not->toContain('data-updated-since-review')
        ->not->toContain(route('admin.government-ids.review', $record));
});

it('sends both dates to the app without the reviewer, for IDs and documents', function () {
    Carbon::setTestNow('2026-11-01 08:00:00');
    $id = rdWithSource();
    $document = Document::create(['name' => 'Fixture document', 'research_metadata' => ['sources' => [rdSource()]]]);
    Carbon::setTestNow('2026-11-05 08:00:00');
    rdReview($id);
    rdReview($document);

    foreach (['/api/government-ids/'.$id->id, '/api/documents/'.$document->id] as $url) {
        $dates = test()->getJson($url)->assertOk()->json('data.review_dates');
        expect($dates)->toBe(['last_updated_at' => '2026-11-01T08:00:00.000000Z', 'last_reviewed_at' => '2026-11-05T08:00:00.000000Z',
            'updated_since_review' => false]);
    }
    expect(json_encode(test()->getJson('/api/government-ids/'.$id->id)->json()))->not->toContain('last_reviewed_by');
});

it('fills Last Updated for existing records from the change history, marks nothing reviewed, and rolls back cleanly', function () {
    $migration = require database_path('migrations/2026_11_15_000100_add_review_dates_to_catalog_records.php');
    Carbon::setTestNow('2026-10-01 08:00:00');
    $withHistory = avRecord();
    $noHistory = avRecord();
    Carbon::setTestNow();
    $migration->down();
    expect(Schema::hasColumn('government_ids', 'content_updated_at'))->toBeFalse();

    DB::table('content_change_logs')->delete();
    DB::table('content_change_logs')->insert([
        ['action' => 'updated', 'entity_type' => 'government_id', 'entity_id' => $withHistory->id, 'changed_fields' => json_encode(['fees']), 'created_at' => '2026-10-10 08:00:00'],
        ['action' => 'updated', 'entity_type' => 'government_id', 'entity_id' => $withHistory->id, 'changed_fields' => json_encode(['image']), 'created_at' => '2026-10-20 08:00:00'],
    ]);
    $before = DB::table('government_ids')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();

    $migration->up();

    $rows = DB::table('government_ids')->orderBy('id')->get()->keyBy('id');
    expect($rows[$withHistory->id]->content_updated_at)->toStartWith('2026-10-10 08:00:00')
        ->and($rows[$noHistory->id]->content_updated_at)->toStartWith('2026-10-01 08:00:00')
        ->and($rows->whereNotNull('last_reviewed_at')->count())->toBe(0)
        ->and($rows->map(fn ($row) => array_diff_key((array) $row, array_flip(['content_updated_at', 'last_reviewed_at', 'last_reviewed_by'])))->values()->all())
        ->toBe($before);
});
