<?php

namespace App\Support;

use App\Models\GovernmentIdRequirementSet;
use App\Services\ApplicabilityEvaluator;

/** CMS presentation only; rule status belongs to the shared evaluator. */
final class ApplicabilityProfileSummary
{
    public static function forProfile(GovernmentIdRequirementSet $profile): array
    {
        $manual = (new ApplicabilityEvaluator)->needsRuleConfiguration($profile);
        $applicant = $profile->applicant_type === 'custom'
            ? ($profile->applicant_type_custom ?: 'Manual profile')
            : (GovernmentIdRequirementSet::APPLICANT_TYPES[$profile->applicant_type] ?? 'Manual profile');
        $application = $profile->application_type === 'custom'
            ? ($profile->application_type_custom ?: 'Custom application')
            : ($profile->application_type === 'new' ? 'New Application'
                : (GovernmentIdRequirementSet::APPLICATION_TYPES[$profile->application_type] ?? 'Custom application'));
        $age = match (true) {
            $profile->applicant_type === 'adult' => 'Age 18+',
            $profile->applicant_type === 'minor' => 'Below 18',
            $profile->min_age !== null && $profile->max_age !== null => "Ages {$profile->min_age}–{$profile->max_age}",
            $profile->min_age !== null => "Age {$profile->min_age}+",
            $profile->max_age !== null => "Age {$profile->max_age} and below",
            default => 'No age restriction',
        };

        return [
            'summary' => implode(' · ', [$applicant, $application, $age]),
            'needs_rule_setup' => $manual,
            'status' => $manual ? 'Needs rule setup' : 'Structured profile',
            'explanation' => $manual
                ? 'Automatic matching is disabled until structured applicability rules are configured. Manual selection remains available. Labels and age limits alone do not resolve a custom profile.'
                : 'These structured fields support automatic matching. Manual selection remains available.',
        ];
    }
}
