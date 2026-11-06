<?php

namespace App\Services;

use App\Mail\SignupVerificationCode;
use Illuminate\Http\Exceptions\HttpResponseException;
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
            $seconds = RateLimiter::availableIn($key);
            throw new HttpResponseException(response()->json([
                'message' => "A code was already requested. You can resend in {$seconds}s.",
                'remaining_seconds' => $seconds,
            ], 429, ['Retry-After' => $seconds]));
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
            RateLimiter::clear($key);
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
            // Preserve the deadline on databases with implicit ON UPDATE timestamps.
            DB::table('signup_email_verifications')->where('id', $id)
                ->increment('attempts', 1, ['expires_at' => $row->expires_at]);
            if (! Hash::check($code, $row->code_hash)) {
                return null;
            }
            $token = Str::random(64);
            DB::table('signup_email_verifications')->where('id', $id)->update([
                'token_hash' => hash('sha256', $token),
                'expires_at' => $row->expires_at,
            ]);

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
