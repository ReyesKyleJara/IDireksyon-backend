<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('password can be updated', function () {
    $user = User::factory()->create(['role' => 'researcher']);

    $response = $this
        ->actingAs($user)
        ->from('/admin/change-password')
        ->put('/password', [
            'current_password' => 'password',
            'password' => 'New-Resident-pass123!!',
            'password_confirmation' => 'New-Resident-pass123!!',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/admin/change-password');

    $this->assertTrue(Hash::check('New-Resident-pass123!!', $user->refresh()->password));
});

test('correct password must be provided to update password', function () {
    $user = User::factory()->create(['role' => 'researcher']);

    $response = $this
        ->actingAs($user)
        ->from('/admin/change-password')
        ->put('/password', [
            'current_password' => 'wrong-password',
            'password' => 'New-Resident-pass123!!',
            'password_confirmation' => 'New-Resident-pass123!!',
        ]);

    $response
        ->assertSessionHasErrorsIn('updatePassword', 'current_password')
        ->assertRedirect('/admin/change-password');
});
