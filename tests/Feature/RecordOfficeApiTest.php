<?php

use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\Office;

dataset('record office owners', [
    'PhilSys' => [GovernmentId::class, 'government-ids', 'PhilSys National ID'],
    'Passport' => [GovernmentId::class, 'government-ids', 'Philippine Passport'],
    'NBI' => [Document::class, 'documents', 'NBI Clearance'],
]);

it('publishes the record office relationship independently of request methods', function ($model, $resource, $name) {
    $owner = $model::create(['name' => $name]);
    $first = Office::create(['name' => 'Linked main office', 'address' => 'First saved address', 'status' => 'active', 'latitude' => 14.8, 'longitude' => 120.9]);
    $second = Office::create(['name' => 'Another linked office', 'status' => 'inactive']);
    $draft = Office::create(['name' => 'Draft linked office', 'status' => 'draft']);
    $unrelated = Office::create(['name' => 'Unrelated active office', 'status' => 'active']);
    $owner->offices()->attach($first, ['service_notes' => 'Record-specific service note', 'new_application_status' => 'available']);
    $owner->offices()->attach([$second->id, $draft->id]);
    $schedule = $first->schedules()->create(['day_of_week' => 1, 'status' => 'open']);
    $schedule->intervals()->create(['opens_at' => '08:00:00', 'closes_at' => '12:00:00']);
    $inPerson = $owner->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'all',
        'request_channel' => ['label' => 'In person', 'office_ids' => [$first->id]]]);
    $online = $owner->requirementSets()->create(['application_type' => 'copy', 'applicant_type' => 'all',
        'request_channel' => ['label' => 'Online', 'provider' => 'Saved provider', 'office_ids' => []]]);

    $data = $this->getJson('/api/'.$resource.'/'.$owner->id)->assertOk()->json('data');
    expect(array_column($data['offices'], 'id'))->toEqualCanonicalizing([$first->id, $second->id, $draft->id]);
    $published = collect($data['offices'])->firstWhere('id', $first->id);
    expect($published['address'])->toBe('First saved address')
        ->and((float) $published['latitude'])->toBe(14.8)
        ->and((float) $published['longitude'])->toBe(120.9)
        ->and($published['service_notes'])->toBe('Record-specific service note')
        ->and($published['services']['new_application_status'])->toBe('available')
        ->and($published['schedules'][0]['intervals'])->toHaveCount(1);
    $paths = collect($data['request_paths'])->keyBy('id');
    expect(array_column($paths[$inPerson->id]['offices'], 'id'))->toBe([$first->id])
        ->and($paths[$online->id]['offices'])->toBe([])
        ->and($paths[$online->id]['provider'])->toBe('Saved provider');

    // Removing all channel office references does not change the main relationship.
    $inPerson->update(['request_channel' => ['label' => 'In person', 'office_ids' => []]]);
    $after = $this->getJson('/api/'.$resource.'/'.$owner->id)->assertOk()->json('data');
    expect($after['offices'])->toBe($data['offices'])
        ->and($owner->fresh()->offices)->toHaveCount(3)
        ->and($unrelated->exists)->toBeTrue();
})->with('record office owners');

it('publishes direct offices even without any request method', function ($model, $resource, $name) {
    $owner = $model::create(['name' => $name]);
    $office = Office::create(['name' => 'Direct record office', 'status' => 'active']);
    $owner->offices()->attach($office);
    $this->getJson('/api/'.$resource.'/'.$owner->id)->assertOk()
        ->assertJsonPath('data.offices.0.id', $office->id)
        ->assertJsonPath('data.request_paths', []);
})->with('record office owners');

it('preserves office visibility controls without deleting direct links', function ($model, $resource, $name) {
    $owner = $model::create(['name' => $name, 'research_metadata' => ['hidden_sections' => ['offices']]]);
    $office = Office::create(['name' => 'Retained hidden office', 'status' => 'active']);
    $owner->offices()->attach($office);
    $owner->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'all']);
    $this->getJson('/api/'.$resource.'/'.$owner->id)->assertOk()
        ->assertJsonPath('data.offices', [])
        ->assertJsonPath('data.request_paths.0.offices', []);
    expect($owner->fresh()->offices)->toHaveCount(1);
})->with('record office owners');
