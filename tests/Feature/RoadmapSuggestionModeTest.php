<?php

use App\Models\ContentChangeLog;
use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\ResidentJourney;
use App\Models\User;
use App\Services\CatalogPrerequisites;
use App\Services\RoadmapPlanner;
use Illuminate\Support\Facades\DB;

/** Neutral records on purpose: these rules must hold for any ID or document. */
function modeSet($owner, array $attributes = [])
{
    return $owner->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'all', ...$attributes]);
}

function modeGroup($set, array $items, array $way = [], array $group = [])
{
    $row = $set->groups()->create(['rule' => 'all', 'title' => 'Accepted proof', ...$group]);
    $saved = $row->ways()->create(['required_count' => 1, 'qualification_type' => 'none', ...$way]);
    foreach ($items as $item) {
        $link = is_string($item) ? ['type' => 'custom', 'custom_name' => $item]
            : ($item instanceof Document ? ['type' => 'document', 'document_id' => $item->id]
                : ['type' => 'government_id', 'government_id_id' => $item->id]);
        $model = $row->items()->make($link);
        $model->requirement_way_id = $saved->id;
        $model->save();
    }

    return $row;
}

function modeStep(array $plan, string $type = 'unresolved_requirement'): ?array
{
    return collect($plan['steps'])->firstWhere('type', $type);
}

function modeCredential(string $name): GovernmentId
{
    return GovernmentId::create(['name' => $name, 'application_process' => 'Documented steps']);
}

it('recognises an owned qualified item as self-reported and asks the resident to confirm it', function () {
    $resident = User::factory()->create();
    [$target, $a, $b] = [GovernmentId::create(['name' => 'Target']), modeCredential('Card A'), modeCredential('Card B')];
    modeGroup(modeSet($target), [$a, $b], ['qualification_type' => 'unexpired']);
    $resident->ownedGovernmentIds()->attach($a);

    $step = modeStep(app(RoadmapPlanner::class)->build($resident, [$target->id]));

    expect($step['manual_preparation'])->toBeTrue()->and($step['suggestions'])->toBe([])
        ->and($step['confirmation']['owned_names'])->toBe(['Card A'])
        ->and($step['message'])->toContain('Card A')->toContain('still valid');
});

it('offers accepted alternatives without making every accepted item mandatory', function () {
    $resident = User::factory()->create();
    [$target, $a, $b] = [GovernmentId::create(['name' => 'Target']), modeCredential('Card A'), modeCredential('Card B')];
    modeGroup(modeSet($target), [$a, $b], ['qualification_type' => 'unexpired']);

    $plan = app(RoadmapPlanner::class)->build($resident, [$target->id]);

    expect(array_column($plan['steps'], 'name'))->toBe(['Accepted proof', 'Target'])
        ->and(array_column(modeStep($plan)['suggestions'], 'name'))->toBe(['Card A', 'Card B']);
});

it('keeps custom qualifications for manual review and never interprets them', function () {
    $resident = User::factory()->create();
    [$target, $a] = [GovernmentId::create(['name' => 'Target']), modeCredential('Card A')];
    modeGroup(modeSet($target), [$a, modeCredential('Card B')], ['qualification_type' => 'custom', 'qualification_custom' => 'Different card']);
    $resident->ownedGovernmentIds()->attach($a);

    $plan = app(RoadmapPlanner::class)->build($resident, [$target->id]);

    expect(array_column($plan['steps'], 'name'))->toBe(['Target'])
        ->and($plan['steps'][0]['prerequisites'][0]['manual_kind'])->toBe('review');
});

it('classifies tasks and conditional items without adding acquisition steps', function () {
    $resident = User::factory()->create();
    $target = GovernmentId::create(['name' => 'Target']);
    $set = modeSet($target);
    $booking = Document::create(['name' => 'Booking slip', 'include_in_smart_sequencing' => false]);
    modeGroup($set, [$booking], [], ['title' => 'Booking']);
    modeGroup($set, ['Appear in person'], [], ['title' => 'Appearance']);
    modeGroup($set, [Document::create(['name' => 'Extra paper'])], [], ['title' => 'Extra', 'condition_type' => 'custom', 'condition_custom' => 'If it applies']);

    $plan = app(RoadmapPlanner::class)->build($resident, [$target->id]);
    $kinds = array_column($plan['steps'][0]['prerequisites'], 'manual_kind', 'name');

    expect(array_column($plan['steps'], 'name'))->toBe(['Target'])
        ->and($kinds)->toBe(['Booking slip' => 'task', 'Appear in person' => 'task', 'Extra' => 'conditional']);
});

