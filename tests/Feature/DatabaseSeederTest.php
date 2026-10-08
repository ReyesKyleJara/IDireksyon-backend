<?php

use App\Models\Agency;
use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\Office;
use Database\Seeders\DatabaseSeeder;

it('runs default seeding with an empty research directory', function () {
    $this->seed(DatabaseSeeder::class);

    foreach (['agencies', 'documents', 'government_ids', 'offices', 'users'] as $table) {
        $this->assertDatabaseCount($table, 0);
    }
});

it('preserves existing research when default seeding is repeated', function () {
    $agency = Agency::create(['name' => 'Research agency']);
    $id = GovernmentId::create([
        'name' => 'Researched ID', 'agency_id' => $agency->id,
        'description' => 'Keep the current research.',
    ]);
    $document = Document::create(['name' => 'Researched document']);
    $office = Office::create(['name' => 'Researched branch', 'agency_id' => $agency->id]);
    $records = [$agency, $id, $document, $office];
    $before = array_map(fn ($record) => $record->fresh()->getAttributes(), $records);

    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    foreach ($records as $index => $record) {
        expect($record->fresh()->getAttributes())->toBe($before[$index]);
        $this->assertDatabaseCount($record->getTable(), 1);
    }
});
