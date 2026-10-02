<?php

use App\Models\ContentChangeLog;
use App\Models\GovernmentId;
use App\Models\GovernmentIdOffice;
use App\Models\Office;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['role' => 'researcher']));
});

function officeLinkRow(Office $office, array $overrides = []): array
{
    return array_merge([
        'office_id' => $office->id,
        'new_application_status' => 'unknown',
        'renewal_status' => 'unknown',
        'replacement_status' => 'unknown',
        'service_notes' => '',
    ], $overrides);
}

it('creates an ID with several draft branches and displays their service details', function () {
    $one = Office::create(['name' => 'Branch One']);
    $two = Office::create(['name' => 'Branch Two']);
    $this->get('/admin/government-ids/create')->assertOk()->assertSee('Linked Offices')->assertSee('Select a branch');
    $this->post('/admin/government-ids', [
        'name' => 'Multiple branches ID', 'office_links_present' => 1,
        'office_location' => 'Legacy location', 'office_hours' => 'Legacy hours',
        'office_links' => [
            officeLinkRow($one, ['new_application_status' => 'available', 'service_notes' => 'Appointment required.']),
            officeLinkRow($two),
        ],
    ])->assertSessionHasNoErrors();
    $id = GovernmentId::firstOrFail();
    expect($id->offices)->toHaveCount(2)
        ->and($one->fresh()->status)->toBe('draft')
        ->and($id->offices->first()->pivot->last_verified_at)->toBeNull();
    $this->get('/admin/government-ids/'.$id->id)->assertOk()->assertSee('Branch One')->assertSee('Branch Two')->assertSee('Appointment required.');
    $this->get('/admin/government-ids/'.$id->id.'/edit')->assertOk()->assertSee('Linked Offices')->assertSee('Branch One');
    $this->getJson('/api/government-ids/'.$id->id)->assertOk()
        ->assertJsonPath('data.office_location', 'Legacy location')
        ->assertJsonPath('data.office_hours', 'Legacy hours')
        ->assertJsonMissingPath('data.offices');
});

it('shows an add-office link when the directory is empty', function () {
    $this->get('/admin/government-ids/create')->assertOk()->assertSee('No offices yet.')
        ->assertSee(route('admin.offices.create'), false);
});

it('updates links and removes connections without deleting offices or other ID connections', function () {
    $one = Office::create(['name' => 'One']);
    $two = Office::create(['name' => 'Two']);
    $id = GovernmentId::create(['name' => 'Target']);
    $other = GovernmentId::create(['name' => 'Other']);
    $id->offices()->attach([$one->id, $two->id]);
    $other->offices()->attach($two->id);
    $this->put('/admin/government-ids/'.$id->id, [
        'name' => $id->name, 'office_links_present' => 1,
        'office_links' => [officeLinkRow($one, ['service_notes' => 'Research in progress.'])],
    ])->assertSessionHasNoErrors();
    expect($id->offices()->count())->toBe(1)
        ->and($id->offices()->first()->pivot->service_notes)->toBe('Research in progress.')
        ->and($other->offices()->count())->toBe(1);
    $this->assertModelExists($two);
    $this->put('/admin/government-ids/'.$id->id, ['name' => $id->name, 'office_links_present' => 1])
        ->assertSessionHasNoErrors();
    expect($id->offices()->count())->toBe(0)->and(Office::count())->toBe(2);
});

it('preserves links when another request omits the office editor', function () {
    $office = Office::create(['name' => 'One']);
    $id = GovernmentId::create(['name' => 'Target']);
    $id->offices()->attach($office->id);
    $this->put('/admin/government-ids/'.$id->id, ['name' => 'Renamed'])->assertSessionHasNoErrors();
    expect($id->offices()->count())->toBe(1);
});

it('rejects duplicate and nonexistent offices without saving the ID', function () {
    $office = Office::create(['name' => 'One']);
    $this->post('/admin/government-ids', [
        'name' => 'Duplicate', 'office_links' => [officeLinkRow($office), officeLinkRow($office)],
    ])->assertSessionHasErrors('office_links.0.office_id');
    $this->post('/admin/government-ids', [
        'name' => 'Missing', 'office_links' => [officeLinkRow($office, ['office_id' => 999999])],
    ])->assertSessionHasErrors('office_links.0.office_id');
    expect(GovernmentId::count())->toBe(0);
});

