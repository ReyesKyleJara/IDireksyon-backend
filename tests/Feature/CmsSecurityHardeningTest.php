<?php

use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\Office;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

const CMS_PASSWORD = 'Correct-Horse-Battery-12';

function cmsUser(string $role, bool $active = true, ?string $username = null): User
{
    return User::factory()->create([
        'role' => $role, 'is_active' => $active, 'password' => CMS_PASSWORD,
        'username' => $username ?? 'user'.fake()->unique()->numberBetween(1000, 999999),
    ]);
}

function fromIp($test, string $ip)
{
    return $test->withServerVariables(['REMOTE_ADDR' => $ip]);
}

function attemptLogin($test, string $username, string $password = 'wrong-password')
{
    auth()->forgetGuards();

    return $test->post('/login', ['username' => $username, 'password' => $password]);
}

function isThrottled(): bool
{
    return str_contains((string) session('errors')?->first('username'), 'Too many login attempts');
}

function sessionId($response): string
{
    return $response->getCookie(config('session.cookie'), true)->getValue();
}

function requestWithSession($test, string $id, string $method, string $path)
{
    auth()->forgetGuards();

    return $test->withCookie(config('session.cookie'), $id)->call($method, $path);
}

/** @return array<int, array{0: string, 1: string, 2: ?string}> */
function everyAdminRoute(): array
{
    GovernmentId::create(['name' => 'Sample ID']);
    Document::create(['name' => 'Sample Document']);
    Office::create(['name' => 'Sample Office']);

    $routes = [];
    foreach (Route::getRoutes()->getRoutes() as $route) {
        if (! str_starts_with($route->uri(), 'admin')) {
            continue;
        }
        foreach (array_diff($route->methods(), ['HEAD']) as $method) {
            $path = preg_replace_callback('/\{(\w+)\??\}/', fn ($match) => match ($match[1]) {
                'step' => 'basic', 'type' => 'government-ids', default => '1',
            }, $route->uri());
            $routes[] = [$method, '/'.$path, $route->getName()];
        }
    }

    return $routes;
}

/** Trusted hosts and CSRF are skipped under the testing environment. */
function behaveLikeProduction($test): void
{
    app()['env'] = 'production';
}

function securityLogFile(): string
{
    $path = tempnam(sys_get_temp_dir(), 'security-log');
    config(['logging.channels.security' => ['driver' => 'single', 'path' => $path, 'level' => 'info']]);

    return $path;
}

afterEach(fn () => Request::setTrustedHosts([]));

describe('authorization', function () {
    it('sends guests to the login page from every admin route', function () {
        $routes = everyAdminRoute();
        expect(count($routes))->toBeGreaterThan(50);

        foreach ($routes as [$method, $path]) {
            $response = $this->call($method, $path);
            expect([$path, $response->getStatusCode(), $response->headers->get('Location')])
                ->toBe([$path, 302, url('/login')]);
        }
    });

    it('forbids every admin route to accounts without CMS access', function (string $role, bool $active) {
        $routes = everyAdminRoute();
        $this->actingAs(cmsUser($role, $active));

        foreach ($routes as [$method, $path]) {
            expect([$path, $this->call($method, $path)->getStatusCode()])->toBe([$path, 403]);
        }
    })->with([
        'resident' => ['resident', true],
        'inactive researcher' => ['researcher', false],
        'inactive super admin' => ['super_admin', false],
    ]);

    it('keeps Super Admin functions away from Researchers', function () {
        $routes = array_filter(everyAdminRoute(), fn ($route) => preg_match('/accounts\.|audit-logs|government-ids\.destroy$|documents\.destroy$|delete-impact/', (string) $route[2]));
        $this->actingAs(cmsUser('researcher'));

        expect($routes)->not->toBeEmpty();
        foreach ($routes as [$method, $path]) {
            expect([$path, $this->call($method, $path)->getStatusCode()])->toBe([$path, 403]);
        }
    });

    it('lets each CMS role reach its own pages', function (string $role, array $paths) {
        everyAdminRoute();
        $this->actingAs(cmsUser($role));

        foreach ($paths as $path) {
            $this->get($path)->assertOk();
        }
    })->with([
        'researcher' => ['researcher', ['/admin', '/admin/government-ids', '/admin/offices']],
        'super admin' => ['super_admin', ['/admin', '/admin/users', '/admin/audit-logs', '/admin/government-ids/1/delete-impact']],
    ]);

    it('does not accept a resident API token for the CMS', function () {
        $token = cmsUser('resident')->createToken('resident-app', ['resident'])->plainTextToken;

        $this->withToken($token)->get('/admin')->assertRedirect('/login');
    });
});

