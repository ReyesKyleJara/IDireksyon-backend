<?php

namespace App\Services;

use App\Mail\SignupVerificationCode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SignupEmailVerification
{
    public function send(string $email): string
    {
        $key = 'signup-email:'.hash('sha256', $email);
        if (RateLimiter::tooManyAttempts($key, 1)) {
            abort(429, 'Please wait a minute before requesting another code.');
        }
        RateLimiter::hit($key, 60);
        $id = (string) Str::uuid();
        $code = (string) random_int(100000, 999999);

        DB::table('signup_email_verifications')->insert([
            'id' => $id, 'email' => $email, 'code_hash' => Hash::make($code),
            'attempts' => 0, 'expires_at' => now()->addMinutes(10),
        ]);
        try {
            if (! app()->environment('local', 'testing') && in_array(config('mail.default'), ['log', 'array'])) {
                throw new \RuntimeException('A live email transport is required.');
            }
            Mail::to($email)->send(new SignupVerificationCode($code));
        } catch (\Throwable $error) {
            DB::table('signup_email_verifications')->where('id', $id)->delete();
            // Do not log provider exceptions: they may contain the code or credentials.
            Log::error('Signup verification email could not be sent.');
            abort(503, 'Unable to send the verification email. Please try again later.');
        }
        DB::table('signup_email_verifications')->where('expires_at', '<', now())->delete();

        return $id;
    }

    public function verify(string $id, string $code): string
    {
        // Commit failed attempts before throwing validation errors.
        $token = DB::transaction(function () use ($id, $code) {
            $row = DB::table('signup_email_verifications')->where('id', $id)->lockForUpdate()->first();
            if (! $row || Carbon::parse($row->expires_at)->isPast() || $row->attempts >= 5 || $row->token_hash) {
                return null;
            }
            DB::table('signup_email_verifications')->where('id', $id)->increment('attempts');
            if (! Hash::check($code, $row->code_hash)) {
                return null;
            }
            $token = Str::random(64);
            DB::table('signup_email_verifications')->where('id', $id)->update(['token_hash' => hash('sha256', $token)]);

            return $token;
        });
        if (! $token) {
            throw ValidationException::withMessages(['code' => 'The code is incorrect, expired, or already used. Request a new code after five incorrect attempts.']);
        }

        return $token;
    }

    // Must run inside the registration transaction to consume proof atomically.
    public function consume(string $email, string $token): void
    {
        $row = DB::table('signup_email_verifications')->where('token_hash', hash('sha256', $token))->lockForUpdate()->first();
        if (! $row || $row->email !== $email || Carbon::parse($row->expires_at)->isPast()) {
            throw ValidationException::withMessages(['email_verification_token' => 'Please verify this email address before creating your account.']);
        }
        DB::table('signup_email_verifications')->where('id', $row->id)->delete();
    }
}
