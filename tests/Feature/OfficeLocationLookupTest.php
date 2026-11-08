<?php

use App\Models\GovernmentId;
use App\Models\Office;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function officeLookupFound(string $id = 'place-1'): array
{
    return ['places' => [[
        'id' => $id,
        'displayName' => ['text' => 'Google name'],
        'formattedAddress' => 'Google address',
        'location' => ['latitude' => 14.7574, 'longitude' => 120.9483],
    ]]];
}

beforeEach(function () {
    Http::preventStrayRequests();
    config(['services.google_maps.server_key' => 'test-key']);
    $this->actingAs(User::factory()->create(['role' => 'researcher']));
});

it('finds a new office from its name and address and stores only the location', function () {
    Http::fake(['places.googleapis.com/*' => Http::response(officeLookupFound())]);

    $this->post('/admin/offices', [
        'name' => 'Marilao Post Office', 'address' => '162 Sandico St.', 'municipality' => 'Marilao', 'province' => 'Bulacan',
    ])->assertSessionHasNoErrors();

    $office = Office::firstOrFail();
    expect((float) $office->latitude)->toBe(14.7574)
        ->and((float) $office->longitude)->toBe(120.9483)
        ->and($office->google_place_id)->toBe('place-1')
        ->and($office->location_source)->toBe('auto')
        ->and($office->location_status)->toBe('found')
        ->and($office->location_checked_at)->not->toBeNull()
        ->and($office->address)->toBe('162 Sandico St.');
    Http::assertSent(fn (Request $request) => $request->hasHeader('X-Goog-Api-Key', 'test-key')
        && $request['textQuery'] === 'Marilao Post Office, 162 Sandico St., Marilao, Bulacan, Philippines'
        && $request['regionCode'] === 'PH');
});

it('keeps the lookup columns out of the resident API', function () {
    $office = Office::create(['name' => 'Linked office']);
    $office->forceFill(['google_place_id' => 'place-1', 'location_source' => 'auto', 'location_status' => 'found'])->saveQuietly();
    GovernmentId::create(['name' => 'Postal ID'])->offices()->attach($office);

    $data = $this->getJson('/api/offices')->assertOk()->json('data.0');

    expect($data)->not->toHaveKeys(['google_place_id', 'location_source', 'location_status', 'location_checked_at']);
});

it('treats typed coordinates as a manual override and never calls Google', function () {
    Http::fake();

    $this->post('/admin/offices', ['name' => 'Typed', 'address' => 'Somewhere', 'latitude' => 14.8, 'longitude' => 120.9])
        ->assertSessionHasNoErrors();

    $office = Office::firstOrFail();
    expect($office->location_source)->toBe('manual')
        ->and($office->location_status)->toBeNull()
        ->and((float) $office->latitude)->toBe(14.8);
    Http::assertNothingSent();

    $this->put('/admin/offices/'.$office->id, ['name' => 'Typed', 'address' => 'Moved', 'latitude' => 14.8, 'longitude' => 120.9])
        ->assertSessionHasNoErrors();
    Http::assertNothingSent();
});

it('requires latitude and longitude together', function () {
    Http::fake();

    $this->post('/admin/offices', ['name' => 'Half', 'latitude' => 14.8, 'longitude' => ''])->assertSessionHasErrors('longitude');
    $this->post('/admin/offices', ['name' => 'Half', 'latitude' => '', 'longitude' => 120.9])->assertSessionHasErrors('latitude');

    expect(Office::count())->toBe(0);
});

it('keeps an automatic location when the form is saved with blank override boxes', function () {
    Http::fake(['places.googleapis.com/*' => Http::response(officeLookupFound())]);
    $this->post('/admin/offices', ['name' => 'Found', 'address' => 'Here'])->assertSessionHasNoErrors();
    $office = Office::firstOrFail();

    $this->put('/admin/offices/'.$office->id, [
        'name' => 'Found', 'address' => 'Here', 'notes' => 'Only notes changed', 'latitude' => '', 'longitude' => '',
    ])->assertSessionHasNoErrors();

    expect($office->fresh()->location_source)->toBe('auto')
        ->and((float) $office->fresh()->latitude)->toBe(14.7574);
    Http::assertSentCount(1);
});

it('searches again when the address changes and clears a location it can no longer find', function () {
    Http::fake(['places.googleapis.com/*' => Http::sequence()
        ->push(officeLookupFound())
        ->push(['places' => []])]);
    $this->post('/admin/offices', ['name' => 'Moved', 'address' => 'Old street'])->assertSessionHasNoErrors();
    $office = Office::firstOrFail();

    $this->put('/admin/offices/'.$office->id, ['name' => 'Moved', 'address' => 'New street', 'latitude' => '', 'longitude' => ''])
        ->assertSessionHasNoErrors();

    $fresh = $office->fresh();
    expect($fresh->location_status)->toBe('not_found')
        ->and($fresh->location_source)->toBeNull()
        ->and($fresh->latitude)->toBeNull()
        ->and($fresh->google_place_id)->toBeNull();
    Http::assertSentCount(2);
});

