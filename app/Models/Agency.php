<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agency extends Model
{
    protected $fillable = ['description', 'status', 'name', 'abbreviation'];

    public function offices(): HasMany
    {
        return $this->hasMany(Office::class);
    }

    public function governmentIds(): HasMany
    {
        return $this->hasMany(GovernmentId::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }
}
