<?php

use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\Office;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

function encoder(string $role): User
{
    return User::factory()->create(['role' => $role, 'is_active' => true]);
}

function linkedOffice(array $attributes = []): Office
{
    $office = Office::create(['name' => 'Malolos Post Office', 'address' => 'Paseo del Congreso', ...$attributes]);
    GovernmentId::firstOrCreate(['name' => 'Postal ID'])->offices()->attach($office);

    return $office;
}

function residentOffice(Office $office): array
{
    return collect(test()->getJson('/api/offices')->assertOk()->json('data'))->firstWhere('id', $office->id);
}

it('makes saved coordinates usable straight away for every encoding role', function (string $role) {
    $this->actingAs(encoder($role));

    $this->post('/admin/offices', ['name' => 'New Branch', 'address' => '1 Rizal St.', 'latitude' => '14.8527', 'longitude' => '120.816'])
        ->assertSessionHasNoErrors();
    $office = Office::where('name', 'New Branch')->firstOrFail();
    GovernmentId::create(['name' => 'Passport'])->offices()->attach($office);

    $published = residentOffice($office);
    expect((float) $published['latitude'])->toBe(14.8527)
        ->and((float) $published['longitude'])->toBe(120.816);
})->with(['researcher', 'super_admin']);

it('shows edited coordinates to residents on the next load', function (string $role) {
    $office = linkedOffice(['latitude' => 14.8, 'longitude' => 120.9]);
    $this->actingAs(encoder($role));

    $this->put('/admin/offices/'.$office->id, ['name' => $office->name, 'address' => $office->address, 'latitude' => '14.81', 'longitude' => '120.95'])
        ->assertSessionHasNoErrors();

    $published = residentOffice($office);
    expect((float) $published['latitude'])->toBe(14.81)
        ->and((float) $published['longitude'])->toBe(120.95);
})->with(['researcher', 'super_admin']);

it('keeps automatically found coordinates usable', function () {
    $office = linkedOffice();
    $office->forceFill(['latitude' => 14.7574, 'longitude' => 120.9483, 'location_source' => 'auto', 'location_status' => 'found'])->saveQuietly();

    expect((float) residentOffice($office)['latitude'])->toBe(14.7574);
});

it('never sends a pin for missing or invalid coordinates', function (array $coordinates) {
    $office = linkedOffice();
    $office->forceFill($coordinates)->saveQuietly();

    $published = residentOffice($office);
    expect($published['latitude'])->toBeNull()
        ->and($published['longitude'])->toBeNull()
        ->and($published['name'])->toBe('Malolos Post Office')
        ->and($published['address'])->toBe('Paseo del Congreso');
})->with([
    'both missing' => [['latitude' => null, 'longitude' => null]],
    'latitude missing' => [['latitude' => null, 'longitude' => 120.9]],
    'longitude missing' => [['latitude' => 14.8, 'longitude' => null]],
    'zero zero' => [['latitude' => 0, 'longitude' => 0]],
]);

it('applies the usable-location rule at its boundaries', function (mixed $latitude, mixed $longitude, bool $usable) {
    expect((new Office)->forceFill(['latitude' => $latitude, 'longitude' => $longitude])->hasUsableLocation())->toBe($usable);
})->with([
    'valid' => [14.8527, 120.816, true],
    'zero latitude only' => [0, 120.9, true],
    'zero longitude only' => [14.8, 0, true],
    'edge of range' => [90, 180, true],
    'zero zero' => [0, 0, false],
    'latitude over range' => [90.5, 120, false],
    'longitude over range' => [14, 180.5, false],
    'null' => [null, null, false],
]);

it('rejects out-of-range and placeholder coordinates when encoding', function (array $input, string $field) {
    $this->actingAs(encoder('researcher'));

    $this->post('/admin/offices', ['name' => 'Bad Pin', ...$input])->assertSessionHasErrors($field);
    expect(Office::where('name', 'Bad Pin')->exists())->toBeFalse();
})->with([
    'latitude range' => [['latitude' => '95', 'longitude' => '120'], 'latitude'],
    'longitude range' => [['latitude' => '14', 'longitude' => '190'], 'longitude'],
    'latitude alone' => [['latitude' => '14'], 'longitude'],
    'zero zero' => [['latitude' => '0', 'longitude' => '0'], 'latitude'],
]);

