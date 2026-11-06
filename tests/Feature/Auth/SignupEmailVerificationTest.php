<?php

use App\Mail\SignupVerificationCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

function signupEmailChallenge(array $overrides = []): string
{
    $id = (string) Str::uuid();
    DB::table('signup_email_verifications')->insert(array_replace([
        'id' => $id, 'email' => 'verify@example.com', 'code_hash' => Hash::make('123456'),
        'attempts' => 0, 'expires_at' => now()->addMinutes(10),
    ], $overrides));

    return $id;
}

function emailSignupPayload(string $proof = '', string $email = 'verify@example.com'): array
{
    return ['email' => $email, 'password' => 'password123',
        'password_confirmation' => 'password123', 'terms_accepted' => true,
        'email_verification_token' => $proof];
}

test('email signup requires mailbox verification and marks the account verified', function () {
    Mail::fake();
    $this->postJson('/api/auth/register', emailSignupPayload())
        ->assertUnprocessable()->assertJsonValidationErrors('email_verification_token');
    $id = $this->postJson('/api/auth/email-verification/send', ['email' => ' VERIFY@example.com '])
        ->assertOk()->assertJsonMissingPath('code')->json('challenge_id');
    $code = null;
    Mail::assertSent(SignupVerificationCode::class, function ($mail) use (&$code) {
        $code = $mail->code;

        return $mail->hasTo('verify@example.com');
    });
    expect(DB::table('users')->count())->toBe(0);
    expect(Hash::check($code, DB::table('signup_email_verifications')->where('id', $id)->value('code_hash')))->toBeTrue();
    $proof = $this->postJson('/api/auth/email-verification/verify', ['challenge_id' => $id, 'code' => $code])
        ->assertOk()->json('email_verification_token');
    $this->postJson('/api/auth/register', emailSignupPayload($proof))->assertCreated();
    expect(DB::table('users')->first()->email_verified_at)->not->toBeNull();
    expect(DB::table('signup_email_verifications')->where('id', $id)->exists())->toBeFalse();
});

test('malformed and registered email addresses are rejected before sending', function () {
    Mail::fake();
    $this->postJson('/api/auth/email-verification/send', ['email' => 'not-an-email'])->assertUnprocessable();
    User::factory()->create(['email' => 'existing@example.com']);
    $this->postJson('/api/auth/email-verification/send', ['email' => 'existing@example.com'])->assertUnprocessable();
    Mail::assertNothingSent();
});

test('incorrect codes lock after five guesses and expired codes cannot verify', function () {
    $id = signupEmailChallenge();
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/auth/email-verification/verify', ['challenge_id' => $id, 'code' => '000000'])->assertUnprocessable();
    }
    $this->postJson('/api/auth/email-verification/verify', ['challenge_id' => $id, 'code' => '123456'])->assertUnprocessable();
    $expired = signupEmailChallenge(['expires_at' => now()->subSecond()]);
    $this->postJson('/api/auth/email-verification/verify', ['challenge_id' => $expired, 'code' => '123456'])->assertUnprocessable();
});

test('proof is bound to the email address and cannot be replayed or used after expiry', function () {
    $id = signupEmailChallenge();
    $proof = $this->postJson('/api/auth/email-verification/verify', ['challenge_id' => $id, 'code' => '123456'])
        ->assertOk()->json('email_verification_token');
    $this->postJson('/api/auth/email-verification/verify', ['challenge_id' => $id, 'code' => '123456'])->assertUnprocessable();
    $this->postJson('/api/auth/register', emailSignupPayload($proof, 'other@example.com'))
        ->assertUnprocessable()->assertJsonValidationErrors('email_verification_token');
    $this->postJson('/api/auth/register', emailSignupPayload($proof))->assertCreated();
    $this->postJson('/api/auth/register', emailSignupPayload($proof, 'other@example.com'))->assertUnprocessable();
    $id = signupEmailChallenge(['email' => 'expired@example.com']);
    $proof = $this->postJson('/api/auth/email-verification/verify', ['challenge_id' => $id, 'code' => '123456'])
        ->assertOk()->json('email_verification_token');
    $this->travel(11)->minutes();
    $this->postJson('/api/auth/register', emailSignupPayload($proof, 'expired@example.com'))->assertUnprocessable();
});

