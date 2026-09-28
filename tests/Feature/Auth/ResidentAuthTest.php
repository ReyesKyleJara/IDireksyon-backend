<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

function residentData(array $overrides = []): array
{
    return array_replace(['name' => 'Juan', 'email' => 'juan@example.com',
        'password' => 'password123', 'password_confirmation' => 'password123',
        'terms_accepted' => true], $overrides);
}

test('residents register and authenticate with email or normalized phone', function (array $contact, string $identifier) {
    $response = $this->postJson('/api/auth/register', residentData($contact))
        ->assertCreated()->assertJsonStructure(['token', 'user' => ['id', 'name']]);
    $user = User::first();
    expect($user->role)->toBe('resident');
    expect(Hash::check('password123', $user->password))->toBeTrue();
    $this->postJson('/api/auth/login', ['identifier' => $identifier, 'password' => 'password123'])->assertOk();
    $this->withToken($response->json('token'))->getJson('/api/auth/me')->assertOk();
    $this->withToken($response->json('token'))->postJson('/api/auth/logout')->assertOk();
    expect($user->tokens()->count())->toBe(1);
})->with([
    'email' => [['email' => 'JUAN@example.com'], 'juan@example.com'],
    'phone' => [['email' => null, 'phone' => '0909 123 4567'], '+639091234567'],
]);

test('registration validates confirmation agreement and duplicates', function () {
    $this->postJson('/api/auth/register', residentData(['password_confirmation' => 'wrong', 'terms_accepted' => false]))
        ->assertUnprocessable()->assertJsonValidationErrors(['password', 'terms_accepted']);
    $this->postJson('/api/auth/register', residentData())->assertCreated();
    $this->postJson('/api/auth/register', residentData())->assertUnprocessable()->assertJsonValidationErrors('email');
});

test('resident login rejects wrong passwords inactive accounts and cms accounts', function () {
    $user = User::factory()->create(['password' => 'password123']);
    $this->postJson('/api/auth/login', ['identifier' => $user->email, 'password' => 'wrong'])->assertUnprocessable();
    $user->forceFill(['is_active' => false])->save();
    $this->postJson('/api/auth/login', ['identifier' => $user->email, 'password' => 'password123'])->assertUnprocessable();
    $user->forceFill(['is_active' => true, 'role' => 'super_admin'])->save();
    $this->postJson('/api/auth/login', ['identifier' => $user->email, 'password' => 'password123'])->assertUnprocessable();
    expect($user->tokens()->count())->toBe(0);
});

test('resident endpoints require a token and login is rate limited', function () {
    $this->getJson('/api/auth/me')->assertUnauthorized();
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/auth/login', ['identifier' => 'missing@example.com', 'password' => 'wrong'])->assertUnprocessable();
    }
    $this->postJson('/api/auth/login', ['identifier' => 'missing@example.com', 'password' => 'wrong'])->assertStatus(429);
});
