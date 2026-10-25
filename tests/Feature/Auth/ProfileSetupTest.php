<?php

use App\Models\Barangay;
use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\User;

test('resident setup persists only the authenticated users inventory', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $passport = GovernmentId::create([
        'name' => 'Passport',
    ]);

    $nationalId = GovernmentId::create([
        'name' => 'National ID',
    ]);

    $birthCertificate = Document::create([
        'name' => 'PSA Birth Certificate',
    ]);

    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/auth/profile-setup', [
            'name' => 'Test Resident',
            'date_of_birth' => '2000-01-01',
            'barangay_id' => Barangay::create(['name' => 'Test Barangay'])->id,
            'government_id_ids' => [
                $passport->id,
                $nationalId->id,
            ],
            'document_ids' => [
                $birthCertificate->id,
            ],

            // Must not allow choosing another user.
            'user_id' => $other->id,
        ])
        ->assertOk();

    expect(
        $user->fresh()
            ->ownedGovernmentIds()
            ->pluck('government_ids.id')
            ->sort()
            ->values()
            ->all()
    )->toBe(
        collect([$passport->id, $nationalId->id])
            ->sort()
            ->values()
            ->all()
    );

    expect(
        $user->fresh()
            ->ownedDocuments()
            ->pluck('documents.id')
            ->values()
            ->all()
    )->toBe([$birthCertificate->id]);

    expect($user->fresh()->profile_setup_completed_at)
        ->not->toBeNull();

    expect($other->fresh()->profile_setup_completed_at)
        ->toBeNull();

    expect($other->ownedGovernmentIds()->count())
        ->toBe(0);

    expect($other->ownedDocuments()->count())
        ->toBe(0);
});

test('setup permits skipping both inventory steps and rejects invalid selections', function () {
    $user = User::factory()->create();

    $this->withToken(
        $user->createToken('test')->plainTextToken
    );

    $profile = [
        'name' => 'Test Resident',
        'date_of_birth' => '2000-01-01',
        'barangay_id' => Barangay::create(['name' => 'Test Barangay'])->id,
    ];

    $this->postJson('/api/auth/profile-setup', array_merge($profile, [
        'government_id_ids' => [999999],
        'document_ids' => [],
    ]))->assertUnprocessable()->assertJsonValidationErrors('government_id_ids.0');

    $this->postJson('/api/auth/profile-setup', array_merge($profile, [
        'government_id_ids' => [],
        'document_ids' => [],
    ]))->assertOk();

    expect($user->fresh()->ownedGovernmentIds()->count())
        ->toBe(0);

    expect($user->fresh()->ownedDocuments()->count())
        ->toBe(0);

    expect($user->fresh()->profile_setup_completed_at)
        ->not->toBeNull();
});

test('setup requires an active resident', function () {
    $this->postJson('/api/auth/profile-setup', [
        'government_id_ids' => [],
        'document_ids' => [],
    ])->assertUnauthorized();

    $user = User::factory()->create([
        'is_active' => false,
    ]);

    $this->withToken(
        $user->createToken('test')->plainTextToken
    )
        ->postJson('/api/auth/profile-setup', [
            'government_id_ids' => [],
            'document_ids' => [],
        ])
        ->assertForbidden();
});