describe('authentication', function () {
    it('signs in active CMS accounts', function (string $role) {
        cmsUser($role, true, 'staffmember');

        attemptLogin($this, 'staffmember', CMS_PASSWORD)->assertRedirect('/admin');
        $this->assertAuthenticated();
    })->with(['researcher', 'super_admin']);

    it('rejects residents and inactive accounts with the normal failure message', function (string $role, bool $active) {
        cmsUser($role, $active, 'blockeduser');

        attemptLogin($this, 'blockeduser', CMS_PASSWORD)
            ->assertSessionHasErrors(['username' => 'Invalid username or password.']);
        $this->assertGuest();
    })->with([
        'resident' => ['resident', true],
        'inactive researcher' => ['researcher', false],
        'inactive super admin' => ['super_admin', false],
    ]);

    it('rejects administrator passwords under 12 characters when changing their own', function (string $role) {
        $user = cmsUser($role);
        $this->actingAs($user);

        $this->put('/password', ['current_password' => CMS_PASSWORD, 'password' => 'elevenchars', 'password_confirmation' => 'elevenchars'])
            ->assertSessionHasErrorsIn('updatePassword', 'password');
        expect(Hash::check(CMS_PASSWORD, $user->refresh()->password))->toBeTrue();

        $this->put('/password', ['current_password' => CMS_PASSWORD, 'password' => 'twelve-chars', 'password_confirmation' => 'twelve-chars'])
            ->assertSessionHasNoErrors();
        expect(Hash::check('twelve-chars', $user->refresh()->password))->toBeTrue();
    })->with(['researcher', 'super_admin']);

    it('rejects administrator passwords under 12 characters when creating accounts', function () {
        $this->actingAs(cmsUser('super_admin'));

        $this->post('/admin/users', [
            'name' => 'New Admin', 'username' => 'newadmin', 'email' => 'new@test.invalid', 'role' => 'researcher', 'is_active' => 1,
            'password' => 'elevenchars', 'password_confirmation' => 'elevenchars',
        ])->assertSessionHasErrors('password');
    });

    it('leaves the resident password rule at 8 characters', function () {
        $this->postJson('/api/auth/register', [
            'phone' => '09171234567', 'password' => 'eightchr', 'password_confirmation' => 'eightchr', 'terms_accepted' => true,
        ])->assertCreated();
    });
});

