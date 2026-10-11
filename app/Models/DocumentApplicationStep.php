<?php

namespace App\Models;

class DocumentApplicationStep extends GovernmentIdApplicationStep
{
    protected $table = 'document_application_steps';

    public function requirementSet(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(DocumentRequirementSet::class, 'requirement_set_id');
    }

    public function blocks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(DocumentApplicationStepBlock::class, 'application_step_id')->orderBy('sort_order')->orderBy('id');
    }
}
