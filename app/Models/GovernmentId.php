<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GovernmentId extends Model
{
    protected $fillable = [
        'name',
        'level',
        'category',
        'issued_by',
        'office_location',
        'description',
        'eligibility',
        'requirements',
        'fee',
        'processing_time',
        'validity',
    ];
}