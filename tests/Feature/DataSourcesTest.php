<?php

require_once __DIR__.'/../Support/agency_verification.php';

use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\User;

/* Data Sources in Basic Information: Online Sources and the four calculated statuses. Fake data, in-memory SQLite. */

/** Saves the Basic Information step the way the page does: name, validity note, and the online sources list. */
function dsSave(GovernmentId|Document $record, ?array $sources, ?User $user = null, array $extra = [])
{
    $route = $record instanceof Document ? 'admin.documents.edit-wizard.save' : 'admin.government-ids.edit-wizard.save';
    $research = $sources === null ? [] : ['sources' => $sources];

    return test()->actingAs($user ?? avResearcher())->post(route($route, [$record, 'basic']), [
        'name' => $record->name, 'research_metadata' => [...$research, 'validity_note' => 'Keep this note.'],
        ...($sources === null ? [] : ['research_sources_present' => '1']), ...$extra,
    ]);
}

function dsSource(array $overrides = []): array
{
    return array_replace(['title' => 'DFA – Passport requirements', 'url' => 'https://passport.gov.ph/requirements',
        'checked_on' => '2026-11-10', 'scope' => 'Requirements for first-time applicants', 'section' => 'Requirements'], $overrides);
}

it('saves online sources with Basic Information and keeps every other research field', function () {
    $record = avRecord();
    $record->update(['research_metadata' => ['status' => 'needs_research', 'hidden_sections' => ['offices'],
        'special_cases' => [['id' => 'keep']], 'sources' => []]]);

    dsSave($record, [dsSource()])->assertSessionHasNoErrors();

    $research = $record->fresh()->research_metadata;
    expect($research['sources'])->toBe([dsSource()])
        ->and($research['validity_note'])->toBe('Keep this note.')
        ->and($research['hidden_sections'])->toBe(['offices'])
        ->and($research['special_cases'])->toBe([['id' => 'keep']])
        ->and($research['status'])->toBe('needs_research');
});

it('works for documents and keeps a publication date saved earlier', function () {
    $document = Document::create(['name' => 'Fixture document', 'research_metadata' => ['status' => 'draft',
        'sources' => [dsSource(['published_on' => '2025-01-15'])]]]);

    dsSave($document, [dsSource(['published_on' => '2025-01-15'])])->assertSessionHasNoErrors();

    expect($document->fresh()->research_metadata['sources'][0])->toHaveKey('published_on', '2025-01-15');
});

it('refuses unsafe links, future dates and missing fields without saving anything', function (array $source) {
    $record = avRecord();
    $record->update(['research_metadata' => ['status' => 'draft', 'sources' => [dsSource()]]]);

    dsSave($record, [$source])->assertSessionHasErrors();

    expect($record->fresh()->research_metadata['sources'])->toBe([dsSource()]);
})->with([
    'javascript link' => [dsSource(['url' => 'javascript:alert(1)'])],
    'not a link' => [dsSource(['url' => 'passport requirements'])],
    'future check date' => [dsSource(['checked_on' => now('Asia/Manila')->addDays(3)->toDateString()])],
    'no title' => [dsSource(['title' => ''])],
]);

it('keeps the reviewed rule: each source of reviewed content needs a checked date', function () {
    $record = avRecord();
    $record->update(['research_metadata' => ['status' => 'reviewed', 'sources' => [dsSource()]]]);

    dsSave($record, [dsSource(['checked_on' => null])], extra: ['research_metadata' => [
        'status' => 'reviewed', 'sources' => [dsSource(['checked_on' => null])]]])->assertSessionHasErrors('research_metadata.sources');
    expect($record->fresh()->research_metadata['sources'])->toBe([dsSource()]);
});

it('removes all sources only when the sources list was on the page', function () {
    $record = avRecord();
    $record->update(['research_metadata' => ['status' => 'draft', 'sources' => [dsSource()]]]);

    dsSave($record, null)->assertSessionHasNoErrors();
    expect($record->fresh()->research_metadata['sources'])->toBe([dsSource()]);

    dsSave($record, [])->assertSessionHasNoErrors();
    expect($record->fresh()->research_metadata['sources'])->toBe([]);
});

it('never makes a record agency-confirmed from online sources alone', function () {
    $record = avRecord();
    dsSave($record, [dsSource(), dsSource(['title' => 'Second page', 'url' => 'https://passport.gov.ph/fees'])]);

    expect(avService()->progress($record->fresh())['key'])->toBe('not_yet_confirmed')
        ->and(avService()->publicSummary($record->fresh()))->toBe(['status' => null, 'confirmations' => []])
        ->and($record->agencyVerifications()->count())->toBe(0);
});

it('shows the four statuses in plain words, calculated from the records', function () {
    $progress = fn (GovernmentId $record) => avService()->progress($record->fresh());

    $none = avRecord();
    expect($progress($none))->toMatchArray(['key' => 'not_yet_confirmed', 'label' => 'Not Yet Confirmed',
        'reasons' => ['No online sources or agency confirmation yet.']]);

    $draft = avRecord();
    avDraft($draft, ['outcome' => 'pending', 'confirmed_aspects' => [], 'responded_on' => null]);
    expect($progress($draft)['key'])->toBe('not_yet_confirmed');

    $asked = avRecord();
    avSubmitted($asked, ['outcome' => 'pending', 'confirmed_aspects' => [], 'responded_on' => null]);
    expect($progress($asked))->toMatchArray(['key' => 'waiting_for_agency', 'label' => 'Waiting for Agency']);

    $replied = avRecord();
    avSubmitted($replied);
    expect($progress($replied))->toMatchArray(['key' => 'pending_review', 'label' => 'Pending Review']);

    $confirmed = avRecord();
    avApproved($confirmed);
    expect($progress($confirmed))->toMatchArray(['key' => 'confirmed', 'label' => 'Confirmed']);
    avSubmitted($confirmed);
    expect($progress($confirmed)['reasons'])->toContain('A newer agency reply is waiting for review.');

    $outdated = avRecord();
    avApproved($outdated, ['confirmed_aspects' => ['fees']]);
    $outdated->fees()->first()->update(['amount_min' => 1400]);
    expect($progress($outdated))->toMatchArray(['key' => 'not_yet_confirmed',
        'reasons' => ['A confirmation is outdated because the information changed after approval.']]);
});

