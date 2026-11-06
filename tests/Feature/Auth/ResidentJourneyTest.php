<?php

use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\ResidentJourney;
use App\Models\User;
use App\Services\CatalogPrerequisites;
use Illuminate\Support\Facades\DB;

function threeStepJourney(User $resident): array
{
    $document = Document::create(['name' => 'Supporting document']);
    $id = GovernmentId::create(['name' => 'Supporting ID']);
    $target = GovernmentId::create(['name' => 'Target ID']);
    app(CatalogPrerequisites::class)->save($id, [['type' => 'document', 'document_id' => $document->id]]);
    app(CatalogPrerequisites::class)->save($target, [['type' => 'government_id', 'government_id_id' => $id->id]]);

    return [
        'target_government_id_ids' => [$target->id],
        'target_document_ids' => [],
    ];
}

test('confirmation saves zero completion and a stable server plan without changing Inventory', function () {
    $user = User::factory()->create();
    $targets = threeStepJourney($user);
    $this->actingAs($user);
    $journey = $this->postJson('/api/auth/journeys', $targets)->assertCreated()
        ->assertJsonPath('data.completed_step_identities', [])->assertJsonPath('data.progress_percentage', 0)
        ->assertJsonPath('data.total_steps', 3)->json('data');
    $this->postJson('/api/auth/journeys', $targets)->assertOk()->assertJsonPath('data.id', $journey['id']);
    $this->assertDatabaseCount('resident_journeys', 1);
    $this->assertDatabaseCount('resident_journey_completions', 0);
    $this->assertDatabaseCount('resident_government_ids', 0);
    $this->assertDatabaseCount('resident_documents', 0);
    $this->assertDatabaseCount('resident_id_preparations', 0);
    $this->assertDatabaseCount('resident_document_preparations', 0);
});

test('major step completion is ordered idempotent and survives another login and device', function () {
    $user = User::factory()->create(['password' => 'Password123!']);
    $targets = threeStepJourney($user);
    $token = $this->postJson('/api/auth/login', ['identifier' => $user->email, 'password' => 'Password123!'])->assertOk()->json('token');
    $this->withToken($token);
    $journey = $this->postJson('/api/auth/journeys', $targets)->assertCreated()->json('data');
    $path = '/api/auth/journeys/'.$journey['id'].'/completion';
    $identities = array_map(ResidentJourney::identity(...), $journey['plan']['steps']);
    $this->putJson($path, ['step_identity' => $identities[1]])->assertUnprocessable();
    foreach ([33, 67, 100] as $i => $percentage) {
        $this->putJson($path, ['step_identity' => $identities[$i]])->assertOk()
            ->assertJsonPath('data.completed_steps', $i + 1)->assertJsonPath('data.progress_percentage', $percentage);
    }
    $before = DB::table('resident_journey_completions')->orderBy('step_identity')->get()->toJson();
    $this->travel(30)->seconds();
    $this->putJson($path, ['step_identity' => $identities[0]])->assertOk()->assertJsonPath('data.completed_steps', 3);
    expect(DB::table('resident_journey_completions')->orderBy('step_identity')->get()->toJson())->toBe($before);
    $this->postJson('/api/auth/logout')->assertOk();
    app('auth')->forgetGuards();
    $this->withHeader('Authorization', '');
    $nextToken = $this->postJson('/api/auth/login', ['identifier' => $user->email, 'password' => 'Password123!'])->assertOk()->json('token');
    $this->withToken($nextToken)->getJson('/api/auth/journeys')->assertOk()
        ->assertJsonPath('data.0.id', $journey['id'])->assertJsonPath('data.0.progress_percentage', 100)
        ->assertJsonPath('data.0.plan', $journey['plan']);
    // Another device uses an independent bearer token against the same database.
    app('auth')->forgetGuards();
    $this->withToken($user->createToken('another-device')->plainTextToken)
        ->getJson('/api/auth/journeys')->assertOk()->assertJsonPath('data.0.completed_steps', 3);
    $this->assertDatabaseCount('resident_journey_completions', 3);
    $this->assertDatabaseCount('resident_government_ids', 0);
    $this->assertDatabaseCount('resident_documents', 0);
    $this->assertDatabaseCount('resident_id_preparations', 0);
    $this->assertDatabaseCount('resident_document_preparations', 0);
});

