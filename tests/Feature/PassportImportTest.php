<?php

use App\Models\Agency;
use App\Models\ContentChangeLog;
use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\GovernmentIdApplicationStep;
use App\Models\GovernmentIdApplicationStepBlock;
use App\Models\GovernmentIdRequirementItem;
use App\Models\GovernmentIdRequirementSet;
use App\Models\Office;
use Illuminate\Support\Facades\Storage;

it('previews the complete import without writing records', function () {
    $this->artisan('idireksyon:import-passport')->assertSuccessful();
    expect(GovernmentId::count())->toBe(0)->and(Document::count())->toBe(0)
        ->and(Agency::count())->toBe(0)->and(GovernmentIdRequirementSet::count())->toBe(0)
        ->and(GovernmentIdApplicationStep::count())->toBe(0)->and(ContentChangeLog::count())->toBe(0);
});

it('imports structured Passport research with choices references and guide steps', function () {
    $this->artisan('idireksyon:import-passport', ['--apply' => true])->assertSuccessful();
    $passport = GovernmentId::where('name', 'Philippine Passport (ePassport)')->firstOrFail();
    expect($passport->agency->acronym)->toBe('DFA')->and($passport->requirementSets()->count())->toBe(4)
        ->and($passport->fees()->count())->toBe(5)->and($passport->last_verified_at)->toBeNull()
        ->and($passport->official_sources)->toContain('Passport source review')
        ->and(Office::count())->toBe(0);
    $adult = $passport->requirementSets()->where('application_type', 'new')->where('applicant_type', 'adult')->firstOrFail();
    $proof = $adult->groups()->where('title', 'Proof of identity — choose one accepted item')->firstOrFail();
    expect($proof->ways->first()->required_count)->toBe(1)->and($proof->items()->count())->toBeGreaterThan(10);
    expect($adult->applicationSteps()->count())->toBe(7)->and(GovernmentIdApplicationStep::count())->toBe(14);
    $marriage = $adult->groups()->where('condition_type', 'spouse_surname')->firstOrFail();
    expect($marriage->items->first()->document->name)->toBe('PSA Marriage Certificate');
    expect(GovernmentIdRequirementItem::where('government_id_id', $passport->id)->count())->toBe(0);
    $reference = GovernmentIdApplicationStepBlock::where('type', 'requirements')->firstOrFail();
    expect(array_keys($reference->content))->toBe(['intro']);
});

it('reuses existing aliases and preserves populated records and later edits on repeat imports', function () {
    $passport = GovernmentId::create(['name' => 'Passport', 'description' => 'Keep my description']);
    $national = GovernmentId::create(['name' => 'National ID (PhilSys)']);
    $birth = Document::create(['name' => 'Birth Certificate (PSA)']);
    $agency = Agency::create(['name' => 'Department of Foreign Affairs', 'acronym' => 'DFA']);
    $this->artisan('idireksyon:import-passport', ['--apply' => true])->assertSuccessful();
    expect($passport->fresh()->description)->toBe('Keep my description')->and($passport->fresh()->agency_id)->toBe($agency->id);
    expect(GovernmentIdRequirementItem::where('government_id_id', $national->id)->count())->toBeGreaterThan(0)
        ->and(GovernmentIdRequirementItem::where('document_id', $birth->id)->count())->toBeGreaterThan(0);
    $step = GovernmentIdApplicationStep::firstOrFail(); $step->update(['title' => 'Maintainer revised this step']);
    $counts = [GovernmentId::count(), Document::count(), GovernmentIdRequirementItem::count(), GovernmentIdApplicationStep::count(), ContentChangeLog::count()];
    $this->artisan('idireksyon:import-passport', ['--apply' => true])->assertSuccessful();
    expect([GovernmentId::count(), Document::count(), GovernmentIdRequirementItem::count(), GovernmentIdApplicationStep::count(), ContentChangeLog::count()])->toBe($counts)
        ->and($step->fresh()->title)->toBe('Maintainer revised this step');
});

