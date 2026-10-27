<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentPrerequisite extends GovernmentIdPrerequisite
{
    protected $table = 'catalog_document_prerequisites';

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'owner_id');
    }
}
