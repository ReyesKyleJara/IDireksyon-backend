<?php

namespace App\Services;

use App\Mail\SignupEmailCode;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SignupEmailVerification
{
    private function key(string $id): string
    {
        return 'signup-email:challenge:'.$id;
    }

    private function emailKey(string $email): string
    {
        return 'signup-email:'.hash('sha256', $email);
    }

    public function send(string $email): string
    {
        return Cache::lock($this->emailKey($email).':lock', 30)->block(3, function () use ($email) {
            $active = Cache::get($this->emailKey($email));
            if ($active && $active['sent_at'] > now()->timestamp - 60) {
                throw ValidationException::withMessages(['email' => 'Please wait 60 seconds before requesting another code.']);
            }
            $id = (string) Str::uuid();
            $code = (string) random_int(100000, 999999);
            // Only replace the previous challenge after delivery succeeds.
            Mail::to($email)->send(new SignupEmailCode($code));
            if ($active) {
                Cache::forget($this->key($active['id']));
            }
            Cache::put($this->key($id), [
                'email' => $email,
                'code_hash' => hash_hmac('sha256', $code, config('app.key')),
                'attempts' => 0,
                'expires_at' => now()->addMinutes(10)->timestamp,
            ], now()->addMinutes(10));
            Cache::put($this->emailKey($email), [
                'id' => $id, 'sent_at' => now()->timestamp,
            ], now()->addMinutes(10));

            return $id;
        });
    }

    public function verify(string $id, string $code): string
    {
        $challenge = Cache::get($this->key($id));
        if (! $challenge) {
            $this->invalidCode();
        }

        return Cache::lock($this->emailKey($challenge['email']).':lock', 30)->block(3, function () use ($id, $code) {
            $challenge = Cache::get($this->key($id));
            if (! $challenge || $challenge['expires_at'] <= now()->timestamp ||
                $challenge['attempts'] >= 5 || isset($challenge['proof_hash'])) {
                $this->invalidCode();
            }
            $challenge['attempts']++;
            Cache::put($this->key($id), $challenge, $challenge['expires_at'] - now()->timestamp);
            if (! hash_equals($challenge['code_hash'], hash_hmac('sha256', $code, config('app.key')))) {
                $this->invalidCode();
            }
            $secret = Str::random(64);
            unset($challenge['code_hash']);
            $challenge['proof_hash'] = hash('sha256', $secret);
            $challenge['expires_at'] = now()->addMinutes(10)->timestamp;
            Cache::put($this->key($id), $challenge, now()->addMinutes(10));
            $active = Cache::get($this->emailKey($challenge['email']));
            Cache::put($this->emailKey($challenge['email']), $active, now()->addMinutes(10));

            return $id.'.'.$secret;
        });
    }

    public function consume(string $email, string $token, Closure $createAccount): mixed
    {
        return Cache::lock($this->emailKey($email).':lock', 30)->block(3, function () use ($email, $token, $createAccount) {
            [$id, $secret] = array_pad(explode('.', $token, 2), 2, '');
            $challenge = Cache::get($this->key($id));
            $active = Cache::get($this->emailKey($email));
            if (! $challenge || ($active['id'] ?? null) !== $id ||
                $challenge['email'] !== $email || $challenge['expires_at'] <= now()->timestamp ||
                ! isset($challenge['proof_hash']) ||
                ! hash_equals($challenge['proof_hash'], hash('sha256', $secret))) {
                throw ValidationException::withMessages([
                    'email_verification_token' => 'Please verify your email again before creating an account.',
                ]);
            }
            $response = $createAccount();
            Cache::forget($this->key($id));
            Cache::forget($this->emailKey($email));

            return $response;
        });
    }

    private function invalidCode(): never
    {
        throw ValidationException::withMessages(['code' => 'The code is incorrect or expired. Request a new code if needed.']);
    }
}
