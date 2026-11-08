<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Office;
use App\Services\CatalogEncoding;
use Illuminate\Http\JsonResponse;

class OfficeController extends Controller
{
    /**
     * Published offices for the Office Finder. As in the CMS, an office reaches
     * residents only when an ID or document that shows its offices links to it.
     */
    public function index(): JsonResponse
    {
        $offices = Office::query()
            ->where('status', 'active')
            ->with([
                'agency',
                'schedules.intervals',
                'governmentIds:government_ids.id,name,research_metadata',
                'documents:documents.id,name,research_metadata',
            ])
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->map(function (Office $office) {
                $records = $office->governmentIds->concat($office->documents)
                    ->reject(fn ($record) => in_array('offices', $record->research_metadata['hidden_sections'] ?? [], true))
                    ->pluck('name')
                    ->unique()
                    ->sort()
                    ->values()
                    ->all();

                return [
                    ...CatalogEncoding::office($office),
                    'agency' => $office->agency?->only(['id', 'name', 'acronym']),
                    'records' => $records,
                ];
            })
            ->filter(fn (array $office) => $office['records'] !== [])
            ->values();

        return response()->json(['data' => $offices]);
    }
}
