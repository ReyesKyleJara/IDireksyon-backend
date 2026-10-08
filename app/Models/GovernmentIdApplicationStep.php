<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GovernmentIdApplicationStep extends Model
{
    public const TYPES = [
        'general' => 'General / Instructions',
        'online_action' => 'Online Action',
        'form_submission' => 'Form / Submission',
        'payment' => 'Payment',
        'office_visit' => 'Office Visit',
        'in_person' => 'In-Person Process',
        'processing' => 'Waiting / Processing',
        'release' => 'Release / Claiming',
        'custom' => 'Other / Custom',
    ];

    protected $fillable = ['title', 'short_description', 'type', 'sort_order'];
    protected $attributes = ['type' => 'general', 'sort_order' => 0];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public function requirementSet(): BelongsTo
    {
        return $this->belongsTo(GovernmentIdRequirementSet::class, 'requirement_set_id');
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(GovernmentIdApplicationStepBlock::class, 'application_step_id')
            ->orderBy('sort_order')->orderBy('id');
    }
}
