<?php

/*
 * Fake fixtures for agency verification tests (in-memory SQLite only).
 * No real agency is involved.
 */

use App\Models\CatalogAgencyVerification;
use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\User;
use App\Services\CatalogAgencyValidation;

function avService(): CatalogAgencyValidation
{
    return app(CatalogAgencyValidation::class);
}

function avResearcher(array $attributes = []): User
{
    return User::factory()->create(['role' => 'researcher', ...$attributes]);
}

function avSuperAdmin(array $attributes = []): User
{
    return User::factory()->create(['role' => 'super_admin', ...$attributes]);
}

/** A record with two request paths (first-time and renewal), a fee and record-level facts. */
function avRecord(): GovernmentId
{
    $record = GovernmentId::create(['name' => 'Fixture Passport', 'eligibility' => 'Filipino citizens',
        'processing_time' => '10-12 working days', 'validity' => '10 years', 'fee' => 'PHP 950']);
    $first = $record->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'all']);
    $first->groups()->create(['title' => 'Proof of identity', 'rule' => 'all'])
        ->items()->create(['type' => 'custom', 'custom_name' => 'Birth certificate', 'submission_format' => 'not_specified']);
    $renewal = $record->requirementSets()->create(['application_type' => 'renewal', 'applicant_type' => 'all']);
    $renewal->groups()->create(['title' => 'Current passport', 'rule' => 'all'])
        ->items()->create(['type' => 'custom', 'custom_name' => 'Old passport', 'submission_format' => 'not_specified']);
    $record->fees()->create(['label' => 'Regular processing', 'type' => 'fixed', 'amount_min' => 950]);

    return $record->fresh();
}

function avFirstPath(GovernmentId $record): int
{
    return (int) $record->requirementSets()->where('application_type', 'new')->value('id');
}

function avRenewalPath(GovernmentId $record): int
{
    return (int) $record->requirementSets()->where('application_type', 'renewal')->value('id');
}

function avInput(array $overrides = []): array
{
    return array_replace([
        'agency_name' => 'Fixture Agency',
        'method' => 'written_letter',
        'requested_on' => '2026-02-01',
        'responded_on' => '2026-02-10',
        'outcome' => 'confirmed',
        'confirmed_aspects' => ['requirements', 'fees'],
        'not_covered_aspects' => [],
        'scope' => 'Requirements and fees for first-time applications (test fixture).',
        'evidence_reference' => 'TEST Appendix C - Letter 01',
        'contact_role' => 'Consular officer',
    ], $overrides);
}

function avDraft(GovernmentId|Document $record, array $overrides = [], ?User $recorder = null): CatalogAgencyVerification
{
    return avService()->createDraft($recorder ?? avResearcher(), $record, avInput($overrides));
}

function avSubmitted(GovernmentId|Document $record, array $overrides = [], ?User $recorder = null): CatalogAgencyVerification
{
    $recorder ??= avResearcher();

    return avService()->submit($recorder, avDraft($record, $overrides, $recorder));
}

function avApproved(GovernmentId|Document $record, array $overrides = [], ?User $recorder = null, ?User $approver = null): CatalogAgencyVerification
{
    return avService()->approve($approver ?? avSuperAdmin(), avSubmitted($record, $overrides, $recorder));
}
