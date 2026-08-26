<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GovernmentID extends Model
{
    use HasFactory;

    protected $table = 'government_ids';

    protected $fillable = [
        'government_office_id',
        'name',
        'code',
        'description',
    ];

    public function governmentOffice(): BelongsTo
    {
        return $this->belongsTo(GovernmentOffice::class);
    }
}
