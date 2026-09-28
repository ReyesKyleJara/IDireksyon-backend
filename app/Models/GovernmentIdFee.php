<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GovernmentIdFee extends Model
{
    protected $fillable = [
        'government_id_id',
        'label',
        'type',
        'amount_min',
        'amount_max',
        'currency',
        'is_optional',
        'notes',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'amount_min' => 'decimal:2',
            'amount_max' => 'decimal:2',
            'is_optional' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function governmentId(): BelongsTo
    {
        return $this->belongsTo(GovernmentId::class);
    }
}