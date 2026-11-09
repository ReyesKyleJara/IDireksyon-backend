<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentRequirementItem;
use App\Models\GovernmentId;
use App\Models\GovernmentIdRequirementItem;
use App\Services\CatalogResidentReferences;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResidentRequirementPreparationController extends Controller
{
    private function scope(Request $request, string $recordType, int $recordId): array
    {
        abort_unless($request->user()->is_active && $request->user()->role === 'resident', 403);
        [$owner, $items, $foreignKey, $table] = match ($recordType) {
            'government_id' => [GovernmentId::class, GovernmentIdRequirementItem::class, 'government_id_id', 'resident_id_preparations'],
            'document' => [Document::class, DocumentRequirementItem::class, 'document_id', 'resident_document_preparations'],
            default => abort(404),
        };
        $owner::findOrFail($recordId);

        return [$items::whereHas('group.requirementSet', fn ($query) => $query->where($foreignKey, $recordId)), $table];
    }

    public function show(Request $request, string $recordType, int $recordId): JsonResponse
    {
        [$items, $table] = $this->scope($request, $recordType, $recordId);

        $saved = DB::table($table)->where('user_id', $request->user()->id)
            ->pluck('prepared', 'requirement_item_id');
        // Only explicit choices count; owning an item never marks it prepared.
        $selections = $items->orderBy('id')->get()
            ->filter(fn ($item) => $saved->has($item->id))
            ->map(fn ($item) => ['item_id' => $item->id, 'prepared' => (bool) $saved[$item->id]])
            ->values()->all();

        return response()->json(['data' => [
            'record_type' => $recordType,
            'record_id' => $recordId,
            'items' => $selections,
        ]]);
    }

    public function update(Request $request, string $recordType, int $recordId): JsonResponse
    {
        return CatalogResidentReferences::transaction(function () use ($request, $recordType, $recordId) {
            [$items, $table] = $this->scope($request, $recordType, $recordId);
            $data = $request->validate([
                'item_id' => ['required', 'integer', 'min:1'],
                'prepared' => ['required', 'boolean'],
                'user_id' => ['prohibited'],
            ]);
            if (! $items->whereKey($data['item_id'])->exists()) {
                throw ValidationException::withMessages(['item_id' => 'This requirement does not belong to this application.']);
            }

            // Update a single item; never sync or change Inventory ownership.
            DB::table($table)->upsert([[
                'user_id' => $request->user()->id,
                'requirement_item_id' => $data['item_id'],
                'prepared' => $data['prepared'],
                'created_at' => now(),
                'updated_at' => now(),
            ]], ['user_id', 'requirement_item_id'], ['prepared', 'updated_at']);

            return $this->show($request, $recordType, $recordId);
        });
    }
}
