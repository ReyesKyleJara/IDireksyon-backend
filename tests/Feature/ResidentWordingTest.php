<?php

require_once __DIR__.'/../Support/agency_verification.php';

use App\Models\Document;
use App\Models\GovernmentId;
use App\Services\ResidentWording;

/* Resident-friendly wording in the app API. CMS data is never changed. Fake data, in-memory SQLite. */

function rwId(array $attributes): GovernmentId
{
    return GovernmentId::create(['name' => 'Fixture ID', ...$attributes]);
}

it('turns eligibility choices into one natural sentence', function (array $choices, ?string $expected) {
    expect(ResidentWording::eligibility(rwId($choices)))->toBe($expected);
})->with([
    'Filipino, any age' => [['eligibility_age_type' => 'none', 'eligibility_citizenship' => 'filipino', 'eligibility_residency' => 'none'], 'Filipino citizens of any age.'],
    'Filipino, 18+, Santa Maria' => [['eligibility_age_type' => 'minimum', 'eligibility_min_age' => 18, 'eligibility_citizenship' => 'filipino', 'eligibility_residency' => 'santa_maria'],
        'Filipino citizens aged 18 or older living in Santa Maria, Bulacan.'],
    'age range only' => [['eligibility_age_type' => 'range', 'eligibility_min_age' => 15, 'eligibility_max_age' => 30, 'eligibility_citizenship' => 'none', 'eligibility_residency' => 'none'], 'Applicants aged 15 to 30.'],
    'residents 60+' => [['eligibility_age_type' => 'minimum', 'eligibility_min_age' => 60, 'eligibility_citizenship' => 'none', 'eligibility_residency' => 'santa_maria'], 'Residents of Santa Maria, Bulacan aged 60 or older.'],
    'Philippine residents' => [['eligibility_age_type' => 'none', 'eligibility_citizenship' => 'none', 'eligibility_residency' => 'philippines'], 'Residents of the Philippines.'],
    'custom residency' => [['eligibility_age_type' => 'none', 'eligibility_citizenship' => 'none', 'eligibility_residency' => 'custom',
        'eligibility_residency_custom' => 'Must be a resident of the barangay issuing the clearance.'], 'Must be a resident of the barangay issuing the clearance.'],
    'no restrictions: hidden' => [['eligibility_age_type' => 'none', 'eligibility_citizenship' => 'none', 'eligibility_residency' => 'none'], null],
    'not applicable: hidden' => [['eligibility_age_type' => 'not_applicable', 'eligibility_citizenship' => 'not_applicable', 'eligibility_residency' => 'not_applicable'], null],
    'other conditions only' => [['eligibility_age_type' => 'none', 'eligibility_citizenship' => 'none', 'eligibility_residency' => 'none',
        'eligibility_other_conditions' => "Must be a registered voter.\nBring a valid ID."], "Must be a registered voter.\nBring a valid ID."],
    'older free text keeps real lines' => [['eligibility' => "Age: No age requirement.\nCitizenship: No specific citizenship requirement.\nMust be employed."], 'Must be employed.'],
    'older free text, nothing real' => [['eligibility' => "Age: No age requirement.\nResidency: No specific residency requirement."], null],
]);

it('hides "Not applicable", explains a bare "Varies", and keeps real values', function () {
    $data = ResidentWording::apply([
        'processing_time' => 'Varies', 'processing_time_note' => null, 'validity' => 'Not applicable',
        'fee' => 'Processing Fee: ₱100.00; Courier Delivery: Varies',
        'fees' => [['label' => 'Courier', 'display_amount' => 'Varies', 'options' => [['display_amount' => 'Varies'], ['display_amount' => '₱950.00']]]],
        'request_paths' => [['processing' => 'Not applicable'], ['processing' => 'Varies'], ['processing' => '10–12 working days']],
    ], rwId([]));

    expect($data['processing_time'])->toBe('Varies. Please ask the office.')
        ->and($data['validity'])->toBeNull()
        ->and($data['fee'])->toBe('Processing Fee: ₱100.00; Courier Delivery: Amount varies')
        ->and($data['fees'][0]['display_amount'])->toBe('Amount varies')
        ->and(array_column($data['fees'][0]['options'], 'display_amount'))->toBe(['Amount varies', '₱950.00'])
        ->and(array_column($data['request_paths'], 'processing'))->toBe([null, 'Varies. Please ask the office.', '10–12 working days']);

    // With an explanatory note, "Varies" stays short and the note explains it.
    expect(ResidentWording::apply(['processing_time' => 'Varies', 'processing_time_note' => 'Depends on delivery.'], rwId([]))['processing_time'])->toBe('Varies');
});

it('uses the new wording in both detail APIs without changing CMS data', function () {
    $id = rwId(['eligibility_age_type' => 'none', 'eligibility_citizenship' => 'filipino', 'eligibility_residency' => 'none',
        'eligibility' => "Age: No age requirement.\nCitizenship: Filipino citizen required.\nResidency: No specific residency requirement.",
        'validity_type' => 'varies', 'validity' => 'Varies']);
    $document = Document::create(['name' => 'Fixture document', 'eligibility_age_type' => 'none', 'eligibility_citizenship' => 'none',
        'eligibility_residency' => 'none', 'eligibility' => "Age: No age requirement.", 'processing_time' => 'Not applicable']);
    $before = [$id->fresh()->getAttributes(), $document->fresh()->getAttributes()];

    $this->getJson('/api/government-ids/'.$id->id)->assertOk()
        ->assertJsonPath('data.eligibility', 'Filipino citizens of any age.')
        ->assertJsonPath('data.validity', 'Varies. Please ask the issuing office.');
    $this->getJson('/api/documents/'.$document->id)->assertOk()
        ->assertJsonPath('data.eligibility', null)
        ->assertJsonPath('data.processing_time', null);

    expect([$id->fresh()->getAttributes(), $document->fresh()->getAttributes()])->toBe($before);
});

it('keeps agency confirmation fingerprints independent of the wording', function () {
    $record = avRecord();
    $record->update(['eligibility_age_type' => 'none', 'eligibility_citizenship' => 'filipino', 'eligibility_residency' => 'none']);
    $approved = avApproved($record->fresh(), ['confirmed_aspects' => ['eligibility']]);

    expect(app(\App\Services\CatalogVerificationFingerprint::class)->isCurrent($approved->fresh()))->toBeTrue();
    $this->getJson('/api/government-ids/'.$record->id)->assertOk();
    expect(app(\App\Services\CatalogVerificationFingerprint::class)->isCurrent($approved->fresh()))->toBeTrue();
});