it('never recommends an option or a target with no researched details', function () {
    $resident = User::factory()->create();
    $target = GovernmentId::create(['name' => 'Target']);
    $blank = GovernmentId::create(['name' => 'A blank record']);
    $known = modeCredential('Z researched record');
    modeGroup(modeSet($target), [$blank, $known]);

    $plan = app(RoadmapPlanner::class)->build($resident, [$target->id]);
    $options = modeStep($plan)['suggestions'];

    expect(array_column($options, 'name'))->toBe(['Z researched record', 'A blank record'])
        ->and(array_column($options, 'recommended'))->toBe([true, false])
        ->and($options[1]['no_additional_id_required'])->toBeFalse()
        ->and($options[1]['recommendation_reasons'])->toBe(['Details are incomplete. Check with the issuing agency.']);
    $bare = app(RoadmapPlanner::class)->build($resident, [$blank->id]);
    expect($bare['steps'][0]['guidance_incomplete'])->toBeTrue();
});

it('caps readiness below complete while requirements cannot be checked', function () {
    $resident = User::factory()->create();
    $target = GovernmentId::create(['name' => 'Target']);
    $set = modeSet($target);
    $paper = Document::create(['name' => 'Paper']);
    modeGroup($set, [$paper], [], ['title' => 'Paper']);
    modeGroup($set, ['Appear in person'], [], ['title' => 'Appearance']);
    $resident->ownedDocuments()->attach($paper);

    expect(app(RoadmapPlanner::class)->build($resident, [$target->id])['steps'][0]['prerequisite_readiness']['percentage'])->toBe(99);
});

