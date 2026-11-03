<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\Document;
use App\Models\GovernmentId;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

trait EditsCatalogWizard
{
    private function saveSectionVisibility(Request $request, GovernmentId|Document $record, string $step): void
    {
        if (!$request->has('section_visibility')) return;
        $allowed = match ($step) {
            'requirements', 'fees', 'offices' => [$step],
            'guide' => ['guide', 'troubleshooting'],
            default => [],
        };
        abort_if($allowed === [], 422);
        $data = $request->validate([
            'section_visibility' => ['required', 'array:'.implode(',', $allowed)],
            'section_visibility.*' => ['required', 'boolean'],
        ]);
        $research = $record->research_metadata ?? [];
        $hidden = $research['hidden_sections'] ?? [];
        foreach ($data['section_visibility'] as $section => $visible) {
            if ($visible) $hidden = array_values(array_diff($hidden, [$section]));
            elseif (!in_array($section, $hidden, true)) $hidden[] = $section;
        }
        $updated = [...$research, 'hidden_sections' => $hidden];
        if ($research !== $updated) {
            $record->update(['research_metadata' => $updated]);
            \App\Models\ContentChangeLog::record($record, 'updated', ['research_metadata']);
        }
    }

    private function selectedCatalogPath(Request $request, GovernmentId|Document $record): ?int
    {
        $request->validate(['path' => ['nullable', 'integer', 'min:0']]);
        $key = 'catalog_selected_path.'.($record instanceof Document ? 'document' : 'government_id').'.'.$record->id;
        $sets = $record->requirementSets;
        $explicit = $request->integer('path') ?: null;
        if ($explicit && ! $sets->contains('id', $explicit)) abort(404);
        $selected = $explicit ?? $request->session()->get($key);
        if (! $sets->contains('id', $selected)) $selected = $sets->first()?->id;
        $request->session()->put($key, $selected);
        return $selected;
    }

    private function optionScope(GovernmentId|Document $record, ?int $path, string $field): ?array
    {
        return $record->requirementSets->firstWhere('id', $path)?->request_channel[$field] ?? null;
    }

    private function saveOptionFees(Request $request, GovernmentId|Document $record, array $rows, ?int $path): void
    {
        $scope = $request->boolean('option_content') ? $this->optionScope($record, $path, 'fee_ids') : null;
        if ($scope === null) { $this->syncFees($record, $this->prepareFeeRows($rows), true); return; }
        $submitted = $this->prepareFeeRows($rows);
        $ids = collect($submitted)->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();
        if (array_diff($ids, $scope)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['fees' => 'A submitted fee is not selected for this request option. Reload before editing it.']);
        }
        $record->loadMissing('fees.options');
        $before = $record->fees->pluck('id')->all();
        $updates = collect($submitted)->filter(fn ($row) => filled($row['id'] ?? null))->keyBy('id');
        // Keep every reusable entry, including those removed from this option's list.
        $merged = $record->fees->sortBy('sort_order')->map(fn ($fee) => $updates->get($fee->id)
            ?? [...$fee->only(['id', 'label', 'type', 'amount_min', 'amount_max', 'currency', 'is_optional', 'notes']), 'options' => $fee->options->toArray()])->values()->all();
        $merged = [...$merged, ...collect($submitted)->filter(fn ($row) => empty($row['id']))->values()->all()];
        $this->syncFees($record, $this->prepareFeeRows($merged), true);
        $newIds = array_values(array_diff($record->fees()->pluck('id')->all(), $before));
        $set = $record->requirementSets->firstWhere('id', $path);
        $set->update(['request_channel' => [...$set->request_channel, 'fee_ids' => [...$ids, ...$newIds]]]);
        \App\Models\ContentChangeLog::record($record, 'updated', ['request_channels']);
    }

    private function saveOptionOffices(Request $request, GovernmentId|Document $record, array $rows, ?int $path): void
    {
        // Offices are record-level, even when an older form sends option_content.
        // Preserve all existing Request Method metadata without rewriting it.
        $this->syncOfficeLinks($record, $rows);
    }

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
        // Visibility now belongs to the individual sections, not the Basic Info form.
        if (isset($data['research_metadata']) && !$request->has('research_metadata.hidden_sections')) {
            $data['research_metadata']['hidden_sections'] = $record->research_metadata['hidden_sections'] ?? [];
        }
        // Dedicated guides are managed separately; a Basic Info save must retain them.
        if (isset($data['research_metadata']) && array_key_exists('special_cases', $record->research_metadata ?? [])) {
            $data['research_metadata']['special_cases'] = $record->research_metadata['special_cases'];
        }
        if (! $this->editingWizard($request)) return $data;

        foreach (['eligibility', 'validity', 'processing_time'] as $summary) {
            $fields = array_filter(array_keys($record->getAttributes() + $data),
                fn ($key) => str_starts_with($key, $summary.'_') && $key !== 'processing_time_note');
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
