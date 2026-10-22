<?php

use App\Models\GovernmentId;
use App\Models\Office;
use Illuminate\Support\Facades\DB;

it('exposes only associated offices and ordered scenario guide content without changing old responses', function () {
    $id = GovernmentId::create(['name' => 'Example ID', 'eligibility_age_type' => 'minimum', 'eligibility_min_age' => 0]);
    $other = GovernmentId::create(['name' => 'Other']);
    $office = Office::create(['name' => 'Linked office', 'address' => 'CMS address', 'latitude' => 14.8, 'longitude' => 120.9]);
    Office::create(['name' => 'Unrelated office']);
    $id->offices()->attach($office, ['service_notes' => 'Confirm appointment availability']);
    $day = $office->schedules()->create(['day_of_week' => 1, 'status' => 'open']);
    $day->intervals()->create(['opens_at' => '08:00', 'closes_at' => '12:00']);
    $set = $id->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'adult']);
    $later = $set->applicationSteps()->create(['title' => 'Later', 'sort_order' => 2]);
    $first = $set->applicationSteps()->create(['title' => 'First', 'short_description' => 'CMS instructions', 'sort_order' => 0]);
    $block = $first->blocks()->create(['type' => 'official_link', 'content' => ['label' => 'Official link', 'url' => 'https://example.gov.ph/']]);
    $before = $this->getJson('/api/government-ids/'.$id->id)->assertOk()->json();
    $queryCount = 0;
    DB::listen(function ($query) use (&$queryCount) { if (str_starts_with(strtolower($query->sql), 'select')) $queryCount++; });
    $result = $this->getJson('/api/government-ids/'.$id->id.'/content')->assertOk()
        ->assertJsonPath('data.id', $id->id)->assertJsonCount(1, 'data.offices')
        ->assertJsonPath('data.offices.0.id', $office->id)
        ->assertJsonPath('data.offices.0.schedules.0.intervals.0.opens_at', '08:00')
        ->assertJsonPath('data.eligibility_details.eligibility_min_age', 0)
        ->assertJsonPath('data.application_guide.0.id', $set->id)
        ->assertJsonPath('data.application_guide.0.steps.0.id', $first->id)
        ->assertJsonPath('data.application_guide.0.steps.1.id', $later->id)
        ->assertJsonPath('data.application_guide.0.steps.0.blocks.0.id', $block->id)
        ->assertJsonMissingPath('data.offices.0.pivot')->json('data');
    expect($queryCount)->toBeLessThan(12);
    expect(json_encode($result))->not->toContain('last_verified_by', 'password');
    expect($this->getJson('/api/government-ids/'.$id->id)->json())->toBe($before);
    $this->getJson('/api/government-ids/'.$other->id.'/content')->assertOk()->assertJsonPath('data.offices', [])->assertJsonPath('data.application_guide', []);
    $this->getJson('/api/government-ids/999999/content')->assertNotFound();
});
