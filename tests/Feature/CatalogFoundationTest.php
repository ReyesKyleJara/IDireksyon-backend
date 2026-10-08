<?php

use App\Models\Agency;
use App\Models\GovernmentId;
use App\Models\Office;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['role' => 'researcher']));
});

it('creates an agency through the CMS and reuses its record for an ID and office', function () {
    $agencyId = $this->postJson('/admin/agencies', ['name' => 'Example Agency', 'acronym' => 'EA'])
        ->assertCreated()->assertJsonPath('agency.name', 'Example Agency')->json('agency.id');
    $this->post('/admin/government-ids', ['name' => 'Example credential', 'agency_id' => $agencyId])->assertSessionHasNoErrors();
    $this->post('/admin/offices', ['name' => 'Example branch', 'agency_id' => $agencyId, 'status' => 'draft'])->assertSessionHasNoErrors();
    expect(GovernmentId::sole()->agency_id)->toBe($agencyId)
        ->and(Office::sole()->agency_id)->toBe($agencyId)->and(Agency::count())->toBe(1);
});

it('rejects duplicate agency names and invalid website values without creating records', function () {
    Agency::create(['name' => 'Existing Agency']);
    $this->postJson('/admin/agencies', ['name' => 'Existing Agency'])->assertUnprocessable()->assertJsonValidationErrors('name');
    $this->postJson('/admin/agencies', ['name' => 'New Agency', 'official_website' => 'not a URL'])
        ->assertUnprocessable()->assertJsonValidationErrors('official_website');
    expect(Agency::count())->toBe(1);
});

it('rejects a nonexistent issuing agency without saving an ID', function () {
    $this->postJson('/admin/government-ids', ['name' => 'Invalid agency ID', 'agency_id' => 999999])
        ->assertUnprocessable()->assertJsonValidationErrors('agency_id');
    $this->assertDatabaseMissing('government_ids', ['name' => 'Invalid agency ID']);
});

it('does not expose retired reference directory routes', function ($resource) {
    $this->get('/admin/reference/'.$resource)->assertNotFound();
    $this->post('/admin/reference/'.$resource, ['name' => 'Unused entry'])->assertNotFound();
})->with(['levels', 'categories', 'barangays', 'agencies', 'offices']);

it('protects agency creation from guests residents and inactive staff', function () {
    auth()->logout();
    $this->postJson('/admin/agencies', ['name' => 'Denied'])->assertUnauthorized();
    foreach ([['role' => 'resident'], ['role' => 'researcher', 'is_active' => false]] as $attributes) {
        $this->actingAs(User::factory()->create($attributes));
        $this->postJson('/admin/agencies', ['name' => 'Denied'])->assertForbidden();
    }
    $this->assertDatabaseMissing('agencies', ['name' => 'Denied']);
});
