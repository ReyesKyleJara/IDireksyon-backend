<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = ['description', 'status', 'parent_category_id', 'name', 'slug'];

    public function parentCategory()
    {
        return $this->belongsTo(Category::class, 'parent_category_id');
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_category_id');
    }

    public function getDisplayNameAttribute(): string
    {
        return ($this->parentCategory ? $this->parentCategory->name.' / ' : '').$this->name;
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
