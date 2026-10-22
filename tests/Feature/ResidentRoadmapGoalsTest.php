<?php

use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\GovernmentIdPrerequisite;
use App\Models\ResidentRoadmapGoal;
use App\Models\User;
use App\Services\ReferenceData;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->resident = User::factory()->create();
    $this->other = User::factory()->create();
    $this->target = GovernmentId::create(['name' => 'Target License']);
    $this->permit = GovernmentId::create(['name' => 'Prerequisite Permit']);
    $this->document = Document::create(['name' => 'Birth Certificate']);
    $this->profile = $this->permit->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'custom', 'applicant_type_custom' => 'Students']);
    $this->url = '/api/auth/roadmap-goals/'.$this->target->id;
    $this->payload = fn (array $selections = []) => ['application_type' => 'new', 'profile_selections' => (object) $selections];
    Sanctum::actingAs($this->resident);
});

it('saves a unique goal per resident and target while allowing other targets and residents', function () {
    $this->putJson($this->url, ($this->payload)())->assertOk()->assertJsonPath('data.government_id.id', $this->target->id);
    $this->putJson($this->url, ['application_type' => 'renewal', 'profile_selections' => (object) []])->assertOk();
    expect(ResidentRoadmapGoal::count())->toBe(1)->and(ResidentRoadmapGoal::first()->application_type)->toBe('renewal');
    $this->putJson('/api/auth/roadmap-goals/'.$this->permit->id, ($this->payload)())->assertOk();
    Sanctum::actingAs($this->other);
    $this->putJson($this->url, ($this->payload)())->assertOk();
    expect(ResidentRoadmapGoal::count())->toBe(3);
    $this->getJson('/api/auth/roadmap-goals')->assertOk()->assertJsonCount(1, 'data');
});

it('scopes every read and mutation to the authenticated resident', function () {
    $this->putJson($this->url, ($this->payload)([$this->permit->id => $this->profile->id]))->assertOk();
    $original = ResidentRoadmapGoal::first()->getAttributes();
    Sanctum::actingAs($this->other);
    $this->getJson($this->url.'?user_id='.$this->resident->id)->assertNotFound();
    $this->deleteJson($this->url)->assertNotFound();
    $this->getJson('/api/auth/roadmap-goals')->assertJsonCount(0, 'data');
    $this->putJson($this->url, ($this->payload)())->assertOk();
    expect(ResidentRoadmapGoal::where('user_id', $this->resident->id)->first()->getAttributes())->toBe($original);
});

it('removes only the resident goal without removing any catalog content', function () {
    $this->putJson($this->url, ($this->payload)())->assertOk();
    $this->deleteJson($this->url)->assertNoContent();
    expect(ResidentRoadmapGoal::count())->toBe(0)->and(GovernmentId::count())->toBe(2)->and(Document::count())->toBe(1);
});

it('persists decisions only and rejects ownership age and private profile payloads', function () {
    $this->resident->ownedGovernmentIds()->attach($this->permit);
    $this->resident->ownedDocuments()->attach($this->document);
    $this->putJson($this->url, ($this->payload)([$this->permit->id => $this->profile->id]))->assertOk();
    $row = ResidentRoadmapGoal::first()->getAttributes();
    expect(array_keys($row))->toBe(['id', 'user_id', 'government_id_id', 'application_type', 'profile_selections', 'created_at', 'updated_at']);
    foreach (['age' => 25, 'owned_government_id_ids' => [$this->permit->id], 'owned_document_ids' => [$this->document->id], 'user_id' => $this->other->id, 'name' => 'Private', 'date_of_birth' => '2000-01-01', 'plan' => []] as $key => $value) {
        $this->putJson($this->url, [...($this->payload)(), $key => $value])->assertUnprocessable()->assertJsonValidationErrors($key);
    }
});

it('restores valid manual selections and filters missing or reparented profiles without inferring labels', function () {
    $map = [$this->permit->id => $this->profile->id];
    $this->putJson($this->url, ($this->payload)($map))->assertOk()->assertJsonPath('data.profile_selections.'.$this->permit->id, $this->profile->id);
    $this->getJson($this->url)->assertOk()->assertJsonPath('data.discarded_profile_selections', false);
    $this->profile->update(['government_id_id' => $this->target->id]);
    $this->getJson($this->url)->assertOk()->assertJsonPath('data.discarded_profile_selections', true)->assertJsonCount(0, 'data.profile_selections');
    $this->profile->delete();
    $this->getJson($this->url)->assertOk()->assertJsonCount(0, 'data.profile_selections');
    $this->putJson($this->url, ($this->payload)($map))->assertOk()->assertJsonPath('data.discarded_profile_selections', true)->assertJsonCount(0, 'data.profile_selections');
});

it('does not report stale decisions merely because MySQL reordered JSON keys', function () {
    $root = $this->target->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'all']);
    $map = [$this->permit->id => $this->profile->id, $this->target->id => $root->id];
    $this->putJson($this->url, ($this->payload)($map))->assertOk();
    DB::table('resident_roadmap_goals')->update(['profile_selections' => json_encode($map, JSON_THROW_ON_ERROR)]);
    $this->getJson($this->url)->assertOk()->assertJsonPath('data.discarded_profile_selections', false)->assertJsonCount(2, 'data.profile_selections');
});

it('reports a readable CMS deletion error for a saved target', function () {
    $this->putJson($this->url, ($this->payload)())->assertOk();
    $this->actingAs(User::factory()->create(['role' => 'researcher']), 'web')
        ->delete('/admin/government-ids/'.$this->target->id)
        ->assertSessionHas('error', 'This Government ID is a saved resident goal and cannot be deleted.');
    expect(ResidentRoadmapGoal::count())->toBe(1)->and($this->target->fresh())->not->toBeNull();
});

