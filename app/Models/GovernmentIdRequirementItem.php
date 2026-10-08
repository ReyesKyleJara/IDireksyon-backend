<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GovernmentIdRequirementItem extends Model
{
    public const TYPES = [
        'government_id' => 'Government ID',
        'document' => 'Document',
        'custom' => 'Other / Custom Requirement',
    ];

    public const FORMATS = [
        'not_specified' => 'Not specified',
        'original' => 'Original',
        'photocopy' => 'Photocopy',
        'original_photocopy' => 'Original + Photocopy',
        'certified_true_copy' => 'Certified True Copy',
        'digital_copy' => 'Digital Copy',
        'custom' => 'Other / Custom',
    ];

    // Digital details and unspecified quantities can be explained in instructions.
    public const COUNTABLE_FORMATS = ['original', 'photocopy', 'original_photocopy', 'certified_true_copy', 'custom'];

    public const EDITABLE_FIELDS = [
        'type', 'government_id_id', 'document_id', 'custom_name',
        'submission_format', 'submission_format_custom', 'copies', 'instructions', 'quantity',
    ];

    protected $fillable = [
        'type', 'government_id_id', 'document_id', 'custom_name',
        'submission_format', 'submission_format_custom', 'copies', 'instructions', 'quantity', 'sort_order',
    ];

    protected $attributes = ['submission_format' => 'not_specified', 'sort_order' => 0];

    protected function casts(): array
    {
        return ['government_id_id' => 'integer', 'document_id' => 'integer', 'quantity' => 'integer', 'copies' => 'integer', 'sort_order' => 'integer'];
    }

    public function way(): BelongsTo
    {
        return $this->belongsTo(GovernmentIdRequirementWay::class, 'requirement_way_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(GovernmentIdRequirementGroup::class, 'requirement_group_id');
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

    public function getSubmissionLabelAttribute(): string
    {
        return $this->submission_format === 'custom'
            ? ($this->submission_format_custom ?? '')
            : (self::FORMATS[$this->submission_format] ?? '');
    }
}
