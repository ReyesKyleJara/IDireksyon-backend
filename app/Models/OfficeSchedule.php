<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OfficeSchedule extends Model
{
    protected $fillable = ['office_id', 'day_of_week', 'status'];

    protected $attributes = ['status' => 'unknown'];

    protected function casts(): array
    {
        return ['day_of_week' => 'integer'];
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function intervals(): HasMany
    {
        return $this->hasMany(OfficeScheduleInterval::class)->orderBy('opens_at');
    }
}
