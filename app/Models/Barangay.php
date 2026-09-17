<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Barangay extends Model
{
    protected $fillable = ['description', 'status', 'name', 'municipality', 'province'];

    public function offices(): HasMany
    {
        return $this->hasMany(Office::class);
    }
}
