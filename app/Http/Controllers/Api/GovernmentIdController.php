<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GovernmentId;
use Illuminate\Http\JsonResponse;

class GovernmentIdController extends Controller
{
    public function index(): JsonResponse
    {
        $governmentIds = GovernmentId::query()
            ->with('agency')
            ->orderBy('name')
            ->get()
            ->map(function (GovernmentId $governmentId) {
                return [
                    'id' => $governmentId->id,
                    'name' => $governmentId->name,

                    'level' => $governmentId->level,
                    'category' => $governmentId->category,

                    'agency' => $governmentId->agency
                        ? [
                            'id' => $governmentId->agency->id,
                            'name' => $governmentId->agency->name,
                            'acronym' => $governmentId->agency->acronym,
                        ]
                        : null,

                    // Temporary compatibility for the current Flutter UI
                    'issued_by' => $governmentId->agency?->name,

                    'purpose' => $governmentId->purpose,
                    'description' => $governmentId->description,

                    'eligibility' => $governmentId->eligibility,
                    'requirements' => $governmentId->requirements,

                    'fee' => $governmentId->fee,
                    'processing_time' => $governmentId->processing_time,
                    'validity' => $governmentId->validity,

                    'office_location' => $governmentId->office_location,

                    'last_verified_at' =>
                        $governmentId->last_verified_at?->toISOString(),
                ];
            })
            ->values();

        return response()->json([
            'data' => $governmentIds,
        ]);
    }


    public function show(
        GovernmentId $governmentId
    ): JsonResponse {
        $governmentId->load('agency');

        return response()->json([
            'data' => [
                'id' => $governmentId->id,
                'name' => $governmentId->name,

                'level' => $governmentId->level,
                'category' => $governmentId->category,

                'agency' => $governmentId->agency
                    ? [
                        'id' => $governmentId->agency->id,
                        'name' => $governmentId->agency->name,
                        'acronym' => $governmentId->agency->acronym,
                        'official_website' =>
                            $governmentId->agency->official_website,
                    ]
                    : null,

                // Keep this for now so Flutter does not break
                'issued_by' => $governmentId->agency?->name,

                'purpose' => $governmentId->purpose,
                'description' => $governmentId->description,

                'eligibility' => $governmentId->eligibility,
                'requirements' => $governmentId->requirements,

                'prerequisite_notes' =>
                    $governmentId->prerequisite_notes,

                'fee' => $governmentId->fee,

                'application_process' =>
                    $governmentId->application_process,

                'processing_time' =>
                    $governmentId->processing_time,

                'renewal_process' =>
                    $governmentId->renewal_process,

                'replacement_process' =>
                    $governmentId->replacement_process,

                'validity' => $governmentId->validity,

                'office_location' =>
                    $governmentId->office_location,

                'office_hours' =>
                    $governmentId->office_hours,

                'official_link' =>
                    $governmentId->official_link,

                'official_sources' =>
                    $governmentId->official_sources,

                'last_verified_at' =>
                    $governmentId->last_verified_at?->toISOString(),
            ],
        ]);
    }
}