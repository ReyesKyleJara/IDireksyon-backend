<?php

namespace App\Http\Requests\Admin;

use App\Models\GovernmentIdRequirementSet;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GovernmentIdRequirementSetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessCms() ?? false;
    }

    public function rules(): array
    {
        $customApplicant = $this->input('applicant_type') === 'custom';
        $minimum = ['exclude_unless:applicant_type,custom', 'nullable', 'integer', 'min:0', 'max:65535'];
        $maximum = $minimum;
        if ($customApplicant && $this->filled('min_age') && $this->filled('max_age')) {
            $maximum[] = 'gte:min_age';
        }

        return [
            'government_id_id' => ['prohibited'],
            'application_type' => ['required', Rule::in(array_keys(GovernmentIdRequirementSet::APPLICATION_TYPES))],
            'application_type_custom' => ['exclude_unless:application_type,custom', 'required', 'string', 'max:255'],
            'applicant_type' => ['required', Rule::in(array_keys(GovernmentIdRequirementSet::APPLICANT_TYPES))],
            'applicant_type_custom' => ['exclude_unless:applicant_type,custom', 'nullable', 'string', 'max:255'],
            'min_age' => $minimum,
            'max_age' => $maximum,
        ];
    }

    public function attributes(): array
    {
        return [
            'application_type_custom' => 'custom application name',
            'applicant_type_custom' => 'custom applicant label',
            'min_age' => 'minimum age',
            'max_age' => 'maximum age',
        ];
    }
}
