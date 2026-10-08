<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GovernmentIdRequirementGroup extends Model
{
    public const RULES = ['all' => 'All Required', 'choose_one' => 'Choose 1'];

    public const CONDITIONS = [
        'always' => 'Always applies',
        'spouse_surname' => 'Married applicant using spouse’s surname',
        'dual_citizenship' => 'Dual citizen / reacquired Philippine citizenship',
        'name_discrepancy' => 'Applicant has a name discrepancy',
        'below_18' => 'Applicant is below 18',
        'non_apparent_disability' => 'Disability is non-apparent',
        'representative' => 'Representative / guardian is applying',
        'custom' => 'Other / Custom situation',
    ];

    protected $fillable = ['title', 'rule', 'condition_type', 'condition_custom', 'sort_order'];

    protected $attributes = ['condition_type' => 'always', 'sort_order' => 0];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public function requirementSet(): BelongsTo
    {
        return $this->belongsTo(GovernmentIdRequirementSet::class, 'requirement_set_id');
    }

    public function ways(): HasMany
    {
        return $this->hasMany(GovernmentIdRequirementWay::class, 'requirement_group_id')->orderBy('sort_order')->orderBy('id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(GovernmentIdRequirementItem::class, 'requirement_group_id')
            ->orderBy('sort_order')->orderBy('id');
    }

    public function getConditionLabelAttribute(): string
    {
        return $this->condition_type === 'custom'
            ? ($this->condition_custom ?? '')
            : (self::CONDITIONS[$this->condition_type] ?? '');
    }
}
