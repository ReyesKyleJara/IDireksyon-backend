<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfficeHour extends Model
{
    protected $fillable = ['office_id', 'day_of_week', 'opens_at', 'closes_at', 'notes'];

    public function office()
    {
        return $this->belongsTo(Office::class);
    }
}
