<?php

use App\Models\Agency;
use App\Models\ContentChangeLog;
use App\Models\Office;
use App\Models\OfficeSchedule;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['role' => 'researcher']));
});

it('renders the office directory and creates a name-only draft', function () {
    $this->get('/admin/offices')->assertOk()->assertSee('No offices yet.');
    $this->get('/admin/offices/create')->assertOk()->assertSee('Office Hours')->assertSee('Monday')->assertSee('Sunday');
    $this->post('/admin/offices', ['name' => 'Research branch'])->assertSessionHasNoErrors();
    $office = Office::firstOrFail();
    expect($office->status)->toBe('draft')->and($office->agency_id)->toBeNull()
        ->and($office->latitude)->toBeNull()->and($office->last_verified_at)->toBeNull()
        ->and($office->schedules)->toHaveCount(0);
    $this->get('/admin/offices/'.$office->id.'/edit')->assertOk()->assertSee('Research branch');
    expect(ContentChangeLog::where('entity_type', 'office')->where('action', 'created')->exists())->toBeTrue();
});

it('saves split intervals and clears them when a day becomes closed', function () {
    $agency = Agency::create(['name' => 'Research agency']);
    $this->post('/admin/offices', [
        'name' => 'Local branch', 'agency_id' => $agency->id,
        'municipality' => 'Santa Maria', 'province' => 'Bulacan',
        'source_url' => 'https://example.org/office',
        'schedules' => [
            1 => ['status' => 'open', 'intervals' => [
                ['opens_at' => '13:00', 'closes_at' => '17:00'],
                ['opens_at' => '08:00', 'closes_at' => '12:00'],
            ]],
            0 => ['status' => 'unknown'],
        ],
    ])->assertSessionHasNoErrors();
    $office = Office::firstOrFail();
    expect($office->schedules()->where('day_of_week', 1)->firstOrFail()->intervals->pluck('opens_at')->all())
        ->toBe(['08:00:00', '13:00:00']);
    $this->get('/admin/offices/'.$office->id.'/edit')->assertOk()->assertSee('Local branch');
    $this->put('/admin/offices/'.$office->id, [
        'name' => 'Local branch', 'status' => 'inactive',
        'schedules' => [1 => ['status' => 'closed']],
    ])->assertSessionHasNoErrors();
    $monday = $office->schedules()->where('day_of_week', 1)->firstOrFail();
    expect($monday->status)->toBe('closed')->and($monday->intervals)->toHaveCount(0)
        ->and($office->fresh()->status)->toBe('inactive')
        ->and($office->schedules()->where('day_of_week', 0)->firstOrFail()->status)->toBe('unknown');
});

it('rejects invalid schedules without saving office changes', function (array $schedules, string $error) {
    $office = Office::create(['name' => 'Original branch']);
    $office->schedules()->create(['day_of_week' => 1, 'status' => 'closed']);
    $this->put('/admin/offices/'.$office->id, ['name' => 'Must not save', 'schedules' => $schedules])
        ->assertSessionHasErrors($error);
    expect($office->fresh()->name)->toBe('Original branch')
        ->and($office->schedules()->firstOrFail()->status)->toBe('closed');
})->with([
    'open without hours' => [[1 => ['status' => 'open']], 'schedules.1.intervals'],
    'reversed times' => [[1 => ['status' => 'open', 'intervals' => [['opens_at' => '17:00', 'closes_at' => '08:00']]]], 'schedules.1.intervals.0.closes_at'],
    'equal times' => [[1 => ['status' => 'open', 'intervals' => [['opens_at' => '08:00', 'closes_at' => '08:00']]]], 'schedules.1.intervals.0.closes_at'],
    'overlap' => [[1 => ['status' => 'open', 'intervals' => [['opens_at' => '08:00', 'closes_at' => '12:00'], ['opens_at' => '11:00', 'closes_at' => '17:00']]]], 'schedules.1.intervals.1.opens_at'],
    'bad time' => [[1 => ['status' => 'open', 'intervals' => [['opens_at' => '25:00', 'closes_at' => '17:00']]]], 'schedules.1.intervals.0.opens_at'],
    'closed with hours' => [[1 => ['status' => 'closed', 'intervals' => [['opens_at' => '08:00', 'closes_at' => '12:00']]]], 'schedules.1.intervals'],
    'invalid weekday' => [[7 => ['status' => 'unknown']], 'schedules'],
    'invalid daily status' => [[1 => ['status' => 'maybe']], 'schedules.1.status'],
]);

