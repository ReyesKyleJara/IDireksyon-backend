<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Models\GovernmentId;
use App\Services\CatalogAgencyValidation;
use Illuminate\Console\Command;

/**
 * Recalculates the cached agency_validation_status column.
 *
 * Preview only unless --apply is given. It writes nothing but that one column,
 * never creates or changes verification rows, and can only report
 * "agency_confirmed" where an approved, current verification already exists.
 */
class RefreshAgencyValidationStatus extends Command
{
    protected $signature = 'catalog:refresh-agency-validation {--apply : Write the recalculated statuses (default: preview only)}';

    protected $description = 'Preview or refresh the cached research validation status of IDs and documents';

    public function handle(CatalogAgencyValidation $validation): int
    {
        $apply = (bool) $this->option('apply');
        $changes = [];

        foreach ([GovernmentId::class, Document::class] as $model) {
            $model::query()->orderBy('id')->each(function ($record) use ($validation, $apply, &$changes) {
                $current = (string) $record->agency_validation_status;
                $calculated = $validation->evaluate($record)['status'];
                if ($current === $calculated) {
                    return;
                }
                $changes[] = [class_basename($record), $record->id, $record->name, $current, $calculated];
                if ($apply) {
                    $validation->refresh($record);
                }
            });
        }

        if ($changes === []) {
            $this->info('All cached statuses are already up to date.');

            return self::SUCCESS;
        }
        $this->table(['Type', 'ID', 'Name', 'Cached', 'Calculated'], $changes);
        $this->info($apply ? count($changes).' cached status(es) updated.'
            : 'Preview only: nothing was written. Re-run with --apply to update '.count($changes).' cached status(es).');

        return self::SUCCESS;
    }
}