describe('rate limiting', function () {
    it('blocks one username from one address after 5 failures', function () {
        for ($i = 0; $i < 5; $i++) {
            attemptLogin($this, 'someone');
            expect(isThrottled())->toBeFalse();
        }

        attemptLogin($this, 'someone');
        expect(isThrottled())->toBeTrue();
    });

    it('treats case, spacing and accents as the same username', function (string $variant) {
        for ($i = 0; $i < 5; $i++) {
            attemptLogin($this, 'someone');
        }

        attemptLogin($this, $variant);
        expect(isThrottled())->toBeTrue();
    })->with(['SOMEONE', ' someone ', 'sómeone']);

    it('stops one address spraying many usernames, even with the right password', function () {
        cmsUser('researcher', true, 'realuser');

        for ($i = 0; $i < 30; $i++) {
            attemptLogin($this, 'spray'.$i);
            expect(isThrottled())->toBeFalse();
        }

        attemptLogin($this, 'another-name');
        expect(isThrottled())->toBeTrue();
        attemptLogin($this, 'realuser', CMS_PASSWORD);
        expect(isThrottled())->toBeTrue();
        $this->assertGuest();
    });

    it('stops one username being attacked from many addresses', function () {
        cmsUser('researcher', true, 'targeted');

        for ($i = 1; $i <= 10; $i++) {
            attemptLogin(fromIp($this, "198.51.100.$i"), 'targeted');
            expect(isThrottled())->toBeFalse();
        }

        attemptLogin(fromIp($this, '198.51.100.200'), 'targeted', CMS_PASSWORD);
        expect(isThrottled())->toBeTrue();
        $this->assertGuest();
    });

    it('keeps other people signing in while an account is locked', function () {
        cmsUser('researcher', true, 'bystander');
        for ($i = 1; $i <= 10; $i++) {
            attemptLogin(fromIp($this, "198.51.100.$i"), 'targeted');
        }

        attemptLogin(fromIp($this, '203.0.113.50'), 'bystander', CMS_PASSWORD)->assertRedirect('/admin');
    });

    it('does not let a successful login reset the address limit', function () {
        cmsUser('researcher', true, 'insider');

        for ($i = 0; $i < 29; $i++) {
            attemptLogin($this, 'spray'.$i);
        }
        attemptLogin($this, 'insider', CMS_PASSWORD)->assertRedirect('/admin');
        session()->flush();
        attemptLogin($this, 'spray-final');
        expect(isThrottled())->toBeFalse();

        attemptLogin($this, 'one-more');
        expect(isThrottled())->toBeTrue();
    });

    it('clears a username limit after that account signs in', function () {
        cmsUser('researcher', true, 'forgetful');
        for ($i = 0; $i < 4; $i++) {
            attemptLogin($this, 'forgetful');
        }
        attemptLogin($this, 'forgetful', CMS_PASSWORD)->assertRedirect('/admin');
        session()->flush();

        for ($i = 0; $i < 5; $i++) {
            attemptLogin($this, 'forgetful');
            expect(isThrottled())->toBeFalse();
        }
    });

    it('releases each limit when its window ends', function () {
        for ($i = 1; $i <= 10; $i++) {
            attemptLogin(fromIp($this, "198.51.100.$i"), 'targeted');
        }
        attemptLogin(fromIp($this, '198.51.100.200'), 'targeted');
        expect(isThrottled())->toBeTrue();

        $this->travel(16)->minutes();

        session()->flush();
        attemptLogin(fromIp($this, '198.51.100.200'), 'targeted');
        expect(isThrottled())->toBeFalse();
    });

    it('uses the address the proxy reports, ignoring what the caller added before it', function () {
        for ($i = 0; $i < 5; $i++) {
            attemptLogin(fromIp($this, '10.0.0.1')->withHeader('X-Forwarded-For', "192.0.2.$i, 203.0.113.9"), 'someone');
        }

        attemptLogin(fromIp($this, '10.0.0.1')->withHeader('X-Forwarded-For', '192.0.2.99, 203.0.113.9'), 'someone');
        expect(isThrottled())->toBeTrue();
    });

    it('still limits a username if every request claims a different address', function () {
        for ($i = 1; $i <= 10; $i++) {
            attemptLogin(fromIp($this, '10.0.0.1')->withHeader('X-Forwarded-For', "192.0.2.$i"), 'targeted');
        }

        attemptLogin(fromIp($this, '10.0.0.1')->withHeader('X-Forwarded-For', '192.0.2.200'), 'targeted');
        expect(isThrottled())->toBeTrue();
    });

    it('stores no typed username in limiter keys', function () {
        $key = (new App\Http\Requests\Auth\LoginRequest(request: ['username' => 'Secret-Typed-Value']))->throttleKey();

        expect($key)->not->toContain('secret');
    });
});

describe('sessions', function () {
    it('issues a new session id at login', function () {
        cmsUser('researcher', true, 'staffmember');
        $before = sessionId($this->get('/login'));

        expect(sessionId(attemptLogin($this, 'staffmember', CMS_PASSWORD)))->not->toBe($before);
    });

    it('ends the session on logout', function () {
        cmsUser('researcher', true, 'staffmember');
        $id = sessionId(attemptLogin($this, 'staffmember', CMS_PASSWORD));
        requestWithSession($this, $id, 'GET', '/admin')->assertOk();

        requestWithSession($this, $id, 'POST', '/logout')->assertRedirect('/login');

        requestWithSession($this, $id, 'GET', '/admin')->assertRedirect('/login');
    });

    it('applies account changes to sessions already signed in', function (array $change, int $status) {
        $user = cmsUser('super_admin', true, 'staffmember');
        $id = sessionId(attemptLogin($this, 'staffmember', CMS_PASSWORD));
        requestWithSession($this, $id, 'GET', '/admin')->assertOk();

        DB::table('users')->where('id', $user->id)->update($change);

        expect(requestWithSession($this, $id, 'GET', '/admin')->getStatusCode())->toBe($status);
    })->with([
        'deactivated' => [['is_active' => false], 403],
        'demoted' => [['role' => 'resident'], 403],
        'password changed' => [fn () => ['password' => Hash::make('Another-Long-Password-99')], 302],
    ]);

    it('offers no remember-me option', function () {
        $this->get('/login')->assertDontSee('name="remember"', false);
    });

    it('rejects state-changing requests without a CSRF token', function (string $method, string $path) {
        behaveLikeProduction($this);
        $this->actingAs(cmsUser('super_admin'));

        expect($this->call($method, 'https://www.idireksyon.online'.$path)->getStatusCode())->toBe(419);
    })->with([
        ['POST', '/admin/agencies'],
        ['POST', '/logout'],
        ['PUT', '/password'],
    ]);

    it('rejects a login post without a CSRF token', function () {
        behaveLikeProduction($this);
        cmsUser('researcher', true, 'staffmember');

        $response = $this->post('https://www.idireksyon.online/login', ['username' => 'staffmember', 'password' => CMS_PASSWORD]);

        expect($response->getStatusCode())->toBe(419);
        $this->assertGuest();
    });
});

