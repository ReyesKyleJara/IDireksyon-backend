<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GovernmentIdRequirementGroupRequest;
use App\Models\ContentChangeLog;
use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\GovernmentIdRequirementGroup;
use App\Models\GovernmentIdRequirementItem;
use App\Models\GovernmentIdRequirementSet;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class GovernmentIdRequirementGroupController extends Controller
{
    public function index(GovernmentId $governmentId, string $requirementSet)
    {
        $set = $governmentId->requirementSets()->findOrFail($requirementSet);
        if ($set->groups()->whereHas('ways')->exists()) {
            return redirect()->route('admin.government-ids.edit', $governmentId);
        }
        return view('admin.government_ids.requirement_groups.index', [
            'governmentId' => $governmentId,
            'set' => $set,
            'groups' => $set->groups()->with(['items.governmentId', 'items.document'])->get(),
        ]);
    }

    public function create(GovernmentId $governmentId, string $requirementSet)
    {
        $set = $governmentId->requirementSets()->findOrFail($requirementSet);
        return $this->form($governmentId, $set, new GovernmentIdRequirementGroup());
    }

    public function edit(GovernmentId $governmentId, string $requirementSet, string $requirementGroup)
    {
        $set = $governmentId->requirementSets()->findOrFail($requirementSet);
        $group = $set->groups()->with('items')->findOrFail($requirementGroup);
        if ($group->ways()->exists()) {
            return redirect()->route('admin.government-ids.edit', $governmentId)->with('success', 'Open the checklist to edit this requirement.');
        }
        return $this->form($governmentId, $set, $group);
    }

    private function form(GovernmentId $governmentId, GovernmentIdRequirementSet $set, GovernmentIdRequirementGroup $group)
    {
        return view('admin.government_ids.requirement_groups.form', [
            'governmentId' => $governmentId,
            'set' => $set,
            'group' => $group,
            'governmentIds' => GovernmentId::orderBy('name')->get(['id', 'name']),
            'documents' => Document::orderBy('name')->get(['id', 'name']),
            'rules' => GovernmentIdRequirementGroup::RULES,
            'conditions' => GovernmentIdRequirementGroup::CONDITIONS,
            'types' => GovernmentIdRequirementItem::TYPES,
            'formats' => GovernmentIdRequirementItem::FORMATS,
        ]);
    }

    public function store(GovernmentIdRequirementGroupRequest $request, GovernmentId $governmentId, string $requirementSet)
    {
        $this->saveGroup($request, $governmentId, $requirementSet);
        return $this->backToGroups($governmentId, $requirementSet, 'Requirement group added.');
    }

    public function update(GovernmentIdRequirementGroupRequest $request, GovernmentId $governmentId, string $requirementSet, string $requirementGroup)
    {
        $this->saveGroup($request, $governmentId, $requirementSet, $requirementGroup);
        return $this->backToGroups($governmentId, $requirementSet, 'Requirement group updated.');
    }

    private function saveGroup(GovernmentIdRequirementGroupRequest $request, GovernmentId $governmentId, string $requirementSet, ?string $requirementGroup = null): void
    {
        DB::transaction(function () use ($request, $governmentId, $requirementSet, $requirementGroup) {
            $set = $governmentId->requirementSets()->lockForUpdate()->findOrFail($requirementSet);
            $group = $requirementGroup === null
                ? $set->groups()->make()
                : $set->groups()->findOrFail($requirementGroup);
            if ($group->exists && $group->ways()->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'items' => 'Edit this requirement from the checklist on the Government ID Edit page.',
                ]);
            }
            $before = $group->exists ? $this->snapshot($group) : null;
            $data = $request->validated();
            $group->fill([
                'title' => $data['title'] ?? null,
                'rule' => $data['rule'],
                'condition_type' => $data['condition_type'],
                'condition_custom' => $data['condition_type'] === 'custom' ? $data['condition_custom'] : null,
            ]);
            if (! $group->exists) {
                $group->sort_order = ($set->groups()->max('sort_order') ?? -1) + 1;
            }
            $group->save();

            $kept = [];
            foreach (array_values($data['items']) as $position => $row) {
                $item = filled($row['id'] ?? null)
                    ? $group->items()->findOrFail($row['id'])
                    : $group->items()->make();
                $item->fill(array_replace(
                    array_fill_keys(GovernmentIdRequirementItem::EDITABLE_FIELDS, null),
                    Arr::only($row, GovernmentIdRequirementItem::EDITABLE_FIELDS),
                    ['sort_order' => $position],
                ));
                $item->save();
                $kept[] = $item->id;
            }
            $group->items()->whereNotIn('id', $kept)->delete();

            if ($before !== $this->snapshot($group->fresh())) {
                ContentChangeLog::record($governmentId, 'updated', ['requirement_groups']);
            }
        });
    }

    private function snapshot(GovernmentIdRequirementGroup $group): array
    {
        return [
            'group' => $group->only(['title', 'rule', 'condition_type', 'condition_custom', 'sort_order']),
            'items' => $group->items()->get()->map(fn ($item) => $item->only([
                'id', ...GovernmentIdRequirementItem::EDITABLE_FIELDS, 'sort_order',
            ]))->all(),
        ];
    }

    public function destroy(GovernmentId $governmentId, string $requirementSet, string $requirementGroup)
    {
        DB::transaction(function () use ($governmentId, $requirementSet, $requirementGroup) {
            $set = $governmentId->requirementSets()->lockForUpdate()->findOrFail($requirementSet);
            $set->groups()->findOrFail($requirementGroup)->delete();
            ContentChangeLog::record($governmentId, 'updated', ['requirement_groups']);
        });
        return $this->backToGroups($governmentId, $requirementSet, 'Requirement group deleted.');
    }

    private function backToGroups(GovernmentId $governmentId, string $requirementSet, string $message)
    {
        return redirect()->route('admin.government-ids.requirement-sets.groups.index', [$governmentId, $requirementSet])
            ->with('success', $message);
    }
}
