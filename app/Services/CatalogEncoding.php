<?php

namespace App\Services;

use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\GovernmentIdRequirementSet;
use App\Support\ResidentRequirements;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CatalogEncoding
{
    public const CHANNELS = ['outlet', 'online', 'partner', 'hybrid'];
    public const FULFILLMENT = ['pickup', 'delivery', 'download', 'email', 'view_online'];
    public const SECTIONS = ['requirements', 'fees', 'guide', 'offices', 'troubleshooting'];

    public static function researchRules(): array
    {
        return [
            'research_metadata' => ['sometimes', 'array:status,sources,hidden_sections,validity_note'],
            'research_metadata.status' => ['required_with:research_metadata', 'in:draft,needs_research,reviewed'],
            'research_metadata.validity_note' => ['nullable', 'string', 'max:2000'],
            'research_metadata.hidden_sections' => ['sometimes', 'array', 'max:5'],
            'research_metadata.hidden_sections.*' => [Rule::in(self::SECTIONS), 'distinct'],
            'research_metadata.sources' => ['sometimes', 'array', 'list', 'max:50'],
            'research_metadata.sources.*' => ['array:title,url,checked_on,scope,published_on,section'],
            'research_metadata.sources.*.title' => ['required', 'string', 'max:255'],
            'research_metadata.sources.*.url' => ['required', 'url:http,https', 'max:2048'],
            'research_metadata.sources.*.checked_on' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:'.now('Asia/Manila')->toDateString()],
            'research_metadata.sources.*.scope' => ['nullable', 'string', 'max:255'],
            'research_metadata.sources.*.published_on' => ['nullable', 'date_format:Y-m-d'],
            'research_metadata.sources.*.section' => ['nullable', 'string', 'max:255'],
        ];
    }

    public static function validateResearch(array $data): void
    {
        $research = $data['research_metadata'] ?? null;
        if (($research['status'] ?? null) === 'reviewed' && (empty($research['sources']) || collect($research['sources'])->contains(fn ($source) => empty($source['checked_on'])))) {
            throw ValidationException::withMessages(['research_metadata.sources' => 'Reviewed content needs official sources and an explicit checked date for each source.']);
        }
    }

    public static function channel(array $data, Document|GovernmentId $owner): array
    {
        $validated = Validator::make($data, [
            'label' => ['required', 'string', 'max:255'],
            'channel' => ['required', Rule::in(self::CHANNELS)],
            'provider' => ['nullable', 'string', 'max:255'],
            'portal_url' => ['nullable', 'url:http,https', 'max:2048'],
            'fulfillment' => ['present', 'array', 'list', 'max:5'],
            'fulfillment.*' => [Rule::in(self::FULFILLMENT), 'distinct'],
            'appointment' => ['required', 'in:required,not_required,conditional,unknown'],
            'applicability' => ['nullable', 'string', 'max:4000'],
            'preparation' => ['nullable', 'string', 'max:10000'],
            'application_guide_text' => ['nullable', 'string', 'max:10000'],
            'processing' => ['nullable', 'string', 'max:2000'],
            'fee_ids' => ['nullable', 'array', 'list', 'max:100'],
            'fee_ids.*' => ['integer', 'distinct'],
            'office_ids' => ['nullable', 'array', 'list', 'max:100'],
            'office_ids.*' => ['integer', 'distinct'],
            'troubleshooting' => ['sometimes', 'array', 'list', 'max:30'],
            'troubleshooting.*' => ['array:title,body,url'],
            'troubleshooting.*.title' => ['required', 'string', 'max:255'],
            'troubleshooting.*.body' => ['required', 'string', 'max:10000'],
            'troubleshooting.*.url' => ['nullable', 'url:http,https', 'max:2048'],
        ])->validate();
        foreach (['fee_ids' => $owner->fees()->pluck('id')->all(), 'office_ids' => $owner->offices()->pluck('offices.id')->all()] as $field => $allowed) {
            if (array_diff($validated[$field] ?? [], $allowed)) {
                throw ValidationException::withMessages(["request_channel.$field" => 'Select only saved entries belonging to this record.']);
            }
            if (isset($validated[$field])) $validated[$field] = array_map('intval', $validated[$field]);
        }
        return $validated;
    }

    public static function assertReferencesRetained(Document|GovernmentId $owner, string $field, array $retained): void
    {
        foreach (CatalogSpecialCases::all($owner) as $case) {
            if (array_diff($case[$field] ?? [], $retained)) {
                throw ValidationException::withMessages([$field => 'This entry is selected by a special case guide. Update that guide before removing it.']);
            }
        }
        foreach ($owner->requirementSets()->get() as $set) {
            if (array_diff($set->request_channel[$field] ?? [], $retained)) {
                throw ValidationException::withMessages([$field => 'This entry is selected by a request channel. Update that channel before removing it.']);
            }
        }
    }

    public static function fee($fee): array
    {
        return [...$fee->only(['id', 'label', 'type', 'amount_min', 'amount_max', 'currency', 'description', 'notes']),
            'is_optional' => (bool) ($fee->is_optional ?? false),
            'options' => $fee->relationLoaded('options') ? $fee->options->map(fn ($option) => self::fee($option))->values()->all() : []];
    }

    public static function office($office): array
    {
        $usable = $office->hasUsableLocation();

        return [...$office->only(['id', 'name', 'address', 'municipality', 'province', 'source_url']),
            'latitude' => $usable ? $office->latitude : null,
            'longitude' => $usable ? $office->longitude : null,
            'service_notes' => $office->pivot?->service_notes,
            'services' => $office->pivot?->only(['new_application_status', 'renewal_status', 'replacement_status']),
            'schedules' => $office->schedules->map(fn ($schedule) => [...$schedule->only(['day_of_week', 'status']),
                'intervals' => $schedule->intervals->map->only(['opens_at', 'closes_at'])->values()->all()])->values()->all()];
    }

    public static function detail(Document|GovernmentId $owner): array
    {
        $owner->loadMissing(['agency', 'requirementSets.groups.items.governmentId', 'requirementSets.groups.items.document',
            'requirementSets.groups.ways.items.governmentId', 'requirementSets.groups.ways.items.document',
            'requirementSets.applicationSteps.blocks', 'fees.options', 'offices.schedules.intervals']);
        $research = $owner->research_metadata ?? [];
        $hidden = $research['hidden_sections'] ?? [];
        $requirements = ResidentRequirements::forId($owner);
        $paths = $owner->requirementSets->map(function ($set) use ($owner, $requirements, $hidden) {
            $channel = $set->request_channel ?? [];
            $fees = $owner->fees->filter(fn ($fee) => ! array_key_exists('fee_ids', $channel) || $channel['fee_ids'] === null || in_array($fee->id, $channel['fee_ids'], true));
            $offices = $owner->offices->filter(fn ($office) => ! array_key_exists('office_ids', $channel) || $channel['office_ids'] === null || in_array($office->id, $channel['office_ids'], true));
            $guide = $set->applicationSteps->map(fn ($step) => [...$step->only(['id', 'title', 'short_description', 'type']),
                'blocks' => $step->blocks->map->only(['id', 'type', 'section_title', 'content'])->values()->all()])->values()->all();
            return [...$set->only(['id', 'application_type', 'application_type_custom', 'applicant_type', 'applicant_type_custom']),
                'label' => $channel['label'] ?? $set->display_label,
                'is_request_method' => ResidentRequirements::isRequestMethod($set),
                'channel' => $channel['channel'] ?? null, 'provider' => $channel['provider'] ?? null,
                'portal_url' => $channel['portal_url'] ?? null, 'fulfillment' => $channel['fulfillment'] ?? [],
                'appointment' => $channel['appointment'] ?? 'unknown', 'applicability' => $channel['applicability'] ?? null,
                'processing' => $channel['processing'] ?? $owner->processing_time,
                'fee_ids' => $channel['fee_ids'] ?? null,
                'preparation' => in_array('guide', $hidden) ? null : ($channel['preparation'] ?? null),
                'application_guide_text' => in_array('guide', $hidden) ? null : ($channel['application_guide_text'] ?? null),
                'requirement_sets' => in_array('requirements', $hidden) ? [] : array_values(array_filter($requirements, fn ($row) => $row['id'] === $set->id)),
                'fees' => in_array('fees', $hidden) ? [] : $fees->map(fn ($fee) => self::fee($fee))->values()->all(),
                'offices' => in_array('offices', $hidden) ? [] : $offices->map(fn ($office) => self::office($office))->values()->all(),
                'guide' => in_array('guide', $hidden) ? [] : $guide,
                'troubleshooting' => in_array('troubleshooting', $hidden) ? [] : ($channel['troubleshooting'] ?? []),
            ];
        })->values()->all();
        return [
            'record_type' => $owner instanceof Document ? 'document' : 'government_id',
            // Main Offices tab belongs to the record, independent of channel filters.
            'offices' => in_array('offices', $hidden) ? [] : $owner->offices->map(fn ($office) => self::office($office))->values()->all(),
            'research_metadata' => \Illuminate\Support\Arr::except($research, ['special_cases']), 'request_paths' => $paths,
            'special_cases' => in_array('troubleshooting', $hidden) ? [] : CatalogSpecialCases::present($owner),
            'resident_notice' => ($research['status'] ?? null) === 'reviewed' ? null : 'Research is not yet reviewed. Confirm current instructions with the issuing agency.',
        ];
    }

    /** Fees explicitly assigned to a method are not general estimated costs. */
    public static function generalFees(Document|GovernmentId $owner)
    {
        $methodFeeIds = $owner->requirementSets
            ->filter(fn ($set) => ResidentRequirements::isRequestMethod($set))
            ->flatMap(fn ($set) => $set->request_channel['fee_ids'] ?? [])
            ->map(fn ($id) => (int) $id)->unique()->all();

        return $owner->fees->reject(fn ($fee) => in_array($fee->id, $methodFeeIds, true));
    }
}
