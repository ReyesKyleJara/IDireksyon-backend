<?php

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

test('resident reset email links work and successful reset consumes the token and revokes API tokens', function () {
    Notification::fake();
    Event::fake([PasswordReset::class]);
    $user = User::factory()->create();
    $oldRememberToken = $user->remember_token;
    $oldApiToken = $user->createToken('old-device')->plainTextToken;
    $user->createToken('another-device');
    $other = User::factory()->create();
    $other->createToken('unrelated-device');

    $this->postJson('/api/auth/forgot-password', ['email' => ' '.strtoupper($user->email).' '])->assertOk();
    $notification = Notification::sent($user, ResetPassword::class)->sole();
    $mail = $notification->toMail($user);
    expect($mail->actionUrl)->toContain('/reset-password/'.$notification->token);
    $this->get($mail->actionUrl)->assertOk()
        ->assertSee('IDireksyon | Reset password')
        ->assertSee('between 8 and 128 characters.')
        ->assertSee('name="token"', false);

    // Lowercase-only passwords remain valid under main's existing resident policy.
    $payload = ['token' => $notification->token, 'email' => strtoupper($user->email),
        'password' => 'newpassword', 'password_confirmation' => 'newpassword'];
    $this->post('/reset-password', $payload)->assertOk()
        ->assertViewIs('auth.password-reset-complete')
        ->assertSee('Return to the IDireksyon app');
    expect(Hash::check('newpassword', $user->fresh()->password))->toBeTrue();
    expect($user->fresh()->remember_token)->not->toBe($oldRememberToken);
    expect($user->tokens()->count())->toBe(0);
    expect($other->tokens()->count())->toBe(1);
    $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    Event::assertDispatched(PasswordReset::class, fn ($event) => $event->user->is($user));
    $this->post('/reset-password', $payload)->assertSessionHasErrors('email');
    $this->withToken($oldApiToken)->getJson('/api/auth/me')->assertUnauthorized();
    $this->postJson('/api/auth/login', ['identifier' => $user->email, 'password' => 'password'])
        ->assertUnprocessable();
    $this->postJson('/api/auth/login', ['identifier' => $user->email, 'password' => 'newpassword'])
        ->assertOk()->assertJsonStructure(['token']);
});

test('unknown inactive and staff accounts get the same request response without an email', function (array $attributes) {
    Notification::fake();
    $resident = User::factory()->create();
    $email = 'unknown@example.com';
    if ($attributes) {
        $excluded = User::factory()->create($attributes);
        $email = $excluded->email;
    }
    $response = $this->postJson('/api/auth/forgot-password', ['email' => $resident->email])->assertOk();
    $this->postJson('/api/auth/forgot-password', ['email' => $email, 'role' => 'resident', 'is_active' => true])
        ->assertOk()->assertExactJson($response->json());
    Notification::assertCount(1);
    $this->assertDatabaseMissing('password_reset_tokens', ['email' => $email]);
})->with([
    'unknown' => [[]],
    'inactive resident' => [['is_active' => false]],
    'researcher' => [['role' => 'researcher']],
    'super admin' => [['role' => 'super_admin']],
]);

test('a valid token cannot reset inactive residents or staff accounts', function (array $attributes) {
    $user = User::factory()->create($attributes);
    $token = Password::createToken($user);
    $user->createToken('must-remain');
    $this->post('/reset-password', ['token' => $token, 'email' => $user->email,
        'password' => 'newpassword', 'password_confirmation' => 'newpassword',
        'role' => 'resident', 'is_active' => true])->assertSessionHasErrors('email');
    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
    expect($user->tokens()->count())->toBe(1);
    expect(Password::tokenExists($user, $token))->toBeTrue();
})->with([
    'inactive resident' => [['is_active' => false]],
    'researcher' => [['role' => 'researcher']],
    'super admin' => [['role' => 'super_admin']],
]);

test('resident reset accepts existing password policy boundaries', function (int $length) {
    $user = User::factory()->create();
    $password = str_repeat('a', $length);
    $this->post('/reset-password', ['token' => Password::createToken($user), 'email' => $user->email,
        'password' => $password, 'password_confirmation' => $password])->assertOk();
    expect(Hash::check($password, $user->fresh()->password))->toBeTrue();
})->with([8, 128]);