it('keeps existing checklist and fee sections intact', function () {
    $passport = GovernmentId::create(['name' => 'Passport']);
    $set = $passport->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'adult']);
    $group = $set->groups()->create(['title' => 'My researched requirement', 'rule' => 'all']);
    $fee = $passport->fees()->create(['label' => 'Existing fee', 'type' => 'free']);
    $this->artisan('idireksyon:import-passport', ['--apply' => true])->assertSuccessful();
    expect($set->groups()->pluck('id')->all())->toBe([$group->id])->and($passport->fees()->pluck('id')->all())->toBe([$fee->id]);
});

it('stops on ambiguous Passport names and supports an explicit existing target', function () {
    $first = GovernmentId::create(['name' => 'Passport']);
    GovernmentId::create(['name' => 'Philippine Passport']);
    $this->artisan('idireksyon:import-passport', ['--apply' => true])->assertFailed();
    expect(Agency::count())->toBe(0)->and(GovernmentIdRequirementSet::count())->toBe(0);
    $this->artisan('idireksyon:import-passport', ['--apply' => true, '--id' => $first->id])->assertSuccessful();
    expect($first->requirementSets()->count())->toBe(4);
});

it('rolls back earlier inserts if a directory match is ambiguous', function () {
    Document::create(['name' => 'PSA Birth Certificate']);
    Document::create(['name' => 'Birth Certificate (PSA)']);
    $this->artisan('idireksyon:import-passport', ['--apply' => true])->assertFailed();
    expect(GovernmentId::count())->toBe(0)->and(Agency::count())->toBe(0)
        ->and(GovernmentIdApplicationStep::count())->toBe(0)->and(GovernmentIdRequirementSet::count())->toBe(0)
        ->and(Document::count())->toBe(2)->and(ContentChangeLog::count())->toBe(0);
});

it('rolls back the import if the terminal audit cannot be recorded', function () {
    $dispatcher = ContentChangeLog::getEventDispatcher();
    ContentChangeLog::setEventDispatcher(clone $dispatcher);
    ContentChangeLog::creating(function () { throw new RuntimeException('Audit unavailable'); });
    try {
        $this->artisan('idireksyon:import-passport', ['--apply' => true])->assertFailed();
        expect(GovernmentId::count())->toBe(0)->and(Document::count())->toBe(0)->and(Agency::count())->toBe(0);
    } finally { ContentChangeLog::setEventDispatcher($dispatcher); }
});


it('requires an explicit target for replacement', function () {
    $passport = GovernmentId::create(['name' => 'Passport', 'description' => 'Original']);
    $this->artisan('idireksyon:import-passport', ['--replace' => true, '--apply' => true])->assertFailed();
    expect($passport->fresh()->description)->toBe('Original')->and(Agency::count())->toBe(0);
});

it('previews replacements without altering existing records or writing a backup', function () {
    Storage::fake('local');
    $passport = GovernmentId::create(['name' => 'Passport', 'description' => 'Incorrect description', 'office_hours' => 'Incorrect hours']);
    $set = $passport->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'adult']);
    $group = $set->groups()->create(['title' => 'Incorrect requirement', 'rule' => 'all']);
    $this->artisan('idireksyon:import-passport', ['--replace' => true, '--id' => $passport->id])->assertSuccessful();
    expect($passport->fresh()->description)->toBe('Incorrect description')->and($passport->fresh()->office_hours)->toBe('Incorrect hours');
    $this->assertModelExists($group);
    expect(Storage::disk('local')->allFiles('passport-import-backups'))->toBe([])->and(Office::count())->toBe(0);
});

