<?php

namespace App\Models\Concerns;

use App\Models\User;
use App\Support\ResidentVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** IDs and documents: left out of every query while a resident-facing request runs. */
trait HidesFromResidents
{
    public static function bootHidesFromResidents(): void
    {
        static::addGlobalScope(ResidentVisibility::SCOPE, function (Builder $query) {
            if (ResidentVisibility::active()) {
                $query->whereNull($query->getModel()->qualifyColumn('hidden_at'));
            }
        });
    }

    public function isHidden(): bool
    {
        return $this->hidden_at !== null;
    }

    /** Who hid the entry (CMS only; never sent to residents). */
    public function hiddenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hidden_by');
    }
}
