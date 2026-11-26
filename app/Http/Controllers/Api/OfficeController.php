<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Office;
use App\Services\CatalogEncoding;
use App\Services\OfficeRouteEstimator;
use Illuminate\Http\JsonResponse;

class OfficeController extends Controller
{
    /**
     * Offices for the Office Finder. As on ID and document pages, an office
     * reaches residents when a record that shows its offices links to it.
     */
    public function index(): JsonResponse
    {
        $offices = Office::query()
            ->with([
                'agency',
                'barangay',
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

        return response()->json([
            'data' => $offices,
            // Tells the app whether to offer travel times, so the paid Routes
            // API has one on/off switch: GOOGLE_MAPS_ROUTES_ENABLED.
            'meta' => ['travel_times' => app(OfficeRouteEstimator::class)->isEnabled()],
        ]);
    }
}
