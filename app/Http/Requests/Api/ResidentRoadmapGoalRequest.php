<?php

namespace App\Http\Requests\Api;

use Illuminate\Support\Arr;

class ResidentRoadmapGoalRequest extends GovernmentIdSequenceRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active && $this->user()?->role === 'resident';
    }

    public function rules(): array
    {
        // Reuse strict JSON object/key/value validation. Facts not retained are rejected.
        return Arr::only(parent::rules(), ['application_type', 'profile_selections', 'profile_selections.*']);
    }
}
