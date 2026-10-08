<?php

namespace App\Support;

use App\Models\GovernmentId;
use App\Models\GovernmentIdRequirementSet;
use App\Models\GovernmentIdRequirementWay;

/** Resident display projection; does not evaluate eligibility or readiness. */
class ResidentRequirements
{
    public static function forId(GovernmentId $id): array
    {
        return $id->requirementSets->map(fn ($set) => [
            'id' => $set->id,
            'application_type' => $set->application_type,
            'application_type_label' => $set->application_type === 'custom'
                ? $set->application_type_custom : GovernmentIdRequirementSet::APPLICATION_TYPES[$set->application_type],
            'applicant_type' => $set->applicant_type,
            'applicant_type_label' => $set->applicant_type === 'custom'
                ? ($set->applicant_type_custom ?: 'Custom age range') : GovernmentIdRequirementSet::APPLICANT_TYPES[$set->applicant_type],
            'display_label' => $set->display_label,
            'min_age' => $set->min_age,
            'max_age' => $set->max_age,
            'groups' => $set->groups->map(function ($group) {
                // Older checklists store items directly on the group. Project them
                // as one option without migrating or changing their meaning.
                $ways = $group->ways->isNotEmpty()
                    ? $group->ways->map(fn ($way) => self::way($way->id, $way->required_count, $way->items, $way->qualification_type, $way->qualification_scope, $way->qualification_custom))->values()->all()
                    : [self::way(null, $group->rule === 'choose_one' ? 1 : $group->items->count(), $group->items)];

                return [
                    'id' => $group->id,
                    'title' => $group->title,
                    'rule' => $group->rule,
                    'condition_type' => $group->condition_type,
                    'condition_label' => $group->condition_type === 'always' ? null : $group->condition_label,
                    'ways' => $ways,
                ];
            })->values()->all(),
        ])->values()->all();
    }

    private static function way($id, int $count, $items, string $qualification = 'none', string $scope = 'every', ?string $custom = null): array
    {
        $label = $qualification === 'custom' ? $custom : (GovernmentIdRequirementWay::QUALIFICATIONS[$qualification] ?? null);
        $qualificationLabel = $qualification === 'none' ? null
            : ($scope === 'at_least_one' ? 'At least one selected item must: ' : 'Each selected item must: ').$label;

        return [
            'id' => $id,
            'required_count' => $count,
            'qualification_type' => $qualification,
            'qualification_scope' => $scope,
            'qualification_custom' => $custom,
            'qualification_label' => $qualificationLabel,
            'items' => $items->map(fn ($item) => [
                'id' => $item->id,
                'type' => $item->type,
                'government_id_id' => $item->government_id_id,
                'document_id' => $item->document_id,
                'name' => $item->display_name,
                'quantity' => $item->quantity,
                'submission_format' => $item->submission_format,
                'submission_label' => $item->submission_format === 'not_specified' ? null : $item->submission_label,
                'copies' => $item->copies,
                'instructions' => $item->instructions,
            ])->values()->all(),
        ];
    }
}
