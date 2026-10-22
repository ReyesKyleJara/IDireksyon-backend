<?php

use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\GovernmentIdPrerequisite;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('returns only the authenticated residents explicit ownership without private profile data', function () {
    $resident = User::factory()->create();
    $other = User::factory()->create();
    $id = GovernmentId::create(['name' => 'Recorded ID']);
    $notOwned = GovernmentId::create(['name' => 'Other ID']);
    $document = Document::create(['name' => 'Recorded Document']);
    $resident->ownedGovernmentIds()->attach($id);
    $resident->ownedDocuments()->attach($document);
    $other->ownedGovernmentIds()->attach($notOwned);
    Sanctum::actingAs($resident);
    $data = $this->getJson('/api/auth/inventory?user_id='.$other->id)->assertOk()->json('data');
    expect(collect($data['government_ids'])->where('owned', true)->pluck('id')->all())->toBe([$id->id])
        ->and(collect($data['documents'])->where('owned', true)->pluck('id')->all())->toBe([$document->id])
        ->and(array_keys($data))->toBe(['government_ids', 'documents'])
        ->and(array_keys($data['government_ids'][0]))->toBe(['id', 'name', 'owned']);
    Sanctum::actingAs($other);
    $otherData = $this->getJson('/api/auth/inventory')->assertOk()->json('data');
    expect(collect($otherData['government_ids'])->where('owned', true)->pluck('id')->all())->toBe([$notOwned->id])
        ->and(collect($otherData['documents'])->where('owned', true))->toBeEmpty();
});

it('keeps inventory private while allowing public applicability and sequencing', function () {
    $id = GovernmentId::create(['name' => 'Public ID']);
    $this->getJson('/api/auth/inventory')->assertUnauthorized();
    $this->postJson('/api/government-ids/'.$id->id.'/sequence')->assertOk()->assertJsonPath('data.status', 'ready');
    $this->postJson('/api/government-ids/'.$id->id.'/applicability')->assertOk();
});

it('does not allow inactive residents or CMS accounts to read inventory', function (array $attributes) {
    Sanctum::actingAs(User::factory()->create($attributes));
    $this->getJson('/api/auth/inventory')->assertForbidden();
})->with([[['role' => 'researcher']], [['is_active' => false]]]);

it('passes inventory ownership to the public planner and reflects subsequent inventory removal', function () {
    $resident = User::factory()->create();
    $target = GovernmentId::create(['name' => 'Target']);
    $owned = GovernmentId::create(['name' => 'Recorded Permit']);
    $missing = GovernmentId::create(['name' => 'Missing ID']);
    $document = Document::create(['name' => 'Recorded Document']);
    foreach ([$owned, $missing] as $id) {
        GovernmentIdPrerequisite::create(['government_id_id' => $target->id, 'prerequisite_government_id_id' => $id->id]);
    }
    GovernmentIdPrerequisite::create(['government_id_id' => $target->id, 'prerequisite_document_id' => $document->id]);
    Sanctum::actingAs($resident);
    $inventory = $this->putJson('/api/auth/inventory', ['government_id_ids' => [$owned->id], 'document_ids' => [$document->id]])->assertOk()->json('data');
    $context = [
        'age' => 25, 'application_type' => 'new',
        'owned_government_id_ids' => collect($inventory['government_ids'])->where('owned', true)->pluck('id')->all(),
        'owned_document_ids' => collect($inventory['documents'])->where('owned', true)->pluck('id')->all(),
    ];
    $url = '/api/government-ids/'.$target->id;
    $plan = $this->postJson($url.'/sequence', $context)->assertOk()->assertJsonPath('data.plan_is_complete', true)->json('data');
    expect(array_column($plan['satisfied'], 'id'))->toBe([$owned->id, $document->id])
        ->and(array_column($plan['plan'], 'id'))->toBe([$missing->id, $target->id]);
    $this->postJson($url.'/applicability', $context)->assertOk();
    $this->putJson('/api/auth/inventory', ['government_id_ids' => [], 'document_ids' => []])->assertOk();
    $empty = $this->getJson('/api/auth/inventory')->json('data');
    expect(collect($empty['government_ids'])->where('owned', true))->toBeEmpty()
        ->and(collect($empty['documents'])->where('owned', true))->toBeEmpty();
    $this->postJson($url.'/sequence', ['age' => 25, 'application_type' => 'new'])->assertOk()->assertJsonCount(0, 'data.satisfied')->assertJsonCount(4, 'data.plan');
    expect($resident->fresh()->ownedGovernmentIds()->count())->toBe(0);
});

it('does not infer inventory from viewing context or selecting an application option', function () {
    $resident = User::factory()->create();
    $id = GovernmentId::create(['name' => 'Viewed ID']);
    $profile = $id->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'all']);
    Sanctum::actingAs($resident);
    $this->getJson('/api/government-ids/'.$id->id)->assertOk();
    $this->postJson('/api/government-ids/'.$id->id.'/applicability', ['selected_profile_id' => $profile->id])->assertOk();
    $this->postJson('/api/government-ids/'.$id->id.'/sequence', ['profile_selections' => [$id->id => $profile->id]])->assertOk();
    expect($resident->ownedGovernmentIds()->count())->toBe(0)->and($resident->ownedDocuments()->count())->toBe(0);
});
