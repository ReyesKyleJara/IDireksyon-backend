<?php

namespace App\Security;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Records CMS sign-in activity in the security log.
 *
 * Usernames and IPs are stored as keyed hashes: repeats from one source still
 * show up, but the log itself reveals neither. Typed usernames are never kept
 * as written because people sometimes enter a password in that box.
 */
class AuthEventLogger
{
    public function login(Login $event): void
    {
        $this->write('auth.login', [
            'user_id' => $event->user->getAuthIdentifier(),
            'role' => $event->user->role,
        ]);
    }

    public function failed(Failed $event): void
    {
        $this->write('auth.login_failed', [
            // Null when no active CMS account matched.
            'user_id' => $event->user?->getAuthIdentifier(),
            'username_hash' => $this->usernameHash($event->credentials['username'] ?? null),
        ]);
    }

    public function lockout(Lockout $event): void
    {
        $this->write('auth.lockout', [
            'username_hash' => $this->usernameHash($event->request->input('username')),
            'limit' => $event->request->attributes->get('login_lockout_scope'),
        ]);
    }

    private function write(string $name, array $context): void
    {
        Log::channel('security')->info($name, [...$context, 'ip_hash' => $this->hash((string) request()->ip())]);
    }

    private function usernameHash(mixed $username): ?string
    {
        return is_string($username) && $username !== ''
            ? $this->hash(Str::transliterate(Str::lower(trim($username))))
            : null;
    }

    private function hash(string $value): string
    {
        return substr(hash_hmac('sha256', $value, (string) config('app.key')), 0, 16);
    }
}
