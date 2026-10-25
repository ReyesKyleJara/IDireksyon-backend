<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('an eight character password is accepted for registration and password changes', function () {
    $this->postJson('/api/auth/register', [
        'email' => 'eight@example.com',
        'email_verification_token' => signupEmailProof('eight@example.com'),
        'password' => 'Valid12!',
        'password_confirmation' => 'Valid12!',
        'terms_accepted' => true,
    ])->assertCreated();

    $user = User::where('email', 'eight@example.com')->firstOrFail();
    $this->withToken($user->createToken('test')->plainTextToken)
        ->postJson('/api/auth/password', [
            'current_password' => 'Valid12!',
            'password' => 'Other34!',
            'password_confirmation' => 'Other34!',
        ])->assertOk();
    expect(Hash::check('Other34!', $user->fresh()->password))->toBeTrue();
});

test('weak passwords are rejected for registration and password changes', function (string $password) {
    $this->postJson('/api/auth/register', [
        'email' => 'policy@example.com',
        'password' => $password,
        'password_confirmation' => $password,
        'terms_accepted' => true,
    ])->assertUnprocessable()->assertJsonValidationErrors('password');
    expect(User::where('email', 'policy@example.com')->exists())->toBeFalse();

    $user = User::factory()->create(['password' => 'Current-pass123!']);
    $this->withToken($user->createToken('test')->plainTextToken)
        ->postJson('/api/auth/password', [
            'current_password' => 'Current-pass123!',
            'password' => $password,
            'password_confirmation' => $password,
        ])->assertUnprocessable()->assertJsonValidationErrors('password');
    expect(Hash::check('Current-pass123!', $user->fresh()->password))->toBeTrue();
})->with([
    'too short' => 'Short1!',
    'no uppercase' => 'lowercase123!',
    'no lowercase' => 'UPPERCASE123!',
    'no number' => 'MissingNumbers!',
    'no symbol' => 'MissingSymbols123',
    'too long' => str_repeat('Aa1!', 5).'x',
]);

test('resident cannot reuse the current password', function () {
    $user = User::factory()->create(['password' => 'Current-pass123!']);
    $this->withToken($user->createToken('test')->plainTextToken)
        ->postJson('/api/auth/password', [
            'current_password' => 'Current-pass123!',
            'password' => 'Current-pass123!',
            'password_confirmation' => 'Current-pass123!',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');
});

test('a twenty character password is accepted for registration', function () {
    $password = str_repeat('Aa1!', 5);
    $this->postJson('/api/auth/register', [
        'phone' => '09123456789',
        'password' => $password,
        'password_confirmation' => $password,
        'terms_accepted' => true,
    ])->assertCreated();
    expect(Hash::check($password, User::where('phone', '+639123456789')->firstOrFail()->password))->toBeTrue();
});
