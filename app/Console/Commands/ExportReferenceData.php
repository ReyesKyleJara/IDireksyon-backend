<?php

namespace App\Console\Commands;

use App\Services\ReferenceData;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

class ExportReferenceData extends Command
{
    protected $signature = 'idireksyon:export-reference-data
        {--development : Explicitly authorize a DEVELOPMENT ONLY, unverified snapshot}
        {--write : Write the reviewed development snapshot}
        {--approve-hash= : Exact SHA-256 fingerprint from the reviewed preview}
        {--output= : Optional output path; defaults to development-reference-data.json}';

    protected $description = 'Preview DEVELOPMENT ONLY CMS reference data; require explicit development authorization and fingerprint to export';

    public function handle(ReferenceData $references): int
    {
        try {
            $data = $references->capture();
            $hash = $references->fingerprint($data);
            $this->warn(ReferenceData::DEVELOPMENT_METADATA['warning']);
            $this->table(['Included table', 'Records'], array_map(
                fn ($table, $rows) => [$table, count($rows)], array_keys($data['tables']), array_values($data['tables'])
            ));
            $this->line('Preview fingerprint: '.$hash);
            $this->line('Accounts, resident data, audit logs, and timestamps are excluded. last_verified_by is null.');
            $this->warn('Review all CMS text and guide content for personal information before approving this fingerprint.');
            if (! $this->option('write')) {
                $this->info('PREVIEW ONLY: no file or database writes.');

                return self::SUCCESS;
            }
            if (! $this->option('development')) {
                throw new RuntimeException('Writing requires --development. This does not approve content for production.');
            }
            if (! hash_equals($hash, (string) $this->option('approve-hash'))) {
                throw new RuntimeException('Approval fingerprint is missing or stale. Preview and review the current content first.');
            }
            $path = $this->option('output') ?: database_path('data/development-reference-data.json');
            if (is_file($path) && ($this->input->hasParameterOption('--no-interaction') || ! $this->confirm('Replace the existing DEVELOPMENT ONLY snapshot at '.$path.'?', false))) {
                throw new RuntimeException('Existing dataset was preserved; explicit interactive replacement confirmation is required.');
            }
            $data['snapshot_state'] = 'exported';
            $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
            $temporary = tempnam(dirname($path), '.reference-');
            if ($temporary === false) {
                throw new RuntimeException('Could not create temporary dataset file.');
            }
            try {
                if (file_put_contents($temporary, $json) !== strlen($json) || ! rename($temporary, $path)) {
                    throw new RuntimeException('Could not save dataset atomically.');
                }
            } finally {
                if (is_file($temporary)) {
                    unlink($temporary);
                }
            }
            $this->info('DEVELOPMENT ONLY snapshot written: '.$path.'. Review the Git diff before committing.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
