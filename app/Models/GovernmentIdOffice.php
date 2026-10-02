<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class GovernmentIdOffice extends Pivot
{
    protected $table = 'government_id_office';

    public $incrementing = true;

    protected $fillable = [
        'government_id_id', 'office_id', 'new_application_status',
        'renewal_status', 'replacement_status', 'service_notes', 'source_url',
    ];

    protected $attributes = [
        'new_application_status' => 'unknown',
        'renewal_status' => 'unknown',
        'replacement_status' => 'unknown',
    ];

    protected function casts(): array
    {
        return ['last_verified_at' => 'datetime'];
    }

    public function governmentId(): BelongsTo
    {
        return $this->belongsTo(GovernmentId::class);
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function lastVerifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_verified_by');
    }
}