test('resending has a normalized destination cooldown', function () {
    Mail::fake();
    $this->postJson('/api/auth/email-verification/send', ['email' => 'verify@example.com'])->assertOk();
    $this->travel(18)->seconds();
    $this->postJson('/api/auth/email-verification/send', ['email' => 'VERIFY@example.com'])
        ->assertStatus(429)->assertHeader('Retry-After', '42')
        ->assertJsonPath('remaining_seconds', 42)
        ->assertJsonPath('message', 'A code was already requested. You can resend in 42s.');
    Mail::assertSentCount(1);
    $this->travel(41)->seconds();
    $this->postJson('/api/auth/email-verification/send', ['email' => 'verify@example.com'])
        ->assertStatus(429)->assertJsonPath('remaining_seconds', 1);
    $this->travel(1)->seconds();
    $this->postJson('/api/auth/email-verification/send', ['email' => 'verify@example.com'])->assertOk();
    Mail::assertSentCount(2);
});

test('mail delivery failure removes the challenge and returns a safe error', function () {
    Mail::shouldReceive('to')->andThrow(new RuntimeException('Private provider information'));
    $this->postJson('/api/auth/email-verification/send', ['email' => 'verify@example.com'])
        ->assertStatus(503)->assertJsonPath('message', 'Unable to send the verification email. Please try again later.');
    $this->assertDatabaseCount('signup_email_verifications', 0);
    Mail::swap(new \Illuminate\Mail\MailManager(app()));
    Mail::fake();
    $this->postJson('/api/auth/email-verification/send', ['email' => 'verify@example.com'])
        ->assertOk()->assertJsonPath('expires_in', 600)->assertJsonPath('resend_after', 60);
    Mail::assertSentCount(1);
});

test('the verification email renders the code', function () {
    $mail = new SignupVerificationCode('123456');
    expect($mail->render())->toContain('123456', '10 minutes');
});


test('public auth endpoint throttles have independent counters and retain their limits', function () {
    Mail::fake();
    foreach ([
        '/api/auth/login' => 5,
        '/api/auth/register' => 5,
        '/api/auth/forgot-password' => 5,
        '/api/auth/email-verification/verify' => 20,
        '/api/auth/email-verification/send' => 5,
    ] as $path => $limit) {
        // Invalid requests exercise middleware without sending mail or creating accounts.
        for ($i = 0; $i < $limit; $i++) {
            $this->postJson($path, [])->assertUnprocessable();
        }
        $this->postJson($path, [])->assertStatus(429)->assertHeader('Retry-After');
    }
    Mail::assertNothingSent();
});


test('real sent code persists a database proof across cache changes and normalized registration', function (string $sendEmail, string $registerEmail) {
    Mail::fake();
    $id = $this->postJson('/api/auth/email-verification/send', ['email' => $sendEmail])
        ->assertOk()->assertJsonPath('expires_in', 600)->json('challenge_id');
    $code = null;
    Mail::assertSent(SignupVerificationCode::class, function ($mail) use (&$code) {
        $code = $mail->code;
        return $mail->hasTo('verify@example.com');
    });
    $before = DB::table('signup_email_verifications')->where('id', $id)->first();
    expect($before->email)->toBe('verify@example.com');
    expect($before->token_hash)->toBeNull();

    $proof = $this->postJson('/api/auth/email-verification/verify', ['challenge_id' => $id, 'code' => $code])
        ->assertOk()->assertJsonStructure(['email_verification_token'])->json('email_verification_token');
    expect(strlen($proof))->toBe(64);
    $verified = DB::table('signup_email_verifications')->where('id', $id)->first();
    expect($verified->token_hash)->toBe(hash('sha256', $proof));
    expect($verified->expires_at)->toBe($before->expires_at);
    expect($verified->attempts)->toBe(1);
    $this->assertDatabaseCount('users', 0);

    // Only rate limits use cache; losing cache must not lose the verified proof.
    \Illuminate\Support\Facades\Cache::flush();
    $this->postJson('/api/auth/register', emailSignupPayload($proof, $registerEmail))
        ->assertCreated()->assertJsonPath('user.email', 'verify@example.com');
    expect(User::where('email', 'verify@example.com')->first()->email_verified_at)->not->toBeNull();
    $this->assertDatabaseMissing('signup_email_verifications', ['id' => $id]);
    $this->postJson('/api/auth/register', emailSignupPayload($proof, 'other@example.com'))
        ->assertUnprocessable()->assertJsonValidationErrors('email_verification_token');
    // Even if the original email becomes available again, the consumed proof is gone.
    User::where('email', 'verify@example.com')->delete();
    $this->postJson('/api/auth/register', emailSignupPayload($proof, $registerEmail))
        ->assertUnprocessable()->assertJsonValidationErrors('email_verification_token');
    $this->assertDatabaseCount('users', 0);
})->with([
    [' VERIFY@example.com ', 'verify@example.com'],
    ['verify@example.com', ' VeRiFy@EXAMPLE.COM '],
]);

