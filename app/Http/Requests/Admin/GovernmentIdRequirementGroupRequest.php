<?php

namespace App\Http\Requests\Admin;

use App\Models\GovernmentIdRequirementGroup;
use App\Models\GovernmentIdRequirementItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GovernmentIdRequirementGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessCms() ?? false;
    }

    protected function prepareForValidation(): void
    {
        // Clear fields that no longer apply when a researcher changes a selection.
        $data = [];
        if ($this->input('condition_type') !== 'custom') {
            $data['condition_custom'] = null;
        }
        if (is_array($this->input('items'))) {
            $data['items'] = array_map(function ($row) {
                if (! is_array($row)) {
                    return $row;
                }
                foreach (['government_id' => 'government_id_id', 'document' => 'document_id', 'custom' => 'custom_name'] as $type => $field) {
                    if (($row['type'] ?? null) !== $type) {
                        $row[$field] = null;
                    }
                }
                if (($row['submission_format'] ?? null) !== 'custom') {
                    $row['submission_format_custom'] = null;
                }
                if (! in_array($row['submission_format'] ?? null, GovernmentIdRequirementItem::COUNTABLE_FORMATS, true)) {
                    $row['copies'] = null;
                }
                return $row;
            }, $this->input('items'));
        }
        $this->merge($data);
    }

    public function rules(): array
    {
        $idRules = $this->route('requirementGroup') === null
            ? ['prohibited']
            : ['nullable', 'integer', 'distinct', Rule::exists('government_id_requirement_items', 'id')
                ->where('requirement_group_id', $this->route('requirementGroup'))];

        return [
            'requirement_set_id' => ['prohibited'],
            'title' => ['nullable', 'string', 'max:255'],
            'rule' => ['required', Rule::in(array_keys(GovernmentIdRequirementGroup::RULES))],
            'condition_type' => ['required', Rule::in(array_keys(GovernmentIdRequirementGroup::CONDITIONS))],
            'condition_custom' => ['required_if:condition_type,custom', 'nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:'.($this->input('rule') === 'choose_one' ? 2 : 1), 'max:50'],
            'items.*' => ['required', 'array:id,type,government_id_id,document_id,custom_name,submission_format,submission_format_custom,copies,instructions'],
            'items.*.id' => $idRules,
            'items.*.type' => ['required', Rule::in(array_keys(GovernmentIdRequirementItem::TYPES))],
            'items.*.government_id_id' => ['required_if:items.*.type,government_id', 'nullable', 'integer', 'exists:government_ids,id'],
            'items.*.document_id' => ['required_if:items.*.type,document', 'nullable', 'integer', 'exists:documents,id'],
            'items.*.custom_name' => ['required_if:items.*.type,custom', 'nullable', 'string', 'max:255'],
            'items.*.submission_format' => ['required', Rule::in(array_keys(GovernmentIdRequirementItem::FORMATS))],
            'items.*.submission_format_custom' => ['required_if:items.*.submission_format,custom', 'nullable', 'string', 'max:255'],
            'items.*.copies' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'items.*.instructions' => ['nullable', 'string', 'max:10000'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Add at least one requirement item.',
            'items.min' => 'Choose 1 needs at least two options. All Required needs at least one item.',
            'items.*.government_id_id.required_if' => 'Select a Government ID for each Government ID item.',
            'items.*.document_id.required_if' => 'Select a Document for each Document item.',
            'items.*.custom_name.required_if' => 'Enter a name for each Custom requirement.',
            'items.*.submission_format_custom.required_if' => 'Describe the custom submission format.',
            'condition_custom.required_if' => 'Enter a clear question for this custom condition.',
        ];
    }
}
