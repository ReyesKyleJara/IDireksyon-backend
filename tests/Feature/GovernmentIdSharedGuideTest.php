<?php

use App\Models\GovernmentId;
use App\Models\User;
use App\Services\ApplicabilityEvaluator;
use App\Services\GovernmentIdApplicationGuide;
use App\Support\ApplicantContext;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['role' => 'researcher']));
    $this->target = GovernmentId::create(['name' => 'Shared guide ID', 'application_process' => 'Older instructions']);
    $this->adult = $this->target->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'adult']);
    $this->minor = $this->target->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'minor']);
    $this->renewal = $this->target->requirementSets()->create(['application_type' => 'renewal', 'applicant_type' => 'all']);
    $this->guide = app(GovernmentIdApplicationGuide::class);
    $this->shared = $this->target->applicationSteps()->create(['title' => 'Book appointment', 'sort_order' => 0]);
    $this->renewalStep = $this->renewal->applicationSteps()->create(['title' => 'Bring old passport', 'sort_order' => 2]);
});

it('combines shared steps with the same profiles used by requirements', function (int $age) {
    $profile = $age >= 18 ? $this->adult : $this->minor;
    $specific = $profile->applicationSteps()->create(['title' => 'Prepare applicant documents', 'sort_order' => 1]);
    $context = new ApplicantContext($age, 'new');
    $result = $this->guide->applicableSteps($this->target, $context);
    expect((new ApplicabilityEvaluator)->profileApplies($profile, $context))->toBeTrue()
        ->and(array_column($result['steps'], 'id'))->toBe([$this->shared->id, $specific->id])
        ->and($result['requires_manual_selection'])->toBeFalse()
        ->and($specific->fresh()->requirement_set_id)->toBe($profile->id);
})->with([18, 17]);

it('includes renewal steps only for renewal', function () {
    $result = $this->guide->applicableSteps($this->target, new ApplicantContext(30, 'renewal'));
    expect(array_column($result['steps'], 'id'))->toBe([$this->shared->id, $this->renewalStep->id]);
    $new = $this->guide->applicableSteps($this->target, new ApplicantContext(30, 'new'));
    expect(array_column($new['steps'], 'id'))->toBe([$this->shared->id]);
});

it('orders by explicit position then shared first and stable profile and step ties', function () {
    $specific = $this->adult->applicationSteps()->create(['title' => 'Specific at zero', 'sort_order' => 0]);
    $lateShared = $this->target->applicationSteps()->create(['title' => 'Common ending', 'sort_order' => 10]);
    $result = $this->guide->applicableSteps($this->target, new ApplicantContext(20, 'new'));
    expect(array_column($result['steps'], 'id'))->toBe([$this->shared->id, $specific->id, $lateShared->id])
        ->and(array_column($this->guide->stepsForProfile($this->target, $this->adult->id), 'id'))->toBe([$this->shared->id, $specific->id, $lateShared->id]);
});

it('returns unresolved and incomplete content separately and retains manual access and blocks', function () {
    $custom = $this->target->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'custom', 'applicant_type_custom' => 'Students', 'min_age' => 18]);
    $step = $custom->applicationSteps()->create(['title' => 'Student instructions']);
    $block = $step->blocks()->create(['type' => 'text', 'content' => ['body' => 'Keep this content']]);
    $result = $this->guide->applicableSteps($this->target, new ApplicantContext(20, 'new'));
    expect(array_column($result['steps'], 'id'))->toBe([$this->shared->id])
        ->and(array_column($result['pending_steps'], 'id'))->toBe([$step->id])
        ->and($result['requires_manual_selection'])->toBeTrue()
        ->and(array_column($result['pending_profiles'], 'id'))->toBe([$custom->id]);
    $manual = $this->guide->stepsForProfile($this->target, $custom->id);
    expect(array_column($manual, 'id'))->toContain($step->id)
        ->and(collect($manual)->firstWhere('id', $step->id)->blocks->sole()->id)->toBe($block->id);
    $incomplete = $this->guide->applicableSteps($this->target, new ApplicantContext);
    expect(array_column($incomplete['pending_steps'], 'id'))->toContain($this->renewalStep->id, $step->id);
});

it('saves explicit shared steps through CMS and preserves omitted profiles', function () {
    $url = '/admin/government-ids/'.$this->target->id;
    $this->put($url, ['name' => $this->target->name, 'application_guide_payload' => json_encode([
        ['everyone' => true, 'steps' => [['id' => $this->shared->id, 'title' => 'Common appointment', 'type' => 'general', 'sort_order' => 1, 'blocks' => []]]],
    ])])->assertSessionHasNoErrors();
    expect($this->shared->fresh()->requirement_set_id)->toBeNull()
        ->and($this->shared->fresh()->government_id_id)->toBe($this->target->id);
    $this->assertModelExists($this->renewalStep);
    $this->get($url)->assertOk()->assertSee('Everyone')->assertSee('Common appointment')->assertDontSee('Older instructions');
    $this->get($url.'/edit')->assertOk()->assertSee('Applies To')->assertDontSee('+ Add scenario');
});

