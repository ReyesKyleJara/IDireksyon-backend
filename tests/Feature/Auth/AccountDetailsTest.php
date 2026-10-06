<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('account updates affect only the resident and reject duplicate email', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $this->withToken($user->createToken('test')->plainTextToken);
    $this->postJson('/api/auth/account', ['name' => 'Updated Name', 'email' => $other->email])->assertUnprocessable();
    $this->postJson('/api/auth/account', ['name' => ' Updated Name ', 'email' => ' NEW@example.com ', 'user_id' => $other->id, 'role' => 'super_admin'])
        ->assertOk()->assertJsonPath('user.name', 'Updated Name')->assertJsonPath('user.email', 'new@example.com');
    expect($user->fresh()->role)->toBe('resident');
    expect($user->fresh()->email_verified_at)->toBeNull();
    expect($other->fresh()->email)->toBe($other->email);
});

test('phone account saves normalized mobile number', function () {
    $user = User::factory()->create(['email' => null, 'phone' => '+639171234567']);
    $this->withToken($user->createToken('test')->plainTextToken)
        ->postJson('/api/auth/account', ['name' => 'Resident', 'phone' => '09181234567'])
        ->assertOk()->assertJsonPath('user.phone', '+639181234567');
});

test('password update checks current password and confirmation before saving', function () {
    $user = User::factory()->create(['password' => Hash::make('old-password')]);
    $this->withToken($user->createToken('test')->plainTextToken);
    $body = ['current_password' => 'wrong', 'password' => 'new-password', 'password_confirmation' => 'new-password'];
    $this->postJson('/api/auth/password', $body)->assertUnprocessable();
    expect(Hash::check('old-password', $user->fresh()->password))->toBeTrue();
    $body['current_password'] = 'old-password';
    $body['password_confirmation'] = 'mismatch';
    $this->postJson('/api/auth/password', $body)->assertUnprocessable();
    $body['password_confirmation'] = 'new-password';
    $this->postJson('/api/auth/password', $body)->assertOk();
    expect(Hash::check('new-password', $user->fresh()->password))->toBeTrue();
});

test('account endpoints require an active resident session', function () {
    $this->postJson('/api/auth/account', [])->assertUnauthorized();
    $this->postJson('/api/auth/password', [])->assertUnauthorized();
    $user = User::factory()->create(['is_active' => false]);
    $this->withToken($user->createToken('test')->plainTextToken);
    $this->postJson('/api/auth/account', [])->assertForbidden();
    $this->postJson('/api/auth/password', [])->assertForbidden();
});
