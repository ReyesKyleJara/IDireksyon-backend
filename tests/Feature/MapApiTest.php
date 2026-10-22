<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MapApiTest extends TestCase
{
    public function test_walk_and_transit_do_not_send_driving_preferences(): void
    {
        config(['services.google_maps.server_key' => 'secret']);
        $point = ['location' => ['latLng' => ['latitude' => 14.8, 'longitude' => 120.9]]];
        foreach (['WALK', 'TRANSIT'] as $mode) {
            Http::fake(['routes.googleapis.com/*' => Http::response(['routes' => []])]);
            $this->postJson('/api/maps/route', [
                'origin' => $point, 'destination' => $point, 'travelMode' => $mode,
            ])->assertOk();
            Http::assertSent(fn ($request) => $request['travelMode'] === $mode
                && ! isset($request['routingPreference']));
        }
        $this->postJson('/api/maps/route', [
            'origin' => $point, 'destination' => $point, 'travelMode' => 'FLY',
        ])->assertUnprocessable();
    }

    public function test_route_forwards_coordinates_with_server_selected_options(): void
    {
        config(['services.google_maps.server_key' => 'server-secret']);
        Http::preventStrayRequests();
        Http::fake(['routes.googleapis.com/*' => Http::response(['routes' => []])]);
        $point = ['location' => ['latLng' => ['latitude' => 14.8, 'longitude' => 120.9]]];
        $this->postJson('/api/maps/route', [
            'origin' => $point, 'destination' => $point,
        ])->assertOk()->assertExactJson(['routes' => []]);
        Http::assertSent(fn ($request) => $request['origin'] === $point
            && $request['travelMode'] === 'DRIVE'
            && $request->hasHeader('X-Goog-Api-Key', 'server-secret'));
    }

    public function test_search_uses_server_credentials_and_fixed_fields(): void
    {
        config(['services.google_maps.server_key' => 'server-secret']);
        Http::preventStrayRequests();
        Http::fake(['places.googleapis.com/*' => Http::response(['places' => []])]);
        $this->postJson('/api/maps/search', ['textQuery' => 'Santa Maria', 'pageSize' => 100])
            ->assertOk()->assertExactJson(['places' => []]);
        Http::assertSent(fn ($request) => $request->hasHeader('X-Goog-Api-Key', 'server-secret')
            && $request['pageSize'] === 5 && $request['textQuery'] === 'Santa Maria');
    }

    public function test_invalid_coordinates_never_call_google(): void
    {
        Http::fake();
        $this->postJson('/api/maps/route', ['origin' => ['location' => ['latLng' => ['latitude' => 999]]]])
            ->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_missing_key_and_google_failures_are_safe(): void
    {
        config(['services.google_maps.server_key' => '']);
        $this->postJson('/api/maps/search', ['textQuery' => 'office'])->assertStatus(503);
        config(['services.google_maps.server_key' => 'secret']);
        Http::fake(['*' => Http::response(['error' => 'private diagnostics'], 403)]);
        $this->postJson('/api/maps/search', ['textQuery' => 'office'])
            ->assertStatus(502)->assertDontSee('private diagnostics');
    }
}
