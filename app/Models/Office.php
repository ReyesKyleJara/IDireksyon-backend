<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Office extends Model
{
    protected $fillable = [
        'name', 'agency_id', 'address', 'municipality', 'province',
        'latitude', 'longitude', 'status', 'source_url', 'notes',
    ];

    protected $attributes = ['status' => 'draft'];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'last_verified_at' => 'datetime',
            'location_checked_at' => 'datetime',
        ];
    }

    /**
     * Whether residents get a map pin and directions for this office.
     * The Flutter app applies the same rule in OfficeModel.
     */
    public function hasUsableLocation(): bool
    {
        if (! is_numeric($this->latitude) || ! is_numeric($this->longitude)) {
            return false;
        }

        $latitude = (float) $this->latitude;
        $longitude = (float) $this->longitude;

        // 0, 0 is what an unset value looks like, and no office is there.
        return abs($latitude) <= 90 && abs($longitude) <= 180
            && ($latitude != 0.0 || $longitude != 0.0);
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function lastVerifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_verified_by');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(OfficeSchedule::class)->orderBy('day_of_week');
    }

    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(Document::class, 'document_office')
            ->using(DocumentOffice::class);
    }

    public function governmentIds(): BelongsToMany
    {
        return $this->belongsToMany(GovernmentId::class, 'government_id_office')
            ->using(GovernmentIdOffice::class)
            ->withPivot([
                'id', 'new_application_status', 'renewal_status',
                'replacement_status', 'service_notes', 'source_url',
                'last_verified_at', 'last_verified_by',
            ])
            ->withTimestamps();
    }
}
