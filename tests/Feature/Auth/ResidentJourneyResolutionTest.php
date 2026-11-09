<?php

use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\ResidentJourney;
use App\Models\User;
use App\Services\RoadmapPlanner;
use Illuminate\Support\Facades\DB;

function resolutionSet($owner)
{
    return $owner->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'all']);
}

function resolutionGroup($set, array $ways, ?string $title = null)
{
    $group = $set->groups()->create(['title' => $title, 'rule' => 'all']);
    foreach ($ways as $spec) {
        $way = $group->ways()->create(['required_count' => $spec['count'] ?? 1, 'qualification_type' => 'none']);
        foreach ($spec['items'] as $item) {
            $link = is_string($item) ? ['type' => 'custom', 'custom_name' => $item]
                : ($item instanceof Document ? ['type' => 'document', 'document_id' => $item->id]
                    : ['type' => 'government_id', 'government_id_id' => $item->id]);
            $row = $group->items()->make($link);
            $row->requirement_way_id = $way->id;
            $row->save();
        }
    }

    return $group;
}

function legacyCredentialJourney(User $user): array
{
    $other = GovernmentId::create(['name' => 'Travel target']);
    $target = GovernmentId::create(['name' => 'National identity target']);
    $birth = Document::create(['name' => 'Shared birth document']);
    $accepted = [];
    foreach (range(1, 4) as $number) {
        $accepted[] = GovernmentId::create(['name' => 'Accepted ID '.$number, 'processing_time' => $number.' days', 'application_process' => 'Documented steps']);
    }
    $proofGroup = resolutionGroup(resolutionSet($target), [
        ['count' => 2, 'items' => [$birth, 'Additional government-issued photo ID']],
        ['items' => [...$accepted, $other]],
    ], 'Proof of Identity / Supporting Document');
    $set = resolutionSet($other);
    resolutionGroup($set, [['items' => [$birth]]]);
    resolutionGroup($set, [['items' => [$target, $accepted[0]]]]);
    $targets = [$target->id, $other->id];
    $planner = app(RoadmapPlanner::class);
    $plan = $planner->build($user, $targets);
    $pending = collect($plan['steps'])->firstWhere('type', 'unresolved_requirement');
    $option = collect($pending['suggestions'])->first(fn ($item) => $item['type'] === 'document' && $item['id'] === $birth->id);
    $plan = $planner->build($user, $targets, [], ['key' => $pending['resolution_key'], 'type' => 'document', 'id' => $birth->id, 'way_id' => $option['way_id']]);
    $pending = collect($plan['steps'])->firstWhere('type', 'unresolved_requirement');
    foreach ($plan['steps'] as &$step) {
        if ($step['type'] === 'unresolved_requirement') {
            $step['suggestions'] = [];
            $step['manual_preparation'] = true; // Old incorrectly classified snapshot.
        }
    }
    unset($step);
    $keys = array_map(fn ($id) => 'government_id:'.$id, $targets);
    sort($keys);
    $saved = ResidentJourney::create(['user_id' => $user->id, 'journey_key' => hash('sha256', json_encode($keys)), 'plan' => $plan]);

    return compact('saved', 'pending', 'birth', 'target', 'accepted', 'proofGroup');
}

test('saved credential choices refresh on read without rewriting the frozen acquisition snapshot', function () {
    $user = User::factory()->create();
    $fixture = legacyCredentialJourney($user);
    $saved = $fixture['saved'];
    $before = $saved->fresh()->plan;
    foreach (['document:'.$fixture['birth']->id, $fixture['pending']['resolution_key']] as $identity) {
        DB::table('resident_journey_completions')->insert(['resident_journey_id' => $saved->id, 'step_identity' => $identity, 'completed_at' => now()]);
    }
    $data = $this->actingAs($user)->getJson('/api/auth/journeys')->assertOk()->json('data.0');
    $pending = collect($data['plan']['steps'])->firstWhere('type', 'unresolved_requirement');
    expect($pending['manual_preparation'])->toBeFalse()
        ->and($pending['suggestions'])->toHaveCount(4)
        ->and(array_column($pending['suggestions'], 'recommended'))->toBe([true, true, true, false])
        ->and($saved->fresh()->plan)->toBe($before)
        ->and($data['completed_step_identities'])->toBe(['document:'.$fixture['birth']->id]);
    $this->putJson('/api/auth/journeys/'.$saved->id.'/completion', ['step_identity' => $pending['resolution_key']])->assertUnprocessable();
    $this->putJson('/api/auth/journeys/'.$saved->id.'/completion', ['step_identity' => 'government_id:'.$fixture['target']->id])->assertUnprocessable();
    $this->assertDatabaseCount('resident_journey_completions', 2); // History is not deleted.
});

