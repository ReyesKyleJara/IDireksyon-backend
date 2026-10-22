<?php

namespace App\Http\Requests\Api;

use App\Models\GovernmentIdRequirementSet;
use App\Support\ApplicantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class GovernmentIdApplicabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // JSON numeric strings, fractions and booleans must not become applicant facts.
        $integer = function ($attribute, $value, $fail) {
            if (! is_int($value)) {
                $fail("The {$attribute} field must be a JSON integer.");
            }
        };

        return [
            'age' => ['sometimes', 'nullable', $integer, 'integer', 'between:0,65535'],
            'application_type' => ['sometimes', 'nullable', 'string', Rule::in(array_keys(GovernmentIdRequirementSet::APPLICATION_TYPES))],
            'owned_government_id_ids' => ['sometimes', 'array', 'list', 'max:500'],
            'owned_government_id_ids.*' => ['bail', $integer, 'integer', 'min:1', 'distinct:strict', Rule::exists('government_ids', 'id')],
            'owned_document_ids' => ['sometimes', 'array', 'list', 'max:500'],
            'owned_document_ids.*' => ['bail', $integer, 'integer', 'min:1', 'distinct:strict', Rule::exists('documents', 'id')],
            'selected_profile_id' => ['sometimes', 'nullable', 'bail', $integer, 'integer', 'min:1',
                Rule::exists('government_id_requirement_sets', 'id')->where('government_id_id', $this->route('governmentId')->id)],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            foreach (array_diff(array_keys($this->all()), ['age', 'application_type', 'owned_government_id_ids', 'owned_document_ids', 'selected_profile_id']) as $field) {
                $validator->errors()->add($field, 'This field is not supported by the applicability request.');
            }
        }];
    }

    public function applicantContext(): ApplicantContext
    {
        $data = $this->validated();

        return new ApplicantContext(
            $data['age'] ?? null,
            $data['application_type'] ?? null,
            $data['owned_government_id_ids'] ?? [],
            $data['owned_document_ids'] ?? [],
        );
    }
}
