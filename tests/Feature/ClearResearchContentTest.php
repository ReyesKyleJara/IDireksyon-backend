<?php

use App\Models\Agency;
use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\Office;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

it('previews cleanup without changing data or writing a backup', function () {
    Storage::fake('local');
    GovernmentId::create(['name' => 'Keep ID']);
    Office::create(['name' => 'Keep office']);
    $this->artisan('idireksyon:clear-research')->assertSuccessful();
    $this->assertDatabaseCount('government_ids', 1);
    $this->assertDatabaseCount('offices', 1);
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

it('backs up linked research and clears it while preserving other records', function () {
    Storage::fake('local');
    $agency = Agency::create(['name' => 'Keep agency']);
    $document = Document::create(['name' => 'Keep document']);
    $user = User::factory()->create();
    $id = GovernmentId::create(['name' => 'Clear ID', 'agency_id' => $agency->id]);
    $office = Office::create(['name' => 'Clear branch', 'agency_id' => $agency->id]);
    $id->offices()->attach($office->id);
    $set = $id->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'adult']);
    $step = DB::table('government_id_application_steps')->insertGetId([
        'requirement_set_id' => $set->id, 'title' => 'Visit the office',
    ]);
    DB::table('government_id_application_step_blocks')->insert([
        'application_step_id' => $step, 'type' => 'note', 'content' => '{}',
    ]);
    $logId = DB::table('content_change_logs')->insertGetId([
        'user_id' => $user->id, 'action' => 'created', 'entity_type' => 'government_id',
        'entity_id' => $id->id, 'entity_name' => $id->name, 'changed_fields' => '[]', 'created_at' => now(),
    ]);
    $this->artisan('idireksyon:clear-research', ['--apply' => true])->assertSuccessful();
    $files = Storage::disk('local')->allFiles('research-backups');
    expect($files)->toHaveCount(1);
    $backup = json_decode(Storage::disk('local')->get($files[0]), true, 512, JSON_THROW_ON_ERROR);
    expect($backup['tables']['government_ids'][0]['name'])->toBe('Clear ID');
    expect($backup['tables']['government_id_application_steps'][0]['title'])->toBe('Visit the office');
    foreach (array_keys($backup['tables']) as $table) {
        $this->assertDatabaseCount($table, 0);
    }
    $this->assertModelExists($agency);
    $this->assertModelExists($document);
    $this->assertModelExists($user);
    $this->assertDatabaseHas('content_change_logs', ['id' => $logId]);
});

it('does not delete research when writing the backup fails', function () {
    GovernmentId::create(['name' => 'Keep ID']);
    Storage::shouldReceive('disk')->with('local')->andReturnSelf();
    Storage::shouldReceive('put')->once()->andReturn(false);
    Storage::shouldReceive('path')->andReturn('unwritten-backup.json');
    $this->artisan('idireksyon:clear-research', ['--apply' => true])->assertFailed();
    $this->assertDatabaseHas('government_ids', ['name' => 'Keep ID']);
});
