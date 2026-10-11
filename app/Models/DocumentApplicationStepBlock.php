<?php

namespace App\Models;

class DocumentApplicationStepBlock extends GovernmentIdApplicationStepBlock
{
    protected $table = 'document_application_step_blocks';

    public function step(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(DocumentApplicationStep::class, 'application_step_id');
    }
}
