<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

test('reset email contains a working link and reset revokes sessions and consumes token', function () {
    Notification::fake();
    $user = User::factory()->create();
    $user->createToken('old-session');
    $this->postJson('/api/auth/forgot-password', ['email' => strtoupper($user->email)])->assertOk();
    $notification = Notification::sent($user, ResetPassword::class)->sole();
    $mail = $notification->toMail($user);
    expect($mail->actionUrl)->toContain('/reset-password/'.$notification->token);
    $this->get($mail->actionUrl)->assertOk();
    $payload = ['token' => $notification->token, 'email' => $user->email,
        'password' => 'NewValid12!', 'password_confirmation' => 'NewValid12!'];
    $this->post('/reset-password', $payload)->assertOk()->assertSee('Password reset successful');
    expect(Hash::check('NewValid12!', $user->fresh()->password))->toBeTrue();
    expect($user->tokens()->count())->toBe(0);
    $this->post('/reset-password', $payload)->assertSessionHasErrors('email');
    $this->postJson('/api/auth/login', ['identifier' => $user->email, 'password' => 'NewValid12!'])->assertOk();
});

test('reset requests hide unknown and inactive accounts and throttle duplicate mail', function () {
    Notification::fake();
    $user = User::factory()->create();
    $inactive = User::factory()->create(['is_active' => false]);
    $response = $this->postJson('/api/auth/forgot-password', ['email' => $user->email])->assertOk();
    $this->postJson('/api/auth/forgot-password', ['email' => 'unknown@example.com'])
        ->assertOk()->assertExactJson($response->json());
    $this->postJson('/api/auth/forgot-password', ['email' => $inactive->email])
        ->assertOk()->assertExactJson($response->json());
    $this->postJson('/api/auth/forgot-password', ['email' => $user->email])->assertOk();
    Notification::assertSentToTimes($user, ResetPassword::class, 1);
    Notification::assertNotSentTo($inactive, ResetPassword::class);
});

test('invalid expired and mismatched reset credentials cannot change the password', function () {
    $user = User::factory()->create();
    $token = Password::createToken($user);
    $payload = ['token' => $token, 'email' => $user->email,
        'password' => 'NewValid12!', 'password_confirmation' => 'mismatch'];
    $this->post('/reset-password', $payload)->assertSessionHasErrors('password');
    $payload['password_confirmation'] = $payload['password'];
    $this->post('/reset-password', array_merge($payload, ['token' => 'invalid']))->assertSessionHasErrors('email');
    $this->travel(61)->minutes();
    $this->post('/reset-password', $payload)->assertSessionHasErrors('email');
    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

test('reset validates email and renders an email through the configured mailer', function () {
    $this->postJson('/api/auth/forgot-password', ['email' => 'invalid'])->assertUnprocessable();
    User::factory()->create(['email' => 'resident@example.com']);
    $this->postJson('/api/auth/forgot-password', ['email' => 'resident@example.com'])->assertOk();
    expect(app('mailer')->getSymfonyTransport()->messages())->toHaveCount(1);
});