test('journeys and completion cannot be accessed across accounts or forged', function () {
    $user = User::factory()->create();
    $targets = threeStepJourney($user);
    $this->actingAs($user);
    $journey = $this->postJson('/api/auth/journeys', $targets)->assertCreated()->json('data');
    $path = '/api/auth/journeys/'.$journey['id'].'/completion';
    $this->putJson($path, ['step_identity' => 'document:999999'])->assertUnprocessable();
    $this->putJson($path, ['step_identity' => 'unresolved:fake'])->assertUnprocessable();
    $this->postJson('/api/auth/journeys', [...$targets, 'completed_step_identities' => ['government_id:1']])->assertUnprocessable();
    $this->postJson('/api/auth/journeys', [...$targets, 'plan' => $journey['plan']])->assertUnprocessable();
    $other = User::factory()->create();
    $this->actingAs($other)->getJson('/api/auth/journeys')->assertOk()->assertJsonPath('data', []);
    $this->putJson($path, ['step_identity' => ResidentJourney::identity($journey['plan']['steps'][0])])->assertNotFound();
    $this->postJson('/api/auth/journeys', [...$targets, 'user_id' => $user->id])->assertUnprocessable();
    $this->assertDatabaseCount('resident_journey_completions', 0);
});

test('completion is scoped to each journey and account deletion cascades', function () {
    $user = User::factory()->create();
    $targets = threeStepJourney($user);
    $this->actingAs($user);
    $first = $this->postJson('/api/auth/journeys', $targets)->assertCreated()->json('data');
    $otherTarget = GovernmentId::create(['name' => 'Another target']);
    $second = $this->postJson('/api/auth/journeys', ['target_government_id_ids' => [$otherTarget->id]])->assertCreated()->json('data');
    $this->putJson('/api/auth/journeys/'.$first['id'].'/completion', ['step_identity' => ResidentJourney::identity($first['plan']['steps'][0])])->assertOk();
    $this->getJson('/api/auth/journeys')->assertJsonPath('data.1.completed_steps', 0);
    $user->delete();
    $this->assertDatabaseCount('resident_journeys', 0);
    $this->assertDatabaseCount('resident_journey_completions', 0);
});

test('journey endpoints require an active resident session', function () {
    $this->getJson('/api/auth/journeys')->assertUnauthorized();
    $this->postJson('/api/auth/journeys', [])->assertUnauthorized();
    $this->putJson('/api/auth/journeys/1/completion', ['step_identity' => 'document:1'])->assertUnauthorized();
    foreach ([['role' => 'researcher'], ['is_active' => false]] as $attributes) {
        $this->actingAs(User::factory()->create($attributes));
        $this->getJson('/api/auth/journeys')->assertForbidden();
        $this->postJson('/api/auth/journeys', [])->assertForbidden();
        $this->putJson('/api/auth/journeys/1/completion', ['step_identity' => 'document:1'])->assertForbidden();
    }
});

test('placeholders and informational entries remain visible but cannot count as completed acquisitions', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $journey = ResidentJourney::create([
        'user_id' => $user->id, 'journey_key' => str_repeat('a', 64),
        'plan' => ['target_government_id_ids' => [2], 'target_document_ids' => [], 'steps' => [
            ['type' => 'unresolved_requirement', 'id' => 1, 'resolution_key' => 'pending:1', 'name' => 'Pending choice'],
            ['type' => 'document', 'id' => 1, 'name' => 'Reference', 'guidance_mode' => 'informational_only'],
            ['type' => 'government_id', 'id' => 2, 'name' => 'Actual major step'],
        ]],
    ]);
    $path = '/api/auth/journeys/'.$journey->id.'/completion';
    $this->putJson($path, ['step_identity' => 'pending:1'])->assertUnprocessable();
    $this->putJson($path, ['step_identity' => 'document:1'])->assertUnprocessable();
    $this->getJson('/api/auth/journeys')->assertJsonCount(3, 'data.0.plan.steps')->assertJsonPath('data.0.total_steps', 1);
    $this->putJson($path, ['step_identity' => 'government_id:2'])->assertOk()->assertJsonPath('data.progress_percentage', 100);
});

