<?php

use App\Models\ContentChangeLog;
use App\Models\GovernmentId;
use App\Models\GovernmentIdRequirementSet;
use App\Models\User;
use App\Services\ApplicabilityEvaluator;
use App\Support\ApplicabilityProfileSummary;
use App\Support\ApplicantContext;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['role' => 'researcher', 'name' => 'Set Researcher']));
    $this->governmentId = GovernmentId::create(['name' => 'Example ID', 'requirements' => 'Existing requirements.']);
    $this->setsUrl = '/admin/government-ids/'.$this->governmentId->id.'/requirement-sets';
});

it('shows structured applicability and updates matching without replacing records', function () {
    $set = $this->governmentId->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'adult']);
    $group = $set->groups()->create(['rule' => 'choose_one', 'condition_type' => 'always']);
    $item = $group->items()->create(['type' => 'custom', 'custom_name' => 'Existing proof']);
    $evaluator = new ApplicabilityEvaluator;
    $context = new ApplicantContext(20, 'new');
    $this->get($this->setsUrl)->assertOk()->assertSee('Adult · New Application · Age 18+')
        ->assertSee('Structured profile')->assertSee('Used by 1 requirement groups');
    expect($evaluator->needsRuleConfiguration($set))->toBeFalse()
        ->and($evaluator->profileApplies($set, $context))->toBeTrue();
    $this->put($this->setsUrl.'/'.$set->id, ['application_type' => 'renewal', 'applicant_type' => 'minor'])
        ->assertSessionHasNoErrors();
    expect($evaluator->profileApplies($set->fresh(), $context))->toBeFalse()
        ->and($evaluator->profileApplies($set->fresh(), new ApplicantContext(17, 'renewal')))->toBeTrue()
        ->and($group->fresh()->rule)->toBe('choose_one')
        ->and($item->fresh()->custom_name)->toBe('Existing proof')
        ->and($this->governmentId->requirementSets()->sole()->id)->toBe($set->id);
});

it('keeps custom content visible editable and manual without label inference', function () {
    $set = $this->governmentId->requirementSets()->create([
        'application_type' => 'new', 'applicant_type' => 'custom',
        'applicant_type_custom' => 'Adult', 'min_age' => 18, 'max_age' => 25,
    ]);
    $group = $set->groups()->create(['rule' => 'all', 'condition_type' => 'custom', 'condition_custom' => 'Other situation']);
    $item = $group->items()->create(['type' => 'custom', 'custom_name' => 'Preserved proof']);
    $step = $set->applicationSteps()->create(['title' => 'Preserved guide']);
    $this->get($this->setsUrl)->assertOk()->assertSee('Needs rule setup')->assertSee('Ages 18–25')
        ->assertSee('Manual selection remains available')->assertSee('1 Application Guide steps');
    $this->get($this->setsUrl.'/'.$set->id.'/edit')->assertOk()->assertSee('Needs rule setup')->assertSee('Adult');
    $this->get('/admin/government-ids/'.$this->governmentId->id.'/edit')->assertOk()
        ->assertViewHas('checklists', fn ($rows) => $rows[0]['id'] === $set->id
            && $rows[0]['applicability']['needs_rule_setup'] === true
            && $rows[0]['groups'][0]['items'][0]['id'] === $item->id)
        ->assertSee('Choose an existing applicability profile to edit');
    $this->put($this->setsUrl.'/'.$set->id, [
        'application_type' => 'new', 'applicant_type' => 'custom',
        'applicant_type_custom' => 'Students', 'min_age' => 18, 'max_age' => 25,
    ])->assertSessionHasNoErrors();
    expect((new ApplicabilityEvaluator)->profileApplies($set->fresh(), new ApplicantContext(20, 'new')))->toBeNull();
    $this->assertModelExists($group);
    $this->assertModelExists($item);
    $this->assertModelExists($step);
    expect($group->fresh()->condition_custom)->toBe('Other situation');
});

it('shows inclusive custom age limits including zero without implying automatic matching', function ($minimum, $maximum, string $label) {
    $set = $this->governmentId->requirementSets()->create([
        'application_type' => 'new', 'applicant_type' => 'custom',
        'min_age' => $minimum, 'max_age' => $maximum,
    ]);
    $summary = ApplicabilityProfileSummary::forProfile($set);
    expect($summary['summary'])->toContain($label)
        ->and($summary['needs_rule_setup'])->toBeTrue();
    $this->get($this->setsUrl)->assertOk()->assertSee($label)->assertSee('Needs rule setup');
})->with([[0, 0, 'Ages 0–0'], [21, null, 'Age 21+'], [null, 12, 'Age 12 and below'], [null, null, 'No age restriction']]);

