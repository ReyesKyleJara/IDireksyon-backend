<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Office;
use App\Services\OfficeRouteEstimator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OfficeRouteController extends Controller
{
    private const UNAVAILABLE = 'Road distance is unavailable right now.';

    /** The destination always comes from the CMS, never from the request. */
    public function estimate(Request $request, Office $office, OfficeRouteEstimator $estimator): JsonResponse
    {
        // Roughly the Philippines, so an out-of-range location costs no Google call.
        $origin = $request->validate([
            'latitude' => ['required', 'numeric', 'between:4,22'],
            'longitude' => ['required', 'numeric', 'between:116,127'],
        ]);

        if (! $office->hasUsableLocation()) {
            return $this->problem('office_no_location', 'Road distance is not available for this office yet.', 422);
        }

        if (! $estimator->isEnabled()) {
            return $this->problem('routes_unavailable', self::UNAVAILABLE, 503);
        }

        $route = $estimator->estimate($office, (float) $origin['latitude'], (float) $origin['longitude']);

        return match ($route['status']) {
            'found' => response()->json(['data' => [
                'distance_meters' => $route['distance_meters'],
                'duration_seconds' => $route['duration_seconds'],
            ]]),
            'no_route' => $this->problem('no_route', 'No driving route was found to this office.', 422),
            default => $this->problem('routes_unavailable', self::UNAVAILABLE, 503),
        };
    }

    private function problem(string $code, string $message, int $status): JsonResponse
    {
        return response()->json(['code' => $code, 'message' => $message], $status);
    }
}
