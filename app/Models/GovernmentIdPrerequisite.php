<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GovernmentIdPrerequisite extends Model
{
    protected $table = 'catalog_government_id_prerequisites';

    protected $fillable = ['owner_id', 'type', 'government_id_id', 'document_id', 'custom_name', 'condition', 'sort_order'];

    protected function casts(): array
    {
        return ['owner_id' => 'integer', 'government_id_id' => 'integer', 'document_id' => 'integer', 'sort_order' => 'integer'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(GovernmentId::class, 'owner_id');
    }

    public function governmentId(): BelongsTo
    {
        return $this->belongsTo(GovernmentId::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function getDisplayNameAttribute(): string
    {
        return match ($this->type) {
            'government_id' => $this->governmentId?->name ?? '',
            'document' => $this->document?->name ?? '',
            default => $this->custom_name ?? '',
        };
    }
}
