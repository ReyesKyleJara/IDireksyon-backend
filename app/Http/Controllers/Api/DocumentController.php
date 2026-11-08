<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Services\CatalogEncoding;
use App\Support\ResidentRequirements;

class DocumentController extends Controller
{
    public function index()
    {
        return response()->json(['data' => Document::with('agency')->orderBy('name')->get()->map(fn ($document) => [
            ...$document->only(['id', 'name', 'description', 'level', 'category', 'guidance_mode', 'application_availability', 'acquisition_type', 'target_group', 'include_in_smart_sequencing']),
            'record_type' => 'document', 'issued_by' => $document->agency?->name ?? $document->issued_by,
            'research_metadata' => $document->research_metadata ?? [],
        ])->values()]);
    }

    public function show(Document $document)
    {
        return response()->json(['data' => [
            ...$document->only(['id', 'name', 'description', 'purpose', 'level', 'category', 'eligibility', 'validity', 'validity_type',
                'processing_time', 'processing_time_note', 'guidance_mode', 'application_availability', 'acquisition_type', 'target_group', 'include_in_smart_sequencing', 'requirements', 'fee']),
            ...CatalogEncoding::detail($document),
            'issued_by' => $document->agency?->name ?? $document->issued_by,
            'requirement_sets' => ResidentRequirements::generalForId($document),
            'fee' => $document->fees->isNotEmpty() && CatalogEncoding::generalFees($document)->isEmpty() ? null : $document->fee,
            'fees' => CatalogEncoding::generalFees($document)->map(fn ($fee) => CatalogEncoding::fee($fee))->values(),
        ]]);
    }
}
