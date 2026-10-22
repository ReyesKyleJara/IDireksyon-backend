<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

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

    protected static function booted(): void
    {
        static::saving(function (self $step) {
            // Document guides still use their existing schema in this step.
            if ($step instanceof DocumentApplicationStep) {
                return;
            }
            if ($step->requirement_set_id !== null) {
                $owner = GovernmentIdRequirementSet::query()->findOrFail($step->requirement_set_id)->government_id_id;
                if ($step->government_id_id !== null && (int) $step->government_id_id !== (int) $owner) {
                    throw ValidationException::withMessages(['application_guide_payload' => 'The profile belongs to another Government ID.']);
                }
                $step->government_id_id = $owner;
            }
            if ($step->government_id_id === null) {
                throw ValidationException::withMessages(['application_guide_payload' => 'A shared guide step must belong to a Government ID.']);
            }
        });
    }

    public function governmentId(): BelongsTo
    {
        return $this->belongsTo(GovernmentId::class);
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
