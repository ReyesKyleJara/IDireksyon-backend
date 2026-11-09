<?php

use App\Models\Office;
use App\Models\User;
use App\Services\OfficeRouteEstimator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;

const ROUTE_ORIGIN = ['latitude' => 14.7574, 'longitude' => 120.9483];

function routeOffice(array $attributes = []): Office
{
    return tap(Office::create(['name' => 'Bocaue Post Office']), fn (Office $office) => $office->forceFill([
        'latitude' => 14.8, 'longitude' => 120.92, 'location_source' => 'manual', ...$attributes,
    ])->save());
}

function routePath(Office $office): string
{
    return "/api/auth/offices/{$office->id}/route";
}

function fakeGoogleRoute(array $body = ['routes' => [['distanceMeters' => 8400, 'duration' => '1200s']]], int $status = 200): void
{
    Http::fake(['routes.googleapis.com/*' => Http::response($body, $status)]);
}

beforeEach(function () {
    Http::preventStrayRequests();
    $this->enabledByDefault = config('services.google_maps.routes_enabled');
    config([
        'services.google_maps.server_key' => 'test-server-key',
        'services.google_maps.routes_enabled' => true,
        'services.google_maps.routes_daily_limit' => 300,
    ]);
});

it('stays off until it is deliberately enabled', function () {
    expect($this->enabledByDefault)->toBeFalse();
});

it('returns road distance and driving time without exposing the key', function () {
    fakeGoogleRoute();
    Sanctum::actingAs(User::factory()->create());

    $this->postJson(routePath(routeOffice()), ROUTE_ORIGIN)
        ->assertOk()
        ->assertExactJson(['data' => ['distance_meters' => 8400, 'duration_seconds' => 1200]])
        ->assertDontSee('test-server-key');
});

it('makes an Essentials request with only distance and duration', function () {
    fakeGoogleRoute();
    Sanctum::actingAs(User::factory()->create());

    $this->postJson(routePath(routeOffice()), ROUTE_ORIGIN)->assertOk();

    Http::assertSent(function (Request $request) {
        $body = $request->data();

        return $request->url() === 'https://routes.googleapis.com/directions/v2:computeRoutes'
            && $request->hasHeader('X-Goog-Api-Key', 'test-server-key')
            && $request->hasHeader('X-Goog-FieldMask', 'routes.distanceMeters,routes.duration')
            && array_keys($body) === ['origin', 'destination', 'travelMode', 'routingPreference']
            && array_keys($body['origin']['location']) === ['latLng']
            && $body['travelMode'] === 'DRIVE'
            && $body['routingPreference'] === 'TRAFFIC_UNAWARE'
            && $body['origin']['location']['latLng'] === ROUTE_ORIGIN;
    });
});

it('counts attempted Google calls, including ones that fail', function () {
    fakeGoogleRoute(['error' => ['message' => 'denied']], 403);
    config(['services.google_maps.routes_daily_limit' => 1]);
    Sanctum::actingAs(User::factory()->create());
    $office = routeOffice();

    $this->postJson(routePath($office), ROUTE_ORIGIN)->assertStatus(503);
    $this->postJson(routePath($office), ROUTE_ORIGIN)->assertStatus(503);

    Http::assertSentCount(1);
});

it('does not count requests that never reach Google', function () {
    fakeGoogleRoute();
    config(['services.google_maps.routes_daily_limit' => 1]);
    Sanctum::actingAs(User::factory()->create());

    $this->postJson(routePath(routeOffice(['latitude' => null, 'longitude' => null])), ROUTE_ORIGIN)->assertUnprocessable();
    $this->postJson(routePath(routeOffice()), ['latitude' => 1, 'longitude' => 1])->assertUnprocessable();
    $this->postJson(routePath(routeOffice()), ROUTE_ORIGIN)->assertOk();

    Http::assertSentCount(1);
});

it('enforces the daily limit through the real database counter', function () {
    fakeGoogleRoute();
    config(['services.google_maps.routes_daily_limit' => 2]);
    Cache::setDefaultDriver('database');
    $office = routeOffice();
    $estimator = app(OfficeRouteEstimator::class);

    $statuses = collect(range(1, 3))->map(fn () => $estimator->estimate($office, 14.7574, 120.9483)['status']);

    expect($statuses->all())->toBe(['found', 'found', 'limit_reached']);
    Http::assertSentCount(2);
});

it('makes no Google call when the counter cannot be trusted', function (Closure $breakCounter) {
    fakeGoogleRoute();
    $office = routeOffice();
    $breakCounter();

    expect(app(OfficeRouteEstimator::class)->estimate($office, 14.7574, 120.9483)['status'])->toBe('limit_reached');

    Http::assertNothingSent();
})->with([
    'the counter is missing' => [function () {
        Cache::shouldReceive('add')->andReturnTrue();
        Cache::shouldReceive('increment')->andReturnFalse();
    }],
    'storage fails while creating it' => [function () {
        Cache::shouldReceive('add')->andThrow(new RuntimeException('database down'));
    }],
    'storage fails while counting' => [function () {
        Cache::shouldReceive('add')->andReturnTrue();
        Cache::shouldReceive('increment')->andThrow(new RuntimeException('database down'));
    }],
]);

