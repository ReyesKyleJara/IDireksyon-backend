<?php

namespace App\Models;

class DocumentRequirementItem extends GovernmentIdRequirementItem
{
    protected $table = 'document_requirement_items';

    public function group(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(DocumentRequirementGroup::class, 'requirement_group_id');
    }

    public function way(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(DocumentRequirementWay::class, 'requirement_way_id');
    }
}
