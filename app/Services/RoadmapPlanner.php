<?php

namespace App\Services;

use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RoadmapPlanner
{
    public function build(User $resident, array $targets): array
    {
        return DB::transaction(function () use ($resident, $targets) {
            $ownedIds = $resident->ownedGovernmentIds()->pluck('government_ids.id')->all();
            $ownedDocuments = $resident->ownedDocuments()->pluck('documents.id')->all();
            $status = app(PrerequisiteStatus::class);
            $catalog = [];
            foreach (['government_id' => GovernmentId::class, 'document' => Document::class] as $type => $model) {
                foreach ($model::with(['prerequisites.governmentId', 'prerequisites.document'])->orderBy('id')->get() as $record) {
                    $catalog[$type.':'.$record->id] = [
                        'type' => $type, 'id' => $record->id, 'name' => $record->name,
                        'application_availability' => $record->application_availability,
                        'prerequisites' => $status->evaluate(CatalogPrerequisites::present($record), $ownedIds, $ownedDocuments),
                    ];
                }
            }
            $states = $nodes = $ordered = [];
            $selected = array_fill_keys(array_map(fn ($id) => 'government_id:'.$id, $targets), true);
            $visit = function (string $key, int $depth = 0) use (&$visit, &$states, &$nodes, &$ordered, $catalog, $selected, $status): bool {
                if (($states[$key] ?? null) === 'done') return $nodes[$key]['blocked'];
                if (($states[$key] ?? null) === 'visiting') {
                    $nodes[$key]['blocked'] = true;
                    $nodes[$key]['issues'][] = 'Circular prerequisite detected. Contact the administrator.';
                    return true;
                }
                $states[$key] = 'visiting';
                $record = $catalog[$key] ?? ['type' => explode(':', $key)[0], 'id' => (int) explode(':', $key)[1],
                    'name' => 'Unavailable catalog record', 'application_availability' => null, 'prerequisites' => []];
                $issues = [];
                if (!isset($catalog[$key])) $issues[] = 'A linked catalog record is missing. Contact the administrator.';
                elseif (!in_array($record['application_availability'], ['available', 'limited_conditional'], true)) {
                    $issues[] = 'This item is currently unavailable for acquisition.';
                }
                if ($depth >= 256) $issues[] = 'The prerequisite chain could not be verified. Contact the administrator.';
                $nodes[$key] = [...$record, 'selected' => isset($selected[$key]), 'blocked' => (bool) $issues, 'issues' => $issues];
                foreach ($record['prerequisites'] as $row) {
                    if ($row['status'] !== 'missing' || $depth >= 256) continue;
                    $dependency = $row['type'].':'.$row['id'];
                    if ($visit($dependency, $depth + 1)) {
                        $nodes[$key]['blocked'] = true;
                        $nodes[$key]['issues'][] = 'Prerequisite path blocked: '.$row['name'];
                    }
                }
                $nodes[$key]['prerequisite_readiness'] = $status->readiness($record['prerequisites']);
                $states[$key] = 'done';
                $ordered[] = $key;
                return $nodes[$key]['blocked'];
            };
            foreach ($targets as $id) $visit('government_id:'.$id);
            $steps = array_map(fn ($key) => $nodes[$key], $ordered);
            return [
                'target_government_id_ids' => array_values($targets),
                'blocked' => (bool) array_filter($steps, fn ($step) => $step['blocked']),
                'steps' => $steps,
            ];
        });
    }
}
