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
            ...$document->only(['id', 'name', 'acronym', 'description', 'level', 'category', 'guidance_mode', 'application_availability', 'acquisition_type', 'target_group', 'include_in_smart_sequencing']),
            'record_type' => 'document', 'issued_by' => $document->agency?->name ?? $document->issued_by,
            'image_url' => $document->image_url,
            'research_metadata' => CatalogEncoding::publicResearch($document),
        ])->values()]);
    }

    public function show(Document $document)
    {
        // Resident-friendly wording is applied last, to the response only.
        return response()->json(['data' => \App\Services\ResidentWording::apply([
            ...$document->only(['id', 'name', 'acronym', 'description', 'purpose', 'level', 'category', 'eligibility', 'validity', 'validity_type',
                'processing_time', 'processing_time_note', 'guidance_mode', 'application_availability', 'acquisition_type', 'target_group', 'include_in_smart_sequencing', 'requirements', 'fee']),
            ...CatalogEncoding::detail($document),
            // Added after detail(): the fingerprint reads detail(), so it must not depend on this.
            'agency_verification' => app(\App\Services\CatalogAgencyValidation::class)->publicSummary($document),
            'verified_information' => app(\App\Services\CatalogAgencyValidation::class)->verifiedInformation($document),
            'research_metadata' => \App\Services\CatalogEncoding::publicResearch($document),
            // Last Updated / Last Reviewed by the IDireksyon team (not an agency confirmation).
            'review_dates' => \App\Services\CatalogReviewDates::publicSummary($document),
            'image_url' => $document->image_url,
            'issued_by' => $document->agency?->name ?? $document->issued_by,
            'requirement_sets' => ResidentRequirements::generalForId($document),
            'fee' => $document->fees->isNotEmpty() && CatalogEncoding::generalFees($document)->isEmpty() ? null : $document->fee,
            'fees' => CatalogEncoding::generalFees($document)->map(fn ($fee) => CatalogEncoding::fee($fee))->values(),
        ], $document)]);
    }
}
