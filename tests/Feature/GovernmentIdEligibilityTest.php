<?php

use App\Models\GovernmentId;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['role' => 'researcher']));
});

it('saves structured eligibility and exposes the same readable string in both APIs', function () {
    $this->post('/admin/government-ids', [
        'name' => 'Eligibility example',
        'eligibility_age_type' => 'minimum',
        'eligibility_min_age' => '18',
        'eligibility_citizenship' => 'filipino',
        'eligibility_residency' => 'santa_maria',
        'eligibility_other_conditions' => 'First-time applicants only.',
    ])->assertSessionHasNoErrors();

    $id = GovernmentId::firstOrFail();
    $summary = "Age: 18 years old and above.\nCitizenship: Filipino citizen required.\nResidency: Santa Maria, Bulacan resident required.\nFirst-time applicants only.";
    expect($id->eligibility_min_age)->toBe(18)
        ->and($id->eligibility_max_age)->toBeNull()
        ->and($id->eligibility)->toBe($summary);
    $this->get('/admin/government-ids/'.$id->id)->assertOk()->assertSee($summary);
    $this->get('/admin/government-ids/'.$id->id.'/edit')->assertOk()
        ->assertSee('Age Requirement')->assertSee('First-time applicants only.');
    $this->getJson('/api/government-ids')->assertOk()->assertJsonPath('data.0.eligibility', $summary);
    $this->getJson('/api/government-ids/'.$id->id)->assertOk()->assertJsonPath('data.eligibility', $summary);
});

it('rejects incomplete or invalid eligibility without creating a record', function (array $fields, string $error) {
    $this->post('/admin/government-ids', ['name' => 'Invalid example'] + $fields)
        ->assertSessionHasErrors($error);
    expect(GovernmentId::count())->toBe(0);
})->with([
    'minimum is required' => [['eligibility_age_type' => 'minimum'], 'eligibility_min_age'],
    'age must be integer' => [['eligibility_age_type' => 'minimum', 'eligibility_min_age' => '18 years old'], 'eligibility_min_age'],
    'no fractional age' => [['eligibility_age_type' => 'minimum', 'eligibility_min_age' => 18.5], 'eligibility_min_age'],
    'no negative age' => [['eligibility_age_type' => 'minimum', 'eligibility_min_age' => -1], 'eligibility_min_age'],
    'upper bound' => [['eligibility_age_type' => 'minimum', 'eligibility_min_age' => 151], 'eligibility_min_age'],
    'range needs maximum' => [['eligibility_age_type' => 'range', 'eligibility_min_age' => 18], 'eligibility_max_age'],
    'reversed range' => [['eligibility_age_type' => 'range', 'eligibility_min_age' => 25, 'eligibility_max_age' => 18], 'eligibility_max_age'],
    'custom needs explanation' => [['eligibility_residency' => 'custom'], 'eligibility_residency_custom'],
    'unknown citizenship' => [['eligibility_citizenship' => 'anything'], 'eligibility_citizenship'],
    'unknown age mode' => [['eligibility_age_type' => 'maximum'], 'eligibility_age_type'],
    'unknown residency' => [['eligibility_residency' => 'barangay'], 'eligibility_residency'],
]);

it('clears unused ages and custom residency when selections change', function () {
    $this->post('/admin/government-ids', [
        'name' => 'Switching example',
        'eligibility_age_type' => 'range',
        'eligibility_min_age' => 18,
        'eligibility_max_age' => 25,
        'eligibility_residency' => 'custom',
        'eligibility_residency_custom' => 'Resident for at least six months.',
    ])->assertSessionHasNoErrors();
    $id = GovernmentId::firstOrFail();
    expect($id->eligibility)->toContain('18–25 years old (inclusive).')
        ->toContain('Resident for at least six months.');

    $this->put('/admin/government-ids/'.$id->id, [
        'name' => $id->name,
        'eligibility_age_type' => 'minimum',
        'eligibility_min_age' => 21,
        'eligibility_max_age' => 25,
        'eligibility_residency' => 'philippines',
        'eligibility_residency_custom' => 'Old custom wording',
    ])->assertSessionHasNoErrors();
    $id->refresh();
    expect($id->eligibility_min_age)->toBe(21)
        ->and($id->eligibility_max_age)->toBeNull()
        ->and($id->eligibility_residency_custom)->toBeNull()
        ->and($id->eligibility)->toBe("Age: 21 years old and above.\nResidency: Philippine resident required.");

    $this->put('/admin/government-ids/'.$id->id, [
        'name' => $id->name,
        'eligibility_age_type' => 'none',
        'eligibility_min_age' => 'stale input',
        'eligibility_max_age' => 'stale input',
        'eligibility_citizenship' => 'none',
        'eligibility_residency' => 'none',
    ])->assertSessionHasNoErrors();
    $id->refresh();
    expect($id->eligibility_min_age)->toBeNull()
        ->and($id->eligibility_max_age)->toBeNull()
        ->and($id->eligibility)->toContain('No age requirement.')
        ->toContain('No specific citizenship requirement.')
        ->toContain('No specific residency requirement.');
});

it('preserves legacy eligibility through the notes field without guessing rules', function () {
    $legacy = '18 yrs old; special exceptions apply.';
    $id = GovernmentId::create(['name' => 'Legacy example', 'eligibility' => $legacy]);
    $this->get('/admin/government-ids/'.$id->id.'/edit')->assertOk()
        ->assertSee('Existing eligibility text is preserved')->assertSee($legacy);
    $this->put('/admin/government-ids/'.$id->id, [
        'name' => $id->name,
        'eligibility_age_type' => '',
        'eligibility_citizenship' => '',
        'eligibility_residency' => '',
        'eligibility_other_conditions' => $legacy,
    ])->assertSessionHasNoErrors();
    $id->refresh();
    expect($id->eligibility)->toBe($legacy)
        ->and($id->eligibility_other_conditions)->toBe($legacy)
        ->and($id->eligibility_age_type)->toBeNull();
});

it('leaves unresearched eligibility blank and supports zero and equal age boundaries', function () {
    $this->get('/admin/government-ids/create')->assertOk()->assertSee('Other Eligibility Conditions');
    $this->post('/admin/government-ids', [
        'name' => 'Unknown example',
        'eligibility_age_type' => '',
        'eligibility_citizenship' => '',
        'eligibility_residency' => '',
        'eligibility_other_conditions' => '',
    ])->assertSessionHasNoErrors();
    $id = GovernmentId::firstOrFail();
    expect($id->eligibility)->toBeNull();
    $this->put('/admin/government-ids/'.$id->id, [
        'name' => $id->name,
        'eligibility_age_type' => 'range',
        'eligibility_min_age' => 0,
        'eligibility_max_age' => 0,
    ])->assertSessionHasNoErrors();
    expect($id->fresh()->eligibility)->toBe('Age: 0–0 years old (inclusive).');
});

it('redisplays invalid form input safely and retains valid selections', function () {
    $this->from('/admin/government-ids/create')->post('/admin/government-ids', [
        'name' => 'Retry example',
        'eligibility_age_type' => 'range',
        'eligibility_min_age' => 18,
        'eligibility_max_age' => 10,
        'eligibility_other_conditions' => ['invalid'],
    ])->assertSessionHasErrors(['eligibility_max_age', 'eligibility_other_conditions']);
    $this->get('/admin/government-ids/create')->assertOk()->assertSee('Retry example')
        ->assertSee('value="18"', false);
});
