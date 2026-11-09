<?php

it('serves both policy pages publicly without logging in', function (string $path, string $heading) {
    $this->assertGuest();

    $this->get($path)->assertOk()->assertSee($heading);
})->with([
    'privacy' => ['/privacy', 'Privacy Policy'],
    'account deletion' => ['/account-deletion', 'Delete your IDireksyon account'],
]);

it('covers the information the app actually handles', function () {
    $this->get('/privacy')->assertOk()
        ->assertSee('birth date')
        ->assertSee('barangay')
        ->assertSee('IDs and documents you own')
        ->assertSee('Roadmap progress:')
        ->assertSee('saved roadmap progress')
        ->assertSee('save your inventory and roadmap progress')
        ->assertDontSee('journey')
        ->assertSee('Location')
        ->assertSee('Profile photo')
        ->assertSee('Railway')
        ->assertSee('Google Maps Platform')
        ->assertSee('Resend')
        ->assertSee('does not sell')
        ->assertSee('not an official government app');
});

it('explains the in-app deletion path and what is deleted', function () {
    $this->get('/account-deletion')->assertOk()
        ->assertSee('Password &amp; security', false)
        ->assertSee('Delete account')
        ->assertSee('inventory')
        ->assertSee('Roadmap progress, choices, and completed steps')
        ->assertSee('If you cannot log in');
});

it('never ties the public pages to a hosting domain', function (string $path) {
    $this->get($path)->assertOk()
        ->assertDontSee('railway.app')
        ->assertDontSee(config('app.url').$path);
})->with(['/privacy', '/account-deletion']);

it('names GeoBytes as the developer on both pages', function (string $path) {
    $this->get($path)->assertOk()->assertSee('GeoBytes');
})->with(['/privacy', '/account-deletion']);

it('uses the configured contact as the deletion request method', function () {
    config(['services.idireksyon.contact_email' => 'help@test.invalid']);

    $this->get('/account-deletion')->assertOk()
        ->assertSee('mailto:help@test.invalid?subject=IDireksyon%20account%20deletion%20request', false)
        ->assertSee('email address or mobile number on your account');
});

it('says no contact is published until one is configured', function () {
    config(['services.idireksyon.contact_email' => null]);

    $this->get('/privacy')->assertOk()->assertSee('has not been published yet')->assertDontSee('mailto:', false);
    $this->get('/account-deletion')->assertOk()->assertSee('has not been published yet')->assertDontSee('mailto:', false);
});

it('shows the configured contact on both pages', function () {
    config(['services.idireksyon.contact_email' => 'help@test.invalid']);

    $this->get('/privacy')->assertOk()->assertSee('mailto:help@test.invalid', false)->assertDontSee('has not been published yet');
    $this->get('/account-deletion')->assertOk()->assertSee('mailto:help@test.invalid', false)->assertDontSee('has not been published yet');
});
