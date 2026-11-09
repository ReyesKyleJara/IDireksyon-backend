<?php

namespace App\Services;

use App\Models\Office;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Road distance and driving time from a resident to an office.
 *
 * Stays on the Routes API Essentials tier: no traffic-aware routing, and the
 * field mask asks only for distance and duration. The resident's location is
 * used for this one call and is never stored, cached, or logged.
 */
class OfficeRouteEstimator
{
    private const ENDPOINT = 'https://routes.googleapis.com/directions/v2:computeRoutes';

    private const FIELD_MASK = 'routes.distanceMeters,routes.duration';

    public function isEnabled(): bool
    {
        return config('services.google_maps.routes_enabled') === true
            && filled(config('services.google_maps.server_key'));
    }

    /**
     * @return array{status: string, distance_meters?: int, duration_seconds?: int}
     */
    public function estimate(Office $office, float $latitude, float $longitude): array
    {
        if (! $this->reserveDailyCall()) {
            Log::warning('The daily Routes API limit was reached.');

            return ['status' => 'limit_reached'];
        }

        try {
            $response = Http::connectTimeout(5)->timeout(10)->withHeaders([
                'X-Goog-Api-Key' => config('services.google_maps.server_key'),
                'X-Goog-FieldMask' => self::FIELD_MASK,
            ])->post(self::ENDPOINT, [
                'origin' => $this->waypoint($latitude, $longitude),
                'destination' => $this->waypoint((float) $office->latitude, (float) $office->longitude),
                'travelMode' => 'DRIVE',
                'routingPreference' => 'TRAFFIC_UNAWARE',
            ]);
        } catch (ConnectionException) {
            Log::warning('Office route estimate could not reach Google.', ['office_id' => $office->id]);

            return ['status' => 'failed'];
        }

        if (! $response->successful()) {
            Log::warning('Office route estimate was rejected.', ['office_id' => $office->id, 'http_status' => $response->status()]);

            return ['status' => 'failed'];
        }

        return $this->read($response->json('routes'), $office);
    }

    /**
     * Counts each attempted call before it is made, so concurrent requests
     * cannot pass the limit together. If the count cannot be trusted, no call.
     */
    private function reserveDailyCall(): bool
    {
        $key = 'google-routes-calls:'.now('Asia/Manila')->toDateString();

        try {
            Cache::add($key, 0, now()->addDays(2));
            $count = Cache::increment($key);
        } catch (Throwable $exception) {
            Log::error('The Routes API call counter is unavailable.', ['exception' => $exception::class]);

            return false;
        }

        return is_int($count) && $count <= (int) config('services.google_maps.routes_daily_limit');
    }

    private function waypoint(float $latitude, float $longitude): array
    {
        return ['location' => ['latLng' => ['latitude' => $latitude, 'longitude' => $longitude]]];
    }

    private function read(mixed $routes, Office $office): array
    {
        if (! is_array($routes) || ! isset($routes[0]) || ! is_array($routes[0])) {
            return ['status' => 'no_route'];
        }

        // Google leaves out values of zero, such as when already at the office.
        $meters = $routes[0]['distanceMeters'] ?? 0;
        $duration = $routes[0]['duration'] ?? '0s';
        if (! is_int($meters) || ! is_string($duration) || ! preg_match('/^(\d+)(\.\d+)?s$/', $duration, $parts)) {
            Log::warning('Office route estimate had an unexpected response.', ['office_id' => $office->id]);

            return ['status' => 'failed'];
        }

        return ['status' => 'found', 'distance_meters' => $meters, 'duration_seconds' => (int) $parts[1]];
    }
}