it('validates map shape and rejects ambiguous or unsupported input', function () {
    foreach ([['profile_selections' => []], ['profile_selections' => ['01' => 1]], ['profile_selections' => ['1' => '1']], ['application_type' => 'invented']] as $data) {
        $this->putJson($this->url, $data)->assertUnprocessable();
    }
    $this->call('PUT', $this->url, [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], '{"profile_selections":{"2":1,"2":2}}')->assertUnprocessable()->assertJsonValidationErrors('request');
});

it('recalculates the sequence from current prerequisites and inventory rather than storing a frozen plan', function () {
    $this->putJson($this->url, ($this->payload)())->assertOk();
    $sequence = '/api/government-ids/'.$this->target->id.'/sequence';
    $this->postJson($sequence, ['application_type' => 'new'])->assertOk()->assertJsonCount(1, 'data.plan');
    GovernmentIdPrerequisite::create(['government_id_id' => $this->target->id, 'prerequisite_government_id_id' => $this->permit->id]);
    GovernmentIdPrerequisite::create(['government_id_id' => $this->permit->id, 'prerequisite_document_id' => $this->document->id]);
    $goal = $this->getJson($this->url)->assertOk()->json('data');
    $this->postJson($sequence, ['application_type' => $goal['application_type'], 'profile_selections' => (object) $goal['profile_selections']])->assertOk()
        ->assertJsonPath('data.plan.0.id', $this->document->id)->assertJsonPath('data.plan.1.id', $this->permit->id)->assertJsonPath('data.plan.2.id', $this->target->id);
    $this->resident->ownedGovernmentIds()->attach($this->permit);
    $inventory = $this->getJson('/api/auth/inventory')->assertOk()->json('data');
    $owned = collect($inventory['government_ids'])->where('owned', true)->pluck('id')->all();
    $this->postJson($sequence, ['application_type' => 'new', 'owned_government_id_ids' => $owned])->assertOk()->assertJsonCount(1, 'data.plan')->assertJsonPath('data.satisfied.0.id', $this->permit->id);
    expect(ResidentRoadmapGoal::first()->profile_selections)->toBe([]);
});

it('requests age again when a saved automatic adult goal is opened without session age', function () {
    $adult = $this->target->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'adult']);
    GovernmentIdPrerequisite::create(['government_id_id' => $this->target->id, 'requirement_set_id' => $adult->id, 'prerequisite_government_id_id' => $this->permit->id]);
    $this->putJson($this->url, ($this->payload)())->assertOk();
    $saved = $this->getJson($this->url)->assertOk()->json('data');
    expect(array_key_exists('age', $saved))->toBeFalse();
    $this->postJson('/api/government-ids/'.$this->target->id.'/sequence', ['application_type' => $saved['application_type'], 'profile_selections' => (object) $saved['profile_selections']])
        ->assertOk()->assertJsonPath('data.status', 'insufficient_context')->assertJsonPath('data.missing_context', ['age']);
});

it('creates and rolls back only the goal table without changing existing resident or catalog records', function () {
    $migration = require database_path('migrations/2026_10_22_000001_create_resident_roadmap_goals_table.php');
    $counts = [User::count(), GovernmentId::count(), Document::count()];
    $migration->down();
    $migration->up();
    expect([User::count(), GovernmentId::count(), Document::count()])->toBe($counts)
        ->and(ResidentRoadmapGoal::count())->toBe(0);
});

it('reports target ownership as completion without deleting the saved goal', function () {
    $this->putJson($this->url, ($this->payload)())->assertOk()->assertJsonPath('data.is_completed', false);
    $this->resident->ownedGovernmentIds()->attach($this->target);
    $this->getJson($this->url)->assertOk()->assertJsonPath('data.is_completed', true);
    $this->getJson('/api/auth/roadmap-goals')->assertOk()->assertJsonPath('data.0.is_completed', true);
    $this->postJson('/api/government-ids/'.$this->target->id.'/sequence', ['owned_government_id_ids' => [$this->target->id]])->assertOk()->assertJsonCount(0, 'data.plan')->assertJsonPath('data.satisfied.0.is_target', true);
    expect(ResidentRoadmapGoal::count())->toBe(1);
});

it('excludes goals and selections from reference exports', function () {
    $this->putJson($this->url, ($this->payload)([$this->permit->id => $this->profile->id]))->assertOk();
    $snapshot = app(ReferenceData::class)->capture();
    expect(array_keys($snapshot['tables']))->not->toContain('resident_roadmap_goals', 'users', 'resident_government_ids')
        ->and(array_key_exists('resident_roadmap_goals', ReferenceData::COLUMNS))->toBeFalse();
});

it('protects a goal target from deletion and cascades goals only with account deletion', function () {
    $this->putJson($this->url, ($this->payload)())->assertOk();
    expect(fn () => $this->target->delete())->toThrow(QueryException::class);
    $this->resident->delete();
    expect(ResidentRoadmapGoal::count())->toBe(0)->and(GovernmentId::whereKey($this->target->id)->exists())->toBeTrue();
});

it('requires an active authenticated resident', function () {
    auth()->forgetGuards();
    $this->getJson('/api/auth/roadmap-goals')->assertUnauthorized();
    $this->putJson($this->url, ($this->payload)())->assertUnauthorized();
    foreach ([['role' => 'researcher'], ['is_active' => false]] as $attributes) {
        Sanctum::actingAs(User::factory()->create($attributes));
        $this->getJson('/api/auth/roadmap-goals')->assertForbidden();
        $this->putJson($this->url, ($this->payload)())->assertForbidden();
    }
});