it('keeps existing schedules when a details-only request omits them', function () {
    $office = Office::create(['name' => 'Original']);
    $schedule = $office->schedules()->create(['day_of_week' => 1, 'status' => 'open']);
    $interval = $schedule->intervals()->create(['opens_at' => '08:00', 'closes_at' => '17:00']);
    $this->put('/admin/offices/'.$office->id, ['name' => 'Updated'])->assertSessionHasNoErrors();
    $this->assertModelExists($interval);
    expect($office->fresh()->name)->toBe('Updated');
});

it('logs schedule-only edits without marking research verified', function () {
    $office = Office::create(['name' => 'Branch']);
    $before = ContentChangeLog::count();
    $this->put('/admin/offices/'.$office->id, [
        'name' => 'Branch', 'schedules' => [1 => ['status' => 'closed']],
    ])->assertSessionHasNoErrors();
    expect(ContentChangeLog::count())->toBe($before + 1)
        ->and(ContentChangeLog::latest('id')->first()->changed_fields)->toBe(['schedules'])
        ->and($office->fresh()->last_verified_at)->toBeNull();
});

it('rejects publication and ignores unapproved verification and coordinate input', function () {
    $this->post('/admin/offices', ['name' => 'Branch', 'status' => 'published'])->assertSessionHasErrors('status');
    expect(Office::count())->toBe(0);
    $this->post('/admin/offices', [
        'name' => 'Branch', 'latitude' => 14.8, 'longitude' => 120.9,
        'last_verified_at' => '2026-10-01', 'last_verified_by' => auth()->id(),
    ])->assertSessionHasNoErrors();
    $office = Office::firstOrFail();
    expect($office->latitude)->toBeNull()->and($office->longitude)->toBeNull()
        ->and($office->last_verified_at)->toBeNull()->and($office->last_verified_by)->toBeNull();
});

it('validates office details and redisplays malformed inputs safely', function () {
    $this->from('/admin/offices/create')->post('/admin/offices', [
        'name' => ['invalid'], 'agency_id' => 999999, 'source_url' => 'javascript:bad',
        'schedules' => [1 => ['status' => ['invalid'], 'intervals' => 'bad']],
    ])->assertSessionHasErrors(['name', 'agency_id', 'source_url', 'schedules.1.status', 'schedules.1.intervals']);
    $this->get('/admin/offices/create')->assertOk()->assertSee('Please check');
});

it('filters and paginates offices while preserving the query', function () {
    $agency = Agency::create(['name' => 'Selected agency']);
    foreach (range(1, 16) as $i) {
        Office::create(['name' => sprintf('Branch %02d', $i), 'agency_id' => $agency->id, 'municipality' => 'Santa Maria']);
    }
    Office::create(['name' => 'Excluded entry', 'status' => 'inactive']);
    $url = '/admin/offices?q=Branch&agency_id='.$agency->id.'&status=draft';
    $this->get($url)->assertOk()->assertSee('Branch 01')->assertDontSee('Branch 16')->assertDontSee('Excluded entry')->assertSee('page=2');
    $this->get($url.'&page=2')->assertOk()->assertSee('Branch 16')->assertDontSee('Branch 01');
    $this->get('/admin/offices?q=Missing')->assertOk()->assertSee('No matching offices found.');
});

it('protects all office actions from guests residents and inactive staff', function () {
    $office = Office::create(['name' => 'Protected branch']);
    auth()->logout();
    $this->get('/admin/offices')->assertRedirect('/login');
    $this->post('/admin/offices', ['name' => 'Blocked'])->assertRedirect('/login');
    foreach ([['role' => 'resident'], ['role' => 'researcher', 'is_active' => false]] as $attributes) {
        $this->actingAs(User::factory()->create($attributes));
        foreach (['/admin/offices', '/admin/offices/create', '/admin/offices/'.$office->id.'/edit'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->post('/admin/offices', ['name' => 'Blocked'])->assertForbidden();
        $this->put('/admin/offices/'.$office->id, ['name' => 'Blocked'])->assertForbidden();
    }
    expect(Office::count())->toBe(1)->and($office->fresh()->name)->toBe('Protected branch');
});

it('rolls back office and audit writes if schedule persistence fails', function () {
    $this->withoutExceptionHandling();
    OfficeSchedule::creating(function () {
        throw new RuntimeException('Simulated schedule failure');
    });
    try {
        expect(fn () => $this->post('/admin/offices', [
            'name' => 'Rolled back branch', 'schedules' => [1 => ['status' => 'closed']],
        ]))->toThrow(RuntimeException::class, 'Simulated schedule failure');
        expect(Office::count())->toBe(0)->and(ContentChangeLog::where('entity_type', 'office')->count())->toBe(0);
    } finally {
        OfficeSchedule::flushEventListeners();
    }
});
