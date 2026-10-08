<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GovernmentIdRequirementWay extends Model
{
    public const QUALIFICATIONS = [
        'none' => 'No extra requirement',
        'current_address' => 'Show current address',
        'unexpired' => 'Be valid / unexpired',
        'photo' => 'Contain photo',
        'signature' => 'Contain signature',
        'photo_signature' => 'Contain photo and signature',
        'custom' => 'Other / Custom',
    ];

    public const EDITABLE_FIELDS = [
        'required_count', 'qualification_type', 'qualification_scope', 'qualification_custom',
    ];

    protected $fillable = [...self::EDITABLE_FIELDS, 'sort_order'];
    protected $attributes = ['required_count' => 1, 'qualification_type' => 'none', 'qualification_scope' => 'every', 'sort_order' => 0];

    protected function casts(): array
    {
        return ['required_count' => 'integer', 'sort_order' => 'integer'];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(GovernmentIdRequirementGroup::class, 'requirement_group_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(GovernmentIdRequirementItem::class, 'requirement_way_id')->orderBy('sort_order')->orderBy('id');
    }
}