describe('security headers', function () {
    it('protects every kind of response', function (string $path, int $status) {
        $response = $this->get($path)->assertStatus($status);

        expect($response->headers->get('X-Frame-Options'))->toBe('SAMEORIGIN')
            ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
            ->and($response->headers->get('Referrer-Policy'))->toBe('strict-origin-when-cross-origin')
            ->and($response->headers->get('Permissions-Policy'))->toContain('geolocation=()');
    })->with([
        'landing' => ['/', 200],
        'login' => ['/login', 200],
        'admin redirect' => ['/admin', 302],
        'privacy' => ['/privacy', 200],
        'api' => ['/api/health', 200],
        'not found' => ['/no-such-page', 404],
    ]);

    it('only reports content policy violations for now', function () {
        $response = $this->get('/');

        expect($response->headers->has('Content-Security-Policy'))->toBeFalse()
            ->and($response->headers->get('Content-Security-Policy-Report-Only'))
            ->toContain("default-src 'self'", "frame-ancestors 'self'", "object-src 'none'", 'https://fonts.bunny.net');
    });

    it('sends HSTS over HTTPS only', function () {
        expect($this->get('http://localhost/')->headers->has('Strict-Transport-Security'))->toBeFalse()
            ->and($this->get('https://localhost/')->headers->get('Strict-Transport-Security'))->toBe('max-age=86400');
    });

    it('recognizes HTTPS forwarded by the proxy', function () {
        $response = fromIp($this, '10.0.0.1')->withHeader('X-Forwarded-Proto', 'https')->get('/');

        expect($response->headers->get('Strict-Transport-Security'))->toBe('max-age=86400');
    });

    it('can switch HSTS off', function () {
        config(['security.hsts_max_age' => 0]);

        expect($this->get('https://localhost/')->headers->has('Strict-Transport-Security'))->toBeFalse();
    });

    it('keeps CMS pages out of search engines', function () {
        $this->get('/login')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/admin')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/')->assertHeaderMissing('X-Robots-Tag');
    });
});

describe('trusted hosts', function () {
    it('answers on every host the site and mobile app use', function (string $host) {
        behaveLikeProduction($this);

        $this->get("https://$host/api/health")->assertOk();
        $this->get("https://$host/")->assertOk();
    })->with([
        'www' => 'www.idireksyon.online',
        'apex' => 'idireksyon.online',
        'mobile app api' => 'idireksyon-backend-production.up.railway.app',
    ]);

    it('rejects unknown hosts and lookalikes', function (string $host) {
        behaveLikeProduction($this);

        $this->get("https://$host/")->assertBadRequest();
    })->with([
        'unknown' => 'attacker.invalid',
        'prefix' => 'www.idireksyon.online.attacker.invalid',
        'unescaped dot' => 'wwwxidireksyon.online',
        'other railway app' => 'someone-else.up.railway.app',
    ]);

    it('rejects a forged forwarded host and still sends security headers', function () {
        behaveLikeProduction($this);

        $response = fromIp($this, '10.0.0.1')->withHeader('X-Forwarded-Host', 'attacker.invalid')->get('https://www.idireksyon.online/');

        $response->assertBadRequest();
        expect($response->headers->get('X-Frame-Options'))->toBe('SAMEORIGIN');
    });

    it('sends no reset email for a forged forwarded host', function () {
        behaveLikeProduction($this);
        User::factory()->create(['role' => 'resident', 'email' => 'resident@test.invalid', 'is_active' => true]);

        fromIp($this, '10.0.0.1')->withHeader('X-Forwarded-Host', 'attacker.invalid')
            ->postJson('https://www.idireksyon.online/api/auth/forgot-password', ['email' => 'resident@test.invalid'])
            ->assertBadRequest();

        expect(app('mailer')->getSymfonyTransport()->messages())->toHaveCount(0);
    });

    it('accepts extra hosts from configuration', function () {
        behaveLikeProduction($this);
        config(['security.trusted_hosts' => [...config('security.trusted_hosts'), 'staging.test.invalid']]);

        $this->get('https://staging.test.invalid/api/health')->assertOk();
    });

    it('does not interfere with local development', function () {
        $this->app['env'] = 'local';

        $this->get('http://127.0.0.1:8000/api/health')->assertOk();
    });
});

