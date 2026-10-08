<?php

use App\Models\Office;
use App\Models\User;

it('renders the existing office form without the CMS sidebar when embedded', function () {
    $this->actingAs(User::factory()->create(['role' => 'researcher']));
    $this->get('/admin/offices/create?embedded=1')->assertOk()
        ->assertSee('Office / Branch Name')->assertSee('Office Hours')
        ->assertSee('embedded=1')->assertDontSee('admin-sidebar')->assertDontSee('Back to offices');
    $this->get('/admin/offices/create')->assertOk()->assertSee('Back to offices');
});

it('creates an office and its schedule and returns its selection data to the parent', function () {
    $this->actingAs(User::factory()->create(['role' => 'researcher']));
    $this->post('/admin/offices?embedded=1', [
        'name' => 'Example branch', 'municipality' => 'Santa Maria', 'province' => 'Bulacan',
        'schedules' => [1 => ['status' => 'open', 'intervals' => [['opens_at' => '08:00', 'closes_at' => '17:00']]]],
    ])->assertOk()->assertViewIs('admin.offices.created-embedded')
        ->assertViewHas('createdOffice', fn ($office) => $office['name'] === 'Example branch' && $office['location'] === 'Santa Maria, Bulacan')
        ->assertSee('idireksyon:office-created');
    $office = Office::sole();
    expect($office->status)->toBe('draft')
        ->and($office->schedules()->sole()->intervals()->count())->toBe(1);
});

it('keeps validation and CMS authorization for embedded creation', function () {
    $this->get('/admin/offices/create?embedded=1')->assertRedirect('/login');
    $this->actingAs(User::factory()->create(['role' => 'resident']));
    $this->post('/admin/offices?embedded=1', ['name' => 'Forbidden'])->assertForbidden();
    $this->actingAs(User::factory()->create(['role' => 'researcher']));
    $this->from('/admin/offices/create?embedded=1')
        ->post('/admin/offices?embedded=1', ['name' => '', 'status' => 'published'])
        ->assertRedirect('/admin/offices/create?embedded=1')->assertSessionHasErrors(['name', 'status']);
    expect(Office::count())->toBe(0);
});
