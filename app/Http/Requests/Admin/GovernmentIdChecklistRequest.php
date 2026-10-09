<?php

namespace App\Http\Requests\Admin;

use App\Models\GovernmentIdRequirementGroup;
use App\Models\GovernmentIdRequirementItem;
use App\Models\GovernmentIdRequirementWay;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class GovernmentIdChecklistRequest extends GovernmentIdRequirementSetRequest
{
    /** Reuse the CMS rules for unsaved checklists submitted with a new ID. */
    public static function validateForCreation(array $data): array
    {
        // JSON inside a form field does not pass through Laravel's nested input trimming.
        array_walk_recursive($data, function (&$value) {
            if (is_string($value)) $value = trim($value) === '' ? null : trim($value);
        });
        $request = new self();
        $request->replace($data);
        $request->prepareForValidation();
        $rules = $request->rules();
        foreach ($rules as $field => &$rule) {
            if (str_ends_with($field, '.id')) $rule = ['prohibited'];
        }
        unset($rule);
        $rules['id'] = ['prohibited'];
        $validator = \Illuminate\Support\Facades\Validator::make(
            $request->all(), $rules, $request->messages(), $request->attributes(),
        );
        $request->withValidator($validator);
        return $validator->validate();
    }

    protected function prepareForValidation(): void
    {
        $groups = $this->input('groups');
        if (! is_array($groups)) {
            return;
        }
        foreach ($groups as &$group) {
            if (! is_array($group)) {
                continue;
            }
            if (($group['condition_type'] ?? null) !== 'custom') {
                $group['condition_custom'] = null;
            }
            if (array_key_exists('ways', $group) && is_array($group['ways'])) {
                foreach ($group['ways'] as &$way) {
                    if (! is_array($way)) {
                        continue;
                    }
                    if (($way['qualification_type'] ?? null) !== 'custom') {
                        $way['qualification_custom'] = null;
                    }
                    if (($way['qualification_type'] ?? null) === 'none' || ($way['required_count'] ?? null) == 1) {
                        $way['qualification_scope'] = 'every';
                    }
                    $this->normalizeItems($way);
                }
                unset($way);
            } else {
                $this->normalizeItems($group);
            }
        }
        unset($group);
        $this->merge(['groups' => $groups]);
    }

    private function normalizeItems(array &$container): void
    {
        if (! is_array($container['items'] ?? null)) {
            return;
        }
        foreach ($container['items'] as &$item) {
            if (! is_array($item)) {
                continue;
            }
            foreach (['government_id' => 'government_id_id', 'document' => 'document_id', 'custom' => 'custom_name'] as $type => $field) {
                if (($item['type'] ?? null) !== $type) {
                    $item[$field] = null;
                }
            }
            if (($item['type'] ?? null) !== 'custom') {
                $item['quantity'] = null;
            }
            if (($item['submission_format'] ?? null) !== 'custom') {
                $item['submission_format_custom'] = null;
            }
            if (! in_array($item['submission_format'] ?? null, GovernmentIdRequirementItem::COUNTABLE_FORMATS, true)) {
                $item['copies'] = null;
            }
        }
        unset($item);
    }

    private function itemRules(string $prefix): array
    {
        return [
            $prefix => ['required', 'array', 'list', 'min:1', 'max:50'],
            "$prefix.*" => ['array:id,type,government_id_id,document_id,custom_name,submission_format,submission_format_custom,copies,instructions,quantity'],
            "$prefix.*.id" => ['nullable', 'integer', 'distinct'],
            "$prefix.*.type" => ['required', Rule::in(array_keys(GovernmentIdRequirementItem::TYPES))],
            "$prefix.*.government_id_id" => ["required_if:$prefix.*.type,government_id", 'nullable', 'integer', 'exists:government_ids,id'],
            "$prefix.*.document_id" => ["required_if:$prefix.*.type,document", 'nullable', 'integer', 'exists:documents,id'],
            "$prefix.*.custom_name" => ["required_if:$prefix.*.type,custom", 'nullable', 'string', 'max:255'],
            "$prefix.*.submission_format" => ['required', Rule::in(array_keys(GovernmentIdRequirementItem::FORMATS))],
            "$prefix.*.submission_format_custom" => ["required_if:$prefix.*.submission_format,custom", 'nullable', 'string', 'max:255'],
            "$prefix.*.copies" => ['nullable', 'integer', 'min:1', 'max:65535'],
            "$prefix.*.quantity" => ['nullable', 'integer', 'min:1', 'max:65535'],
            "$prefix.*.instructions" => ['nullable', 'string', 'max:10000'],
        ];
    }

    public function rules(): array
    {
        $rules = array_merge(parent::rules(), [
            'groups' => ['present', 'array', 'list', 'max:100'],
            'groups.*' => ['array:id,title,rule,condition_type,condition_custom,items,ways'],
            'groups.*.id' => ['nullable', 'integer', 'distinct'],
            'groups.*.title' => ['nullable', 'string', 'max:255'],
            'groups.*.rule' => ['required', Rule::in(array_keys(GovernmentIdRequirementGroup::RULES))],
            'groups.*.condition_type' => ['required', Rule::in(array_keys(GovernmentIdRequirementGroup::CONDITIONS))],
            'groups.*.condition_custom' => ['required_if:groups.*.condition_type,custom', 'nullable', 'string', 'max:1000'],
            'groups.*.ways' => ['sometimes', 'required', 'array', 'list', 'min:1', 'max:10'],
            'groups.*.ways.*' => ['array:id,required_count,qualification_type,qualification_scope,qualification_custom,items'],
            'groups.*.ways.*.id' => ['nullable', 'integer', 'distinct'],
            'groups.*.ways.*.required_count' => ['required', 'integer', 'min:1', 'max:50'],
            'groups.*.ways.*.qualification_type' => ['required', Rule::in(array_keys(GovernmentIdRequirementWay::QUALIFICATIONS))],
            'groups.*.ways.*.qualification_scope' => ['required', Rule::in(['every', 'at_least_one'])],
            'groups.*.ways.*.qualification_custom' => ['required_if:groups.*.ways.*.qualification_type,custom', 'nullable', 'string', 'max:1000'],
        ]);
        $groups = $this->input('groups');
        foreach (is_array($groups) ? $groups : [] as $index => $group) {
            if (! is_array($group)) {
                continue;
            }
            if (array_key_exists('ways', $group)) {
                $rules["groups.$index.items"] = ['prohibited'];
                $rules = array_merge($rules, $this->itemRules("groups.$index.ways.*.items"));
            } else {
                $rules = array_merge($rules, $this->itemRules("groups.$index.items"));
            }
        }
        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $itemIds = [];
            foreach ($this->input('groups', []) as $index => $group) {
                if (! isset($group['ways'])) {
                    if ($group['rule'] === 'choose_one' && count($group['items']) < 2) {
                        $validator->errors()->add("groups.$index.items", 'Choose any ONE needs at least two options.');
                    }
                    $ways = [['items' => $group['items']]];
                } else {
                    $ways = $group['ways'];
                }
                foreach ($ways as $position => $way) {
                    $prefix = isset($group['ways']) ? "groups.$index.ways.$position" : "groups.$index";
                    if (isset($way['required_count']) && $way['required_count'] > count($way['items'])) {
                        $validator->errors()->add("$prefix.required_count", 'The number needed cannot exceed the accepted items.');
                    }
                    $references = [];
                    foreach ($way['items'] as $item) {
                        if (filled($item['id'] ?? null)) {
                            if (in_array((string) $item['id'], $itemIds, true)) {
                                $validator->errors()->add("$prefix.items", 'An existing item cannot be used twice.');
                            }
                            $itemIds[] = (string) $item['id'];
                        }
                        $reference = match ($item['type']) {
                            'government_id' => 'id:'.$item['government_id_id'],
                            'document' => 'document:'.$item['document_id'],
                            default => 'custom:'.mb_strtolower(trim($item['custom_name'])),
                        };
                        if (isset($group['ways']) && in_array($reference, $references, true)) {
                            $validator->errors()->add("$prefix.items", 'Each accepted item can only be added once per way.');
                        }
                        $references[] = $reference;
                    }
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'groups.present' => 'The checklist could not be read. Please reopen it and try again.',
            '*.required_count.required' => 'Enter how many accepted items are needed.',
            'groups.*.condition_custom.required_if' => 'Describe when this requirement applies.',
        ];
    }
}
