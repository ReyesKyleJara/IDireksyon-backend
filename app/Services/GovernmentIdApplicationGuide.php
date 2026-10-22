<?php

namespace App\Services;

use App\Models\ContentChangeLog;
use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\GovernmentIdApplicationStep;
use App\Models\GovernmentIdApplicationStepBlock;
use App\Models\GovernmentIdRequirementSet;
use App\Support\ApplicabilityProfileSummary;
use App\Support\ApplicantContext;
use App\Support\GuideRichText;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GovernmentIdApplicationGuide
{
    public function read(Request $request): array
    {
        if (! $request->has('application_guide_payload')) {
            return [];
        }
        $raw = $request->input('application_guide_payload');
        if (! is_string($raw) || strlen($raw) > 1000000 || ! str_starts_with(ltrim($raw), '[')) {
            $this->fail('The guide is too large or has an invalid format.');
        }
        try {
            $rows = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $this->fail('The guide could not be read. Please check its contents.');
        }
        if (! is_array($rows) || ! array_is_list($rows)) {
            $this->fail('The guide must contain a list of applicability profiles.');
        }
        $data = ['application_guide' => $rows];
        Validator::make($data, [
            'application_guide' => ['array', 'max:51'],
            'application_guide.*' => ['array:everyone,id,client_key,application_type,application_type_custom,applicant_type,applicant_type_custom,min_age,max_age,steps'],
            'application_guide.*.client_key' => $request->routeIs('admin.government-ids.store', 'admin.documents.store')
                ? ['nullable', 'string', 'max:80', 'regex:/^[a-zA-Z0-9_-]+$/', 'distinct'] : ['prohibited'],
            'application_guide.*.everyone' => ['sometimes', 'boolean'],
            'application_guide.*.id' => ['nullable', 'integer', 'min:1', 'distinct'],
            'application_guide.*.application_type' => ['sometimes', Rule::in(array_keys(GovernmentIdRequirementSet::APPLICATION_TYPES))],
            'application_guide.*.application_type_custom' => ['nullable', 'string', 'max:255'],
            'application_guide.*.applicant_type' => ['sometimes', Rule::in(array_keys(GovernmentIdRequirementSet::APPLICANT_TYPES))],
            'application_guide.*.applicant_type_custom' => ['nullable', 'string', 'max:255'],
            'application_guide.*.min_age' => ['nullable', 'integer', 'between:0,65535'],
            'application_guide.*.max_age' => ['nullable', 'integer', 'between:0,65535'],
            'application_guide.*.steps' => ['present', 'array', 'max:100'],
            'application_guide.*.steps.*' => ['array:id,title,short_description,type,sort_order,blocks'],
            'application_guide.*.steps.*.id' => ['nullable', 'integer', 'min:1', 'distinct'],
            'application_guide.*.steps.*.sort_order' => ['sometimes', 'integer', 'between:0,4294967295'],
            'application_guide.*.steps.*.title' => ['required', 'string', 'max:255'],
            'application_guide.*.steps.*.short_description' => ['nullable', 'string', 'max:2000'],
            'application_guide.*.steps.*.type' => ['required', Rule::in(array_keys(GovernmentIdApplicationStep::TYPES))],
            'application_guide.*.steps.*.blocks' => ['present', 'array', 'max:50'],
            'application_guide.*.steps.*.blocks.*' => ['array:id,type,section_title,content'],
            'application_guide.*.steps.*.blocks.*.id' => ['nullable', 'integer', 'min:1', 'distinct'],
            'application_guide.*.steps.*.blocks.*.type' => ['required', Rule::in(array_keys(GovernmentIdApplicationStepBlock::TYPES + GovernmentIdApplicationStepBlock::LEGACY_TYPES))],
            'application_guide.*.steps.*.blocks.*.section_title' => ['nullable', 'string', 'max:255'],
            'application_guide.*.steps.*.blocks.*.content' => ['present', 'array'],
        ])->validate();
        $everyoneRows = 0;
        foreach ($rows as $si => &$row) {
            if ($row['everyone'] ?? false) {
                if (++$everyoneRows > 1 || isset($row['id']) || isset($row['client_key'])) {
                    $this->fail('Everyone must be a single shared guide section without a profile reference.');
                }
            }
            if (($row['application_type'] ?? null) === 'custom' && blank($row['application_type_custom'] ?? null)) {
                $this->fail('Enter a name for the custom application type.');
            }
            if (isset($row['min_age'], $row['max_age']) && $row['max_age'] < $row['min_age']) {
                $this->fail('Maximum age cannot be lower than minimum age.');
            }
            if (! array_is_list($row['steps'])) {
                $this->fail('Steps must be an ordered list.');
            }
            foreach ($row['steps'] as $ti => &$step) {
                $step['title'] = trim($step['title']);
                if ($step['title'] === '') {
                    $this->fail('Give each step a title.');
                }
                if (! array_is_list($step['blocks'])) {
                    $this->fail('Information blocks must be an ordered list.');
                }
                foreach ($step['blocks'] as $bi => &$block) {
                    $path = "application_guide.$si.steps.$ti.blocks.$bi.content";
                    $content = $block['content'];
                    $keys = match ($block['type']) {
                        'instructions', 'checklist' => ['items'],
                        'text', 'heading', 'note', 'warning', 'reminder', 'custom' => ['body'],
                        'official_link' => ['label', 'url', 'description'],
                        default => ['intro'],
                    };
                    if (array_diff(array_keys($content), $keys)) {
                        $this->fail('A guide block contains unsupported fields.');
                    }
                    if (in_array($block['type'], ['instructions', 'checklist'], true)) {
                        $items = $content['items'] ?? null;
                        if (! is_array($items) || ! array_is_list($items) || count($items) < 1 || count($items) > 100) {
                            $this->fail('Add between 1 and 100 instruction/checklist items.');
                        }
                        foreach ($items as $item) {
                            if (! is_array($item) || array_keys($item) !== ['body']) {
                                $this->fail('An instruction item is invalid.');
                            }
                            if ($block['type'] === 'checklist' && is_string($item['body'])) {
                                Validator::make($item, ['body' => ['required', 'string', 'max:20000']])->validate();
                                if (trim($item['body']) === '') {
                                    $this->fail('Checklist items cannot be blank.');
                                }
                            } else {
                                GuideRichText::validate($item['body'], $path);
                            }
                        }
                    } elseif (in_array($block['type'], ['text', 'heading', 'note', 'warning'], true)) {
                        Validator::make($content, ['body' => ['required', 'string', 'max:'.($block['type'] === 'heading' ? 255 : 20000)]])->validate();
                        if (trim($content['body']) === '') {
                            $this->fail('Content blocks cannot be blank.');
                        }
                    } elseif ($block['type'] === 'official_link') {
                        Validator::make($content, ['label' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:2000']])->validate();
                        if (! GuideRichText::safeUrl($content['url'] ?? null)) {
                            $this->fail('Official links must start with https:// or http:// and use a valid address.');
                        }
                    } else {
                        $field = in_array($block['type'], ['reminder', 'custom'], true) ? 'body' : 'intro';
                        GuideRichText::validate($content[$field] ?? null, $path);
                    }
                }
                unset($block);
            }
            unset($step);
        }
        unset($row);

        return $rows;
    }

    /** Called inside the parent form transaction, including its audit writes. */
    public function sync(GovernmentId|Document $id, array $rows, array $draftSets = []): void
    {
        if ($rows === []) {
            return;
        }
        $before = $this->present($id);
        $keptByScope = [];
        $allKeptSteps = [];
        foreach ($rows as $row) {
            $set = null;
            $everyone = (bool) ($row['everyone'] ?? false);
            if ($everyone && $id instanceof Document) {
                $this->fail('Shared document guide steps are not supported yet.');
            }
            if (isset($row['client_key'])) {
                $set = $draftSets[$row['client_key']] ?? null;
                if (! $set || isset($row['id']) || (int) $set->getAttribute($id->getForeignKey()) !== (int) $id->id) {
                    $this->fail('A guide profile could not be linked to Requirements.');
                }
            } elseif (isset($row['id'])) {
                $set = $id->requirementSets()->lockForUpdate()->find($row['id']);
                if (! $set) {
                    $this->fail('This profile no longer belongs to this entry. Reload before saving.');
                }
            } elseif (! $everyone) {
                // Compatibility for older CMS submissions: resolve exact stored fields,
                // never interpret labels or create a separate guide profile.
                if ($id instanceof Document) {
                    $set = $id->requirementSets()->create(collect($row)->except(['everyone', 'steps'])->all());
                } else {
                    $query = $id->requirementSets();
                    foreach (['application_type', 'application_type_custom', 'applicant_type', 'applicant_type_custom'] as $field) {
                        $query->where($field, $row[$field] ?? null);
                    }
                    $minimum = match ($row['applicant_type'] ?? null) {
                        'adult' => 18, 'custom' => $row['min_age'] ?? null, default => null
                    };
                    $maximum = match ($row['applicant_type'] ?? null) {
                        'minor' => 17, 'custom' => $row['max_age'] ?? null, default => null
                    };
                    $matches = $query->where('min_age', $minimum)->where('max_age', $maximum)->get();
                    if ($matches->count() !== 1) {
                        $this->fail('Select an existing Applicability Profile from Requirements.');
                    }
                    $set = $matches->first();
                }
            }
            $scope = $set?->id ?? 'everyone';
            if (array_key_exists($scope, $keptByScope)) {
                $this->fail('Submit each applicability profile only once.');
            }
            $keptSteps = [];
            foreach ($row['steps'] as $order => $input) {
                $steps = $id instanceof Document ? $set->applicationSteps() : $id->applicationSteps();
                $step = isset($input['id']) ? $steps->lockForUpdate()->find($input['id']) : $steps->make();
                if (! $step) {
                    $this->fail('A step does not belong to this entry.');
                }
                $step->requirement_set_id = $set?->id;
                $step->fill(collect($input)->only(['title', 'short_description', 'type'])->all());
                $step->sort_order = $input['sort_order'] ?? $order;
                if ($step->isDirty() || ! $step->exists) {
                    $step->save();
                }
                $keptSteps[] = $step->id;
                $keptBlocks = [];
                foreach ($input['blocks'] as $blockOrder => $blockInput) {
                    $block = isset($blockInput['id']) ? $step->blocks()->find($blockInput['id']) : $step->blocks()->make();
                    if (! $block) {
                        $this->fail('An information block does not belong to this step.');
                    }
                    $block->fill(collect($blockInput)->only(['type', 'section_title', 'content'])->all());
                    $block->sort_order = $blockOrder;
                    if ($block->isDirty() || ! $block->exists) {
                        $block->save();
                    }
                    $keptBlocks[] = $block->id;
                }
                $step->blocks()->whereNotIn('id', $keptBlocks)->delete();
            }
            $keptByScope[$scope] = $keptSteps;
            $allKeptSteps = array_merge($allKeptSteps, $keptSteps);
        }
        // Delete only after all moves are saved, so changing scope cannot delete a moved step.
        foreach ($keptByScope as $scope => $keptSteps) {
            $steps = $id instanceof Document ? $id->requirementSets()->findOrFail($scope)->applicationSteps()
                : $id->applicationSteps()->where('requirement_set_id', $scope === 'everyone' ? null : $scope);
            $steps->whereNotIn('id', $allKeptSteps)->delete();
        }
        if ($before !== $this->present($id)) {
            ContentChangeLog::record($id, 'updated', ['application_guide']);
        }
    }

    public function present(GovernmentId|Document|null $id): array
    {
        if (! $id) {
            return [];
        }
        $rows = $id->requirementSets()->with('applicationSteps.blocks')->get()->map(fn ($set) => [
            ...$set->only(['id', 'application_type', 'application_type_custom', 'applicant_type', 'applicant_type_custom', 'min_age', 'max_age']),
            'label' => $set->display_label,
            'applicability' => ApplicabilityProfileSummary::forProfile($set),
            'steps' => $set->applicationSteps->map(fn ($step) => $this->presentStep($step))->values()->all(),
        ])->values()->all();
        if (! $id instanceof Document) {
            $rows[] = ['everyone' => true, 'label' => 'Everyone', 'steps' => $id->applicationSteps()->whereNull('requirement_set_id')->with('blocks')->get()
                ->map(fn ($step) => $this->presentStep($step))->all()];
        }

        return $rows;
    }

    private function presentStep(GovernmentIdApplicationStep $step): array
    {
        return [
            ...$step->only(['id', 'title', 'short_description', 'type', 'sort_order']),
            'blocks' => $step->blocks->map(fn ($block) => $block->only(['id', 'type', 'section_title', 'content']))->values()->all(),
        ];
    }

    /** Unknown content stays in pending_steps, never silently treated as excluded.
     * Order: explicit sort_order, then shared first, profile ID, step ID for ties.
     */
    public function applicableSteps(GovernmentId $id, ApplicantContext $context): array
    {
        $evaluator = new ApplicabilityEvaluator;
        $steps = $id->applicationSteps()->with(['requirementSet', 'blocks'])->get()
            ->sortBy(fn ($step) => [$step->sort_order, $step->requirement_set_id === null ? 0 : 1, $step->requirement_set_id ?? 0, $step->id]);
        $applicable = [];
        $pending = [];
        $profiles = [];
        foreach ($steps as $step) {
            $matches = $evaluator->stepApplies($step, $context);
            if ($matches === true) {
                $applicable[] = $step;
            } elseif ($matches === null) {
                $pending[] = $step;
                if ($step->requirementSet) {
                    $profiles[$step->requirementSet->id] = $step->requirementSet;
                }
            }
        }

        return ['steps' => $applicable, 'pending_steps' => $pending, 'pending_profiles' => array_values($profiles), 'requires_manual_selection' => $pending !== []];
    }

    /** Manual selection deliberately includes unresolved profiles and shared steps. */
    public function stepsForProfile(GovernmentId $id, int $profileId): array
    {
        $id->requirementSets()->findOrFail($profileId);

        return $id->applicationSteps()->with('blocks')->where(fn ($query) => $query->whereNull('requirement_set_id')->orWhere('requirement_set_id', $profileId))
            ->get()->sortBy(fn ($step) => [$step->sort_order, $step->requirement_set_id === null ? 0 : 1, $step->requirement_set_id ?? 0, $step->id])->values()->all();
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['application_guide_payload' => $message]);
    }
}
