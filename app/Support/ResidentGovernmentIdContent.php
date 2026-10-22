<?php

namespace App\Support;

use App\Models\GovernmentId;

/** Explicit read-only projection: never serialize users, audit metadata or pivots wholesale. */
class ResidentGovernmentIdContent
{
    public static function forId(GovernmentId $id): array
    {
        $id->loadMissing(['offices.schedules.intervals', 'requirementSets.applicationSteps.blocks']);
        return [
            'id' => $id->id,
            'eligibility_details' => $id->only([
                'eligibility_age_type', 'eligibility_min_age', 'eligibility_max_age',
                'eligibility_citizenship', 'eligibility_residency', 'eligibility_residency_custom', 'eligibility_other_conditions',
            ]),
            'offices' => $id->offices->sortBy('id')->values()->map(fn ($office) => [
                ...$office->only(['id', 'name', 'address', 'municipality', 'province', 'latitude', 'longitude', 'status', 'notes']),
                'services' => $office->pivot->only(['new_application_status', 'renewal_status', 'replacement_status', 'service_notes']),
                'schedules' => $office->schedules->map(fn ($day) => [
                    ...$day->only(['id', 'day_of_week', 'status']),
                    'intervals' => $day->intervals->map(fn ($interval) => $interval->only(['opens_at', 'closes_at']))->values()->all(),
                ])->values()->all(),
            ])->all(),
            'application_guide' => $id->requirementSets->map(fn ($set) => [
                'id' => $set->id, 'display_label' => $set->display_label,
                ...$set->only(['application_type', 'applicant_type', 'min_age', 'max_age']),
                'steps' => $set->applicationSteps->map(fn ($step) => [
                    ...$step->only(['id', 'title', 'short_description', 'type', 'sort_order']),
                    'blocks' => $step->blocks->map(fn ($block) => $block->only(['id', 'type', 'section_title', 'content', 'sort_order']))->values()->all(),
                ])->values()->all(),
            ])->values()->all(),
        ];
    }
}
