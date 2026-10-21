<?php

namespace Database\Seeders;

use App\Services\ReferenceData;
use Illuminate\Database\Seeder;

class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $references = app(ReferenceData::class);
        $references->assertDevelopmentEnvironment();
        $this->command?->warn(ReferenceData::DEVELOPMENT_METADATA['warning']);
        $data = json_decode(file_get_contents(database_path('data/development-reference-data.json')), true, 512, JSON_THROW_ON_ERROR);
        $counts = $references->import($data);
        $this->command?->info("Reference data: {$counts['inserted']} inserted; {$counts['skipped']} identical records skipped.");
    }
}
