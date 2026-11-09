<?php

namespace App\Services;

use App\Models\Office;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Finds an office's map location from its CMS name and address.
 * Typed coordinates always win, so manual offices are never looked up.
 */
class OfficeLocator
{
    private const ENDPOINT = 'https://places.googleapis.com/v1/places:searchText';

    public function isConfigured(): bool
    {
        return filled(config('services.google_maps.server_key'));
    }

    public function searchText(Office $office): string
    {
        return collect([$office->name, $office->address, $office->municipality, $office->province, 'Philippines'])
            ->map(fn ($part) => trim((string) $part))
            ->filter()
            ->implode(', ');
    }

    /**
     * Saves the result on the office and returns it for display. Google's name
     * and address are returned only: its terms restrict storing them.
     *
     * @return array{status: string, name?: ?string, address?: ?string, latitude?: float, longitude?: float, place_id?: string, http_status?: int}
     */
    public function locate(Office $office): array
    {
        $result = $this->search($office);
        $found = $result['status'] === 'found';

        // A failed search clears old coordinates: they belong to a previous address.
        $office->forceFill([
            'latitude' => $found ? $result['latitude'] : null,
            'longitude' => $found ? $result['longitude'] : null,
            'google_place_id' => $found ? $result['place_id'] : null,
            'location_source' => $found ? 'auto' : null,
            'location_status' => $result['status'],
            'location_checked_at' => now(),
        ])->saveQuietly();

        return $result;
    }

    private function search(Office $office): array
    {
        try {
            $response = Http::connectTimeout(5)->timeout(10)->withHeaders([
                'X-Goog-Api-Key' => config('services.google_maps.server_key'),
                'X-Goog-FieldMask' => 'places.id,places.displayName,places.formattedAddress,places.location',
            ])->post(self::ENDPOINT, [
                'textQuery' => $this->searchText($office),
                'regionCode' => 'PH',
                'languageCode' => 'en',
                'pageSize' => 1,
            ]);
        } catch (ConnectionException) {
            Log::warning('Office location lookup could not reach Google.', ['office_id' => $office->id]);

            return ['status' => 'failed'];
        }

        if (! $response->successful()) {
            Log::warning('Office location lookup was rejected.', ['office_id' => $office->id, 'http_status' => $response->status()]);

            return ['status' => 'failed', 'http_status' => $response->status()];
        }

        return $this->match($response->json('places.0')) ?? ['status' => 'not_found'];
    }

    private function match(mixed $place): ?array
    {
        $id = data_get($place, 'id');
        $latitude = data_get($place, 'location.latitude');
        $longitude = data_get($place, 'location.longitude');
        if (! is_string($id) || $id === '' || strlen($id) > 255 || ! is_numeric($latitude) || ! is_numeric($longitude)) {
            return null;
        }

        return [
            'status' => 'found',
            'name' => data_get($place, 'displayName.text'),
            'address' => data_get($place, 'formattedAddress'),
            'latitude' => (float) $latitude,
            'longitude' => (float) $longitude,
            'place_id' => $id,
        ];
    }
}
