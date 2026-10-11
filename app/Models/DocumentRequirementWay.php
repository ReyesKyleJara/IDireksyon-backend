<?php

namespace App\Models;

class DocumentRequirementWay extends GovernmentIdRequirementWay
{
    protected $table = 'document_requirement_ways';

    public function group(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(DocumentRequirementGroup::class, 'requirement_group_id');
    }

    public function items(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(DocumentRequirementItem::class, 'requirement_way_id')->orderBy('sort_order')->orderBy('id');
    }
}
