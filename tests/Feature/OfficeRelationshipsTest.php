<?php

use App\Models\Agency;
use App\Models\GovernmentId;
use App\Models\GovernmentIdOffice;
use App\Models\Office;
use App\Models\OfficeSchedule;
use App\Models\OfficeScheduleInterval;
use App\Models\User;
use Illuminate\Database\QueryException;

it('keeps new offices as unverified drafts and connects their agency', function () {
    $agency = Agency::create(['name' => 'Example agency']);
    $office = $agency->offices()->create(['name' => 'Example branch']);
    $office->refresh();

    expect($office->status)->toBe('draft')
        ->and($office->latitude)->toBeNull()
        ->and($office->last_verified_at)->toBeNull()
        ->and($office->agency->is($agency))->toBeTrue()
        ->and($agency->offices->sole()->is($office))->toBeTrue()
        ->and($office->schedules)->toHaveCount(0);
});

it('retrieves daily schedules and split opening intervals in order', function () {
    $office = Office::create(['name' => 'Example branch']);
    $unknown = $office->schedules()->create(['day_of_week' => 6]);
    $monday = $office->schedules()->create(['day_of_week' => 1, 'status' => 'open']);
    $monday->intervals()->create(['opens_at' => '13:00:00', 'closes_at' => '17:00:00']);
    $morning = $monday->intervals()->create(['opens_at' => '08:00:00', 'closes_at' => '12:00:00']);

    expect($unknown->fresh()->status)->toBe('unknown')
        ->and($unknown->intervals)->toHaveCount(0)
        ->and($office->schedules->pluck('day_of_week')->all())->toBe([1, 6])
        ->and($monday->office->is($office))->toBeTrue()
        ->and($monday->intervals->pluck('opens_at')->all())->toBe(['08:00:00', '13:00:00'])
        ->and($morning->schedule->is($monday))->toBeTrue();
});

it('links several IDs and offices with separate service information', function () {
    $firstId = GovernmentId::create(['name' => 'ID A']);
    $secondId = GovernmentId::create(['name' => 'ID B']);
    $firstOffice = Office::create(['name' => 'Branch A']);
    $secondOffice = Office::create(['name' => 'Branch B']);
    $firstId->offices()->attach($firstOffice->id, [
        'new_application_status' => 'available',
        'renewal_status' => 'unavailable',
        'service_notes' => 'Appointment required.',
    ]);
    $firstId->offices()->attach($secondOffice->id);
    $secondId->offices()->attach($firstOffice->id);

    $link = $firstId->offices()->where('offices.id', $firstOffice->id)->firstOrFail()->pivot;
    expect($firstId->offices)->toHaveCount(2)
        ->and($firstOffice->governmentIds)->toHaveCount(2)
        ->and($link)->toBeInstanceOf(GovernmentIdOffice::class)
        ->and($link->id)->not->toBeNull()
        ->and($link->new_application_status)->toBe('available')
        ->and($link->renewal_status)->toBe('unavailable')
        ->and($link->replacement_status)->toBe('unknown')
        ->and($link->service_notes)->toBe('Appointment required.')
        ->and($link->created_at)->not->toBeNull()
        ->and($link->governmentId->is($firstId))->toBeTrue()
        ->and($link->office->is($firstOffice))->toBeTrue()
        ->and($secondId->offices->sole()->pivot->new_application_status)->toBe('unknown');

    $firstId->offices()->updateExistingPivot($firstOffice->id, ['service_notes' => 'Updated note.']);
    expect($firstOffice->governmentIds()->where('government_ids.id', $firstId->id)->firstOrFail()->pivot->service_notes)
        ->toBe('Updated note.');
    $firstId->offices()->detach($secondOffice->id);
    expect($firstId->offices()->count())->toBe(1);
    $this->assertModelExists($secondOffice);
});

it('preserves explicit verification dates during normal edits and clears deleted verifiers', function () {
    $user = User::factory()->create();
    $office = Office::create(['name' => 'Example branch']);
    $id = GovernmentId::create(['name' => 'Example ID']);
    $office->last_verified_at = '2026-10-01 09:00:00';
    $office->last_verified_by = $user->id;
    $office->save();
    $id->offices()->attach($office->id);
    $link = $id->offices->sole()->pivot;
    $link->last_verified_at = '2026-10-01 10:00:00';
    $link->last_verified_by = $user->id;
    $link->save();
    $office->update(['name' => 'Renamed branch']);
    $link->update(['service_notes' => 'Updated notes']);

    expect($office->fresh()->last_verified_at->format('Y-m-d H:i:s'))->toBe('2026-10-01 09:00:00')
        ->and($link->fresh()->last_verified_at->format('Y-m-d H:i:s'))->toBe('2026-10-01 10:00:00')
        ->and($office->lastVerifier->is($user))->toBeTrue()
        ->and($link->lastVerifier->is($user))->toBeTrue();
    $user->delete();
    expect($office->fresh()->last_verified_by)->toBeNull()
        ->and($link->fresh()->last_verified_by)->toBeNull();
});

it('rejects duplicate weekdays for an office', function () {
    $office = Office::create(['name' => 'Example branch']);
    $office->schedules()->create(['day_of_week' => 1]);
    expect(fn () => $office->schedules()->create(['day_of_week' => 1]))->toThrow(QueryException::class);
});

it('rejects duplicate ID and office pairs', function () {
    $office = Office::create(['name' => 'Example branch']);
    $id = GovernmentId::create(['name' => 'Example ID']);
    $id->offices()->attach($office->id);
    expect(fn () => $id->offices()->attach($office->id))->toThrow(QueryException::class);
});

it('blocks deletion of an agency with an office', function () {
    $agency = Agency::create(['name' => 'Example agency']);
    $office = $agency->offices()->create(['name' => 'Example branch']);
    expect(fn () => $agency->delete())->toThrow(QueryException::class);
    $this->assertModelExists($agency);
    $this->assertModelExists($office);
});

it('cascades office deletion through schedules and links without deleting IDs', function () {
    $office = Office::create(['name' => 'Example branch']);
    $id = GovernmentId::create(['name' => 'Example ID']);
    $id->offices()->attach($office->id);
    $schedule = $office->schedules()->create(['day_of_week' => 1, 'status' => 'open']);
    $schedule->intervals()->create(['opens_at' => '08:00:00', 'closes_at' => '17:00:00']);
    $office->delete();

    expect(OfficeSchedule::count())->toBe(0)
        ->and(OfficeScheduleInterval::count())->toBe(0)
        ->and(GovernmentIdOffice::count())->toBe(0);
    $this->assertModelExists($id);
});

it('deletes ID links without deleting offices or their schedules', function () {
    $office = Office::create(['name' => 'Example branch']);
    $schedule = $office->schedules()->create(['day_of_week' => 1]);
    $id = GovernmentId::create(['name' => 'Example ID']);
    $id->offices()->attach($office->id);
    $id->delete();

    expect(GovernmentIdOffice::count())->toBe(0);
    $this->assertModelExists($office);
    $this->assertModelExists($schedule);
});

it('deletes only the selected daily schedule and its intervals', function () {
    $office = Office::create(['name' => 'Example branch']);
    $monday = $office->schedules()->create(['day_of_week' => 1, 'status' => 'open']);
    $tuesday = $office->schedules()->create(['day_of_week' => 2, 'status' => 'closed']);
    $monday->intervals()->create(['opens_at' => '08:00:00', 'closes_at' => '17:00:00']);
    $monday->delete();

    expect(OfficeScheduleInterval::count())->toBe(0);
    $this->assertModelExists($office);
    $this->assertModelExists($tuesday);
});
