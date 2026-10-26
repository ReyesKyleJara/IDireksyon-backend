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
    public function options(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(GovernmentIdFeeOption::class)->orderBy('sort_order')->orderBy('id');
    }

    public static function amountLabel($fee): string
    {
        $prefix = $fee->currency === 'PHP' ? '₱' : $fee->currency.' ';
        return match ($fee->type) {
            'fixed' => $prefix.number_format((float) $fee->amount_min, 2),
            'range' => $prefix.number_format((float) $fee->amount_min, 2).'–'.$prefix.number_format((float) $fee->amount_max, 2),
            'free' => 'Free',
            'options' => 'Choose one',
            default => 'Varies',
        };
    }

    public function getDisplayAmountAttribute(): string
    {
        return self::amountLabel($this);
    }
}