it('lets a resident change a saved path and keeps targets for deletion safety', function () {
    $resident = User::factory()->create();
    $target = GovernmentId::create(['name' => 'Target']);
    $first = modeSet($target, ['application_type' => 'renewal']);
    $second = modeSet($target, ['application_type' => 'replacement']);
    $paper = Document::create(['name' => 'Paper']);
    $other = Document::create(['name' => 'Other paper']);
    modeGroup($first, [$paper, $other]);
    modeGroup($second, ['Form']);
    $planner = app(RoadmapPlanner::class);
    $key = 'government_id:'.$target->id.':path';

    $chosen = $planner->build($resident, [$target->id], [], ['key' => $key, 'set_id' => $first->id]);
    $choice = modeStep($chosen);
    $picked = $planner->build($resident, [$target->id], [], ['key' => $choice['resolution_key'], 'type' => 'document', 'id' => $paper->id, 'way_id' => $choice['suggestions'][0]['way_id']]);
    expect(array_column($picked['saved_choices'], 'selected'))->toContain('Paper');

    $option = $planner->build($resident, [$target->id], [], ['key' => $choice['resolution_key'], 'reset' => true]);
    expect(array_column($option['saved_choices'], 'key'))->toBe([$key]);
    $stored = json_decode(DB::table('resident_journey_resolutions')->value('selections'), true);
    expect($stored['target_government_id_ids'])->toBe([$target->id]);

    $cleared = $planner->build($resident, [$target->id], [], ['key' => $key, 'reset' => true]);
    expect($cleared['saved_choices'])->toBe([])->and(modeStep($cleared)['kind'])->toBe('path');
    expect(DB::table('resident_journey_resolutions')->count())->toBe(0);
    expect(fn () => $planner->build($resident, [$target->id], [], ['key' => $key, 'reset' => true]))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

function modeJourney(User $user): array
{
    $paper = Document::create(['name' => 'Paper']);
    $target = GovernmentId::create(['name' => 'Target']);
    app(CatalogPrerequisites::class)->save($target, [['type' => 'document', 'document_id' => $paper->id]]);
    test()->actingAs($user);
    $journey = test()->postJson('/api/auth/journeys', ['target_government_id_ids' => [$target->id]])->assertCreated()->json('data');

    return [$journey, $paper, $target, '/api/auth/journeys/'.$journey['id']];
}

it('warns about suggested order without blocking what the resident confirms', function () {
    $user = User::factory()->create();
    [, $paper, $target, $path] = modeJourney($user);

    $this->putJson($path.'/completion', ['step_identity' => 'government_id:'.$target->id])
        ->assertUnprocessable()->assertJsonPath('errors.step_identity.0', 'Complete Paper first.');
    $this->putJson($path.'/completion', ['step_identity' => 'government_id:'.$target->id, 'acknowledge_order' => true])
        ->assertOk()->assertJsonPath('data.completed_steps', 1)->assertJsonPath('data.completed_at', null);
    $this->assertDatabaseCount('resident_documents', 0);
    $this->assertDatabaseCount('resident_government_ids', 0);
});

it('undoes a completion without touching other steps or Inventory', function () {
    $user = User::factory()->create();
    [, $paper, $target, $path] = modeJourney($user);
    $user->ownedDocuments()->attach($paper);
    $this->putJson($path.'/completion', ['step_identity' => 'document:'.$paper->id])->assertOk();
    $this->putJson($path.'/completion', ['step_identity' => 'government_id:'.$target->id])->assertOk()->assertJsonPath('data.progress_percentage', 100);

    $this->putJson($path.'/completion', ['step_identity' => 'government_id:'.$target->id, 'completed' => false])
        ->assertOk()->assertJsonPath('data.completed_step_identities', ['document:'.$paper->id])->assertJsonPath('data.completed_at', null);
    expect($user->ownedDocuments()->count())->toBe(1);
    $this->putJson($path.'/completion', ['step_identity' => 'government_id:'.$target->id, 'completed' => false])->assertOk();
});

it('removes only the selected Roadmap and its own progress and choices', function () {
    $user = User::factory()->create();
    [$journey, $paper, $target, $path] = modeJourney($user);
    $user->ownedDocuments()->attach($paper);
    $otherTarget = GovernmentId::create(['name' => 'Other']);
    $other = $this->postJson('/api/auth/journeys', ['target_government_id_ids' => [$otherTarget->id]])->assertCreated()->json('data');
    $this->putJson($path.'/completion', ['step_identity' => 'document:'.$paper->id])->assertOk();
    $saved = ResidentJourney::find($journey['id']);
    DB::table('resident_journey_resolutions')->insert(['user_id' => $user->id, 'journey_key' => $saved->journey_key, 'selections' => '{"paths":{},"groups":{}}']);
    DB::table('resident_journey_resolutions')->insert(['user_id' => $user->id, 'journey_key' => ResidentJourney::find($other['id'])->journey_key, 'selections' => '{"paths":{},"groups":{}}']);
    $item = modeGroup(modeSet($target), ['Form'])->items()->sole();
    DB::table('resident_id_preparations')->insert(['user_id' => $user->id, 'requirement_item_id' => $item->id, 'prepared' => true]);

    $this->deleteJson($path)->assertOk();

    $this->assertDatabaseMissing('resident_journeys', ['id' => $journey['id']]);
    $this->assertDatabaseHas('resident_journeys', ['id' => $other['id']]);
    $this->assertDatabaseCount('resident_journey_completions', 0);
    $this->assertDatabaseCount('resident_journey_resolutions', 1);
    $this->assertDatabaseCount('resident_documents', 1);
    $this->assertDatabaseCount('resident_id_preparations', 1);
});

it('does not let one resident remove or undo another residents Roadmap', function () {
    $user = User::factory()->create();
    [, $paper, , $path] = modeJourney($user);
    app('auth')->forgetGuards();
    $this->actingAs(User::factory()->create());
    $this->deleteJson($path)->assertNotFound();
    $this->putJson($path.'/completion', ['step_identity' => 'document:'.$paper->id, 'completed' => false])->assertNotFound();
    $this->assertDatabaseCount('resident_journeys', 1);
});

it('warns about catalog changes without rewriting steps and refreshes only when asked', function () {
    $user = User::factory()->create();
    [$journey, $paper, $target, $path] = modeJourney($user);
    $this->putJson($path.'/completion', ['step_identity' => 'document:'.$paper->id])->assertOk();
    $before = ResidentJourney::find($journey['id'])->plan;

    $target->update(['application_availability' => 'temporarily_unavailable']);
    $data = $this->getJson('/api/auth/journeys')->json('data.0');
    expect(collect($data['plan']['steps'])->last()['catalog_notice'])->toContain('unavailable')
        ->and(ResidentJourney::find($journey['id'])->plan)->toBe($before)
        ->and($data['completed_steps'])->toBe(1);
    $this->putJson($path.'/refresh')->assertUnprocessable();

    $target->update(['application_availability' => 'available']);
    $this->travel(1)->minutes();
    ContentChangeLog::create(['action' => 'updated', 'entity_type' => 'government_id', 'entity_id' => $target->id, 'entity_name' => 'Target', 'changed_fields' => ['requirement_sets'], 'created_at' => now()]);
    expect(collect($this->getJson('/api/auth/journeys')->json('data.0.plan.steps'))->last()['catalog_notice'])->toContain('changed');

    $this->travel(1)->minutes();
    $this->putJson($path.'/refresh')->assertOk()->assertJsonPath('data.completed_step_identities', ['document:'.$paper->id]);
    expect(collect($this->getJson('/api/auth/journeys')->json('data.0.plan.steps'))->last())->not->toHaveKey('catalog_notice');
});
