<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('requires login and rejects ordinary accounts across CMS routes', function () {
    $this->get('/admin')->assertRedirect('/login');
    $this->post('/admin/documents', ['name' => 'Denied'])->assertRedirect('/login');
    $this->actingAs(User::factory()->create());
    foreach (['/admin', '/admin/documents', '/admin/government-ids', '/admin/reference/offices', '/admin/users'] as $url) {
        $this->get($url)->assertForbidden();
    }
    $this->post('/admin/documents', ['name' => 'Denied'])->assertForbidden();
    $this->assertDatabaseMissing('documents', ['name' => 'Denied']);
});

it('lets researchers maintain content but denies account management even by direct request', function () {
    $researcher = User::factory()->create(['role' => 'researcher']);
    $target = User::factory()->create(['role' => 'super_admin']);
    $this->actingAs($researcher)->get('/admin/government-ids')->assertOk()->assertDontSee('Admin Users');
    $this->get('/admin/documents/create')->assertOk();
    $this->get('/admin/users')->assertForbidden();
    $this->post('/admin/users', [])->assertForbidden();
    $this->put('/admin/users/'.$target->id, ['role' => 'researcher'])->assertForbidden();
    expect($target->fresh()->role)->toBe('super_admin');
});

it('creates and edits staff accounts without exposing or resetting their password accidentally', function () {
    $super = User::factory()->create(['role' => 'super_admin']);
    $this->actingAs($super)->get('/admin')->assertOk()->assertSee('Admin Users')->assertSee($super->name);
    $payload = ['username' => 'research.team', 'name' => 'Research team', 'email' => 'research@example.test', 'role' => 'researcher', 'is_active' => 1, 'password' => 'a-long-test-password', 'password_confirmation' => 'a-long-test-password'];
    $this->post('/admin/users', $payload)->assertSessionHasNoErrors()->assertRedirect('/admin/users');
    $account = User::where('email', $payload['email'])->firstOrFail();
    expect(Hash::check($payload['password'], $account->password))->toBeTrue();
    $this->get('/admin/users/'.$account->id.'/edit')->assertOk()->assertDontSee($account->password)->assertDontSee($payload['password']);
    $hash = $account->password;
    $this->put('/admin/users/'.$account->id, array_merge($payload, ['name' => 'Revised team', 'password' => '', 'password_confirmation' => '']))->assertSessionHasNoErrors();
    expect($account->fresh()->name)->toBe('Revised team')->and($account->fresh()->password)->toBe($hash);
    $this->get('/admin/users?q=Revised&role=researcher&status=active')->assertOk()->assertSee('Revised team')->assertDontSee($super->email);
    $this->post('/admin/users', $payload)->assertSessionHasErrors('email');
    $this->post('/admin/users', array_merge($payload, ['email' => 'other@example.test', 'role' => 'agency_admin']))->assertSessionHasErrors('role');
});

it('denies inactive staff login and existing CMS sessions immediately', function () {
    $account = User::factory()->create(['role' => 'researcher', 'is_active' => false]);
    $this->post('/login', ['username' => $account->username, 'password' => 'password'])->assertSessionHasErrors('username');
    $this->assertGuest();
    $this->actingAs($account)->get('/admin')->assertForbidden();
    $this->post('/admin/documents', ['name' => 'Denied'])->assertForbidden();
});

it('deactivates an account and denies its next content request', function () {
    $super = User::factory()->create(['role' => 'super_admin']);
    $researcher = User::factory()->create(['role' => 'researcher']);
    $this->actingAs($super)->put('/admin/users/'.$researcher->id, ['username' => $researcher->username, 'name' => $researcher->name, 'email' => $researcher->email, 'role' => 'researcher', 'is_active' => 0])->assertSessionHasNoErrors();
    $this->actingAs($researcher->fresh())->get('/admin')->assertForbidden();
});

it('protects the last super admin and prevents self-removal through profile deletion', function () {
    $super = User::factory()->create(['role' => 'super_admin']);
    $this->actingAs($super);
    $payload = ['username' => $super->username, 'name' => $super->name, 'email' => $super->email, 'role' => 'researcher', 'is_active' => 0];
    $this->put('/admin/users/'.$super->id, $payload)->assertSessionHasErrors('role');
    expect($super->fresh()->canManageAdmins())->toBeTrue();
    $this->delete('/profile', ['password' => 'password'])->assertNotFound();
    $this->assertModelExists($super);
    $this->delete('/admin/users/'.$super->id)->assertStatus(405);
});

it('lets another super admin change access but not a super admin change their own access', function () {
    $super = User::factory()->create(['role' => 'super_admin']);
    $other = User::factory()->create(['role' => 'super_admin']);
    $payload = ['username' => $other->username, 'name' => $other->name, 'email' => $other->email, 'role' => 'researcher', 'is_active' => 1];
    $this->actingAs($other)->put('/admin/users/'.$other->id, $payload)->assertSessionHasErrors('role');
    $this->actingAs($super)->put('/admin/users/'.$other->id, $payload)->assertSessionHasNoErrors();
    expect($other->fresh()->role)->toBe('researcher');
});

it('blocks privilege injection through removed public account endpoints', function () {
    $this->post('/register', ['name' => 'Public user', 'role' => 'super_admin'])->assertNotFound();
    $user = User::factory()->create();
    $this->actingAs($user)->patch('/profile', ['role' => 'super_admin'])->assertNotFound();
    expect($user->fresh()->role)->toBe('resident');
    $this->get('/admin')->assertForbidden();
});

it('sends staff to the CMS after login and bootstraps an existing account without changing its password', function () {
    $user = User::factory()->create();
    $hash = $user->password;
    $this->artisan('cms:make-super-admin', ['username' => $user->username, '--no-interaction' => true])->assertSuccessful();
    expect($user->fresh()->canManageAdmins())->toBeTrue()->and($user->fresh()->password)->toBe($hash);
    $this->post('/login', ['username' => $user->username, 'password' => 'password'])->assertRedirect('/admin');
});
