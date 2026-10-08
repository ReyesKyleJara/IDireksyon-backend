<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GovernmentIdApplicationStepBlock extends Model
{
    public const TYPES = [
        'instructions' => 'Instructions',
        'checklist' => 'Checklist / Things to Prepare',
        'reminder' => 'Important Reminder / Note',
        'official_link' => 'Official Link',
        'requirements' => 'Use Existing Requirements',
        'fees' => 'Use Existing Fees',
        'offices' => 'Use Linked Offices',
        'processing_time' => 'Use Processing Time',
        'custom' => 'Other / Custom Information',
    ];

    protected $fillable = ['type', 'section_title', 'content', 'sort_order'];
    protected $attributes = ['sort_order' => 0];

    protected function casts(): array
    {
        return ['content' => 'array', 'sort_order' => 'integer'];
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(GovernmentIdApplicationStep::class, 'application_step_id');
    }
}
