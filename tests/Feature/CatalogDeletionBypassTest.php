<?php

use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

it('refuses research cleanup when a resident owns an ID and writes no backup', function () {
    Storage::fake('local');
    $record = GovernmentId::create(['name' => 'Resident-owned ID']);
    User::factory()->create()->ownedGovernmentIds()->attach($record);
    $before = DB::table('resident_government_ids')->get()->toJson();

    $this->artisan('idireksyon:clear-research', ['--apply' => true])->assertFailed();

    $this->assertModelExists($record);
    expect(DB::table('resident_government_ids')->get()->toJson())->toBe($before)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('refuses checklist deletion or replacement that would cascade saved preparation', function ($model, $resource, $table, $method) {
    $this->actingAs(User::factory()->create(['role' => 'researcher']));
    $record = $model::create(['name' => 'Prepared checklist owner']);
    $set = $record->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'all']);
    $item = $set->groups()->create(['rule' => 'all'])->items()->create(['type' => 'custom', 'custom_name' => 'Keep form']);
    DB::table($table)->insert(['user_id' => User::factory()->create()->id, 'requirement_item_id' => $item->id, 'prepared' => false]);
    $before = DB::table($table)->get()->toJson();
    $url = '/admin/'.$resource.'/'.$record->id.'/checklists/'.$set->id;

    if ($method === 'delete') {
        $this->deleteJson($url)->assertUnprocessable();
    } else {
        $this->putJson($url, ['application_type' => 'new', 'applicant_type' => 'all', 'groups' => []])->assertUnprocessable();
    }
    $this->assertModelExists($set);
    $this->assertModelExists($item);
    expect(DB::table($table)->get()->toJson())->toBe($before);
})->with([
    [GovernmentId::class, 'government-ids', 'resident_id_preparations'],
    [Document::class, 'documents', 'resident_document_preparations'],
])->with(['delete', 'replace']);

it('refuses a destructive Passport replacement when resident data depends on it', function () {
    Storage::fake('local');
    $passport = GovernmentId::create(['name' => 'Passport']);
    $set = $passport->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'adult']);
    $item = $set->groups()->create(['rule' => 'all'])->items()->create(['type' => 'custom', 'custom_name' => 'Saved form']);
    DB::table('resident_id_preparations')->insert([
        'user_id' => User::factory()->create()->id, 'requirement_item_id' => $item->id, 'prepared' => true,
    ]);
    $before = DB::table('resident_id_preparations')->get()->toJson();
    $this->artisan('idireksyon:import-passport', ['--apply' => true, '--replace' => true, '--id' => $passport->id])->assertFailed();
    $this->assertModelExists($item);
    expect(DB::table('resident_id_preparations')->get()->toJson())->toBe($before)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});
