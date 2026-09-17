<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CatalogFee;
use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\RequirementGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CatalogStructureController extends Controller
{
    private function record(string $catalog, string $id): GovernmentId|Document
    {
        $model = match ($catalog) {
            'government-ids' => GovernmentId::class,
            'documents' => Document::class,
            default => abort(404),
        };

        return $model::findOrFail($id);
    }

    public function saveGroup(Request $request, string $catalog, string $id, ?string $group = null)
    {
        $record = $this->record($catalog, $id);
        $item = $group ? $record->requirementGroups()->findOrFail($group) : null;
        $data = $request->validateWithBag('structure', [
            'name' => ['required', 'string', 'max:255'],
            'match_rule' => ['required', Rule::in(array_keys(RequirementGroup::MATCH_RULES))],
            'minimum_count' => ['required_if:match_rule,at_least', 'nullable', 'integer', 'min:1', 'max:100'],
            'condition_notes' => ['nullable', 'string', 'max:5000'],
            'position' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ]);
        $data['minimum_count'] = $data['match_rule'] === 'at_least' ? $data['minimum_count'] : 1;
        $data['position'] = $data['position'] ?? 0;
        DB::transaction(function () use ($record, $item, $data) {
            $record = $record->newQuery()->lockForUpdate()->findOrFail($record->id);
            $item ? $item->update($data) : $record->requirementGroups()->create($data);
            $record->update(['requirements_reviewed' => false, 'last_updated' => now()]);
        });

        return redirect()->to(route('admin.'.$catalog.'.edit', $record).'#requirements')
            ->with('success', 'Requirement group saved. Review the complete rules before sequencing use.');
    }

    public function deleteGroup(string $catalog, string $id, string $group)
    {
        $record = $this->record($catalog, $id);

        return DB::transaction(function () use ($record, $catalog, $group) {
            $record = $record->newQuery()->lockForUpdate()->findOrFail($record->id);
            $item = $record->requirementGroups()->findOrFail($group);
            if ($item->requirements()->where(fn ($q) => $q->whereHas('governmentIds')->orWhereHas('documents'))->exists()) {
                return back()->with('error', 'Move or remove this group’s requirements before deleting the group.');
            }
            $item->delete();
            $record->update(['requirements_reviewed' => false, 'last_updated' => now()]);

            return redirect()->to(route('admin.'.$catalog.'.edit', $record).'#requirements')->with('success', 'Empty requirement group removed.');
        });
    }

    public function saveFee(Request $request, string $catalog, string $id, ?string $fee = null)
    {
        $record = $this->record($catalog, $id);
        $item = $fee ? $record->fees()->findOrFail($fee) : null;
        $data = $request->validateWithBag('fees', [
            'name' => ['required', 'string', 'max:255'],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
            'kind' => ['required', Rule::in(array_keys(CatalogFee::KINDS))],
            'choice_group' => ['required_if:kind,alternative', 'nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $data['choice_group'] = $data['kind'] === 'alternative' ? trim($data['choice_group']) : null;
        DB::transaction(function () use ($record, $item, $data) {
            $item ? $item->update($data) : $record->fees()->create($data);
            $record->update(['last_updated' => now()]);
        });

        return redirect()->to(route('admin.'.$catalog.'.edit', $record).'#fees')->with('success', 'Fee saved.');
    }

    public function deleteFee(string $catalog, string $id, string $fee)
    {
        $record = $this->record($catalog, $id);
        DB::transaction(function () use ($record, $fee) {
            $record->fees()->findOrFail($fee)->delete();
            $record->update(['last_updated' => now()]);
        });

        return redirect()->to(route('admin.'.$catalog.'.edit', $record).'#fees')->with('success', 'Fee removed.');
    }
}