it('moves a step to Everyone without changing its identity or blocks', function () {
    $block = $this->renewalStep->blocks()->create(['type' => 'text', 'content' => ['body' => 'Preserved']]);
    $step = ['id' => $this->renewalStep->id, 'title' => 'Bring old passport', 'type' => 'general', 'blocks' => [$block->only(['id', 'type', 'section_title', 'content'])]];
    $this->put('/admin/government-ids/'.$this->target->id, ['name' => $this->target->name, 'application_guide_payload' => json_encode([
        ['id' => $this->renewal->id, 'steps' => []],
        ['everyone' => true, 'steps' => [$step]],
    ])])->assertSessionHasNoErrors();
    expect($this->renewalStep->fresh()->requirement_set_id)->toBeNull()
        ->and($block->fresh()->content)->toBe(['body' => 'Preserved']);
});

it('protects profiles and shared-only parent IDs from deletion', function () {
    $url = '/admin/government-ids/'.$this->target->id;
    $this->deleteJson($url.'/requirement-sets/'.$this->renewal->id)->assertUnprocessable();
    $this->deleteJson($url.'/checklists/'.$this->renewal->id)->assertUnprocessable();
    $this->renewalStep->delete();
    $this->delete($url)->assertRedirect($url)->assertSessionHas('error');
    $this->assertModelExists($this->target);
    $this->assertModelExists($this->shared);
});

it('rejects cross ID ownership and shared-step adoption', function () {
    $other = GovernmentId::create(['name' => 'Other owner']);
    $step = $other->applicationSteps()->create(['title' => 'Other common step']);
    $this->putJson('/admin/government-ids/'.$this->target->id, ['name' => 'Must not save', 'application_guide_payload' => json_encode([
        ['everyone' => true, 'steps' => [['id' => $step->id, 'title' => 'Adopt', 'type' => 'general', 'blocks' => []]]],
    ])])->assertUnprocessable();
    expect($this->target->fresh()->name)->toBe('Shared guide ID');
});

it('creates a shared-only guide without creating any applicability profiles', function () {
    $this->post('/admin/government-ids', [
        'name' => 'Common guide only',
        'application_guide_payload' => json_encode([['everyone' => true, 'steps' => [
            ['title' => 'Book appointment', 'type' => 'general', 'blocks' => []],
        ]]]),
    ])->assertSessionHasNoErrors();
    $owner = GovernmentId::where('name', 'Common guide only')->sole();
    expect($owner->requirementSets()->count())->toBe(0)
        ->and($owner->applicationSteps()->sole()->requirement_set_id)->toBeNull();
    $this->get('/admin/government-ids/'.$owner->id)->assertOk()->assertSee('Everyone')->assertSee('Book appointment');
});

it('does not build new Government ID profiles from guide-side descriptive fields', function () {
    $this->putJson('/admin/government-ids/'.$this->target->id, [
        'name' => $this->target->name,
        'application_guide_payload' => json_encode([[
            'application_type' => 'replacement', 'applicant_type' => 'custom', 'applicant_type_custom' => 'Students',
            'steps' => [['title' => 'Student guide', 'type' => 'general', 'blocks' => []]],
        ]]),
    ])->assertUnprocessable();
    expect($this->target->requirementSets()->count())->toBe(3)
        ->and($this->target->applicationSteps()->count())->toBe(2);
});

it('guards shared-step rollback and backfills existing profile steps without losing data', function () {
    // DDL needs a separate connection outside RefreshDatabase's transaction.
    config(['database.connections.guide_migration' => config('database.connections.sqlite')]);
    $previous = DB::getDefaultConnection();
    DB::setDefaultConnection('guide_migration');
    try {
        $this->artisan('migrate', ['--database' => 'guide_migration', '--force' => true])->assertSuccessful();
        auth()->logout();
        $owner = GovernmentId::create(['name' => 'Migration owner']);
        $profile = $owner->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'adult']);
        $shared = $owner->applicationSteps()->create(['title' => 'Shared']);
        $specific = $profile->applicationSteps()->create(['title' => 'Preserved', 'sort_order' => 7]);
        $block = $specific->blocks()->create(['type' => 'text', 'content' => ['body' => 'Before migration']]);
        $migration = require database_path('migrations/2026_10_21_000000_support_shared_government_id_guide_steps.php');
        expect(fn () => $migration->down())->toThrow(RuntimeException::class, 'Rollback stopped');
        $shared->delete();
        $before = $specific->fresh()->getAttributes();
        $migration->down();
        $migration->up();
        expect($specific->fresh()->getAttributes())->toBe($before)
            ->and($block->fresh()->content)->toBe(['body' => 'Before migration']);
        expect(DB::table('government_id_application_steps')->count())->toBe(1)
            ->and(DB::select('PRAGMA foreign_key_check'))->toBe([]);
    } finally {
        DB::setDefaultConnection($previous);
        DB::purge('guide_migration');
    }
});
