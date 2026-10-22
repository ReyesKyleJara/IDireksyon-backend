<?php

namespace App\Services;

use App\Models\GovernmentId;
use App\Models\GovernmentIdApplicationStep;
use App\Models\GovernmentIdRequirementSet;
use App\Support\ApplicantContext;
use App\Support\ResidentRequirements;

/** Read-only public projection; all rule decisions belong to ApplicabilityEvaluator. */
class GovernmentIdApplicability
{
    public function __construct(
        private readonly ApplicabilityEvaluator $evaluator,
        private readonly GovernmentIdApplicationGuide $guide,
    ) {}

    public function evaluate(GovernmentId $id, ApplicantContext $context, ?int $selectedProfileId = null): array
    {
        $id->loadMissing([
            'requirementSets.groups.items.governmentId', 'requirementSets.groups.items.document',
            'requirementSets.groups.ways.items.governmentId', 'requirementSets.groups.ways.items.document',
        ]);
        // Scope again at the service boundary, including for future non-HTTP callers.
        $manualSelection = $selectedProfileId === null ? null : $id->requirementSets()->findOrFail($selectedProfileId);
        $matched = [];
        $manual = [];
        $pending = [];
        $missing = [];
        foreach ($id->requirementSets as $profile) {
            $result = $this->evaluator->profileApplies($profile, $context);
            if ($result === true) {
                $matched[] = $profile;
            } elseif ($result === null) {
                $pending[] = $profile;
                $missing = array_merge($missing, $this->evaluator->missingContext($profile, $context));
            }
            if ($this->evaluator->needsRuleConfiguration($profile)) {
                $manual[] = $profile;
            }
        }
        $missing = array_values(array_unique($missing));
        sort($missing);
        $count = count($matched);
        $status = match (true) {
            $manualSelection !== null => 'manual_selected',
            $missing !== [] => 'insufficient_context',
            $manual !== [] => 'manual_confirmation_required',
            $count > 1 => 'multiple_matches',
            $count === 1 => 'matched',
            default => 'no_match',
        };
        $selected = $manualSelection ?? ($status === 'matched' ? $matched[0] : null);
        $activeProfiles = $manualSelection ? [$manualSelection] : $matched;
        $activeIds = array_map(fn ($profile) => $profile->id, $activeProfiles);
        $pendingIds = $manualSelection ? [] : array_map(fn ($profile) => $profile->id, $pending);
        $requirements = collect(ResidentRequirements::forId($id));
        if ($manualSelection) {
            $steps = $this->guide->stepsForProfile($id, $manualSelection->id);
            $pendingSteps = [];
        } else {
            $guide = $this->guide->applicableSteps($id, $context);
            $steps = $guide['steps'];
            $pendingSteps = $guide['pending_steps'];
        }

        return [
            'government_id' => $id->only(['id', 'name']),
            'applicant_context' => [
                'age' => $context->age,
                'application_type' => $context->applicationType,
                'owned_government_id_ids' => $context->governmentIdIds,
                'owned_document_ids' => $context->documentIds,
            ],
            'applicability' => [
                'status' => $status,
                'selection_mode' => $manualSelection ? 'manual' : ($selected ? 'automatic' : 'none'),
                'has_single_automatic_match' => $count === 1,
                'has_multiple_automatic_matches' => $count > 1,
                'requires_manual_confirmation' => $manualSelection === null && ($pending !== [] || $count > 1),
                'missing_context' => $manualSelection ? [] : $missing,
                'available_profiles' => $id->requirementSets->map(fn ($profile) => $this->profile($profile))->values()->all(),
                'matched_profiles' => array_map(fn ($profile) => $this->profile($profile), $matched),
                'manual_profiles' => array_map(fn ($profile) => $this->profile($profile), $manual),
                'pending_profiles' => $manualSelection ? [] : array_map(fn ($profile) => $this->profile($profile), $pending),
                'selected_profile' => $selected ? $this->profile($selected) : null,
            ],
            'requirements' => [
                'profiles' => $requirements->whereIn('id', $activeIds)->values()->all(),
                'pending_profiles' => $requirements->whereIn('id', $pendingIds)->values()->all(),
            ],
            'application_guide' => [
                'steps' => array_map(fn ($step) => $this->step($step), $steps),
                'pending_steps' => array_map(fn ($step) => $this->step($step), $pendingSteps),
            ],
        ];
    }

    private function profile(GovernmentIdRequirementSet $profile): array
    {
        return [
            'id' => $profile->id,
            'label' => $profile->display_label,
            ...$profile->only(['application_type', 'applicant_type', 'min_age', 'max_age']),
            'matching_mode' => $this->evaluator->needsRuleConfiguration($profile) ? 'manual_only' : 'automatic',
        ];
    }

    private function step(GovernmentIdApplicationStep $step): array
    {
        return [
            ...$step->only(['id', 'title', 'short_description', 'type', 'sort_order']),
            'applicability_profile_id' => $step->requirement_set_id,
            'blocks' => $step->blocks->map(fn ($block) => $block->only(['id', 'type', 'section_title', 'content', 'sort_order']))->values()->all(),
        ];
    }
}