it('allows service availability without a source and rejects invalid statuses', function () {
    $office = Office::create(['name' => 'One']);
    foreach (['available', 'unavailable'] as $status) {
        $this->post('/admin/government-ids', [
            'name' => 'No source '.$status,
            'office_links' => [officeLinkRow($office, ['renewal_status' => $status])],
        ])->assertSessionHasNoErrors();
    }
    $this->post('/admin/government-ids', [
        'name' => 'Bad status', 'office_links' => [officeLinkRow($office, ['renewal_status' => 'maybe'])],
    ])->assertSessionHasErrors('office_links.0.renewal_status');
    expect(GovernmentId::count())->toBe(2);
});

it('preserves historical source and verification data when editing services', function () {
    $office = Office::create(['name' => 'One']);
    $id = GovernmentId::create(['name' => 'Target']);
    $id->last_verified_at = '2026-09-25 00:00:00';
    $id->last_verified_by = auth()->id();
    $id->save();
    $link = GovernmentIdOffice::create([
        'government_id_id' => $id->id, 'office_id' => $office->id,
        'source_url' => 'https://example.org/old-source',
    ]);
    $link->last_verified_at = '2026-09-25 10:00:00';
    $link->last_verified_by = auth()->id();
    $link->save();
    $this->put('/admin/government-ids/'.$id->id, [
        'name' => $id->name, 'verify_today' => 1,
        'office_links' => [officeLinkRow($office, ['new_application_status' => 'available'])],
    ])->assertSessionHasNoErrors();
    expect($link->fresh()->source_url)->toBe('https://example.org/old-source')
        ->and($link->fresh()->last_verified_at->toDateTimeString())->toBe('2026-09-25 10:00:00')
        ->and($id->fresh()->last_verified_at->toDateString())->toBe('2026-09-25');
    $this->get('/admin/government-ids/'.$id->id)->assertOk()->assertSee('Service source')
        ->assertDontSee('Verification Status')->assertDontSee('Last verified:');
});

it('removes form verification controls and rejects forged connection metadata', function () {
    $office = Office::create(['name' => 'One']);
    $id = GovernmentId::create(['name' => 'Target']);
    foreach (['/admin/government-ids/create', '/admin/government-ids/'.$id->id.'/edit'] as $url) {
        $this->get($url)->assertOk()->assertDontSee('Official Service Source')
            ->assertDontSee('verify_today', false)->assertDontSee('Verification Status')
            ->assertSee('Not yet researched');
    }
    $this->post('/admin/government-ids', [
        'name' => 'Forged', 'office_links' => [officeLinkRow($office, ['last_verified_by' => auth()->id()])],
    ])->assertSessionHasErrors('office_links.0');
});

it('shows the latest editor from office changes on View only', function () {
    $office = Office::create(['name' => 'One']);
    $id = GovernmentId::create(['name' => 'Target']);
    $editor = User::factory()->create(['role' => 'researcher', 'name' => 'Second Researcher']);
    $this->actingAs($editor);
    $this->travelTo(now()->addDay()->startOfMinute());
    try {
        $this->put('/admin/government-ids/'.$id->id, [
            'name' => $id->name, 'office_links' => [officeLinkRow($office)],
        ])->assertSessionHasNoErrors();
        $this->get('/admin/government-ids/'.$id->id)->assertOk()
            ->assertSee('Edit History')->assertSee('Last edited by')->assertSee('Second Researcher')
            ->assertSee(now()->timezone('Asia/Manila')->format('F j, Y · g:i A').' PHT');
        $this->get('/admin/government-ids/'.$id->id.'/edit')->assertDontSee('Edit History');
    } finally {
        $this->travelBack();
    }
});

