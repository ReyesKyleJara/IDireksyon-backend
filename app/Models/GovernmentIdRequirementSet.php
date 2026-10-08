<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class GovernmentIdRequirementSet extends Model
{
    public const APPLICATION_TYPES = [
        'new' => 'First-Time Application',
        'renewal' => 'Renewal',
        'replacement' => 'Replacement',
        'custom' => 'Other / Custom',
    ];

    public const APPLICANT_TYPES = [
        'all' => 'All Applicants',
        'adult' => 'Adult',
        'minor' => 'Minor',
        'custom' => 'Other / Custom',
    ];

    protected $fillable = [
        'government_id_id', 'application_type', 'application_type_custom',
        'applicant_type', 'applicant_type_custom', 'min_age', 'max_age',
    ];

    protected function casts(): array
    {
        return ['min_age' => 'integer', 'max_age' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $set) {
            foreach (['application_type_custom', 'applicant_type_custom'] as $field) {
                if (is_string($set->$field)) {
                    $set->$field = trim($set->$field) === '' ? null : trim($set->$field);
                }
            }

            if ($set->application_type !== 'custom') {
                $set->application_type_custom = null;
            }

            if ($set->applicant_type !== 'custom') {
                $set->applicant_type_custom = null;
                [$set->min_age, $set->max_age] = match ($set->applicant_type) {
                    'adult' => [18, null],
                    'minor' => [null, 17],
                    default => [null, null],
                };
            }

            // Validate raw attributes so integer casts cannot hide fractional input.
            $data = $set->getAttributes();
            $rules = [
                'application_type' => ['required', Rule::in(array_keys(self::APPLICATION_TYPES))],
                'application_type_custom' => ['required_if:application_type,custom', 'nullable', 'string', 'max:255'],
                'applicant_type' => ['required', Rule::in(array_keys(self::APPLICANT_TYPES))],
                'applicant_type_custom' => ['nullable', 'string', 'max:255'],
                'min_age' => ['nullable', 'integer', 'min:0', 'max:65535'],
                'max_age' => ['nullable', 'integer', 'min:0', 'max:65535'],
            ];

            if (isset($data['min_age'], $data['max_age'])) {
                $rules['max_age'][] = 'gte:min_age';
            }

            Validator::make($data, $rules)->validate();
        });
    }

    public function applicationSteps(): HasMany
    {
        return $this->hasMany(GovernmentIdApplicationStep::class, 'requirement_set_id')
            ->orderBy('sort_order')->orderBy('id');
    }

    public function groups(): HasMany
    {
        return $this->hasMany(GovernmentIdRequirementGroup::class, 'requirement_set_id')
            ->orderBy('sort_order')->orderBy('id');
    }

    public function governmentId(): BelongsTo
    {
        return $this->belongsTo(GovernmentId::class);
    }

    public function getDisplayLabelAttribute(): string
    {
        $applicant = self::APPLICANT_TYPES[$this->applicant_type] ?? '';
        if ($this->applicant_type === 'custom') {
            $age = match (true) {
                $this->min_age !== null && $this->max_age !== null => "Ages {$this->min_age}–{$this->max_age}",
                $this->min_age !== null => "Age {$this->min_age} and above",
                $this->max_age !== null => "Age {$this->max_age} and below",
                default => 'No age restriction',
            };
            $applicant = filled($this->applicant_type_custom)
                ? "{$this->applicant_type_custom} ({$age})"
                : $age;
        }

        $application = $this->application_type === 'custom'
            ? $this->application_type_custom
            : (self::APPLICATION_TYPES[$this->application_type] ?? '');

        return "{$applicant} • {$application}";
    }
}
