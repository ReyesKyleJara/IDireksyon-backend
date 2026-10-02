<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class OfficeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessCms() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'agency_id' => ['nullable', 'integer', 'exists:agencies,id'],
            'address' => ['nullable', 'string', 'max:5000'],
            'municipality' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'required', 'in:draft,inactive'],
            'source_url' => ['nullable', 'url:http,https', 'max:2048'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'schedules' => ['sometimes', 'array:0,1,2,3,4,5,6', 'max:7'],
            'schedules.*' => ['array:status,intervals'],
            'schedules.*.status' => ['required', 'in:unknown,open,closed'],
            'schedules.*.intervals' => ['nullable', 'array', 'max:4'],
            'schedules.*.intervals.*' => ['array:opens_at,closes_at'],
            'schedules.*.intervals.*.opens_at' => ['required', 'date_format:H:i'],
            'schedules.*.intervals.*.closes_at' => ['required', 'date_format:H:i'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            foreach ($this->input('schedules', []) as $day => $schedule) {
                $intervals = $schedule['intervals'] ?? [];
                if ($schedule['status'] !== 'open') {
                    if ($intervals !== []) {
                        $validator->errors()->add("schedules.$day.intervals", 'Only open days can have opening hours.');
                    }
                    continue;
                }
                if ($intervals === []) {
                    $validator->errors()->add("schedules.$day.intervals", 'Add at least one opening interval for an open day.');
                    continue;
                }

                $ordered = collect($intervals)->sortBy('opens_at');
                $previousClose = null;
                foreach ($ordered as $index => $interval) {
                    if ($interval['closes_at'] <= $interval['opens_at']) {
                        $validator->errors()->add("schedules.$day.intervals.$index.closes_at", 'Closing time must be after opening time on the same day.');
                    }
                    if ($previousClose !== null && $interval['opens_at'] < $previousClose) {
                        $validator->errors()->add("schedules.$day.intervals.$index.opens_at", 'Opening intervals must not overlap.');
                    }
                    $previousClose = max($previousClose ?? '', $interval['closes_at']);
                }
            }
        });
    }
}