it('replaces populated Passport sections and stale legacy fields and archives their previous contents', function () {
    Storage::fake('local');
    $passport = GovernmentId::create([
        'name' => 'Passport', 'description' => 'Incorrect description', 'eligibility' => 'Foreign residents eligible',
        'eligibility_age_type' => 'minimum', 'eligibility_min_age' => 21, 'validity_type' => 'fixed', 'validity_value' => 2,
        'validity_unit' => 'year', 'processing_time_min' => 100, 'fee_min' => 1,
        'requirements' => 'Wrong legacy requirements', 'application_process' => 'Wrong legacy steps',
        'office_hours' => 'Wrong general hours', 'official_sources' => 'Wrong old source claim',
    ]);
    $set = $passport->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'adult']);
    $oldGroup = $set->groups()->create(['title' => 'Wrong requirement', 'rule' => 'all']);
    $oldStep = $set->applicationSteps()->create(['title' => 'Wrong step']);
    $oldStep->blocks()->create(['type' => 'custom', 'content' => []]);
    $oldFee = $passport->fees()->create(['label' => 'Wrong fee', 'type' => 'fixed', 'amount_min' => 1]);
    $other = GovernmentId::create(['name' => 'Another Passport record', 'description' => 'Leave alone']);
    $office = Office::create(['name' => 'Unconfirmed Marilao branch']);
    $passport->offices()->attach($office->id); $other->offices()->attach($office->id);
    $this->artisan('idireksyon:import-passport', ['--replace' => true, '--apply' => true, '--id' => $passport->id])->assertSuccessful();
    $passport->refresh();
    expect($passport->name)->toBe('Philippine Passport (ePassport)')->and($passport->description)->not->toBe('Incorrect description')
        ->and($passport->eligibility_citizenship)->toBe('filipino')->and($passport->eligibility_min_age)->toBeNull()
        ->and($passport->validity_value)->toBeNull()->and($passport->processing_time_min)->toBeNull()
        ->and($passport->fee_min)->toBeNull()->and($passport->office_hours)->toBeNull()
        ->and($passport->requirements)->not->toContain('Wrong legacy')->and($passport->application_process)->not->toContain('Wrong legacy')
        ->and($passport->official_sources)->not->toContain('Wrong old source claim')->and($passport->fees()->count())->toBe(5)
        ->and($passport->offices()->count())->toBe(2)->and($other->fresh()->description)->toBe('Leave alone')
        ->and($other->offices()->count())->toBe(1);
    $this->assertModelMissing($oldGroup); $this->assertModelMissing($oldStep); $this->assertModelMissing($oldFee); $this->assertModelExists($office);
    $files = Storage::disk('local')->allFiles('passport-import-backups');
    expect($files)->toHaveCount(1);
    $archive = json_decode(Storage::disk('local')->get($files[0]), true);
    expect($archive['government_id']['description'])->toBe('Incorrect description')
        ->and($archive['government_id']['requirement_sets'][0]['application_steps'][0]['title'])->toBe('Wrong step');
    $late = $set->groups()->where('title', 'Additional evidence for late birth registration')->firstOrFail();
    expect($late->ways->pluck('required_count')->all())->toBe([1, 2]);
    $renewal = $passport->requirementSets()->where('application_type', 'renewal')->firstOrFail();
    expect($renewal->groups()->where('title', 'Proof of identity — choose one accepted item')->exists())->toBeFalse();
    expect(ContentChangeLog::latest('id')->first()->changed_fields)->toContain('passport_research_replacement');
});

it('rolls back replacement deletions if later audit storage fails', function () {
    Storage::fake('local');
    $passport = GovernmentId::create(['name' => 'Passport', 'description' => 'Keep on failure']);
    $set = $passport->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'adult']);
    $step = $set->applicationSteps()->create(['title' => 'Keep on failure']);
    $dispatcher = ContentChangeLog::getEventDispatcher();
    ContentChangeLog::setEventDispatcher(clone $dispatcher);
    ContentChangeLog::creating(function () { throw new RuntimeException('Audit failed'); });
    try {
        $this->artisan('idireksyon:import-passport', ['--replace' => true, '--apply' => true, '--id' => $passport->id])->assertFailed();
        expect($passport->fresh()->description)->toBe('Keep on failure')->and(Document::count())->toBe(0)->and(Office::count())->toBe(0);
        $this->assertModelExists($step);
        expect(Storage::disk('local')->allFiles('passport-import-backups'))->toHaveCount(1);
    } finally { ContentChangeLog::setEventDispatcher($dispatcher); }
});

