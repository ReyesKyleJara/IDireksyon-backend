<?php

namespace App\Support;

use App\Models\GovernmentId;
use App\Models\GovernmentIdRequirementSet;
use App\Models\GovernmentIdRequirementWay;

/** Resident display projection; does not evaluate eligibility or readiness. */
class ResidentRequirements
{
    public static function isRequestMethod($set): bool
    {
        return filled($set->request_channel['label'] ?? null)
            || filled($set->request_channel['channel'] ?? null);
    }

    /** Method checklists are reference material, never general acquisition requirements. */
    public static function generalForId(GovernmentId|\App\Models\Document $owner): array
    {
        $methodIds = $owner->requirementSets->filter(fn ($set) => self::isRequestMethod($set))->pluck('id')->all();

        return array_values(array_filter(self::forId($owner), fn ($set) => ! in_array($set['id'], $methodIds, true)));
    }

    /** Reuse display models for a JSON-only assistance checklist. No model is saved. */
    public static function fromDraft(array $groups, string $title): array
    {
        $rows = collect($groups)->flatMap(fn ($group) => isset($group['ways'])
            ? collect($group['ways'])->flatMap(fn ($way) => $way['items']) : ($group['items'] ?? []));
        $ids = GovernmentId::whereIn('id', $rows->pluck('government_id_id')->filter()->unique())->get(['id', 'name'])->keyBy('id');
        $documents = \App\Models\Document::whereIn('id', $rows->pluck('document_id')->filter()->unique())->get(['id', 'name'])->keyBy('id');
        $set = new GovernmentIdRequirementSet(['application_type' => 'new', 'applicant_type' => 'all']);
        $set->forceFill(['id' => 1]);
        $set->setRelation('groups', collect($groups)->map(function ($row, $index) use ($ids, $documents) {
            $group = new \App\Models\GovernmentIdRequirementGroup($row);
            $group->forceFill(['id' => $index + 1]);
            $items = collect();
            $makeItems = function ($rows) use (&$items, $ids, $documents) {
                $result = collect($rows)->map(function ($data) use ($ids, $documents) {
                    $item = new \App\Models\GovernmentIdRequirementItem($data);
                    $item->setRelation('governmentId', $ids->get($data['government_id_id'] ?? null));
                    $item->setRelation('document', $documents->get($data['document_id'] ?? null));
                    return $item;
                });
                $items = $items->concat($result);
                return $result;
            };
            $ways = collect($row['ways'] ?? [])->map(function ($wayRow) use ($makeItems) {
                $way = new GovernmentIdRequirementWay($wayRow);
                $way->setRelation('items', $makeItems($wayRow['items']));
                return $way;
            });
            if ($ways->isEmpty()) $makeItems($row['items'] ?? []);
            $group->setRelation('ways', $ways)->setRelation('items', $items);
            return $group;
        }));
        $owner = new GovernmentId();
        $owner->setRelation('requirementSets', collect([$set]));
        if ($groups === []) return [];
        $result = self::forId($owner);
        $result[0]['display_label'] = $title;
        return $result;
    }

    public static function forId(GovernmentId|\App\Models\Document $id): array
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