it('does not search again for a not found office unless its address changes', function () {
    Http::fake(['places.googleapis.com/*' => Http::response(['places' => []])]);
    $this->post('/admin/offices', ['name' => 'Unknown'])->assertSessionHasNoErrors();
    $office = Office::firstOrFail();

    $this->put('/admin/offices/'.$office->id, ['name' => 'Unknown', 'notes' => 'Still unknown'])->assertSessionHasNoErrors();
    Http::assertSentCount(1);

    $this->put('/admin/offices/'.$office->id, ['name' => 'Unknown', 'address' => 'Now known'])->assertSessionHasNoErrors();
    Http::assertSentCount(2);
});

it('returns to automatic lookup when manual coordinates are cleared', function () {
    Http::fake(['places.googleapis.com/*' => Http::response(officeLookupFound())]);
    $this->post('/admin/offices', ['name' => 'Manual', 'latitude' => 14.8, 'longitude' => 120.9])->assertSessionHasNoErrors();
    $office = Office::firstOrFail();

    $this->put('/admin/offices/'.$office->id, ['name' => 'Manual', 'latitude' => '', 'longitude' => ''])->assertSessionHasNoErrors();

    expect($office->fresh()->location_source)->toBe('auto')
        ->and($office->fresh()->google_place_id)->toBe('place-1');
});

it('still saves the office when Google rejects or cannot be reached', function ($fake) {
    Http::fake(['places.googleapis.com/*' => $fake]);

    $this->post('/admin/offices', ['name' => 'Saved anyway'])->assertSessionHasNoErrors();

    $office = Office::firstOrFail();
    expect($office->name)->toBe('Saved anyway')
        ->and($office->location_status)->toBe('failed')
        ->and($office->latitude)->toBeNull();
})->with([
    'rejected' => fn () => Http::response(['error' => ['message' => 'denied']], 403),
    'unreachable' => fn () => fn () => throw new ConnectionException('timeout'),
]);

it('makes no lookup and saves normally when no key is configured', function () {
    config(['services.google_maps.server_key' => null]);
    Http::fake();

    $this->post('/admin/offices', ['name' => 'No key'])->assertSessionHasNoErrors();

    expect(Office::firstOrFail()->location_status)->toBeNull();
    Http::assertNothingSent();
});

it('shows automatic coordinates as status and leaves the override boxes blank', function () {
    $office = Office::create(['name' => 'Found office']);
    $office->forceFill([
        'latitude' => 14.7574, 'longitude' => 120.9483, 'google_place_id' => 'place-1',
        'location_source' => 'auto', 'location_status' => 'found',
    ])->saveQuietly();

    $this->get('/admin/offices/'.$office->id.'/edit')->assertOk()
        ->assertSee('View on Google Maps')
        ->assertSee('query_place_id=place-1', false)
        ->assertDontSee('value="14.7574', false);
});

it('keeps typed coordinates visible in the override boxes', function () {
    $office = Office::create(['name' => 'Manual office', 'latitude' => 14.8, 'longitude' => 120.9]);
    $office->forceFill(['location_source' => 'manual'])->saveQuietly();

    $this->get('/admin/offices/'.$office->id.'/edit')->assertOk()
        ->assertSee('value="14.8000000"', false)
        ->assertDontSee('View on Google Maps');
});

it('previews a backfill without calling Google or changing offices', function () {
    Http::fake();
    $office = Office::create(['name' => 'Waiting', 'municipality' => 'Malolos']);

    $this->artisan('offices:locate', ['--dry-run' => true])
        ->expectsOutputToContain('Waiting, Malolos, Philippines')
        ->assertSuccessful();

    Http::assertNothingSent();
    expect($office->fresh()->location_status)->toBeNull();
});

it('backfills only requested offices and skips manual and found ones', function () {
    Http::fake(['places.googleapis.com/*' => Http::response(officeLookupFound())]);
    $wanted = Office::create(['name' => 'Wanted']);
    $other = Office::create(['name' => 'Not requested']);
    $manual = Office::create(['name' => 'Manual', 'latitude' => 14.8, 'longitude' => 120.9]);
    $manual->forceFill(['location_source' => 'manual'])->saveQuietly();
    $found = Office::create(['name' => 'Already found']);
    $found->forceFill(['location_source' => 'auto', 'location_status' => 'found'])->saveQuietly();

    $this->artisan('offices:locate', ['--id' => [$wanted->id, $manual->id, $found->id]])
        ->expectsOutputToContain('Google name')
        ->assertSuccessful();

    expect($wanted->fresh()->location_source)->toBe('auto')
        ->and($other->fresh()->location_status)->toBeNull()
        ->and((float) $manual->fresh()->latitude)->toBe(14.8);
    Http::assertSentCount(1);
});

it('honors the backfill limit and retries failed offices', function () {
    Http::fake(['places.googleapis.com/*' => Http::response(officeLookupFound())]);
    $failed = Office::create(['name' => 'Failed before']);
    $failed->forceFill(['location_status' => 'failed'])->saveQuietly();
    $later = Office::create(['name' => 'Later']);

    $this->artisan('offices:locate', ['--limit' => '1'])->assertSuccessful();

    expect($failed->fresh()->location_status)->toBe('found')
        ->and($later->fresh()->location_status)->toBeNull();
});

it('refuses a real backfill without a key', function () {
    config(['services.google_maps.server_key' => null]);
    Http::fake();

    $this->artisan('offices:locate')->assertFailed();

    Http::assertNothingSent();
});