test('resident reset rejects passwords outside existing policy and mismatched confirmation', function (string $password, string $confirmation) {
    $user = User::factory()->create();
    $token = Password::createToken($user);
    $user->createToken('must-remain');
    $this->post('/reset-password', ['token' => $token, 'email' => $user->email,
        'password' => $password, 'password_confirmation' => $confirmation])->assertSessionHasErrors('password');
    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
    expect($user->tokens()->count())->toBe(1);
    expect(Password::tokenExists($user, $token))->toBeTrue();
})->with([
    'too short' => ['short', 'short'],
    'too long' => [str_repeat('a', 129), str_repeat('a', 129)],
    'mismatch' => ['newpassword', 'differentpassword'],
]);

test('invalid expired and wrong-account reset tokens cannot change passwords', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $token = Password::createToken($user);
    $payload = ['token' => $token, 'email' => $user->email,
        'password' => 'newpassword', 'password_confirmation' => 'newpassword'];
    $this->post('/reset-password', array_merge($payload, ['token' => 'invalid']))->assertSessionHasErrors('email');
    $this->post('/reset-password', array_merge($payload, ['email' => $other->email]))->assertSessionHasErrors('email');
    $this->travel(61)->minutes();
    $this->post('/reset-password', $payload)->assertSessionHasErrors('email');
    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
    expect(Hash::check('password', $other->fresh()->password))->toBeTrue();
});

test('duplicate requests respect the broker mail cooldown without exposing it', function () {
    Notification::fake();
    $user = User::factory()->create();
    $response = $this->postJson('/api/auth/forgot-password', ['email' => $user->email])->assertOk();
    $this->postJson('/api/auth/forgot-password', ['email' => $user->email])
        ->assertOk()->assertExactJson($response->json());
    Notification::assertSentToTimes($user, ResetPassword::class, 1);
    $this->travel(61)->seconds();
    $this->postJson('/api/auth/forgot-password', ['email' => $user->email])->assertOk();
    Notification::assertSentToTimes($user, ResetPassword::class, 2);
});

test('forgot requests and reset submissions are rate limited', function (string $url) {
    for ($i = 0; $i < 5; $i++) {
        $response = $this->postJson($url, ['email' => 'unknown@example.com']);
        $url === '/api/auth/forgot-password'
            ? $response->assertOk()
            : $response->assertUnprocessable()->assertJsonValidationErrors('token');
    }
    $this->postJson($url, ['email' => 'unknown@example.com'])->assertTooManyRequests();
})->with(['/api/auth/forgot-password', '/reset-password']);

test('forgot requests validate email and send mail through the existing configured transport', function () {
    foreach ([null, 'invalid', ['resident@example.com'], str_repeat('a', 250).'@example.com'] as $email) {
        $this->postJson('/api/auth/forgot-password', ['email' => $email])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
    }
    User::factory()->create(['email' => 'resident@example.com']);
    $this->postJson('/api/auth/forgot-password', ['email' => 'resident@example.com'])->assertOk();
    expect(app('mailer')->getSymfonyTransport()->messages())->toHaveCount(1);
});

test('mail delivery failures return a safe error and do not log sensitive provider details', function () {
    User::factory()->create();
    Password::shouldReceive('sendResetLink')->once()
        ->andThrow(new RuntimeException('Private provider credentials and reset URL'));
    Log::shouldReceive('error')->once()->with('Resident password reset email could not be sent.');
    $this->postJson('/api/auth/forgot-password', ['email' => 'resident@example.com'])
        ->assertStatus(503)->assertExactJson([
            'message' => 'Unable to send the reset email. Please try again later.',
        ]);
});

test('resident reset works while CMS staff are signed in without changing that staff account', function () {
    $staff = User::factory()->create(['role' => 'researcher']);
    $resident = User::factory()->create();
    $token = Password::createToken($resident);
    $this->actingAs($staff)->get(route('password.reset', ['token' => $token, 'email' => $resident->email]))
        ->assertOk();
    $this->post('/reset-password', ['token' => $token, 'email' => $resident->email,
        'password' => 'newpassword', 'password_confirmation' => 'newpassword'])->assertOk();
    $this->assertAuthenticatedAs($staff);
    expect(Hash::check('password', $staff->fresh()->password))->toBeTrue();
    $this->get('/admin')->assertOk();
});

test('CMS login keeps its default title and browser forgot-password request routes remain absent', function () {
    $this->get('/login')->assertOk()->assertSee('IDireksyon | Content Management System');
    $this->get('/forgot-password')->assertNotFound();
    $this->post('/forgot-password')->assertNotFound();
});
