<?php

use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\ResidentJourney;
use App\Models\User;
use App\Services\CatalogPrerequisites;
use App\Services\RoadmapPlanner;
use Illuminate\Support\Facades\DB;

function informationalMethod($owner, string $label, $evidence)
{
    $set = $owner->requirementSets()->create([
        'application_type' => 'copy', 'applicant_type' => 'all',
        'request_channel' => ['label' => $label, 'channel' => 'online',
            'preparation' => 'Bring the original record.', 'application_guide_text' => 'Follow the saved instructions.',
            'portal_url' => 'https://example.gov.ph/request', 'processing' => '3 working days',
            'troubleshooting' => [['title' => 'Warning', 'body' => 'Check the receiving office.']]],
    ]);
    $group = $set->groups()->create(['title' => 'Method-specific evidence', 'rule' => 'all']);
    $way = $group->ways()->create(['required_count' => 1, 'qualification_type' => 'none']);
    $item = $group->items()->make(['type' => 'government_id', 'government_id_id' => $evidence->id]);
    $item->requirement_way_id = $way->id;
    $item->save();

    return $set;
}

it('keeps methods out of generation readiness confirmation and completion', function ($model) {
    $resident = User::factory()->create();
    $target = $model::create(['name' => 'Target record']);
    $general = GovernmentId::create(['name' => 'General prerequisite']);
    $methodOnly = GovernmentId::create(['name' => 'Method-only evidence', 'application_availability' => 'temporarily_unavailable']);
    app(CatalogPrerequisites::class)->save($target, [['type' => 'government_id', 'government_id_id' => $general->id]]);
    $ids = $target instanceof Document ? [] : [$target->id];
    $docs = $target instanceof Document ? [$target->id] : [];
    $planner = app(RoadmapPlanner::class);
    $before = $planner->build($resident, $ids, $docs);
    foreach (['PSA CRS Outlet', 'PSA Helpline', 'PSA Serbilis'] as $label) {
        informationalMethod($target, $label, $methodOnly);
    }
    $snapshot = $target->fresh()->requirementSets()->with('groups.items')->get()->toJson();
    expect($planner->build($resident, $ids, $docs))->toBe($before);
    $this->actingAs($resident);
    $saved = $this->postJson('/api/auth/journeys', ['target_government_id_ids' => $ids, 'target_document_ids' => $docs])
        ->assertCreated()->assertJsonPath('data.total_steps', 2)->json('data');
    $path = '/api/auth/journeys/'.$saved['id'].'/completion';
    $identity = ($target instanceof Document ? 'document:' : 'government_id:').$target->id;
    $this->putJson($path, ['step_identity' => $identity])->assertUnprocessable();
    $this->putJson($path, ['step_identity' => 'government_id:'.$general->id])->assertOk()->assertJsonPath('data.progress_percentage', 50);
    $this->putJson($path, ['step_identity' => $identity])->assertOk()->assertJsonPath('data.progress_percentage', 100);
    expect($target->fresh()->requirementSets()->with('groups.items')->get()->toJson())->toBe($snapshot);
    $this->assertDatabaseCount('resident_government_ids', 0);
    $this->assertDatabaseCount('resident_documents', 0);
    $this->assertDatabaseCount('resident_journey_resolutions', 0);
})->with([[Document::class], [GovernmentId::class]]);

it('retains general structured requirements while leaving method content available in the API', function () {
    $resident = User::factory()->create();
    $target = Document::create(['name' => 'PSA Birth Certificate']);
    $evidence = GovernmentId::create(['name' => 'General evidence']);
    $general = $target->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'all']);
    $group = $general->groups()->create(['rule' => 'all']);
    $group->items()->create(['type' => 'government_id', 'government_id_id' => $evidence->id]);
    $method = informationalMethod($target, 'PSA Helpline', $evidence);
    $plan = app(RoadmapPlanner::class)->build($resident, [], [$target->id]);
    expect(array_column($plan['steps'], 'name'))->toBe(['General evidence', 'PSA Birth Certificate']);
    $this->getJson('/api/documents/'.$target->id)->assertOk()
        ->assertJsonCount(1, 'data.requirement_sets')->assertJsonPath('data.requirement_sets.0.id', $general->id)
        ->assertJsonPath('data.request_paths.1.id', $method->id)
        ->assertJsonPath('data.request_paths.1.is_request_method', true)
        ->assertJsonPath('data.request_paths.1.preparation', 'Bring the original record.')
        ->assertJsonPath('data.request_paths.1.application_guide_text', 'Follow the saved instructions.')
        ->assertJsonCount(1, 'data.request_paths.1.requirement_sets.0.groups');
    $this->actingAs($resident)->postJson('/api/auth/roadmap-plan', [
        'target_document_ids' => [$target->id],
        'resolution' => ['key' => 'document:'.$target->id.':path', 'set_id' => $method->id],
    ])->assertUnprocessable();
    $this->assertDatabaseCount('resident_journey_resolutions', 0);
});

it('ignores obsolete method-only prompts in saved journeys without rewriting their stored snapshot', function () {
    $user = User::factory()->create();
    $target = Document::create(['name' => 'Saved document']);
    $evidence = GovernmentId::create(['name' => 'Method evidence']);
    $methods = [informationalMethod($target, 'Outlet', $evidence), informationalMethod($target, 'Online', $evidence)];
    $plan = app(RoadmapPlanner::class)->build($user, [], [$target->id]);
    array_unshift($plan['steps'], [
        'type' => 'unresolved_requirement', 'id' => $target->id, 'kind' => 'path',
        'owner_key' => 'document:'.$target->id, 'resolution_key' => 'document:'.$target->id.':path',
        'name' => 'Choose a request path', 'suggestions' => array_map(fn ($set) => ['set_id' => $set->id, 'name' => 'Method'], $methods),
    ]);
    $saved = ResidentJourney::create(['user_id' => $user->id, 'journey_key' => str_repeat('d', 64), 'plan' => $plan]);
    $this->actingAs($user)->getJson('/api/auth/journeys')->assertOk()
        ->assertJsonCount(1, 'data.0.plan.steps')->assertJsonPath('data.0.total_steps', 1);
    $this->putJson('/api/auth/journeys/'.$saved->id.'/completion', ['step_identity' => 'document:'.$target->id])
        ->assertOk()->assertJsonPath('data.progress_percentage', 100);
    expect($saved->fresh()->plan)->toBe($plan);
    expect(DB::table('resident_journey_completions')->count())->toBe(1);
});
