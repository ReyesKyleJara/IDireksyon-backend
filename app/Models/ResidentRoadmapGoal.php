<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResidentRoadmapGoal extends Model
{
    protected $fillable = ['user_id', 'government_id_id', 'application_type', 'profile_selections'];

    protected function casts(): array
    {
        return ['profile_selections' => 'array', 'user_id' => 'integer', 'government_id_id' => 'integer'];
    }

    public function governmentId(): BelongsTo
    {
        return $this->belongsTo(GovernmentId::class);
    }
}
