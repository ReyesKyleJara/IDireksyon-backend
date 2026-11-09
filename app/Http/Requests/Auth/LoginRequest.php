<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:80'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt(['username' => strtolower(trim($this->string('username'))), 'password' => $this->input('password'), 'is_active' => true, 'role' => array_keys(User::CMS_ROLES)])) {
            foreach ($this->limits() as $limit) {
                RateLimiter::hit($limit['key'], $limit['decay']);
            }

            throw ValidationException::withMessages([
                'username' => 'Invalid username or password.',
            ]);
        }

        // The IP counter stays: an attacker's own valid login must not reset it.
        RateLimiter::clear($this->limits()['username_ip']['key']);
        RateLimiter::clear($this->limits()['username']['key']);
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        foreach ($this->limits() as $scope => $limit) {
            if (! RateLimiter::tooManyAttempts($limit['key'], $limit['max'])) {
                continue;
            }

            $this->attributes->set('login_lockout_scope', $scope);
            event(new Lockout($this));

            $seconds = RateLimiter::availableIn($limit['key']);

            // One message for every limit, so it reveals nothing about which applied.
            throw ValidationException::withMessages([
                'username' => trans('auth.throttle', [
                    'seconds' => $seconds,
                    'minutes' => ceil($seconds / 60),
                ]),
            ]);
        }
    }

    /**
     * Failed attempts count against all three limits.
     *
     * The username limit ignores the IP, so it holds even if an IP is spoofed or
     * rotated. The IP limit ignores the username, which is what stops one source
     * from trying a common password against many accounts.
     *
     * @return array<string, array{key: string, max: int, decay: int}>
     */
    private function limits(): array
    {
        return [
            'username_ip' => ['key' => $this->throttleKey(), 'max' => 5, 'decay' => 60],
            'username' => ['key' => 'login-username:'.hash('sha256', $this->normalizedUsername()), 'max' => 10, 'decay' => 900],
            'ip' => ['key' => 'login-ip:'.hash('sha256', (string) $this->ip()), 'max' => 30, 'decay' => 900],
        ];
    }

    /**
     * Get the rate limiting throttle key for the request.
     *
     * Hashed because people sometimes type a password into the username box.
     */
    public function throttleKey(): string
    {
        return 'login:'.hash('sha256', $this->normalizedUsername().'|'.$this->ip());
    }

    private function normalizedUsername(): string
    {
        return Str::transliterate(Str::lower(trim($this->string('username'))));
    }
}
