<?php

use App\Mail\SignupEmailCode;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
});

function requestSignupCode(string $email = 'new@example.com'): array
{
    $response = test()->postJson('/api/auth/email-verification/send', ['email' => $email])
        ->assertOk()->assertJsonStructure(['challenge_id', 'expires_in']);
    $mail = Mail::sent(SignupEmailCode::class)->last();

    return [$response->json('challenge_id'), $mail->code];
}

function emailRegistration(string $proof, string $email = 'new@example.com'): array
{
    return ['email' => $email, 'password' => 'Valid12!', 'password_confirmation' => 'Valid12!',
        'terms_accepted' => true, 'email_verification_token' => $proof];
}

test('email code flow sends mail verifies and creates a verified resident', function () {
    [$id, $code] = requestSignupCode('NEW@example.com');
    expect(User::count())->toBe(0);
    Mail::assertSent(SignupEmailCode::class, fn ($mail) => $mail->hasTo('new@example.com'));
    $response = $this->postJson('/api/auth/email-verification/verify', ['challenge_id' => $id, 'code' => $code])->assertOk();
    $proof = $response->json('email_verification_token');
    $this->postJson('/api/auth/register', emailRegistration($proof))->assertCreated();
    expect(User::first()->email_verified_at)->not->toBeNull();
});

test('registration requires proof and rejects a proof issued for another email', function () {
    $this->postJson('/api/auth/register', emailRegistration(''))->assertUnprocessable()
        ->assertJsonValidationErrors('email_verification_token');
    [$id, $code] = requestSignupCode();
    $proof = $this->postJson('/api/auth/email-verification/verify', ['challenge_id' => $id, 'code' => $code])
        ->assertOk()->json('email_verification_token');
    $this->postJson('/api/auth/register', emailRegistration($proof, 'other@example.com'))
        ->assertUnprocessable()->assertJsonValidationErrors('email_verification_token');
    expect(User::count())->toBe(0);
    // A rejected attempt does not consume the proof for its actual address.
    $this->postJson('/api/auth/register', emailRegistration($proof))->assertCreated();
});

test('resend has a cooldown and invalidates the previous code', function () {
    [$oldId, $oldCode] = requestSignupCode();
    $this->postJson('/api/auth/email-verification/send', ['email' => 'new@example.com'])
        ->assertUnprocessable()->assertJsonValidationErrors('email');
    $this->travel(61)->seconds();
    requestSignupCode();
    $this->postJson('/api/auth/email-verification/verify', ['challenge_id' => $oldId, 'code' => $oldCode])
        ->assertUnprocessable()->assertJsonValidationErrors('code');
});

test('wrong codes are limited and expired codes cannot verify', function () {
    [$id, $code] = requestSignupCode();
    $wrong = $code === '111111' ? '222222' : '111111';
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/auth/email-verification/verify', ['challenge_id' => $id, 'code' => $wrong])
            ->assertUnprocessable()->assertJsonValidationErrors('code');
    }
    $this->postJson('/api/auth/email-verification/verify', ['challenge_id' => $id, 'code' => $code])->assertUnprocessable();
    $this->travel(61)->seconds();
    [$id, $code] = requestSignupCode();
    $this->travel(10)->minutes();
    $this->postJson('/api/auth/email-verification/verify', ['challenge_id' => $id, 'code' => $code])->assertUnprocessable();
});

test('verified proof is single use and expires after ten minutes', function () {
    [$id, $code] = requestSignupCode();
    $proof = $this->postJson('/api/auth/email-verification/verify', ['challenge_id' => $id, 'code' => $code])
        ->assertOk()->json('email_verification_token');
    $this->travel(10)->minutes();
    $this->postJson('/api/auth/register', emailRegistration($proof))->assertUnprocessable()
        ->assertJsonValidationErrors('email_verification_token');
    [$id, $code] = requestSignupCode();
    $proof = $this->postJson('/api/auth/email-verification/verify', ['challenge_id' => $id, 'code' => $code])
        ->assertOk()->json('email_verification_token');
    $this->postJson('/api/auth/register', emailRegistration($proof))->assertCreated();
    User::first()->delete();
    $this->postJson('/api/auth/register', emailRegistration($proof))->assertUnprocessable()
        ->assertJsonValidationErrors('email_verification_token');
});

test('mail failure returns a usable error and creates no account', function () {
    Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('SMTP unavailable'));
    $this->postJson('/api/auth/email-verification/send', ['email' => 'new@example.com'])
        ->assertStatus(503)->assertJsonPath('message', 'We could not send your verification email. Please try again shortly.');
    expect(User::count())->toBe(0);
});