it('always routes to the CMS coordinates', function () {
    fakeGoogleRoute();
    Sanctum::actingAs(User::factory()->create());

    $this->postJson(routePath(routeOffice()), [...ROUTE_ORIGIN, 'destination' => ['latitude' => 1, 'longitude' => 1]])->assertOk();

    Http::assertSent(fn (Request $request) => $request['destination']['location']['latLng'] === ['latitude' => 14.8, 'longitude' => 120.92]);
});

it('requires a logged-in user', function () {
    fakeGoogleRoute();

    $this->postJson(routePath(routeOffice()), ROUTE_ORIGIN)->assertUnauthorized();

    Http::assertNothingSent();
});

it('rejects a location that is missing or outside the Philippines', function (array $origin) {
    fakeGoogleRoute();
    Sanctum::actingAs(User::factory()->create());

    $this->postJson(routePath(routeOffice()), $origin)->assertUnprocessable();

    Http::assertNothingSent();
})->with([
    'missing' => [[]],
    'only latitude' => [['latitude' => 14.7]],
    'not numbers' => [['latitude' => 'near', 'longitude' => 'here']],
    'south of the Philippines' => [['latitude' => 1.3, 'longitude' => 120.9]],
    'east of the Philippines' => [['latitude' => 14.7, 'longitude' => 140.0]],
]);

it('calls Google only for an office with a usable location', function (array $attributes) {
    fakeGoogleRoute();
    Sanctum::actingAs(User::factory()->create());

    $this->postJson(routePath(routeOffice($attributes)), ROUTE_ORIGIN)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'office_no_location');

    Http::assertNothingSent();
})->with([
    'never located' => [['latitude' => null, 'longitude' => null, 'location_source' => null]],
    'only one coordinate' => [['longitude' => null]],
    'unset zeros' => [['latitude' => 0, 'longitude' => 0]],
]);

it('makes no call while off or without a key', function (array $settings) {
    fakeGoogleRoute();
    config($settings);
    Sanctum::actingAs(User::factory()->create());

    $this->postJson(routePath(routeOffice()), ROUTE_ORIGIN)
        ->assertStatus(503)
        ->assertJsonPath('code', 'routes_unavailable');

    Http::assertNothingSent();
})->with([
    'switched off' => [['services.google_maps.routes_enabled' => false]],
    'no key' => [['services.google_maps.server_key' => null]],
]);

it('stops calling Google at the daily limit for the whole app', function () {
    fakeGoogleRoute();
    config(['services.google_maps.routes_daily_limit' => 2]);
    $office = routeOffice();

    foreach ([200, 200, 503] as $status) {
        Sanctum::actingAs(User::factory()->create());
        $this->postJson(routePath($office), ROUTE_ORIGIN)->assertStatus($status);
    }

    Http::assertSentCount(2);
});

it('limits how quickly one resident can ask', function () {
    fakeGoogleRoute();
    Sanctum::actingAs(User::factory()->create());
    $office = routeOffice();

    foreach (range(1, 10) as $ignored) {
        $this->postJson(routePath($office), ROUTE_ORIGIN)->assertOk();
    }

    $this->postJson(routePath($office), ROUTE_ORIGIN)->assertTooManyRequests();
    Http::assertSentCount(10);
});

it('reports when no driving route exists', function () {
    fakeGoogleRoute([]);
    Sanctum::actingAs(User::factory()->create());

    $this->postJson(routePath(routeOffice()), ROUTE_ORIGIN)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'no_route');
});

it('treats values Google leaves out as zero', function () {
    fakeGoogleRoute(['routes' => [['duration' => '0s']]]);
    Sanctum::actingAs(User::factory()->create());

    $this->postJson(routePath(routeOffice()), ROUTE_ORIGIN)
        ->assertOk()
        ->assertJsonPath('data.distance_meters', 0);
});

it('fails cleanly and never logs the location', function ($fake) {
    Log::spy();
    Http::fake(['routes.googleapis.com/*' => $fake]);
    Sanctum::actingAs(User::factory()->create());

    $this->postJson(routePath(routeOffice()), ROUTE_ORIGIN)
        ->assertStatus(503)
        ->assertJsonPath('code', 'routes_unavailable')
        ->assertDontSee('14.7574');

    Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context) => ! str_contains(json_encode($context), '14.7574')
        && ! str_contains(json_encode($context), '120.9483'));
})->with([
    'rejected' => fn () => Http::response(['error' => ['message' => 'API not enabled']], 403),
    'unreachable' => fn () => fn () => throw new ConnectionException('offline'),
    'malformed' => fn () => Http::response(['routes' => [['distanceMeters' => 'far', 'duration' => 'soon']]]),
]);
