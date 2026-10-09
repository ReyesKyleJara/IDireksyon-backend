<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GovernmentIdChecklistRequest;
use App\Models\ContentChangeLog;
use App\Models\GovernmentId;
use App\Models\GovernmentIdRequirementSet;
use App\Services\GovernmentIdChecklists;
use Illuminate\Support\Facades\DB;

class GovernmentIdChecklistController extends Controller
{
    public function store(GovernmentIdChecklistRequest $request, GovernmentId $governmentId)
    {
        $set = app(GovernmentIdChecklists::class)->save($governmentId, $request->validated());
        return response()->json(['checklist' => self::present($set), 'history' => $this->history($governmentId)], 201);
    }

    public function update(GovernmentIdChecklistRequest $request, GovernmentId $governmentId, string $checklist)
    {
        $set = app(GovernmentIdChecklists::class)->save($governmentId, $request->validated(), $checklist);
        return response()->json(['checklist' => self::present($set), 'history' => $this->history($governmentId)]);
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

    public static function present(GovernmentIdRequirementSet $set): array
    {
        return GovernmentIdChecklists::present($set);
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