it('exposes no verification data and needs no verification column', function () {
    $office = linkedOffice(['latitude' => 14.8, 'longitude' => 120.9]);

    expect(residentOffice($office))->not->toHaveKey('location_verification')
        ->and(Schema::hasColumn('offices', 'location_verification'))->toBeFalse();
});

it('shows no verification step in the CMS', function () {
    $this->actingAs(encoder('super_admin'));
    $office = linkedOffice(['latitude' => 14.8, 'longitude' => 120.9]);

    $this->get('/admin/offices/'.$office->id.'/edit')->assertOk()
        ->assertDontSee('Location Verification')
        ->assertDontSee('Verified Building')
        ->assertDontSee('Verified Entrance')
        ->assertSee('as soon as valid coordinates are saved');
    expect(Route::has('admin.offices.location-verification'))->toBeFalse();
});

it('makes no Google request when saving or loading offices', function () {
    Http::fake();
    config(['services.google_maps.server_key' => 'test-key']);
    $this->actingAs(encoder('researcher'));

    $this->post('/admin/offices', ['name' => 'Quiet Office', 'address' => 'Somewhere'])->assertSessionHasNoErrors();
    $office = Office::where('name', 'Quiet Office')->firstOrFail();
    $this->put('/admin/offices/'.$office->id, ['name' => 'Quiet Office', 'address' => 'Moved'])->assertSessionHasNoErrors();
    $this->getJson('/api/offices')->assertOk();

    Http::assertNothingSent();
});

it('leaves linked records and office details intact when coordinates change', function () {
    $office = linkedOffice(['latitude' => 14.8, 'longitude' => 120.9, 'municipality' => 'Malolos']);
    Document::create(['name' => 'Postal Clearance'])->offices()->attach($office);
    $office->schedules()->create(['day_of_week' => 1, 'status' => 'open'])->intervals()->create(['opens_at' => '08:00:00', 'closes_at' => '17:00:00']);
    $this->actingAs(encoder('researcher'));

    $this->put('/admin/offices/'.$office->id, ['name' => $office->name, 'address' => $office->address, 'municipality' => 'Malolos', 'latitude' => '14.82', 'longitude' => '120.91'])
        ->assertSessionHasNoErrors();

    $published = residentOffice($office);
    expect($published['records'])->toBe(['Postal Clearance', 'Postal ID'])
        ->and($published['municipality'])->toBe('Malolos')
        ->and($published['schedules'][0]['intervals'][0]['opens_at'])->toBe('08:00:00');
});

it('applies the same rule on ID and document office lists', function () {
    $valid = Office::create(['name' => 'Valid', 'latitude' => 14.8, 'longitude' => 120.9]);
    $invalid = Office::create(['name' => 'Placeholder', 'latitude' => 0, 'longitude' => 0]);
    $id = GovernmentId::create(['name' => 'Linked ID']);
    $id->offices()->attach([$valid->id, $invalid->id]);

    $offices = collect($this->getJson('/api/government-ids/'.$id->id)->assertOk()->json('offices') ?? $this->getJson('/api/government-ids/'.$id->id)->json('data.offices'));

    expect((float) $offices->firstWhere('name', 'Valid')['latitude'])->toBe(14.8)
        ->and($offices->firstWhere('name', 'Placeholder')['latitude'])->toBeNull();
});

it('keeps road distance off by default', function () {
    expect(config('services.google_maps.routes_enabled'))->toBeFalse();
});

it('keeps role rules for publishing and account screens', function () {
    $office = Office::create(['name' => 'Draft Office']);
    $this->actingAs(encoder('researcher'));

    $this->put('/admin/offices/'.$office->id, ['name' => 'Draft Office', 'status' => 'active'])->assertSessionHasErrors('status');
    $this->get('/admin/users')->assertForbidden();
});

it('blocks residents and guests from encoding', function () {
    $this->post('/admin/offices', ['name' => 'Guest'])->assertRedirect('/login');

    $this->actingAs(encoder('resident'));
    $this->post('/admin/offices', ['name' => 'Resident'])->assertForbidden();
    expect(Office::count())->toBe(0);
});
