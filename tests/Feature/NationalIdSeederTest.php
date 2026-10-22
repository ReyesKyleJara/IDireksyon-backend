<?php

use App\Models\Agency;
use App\Models\ContentChangeLog;
use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\GovernmentIdApplicationStep;
use App\Models\GovernmentIdApplicationStepBlock;
use App\Models\Office;
use App\Models\User;
use Database\Seeders\NationalIdSeeder;

it('seeds structured National ID research using the existing guide and checklist services', function () {
    $this->seed(NationalIdSeeder::class);
    $id = GovernmentId::sole();
    expect($id->agency->acronym)->toBe('PSA')->and($id->requirementSets()->count())->toBe(5)
        ->and($id->fees()->count())->toBe(3)->and($id->last_verified_at)->toBeNull()
        ->and(Document::count())->toBe(0)->and(Office::count())->toBe(0);
    $filipino = $id->requirementSets()->where('applicant_type_custom', 'Filipino citizens')->sole();
    $primary = $filipino->groups->first();
    expect($primary->ways[0]->required_count)->toBe(2)->and($primary->ways[0]->items)->toHaveCount(2)
        ->and($primary->ways[1]->required_count)->toBe(1);
    $step = $filipino->applicationSteps->first();
    expect($step->title)->toBe('Prepare your supporting documents')
        ->and($step->blocks[0]->type)->toBe('heading')
        ->and($step->blocks[1]->type)->toBe('checklist')
        ->and($step->blocks[1]->content['items'][0]['body'])->toBeString();
    expect(GovernmentIdApplicationStepBlock::pluck('type')->unique()->sort()->values()->all())->toBe(['checklist', 'heading', 'note', 'text', 'warning']);
    $this->actingAs(User::factory()->create(['role' => 'researcher']));
    $this->get('/admin/government-ids/'.$id->id)->assertOk()->assertSee('Required Documents')->assertSee('Complete facial verification');
});

it('reuses an existing National ID and preserves edits and unrelated records on repeat runs', function () {
    $agency = Agency::create(['name' => 'Philippine Statistics Authority', 'acronym' => 'PSA']);
    $id = GovernmentId::create(['name' => 'National ID (PhilSys)', 'agency_id' => $agency->id, 'description' => 'Maintainer description', 'application_process' => 'Legacy instructions']);
    $other = GovernmentId::create(['name' => 'Unrelated ID', 'description' => 'Untouched']);
    $office = Office::create(['name' => 'Existing PSA office']);
    $id->offices()->attach($office->id);
    $this->seed(NationalIdSeeder::class);
    $step = $id->requirementSets()->first()->applicationSteps()->first();
    $step->update(['title' => 'Maintainer revised title']);
    $counts = [GovernmentIdApplicationStep::count(), GovernmentIdApplicationStepBlock::count(), ContentChangeLog::count()];
    $this->seed(NationalIdSeeder::class);
    expect(GovernmentId::count())->toBe(2)->and(Agency::count())->toBe(1)
        ->and($id->fresh()->description)->toBe('Maintainer description')
        ->and($id->fresh()->application_process)->toBe('Legacy instructions')
        ->and($id->offices()->pluck('offices.id')->all())->toBe([$office->id])
        ->and($other->fresh()->description)->toBe('Untouched')
        ->and($step->fresh()->title)->toBe('Maintainer revised title')
        ->and([GovernmentIdApplicationStep::count(), GovernmentIdApplicationStepBlock::count(), ContentChangeLog::count()])->toBe($counts);
});

it('preserves existing populated checklists and fees', function () {
    $id = GovernmentId::create(['name' => 'National ID']);
    $set = $id->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'custom', 'applicant_type_custom' => 'Filipino citizens']);
    $group = $set->groups()->create(['title' => 'Keep researched checklist', 'rule' => 'all']);
    $fee = $id->fees()->create(['label' => 'Keep researched fee', 'type' => 'free']);
    $this->seed(NationalIdSeeder::class);
    expect($set->groups()->pluck('id')->all())->toBe([$group->id])->and($id->fees()->pluck('id')->all())->toBe([$fee->id]);
});

it('stops ambiguous National ID matches without adding content', function () {
    GovernmentId::create(['name' => 'National ID']);
    GovernmentId::create(['name' => 'National ID (PhilSys)']);
    expect(fn () => $this->seed(NationalIdSeeder::class))->toThrow(RuntimeException::class, 'Multiple National ID');
    expect(Agency::count())->toBe(0)->and(GovernmentIdApplicationStep::count())->toBe(0);
});

it('rolls back the seed if the audit cannot be recorded', function () {
    $dispatcher = ContentChangeLog::getEventDispatcher();
    ContentChangeLog::setEventDispatcher(clone $dispatcher);
    ContentChangeLog::creating(function () { throw new RuntimeException('Audit unavailable'); });
    try {
        expect(fn () => $this->seed(NationalIdSeeder::class))->toThrow(RuntimeException::class, 'Audit unavailable');
        expect(GovernmentId::count())->toBe(0)->and(Agency::count())->toBe(0)->and(GovernmentIdApplicationStep::count())->toBe(0);
    } finally {
        ContentChangeLog::setEventDispatcher($dispatcher);
    }
});
