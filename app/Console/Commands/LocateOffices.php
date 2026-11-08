<?php

namespace App\Console\Commands;

use App\Models\Office;
use App\Services\OfficeLocator;
use Illuminate\Console\Command;

class LocateOffices extends Command
{
    protected $signature = 'offices:locate
        {--id=* : Only these office IDs}
        {--limit= : Stop after this many offices}
        {--dry-run : Show what would be searched without calling Google}';

    protected $description = 'Find map locations for offices that have none, using their CMS name and address';

    public function handle(OfficeLocator $locator): int
    {
        $dryRun = (bool) $this->option('dry-run');
        if (! $dryRun && ! $locator->isConfigured()) {
            $this->error('GOOGLE_MAPS_SERVER_API_KEY is not set, so no lookup was made.');

            return self::FAILURE;
        }

        $limit = $this->option('limit');
        if ($limit !== null && (! ctype_digit($limit) || (int) $limit < 1)) {
            $this->error('--limit must be a whole number of at least 1.');

            return self::FAILURE;
        }

        // Manual and already-found offices have a source, so they are always skipped.
        $offices = Office::query()
            ->whereNull('location_source')
            ->when($this->option('id'), fn ($query, $ids) => $query->whereIn('id', $ids))
            ->when($limit, fn ($query, $limit) => $query->limit((int) $limit))
            ->orderBy('id')
            ->get();

        if ($offices->isEmpty()) {
            $this->info('No offices need a location lookup.');

            return self::SUCCESS;
        }

        $totals = [];
        foreach ($offices as $office) {
            $this->line("[{$office->id}] {$office->name}");
            $this->line('    Search:  '.$locator->searchText($office));
            if ($dryRun) {
                continue;
            }

            $result = $locator->locate($office);
            $totals[$result['status']] = ($totals[$result['status']] ?? 0) + 1;
            $this->report($result);
        }

        $this->newLine();
        if ($dryRun) {
            $this->info("Dry run: {$offices->count()} offices would be searched. Google was not called.");

            return self::SUCCESS;
        }

        $this->info(collect($totals)->map(fn ($count, $status) => "$status: $count")->implode(', '));

        return self::SUCCESS;
    }

    private function report(array $result): void
    {
        $this->line('    Result:  '.$result['status'].(isset($result['http_status']) ? " (HTTP {$result['http_status']})" : ''));
        if ($result['status'] !== 'found') {
            return;
        }

        $this->line('    Match:   '.($result['name'] ?? '(no name)'));
        $this->line('    Address: '.($result['address'] ?? '(no address)'));
        $this->line("    Pin:     {$result['latitude']}, {$result['longitude']}");
        $this->line("    Place:   {$result['place_id']}");
    }
}
