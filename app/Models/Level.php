<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Level extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'status'];

    public function governmentIds()
    {
        return $this->hasMany(GovernmentId::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }
}