test('active resolution saves the actual fourth acquisition and retains existing completion identities', function () {
    $user = User::factory()->create();
    $fixture = legacyCredentialJourney($user);
    $saved = $fixture['saved'];
    DB::table('resident_journey_completions')->insert(['resident_journey_id' => $saved->id, 'step_identity' => 'document:'.$fixture['birth']->id, 'completed_at' => now()]);
    $this->actingAs($user);
    $pending = collect($this->getJson('/api/auth/journeys')->json('data.0.plan.steps'))->firstWhere('type', 'unresolved_requirement');
    $option = $pending['suggestions'][3];
    $result = $this->putJson('/api/auth/journeys/'.$saved->id.'/resolution', ['resolution' => [
        'key' => $pending['resolution_key'], 'type' => $option['type'], 'id' => $option['id'], 'way_id' => $option['way_id'],
    ]])->assertOk()->json('data');
    expect(array_column($result['plan']['steps'], 'name'))->toContain($option['name'])->not->toContain('Proof of Identity / Supporting Document')
        ->and($result['completed_step_identities'])->toBe(['document:'.$fixture['birth']->id])
        ->and($result['completed_step_identities'])->not->toContain($option['type'].':'.$option['id']);
    expect($saved->fresh()->plan)->toBe($result['plan']);
    $this->getJson('/api/auth/journeys')->assertOk()->assertJsonPath('data.0.plan', $result['plan']);
    $this->assertDatabaseCount('resident_journey_completions', 1);
    $this->assertDatabaseCount('resident_government_ids', 0);
    $this->assertDatabaseCount('resident_documents', 0);
});

test('active resolution validates options scopes ownership and rolls back rejected selections', function () {
    $user = User::factory()->create();
    $fixture = legacyCredentialJourney($user);
    $saved = $fixture['saved'];
    $before = $saved->fresh()->plan;
    $url = '/api/auth/journeys/'.$saved->id.'/resolution';
    $this->putJson($url, ['resolution' => ['key' => $fixture['pending']['resolution_key']]])->assertUnauthorized();
    $this->actingAs($user)->putJson($url, ['resolution' => ['key' => 'foreign:choice', 'type' => 'government_id', 'id' => 999, 'way_id' => 999]])->assertUnprocessable();
    $this->putJson($url, ['resolution' => ['key' => $fixture['pending']['resolution_key'], 'type' => 'government_id', 'id' => 999, 'way_id' => 999]])->assertUnprocessable();
    $this->putJson($url, ['resolution' => ['key' => $fixture['pending']['resolution_key']], 'plan' => $before])->assertUnprocessable();
    expect($saved->fresh()->plan)->toBe($before);
    $this->actingAs(User::factory()->create())->putJson($url, ['resolution' => ['key' => $fixture['pending']['resolution_key']]])->assertNotFound();
    foreach ([['role' => 'researcher'], ['is_active' => false]] as $attributes) {
        $this->actingAs(User::factory()->create($attributes))->putJson($url, ['resolution' => ['key' => $fixture['pending']['resolution_key']]])->assertForbidden();
    }
});

test('a genuinely unavailable guided requirement remains an explicit manual fallback', function () {
    $user = User::factory()->create();
    $target = GovernmentId::create(['name' => 'Target']);
    $a = GovernmentId::create(['name' => 'Reference A', 'guidance_mode' => 'informational_only']);
    $b = GovernmentId::create(['name' => 'Reference B', 'guidance_mode' => 'informational_only']);
    resolutionGroup(resolutionSet($target), [['items' => [$a, $b]]]);
    $saved = $this->actingAs($user)->postJson('/api/auth/journeys', ['target_government_id_ids' => [$target->id]])->assertCreated()->json('data');
    $pending = collect($saved['plan']['steps'])->firstWhere('type', 'unresolved_requirement');
    expect($pending['manual_preparation'])->toBeTrue()->and($pending['suggestions'])->toBe([]);
    $this->putJson('/api/auth/journeys/'.$saved['id'].'/completion', ['step_identity' => $pending['resolution_key']])->assertOk();
});


test('a saved placeholder covered by a new complete CMS alternative is applied only after explicit review', function () {
    $user = User::factory()->create();
    $fixture = legacyCredentialJourney($user);
    $saved = $fixture['saved'];
    $before = $saved->fresh()->plan;
    $way = $fixture['proofGroup']->ways()->create(['required_count' => 1, 'qualification_type' => 'none']);
    $item = $fixture['proofGroup']->items()->make(['type' => 'document', 'document_id' => $fixture['birth']->id]);
    $item->requirement_way_id = $way->id;
    $item->save();
    $this->actingAs($user);
    $data = $this->getJson('/api/auth/journeys')->assertOk()->json('data.0');
    $pending = collect($data['plan']['steps'])->firstWhere('type', 'unresolved_requirement');
    expect($pending['manual_preparation'])->toBeFalse()
        ->and($pending['resolution_ready'])->toBeTrue()
        ->and($saved->fresh()->plan)->toBe($before);
    $updated = $this->putJson('/api/auth/journeys/'.$saved->id.'/resolution', [
        'resolution' => ['key' => $pending['resolution_key']],
    ])->assertOk()->json('data');
    expect(array_column($updated['plan']['steps'], 'name'))->not->toContain('Proof of Identity / Supporting Document')
        ->and($updated['completed_step_identities'])->toBe([]);
});
