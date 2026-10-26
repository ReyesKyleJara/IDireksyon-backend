<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\Document;
use App\Models\GovernmentId;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

trait EditsCatalogWizard
{
    private function editingWizard(Request $request): bool
    {
        return $request->attributes->get('catalog_wizard_editing', false);
    }

    private function wizardRoutes(Request $request, string $resource): string
    {
        return 'admin.'.$resource.'.'.($this->editingWizard($request) ? 'edit-wizard' : 'wizard');
    }

    private function editBasicData(Request $request, GovernmentId|Document $record, array $data): array
    {
        if (! $this->editingWizard($request)) return $data;

        foreach (['eligibility', 'validity', 'processing_time'] as $summary) {
            $fields = array_filter(array_keys($record->getAttributes() + $data),
                fn ($key) => str_starts_with($key, $summary.'_'));
            if (array_intersect($fields, array_keys($request->all())) === []) {
                $data = Arr::except($data, [$summary, ...$fields]);
                continue;
            }
            // Preserve text-only research when no structured choice was changed.
            $hasSavedStructure = collect($fields)->contains(fn ($key) => filled($record->$key));
            $hasNewStructure = collect($fields)->contains(fn ($key) => filled($data[$key] ?? null));
            if (! $hasSavedStructure && ! $hasNewStructure) unset($data[$summary]);
        }

        return $data;
    }

    // An empty legacy record gets its first scenario only when the editor saves.
    // Repeated submissions reuse that scenario instead of creating another one.
    private function editGuideRows(GovernmentId|Document $record, array $rows): array
    {
        foreach ($rows as &$row) {
            if (! isset($row['id']) && ! isset($row['client_key'])) {
                $identity = Arr::except($row, ['id', 'steps', 'client_key']);
                $row['id'] = $record->requirementSets()->firstOrCreate($identity)->id;
            }
        }

        return $rows;
    }
}
