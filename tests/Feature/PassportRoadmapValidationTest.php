<?php

use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\ResidentJourney;
use App\Models\User;
use App\Services\RoadmapPlanner;
use Illuminate\Support\Facades\DB;

/** Passport is data here: the engine has no Passport rule. Uses the repo's research file. */
function passportCatalog(): GovernmentId
{
    test()->artisan('idireksyon:import-passport', ['--apply' => true])->assertSuccessful();
    // The CMS setting staff would apply to these procedural records.
    Document::whereIn('name', ['DFA Appointment Confirmation', 'Passport Application Form', 'Passport Payment Receipt', 'Passport Data Page Photocopy'])
        ->update(['include_in_smart_sequencing' => false]);
    GovernmentId::where('name', 'PhilSys National ID')->update(['application_process' => 'Documented steps']);
    Document::where('name', 'PSA Birth Certificate')->update(['application_process' => 'Documented steps']);

    return GovernmentId::where('name', 'Philippine Passport (ePassport)')->firstOrFail();
}

function passportPlan(User $resident, GovernmentId $passport, string $application = 'new', string $applicant = 'adult'): array
{
    $planner = app(RoadmapPlanner::class);
    $set = $passport->requirementSets()->where('application_type', $application)->where('applicant_type', $applicant)->firstOrFail();
    $planner->build($resident, [$passport->id], [], ['key' => 'government_id:'.$passport->id.':path', 'set_id' => $set->id]);

    return $planner->build($resident, [$passport->id]);
}

function passportAdult(): User
{
    return User::factory()->create(['date_of_birth' => '1995-05-05']);
}

function passportRows(array $plan): array
{
    return collect($plan['steps'])->last()['prerequisites'];
}

it('asks an adult with empty Inventory to choose a checklist and offers only applicable paths', function () {
    $passport = passportCatalog();
    $names = fn ($user) => array_column(collect(app(RoadmapPlanner::class)->build($user, [$passport->id])['steps'])->firstWhere('kind', 'path')['suggestions'], 'name');

    expect($names(passportAdult()))->toBe(['Adult • First-Time Application', 'Adult • Renewal', 'All Applicants • Replacement'])
        ->and($names(User::factory()->create(['date_of_birth' => now()->subYears(14)->format('Y-m-d')])))->toBe(['Minor • First-Time Application', 'All Applicants • Replacement']);
});

it('sequences first-time adult evidence without procedural acquisition steps', function () {
    $passport = passportCatalog();
    $plan = passportPlan(passportAdult(), $passport);
    $identity = collect($plan['steps'])->firstWhere('name', 'Proof of identity — choose one accepted item');

    expect(array_column($plan['steps'], 'name'))->toBe(['PSA Birth Certificate', 'Proof of identity — choose one accepted item', 'Philippine Passport (ePassport)'])
        ->and(count($identity['suggestions']))->toBeGreaterThan(10)
        ->and($identity['remaining_count'])->toBe(1);
    $rows = collect(passportRows($plan));
    expect($rows->where('manual_kind', 'task')->pluck('name')->all())
        ->toBe(['DFA Appointment Confirmation', 'Passport Application Form', 'Applicant must appear in person'])
        ->and($rows->where('manual_kind', 'conditional')->count())->toBe(3);
});

it('removes PSA once owned and never auto-satisfies identity from an owned accepted ID', function () {
    $passport = passportCatalog();
    $resident = passportAdult();
    $resident->ownedDocuments()->attach(Document::where('name', 'PSA Birth Certificate')->first());
    $resident->ownedGovernmentIds()->attach(GovernmentId::where('name', 'PhilSys National ID')->first());
    $plan = passportPlan($resident, $passport);

    expect(array_column($plan['steps'], 'name'))->toBe(['Proof of identity — choose one accepted item', 'Philippine Passport (ePassport)'])
        ->and($plan['steps'][0]['manual_preparation'])->toBeTrue()
        ->and($plan['steps'][0]['confirmation']['owned_names'])->toBe(['PhilSys National ID']);
});

it('offers one confirmation for several owned accepted IDs and removes it when Inventory changes', function () {
    $passport = passportCatalog();
    $resident = passportAdult();
    $philsys = GovernmentId::where('name', 'PhilSys National ID')->first();
    $resident->ownedGovernmentIds()->attach([$philsys->id, GovernmentId::where('name', 'UMID')->first()->id]);
    $plan = passportPlan($resident, $passport);
    expect(collect($plan['steps'])->where('type', 'unresolved_requirement')->count())->toBe(1)
        ->and($plan['steps'][1]['confirmation']['owned_names'] ?? $plan['steps'][0]['confirmation']['owned_names'])->toHaveCount(2);

    $resident->ownedGovernmentIds()->detach();
    $identity = collect(app(RoadmapPlanner::class)->build($resident, [$passport->id])['steps'])->firstWhere('type', 'unresolved_requirement');
    expect($identity['suggestions'])->not->toBe([])->and($identity)->not->toHaveKey('confirmation');
});

it('never treats the same ID as additional late-registration evidence', function () {
    $passport = passportCatalog();
    $resident = passportAdult();
    $resident->ownedGovernmentIds()->attach(GovernmentId::where('name', 'PhilSys National ID')->first());
    $late = collect(passportRows(passportPlan($resident, $passport)))->firstWhere('name', 'Additional evidence for late birth registration');

    expect($late['status'])->toBe('manual')->and($late['manual_kind'])->toBe('conditional');
});

it('uses each applicant path without carrying identity or adult rules across', function () {
    $passport = passportCatalog();
    $renewal = passportPlan(passportAdult(), $passport, 'renewal', 'adult');
    $replacement = passportPlan(passportAdult(), $passport, 'replacement', 'all');
    $minor = passportPlan(User::factory()->create(['date_of_birth' => now()->subYears(14)->format('Y-m-d')]), $passport, 'new', 'minor');

    expect(array_column($renewal['steps'], 'name'))->toBe(['Philippine Passport (ePassport)'])
        ->and(array_column($replacement['steps'], 'name'))->toBe(['Philippine Passport (ePassport)'])
        ->and(collect(passportRows($replacement))->pluck('manual_kind')->unique()->all())->toBe(['conditional'])
        ->and(array_column($minor['steps'], 'name'))->toBe(['PSA Birth Certificate', 'Philippine Passport (ePassport)']);
});

it('lets a resident correct the wrong application path on a saved Roadmap', function () {
    $passport = passportCatalog();
    $resident = passportAdult();
    $renewal = passportPlan($resident, $passport, 'renewal', 'adult');
    $this->actingAs($resident);
    $journey = $this->postJson('/api/auth/journeys', ['target_government_id_ids' => [$passport->id]])->assertCreated()->json('data');
    expect(array_column($journey['plan']['saved_choices'], 'selected'))->toBe(['Adult • Renewal']);

    $data = $this->putJson('/api/auth/journeys/'.$journey['id'].'/resolution', ['resolution' => ['key' => 'government_id:'.$passport->id.':path', 'reset' => true]])
        ->assertOk()->json('data');

    expect($data['plan']['saved_choices'])->toBe([])
        ->and(collect($data['plan']['steps'])->firstWhere('kind', 'path')['suggestions'])->toHaveCount(3)
        ->and(ResidentJourney::find($journey['id'])->plan['saved_choices'])->toBe([]);
});
