<?php

namespace App\Services;

use App\Models\GovernmentIdApplicationStep;
use App\Models\GovernmentIdRequirementGroup;
use App\Models\GovernmentIdRequirementSet;
use App\Support\ApplicantContext;

/** Read-only evaluation. null MUST remain visible for manual confirmation. */
final class ApplicabilityEvaluator
{
    public function needsRuleConfiguration(GovernmentIdRequirementSet $profile): bool
    {
        return ! in_array($profile->application_type, ['new', 'renewal', 'replacement'], true)
            || ! in_array($profile->applicant_type, ['all', 'adult', 'minor'], true)
            || ($profile->min_age !== null && $profile->max_age !== null && $profile->min_age > $profile->max_age);
    }

    public function profileApplies(GovernmentIdRequirementSet $profile, ApplicantContext $context): ?bool
    {
        // Never interpret free-text labels, including custom labels with age limits.
        if ($this->needsRuleConfiguration($profile)) {
            return null;
        }

        $minimum = $profile->min_age;
        $maximum = $profile->max_age;
        if ($profile->applicant_type === 'adult') {
            $minimum = max(18, $minimum ?? 18);
        } elseif ($profile->applicant_type === 'minor') {
            $maximum = min(17, $maximum ?? 17);
        }

        $application = $context->applicationType === null ? null : $context->applicationType === $profile->application_type;
        $age = $minimum === null && $maximum === null ? true
            : ($context->age === null ? null
                : (($minimum === null || $context->age >= $minimum) && ($maximum === null || $context->age <= $maximum)));

        return $this->all([$application, $age]);
    }

    /** Return every match; overlapping profiles are not resolved by arbitrary order. */
    public function matchingProfiles(iterable $profiles, ApplicantContext $context): array
    {
        $matches = [];
        foreach ($profiles as $profile) {
            if ($this->profileApplies($profile, $context) === true) {
                $matches[] = $profile;
            }
        }

        return $matches;
    }

    /** Only ask for facts that could resolve an otherwise undecided structured profile. */
    public function missingContext(GovernmentIdRequirementSet $profile, ApplicantContext $context): array
    {
        if ($this->needsRuleConfiguration($profile) || $this->profileApplies($profile, $context) !== null) {
            return [];
        }

        $missing = [];
        if ($context->applicationType === null) {
            $missing[] = 'application_type';
        }
        if ($context->age === null && ($profile->applicant_type !== 'all' || $profile->min_age !== null || $profile->max_age !== null)) {
            $missing[] = 'age';
        }

        return $missing;
    }

    /** Evaluates the local condition; callers must also evaluate its parent profile. */
    public function groupApplies(GovernmentIdRequirementGroup $group, ApplicantContext $context): ?bool
    {
        return match ($group->condition_type) {
            'always' => true,
            'below_18' => $context->age === null ? null : $context->age < 18,
            default => null, // Other existing conditions need facts/rules not yet represented.
        };
    }

    public function stepApplies(GovernmentIdApplicationStep $step, ApplicantContext $context): ?bool
    {
        if ($step->requirement_set_id === null && $step->government_id_id !== null) {
            return true;
        }
        // Do not trigger a database lookup or treat a missing parent as "everyone".
        if (! $step->relationLoaded('requirementSet') || ! $step->requirementSet) {
            return null;
        }

        return $this->profileApplies($step->requirementSet, $context);
    }

    private function all(array $results): ?bool
    {
        if (in_array(false, $results, true)) {
            return false;
        }

        return in_array(null, $results, true) ? null : true;
    }
}
