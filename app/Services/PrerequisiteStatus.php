<?php

namespace App\Services;

use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\User;

class PrerequisiteStatus
{
    /** Current ownership only: conditions remain descriptive, never evaluated. */
    public function forResident(GovernmentId|Document $owner, User $resident): array
    {
        // Query relationships each time instead of using a stale loaded Inventory.
        $ids = array_fill_keys($resident->ownedGovernmentIds()->pluck('government_ids.id')->all(), true);
        $documents = array_fill_keys($resident->ownedDocuments()->pluck('documents.id')->all(), true);
        $owner->load(['prerequisites.governmentId', 'prerequisites.document']);

        return array_map(fn (array $row) => [
            ...$row,
            'status' => match ($row['type']) {
                'government_id' => isset($ids[$row['id']]) ? 'satisfied' : 'missing',
                'document' => isset($documents[$row['id']]) ? 'satisfied' : 'missing',
                default => 'manual',
            },
        ], CatalogPrerequisites::present($owner));
    }
}