test('wrong or expired sent code never authorizes registration', function (bool $expired) {
    Mail::fake();
    $id = $this->postJson('/api/auth/email-verification/send', ['email' => 'verify@example.com'])
        ->assertOk()->json('challenge_id');
    $code = null;
    Mail::assertSent(SignupVerificationCode::class, function ($mail) use (&$code) {
        $code = $mail->code;
        return true;
    });
    if ($expired) {
        $this->travel(601)->seconds();
    } else {
        $code = $code === '000000' ? '111111' : '000000';
    }
    $this->postJson('/api/auth/email-verification/verify', ['challenge_id' => $id, 'code' => $code])
        ->assertUnprocessable()->assertJsonValidationErrors('code')->assertJsonMissingPath('email_verification_token');
    expect(DB::table('signup_email_verifications')->where('id', $id)->value('token_hash'))->toBeNull();
    $this->postJson('/api/auth/register', emailSignupPayload(str_repeat('x', 64)))
        ->assertUnprocessable()->assertJsonPath('errors.email_verification_token.0', 'Please verify this email address before creating your account.');
    $this->assertDatabaseCount('users', 0);
})->with([false, true]);

test('registration validation failure preserves a verified proof for a corrected retry', function () {
    $id = signupEmailChallenge();
    $proof = $this->postJson('/api/auth/email-verification/verify', ['challenge_id' => $id, 'code' => '123456'])
        ->assertOk()->json('email_verification_token');
    $this->postJson('/api/auth/register', array_replace(emailSignupPayload($proof), ['password_confirmation' => 'wrong']))
        ->assertUnprocessable()->assertJsonValidationErrors('password');
    $this->assertDatabaseHas('signup_email_verifications', ['id' => $id, 'token_hash' => hash('sha256', $proof)]);
    $this->postJson('/api/auth/register', emailSignupPayload($proof))->assertCreated();
    $this->assertDatabaseMissing('signup_email_verifications', ['id' => $id]);
});


test('failed and successful code checks preserve the original deadline through registration', function () {
    $id = signupEmailChallenge();
    $deadline = DB::table('signup_email_verifications')->where('id', $id)->value('expires_at');
    $this->travel(30)->seconds();
    $this->postJson('/api/auth/email-verification/verify', ['challenge_id' => $id, 'code' => '000000'])
        ->assertUnprocessable()->assertJsonValidationErrors('code');
    $this->assertDatabaseHas('signup_email_verifications', [
        'id' => $id, 'expires_at' => $deadline, 'attempts' => 1, 'token_hash' => null,
    ]);
    $proof = $this->postJson('/api/auth/email-verification/verify', ['challenge_id' => $id, 'code' => '123456'])
        ->assertOk()->json('email_verification_token');
    $this->assertDatabaseHas('signup_email_verifications', [
        'id' => $id, 'expires_at' => $deadline, 'attempts' => 2, 'token_hash' => hash('sha256', $proof),
    ]);
    $this->travel(30)->seconds();
    $this->postJson('/api/auth/register', emailSignupPayload($proof))->assertCreated();
    $this->assertDatabaseMissing('signup_email_verifications', ['id' => $id]);
});
