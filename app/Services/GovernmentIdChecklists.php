<?php

namespace App\Services;

use App\Http\Requests\Admin\GovernmentIdChecklistRequest;
use App\Models\ContentChangeLog;
use App\Models\GovernmentId;
use App\Models\GovernmentIdRequirementItem;
use App\Models\GovernmentIdRequirementSet;
use App\Models\GovernmentIdRequirementWay;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class GovernmentIdChecklists
{
    /** Validate form drafts before writing any part of a new ID. */
    public function readForCreation(Request $request): array
    {
        if (! $request->has('requirements_payload')) return [];
        $raw = $request->input('requirements_payload');
        if (! is_string($raw) || strlen($raw) > 2000000 || ! str_starts_with(ltrim($raw), '[')) {
            throw ValidationException::withMessages(['requirements_payload' => 'The requirements could not be read. Please review the checklists.']);
        }
        try {
            $rows = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw ValidationException::withMessages(['requirements_payload' => 'The requirements could not be read. Please review the checklists.']);
        }
        Validator::make(['requirements_payload' => $rows], [
            'requirements_payload' => ['present', 'array', 'list', 'max:50'],
            'requirements_payload.*' => ['array:client_key,application_type,application_type_custom,applicant_type,applicant_type_custom,min_age,max_age,groups'],
            'requirements_payload.*.client_key' => ['required', 'string', 'max:80', 'regex:/^[a-zA-Z0-9_-]+$/', 'distinct'],
        ])->validate();
        foreach ($rows as $index => &$row) {
            $key = $row['client_key'];
            try {
                $row = GovernmentIdChecklistRequest::validateForCreation(Arr::except($row, ['client_key']));
            } catch (ValidationException $exception) {
                throw ValidationException::withMessages([
                    'requirements_payload' => array_map(
                        fn ($message) => 'Checklist '.($index + 1).': '.$message,
                        Arr::flatten($exception->errors()),
                    ),
                ]);
            }
            $row['client_key'] = $key;
        }
        unset($row);
        return $rows;
    }

    /** Called inside the ID transaction. Temporary keys never become database columns. */
    public function createAll(GovernmentId $governmentId, array $rows): array
    {
        $sets = [];
        foreach ($rows as $row) {
            $sets[$row['client_key']] = $this->save($governmentId, $row);
        }
        return $sets;
    }

    public function save(GovernmentId $governmentId, array $data, ?string $checklist = null): GovernmentIdRequirementSet
    {
        return DB::transaction(function () use ($data, $governmentId, $checklist) {
            $set = $checklist === null
                ? $governmentId->requirementSets()->make()
                : $governmentId->requirementSets()->lockForUpdate()->findOrFail($checklist);
            $before = $set->exists ? self::present($set) : null;
            $set->fill(array_replace([
                'application_type_custom' => null, 'applicant_type_custom' => null,
                'min_age' => null, 'max_age' => null,
            ], Arr::only($data, [
                'application_type', 'application_type_custom', 'applicant_type',
                'applicant_type_custom', 'min_age', 'max_age',
            ])));
            $set->save();

            $groupIds = [];
            foreach (array_values($data['groups']) as $position => $row) {
                // Scope every existing ID to its parent. Never adopt another checklist's rows.
                $group = filled($row['id'] ?? null)
                    ? $set->groups()->findOrFail($row['id'])
                    : $set->groups()->make();
                $group->fill([
                    'title' => $row['title'] ?? null, 'rule' => $row['rule'],
                    'condition_type' => $row['condition_type'],
                    'condition_custom' => $row['condition_custom'] ?? null,
                    'sort_order' => $position,
                ]);
                $group->save();
                if (array_key_exists('ways', $row)) {
                    $wayIds = [];
                    $itemIds = [];
                    foreach (array_values($row['ways']) as $wayPosition => $wayRow) {
                        $way = filled($wayRow['id'] ?? null)
                            ? $group->ways()->findOrFail($wayRow['id'])
                            : $group->ways()->make();
                        $way->fill(Arr::only($wayRow, GovernmentIdRequirementWay::EDITABLE_FIELDS));
                        $way->sort_order = $wayPosition;
                        $way->save();
                        $wayIds[] = $way->id;
                        $itemIds = array_merge($itemIds, $this->saveItems($group, $wayRow['items'], $way));
                    }
                    $group->items()->whereNotIn('id', $itemIds)->delete();
                    $group->ways()->whereNotIn('id', $wayIds)->delete();
                } else {
                    if ($group->ways()->exists()) {
                        throw ValidationException::withMessages(['groups' => 'This requirement uses the updated editor. Refresh and reopen its checklist before saving.']);
                    }
                    $itemIds = $this->saveItems($group, $row['items']);
                    $group->items()->whereNotIn('id', $itemIds)->delete();
                }
                $groupIds[] = $group->id;
            }
            $set->groups()->whereNotIn('id', $groupIds)->delete();
            $set->refresh();
            if ($before !== self::present($set)) {
                ContentChangeLog::record($governmentId, 'updated', ['requirement_sets', 'requirement_groups']);
            }
            return $set;
        });
    }

    private function saveItems($group, array $rows, ?GovernmentIdRequirementWay $way = null): array
    {
        $ids = [];
        foreach (array_values($rows) as $position => $row) {
            $item = filled($row['id'] ?? null)
                ? $group->items()->findOrFail($row['id'])
                : $group->items()->make();
            // Legacy items may enter their first way, but existing ways cannot steal rows.
            abort_if($item->requirement_way_id !== null && (int) $item->requirement_way_id !== $way?->id, 404);
            $item->fill(array_replace(
                array_fill_keys(GovernmentIdRequirementItem::EDITABLE_FIELDS, null),
                Arr::only($row, GovernmentIdRequirementItem::EDITABLE_FIELDS),
                ['sort_order' => $position],
            ));
            $item->requirement_way_id = $way?->id;
            $item->save();
            $ids[] = $item->id;
        }
        return $ids;
    }

    private static function presentItems($items): array
    {
        return $items->map(fn ($item) => [
            ...$item->only(['id', ...GovernmentIdRequirementItem::EDITABLE_FIELDS]),
            'name' => $item->display_name,
        ])->values()->all();
    }

    public static function present(GovernmentIdRequirementSet $set): array
    {
        $set->loadMissing(['groups.items.governmentId', 'groups.items.document', 'groups.ways']);
        return [
            ...$set->only(['id', 'application_type', 'application_type_custom', 'applicant_type', 'applicant_type_custom', 'min_age', 'max_age']),
            'label' => $set->display_label,
            'groups' => $set->groups->map(function ($group) {
                $result = [
                    ...$group->only(['id', 'title', 'rule', 'condition_type', 'condition_custom']),
                    'condition_label' => $group->condition_label,
                ];
                if ($group->ways->isEmpty()) {
                    $result['items'] = self::presentItems($group->items);
                } else {
                    $result['ways'] = $group->ways->map(fn ($way) => [
                        ...$way->only(['id', ...GovernmentIdRequirementWay::EDITABLE_FIELDS]),
                        'items' => self::presentItems($group->items->where('requirement_way_id', $way->id)),
                    ])->values()->all();
                }
                return $result;
            })->values()->all(),
        ];
    }

}
