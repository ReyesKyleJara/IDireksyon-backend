<?php

use App\Models\User;

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create(['role' => 'researcher']);

    $response = $this->post('/login', [
        'username' => strtoupper($user->username),
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('admin.government-ids.index', absolute: false));
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create(['role' => 'researcher']);

    $this->post('/login', [
        'username' => strtoupper($user->username),
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create(['role' => 'researcher']);

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/login');
});
