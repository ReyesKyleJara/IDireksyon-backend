<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Requirement extends Model
{
    protected $fillable = [
        'name', 'requirement_group_id', 'is_dependency',
        'description',
        'type',
        'referenced_government_id_id',
        'referenced_document_id',
    ];

    protected $casts = ['is_dependency' => 'boolean'];

    public function group()
    {
        return $this->belongsTo(RequirementGroup::class, 'requirement_group_id');
    }

    public function governmentIds()
    {
        return $this->belongsToMany(GovernmentId::class, 'government_id_requirement', 'requirement_id', 'government_id');
    }

    public function documents()
    {
        return $this->belongsToMany(Document::class, 'document_requirement', 'requirement_id', 'document_id');
    }

    public function getDisplayNameAttribute(): string
    {
        return match ($this->type) {
            'government_id' => $this->referencedGovernmentId?->name ?? 'Referenced ID no longer available',
            'document' => $this->referencedDocument?->name ?? 'Referenced document no longer available',
            default => $this->name,
        };
    }

    public function referencedGovernmentId()
    {
        return $this->belongsTo(
            GovernmentId::class,
            'referenced_government_id_id'
        );
    }

    public function referencedDocument()
    {
        return $this->belongsTo(
            Document::class,
            'referenced_document_id'
        );
    }
}
