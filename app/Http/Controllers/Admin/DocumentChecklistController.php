<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GovernmentIdChecklistRequest;
use App\Models\ContentChangeLog;
use App\Models\Document;
use App\Models\GovernmentIdRequirementSet;
use App\Services\GovernmentIdChecklists;
use Illuminate\Support\Facades\DB;

class DocumentChecklistController extends Controller
{
    public function store(GovernmentIdChecklistRequest $request, Document $document)
    {
        $set = app(GovernmentIdChecklists::class)->save($document, $request->validated());
        return response()->json(['checklist' => self::present($set), 'history' => $this->history($document)], 201);
    }

    public function update(GovernmentIdChecklistRequest $request, Document $document, string $checklist)
    {
        $set = app(GovernmentIdChecklists::class)->save($document, $request->validated(), $checklist);
        return response()->json(['checklist' => self::present($set), 'history' => $this->history($document)]);
    }

    public function destroy(Document $document, string $checklist)
    {
        DB::transaction(function () use ($document, $checklist) {
            $set = $document->requirementSets()->lockForUpdate()->findOrFail($checklist);
            if ($set->applicationSteps()->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'application_guide' => 'This scenario has an Application Guide. Remove its steps before deleting the scenario.',
                ]);
            }
            $set->delete();
            ContentChangeLog::record($document, 'updated', ['requirement_sets']);
        });
        return response()->json(['history' => $this->history($document)]);
    }

    public static function present(GovernmentIdRequirementSet $set): array
    {
        return GovernmentIdChecklists::present($set);
    }

    private function history(Document $document): ?array
    {
        $edit = ContentChangeLog::with('user')->where('entity_type', 'document')
            ->where('entity_id', $document->id)->whereIn('action', ['created', 'updated'])
            ->latest('created_at')->orderByDesc('id')->first();
        return $edit ? [
            'editor' => $edit->user?->name ?? 'Editor not recorded',
            'editor_label' => $edit->action === 'created' ? 'Created by' : 'Last edited by',
            'date_label' => $edit->action === 'created' ? 'Created on' : 'Last edited',
            'date' => $edit->created_at->copy()->timezone('Asia/Manila')->format('F j, Y · g:i A').' PHT',
        ] : null;
    }
}
