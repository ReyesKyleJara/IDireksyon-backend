<?php

namespace App\Services;

use App\Models\{Document, GovernmentId};
use App\Support\ResidentRequirements;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Informational assistance only. Never writes requirement sets or dependency edges. */
class CatalogSpecialCases
{
    public static function all(Document|GovernmentId $owner): array
    {
        return $owner->research_metadata['special_cases'] ?? [];
    }

    public static function find(Document|GovernmentId $owner, string $id): array
    {
        return collect(self::all($owner))->firstWhere('id', $id) ?? abort(404);
    }

    public static function read(Request $request, Document|GovernmentId $owner): array
    {
        $simplified = $request->input('simplified_editor') === '1' || $request->input('simplified_editor') === 1;
        if ($simplified) {
            $saved = $request->filled('case_id') ? self::find($owner, (string) $request->input('case_id')) : [];
            $scenario = ['application_type' => 'new', 'applicant_type' => 'all'];
            $defaults = [
                'description' => '', 'action_label' => 'View guide', 'status' => 'draft',
                'assistance_title' => null, 'assistance_description' => null,
                'introduction' => null, 'before_you_begin' => null, 'notes' => null,
                'fee_ids' => [], 'office_ids' => [], 'sources' => [],
            ];
            foreach ($defaults as $field => $default) {
                if (!$request->exists($field)) $request->merge([$field => $saved[$field] ?? $default]);
            }
            if (blank($request->input('action_label'))) $request->merge(['action_label' => 'View guide']);
            if ($request->boolean('advanced_content_present')) {
                foreach (['fee_ids', 'office_ids'] as $field) {
                    // Checkbox groups explicitly submit [] when every choice is cleared.
                    $request->merge([$field => $request->input('advanced_'.$field, [])]);
                }
            }
            if ($request->exists('official_source')) {
                $primary = $request->validate([
                    'official_source' => ['nullable', 'url:http,https', 'max:2048'],
                    'source_checked_on' => ['nullable', 'date_format:Y-m-d'],
                    'sources' => ['sometimes', 'array', 'list', 'max:50'],
                    'sources.*' => ['array:title,url,checked_on,scope,published_on,section'],
                ]);
                $sources = $request->input('sources', []);
                if (!is_array($sources)) throw ValidationException::withMessages(['sources' => 'The sources could not be read.']);
                if (filled($primary['official_source'] ?? null)) {
                    $first = array_replace($saved['sources'][0] ?? ['title' => 'Official reference'], $sources[0] ?? []);
                    if (blank($first['title'] ?? null)) $first['title'] = 'Official reference';
                    $first['url'] = $primary['official_source'];
                    $first['checked_on'] = $request->exists('source_checked_on') ? ($primary['source_checked_on'] ?? null) : ($saved['sources'][0]['checked_on'] ?? null);
                    $sources[0] = $first;
                } else {
                    unset($sources[0]);
                }
                ksort($sources);
                $request->merge(['sources' => array_values($sources)]);
            }
            if (!$request->has('requirements_payload')) $request->merge(['requirements_payload' => json_encode([['client_key' => 'special-case', ...$scenario, 'groups' => $saved['requirements'] ?? []]])]);
            if (!$request->has('application_guide_payload')) $request->merge(['application_guide_payload' => json_encode([[...$scenario, 'steps' => $saved['steps'] ?? []]])]);
        }
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => [$simplified ? 'nullable' : 'required', 'string', 'max:2000'],
            'action_label' => ['required', 'string', 'max:120'],
            'assistance_title' => ['nullable', 'string', 'max:255'],
            'assistance_description' => ['nullable', 'string', 'max:2000'],
            'introduction' => ['nullable', 'string', 'max:10000'],
            'before_you_begin' => ['nullable', 'string', 'max:20000'],
            'notes' => ['nullable', 'string', 'max:20000'],
            'status' => ['required', 'in:draft,needs_research,reviewed'],
            'fee_ids' => ['sometimes', 'array', 'list', 'max:100'],
            'fee_ids.*' => ['integer', 'distinct'],
            'office_ids' => ['sometimes', 'array', 'list', 'max:100'],
            'office_ids.*' => ['integer', 'distinct'],
            'requirements_payload' => ['required', 'string', 'max:2000000'],
            'application_guide_payload' => ['required', 'string', 'max:1000000'],
            'sources' => ['sometimes', 'array', 'list', 'max:50'],
        ]);
        $research = Validator::make(['research_metadata' => ['status' => $data['status'], 'sources' => $data['sources'] ?? []]], CatalogEncoding::researchRules())->validate();
        CatalogEncoding::validateResearch($research);
        $checklists = app(GovernmentIdChecklists::class)->readForCreation($request);
        $guides = app(GovernmentIdApplicationGuide::class)->read($request);
        if (count($checklists) !== 1 || count($guides) !== 1) {
            throw ValidationException::withMessages(['requirements_payload' => 'Save one dedicated checklist and guide for this special case.']);
        }
        // These are standalone JSON drafts, never IDs of normal checklists or steps.
        foreach ($guides as $guide) {
            if (isset($guide['id']) || collect($guide['steps'])->contains(fn ($step) => isset($step['id']) || collect($step['blocks'])->contains(fn ($block) => isset($block['id'])))) {
                throw ValidationException::withMessages(['application_guide_payload' => 'Special case steps cannot adopt saved request-channel steps.']);
            }
        }
        foreach (['fee_ids' => $owner->fees()->pluck('id')->all(), 'office_ids' => $owner->offices()->pluck('offices.id')->all()] as $field => $allowed) {
            if (array_diff($data[$field] ?? [], $allowed)) {
                throw ValidationException::withMessages([$field => 'Choose entries already linked to this catalog item.']);
            }
            $data[$field] = array_map('intval', $data[$field] ?? []);
        }
        unset($data['requirements_payload'], $data['application_guide_payload']);
        $data['description'] = $data['description'] ?? '';
        $data['sources'] = $research['research_metadata']['sources'] ?? [];
        $data['requirements'] = $checklists[0]['groups'];
        $data['steps'] = $guides[0]['steps'];
        return $data;
    }

    public static function present(Document|GovernmentId $owner): array
    {
        $owner->loadMissing(['fees.options', 'offices.schedules.intervals']);
        return array_map(fn ($case) => [
            ...$case,
            'requirement_sets' => ResidentRequirements::fromDraft($case['requirements'] ?? [], $case['title']),
            'fees' => $owner->fees->whereIn('id', $case['fee_ids'] ?? [])->map(fn ($fee) => CatalogEncoding::fee($fee))->values()->all(),
            'offices' => $owner->offices->where('status', 'active')->whereIn('id', $case['office_ids'] ?? [])->map(fn ($office) => CatalogEncoding::office($office))->values()->all(),
        ], self::all($owner));
    }
}
