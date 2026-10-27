<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\GovernmentId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ResidentInventoryController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless(
            $user->is_active && $user->role === 'resident',
            403
        );

        $ownedGovernmentIdIds = $user->ownedGovernmentIds()
            ->pluck('government_ids.id')
            ->all();

        $ownedDocumentIds = $user->ownedDocuments()
            ->pluck('documents.id')
            ->all();

        return response()->json([
            'data' => [
                'government_ids' => GovernmentId::query()
                    ->with(['prerequisites.governmentId', 'prerequisites.document'])
                    ->orderBy('name')
                    ->get(['id', 'name', 'application_availability', 'acquisition_type', 'target_group', 'guidance_mode', 'include_in_smart_sequencing'])
                    ->map(fn (GovernmentId $governmentId) => [
                        'id' => $governmentId->id,
                        'name' => $governmentId->name,
                        'application_availability' => $governmentId->application_availability,
                        'acquisition_type' => $governmentId->acquisition_type,
                        'target_group' => $governmentId->target_group,
                        'guidance_mode' => $governmentId->guidance_mode,
                        'include_in_smart_sequencing' => $governmentId->include_in_smart_sequencing,
                        'prerequisites' => \App\Services\CatalogPrerequisites::present($governmentId),
                        'owned' => in_array(
                            $governmentId->id,
                            $ownedGovernmentIdIds,
                            true
                        ),
                    ])
                    ->values(),

                'documents' => Document::query()
                    ->with(['prerequisites.governmentId', 'prerequisites.document'])
                    ->orderBy('name')
                    ->get(['id', 'name', 'application_availability', 'acquisition_type', 'target_group', 'guidance_mode', 'include_in_smart_sequencing'])
                    ->map(fn (Document $document) => [
                        'id' => $document->id,
                        'name' => $document->name,
                        'application_availability' => $document->application_availability,
                        'acquisition_type' => $document->acquisition_type,
                        'target_group' => $document->target_group,
                        'guidance_mode' => $document->guidance_mode,
                        'include_in_smart_sequencing' => $document->include_in_smart_sequencing,
                        'prerequisites' => \App\Services\CatalogPrerequisites::present($document),
                        'owned' => in_array(
                            $document->id,
                            $ownedDocumentIds,
                            true
                        ),
                    ])
                    ->values(),
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless(
            $user->is_active && $user->role === 'resident',
            403
        );

        $data = $request->validate([
            'government_id_ids' => [
                'present',
                'array',
            ],
            'government_id_ids.*' => [
                'integer',
                'distinct',
                'exists:government_ids,id',
            ],

            'document_ids' => [
                'present',
                'array',
            ],
            'document_ids.*' => [
                'integer',
                'distinct',
                'exists:documents,id',
            ],
        ]);

        DB::transaction(function () use ($user, $data) {
            $user->ownedGovernmentIds()
                ->sync($data['government_id_ids']);

            $user->ownedDocuments()
                ->sync($data['document_ids']);
        });

        return $this->show($request);
    }
}
