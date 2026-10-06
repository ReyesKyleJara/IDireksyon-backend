<?php

use App\Models\User;

test('resident setup persists only the authenticated users selections', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;
    $this->withToken($token)->postJson('/api/auth/profile-setup', [
        'ids' => ['PhilSys ID', 'Driver’s License'], 'documents' => ['Birth Certificate'],
        'user_id' => $other->id,
    ])->assertOk()->assertJsonPath('user.owned_ids', ['PhilSys ID', 'Driver’s License']);
    expect($user->fresh()->owned_documents)->toBe(['Birth Certificate']);
    expect($user->fresh()->profile_setup_completed_at)->not->toBeNull();
    expect($other->fresh()->profile_setup_completed_at)->toBeNull();
    $this->withToken($token)->getJson('/api/auth/me')->assertJsonPath('user.owned_documents', ['Birth Certificate']);
});

test('setup permits skipping both steps and rejects invalid selections', function () {
    $user = User::factory()->create();
    $this->withToken($user->createToken('test')->plainTextToken);
    $this->postJson('/api/auth/profile-setup', ['ids' => ['unknown'], 'documents' => []])->assertUnprocessable();
    $this->postJson('/api/auth/profile-setup', ['ids' => [], 'documents' => []])->assertOk();
    expect($user->fresh()->owned_ids)->toBe([]);
    expect($user->fresh()->profile_setup_completed_at)->not->toBeNull();
});

test('setup requires an active resident', function () {
    $this->postJson('/api/auth/profile-setup', ['ids' => [], 'documents' => []])->assertUnauthorized();
    $user = User::factory()->create(['is_active' => false]);
    $this->withToken($user->createToken('test')->plainTextToken)
        ->postJson('/api/auth/profile-setup', ['ids' => [], 'documents' => []])->assertForbidden();
});
