<?php

namespace App\Services;

use App\Models\ContentChangeLog;
use App\Models\GovernmentId;
use App\Models\GovernmentIdApplicationStep;
use App\Models\GovernmentIdApplicationStepBlock;
use App\Models\GovernmentIdRequirementSet;
use App\Support\GuideRichText;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GovernmentIdApplicationGuide
{
    public function read(Request $request): array
    {
        if (! $request->has('application_guide_payload')) return [];
        $raw = $request->input('application_guide_payload');
        if (! is_string($raw) || strlen($raw) > 1000000 || ! str_starts_with(ltrim($raw), '[')) $this->fail('The guide is too large or has an invalid format.');
        try { $rows = json_decode($raw, true, 32, JSON_THROW_ON_ERROR); }
        catch (\JsonException) { $this->fail('The guide could not be read. Please check its contents.'); }
        if (! is_array($rows) || ! array_is_list($rows)) $this->fail('The guide must contain a list of scenarios.');
        $data = ['application_guide' => $rows];
        Validator::make($data, [
            'application_guide' => ['array', 'max:50'],
            'application_guide.*' => ['array:id,application_type,application_type_custom,applicant_type,applicant_type_custom,min_age,max_age,steps'],
            'application_guide.*.id' => ['nullable', 'integer', 'min:1', 'distinct'],
            'application_guide.*.application_type' => ['required', Rule::in(array_keys(GovernmentIdRequirementSet::APPLICATION_TYPES))],
            'application_guide.*.application_type_custom' => ['nullable', 'string', 'max:255'],
            'application_guide.*.applicant_type' => ['required', Rule::in(array_keys(GovernmentIdRequirementSet::APPLICANT_TYPES))],
            'application_guide.*.applicant_type_custom' => ['nullable', 'string', 'max:255'],
            'application_guide.*.min_age' => ['nullable', 'integer', 'between:0,65535'],
            'application_guide.*.max_age' => ['nullable', 'integer', 'between:0,65535'],
            'application_guide.*.steps' => ['present', 'array', 'max:100'],
            'application_guide.*.steps.*' => ['array:id,title,short_description,type,blocks'],
            'application_guide.*.steps.*.id' => ['nullable', 'integer', 'min:1', 'distinct'],
            'application_guide.*.steps.*.title' => ['required', 'string', 'max:255'],
            'application_guide.*.steps.*.short_description' => ['nullable', 'string', 'max:2000'],
            'application_guide.*.steps.*.type' => ['required', Rule::in(array_keys(GovernmentIdApplicationStep::TYPES))],
            'application_guide.*.steps.*.blocks' => ['present', 'array', 'max:50'],
            'application_guide.*.steps.*.blocks.*' => ['array:id,type,section_title,content'],
            'application_guide.*.steps.*.blocks.*.id' => ['nullable', 'integer', 'min:1', 'distinct'],
            'application_guide.*.steps.*.blocks.*.type' => ['required', Rule::in(array_keys(GovernmentIdApplicationStepBlock::TYPES))],
            'application_guide.*.steps.*.blocks.*.section_title' => ['nullable', 'string', 'max:255'],
            'application_guide.*.steps.*.blocks.*.content' => ['present', 'array'],
        ])->validate();
        foreach ($rows as $si => &$row) {
            if ($row['application_type'] === 'custom' && blank($row['application_type_custom'] ?? null)) $this->fail('Enter a name for the custom application type.');
            if (isset($row['min_age'], $row['max_age']) && $row['max_age'] < $row['min_age']) $this->fail('Maximum age cannot be lower than minimum age.');
            if (! array_is_list($row['steps'])) $this->fail('Steps must be an ordered list.');
            foreach ($row['steps'] as $ti => &$step) {
                $step['title'] = trim($step['title']);
                if ($step['title'] === '') $this->fail('Give each step a title.');
                if (! array_is_list($step['blocks'])) $this->fail('Information blocks must be an ordered list.');
                foreach ($step['blocks'] as $bi => &$block) {
                    $path = "application_guide.$si.steps.$ti.blocks.$bi.content";
                    $content = $block['content'];
                    $keys = match ($block['type']) {
                        'instructions', 'checklist' => ['items'],
                        'reminder', 'custom' => ['body'],
                        'official_link' => ['label', 'url', 'description'],
                        default => ['intro'],
                    };
                    if (array_diff(array_keys($content), $keys)) $this->fail('A guide block contains unsupported fields.');
                    if (in_array($block['type'], ['instructions', 'checklist'], true)) {
                        $items = $content['items'] ?? null;
                        if (! is_array($items) || ! array_is_list($items) || count($items) < 1 || count($items) > 100) $this->fail('Add between 1 and 100 instruction/checklist items.');
                        foreach ($items as $item) {
                            if (! is_array($item) || array_keys($item) !== ['body']) $this->fail('An instruction item is invalid.');
                            GuideRichText::validate($item['body'], $path);
                        }
                    } elseif ($block['type'] === 'official_link') {
                        Validator::make($content, ['label' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:2000']])->validate();
                        if (! GuideRichText::safeUrl($content['url'] ?? null)) $this->fail('Official links must start with https:// or http:// and use a valid address.');
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
    public function sync(GovernmentId $id, array $rows): void
    {
        if ($rows === []) return;
        $before = $this->present($id);
        foreach ($rows as $row) {
            $set = isset($row['id']) ? $id->requirementSets()->lockForUpdate()->find($row['id']) : null;
            if (isset($row['id']) && ! $set) $this->fail('This scenario no longer belongs to this ID. Reload before saving.');
            if (! $set) $set = $id->requirementSets()->create(collect($row)->except(['id', 'steps'])->all());
            $keptSteps = [];
            foreach ($row['steps'] as $order => $input) {
                $step = isset($input['id']) ? $set->applicationSteps()->find($input['id']) : $set->applicationSteps()->make();
                if (! $step) $this->fail('A step does not belong to the selected scenario.');
                $step->fill(collect($input)->only(['title', 'short_description', 'type'])->all());
                $step->sort_order = $order;
                if ($step->isDirty() || ! $step->exists) $step->save();
                $keptSteps[] = $step->id;
                $keptBlocks = [];
                foreach ($input['blocks'] as $blockOrder => $blockInput) {
                    $block = isset($blockInput['id']) ? $step->blocks()->find($blockInput['id']) : $step->blocks()->make();
                    if (! $block) $this->fail('An information block does not belong to this step.');
                    $block->fill(collect($blockInput)->only(['type', 'section_title', 'content'])->all());
                    $block->sort_order = $blockOrder;
                    if ($block->isDirty() || ! $block->exists) $block->save();
                    $keptBlocks[] = $block->id;
                }
                $step->blocks()->whereNotIn('id', $keptBlocks)->delete();
            }
            $set->applicationSteps()->whereNotIn('id', $keptSteps)->delete();
        }
        if ($before !== $this->present($id)) ContentChangeLog::record($id, 'updated', ['application_guide']);
    }

    public function present(?GovernmentId $id): array
    {
        if (! $id) return [];
        return $id->requirementSets()->with('applicationSteps.blocks')->get()->map(fn ($set) => [
            ...$set->only(['id', 'application_type', 'application_type_custom', 'applicant_type', 'applicant_type_custom', 'min_age', 'max_age']),
            'label' => $set->display_label,
            'steps' => $set->applicationSteps->map(fn ($step) => [
                ...$step->only(['id', 'title', 'short_description', 'type']),
                'blocks' => $step->blocks->map(fn ($block) => $block->only(['id', 'type', 'section_title', 'content']))->values()->all(),
            ])->values()->all(),
        ])->values()->all();
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['application_guide_payload' => $message]);
    }
}
