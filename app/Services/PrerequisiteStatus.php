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
        $ids = $resident->ownedGovernmentIds()->pluck('government_ids.id')->all();
        $documents = $resident->ownedDocuments()->pluck('documents.id')->all();
        $owner->load(['prerequisites.governmentId', 'prerequisites.document']);

        return $this->evaluate(CatalogPrerequisites::present($owner), $ids, $documents);
    }

    public function evaluate(array $rows, array $governmentIds, array $documentIds): array
    {
        $ids = array_fill_keys($governmentIds, true);
        $documents = array_fill_keys($documentIds, true);
        return array_map(fn (array $row) => [
            ...$row,
            'status' => match ($row['type']) {
                'government_id' => isset($ids[$row['id']]) ? 'satisfied' : 'missing',
                'document' => isset($documents[$row['id']]) ? 'satisfied' : 'missing',
                default => 'manual',
            },
        ], $rows);
    }

    public function readiness(array $rows): array
    {
        $automatic = array_filter($rows, fn ($row) => $row['status'] !== 'manual');
        $satisfied = count(array_filter($automatic, fn ($row) => $row['status'] === 'satisfied'));
        return [
            'satisfied' => $satisfied,
            'total' => count($automatic),
            'percentage' => $automatic ? (int) round(100 * $satisfied / count($automatic)) : null,
            'manual' => count($rows) - count($automatic),
        ];
    }
}
