<?php

use App\Models\User;

it('shows the landing page and legal pages to visitors who are not logged in', function (string $path, string $text) {
    $this->assertGuest();

    $this->get($path)->assertOk()->assertSee($text);
})->with([
    'landing' => ['/', 'Your Guide to Government IDs'],
    'privacy' => ['/privacy', 'Privacy Policy'],
    'terms' => ['/terms', 'Terms of Service'],
    'account deletion' => ['/account-deletion', 'Delete your IDireksyon account'],
]);

it('states on the landing page that this is not a government service', function () {
    $this->get('/')->assertOk()
        ->assertSee('independent student project')
        ->assertSee('not an official government service');
});

it('links to the contact and legal pages', function () {
    config(['services.idireksyon.contact_email' => 'help@test.invalid']);

    $this->get('/')->assertOk()
        ->assertSee('mailto:help@test.invalid', false)
        ->assertSee('href="/privacy"', false)
        ->assertSee('href="/terms"', false)
        ->assertSee('href="/account-deletion"', false);
});

it('gives visitors no way into the CMS from the landing page', function () {
    $html = $this->get('/')->assertOk()->getContent();

    expect($html)
        ->not->toContain('href="/admin')
        ->not->toContain('href="/login')
        ->not->toContain('/api/')
        ->not->toContain('railway.app')
        ->not->toContain('type="password"')
        ->not->toContain('<form')
        ->not->toContain('Log in');
});

it('does not offer a Google Play download yet', function () {
    expect($this->get('/')->assertOk()->getContent())
        ->not->toContain('play.google.com')
        ->not->toContain('Download');
});

it('keeps sending logged-out visitors from the CMS to the login page', function (string $path) {
    $this->get($path)->assertRedirect('/login');
})->with(['/admin', '/admin/government-ids', '/admin/users', '/admin/audit-logs', '/dashboard']);

it('blocks resident accounts from the CMS', function () {
    $resident = User::factory()->create(['role' => 'resident']);

    $this->actingAs($resident)->get('/admin')->assertForbidden();
});

it('lets search engines index public pages but not CMS pages', function () {
    $this->get('/')->assertOk()->assertHeaderMissing('X-Robots-Tag');
    $this->get('/privacy')->assertOk()->assertHeaderMissing('X-Robots-Tag');
    $this->get('/terms')->assertOk()->assertHeaderMissing('X-Robots-Tag');

    $this->get('/login')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    $this->get('/admin')->assertRedirect('/login')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});

it('keeps the mobile app API available', function () {
    $this->getJson('/api/health')->assertOk()->assertJson(['status' => 'ok']);
});