it('does not duplicate scenarios fees or office links on repeated replacement', function () {
    Storage::fake('local');
    $passport = GovernmentId::create(['name' => 'Passport']);
    $options = ['--replace' => true, '--apply' => true, '--id' => $passport->id];
    $this->artisan('idireksyon:import-passport', $options)->assertSuccessful();
    $counts = [GovernmentId::count(), Document::count(), GovernmentIdRequirementItem::count(), GovernmentIdApplicationStep::count(), Office::count()];
    $this->artisan('idireksyon:import-passport', $options)->assertSuccessful();
    expect([GovernmentId::count(), Document::count(), GovernmentIdRequirementItem::count(), GovernmentIdApplicationStep::count(), Office::count()])->toBe($counts)
        ->and($passport->fees()->count())->toBe(5)->and($passport->offices()->count())->toBe(2);
});


it('rolls back when a shared office address conflicts rather than retaining or overwriting it', function () {
    Storage::fake('local');
    $passport = GovernmentId::create(['name' => 'Passport', 'description' => 'Original']);
    $office = Office::create(['name' => 'DFA Consular Office Malolos', 'address' => 'Different researched location']);
    $this->artisan('idireksyon:import-passport', ['--replace' => true, '--apply' => true, '--id' => $passport->id])->assertFailed();
    expect($passport->fresh()->description)->toBe('Original')->and($office->fresh()->address)->toBe('Different researched location')
        ->and($passport->requirementSets()->count())->toBe(0);
});


it('creates a standalone example despite duplicate directory IDs without changing existing passports', function () {
    $original = GovernmentId::create(['name' => 'Passport', 'description' => 'Keep original']);
    GovernmentId::create(['name' => 'OWWA ID']);
    GovernmentId::create(['name' => 'Overseas Workers Welfare Administration (OWWA) ID']);
    $this->artisan('idireksyon:import-passport', ['--example' => true])->assertSuccessful();
    expect(GovernmentId::count())->toBe(3)->and(Document::count())->toBe(0)->and(Agency::count())->toBe(0);
    $this->artisan('idireksyon:import-passport', ['--example' => true, '--apply' => true])->assertSuccessful();
    $example = GovernmentId::where('name', 'Philippine Passport (ePassport) — CMS Example')->firstOrFail();
    expect(GovernmentId::count())->toBe(4)->and(Document::count())->toBe(0)->and(Office::count())->toBe(0)
        ->and($original->fresh()->description)->toBe('Keep original')
        ->and($example->requirementSets()->count())->toBe(4)->and($example->fees()->count())->toBe(5)
        ->and($example->application_process)->toContain('Book an appointment')
        ->and($example->office_location)->toContain('Malolos')->and($example->office_hours)->toBeNull();
    expect(GovernmentIdRequirementItem::whereNotNull('government_id_id')->count())->toBe(0)
        ->and(GovernmentIdRequirementItem::whereNotNull('document_id')->count())->toBe(0);
    expect(GovernmentIdRequirementItem::where('custom_name', 'OWWA E-Card')->exists())->toBeTrue();
    $late = $example->requirementSets()->where('application_type', 'new')->where('applicant_type', 'adult')->firstOrFail()
        ->groups()->where('title', 'Additional evidence for late birth registration')->firstOrFail();
    expect($late->ways->pluck('required_count')->all())->toBe([1, 2]);
    $example->update(['description' => 'My UI review edit']);
    $count = GovernmentIdRequirementItem::count();
    $this->artisan('idireksyon:import-passport', ['--example' => true, '--apply' => true])->assertSuccessful();
    expect(GovernmentId::count())->toBe(4)->and($example->fresh()->description)->toBe('My UI review edit')
        ->and(GovernmentIdRequirementItem::count())->toBe($count);
});

it('rejects mixing example mode with an existing target or replacement', function () {
    $passport = GovernmentId::create(['name' => 'Passport']);
    $this->artisan('idireksyon:import-passport', ['--example' => true, '--id' => $passport->id, '--apply' => true])->assertFailed();
    $this->artisan('idireksyon:import-passport', ['--example' => true, '--replace' => true, '--apply' => true])->assertFailed();
    expect(GovernmentId::count())->toBe(1)->and(GovernmentIdRequirementSet::count())->toBe(0);
});
