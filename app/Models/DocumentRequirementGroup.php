<?php

namespace App\Models;

class DocumentRequirementGroup extends GovernmentIdRequirementGroup
{
    protected $table = 'document_requirement_groups';

    public function requirementSet(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(DocumentRequirementSet::class, 'requirement_set_id');
    }

    public function ways(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(DocumentRequirementWay::class, 'requirement_group_id')->orderBy('sort_order')->orderBy('id');
    }

    public function items(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(DocumentRequirementItem::class, 'requirement_group_id')->orderBy('sort_order')->orderBy('id');
    }
}