it('records fee-only edits even when the readable fee summary stays the same', function () {
    $id = GovernmentId::create(['name' => 'Fee example']);
    $payload = ['name' => $id->name, 'fees' => [[
        'label' => 'Application', 'type' => 'fixed', 'amount_min' => 100,
        'is_optional' => 0, 'notes' => 'Original note',
    ]]];
    $this->put('/admin/government-ids/'.$id->id, $payload)->assertSessionHasNoErrors();
    $editor = User::factory()->create(['role' => 'researcher', 'name' => 'Fee Researcher']);
    $this->actingAs($editor);
    $payload['fees'][0]['notes'] = 'Updated note';
    $this->put('/admin/government-ids/'.$id->id, $payload)->assertSessionHasNoErrors();
    $log = ContentChangeLog::where('entity_type', 'government_id')->where('entity_id', $id->id)->latest('id')->first();
    expect($log->changed_fields)->toBe(['fees'])->and($log->user_id)->toBe($editor->id);
    $this->get('/admin/government-ids/'.$id->id)->assertOk()->assertSee('Fee Researcher');
});

it('shows a safe fallback when the editor was not recorded or was deleted', function () {
    $id = GovernmentId::withoutEvents(fn () => GovernmentId::create(['name' => 'Legacy ID']));
    $this->get('/admin/government-ids/'.$id->id)->assertOk()->assertSee('Editor not recorded');
    $oldEditor = User::factory()->create();
    ContentChangeLog::create([
        'user_id' => $oldEditor->id, 'entity_type' => 'government_id', 'entity_id' => $id->id,
        'action' => 'updated', 'changed_fields' => ['name'], 'created_at' => now(),
    ]);
    $oldEditor->delete();
    $this->get('/admin/government-ids/'.$id->id)->assertOk()->assertSee('Editor not recorded');
});

it('does not erase removed rows intent when another field fails validation', function () {
    $office = Office::create(['name' => 'One']);
    $id = GovernmentId::create(['name' => 'Target']);
    $id->offices()->attach($office->id);
    $this->from('/admin/government-ids/'.$id->id.'/edit')->put('/admin/government-ids/'.$id->id, [
        'name' => '', 'office_links_present' => 1,
    ])->assertSessionHasErrors('name');
    $this->get('/admin/government-ids/'.$id->id.'/edit')->assertOk()->assertSee('links: []', false);
    expect($id->offices()->count())->toBe(1);
});

it('redisplays malformed office data safely', function () {
    $this->from('/admin/government-ids/create')->post('/admin/government-ids', [
        'name' => 'Retry', 'office_links_present' => 1,
        'office_links' => [['office_id' => ['bad'], 'service_notes' => ['bad']]],
    ])->assertSessionHasErrors();
    $this->get('/admin/government-ids/create')->assertOk()->assertSee('Retry');
});

it('blocks residents from changing ID office links', function () {
    $office = Office::create(['name' => 'One']);
    $id = GovernmentId::create(['name' => 'Target']);
    $this->actingAs(User::factory()->create());
    $this->put('/admin/government-ids/'.$id->id, [
        'name' => $id->name, 'office_links' => [officeLinkRow($office)],
    ])->assertForbidden();
    expect($id->offices()->count())->toBe(0);
});

it('audits connection changes and rolls back the ID when link persistence fails', function () {
    $office = Office::create(['name' => 'One']);
    $id = GovernmentId::create(['name' => 'Target']);
    $this->put('/admin/government-ids/'.$id->id, ['name' => $id->name, 'office_links' => [officeLinkRow($office)]])->assertSessionHasNoErrors();
    expect(ContentChangeLog::where('entity_type', 'government_id')->latest('id')->first()->changed_fields)->toBe(['offices']);

    $this->withoutExceptionHandling();
    GovernmentIdOffice::creating(function () { throw new RuntimeException('Link save failed'); });
    try {
        expect(fn () => $this->post('/admin/government-ids', [
            'name' => 'Must roll back', 'office_links' => [officeLinkRow($office)],
        ]))->toThrow(RuntimeException::class, 'Link save failed');
        expect(GovernmentId::where('name', 'Must roll back')->exists())->toBeFalse();
    } finally {
        GovernmentIdOffice::flushEventListeners();
    }
});
