<?php

use App\Models\Agency;
use App\Models\ContentChangeLog;
use App\Models\Document;
use App\Models\Office;
use App\Models\User;
use Database\Seeders\LocalSuperAdminSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

it('offers a username login with no public recovery links and throttles failed attempts', function () {
    $this->get('/login')->assertOk()->assertSee('Username')->assertSee('Sign In')
        ->assertSee('Contact the System Administrator.')->assertDontSee('href="http://localhost/forgot-password"', false)
        ->assertDontSee('name="email"', false)->assertDontSee('name="remember"', false);
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post('/login', ['username' => 'missing', 'password' => 'bad-password'])->assertSessionHasErrors(['username' => 'Invalid username or password.']);
    }
    $this->post('/login', ['username' => 'MISSING', 'password' => 'bad-password'])->assertSessionHasErrors('username');
    expect(session('errors')->first('username'))->toContain('Too many login attempts');
    $this->assertGuest();
});

it('lets researchers use the dashboard and office directory while protecting administration', function () {
    $agency = Agency::create(['name' => 'Test Agency']);
    Office::create(['name' => 'Test Office', 'agency_id' => $agency->id]);
    $this->actingAs(User::factory()->create(['role' => 'researcher']));
    $this->get('/admin')->assertOk()->assertDontSee('Admin Users')->assertDontSee('Audit Logs');
    $this->get('/admin/offices')->assertOk()->assertSee('Test Office');
    $this->get('/admin/government-ids/create')->assertOk()->assertSee('Test Agency')->assertSee('Test Office');
    $this->get('/admin/users')->assertForbidden();
    $this->get('/admin/audit-logs')->assertForbidden();
    $this->post('/admin/users', ['role' => 'super_admin'])->assertForbidden();
    $this->post('/admin/documents', ['name' => 'Researcher entry', 'issued_by' => 'Test Agency'])
        ->assertSessionHasNoErrors()->assertRedirect('/admin/documents');
    expect(Document::sole()->issued_by)->toBe('Test Agency');
    expect($agency->fresh()->name)->toBe('Test Agency');
});

it('logs content changes and relationships but never passwords or hashes and keeps logs read only', function () {
    Notification::fake();
    $super = User::factory()->create(['role' => 'super_admin']);
    $this->actingAs($super)->post('/admin/documents', ['name' => 'Audited document'])->assertSessionHasNoErrors();
    expect(ContentChangeLog::where('action', 'created')->where('entity_type', 'document')->exists())->toBeTrue();
    $payload = ['name' => 'Test Researcher', 'username' => 'audit.researcher', 'email' => 'audit@example.test', 'role' => 'researcher', 'is_active' => 1, 'password' => 'audit-secret-password', 'password_confirmation' => 'audit-secret-password'];
    $this->post('/admin/users', $payload)->assertSessionHasNoErrors();
    $researcher = User::where('username', 'audit.researcher')->firstOrFail();
    $payload['password'] = $payload['password_confirmation'] = 'replacement-password';
    $this->put('/admin/users/'.$researcher->id, $payload)->assertSessionHasNoErrors();
    expect(Hash::check('replacement-password', $researcher->fresh()->password))->toBeTrue();
    $raw = ContentChangeLog::all()->toJson();
    expect($raw)->not->toContain('audit-secret-password')->not->toContain('replacement-password')->not->toContain($researcher->fresh()->password);
    expect(ContentChangeLog::where('action', 'password_changed')->exists())->toBeTrue();
    $this->get('/admin/audit-logs')->assertOk()->assertSee('Audited document');
    $this->delete('/admin/audit-logs/1')->assertNotFound();
    Notification::assertNothingSent();
});

it('seeds the requested local super admin securely and idempotently without changing other users', function () {
    $existing = User::factory()->create(['role' => 'researcher']);
    $before = $existing->fresh()->getAttributes();
    $this->seed(LocalSuperAdminSeeder::class);
    $admin = User::where('username', 'veryveryadmin')->firstOrFail();
    expect($admin->canManageAdmins())->toBeTrue()->and(Hash::check('SPAdmin1234', $admin->password))->toBeTrue();
    $this->seed(LocalSuperAdminSeeder::class);
    expect(User::where('username', 'veryveryadmin')->count())->toBe(1)->and($existing->fresh()->getAttributes())->toBe($before);
    $session = session()->getId();
    $this->post('/login', ['username' => 'VeryVeryAdmin', 'password' => 'SPAdmin1234'])->assertRedirect('/admin');
    expect(session()->getId())->not->toBe($session);
});
