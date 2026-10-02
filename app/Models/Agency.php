<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agency extends Model
{
    protected $fillable = [
        'name',
        'acronym',
        'official_website',
    ];

    public function governmentIds(): HasMany
    {
        return $this->hasMany(GovernmentId::class);
    }

    public function offices(): HasMany
    {
        return $this->hasMany(Office::class);
    }
}