<?php

use App\Models\Agency;
use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\Office;

it('lists active linked offices with their location, agency, hours and records', function () {
    $agency = Agency::create(['name' => 'Department of Foreign Affairs', 'acronym' => 'DFA']);
    $office = Office::create([
        'name' => 'DFA Malolos', 'agency_id' => $agency->id, 'status' => 'active',
        'address' => 'SM City Malolos', 'municipality' => 'Malolos', 'province' => 'Bulacan',
        'latitude' => 14.8527, 'longitude' => 120.816,
    ]);
    $schedule = $office->schedules()->create(['day_of_week' => 1, 'status' => 'open']);
    $schedule->intervals()->create(['opens_at' => '08:00:00', 'closes_at' => '17:00:00']);
    GovernmentId::create(['name' => 'Philippine Passport'])->offices()->attach($office);
    Document::create(['name' => 'Authentication'])->offices()->attach($office);

    $data = $this->getJson('/api/offices')->assertOk()->json('data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['name'])->toBe('DFA Malolos')
        ->and($data[0]['address'])->toBe('SM City Malolos')
        ->and($data[0]['municipality'])->toBe('Malolos')
        ->and((float) $data[0]['latitude'])->toBe(14.8527)
        ->and((float) $data[0]['longitude'])->toBe(120.816)
        ->and($data[0]['agency']['acronym'])->toBe('DFA')
        ->and($data[0]['records'])->toBe(['Authentication', 'Philippine Passport'])
        ->and($data[0]['schedules'][0]['day_of_week'])->toBe(1)
        ->and($data[0]['schedules'][0]['intervals'][0]['opens_at'])->toBe('08:00:00');
});

it('still lists a linked office that has no coordinates yet', function () {
    $office = Office::create(['name' => 'No pin yet', 'status' => 'active']);
    GovernmentId::create(['name' => 'Postal ID'])->offices()->attach($office);

    $this->getJson('/api/offices')->assertOk()
        ->assertJsonPath('data.0.name', 'No pin yet')
        ->assertJsonPath('data.0.latitude', null)
        ->assertJsonPath('data.0.longitude', null)
        ->assertJsonPath('data.0.agency', null);
});

it('lists linked offices whatever their status and hides unlinked ones', function () {
    $id = GovernmentId::create(['name' => 'Postal ID']);
    $id->offices()->attach(Office::create(['name' => 'Draft', 'status' => 'draft']));
    $id->offices()->attach(Office::create(['name' => 'Inactive', 'status' => 'inactive']));
    Office::create(['name' => 'Unlinked', 'status' => 'active']);

    $data = $this->getJson('/api/offices')->assertOk()->json('data');

    expect(array_column($data, 'name'))->toBe(['Draft', 'Inactive']);
});

it('respects records that hide their offices section', function () {
    $office = Office::create(['name' => 'Shared office', 'status' => 'active']);
    GovernmentId::create(['name' => 'Hidden ID', 'research_metadata' => ['hidden_sections' => ['offices']]])
        ->offices()->attach($office);
    $hiddenOnly = Office::create(['name' => 'Only hidden', 'status' => 'active']);
    GovernmentId::create(['name' => 'Other hidden', 'research_metadata' => ['hidden_sections' => ['offices']]])
        ->offices()->attach($hiddenOnly);
    Document::create(['name' => 'Visible document'])->offices()->attach($office);

    $data = $this->getJson('/api/offices')->assertOk()->json('data');

    expect(array_column($data, 'name'))->toBe(['Shared office'])
        ->and($data[0]['records'])->toBe(['Visible document']);
});
