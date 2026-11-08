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

test('account edits can change the birthday, validate it, and keep it when omitted', function () {
    $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    $user = User::factory()->create(['date_of_birth' => '2000-01-01']);
    $other = User::factory()->create(['date_of_birth' => '1990-05-05']);
    $this->withToken($user->createToken('test')->plainTextToken);
    $body = ['name' => $user->name, 'email' => $user->email];

    $this->postJson('/api/auth/account', [...$body, 'date_of_birth' => '1999-12-25', 'user_id' => $other->id])
        ->assertOk()->assertJsonPath('user.date_of_birth', '1999-12-25');
    expect($user->fresh()->date_of_birth)->toStartWith('1999-12-25')
        ->and($other->fresh()->date_of_birth)->toStartWith('1990-05-05');

    foreach ([now()->toDateString(), now()->addDay()->toDateString(), 'not a date', null] as $invalid) {
        $this->postJson('/api/auth/account', [...$body, 'date_of_birth' => $invalid])
            ->assertUnprocessable()->assertJsonValidationErrors('date_of_birth');
    }
    $this->postJson('/api/auth/account', $body)->assertOk();
    expect($user->fresh()->date_of_birth)->toStartWith('1999-12-25');
});

test('profile returns onboarding barangay and account edits validate and preserve it', function () {
    $first = \App\Models\Barangay::create(['name' => 'Poblacion']);
    $second = \App\Models\Barangay::create(['name' => 'San Vicente']);
    $user = User::factory()->create(['barangay_id' => $first->id]);
    $other = User::factory()->create(['barangay_id' => $first->id]);
    $this->withToken($user->createToken('test')->plainTextToken)
        ->getJson('/api/auth/me')->assertOk()
        ->assertJsonPath('user.barangay_id', $first->id)
        ->assertJsonPath('user.barangay_name', 'Poblacion');
    $body = ['name' => $user->name, 'email' => $user->email];
    $this->postJson('/api/auth/account', [...$body, 'barangay_id' => $second->id, 'user_id' => $other->id])
        ->assertOk()->assertJsonPath('user.barangay_name', 'San Vicente');
    expect($user->fresh()->barangay_id)->toBe($second->id)
        ->and($other->fresh()->barangay_id)->toBe($first->id);
    foreach ([999999, null, 'invalid'] as $invalid) {
        $this->postJson('/api/auth/account', [...$body, 'barangay_id' => $invalid])
            ->assertUnprocessable()->assertJsonValidationErrors('barangay_id');
    }
    $this->postJson('/api/auth/account', $body)->assertOk()
        ->assertJsonPath('user.barangay_id', $second->id);
    $this->getJson('/api/auth/me')->assertOk()->assertJsonPath('user.barangay_name', 'San Vicente');
});
