<?php

namespace App\Models;

class DocumentRequirementSet extends GovernmentIdRequirementSet
{
    protected $table = 'document_requirement_sets';

    public function __construct(array $attributes = [])
    {
        $this->fillable = array_values(array_diff($this->fillable, ['government_id_id']));
        $this->fillable[] = 'document_id';
        parent::__construct($attributes);
    }

    public function document(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function applicationSteps(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(DocumentApplicationStep::class, 'requirement_set_id')->orderBy('sort_order')->orderBy('id');
    }

    public function groups(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(DocumentRequirementGroup::class, 'requirement_set_id')->orderBy('sort_order')->orderBy('id');
    }
}
