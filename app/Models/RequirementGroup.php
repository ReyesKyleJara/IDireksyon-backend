<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequirementGroup extends Model
{
    public const MATCH_RULES = ['all' => 'All of these', 'any' => 'Any one of these', 'at_least' => 'At least N of these'];

    protected $fillable = ['name', 'match_rule', 'minimum_count', 'condition_notes', 'position'];

    protected $casts = ['minimum_count' => 'integer', 'position' => 'integer'];

    public function requirements()
    {
        return $this->hasMany(Requirement::class);
    }

    public function getRuleLabelAttribute(): string
    {
        return $this->match_rule === 'at_least' ? 'At least '.$this->minimum_count.' of these' : (self::MATCH_RULES[$this->match_rule] ?? 'Unrecognized rule');
    }
}
