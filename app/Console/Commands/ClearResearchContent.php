<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ClearResearchContent extends Command
{
    protected $signature = 'idireksyon:clear-research {--apply : Back up and delete all Government IDs and offices}';
    protected $description = 'Preview or clear ID and office research while preserving documents, agencies, users and audit logs';

    private const TABLES = [
        'government_id_application_step_blocks',
        'government_id_application_steps',
        'government_id_requirement_items',
        'government_id_requirement_ways',
        'government_id_requirement_groups',
        'government_id_requirement_sets',
        'government_id_fees',
        'government_id_office',
        'office_schedule_intervals',
        'office_schedules',
        'government_ids',
        'offices',
    ];

    public function handle(): int
    {
        $backupPath = null;
        try {
            if (! $this->option('apply')) {
                $this->info('PREVIEW ONLY — no data will be changed.');
                $this->table(['Table', 'Rows'], array_map(
                    fn ($table) => [$table, DB::table($table)->count()], self::TABLES
                ));
                return self::SUCCESS;
            }

            DB::transaction(function () use (&$backupPath) {
                $tables = [];
                foreach (self::TABLES as $table) {
                    $tables[$table] = DB::table($table)->orderBy('id')->lockForUpdate()->get()->toArray();
                }
                $backupPath = 'research-backups/'.now()->format('Ymd-His').'-'.Str::uuid().'.json';
                $json = json_encode([
                    'format_version' => 1,
                    'created_at' => now()->toIso8601String(),
                    'tables' => $tables,
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                $disk = Storage::disk('local');
                if (! $disk->put($backupPath, $json) || $disk->get($backupPath) !== $json) {
                    throw new RuntimeException('Backup could not be saved and verified.');
                }
                foreach (self::TABLES as $table) {
                    foreach (array_chunk(array_column($tables[$table], 'id'), 500) as $ids) {
                        DB::table($table)->whereIn('id', $ids)->delete();
                    }
                }
            });
            $this->info('Government ID and office research cleared. Documents, agencies, users and audit logs preserved.');
            $this->line('Backup: '.Storage::disk('local')->path($backupPath));
            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Cleanup failed; database changes were rolled back. '.$exception->getMessage());
            if ($backupPath !== null) {
                $this->line('Backup location (if written): '.Storage::disk('local')->path($backupPath));
            }
            return self::FAILURE;
        }
    }
}
