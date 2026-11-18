<?php

use App\Models\Barangay;
use App\Models\GovernmentId;
use App\Models\Office;
use App\Models\User;
use App\Services\CatalogVerificationFingerprint;

beforeEach(function () {
    $this->bulac = Barangay::create(['name' => 'Bulac']);
    $this->catmon = Barangay::create(['name' => 'Catmon']);
});

it('saves a barangay-specific office from the single CMS choice', function () {
    $this->actingAs(User::factory()->create(['role' => 'researcher']));

    $this->post('/admin/offices', ['name' => 'Bulac Barangay Hall', 'barangay_coverage' => 'barangay:'.$this->bulac->id])
        ->assertSessionHasNoErrors();
    $office = Office::firstWhere('name', 'Bulac Barangay Hall');
    expect($office->coverage)->toBe('barangay')->and($office->barangay_id)->toBe($this->bulac->id);

    // Switching to shared clears the barangay; "Not set" clears both.
    $this->put('/admin/offices/'.$office->id, ['name' => 'Bulac Barangay Hall', 'barangay_coverage' => 'shared'])
        ->assertSessionHasNoErrors();
    expect($office->fresh()->only(['coverage', 'barangay_id']))->toBe(['coverage' => 'shared', 'barangay_id' => null]);

    $this->put('/admin/offices/'.$office->id, ['name' => 'Bulac Barangay Hall', 'barangay_coverage' => ''])
        ->assertSessionHasNoErrors();
    expect($office->fresh()->only(['coverage', 'barangay_id']))->toBe(['coverage' => null, 'barangay_id' => null]);
});

it('keeps the saved coverage when a save does not send the field', function () {
    $this->actingAs(User::factory()->create(['role' => 'researcher']));
    $office = Office::create(['name' => 'Hall', 'coverage' => 'barangay', 'barangay_id' => $this->bulac->id]);

    $this->put('/admin/offices/'.$office->id, ['name' => 'Hall renamed'])->assertSessionHasNoErrors();
    expect($office->fresh()->only(['coverage', 'barangay_id']))->toBe(['coverage' => 'barangay', 'barangay_id' => $this->bulac->id]);
});

it('tags an existing office without changing its location, hours, links or record dates', function () {
    $this->actingAs(User::factory()->create(['role' => 'researcher']));
    $record = GovernmentId::create(['name' => 'Barangay Clearance', 'level' => 'Barangay']);
    $office = Office::create(['name' => 'Bulac Hall', 'address' => 'Saved address', 'status' => 'active']);
    // A location found automatically earlier (its coordinates are not shown in the form boxes).
    $office->forceFill(['latitude' => 14.8, 'longitude' => 120.9, 'location_source' => 'auto',
        'location_status' => 'found', 'google_place_id' => 'saved-place'])->save();
    $schedule = $office->schedules()->create(['day_of_week' => 1, 'status' => 'open']);
    $schedule->intervals()->create(['opens_at' => '08:00:00', 'closes_at' => '17:00:00']);
    $record->offices()->attach($office, ['service_notes' => 'Saved note']);
    $recordBefore = $record->fresh()->only(['updated_at', 'content_updated_at']);

    // What the CMS form submits: every field as shown, coordinates blank.
    $this->put('/admin/offices/'.$office->id, ['name' => 'Bulac Hall', 'address' => 'Saved address',
        'status' => 'active', 'latitude' => '', 'longitude' => '', 'barangay_coverage' => 'barangay:'.$this->bulac->id])
        ->assertSessionHasNoErrors();

    $office->refresh();
    expect($office->only(['coverage', 'barangay_id', 'location_source', 'google_place_id', 'status']))
        ->toBe(['coverage' => 'barangay', 'barangay_id' => $this->bulac->id, 'location_source' => 'auto',
            'google_place_id' => 'saved-place', 'status' => 'active'])
        ->and((float) $office->latitude)->toBe(14.8)
        ->and($office->schedules()->first()->intervals)->toHaveCount(1)
        ->and($record->offices()->first()->pivot->service_notes)->toBe('Saved note')
        ->and($record->fresh()->only(['updated_at', 'content_updated_at']))->toEqual($recordBefore);
});

