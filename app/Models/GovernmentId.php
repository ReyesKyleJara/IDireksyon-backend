<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GovernmentId extends Model
{
    protected $fillable = [
        'name', 'is_published', 'requirements_reviewed',
        'level_id', 'record_type', 'availability_status',
        'agency_id',
        'category_id',
        'issuance_level',
        'inventory_key',
        'research_notes',
        'research_status',
        'agency',
        'purpose',
        'validity',
        'description',
        'last_updated',
        'application_steps',
        'cost_notes',
        'source_url',
        'source_checked_at',
    ];

    protected $casts = ['is_published' => 'boolean', 'requirements_reviewed' => 'boolean',
        'application_steps' => 'array', 'source_checked_at' => 'date', 'last_updated' => 'datetime',
    ];

    public function requirementGroups()
    {
        return $this->hasMany(RequirementGroup::class, 'government_id_id')->orderBy('position')->orderBy('id');
    }

    public function fees()
    {
        return $this->hasMany(CatalogFee::class, 'government_id_id')->orderBy('id');
    }

    public function scopeForDirectory($query)
    {
        return $query->where('is_published', true)->where('research_status', 'verified')
            ->where('availability_status', '!=', 'inactive')->whereNotNull('source_url')->where('source_url', '!=', '')->whereNotNull('source_checked_at');
    }

    public function level()
    {
        return $this->belongsTo(Level::class);
    }

    public function issuingAgency()
    {
        return $this->belongsTo(Agency::class, 'agency_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function getAgencyNameAttribute(): ?string
    {
        return $this->issuingAgency?->name ?? $this->agency;
    }

    public function offices()
    {
        return $this->belongsToMany(Office::class, 'office_government_id', 'government_id', 'office_id')->withTimestamps();
    }

    public function requirements()
    {
        return $this->belongsToMany(
            Requirement::class,
            'government_id_requirement',
            'government_id',
            'requirement_id'
        );
    }
}