describe('security audit log', function () {
    it('records sign-in outcomes without passwords or typed usernames', function () {
        $log = securityLogFile();
        $user = cmsUser('super_admin', true, 'auditedadmin');

        attemptLogin(fromIp($this, '203.0.113.77'), 'auditedadmin', 'My-Wrong-Secret-Value');
        attemptLogin(fromIp($this, '203.0.113.77'), 'ghost-account', 'Another-Wrong-Secret');
        attemptLogin(fromIp($this, '203.0.113.77'), 'auditedadmin', CMS_PASSWORD);

        $content = file_get_contents($log);
        expect(substr_count($content, 'auth.login_failed'))->toBe(2)
            ->and(substr_count($content, 'auth.login '))->toBe(1)
            ->and($content)->toContain('"user_id":'.$user->id, '"role":"super_admin"', 'username_hash', 'ip_hash')
            ->not->toContain('My-Wrong-Secret-Value', 'Another-Wrong-Secret', CMS_PASSWORD, 'auditedadmin', 'ghost-account', '203.0.113.77', $user->email);
    });

    it('records lockouts with the limit that applied', function () {
        $log = securityLogFile();

        for ($i = 0; $i < 6; $i++) {
            attemptLogin($this, 'someone');
        }

        $content = file_get_contents($log);
        expect(substr_count($content, 'auth.lockout'))->toBe(1)
            ->and($content)->toContain('"limit":"username_ip"');
    });

    it('produces the same hashes for repeat attempts and different ones for other sources', function () {
        $log = securityLogFile();

        attemptLogin(fromIp($this, '203.0.113.1'), 'someone');
        attemptLogin(fromIp($this, '203.0.113.1'), 'SOMEONE');
        attemptLogin(fromIp($this, '203.0.113.2'), 'someone');

        preg_match_all('/"ip_hash":"([a-f0-9]{16})"/', file_get_contents($log), $ips);
        preg_match_all('/"username_hash":"([a-f0-9]{16})"/', file_get_contents($log), $names);
        expect($ips[1][0])->toBe($ips[1][1])->not->toBe($ips[1][2])
            ->and(array_unique($names[1]))->toHaveCount(1);
    });

    it('does not log resident API sign-ins through this channel', function () {
        $log = securityLogFile();
        User::factory()->create(['role' => 'resident', 'email' => 'resident@test.invalid', 'password' => CMS_PASSWORD, 'is_active' => true]);

        $this->postJson('/api/auth/login', ['identifier' => 'resident@test.invalid', 'password' => CMS_PASSWORD])->assertOk();

        expect(file_get_contents($log))->toBe('');
    });
});

describe('public route compatibility', function () {
    it('keeps public pages open to everyone', function (string $path) {
        $this->assertGuest();
        $this->get($path)->assertOk();
    })->with(['/', '/login', '/privacy', '/terms', '/account-deletion', '/api/health', '/up', '/api/barangays', '/api/offices', '/api/government-ids', '/api/documents']);

    it('offers no CMS link on the public site', function (string $path) {
        expect($this->get($path)->getContent())
            ->not->toContain('href="/admin', 'href="/login');
    })->with(['/', '/privacy', '/terms', '/account-deletion']);

    it('keeps the resident API behind a token', function (string $path) {
        $this->getJson($path)->assertUnauthorized();
    })->with(['/api/auth/me', '/api/auth/inventory', '/api/auth/journeys']);
});
