<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Office extends Model
{
    protected $fillable = [
        'municipality', 'province', 'phone', 'email', 'notes', 'source_url', 'source_checked_at', 'status',
        'name',
        'agency_id',
        'barangay_id',
        'address',
        'latitude',
        'longitude',
    ];

    protected $casts = ['source_checked_at' => 'date',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    public function hours()
    {
        return $this->hasMany(OfficeHour::class)->orderBy('day_of_week')->orderBy('opens_at');
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class);
    }

    public function governmentIds(): BelongsToMany
    {
        return $this->belongsToMany(GovernmentId::class, 'office_government_id', 'office_id', 'government_id')->withTimestamps();
    }

    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(Document::class, 'office_document')->withTimestamps();
    }
}
