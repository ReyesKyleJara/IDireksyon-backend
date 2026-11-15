<?php

require_once __DIR__.'/../Support/agency_verification.php';

use App\Models\Document;
use App\Models\GovernmentId;

/* Public preview pages opened from the app's Share button. Fake data, in-memory SQLite. */

function prSource(array $overrides = []): array
{
    return array_replace(['title' => 'Public reference: DFA – Passport requirements', 'url' => 'https://passport.gov.ph/requirements',
        'checked_on' => '2026-11-10', 'scope' => 'Requirements for first-time applicants'], $overrides);
}

it('shows a public summary of an ID without login, with the disclaimer and public references', function () {
    $record = avRecord();
    $record->update(['description' => 'A travel document.', 'eligibility_age_type' => 'none', 'eligibility_citizenship' => 'filipino',
        'eligibility_residency' => 'none', 'research_metadata' => ['sources' => [prSource()]]]);

    $html = $this->get('/ids/'.$record->id)->assertOk()->getContent();

    expect($html)->toContain('Fixture Passport')->toContain('not a government website')
        ->toContain('Filipino citizens of any age.')->toContain('10-12 working days')->toContain('10 years')
        ->toContain('DFA – Passport requirements')->toContain('https://passport.gov.ph/requirements')->toContain('Checked on 10 Nov 2026')
        ->toContain('See the full guide in the IDireksyon app')->toContain('A travel document.')
        ->toContain('property="og:title"')->toContain(url('/ids/'.$record->id))
        // A summary only: no forms and no requirement checklist items.
        ->not->toContain('<form')->not->toContain('Birth certificate');
});

it('works for documents and returns 404 for unknown records', function () {
    $document = Document::create(['name' => 'Fixture Clearance', 'processing_time' => 'Not applicable']);

    $html = $this->get('/documents/'.$document->id)->assertOk()->getContent();
    expect($html)->toContain('Fixture Clearance')->toContain('Document')->not->toContain('Not applicable');

    $this->get('/ids/999999')->assertNotFound();
    $this->get('/documents/999999')->assertNotFound();
    $this->get('/ids/abc')->assertNotFound();
});

it('never links unsafe source addresses and never shows private agency details', function () {
    $record = avRecord();
    $record->update(['research_metadata' => ['sources' => [
        prSource(['url' => 'javascript:alert(1)', 'title' => 'Bad link']),
        prSource(['url' => 'https://user:pass@evil.example/page', 'title' => 'Credential link']),
    ]]]);
    avApproved($record->fresh(), ['evidence_reference' => 'PRIVATE-EVIDENCE-REF', 'notes' => 'PRIVATE-NOTE']);

    $html = $this->get('/ids/'.$record->id)->assertOk()->getContent();

    expect($html)->not->toContain('javascript:')->not->toContain('evil.example')
        ->not->toContain('Bad link')->not->toContain('PRIVATE-EVIDENCE-REF')->not->toContain('PRIVATE-NOTE');
});

it('escapes record text', function () {
    $record = GovernmentId::create(['name' => 'Fixture <script>alert(1)</script>', 'description' => '<b>bold</b>']);

    expect($this->get('/ids/'.$record->id)->assertOk()->getContent())->not->toContain('<script>alert(1)</script>')->not->toContain('<b>bold</b>');
});
