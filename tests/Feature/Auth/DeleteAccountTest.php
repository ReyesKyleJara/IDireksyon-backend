<?php

use App\Models\GovernmentId;
use App\Models\User;
use Illuminate\Support\Facades\DB;

test('wrong password keeps the account and its data', function () {
    $user = User::factory()->create();
    $id = GovernmentId::create(['name' => 'Passport']);
    $user->ownedGovernmentIds()->attach($id);

    $this->withToken($user->createToken('test')->plainTextToken)
        ->postJson('/api/auth/account/delete', ['password' => 'wrong'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('password');

    expect(User::find($user->id))->not->toBeNull();
    expect($user->ownedGovernmentIds()->count())->toBe(1);
    expect($user->tokens()->count())->toBe(1);
});

test('password is required to delete the account', function () {
    $user = User::factory()->create();

    $this->withToken($user->createToken('test')->plainTextToken)
        ->postJson('/api/auth/account/delete', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('password');

    expect(User::find($user->id))->not->toBeNull();
});

test('deleting removes only the resident and their related records', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $id = GovernmentId::create(['name' => 'Passport']);
    $user->ownedGovernmentIds()->attach($id);
    $other->ownedGovernmentIds()->attach($id);
    $other->createToken('other');
    DB::table('sessions')->insert([
        ['id' => 'mine', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()],
        ['id' => 'theirs', 'user_id' => $other->id, 'payload' => '', 'last_activity' => time()],
    ]);
    DB::table('password_reset_tokens')->insert([
        ['email' => $user->email, 'token' => 'a'],
        ['email' => $other->email, 'token' => 'b'],
    ]);
    $user->createToken('second device');

    $this->withToken($user->createToken('test')->plainTextToken)
        ->postJson('/api/auth/account/delete', [
            'password' => 'password',
            'user_id' => $other->id,
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Account deleted.');

    expect(User::find($user->id))->toBeNull();
    expect(DB::table('personal_access_tokens')->where('tokenable_id', $user->id)->count())->toBe(0);
    expect(DB::table('resident_government_ids')->where('user_id', $user->id)->count())->toBe(0);
    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0);
    expect(DB::table('password_reset_tokens')->where('email', $user->email)->count())->toBe(0);
    expect(GovernmentId::find($id->id))->not->toBeNull();

    expect(User::find($other->id))->not->toBeNull();
    expect($other->ownedGovernmentIds()->count())->toBe(1);
    expect($other->tokens()->count())->toBe(1);
    expect(DB::table('sessions')->where('user_id', $other->id)->count())->toBe(1);
    expect(DB::table('password_reset_tokens')->where('email', $other->email)->count())->toBe(1);
});

test('the deleted account can no longer use its token or sign in', function () {
    $user = User::factory()->create(['email' => 'gone@example.com']);
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/auth/account/delete', ['password' => 'password'])
        ->assertOk();

    $this->app['auth']->forgetGuards();

    $this->withToken($token)->getJson('/api/auth/me')->assertUnauthorized();
    $this->postJson('/api/auth/login', [
        'identifier' => 'gone@example.com',
        'password' => 'password',
    ])->assertUnprocessable();
});

test('phone-only residents can delete their account', function () {
    $user = User::factory()->create(['email' => null, 'phone' => '+639171234567']);

    $this->withToken($user->createToken('test')->plainTextToken)
        ->postJson('/api/auth/account/delete', ['password' => 'password'])
        ->assertOk();

    expect(User::find($user->id))->toBeNull();
});

test('delete account requires an active resident session', function () {
    $this->postJson('/api/auth/account/delete', ['password' => 'password'])
        ->assertUnauthorized();

    foreach ([['is_active' => false], ['role' => 'super_admin'], ['role' => 'researcher']] as $state) {
        $user = User::factory()->create($state);
        $this->app['auth']->forgetGuards();

        $this->withToken($user->createToken('test')->plainTextToken)
            ->postJson('/api/auth/account/delete', ['password' => 'password'])
            ->assertForbidden();

        expect(User::find($user->id))->not->toBeNull();
    }
});
