<?php

namespace App\Support;

use App\Models\GovernmentId;
use Illuminate\Support\Arr;

/** Public allowlist only. Order, satisfaction and applicability come from the planner. */
final class ResidentPrerequisitePlan
{
    public static function forPlan(GovernmentId $target, ApplicantContext $context, PrerequisitePlan $plan): array
    {
        $status = $plan->resolutionStatus();
        $base = [
            'government_id' => $target->only(['id', 'name']),
            'applicant_context' => [
                'age' => $context->age,
                'application_type' => $context->applicationType,
                'owned_government_id_ids' => $context->governmentIdIds,
                'owned_document_ids' => $context->documentIds,
            ],
            'status' => $status,
            'plan_is_complete' => $status === 'ready',
        ];
        if ($status === 'configuration_error') {
            return [...$base, 'plan' => [], 'satisfied' => [], 'dependencies' => [],
                'applicability' => null, 'pending_applicability' => [], 'pending_prerequisites' => [], 'missing_context' => [],
                'error' => ['code' => 'invalid_prerequisite_configuration', 'message' => 'The prerequisite plan is unavailable because its configuration needs review. Please try again later.'],
            ];
        }

        $nodes = [];
        foreach ($plan->nodes as $node) {
            $nodes[$node->key()] = $node;
        }
        $rootApplicability = null;
        $pendingApplicability = [];
        $profiles = [];
        $missing = [];
        foreach ($plan->applicabilityResolutions as $resolution) {
            $applicability = self::applicability($resolution['applicability']);
            if ($resolution['government_id']['id'] === $target->id) {
                $rootApplicability = $applicability;
            }
            foreach ($applicability['available_profiles'] as $profile) {
                $profiles[$profile['id']] = $profile;
            }
            if (! in_array($applicability['status'], ['matched', 'manual_selected'], true)) {
                $pendingApplicability[] = [
                    'government_id' => Arr::only($resolution['government_id'], ['id', 'name']),
                    'applicability' => $applicability,
                ];
                $missing = array_merge($missing, $applicability['missing_context']);
            }
        }
        $missing = array_values(array_unique($missing));
        sort($missing);

        $dependencies = [];
        foreach ($plan->edges as $edge) {
            $dependencies[] = [
                'target' => self::reference($nodes[$edge['from']]),
                'prerequisite' => self::reference($nodes[$edge['to']]),
                'applicability_profile' => $profiles[$edge['applicability_profile_id']] ?? null,
            ];
        }
        $pendingPrerequisites = [];
        foreach ($plan->pendingApplicability as $pending) {
            $owner = $nodes['government_id:'.$pending['government_id_id']];
            foreach ($pending['prerequisites'] as $candidate) {
                $pendingPrerequisites[] = [
                    'target' => self::reference($owner),
                    'prerequisite' => self::node(new PrerequisitePlanNode(
                        $candidate['kind'], $candidate['id'], $candidate['name'], false,
                        $candidate['satisfied'], $owner->depth + 1, null,
                    )),
                    'applicability_profile' => $profiles[$candidate['applicability_profile_id']],
                ];
            }
        }

        return [...$base,
            'plan' => array_map(self::node(...), $plan->orderedMissing),
            'satisfied' => array_values(array_map(self::node(...), array_filter($plan->nodes, fn ($node) => $node->satisfied))),
            'dependencies' => $dependencies,
            'applicability' => $rootApplicability,
            'pending_applicability' => $pendingApplicability,
            'pending_prerequisites' => $pendingPrerequisites,
            'missing_context' => $missing,
            'error' => null,
        ];
    }

    private static function node(PrerequisitePlanNode $node): array
    {
        return [
            ...self::reference($node),
            'status' => $node->satisfied ? 'satisfied' : ($node->isTarget ? 'target' : 'missing'),
            'order' => $node->order,
            'depth' => $node->depth,
            'is_target' => $node->isTarget,
        ];
    }

    private static function reference(PrerequisitePlanNode $node): array
    {
        return ['type' => $node->kind, 'id' => $node->id, 'name' => $node->name];
    }

    private static function applicability(array $resolution): array
    {
        $public = Arr::only($resolution, [
            'status', 'selection_mode', 'has_single_automatic_match', 'has_multiple_automatic_matches',
            'requires_manual_confirmation', 'missing_context',
        ]);
        foreach (['available_profiles', 'matched_profiles', 'manual_profiles', 'pending_profiles'] as $key) {
            $public[$key] = array_map(self::profile(...), $resolution[$key]);
        }
        $public['selected_profile'] = $resolution['selected_profile'] === null ? null : self::profile($resolution['selected_profile']);

        return $public;
    }

    private static function profile(array $profile): array
    {
        return Arr::only($profile, ['id', 'label', 'application_type', 'applicant_type', 'min_age', 'max_age', 'matching_mode']);
    }
}
