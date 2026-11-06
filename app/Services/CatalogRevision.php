<?php

namespace App\Services;

use App\Models\Document;
use App\Models\GovernmentId;

/** A content fingerprint catches same-second and nested edits, without changing research timestamps. */
class CatalogRevision
{
    public static function token(Document|GovernmentId $record): string
    {
        $current = $record->fresh()->load([
            'requirementSets.groups.items', 'requirementSets.groups.ways',
            'requirementSets.applicationSteps.blocks', 'fees.options', 'offices', 'prerequisites',
        ]);
        return hash('sha256', json_encode($current->toArray(), JSON_THROW_ON_ERROR));
    }
}