it('preserves guide deletion protection on both CMS deletion routes', function () {
    $set = $this->governmentId->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'adult']);
    $step = $set->applicationSteps()->create(['title' => 'Keep this guide']);
    $this->delete($this->setsUrl.'/'.$set->id)->assertSessionHasErrors('application_guide');
    $this->deleteJson('/admin/government-ids/'.$this->governmentId->id.'/checklists/'.$set->id)
        ->assertUnprocessable()->assertJsonValidationErrors('application_guide');
    $this->assertModelExists($set);
    $this->assertModelExists($step);
});

it('renders the entry point and creates multiple sets for one ID', function () {
    $this->get('/admin/government-ids/'.$this->governmentId->id.'/edit')->assertOk()->assertSee('Add checklist');
    $this->get($this->setsUrl)->assertOk()->assertSee('No applicability profiles yet.');
    $this->get($this->setsUrl.'/create')->assertOk()->assertSee('Application type?')->assertSee('Who is applying?');
    foreach (['adult', 'minor'] as $applicant) {
        $this->post($this->setsUrl, ['application_type' => 'new', 'applicant_type' => $applicant])
            ->assertSessionHasNoErrors()->assertRedirect($this->setsUrl);
    }
    $this->get($this->setsUrl)->assertOk()->assertSee('Adult · New Application · Age 18+')->assertSee('Minor · New Application · Below 18');
    expect($this->governmentId->requirementSets()->count())->toBe(2)
        ->and($this->governmentId->fresh()->requirements)->toBe('Existing requirements.');
    $set = $this->governmentId->requirementSets()->firstOrFail();
    $this->get($this->setsUrl.'/'.$set->id.'/edit')->assertOk()->assertSee('Edit Applicability Profile');
});

it('updates custom ages and clears unused values when switching to a preset', function () {
    $this->post($this->setsUrl, [
        'application_type' => 'custom', 'application_type_custom' => ' Special case ',
        'applicant_type' => 'custom', 'applicant_type_custom' => 'Students',
        'min_age' => 12, 'max_age' => 21,
    ])->assertSessionHasNoErrors();
    $set = $this->governmentId->requirementSets()->sole();
    expect($set->display_label)->toBe('Students (Ages 12–21) • Special case');
    $this->put($this->setsUrl.'/'.$set->id, [
        'application_type' => 'renewal', 'applicant_type' => 'adult',
        'min_age' => 'stale', 'max_age' => ['stale'],
    ])->assertSessionHasNoErrors()->assertRedirect($this->setsUrl);
    $set->refresh();
    expect($set->min_age)->toBe(18)->and($set->max_age)->toBeNull()
        ->and($set->application_type_custom)->toBeNull()->and($set->applicant_type_custom)->toBeNull();
    $this->put($this->setsUrl.'/'.$set->id, [
        'application_type' => 'renewal', 'applicant_type' => 'custom',
        'min_age' => '', 'max_age' => '',
    ])->assertSessionHasNoErrors();
    expect($set->fresh()->min_age)->toBeNull()->and($set->fresh()->max_age)->toBeNull();
});

it('rejects invalid form values without saving or auditing a set', function (array $input, string $error) {
    $before = ContentChangeLog::count();
    $this->post($this->setsUrl, array_replace([
        'application_type' => 'new', 'applicant_type' => 'custom',
    ], $input))->assertSessionHasErrors($error);
    expect(GovernmentIdRequirementSet::count())->toBe(0)
        ->and(ContentChangeLog::count())->toBe($before);
})->with([
    [['application_type' => 'invalid'], 'application_type'],
    [['applicant_type' => 'invalid'], 'applicant_type'],
    [['application_type' => 'custom', 'application_type_custom' => ' '], 'application_type_custom'],
    [['min_age' => -1], 'min_age'],
    [['min_age' => 18.5], 'min_age'],
    [['min_age' => 30, 'max_age' => 18], 'max_age'],
    [['max_age' => 65536], 'max_age'],
    [['government_id_id' => 999999], 'government_id_id'],
]);

it('safely redisplays malformed input and preserves valid selections', function () {
    $this->from($this->setsUrl.'/create')->post($this->setsUrl, [
        'application_type' => 'custom', 'application_type_custom' => ['invalid'],
        'applicant_type' => 'custom', 'applicant_type_custom' => ['invalid'],
        'min_age' => 18, 'max_age' => ['invalid'],
    ])->assertSessionHasErrors(['application_type_custom', 'applicant_type_custom', 'max_age']);
    $this->get($this->setsUrl.'/create')->assertOk()->assertSee('Please check')->assertSee('value="18"', false);
});

