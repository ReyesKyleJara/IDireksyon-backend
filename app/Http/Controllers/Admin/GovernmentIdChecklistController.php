<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GovernmentIdChecklistRequest;
use App\Models\ContentChangeLog;
use App\Models\GovernmentId;
use App\Models\GovernmentIdRequirementItem;
use App\Models\GovernmentIdRequirementSet;
use App\Models\GovernmentIdRequirementWay;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class GovernmentIdChecklistController extends Controller
{
    public function store(GovernmentIdChecklistRequest $request, GovernmentId $governmentId)
    {
        $set = $this->save($request, $governmentId);
        return response()->json(['checklist' => self::present($set), 'history' => $this->history($governmentId)], 201);
    }

    public function update(GovernmentIdChecklistRequest $request, GovernmentId $governmentId, string $checklist)
    {
        $set = $this->save($request, $governmentId, $checklist);
        return response()->json(['checklist' => self::present($set), 'history' => $this->history($governmentId)]);
    }

    private function save(GovernmentIdChecklistRequest $request, GovernmentId $governmentId, ?string $checklist = null): GovernmentIdRequirementSet
    {
        return DB::transaction(function () use ($request, $governmentId, $checklist) {
            $set = $checklist === null
                ? $governmentId->requirementSets()->make()
                : $governmentId->requirementSets()->lockForUpdate()->findOrFail($checklist);
            $before = $set->exists ? self::present($set) : null;
            $data = $request->validated();
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

    public function destroy(GovernmentId $governmentId, string $checklist)
    {
        DB::transaction(function () use ($governmentId, $checklist) {
            $set = $governmentId->requirementSets()->lockForUpdate()->findOrFail($checklist);
            if ($set->applicationSteps()->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'application_guide' => 'This scenario has an Application Guide. Remove its steps before deleting the scenario.',
                ]);
            }
            $set->delete();
            ContentChangeLog::record($governmentId, 'updated', ['requirement_sets']);
        });
        return response()->json(['history' => $this->history($governmentId)]);
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

    private function history(GovernmentId $governmentId): ?array
    {
        $edit = ContentChangeLog::with('user')->where('entity_type', 'government_id')
            ->where('entity_id', $governmentId->id)->whereIn('action', ['created', 'updated'])
            ->latest('created_at')->orderByDesc('id')->first();
        return $edit ? [
            'editor' => $edit->user?->name ?? 'Editor not recorded',
            'editor_label' => $edit->action === 'created' ? 'Created by' : 'Last edited by',
            'date_label' => $edit->action === 'created' ? 'Created on' : 'Last edited',
            'date' => $edit->created_at->copy()->timezone('Asia/Manila')->format('F j, Y · g:i A').' PHT',
        ] : null;
    }
}