it('rejects unknown coverage choices and barangays', function (string $choice) {
    $this->actingAs(User::factory()->create(['role' => 'researcher']));
    $this->post('/admin/offices', ['name' => 'Bad', 'barangay_coverage' => $choice])
        ->assertSessionHasErrors();
    expect(Office::where('name', 'Bad')->exists())->toBeFalse();
})->with(['everywhere', 'barangay:999999', 'barangay:']);

it('shows the coverage choice on the office form', function () {
    $this->actingAs(User::factory()->create(['role' => 'researcher']));
    $office = Office::create(['name' => 'Hall', 'coverage' => 'barangay', 'barangay_id' => $this->catmon->id]);

    $this->get('/admin/offices/'.$office->id.'/edit')->assertOk()
        ->assertSee('Barangay coverage')
        ->assertSee('value="barangay:'.$this->catmon->id.'" selected', false);
});

it('tells the app who each linked office serves', function () {
    $record = GovernmentId::create(['name' => 'Barangay ID', 'level' => 'Barangay']);
    $hall = Office::create(['name' => 'Bulac Hall', 'coverage' => 'barangay', 'barangay_id' => $this->bulac->id]);
    $shared = Office::create(['name' => 'Municipal Hall', 'coverage' => 'shared']);
    $unset = Office::create(['name' => 'Unclassified office']);
    $record->offices()->attach([$hall->id, $shared->id, $unset->id]);

    $offices = collect($this->getJson('/api/government-ids/'.$record->id)->assertOk()->json('data.offices'))->keyBy('name');

    expect($offices['Bulac Hall']['coverage'])->toBe('barangay')
        ->and($offices['Bulac Hall']['barangay'])->toBe(['id' => $this->bulac->id, 'name' => 'Bulac'])
        ->and($offices['Municipal Hall']['coverage'])->toBe('shared')
        ->and($offices['Municipal Hall']['barangay'])->toBeNull()
        ->and($offices['Unclassified office']['coverage'])->toBeNull()
        ->and($offices['Unclassified office']['barangay'])->toBeNull();
});

it('does not outdate agency confirmations when an office is classified', function () {
    $record = GovernmentId::create(['name' => 'Barangay ID', 'level' => 'Barangay']);
    $office = Office::create(['name' => 'Bulac Hall']);
    $record->offices()->attach($office);
    $fingerprints = app(CatalogVerificationFingerprint::class);
    $before = $fingerprints->fingerprint($fingerprints->snapshot($record, ['offices'], null));

    $office->update(['coverage' => 'barangay', 'barangay_id' => $this->bulac->id]);

    expect($fingerprints->fingerprint($fingerprints->snapshot($record->fresh(), ['offices'], null)))->toBe($before);
});

it('words the public share page for barangay records without listing barangay offices', function () {
    $record = GovernmentId::create(['name' => 'Barangay ID', 'level' => 'Barangay']);
    $record->offices()->attach(Office::create(['name' => 'Bulac Hall', 'coverage' => 'barangay', 'barangay_id' => $this->bulac->id]));

    $this->get('/ids/'.$record->id)->assertOk()
        ->assertSee('and the step-by-step guide for Barangay ID, plus the offices that serve your barangay.')
        ->assertDontSee('nearby offices')
        ->assertDontSee('Bulac Hall');

    $national = GovernmentId::create(['name' => 'National ID', 'level' => 'National']);
    $this->get('/ids/'.$national->id)->assertOk()
        ->assertSee('the step-by-step guide and nearby offices for National ID.')
        ->assertDontSee('serve your barangay');
});