it('rejects an invalid update without changing the saved set', function () {
    $set = $this->governmentId->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'adult']);
    $this->put($this->setsUrl.'/'.$set->id, [
        'application_type' => 'renewal', 'applicant_type' => 'custom', 'min_age' => 25, 'max_age' => 18,
    ])->assertSessionHasErrors('max_age');
    expect($set->fresh()->application_type)->toBe('new')->and($set->fresh()->min_age)->toBe(18);
});

it('only edits or deletes sets belonging to the ID in the URL', function () {
    $other = GovernmentId::create(['name' => 'Other ID']);
    $set = $other->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'all']);
    $this->get($this->setsUrl.'/'.$set->id.'/edit')->assertNotFound();
    $this->put($this->setsUrl.'/'.$set->id, ['application_type' => 'renewal', 'applicant_type' => 'adult'])->assertNotFound();
    $this->delete($this->setsUrl.'/'.$set->id)->assertNotFound();
    $this->assertModelExists($set);
    expect($set->fresh()->application_type)->toBe('new');
});

it('records set changes in ID edit history without logging unchanged saves', function () {
    $before = ContentChangeLog::count();
    $this->post($this->setsUrl, ['application_type' => 'new', 'applicant_type' => 'adult'])->assertSessionHasNoErrors();
    $set = $this->governmentId->requirementSets()->sole();
    expect(ContentChangeLog::count())->toBe($before + 1);
    $this->put($this->setsUrl.'/'.$set->id, ['application_type' => 'new', 'applicant_type' => 'adult'])->assertSessionHasNoErrors();
    expect(ContentChangeLog::count())->toBe($before + 1);
    $this->put($this->setsUrl.'/'.$set->id, ['application_type' => 'renewal', 'applicant_type' => 'minor'])->assertSessionHasNoErrors();
    $log = ContentChangeLog::latest('id')->firstOrFail();
    expect($log->entity_type)->toBe('government_id')
        ->and((int) $log->entity_id)->toBe($this->governmentId->id)
        ->and($log->changed_fields)->toBe(['requirement_sets'])
        ->and((int) $log->user_id)->toBe(auth()->id());
    $this->get('/admin/government-ids/'.$this->governmentId->id)->assertOk()->assertSee('Last edited by')->assertSee('Set Researcher');
    $this->delete($this->setsUrl.'/'.$set->id)->assertRedirect($this->setsUrl);
    $this->assertModelMissing($set);
    $this->assertModelExists($this->governmentId);
    expect(ContentChangeLog::count())->toBe($before + 3);
});

it('protects every action from guests residents and inactive staff', function () {
    $set = $this->governmentId->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'all']);
    $actions = [
        ['GET', $this->setsUrl], ['GET', $this->setsUrl.'/create'],
        ['GET', $this->setsUrl.'/'.$set->id.'/edit'], ['POST', $this->setsUrl],
        ['PUT', $this->setsUrl.'/'.$set->id], ['DELETE', $this->setsUrl.'/'.$set->id],
    ];
    auth()->logout();
    foreach ($actions as [$method, $url]) {
        $this->call($method, $url)->assertRedirect('/login');
    }
    foreach ([['role' => 'resident'], ['role' => 'researcher', 'is_active' => false]] as $attributes) {
        $this->actingAs(User::factory()->create($attributes));
        foreach ($actions as [$method, $url]) {
            $this->call($method, $url)->assertForbidden();
        }
    }
    $this->assertModelExists($set);
    expect(GovernmentIdRequirementSet::count())->toBe(1);
});

it('rolls back the set if its audit entry cannot be saved', function () {
    $this->withoutExceptionHandling();
    $dispatcher = ContentChangeLog::getEventDispatcher();
    ContentChangeLog::setEventDispatcher(clone $dispatcher);
    ContentChangeLog::creating(function () {
        throw new RuntimeException('Simulated audit failure');
    });
    try {
        $before = ContentChangeLog::count();
        expect(fn () => $this->post($this->setsUrl, [
            'application_type' => 'new', 'applicant_type' => 'adult',
        ]))->toThrow(RuntimeException::class, 'Simulated audit failure');
        expect(GovernmentIdRequirementSet::count())->toBe(0)
            ->and(ContentChangeLog::count())->toBe($before);
    } finally {
        ContentChangeLog::setEventDispatcher($dispatcher);
    }
});
