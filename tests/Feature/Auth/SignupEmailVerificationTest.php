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
    $this->postJson('/api/auth/email-verification/send', ['email' => 'VERIFY@example.com'])->assertStatus(429);
    Mail::assertSentCount(1);
    $this->travel(61)->seconds();
    $this->postJson('/api/auth/email-verification/send', ['email' => 'verify@example.com'])->assertOk();
    Mail::assertSentCount(2);
});

test('mail delivery failure removes the challenge and returns a safe error', function () {
    Mail::shouldReceive('to')->andThrow(new RuntimeException('Private provider information'));
    $this->postJson('/api/auth/email-verification/send', ['email' => 'verify@example.com'])
        ->assertStatus(503)->assertJsonPath('message', 'Unable to send the verification email. Please try again later.');
    $this->assertDatabaseCount('signup_email_verifications', 0);
});

test('the verification email renders the code', function () {
    $mail = new SignupVerificationCode('123456');
    expect($mail->render())->toContain('123456', '10 minutes');
});
