<?php

namespace App\Models;

class DocumentFee extends GovernmentIdFee
{
    protected $table = 'document_fees';

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
    public function options(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(DocumentFeeOption::class)->orderBy('sort_order')->orderBy('id');
    }
}
