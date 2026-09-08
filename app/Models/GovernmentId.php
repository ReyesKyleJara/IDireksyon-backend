<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GovernmentId extends Model
{
    protected $fillable = [
        'name',
        'agency',
        'purpose',
        'validity',
        'description',
        'last_updated',
    ];

    protected $casts = [
        'last_updated' => 'datetime',
    ];
}