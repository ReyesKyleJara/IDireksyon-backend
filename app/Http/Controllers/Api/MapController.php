<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class MapController extends Controller
{
    public function search(Request $request)
    {
        $data = $request->validate(['textQuery' => 'required|string|max:200']);

        return $this->forward('https://places.googleapis.com/v1/places:searchText',
            'places.displayName,places.formattedAddress,places.location',
            $data + ['regionCode' => 'PH', 'languageCode' => 'en', 'pageSize' => 5]);
    }

    public function route(Request $request)
    {
        $rules = ['travelMode' => 'sometimes|required|in:DRIVE,WALK,TRANSIT'];
        foreach (['origin', 'destination'] as $point) {
            $rules["$point.location.latLng.latitude"] = 'required|numeric|between:-90,90';
            $rules["$point.location.latLng.longitude"] = 'required|numeric|between:-180,180';
        }
        $data = $request->validate($rules);

        $mode = $data['travelMode'] ?? 'DRIVE';
        $data['travelMode'] = $mode;
        if ($mode === 'DRIVE') {
            $data['routingPreference'] = 'TRAFFIC_UNAWARE';
        }

        return $this->forward('https://routes.googleapis.com/directions/v2:computeRoutes',
            'routes.distanceMeters,routes.duration,routes.polyline.encodedPolyline,routes.legs.steps.navigationInstruction,routes.legs.steps.transitDetails',
            $data + ['languageCode' => 'en-US', 'units' => 'METRIC']);
    }

    private function forward(string $url, string $fields, array $payload)
    {
        $key = config('services.google_maps.server_key');
        if (! $key) {
            return response()->json(['message' => 'Google Maps server key is not configured.'], 503);
        }
        try {
            $response = Http::connectTimeout(5)->timeout(15)->withHeaders([
                'X-Goog-Api-Key' => $key,
                'X-Goog-FieldMask' => $fields,
            ])->post($url, $payload);
        } catch (ConnectionException $e) {
            return response()->json(['message' => 'Maps service is unavailable. Please try again.'], 502);
        }
        if (! $response->successful()) {
            return response()->json(['message' => 'Maps service is unavailable. Please try again.'], 502);
        }

        return response()->json($response->json());
    }
}
