<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GovernmentId extends Model
{
    protected $fillable = [
        'name',
        'level',
        'category',
        'agency_id',

        'office_location',
        'office_hours',

        'description',
        'purpose',
        'eligibility',
        'eligibility_age_type',
        'eligibility_min_age',
        'eligibility_max_age',
        'eligibility_citizenship',
        'eligibility_residency',
        'eligibility_residency_custom',
        'eligibility_other_conditions',

        'requirements',
        'prerequisite_notes',

        'fee',
        'fee_type',
        'fee_min',
        'fee_max',
        'fee_currency',
        'fee_notes',

        'application_process',

        'processing_time',
        'processing_time_type',
        'processing_time_min',
        'processing_time_max',
        'processing_time_unit',

        'renewal_process',
        'replacement_process',

        'validity',
        'validity_type',
        'validity_value',
        'validity_unit',

        'official_link',
        'official_sources',
    ];

    protected function casts(): array
    {
        return [
            'last_verified_at' => 'datetime',
            'eligibility_min_age' => 'integer',
            'eligibility_max_age' => 'integer',
            'validity_value' => 'integer',
            'processing_time_min' => 'integer',
            'processing_time_max' => 'integer',
            'fee_min' => 'decimal:2',
            'fee_max' => 'decimal:2',
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function lastVerifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_verified_by');
    }

    public function fees(): HasMany
{
    return $this->hasMany(GovernmentIdFee::class);
}
}