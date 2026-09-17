<?php

namespace Database\Seeders;

use App\Models\Agency;
use App\Models\Barangay;
use App\Models\Category;
use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\Level;
use App\Models\Office;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CatalogFoundationSeeder extends Seeder
{
    public function run(): void
    {
        $inventory = json_decode(file_get_contents(__DIR__.'/data/catalog-inventory.json'), true, 512, JSON_THROW_ON_ERROR);
        DB::transaction(function () use ($inventory) {
            foreach (['barangay' => 'Barangay', 'municipal' => 'Municipal', 'national' => 'National'] as $slug => $name) {
                Level::firstOrCreate(['slug' => $slug], ['name' => $name, 'status' => 'active']);
            }
            foreach ($inventory['barangays'] as $name) {
                $existing = Barangay::where('name', $name)->first();
                if (! $existing && $name === 'Tabing Bakod') {
                    $existing = Barangay::where('name', 'Tabing Bakod (Santo Tomas)')->first();
                }
                if (! $existing) {
                    Barangay::create(['name' => $name, 'municipality' => 'Santa Maria', 'province' => 'Bulacan', 'status' => 'active',
                        'description' => 'Name reference: '.$inventory['barangay_source'].($name === 'Tabing Bakod' ? '. Thesis alias: Santo Tomas. This is one barangay, not an additional entry.' : '')]);
                }
            }
            foreach ($inventory['categories'] as $row) {
                Category::firstOrCreate(['slug' => $row['slug']], ['name' => $row['name'], 'parent_category_id' => $row['parent'] ? Category::where('slug', $row['parent'])->value('id') : null, 'description' => 'IDireksyon editorial classification; not an official government taxonomy.', 'status' => 'active']);
            }
            foreach ($inventory['entries'] as $row) {
                $agency = null;
                if ($row['agency_name']) {
                    $agency = Agency::where('name', $row['agency_name'])->first();
                    if (! $agency && $row['agency_abbreviation']) {
                        $agency = Agency::where('abbreviation', $row['agency_abbreviation'])->first();
                    }
                    $agency ??= Agency::create(['name' => $row['agency_name'], 'abbreviation' => $row['agency_abbreviation'], 'status' => 'active']);
                }
                $model = $row['kind'] === 'id' ? GovernmentId::class : Document::class;
                $record = $model::where('inventory_key', $row['key'])->first();
                if (! $record) {
                    $matches = $model::whereIn('name', array_merge([$row['name']], $row['aliases'] ?? []))->get();
                    if ($matches->count() > 1) {
                        $this->command?->warn('Manual duplicate review needed: '.$row['name']);

                        continue;
                    }
                    $record = $matches->first();
                }
                $attributes = ['inventory_key' => $row['key'], 'agency_id' => $agency?->id, 'category_id' => Category::where('slug', $row['category_slug'])->value('id'),
                    'level_id' => Level::where('slug', $row['issuance_level'])->value('id'), 'issuance_level' => $row['issuance_level'],
                    'record_type' => $row['record_type'], 'research_notes' => $row['notes'], 'source_url' => $row['source_url']];
                if (! $record) {
                    $model::create($attributes + ['name' => $row['name'], 'agency' => $agency?->name ?? '', 'research_status' => 'needs_research', 'availability_status' => $row['availability_status'] ?? 'unknown']);
                } elseif (! $record->inventory_key) {
                    // Adopt existing entries once. Never overwrite researched fields or reseed edits.
                    foreach ($attributes as $key => $value) {
                        if ($record->$key === null || $record->$key === '' || ($key === 'record_type' && $record->$key === 'other')) {
                            $record->$key = $value;
                        }
                    }
                    if ($record->research_status === 'draft') {
                        $record->research_status = 'needs_research';
                    }
                    if ($record->availability_status === 'unknown' && ($row['availability_status'] ?? 'unknown') === 'legacy') {
                        $record->availability_status = 'legacy';
                    }
                    $record->save();
                }
            }
            foreach ($inventory['offices'] as $office) {
                $agencyId = Agency::where('name', $office['agency_name'])->value('id');
                Office::firstOrCreate(['name' => $office['name'], 'agency_id' => $agencyId], [
                    'municipality' => $office['municipality'], 'province' => $office['province'], 'notes' => $office['notes'], 'status' => 'needs_research',
                ]);
            }
        });
    }
}
