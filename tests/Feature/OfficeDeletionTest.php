<?php

use App\Models\ContentChangeLog;
use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\Office;
use App\Models\OfficeSchedule;
use App\Models\User;
use App\Services\OfficeDeletion;
use Illuminate\Support\Facades\DB;

function deletableOffice(string $name = 'Duplicate branch'): Office
{
    $office = Office::create(['name' => $name]);
    $office->schedules()->create(['day_of_week' => 1, 'status' => 'open'])
        ->intervals()->create(['opens_at' => '08:00:00', 'closes_at' => '17:00:00']);

    return $office;
}

function officeVerificationRow(Office $office): void
{
    $record = GovernmentId::create(['name' => 'Verified Passport']);
    $user = User::factory()->create(['role' => 'researcher']);
    DB::table('catalog_agency_verifications')->insert([
        'verifiable_type' => GovernmentId::class,
        'verifiable_id' => $record->id,
        'agency_name' => 'Fixture Agency',
        'office_id' => $office->id,
        'method' => 'written_letter',
        'confirmed_aspects' => json_encode(['requirements']),
        'scope' => 'Fixture scope',
        'recorded_by' => $user->id,
        'recorded_by_name' => 'fixture-researcher',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function superAdmin(): User
{
    return User::factory()->create(['role' => 'super_admin']);
}

it('lets a super admin delete an unused office with its hours', function () {
    $office = deletableOffice();
    $this->actingAs(superAdmin());

    $this->delete(route('admin.offices.destroy', $office))
        ->assertRedirect(route('admin.offices.index'))
        ->assertSessionHas('success', 'Duplicate branch deleted.');

    expect(Office::find($office->id))->toBeNull()
        ->and(OfficeSchedule::where('office_id', $office->id)->exists())->toBeFalse()
        ->and(DB::table('office_schedule_intervals')->count())->toBe(0)
        ->and(ContentChangeLog::where('entity_type', 'office')->where('action', 'deleted')->where('entity_name', 'Duplicate branch')->exists())->toBeTrue();
});

it('offers Delete only to super admins', function (string $role, bool $sees) {
    $office = deletableOffice();
    $this->actingAs(User::factory()->create(['role' => $role]));

    foreach (['/admin/offices', "/admin/offices/{$office->id}/edit"] as $page) {
        $response = $this->get($page)->assertOk();
        $sees
            ? $response->assertSee('Delete permanently')->assertSee(route('admin.offices.destroy', $office), false)
            : $response->assertDontSee('Delete permanently');
    }
})->with([
    'super admin' => ['super_admin', true],
    'researcher' => ['researcher', false],
]);

it('refuses deletion by anyone who is not a super admin', function () {
    $office = deletableOffice();
    $this->actingAs(User::factory()->create(['role' => 'researcher']));

    $this->delete(route('admin.offices.destroy', $office))->assertForbidden();

    expect(Office::find($office->id))->not->toBeNull();
});

it('refuses guests', function () {
    $office = deletableOffice();

    $this->delete(route('admin.offices.destroy', $office))->assertRedirect();

    expect(Office::find($office->id))->not->toBeNull();
});

it('keeps an office still linked to an ID or document', function (string $type) {
    $office = deletableOffice();
    $record = $type === 'id' ? GovernmentId::create(['name' => 'Postal ID']) : Document::create(['name' => 'Birth Certificate']);
    $record->offices()->attach($office->id);
    $this->actingAs(superAdmin());

    $this->get("/admin/offices/{$office->id}/edit")
        ->assertOk()
        ->assertSee('Linked to 1 ID or document. Remove those links first.')
        ->assertSee($record->name)
        ->assertSee(route($type === 'id' ? 'admin.government-ids.edit' : 'admin.documents.edit', $record), false)
        ->assertDontSee('Delete permanently');
    $this->get('/admin/offices')->assertSee('Linked to 1 ID or document.');

    $this->from('/admin/offices')->delete(route('admin.offices.destroy', $office))
        ->assertRedirect('/admin/offices')
        ->assertSessionHasErrors(['office_deletion' => OfficeDeletion::LINKED_MESSAGE]);

    expect(Office::find($office->id))->not->toBeNull()
        ->and($record->offices()->count())->toBe(1);
})->with(['ID' => 'id', 'document' => 'document']);

it('keeps an office named in agency verification history', function () {
    $office = deletableOffice();
    officeVerificationRow($office);
    $this->actingAs(superAdmin());

    $this->get("/admin/offices/{$office->id}/edit")
        ->assertSee('Kept because it is part of agency verification history.')
        ->assertDontSee('Delete permanently');

    $this->delete(route('admin.offices.destroy', $office))
        ->assertSessionHasErrors(['office_deletion' => OfficeDeletion::VERIFICATION_MESSAGE]);

    expect(Office::find($office->id))->not->toBeNull()
        ->and(DB::table('catalog_agency_verifications')->where('office_id', $office->id)->exists())->toBeTrue();
});

it('stops showing a deleted office to residents', function () {
    $office = deletableOffice();
    $this->actingAs(superAdmin());
    $this->delete(route('admin.offices.destroy', $office))->assertRedirect();

    $this->getJson('/api/offices')->assertOk()->assertJsonMissing(['name' => 'Duplicate branch']);
});
