<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfficeScheduleInterval extends Model
{
    // Times remain local clock strings, without a date or timezone conversion.
    protected $fillable = ['office_schedule_id', 'opens_at', 'closes_at'];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(OfficeSchedule::class, 'office_schedule_id');
    }
}