test('blocked plans cannot be persisted as confirmed journeys', function () {
    $user = User::factory()->create();
    $target = GovernmentId::create(['name' => 'Unavailable target', 'application_availability' => 'temporarily_unavailable']);
    $this->actingAs($user)->postJson('/api/auth/journeys', ['target_government_id_ids' => [$target->id]])
        ->assertUnprocessable()->assertJsonValidationErrors('journey');
    $this->assertDatabaseCount('resident_journeys', 0);
});

test('later Inventory changes do not rewrite the saved timeline or infer completion', function () {
    $user = User::factory()->create();
    $targets = threeStepJourney($user);
    $this->actingAs($user);
    $saved = $this->postJson('/api/auth/journeys', $targets)->assertCreated()->json('data');
    $document = Document::firstOrFail();
    $user->ownedDocuments()->attach($document);
    $this->postJson('/api/auth/journeys', $targets)->assertOk()
        ->assertJsonPath('data.plan', $saved['plan'])->assertJsonPath('data.total_steps', 3)
        ->assertJsonPath('data.completed_step_identities', [])->assertJsonPath('data.progress_percentage', 0);
    $this->putJson('/api/auth/journeys/'.$saved['id'].'/completion', [
        'step_identity' => ResidentJourney::identity($saved['plan']['steps'][0]),
    ])->assertOk()->assertJsonPath('data.progress_percentage', 33);
    expect($user->ownedDocuments()->pluck('documents.id')->all())->toBe([$document->id]);
    $this->assertDatabaseCount('resident_government_ids', 0);
});

test('unrelated goals finish in any order while dependencies still come first', function () {
    $user = User::factory()->create();
    $dependency = Document::create(['name' => 'Supporting document']);
    $dependent = GovernmentId::create(['name' => 'Dependent ID']);
    $unrelated = GovernmentId::create(['name' => 'Unrelated ID']);
    app(CatalogPrerequisites::class)->save($dependent, [['type' => 'document', 'document_id' => $dependency->id]]);
    $this->actingAs($user);
    $journey = $this->postJson('/api/auth/journeys', [
        'target_government_id_ids' => [$dependent->id, $unrelated->id],
    ])->assertCreated()->json('data');
    $path = '/api/auth/journeys/'.$journey['id'].'/completion';

    $this->putJson($path, ['step_identity' => 'government_id:'.$dependent->id])
        ->assertUnprocessable()->assertJsonPath('errors.step_identity.0', 'Complete Supporting document first.');
    $this->putJson($path, ['step_identity' => 'government_id:'.$unrelated->id])
        ->assertOk()->assertJsonPath('data.completed_steps', 1);
    $this->putJson($path, ['step_identity' => 'document:'.$dependency->id])->assertOk();
    $this->putJson($path, ['step_identity' => 'government_id:'.$dependent->id])
        ->assertOk()->assertJsonPath('data.progress_percentage', 100);
});

test('an owned target adds no progress step and cannot be completed', function () {
    $user = User::factory()->create();
    $owned = GovernmentId::create(['name' => 'Owned ID']);
    $other = GovernmentId::create(['name' => 'Other ID']);
    $user->ownedGovernmentIds()->attach($owned);
    $this->actingAs($user);
    $journey = $this->postJson('/api/auth/journeys', [
        'target_government_id_ids' => [$owned->id, $other->id],
    ])->assertCreated()->assertJsonPath('data.total_steps', 1)->json('data');
    $path = '/api/auth/journeys/'.$journey['id'].'/completion';
    $this->putJson($path, ['step_identity' => 'government_id:'.$owned->id])->assertUnprocessable();
    $this->putJson($path, ['step_identity' => 'government_id:'.$other->id])
        ->assertOk()->assertJsonPath('data.progress_percentage', 100);
    $this->assertDatabaseCount('resident_government_ids', 1);
});

test('a roadmap of only owned targets is not saved', function () {
    $user = User::factory()->create();
    $owned = GovernmentId::create(['name' => 'Owned ID']);
    $user->ownedGovernmentIds()->attach($owned);
    $this->actingAs($user)->postJson('/api/auth/journeys', ['target_government_id_ids' => [$owned->id]])
        ->assertUnprocessable()->assertJsonPath('errors.journey.0', 'You already have everything in this roadmap.');
    $this->assertDatabaseCount('resident_journeys', 0);
});
