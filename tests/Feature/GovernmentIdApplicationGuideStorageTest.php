<?php

use App\Models\GovernmentId;
use App\Models\GovernmentIdApplicationStep;
use App\Models\GovernmentIdApplicationStepBlock;
use App\Models\User;
use Illuminate\Database\QueryException;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['role' => 'researcher']));
    $this->idRecord = GovernmentId::create(['name' => 'Guide example', 'application_process' => 'Existing guide']);
    $this->scenario = $this->idRecord->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'adult']);
});

it('stores ordered steps and information blocks under their scenario without changing legacy API fields', function () {
    $before = \Illuminate\Support\Arr::except($this->getJson('/api/government-ids/'.$this->idRecord->id)->assertOk()->json('data'), ['requirement_sets']);
    $later = $this->scenario->applicationSteps()->create(['title' => 'Visit office', 'type' => 'office_visit', 'sort_order' => 1]);
    $first = $this->scenario->applicationSteps()->create(['title' => 'Prepare requirements', 'sort_order' => 0]);
    $first->blocks()->create(['type' => 'requirements', 'section_title' => 'Your requirements', 'content' => [], 'sort_order' => 1]);
    $first->blocks()->create(['type' => 'instructions', 'content' => ['items' => ['Prepare before visiting']], 'sort_order' => 0]);
    expect($this->scenario->applicationSteps->pluck('id')->all())->toBe([$first->id, $later->id])
        ->and($first->blocks->pluck('type')->all())->toBe(['instructions', 'requirements'])
        ->and($first->blocks->last()->content)->toBe([]);
    $other = $this->idRecord->requirementSets()->create(['application_type' => 'renewal', 'applicant_type' => 'adult']);
    expect($other->applicationSteps()->count())->toBe(0);
    expect(\Illuminate\Support\Arr::except($this->getJson('/api/government-ids/'.$this->idRecord->id)->assertOk()->json('data'), ['requirement_sets']))->toBe($before);
});

it('protects guides through both scenario deletion endpoints and parent ID deletion', function () {
    $step = $this->scenario->applicationSteps()->create(['title' => 'Keep this step']);
    $base = '/admin/government-ids/'.$this->idRecord->id;
    $this->deleteJson($base.'/checklists/'.$this->scenario->id)->assertUnprocessable()->assertJsonValidationErrors('application_guide');
    $this->deleteJson($base.'/requirement-sets/'.$this->scenario->id)->assertUnprocessable()->assertJsonValidationErrors('application_guide');
    $this->delete($base)->assertRedirect($base)->assertSessionHas('error');
    $this->assertModelExists($step);
    $this->assertModelExists($this->scenario);
    $this->assertModelExists($this->idRecord);
});

it('restricts direct scenario deletion in the database', function () {
    $this->scenario->applicationSteps()->create(['title' => 'Keep']);
    expect(fn () => $this->scenario->delete())->toThrow(QueryException::class);
});

it('deletes only the selected step and its blocks', function () {
    $one = $this->scenario->applicationSteps()->create(['title' => 'First']);
    $two = $this->scenario->applicationSteps()->create(['title' => 'Second']);
    $one->blocks()->create(['type' => 'fees', 'content' => []]);
    $one->delete();
    expect(GovernmentIdApplicationStepBlock::count())->toBe(0)
        ->and(GovernmentIdApplicationStep::count())->toBe(1);
    $this->assertModelExists($two);
    $this->assertModelExists($this->scenario);
});

it('stops rollback before removing saved guides', function () {
    $step = $this->scenario->applicationSteps()->create(['title' => 'Preserve']);
    $migration = require database_path('migrations/2026_10_06_000000_create_government_id_application_guide_tables.php');
    expect(fn () => $migration->down())->toThrow(RuntimeException::class, 'Rollback stopped');
    $this->assertModelExists($step);
});
