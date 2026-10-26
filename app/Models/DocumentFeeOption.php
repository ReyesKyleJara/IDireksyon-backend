<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentFeeOption extends Model
{
    protected $fillable = ['label', 'type', 'amount_min', 'amount_max', 'currency', 'description', 'notes', 'sort_order'];

    protected function casts(): array
    {
        return ['amount_min' => 'decimal:2', 'amount_max' => 'decimal:2', 'sort_order' => 'integer'];
    }

    public function fee(): BelongsTo
    {
        return $this->belongsTo(DocumentFee::class, 'document_fee_id');
    }

    public function getDisplayAmountAttribute(): string
    {
        return GovernmentIdFee::amountLabel($this);
    }
}
