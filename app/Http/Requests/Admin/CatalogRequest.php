<?php

namespace App\Http\Requests\Admin;

use App\Support\CatalogOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessCms() ?? false;
    }

    public function attributes(): array
    {
        return [
            'agency' => 'issuing agency / office',
            'source_url' => 'official source URL',
            'source_checked_at' => 'source checked date',
            'application_steps.*.title' => 'step title',
            'application_steps.*.details' => 'step instructions',
        ];
    }

    public function rules(): array
    {
        return [
            'is_published' => ['sometimes', 'boolean'],
            'requirements_reviewed' => ['sometimes', 'boolean'],
            'name' => ['required', 'string', 'max:255'],
            'agency' => [$this->routeIs('admin.documents.*') ? 'nullable' : 'required_without:agency_id', 'nullable', 'string', 'max:255'],
            'level_id' => ['nullable', 'integer', 'exists:levels,id'],
            'record_type' => ['sometimes', 'required', Rule::in(array_keys(CatalogOptions::RECORD_TYPES))],
            'availability_status' => ['sometimes', 'required', Rule::in(array_keys(CatalogOptions::AVAILABILITY))],
            'agency_id' => ['nullable', 'integer', 'exists:agencies,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'issuance_level' => ['nullable', Rule::in(array_keys(CatalogOptions::LEVELS))],
            'research_status' => ['sometimes', 'required', Rule::in(array_keys(CatalogOptions::RESEARCH_STAGES))],
            'research_notes' => ['nullable', 'string', 'max:10000'],
            'office_ids' => ['sometimes', 'array', 'max:100'],
            'office_ids.*' => ['nullable', 'integer', 'distinct', 'exists:offices,id'],
            'purpose' => ['nullable', 'string', 'max:5000'],
            'validity' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'cost_notes' => ['nullable', 'string', 'max:5000'],
            'source_url' => ['nullable', 'required_with:source_checked_at', 'required_if:research_status,verified', 'required_if:availability_status,available', 'url:http,https', 'max:2048'],
            'source_checked_at' => ['nullable', 'required_if:research_status,verified', 'required_if:availability_status,available', 'date_format:Y-m-d', 'before_or_equal:today'],
            'application_steps' => ['nullable', 'array', 'max:30'],
            'application_steps.*' => ['array:title,details'],
            'application_steps.*.title' => ['required', 'string', 'max:255'],
            'application_steps.*.details' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
