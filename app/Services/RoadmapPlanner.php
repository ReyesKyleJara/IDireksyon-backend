<?php

namespace App\Services;

use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\User;
use App\Support\ProcessingTime;
use App\Support\ResidentRequirements;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RoadmapPlanner
{
    public function build(User $resident, array $targets, array $documentTargets = [], ?array $resolution = null): array
    {
        return DB::transaction(function () use ($resident, $targets, $documentTargets, $resolution) {
            $keys = [...array_map(fn ($id) => 'government_id:'.$id, $targets), ...array_map(fn ($id) => 'document:'.$id, $documentTargets)];
            sort($keys);
            $journeyKey = hash('sha256', json_encode($keys));
            // The account lock serializes independent option clicks for this resident.
            if ($resolution !== null) {
                User::whereKey($resident->id)->lockForUpdate()->firstOrFail();
            }
            $saved = DB::table('resident_journey_resolutions')->where('user_id', $resident->id)->where('journey_key', $journeyKey)->value('selections');
            $selections = $saved ? json_decode($saved, true, 512, JSON_THROW_ON_ERROR) : ['paths' => [], 'groups' => []];
            $plan = $this->compose($resident, $targets, $documentTargets, $selections);
            if ($resolution === null) {
                return $plan;
            }

            $step = collect($plan['steps'])->first(fn ($step) => ($step['resolution_key'] ?? null) === $resolution['key']);
            $option = collect($step['suggestions'] ?? [])->first(function ($option) use ($resolution, $step) {
                return ($step['kind'] ?? null) === 'path'
                    ? ($option['set_id'] === ($resolution['set_id'] ?? null))
                    : ($option['type'] === ($resolution['type'] ?? null) && $option['id'] === ($resolution['id'] ?? null) && $option['way_id'] === ($resolution['way_id'] ?? null));
            });
            if (! $step || ! $option) {
                throw ValidationException::withMessages(['resolution' => 'This option is no longer available for this journey. Reload the suggested options.']);
            }
            if ($step['kind'] === 'path') {
                $selections['paths'][$step['owner_key']] = $option['set_id'];
            } else {
                $previous = $selections['groups'][$resolution['key']] ?? [];
                $items = ($previous['way_id'] ?? null) === $option['way_id'] ? ($previous['items'] ?? []) : [];
                $items[] = $option['type'].':'.$option['id'];
                $selections['groups'][$resolution['key']] = ['way_id' => $option['way_id'], 'items' => array_values(array_unique($items))];
            }
            $plan = $this->compose($resident, $targets, $documentTargets, $selections);
            DB::table('resident_journey_resolutions')->upsert([[
                'user_id' => $resident->id, 'journey_key' => $journeyKey, 'selections' => json_encode($selections, JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ]], ['user_id', 'journey_key'], ['selections', 'updated_at']);

            return $plan;
        });
    }

    private function compose(User $resident, array $targets, array $documentTargets, array $selections): array
    {
        $ownedIds = $resident->ownedGovernmentIds()
            ->pluck('government_ids.id')
            ->all();

        $ownedDocuments = $resident->ownedDocuments()
            ->pluck('documents.id')
            ->all();

        $status = app(PrerequisiteStatus::class);

        $catalog = [];

        foreach (
            [
                'government_id' => GovernmentId::class,
                'document' => Document::class,
            ] as $type => $model
        ) {
            foreach (
                $model::with([
                    'prerequisites.governmentId',
                    'prerequisites.document',
                    'requirementSets.groups.ways.items.governmentId',
                    'requirementSets.groups.ways.items.document',
                    'requirementSets.groups.items.governmentId',
                    'requirementSets.groups.items.document',
                ])
                    ->orderBy('id')
                    ->get() as $record
            ) {
                $catalog[$type.':'.$record->id] = [
                    'type' => $type,
                    'id' => $record->id,
                    'name' => $record->name,
                    'processing_time' => $record->processing_time,
                    'application_availability' => $record->application_availability,
                    'guidance_mode' => $record->guidance_mode,
                    'include_in_smart_sequencing' => $record->include_in_smart_sequencing,
                    'requirement_sets' => array_map(fn ($set) => [
                        ...$set,
                        'path_label' => $record->requirementSets->firstWhere('id', $set['id'])?->request_channel['label'] ?? null,
                    ], ResidentRequirements::forId($record)),
                    'prerequisites' => $status->evaluate(
                        CatalogPrerequisites::present($record),
                        $ownedIds,
                        $ownedDocuments
                    ),
                ];
            }
        }

        $owned = array_fill_keys([
            ...array_map(fn ($id) => 'government_id:'.$id, $ownedIds),
            ...array_map(fn ($id) => 'document:'.$id, $ownedDocuments),
        ], true);
        $age = null;
        if ($resident->date_of_birth) {
            try {
                $dob = CarbonImmutable::parse($resident->date_of_birth);
                if ($dob->isPast()) {
                    $age = (int) $dob->diffInYears(now());
                }
            } catch (\Throwable) { /* Unknown applicant age remains review-only. */
            }
        }
        $requirements = app(JourneyRequirements::class);
        foreach ($catalog as $key => &$record) {
            $hasStructuredRequirements = (bool) $record['requirement_sets'];
            $resolved = $requirements->resolve($key, $record['requirement_sets'], $owned, $age, $selections, $catalog);
            unset($record['requirement_sets']);
            // Structured Requirements are authoritative; legacy explicit prerequisites
            // remain the fallback for records without a structured checklist.
            if ($hasStructuredRequirements) {
                // Preserve legacy descriptive notes on the same linked item.
                // Applicability/status still comes from the structured group.
                foreach ($resolved['rows'] as &$row) {
                    if (! in_array($row['type'], ['government_id', 'document'], true)) {
                        continue;
                    }
                    $legacy = collect($record['prerequisites'])->first(fn ($old) => $old['type'] === $row['type'] && $old['id'] === $row['id'] && filled($old['condition'] ?? null));
                    if ($legacy) {
                        $row['condition'] = $legacy['condition'];
                    }
                }
                unset($row);
                $record['prerequisites'] = $resolved['rows'];
            } else {
                foreach ($record['prerequisites'] as &$row) {
                    if (filled($row['condition'] ?? null)) {
                        $row['status'] = 'manual';
                    }
                }
                unset($row);
            }
            $record['pending'] = $resolved['pending'];
        }
        unset($record);

        // Presentation of a linked item is separate from exact Inventory ownership.
        foreach ($catalog as &$record) {
            foreach ($record['prerequisites'] as &$row) {
                if (! in_array($row['type'], ['government_id', 'document'], true)) {
                    continue;
                }

                $linked = $catalog[$row['type'].':'.$row['id']] ?? null;

                if (
                    ($linked['guidance_mode'] ?? 'guided')
                    === 'informational_only'
                ) {
                    $row['resolution'] = 'reference_only';
                    $row['message'] =
                        'This prerequisite must be obtained through its issuing institution or professional process.';
                }
            }

            unset($row);
        }

        unset($record);

        $states = [];
        $nodes = [];
        $ordered = [];

        $selected = array_fill_keys(
            [
                ...array_map(fn ($id) => 'government_id:'.$id, $targets),
                ...array_map(fn ($id) => 'document:'.$id, $documentTargets),
            ],
            true
        );

        $visit = function (
            string $key,
            int $depth = 0
        ) use (
            &$visit,
            &$states,
            &$nodes,
            &$ordered,
            $catalog,
            $selected,
            $owned,
            $status
        ): bool {
            if (($states[$key] ?? null) === 'done') {
                return $nodes[$key]['blocked'];
            }

            if (($states[$key] ?? null) === 'visiting') {
                $nodes[$key]['blocked'] = true;
                $nodes[$key]['issues'][] =
                    'Circular prerequisite detected. Contact the administrator.';

                return true;
            }

            $states[$key] = 'visiting';

            $record = $catalog[$key] ?? [
                'type' => explode(':', $key)[0],
                'id' => (int) explode(':', $key)[1],
                'name' => 'Unavailable catalog record',
                'application_availability' => null,
                'guidance_mode' => 'guided',
                'prerequisites' => [],
            ];

            // A selected item the resident already holds is shown as owned, not
            // acquired, so it adds no step and pulls in none of its requirements.
            if (isset($selected[$key], $owned[$key])) {
                $nodes[$key] = [
                    ...$record,
                    'selected' => true,
                    'already_owned' => true,
                    'blocked' => false,
                    'issues' => [],
                    'prerequisite_readiness' => $status->readiness($record['prerequisites']),
                ];
                unset($nodes[$key]['pending']);
                $states[$key] = 'done';
                $ordered[] = $key;

                return false;
            }

            $issues = [];

            if (! isset($catalog[$key])) {
                $issues[] =
                    'A linked catalog record is missing. Contact the administrator.';
            } elseif (
                ! in_array(
                    $record['application_availability'],
                    ['available', 'limited_conditional'],
                    true
                )
            ) {
                $issues[] =
                    'This item is currently unavailable for acquisition.';
            }

            if ($depth >= 256) {
                $issues[] =
                    'The prerequisite chain could not be verified. Contact the administrator.';
            }

            if ($record['guidance_mode'] === 'informational_only') {
                $issues[] =
                    'This selected record is now Informational Only. Choose a guided target for acquisition guidance.';
            }

            $nodes[$key] = [
                ...$record,
                'selected' => isset($selected[$key]),
                'blocked' => (bool) $issues,
                'issues' => $issues,
            ];

            foreach ($record['prerequisites'] as $row) {
                if ($record['guidance_mode'] === 'informational_only') {
                    break;
                }

                if (
                    ! in_array($row['type'], ['government_id', 'document'], true)
                    || $row['status'] !== 'missing'
                    || $depth >= 256
                ) {
                    continue;
                }

                $dependency = $row['type'].':'.$row['id'];

                if (($row['resolution'] ?? null) === 'reference_only') {
                    // A reference prerequisite remains required, but is not an acquisition step.
                    if (
                        ! in_array(
                            $catalog[$dependency]['application_availability']
                                ?? null,
                            ['available', 'limited_conditional'],
                            true
                        )
                    ) {
                        $nodes[$key]['blocked'] = true;
                        $nodes[$key]['issues'][] =
                            'Prerequisite path blocked: '
                            .$row['name']
                            .' is currently unavailable for acquisition.';
                    }

                    continue;
                }

                if ($visit($dependency, $depth + 1)) {
                    $nodes[$key]['blocked'] = true;
                    $nodes[$key]['issues'][] =
                        'Prerequisite path blocked: '.$row['name'];
                }
            }

            foreach ($record['pending'] ?? [] as $pending) {
                if ($record['guidance_mode'] !== 'guided') {
                    break;
                }
                $pendingKey = 'unresolved:'.$pending['key'];
                $options = [];
                foreach ($pending['suggestions'] as $option) {
                    if ($pending['kind'] === 'path') {
                        $options[] = $option;

                        continue;
                    }
                    $metrics = $this->metrics($option['type'].':'.$option['id'], $catalog, array_fill_keys(array_keys(array_filter($states, fn ($state) => $state === 'visiting')), true));
                    if ($metrics === null) {
                        continue;
                    } // Never offer a path back to its dependent target.
                    // Already planned here, so using it adds no extra acquisition.
                    $options[] = [...$option, ...$metrics, 'in_journey' => isset($selected[$option['type'].':'.$option['id']])];
                }
                if ($pending['kind'] !== 'path') {
                    usort($options, fn ($a, $b) => [! $a['in_journey'], $a['missing_prerequisites'], $a['dependency_depth'], $a['additional_steps'], $a['estimated_days'] ?? PHP_INT_MAX, $a['name'], $a['type'], $a['id'], $a['way_id']]
                        <=> [! $b['in_journey'], $b['missing_prerequisites'], $b['dependency_depth'], $b['additional_steps'], $b['estimated_days'] ?? PHP_INT_MAX, $b['name'], $b['type'], $b['id'], $b['way_id']]);
                }
                $nodes[$pendingKey] = [
                    ...$pending, 'type' => 'unresolved_requirement', 'id' => $pending['group_id'] ?? $record['id'],
                    'resolution_key' => $pending['key'], 'name' => $pending['name'], 'suggestions' => $options,
                    'selected' => false, 'blocked' => false, 'issues' => [], 'prerequisites' => [],
                    'prerequisite_readiness' => ['satisfied' => 0, 'total' => 0, 'percentage' => null, 'manual' => 0],
                ];
                $ordered[] = $pendingKey;
            }
            unset($nodes[$key]['pending']);
            $nodes[$key]['prerequisite_readiness'] =
                $status->readiness($record['prerequisites']);

            $states[$key] = 'done';
            $ordered[] = $key;

            return $nodes[$key]['blocked'];
        };

        // Each target's dependencies are placed before it. Targets that don't
        // depend on each other have no required order, so this only picks a
        // sensible, repeatable one: fewest steps, then known processing time.
        foreach ($this->independentOrder(array_keys($selected), $catalog) as $key) {
            $visit($key);
        }

        $steps = array_map(
            fn ($key) => $nodes[$key],
            $ordered
        );

        return [
            'target_government_id_ids' => array_values($targets),
            'target_document_ids' => array_values($documentTargets),
            'blocked' => (bool) array_filter(
                $steps,
                fn ($step) => $step['blocked']
            ),
            'steps' => $steps,
        ];
    }

    private function independentOrder(array $keys, array $catalog): array
    {
        $rank = function (string $key) use ($catalog): array {
            $chain = $this->missingChain($key, $catalog);

            return [
                count($chain),
                $this->estimatedDays($chain, $catalog) ?? PHP_INT_MAX,
                $catalog[$key]['name'] ?? '',
                $key,
            ];
        };
        usort($keys, fn ($a, $b) => $rank($a) <=> $rank($b));

        return $keys;
    }

    /** Total days for these records, or null when any of them has no usable duration. */
    private function estimatedDays(array $keys, array $catalog): ?int
    {
        $total = 0;
        foreach ($keys as $key) {
            $days = ProcessingTime::estimatedDays($catalog[$key]['processing_time'] ?? null);
            if ($days === null) {
                return null;
            }
            $total += $days;
        }

        return $total;
    }

    /** The record plus every prerequisite still to be obtained, following linked records. */
    private function missingChain(string $key, array $catalog): array
    {
        $seen = [];
        $walk = function (string $current) use (&$walk, &$seen, $catalog): void {
            if (isset($seen[$current])) {
                return;
            }
            $seen[$current] = true;
            foreach ($catalog[$current]['prerequisites'] ?? [] as $row) {
                if ($row['status'] === 'missing' && in_array($row['type'], ['government_id', 'document'], true)) {
                    $walk($row['type'].':'.$row['id']);
                }
            }
        };
        $walk($key);

        return array_keys($seen);
    }

    /** Estimates from current unresolved paths; counts distinct additional acquisitions. */
    private function metrics(string $key, array $catalog, array $ancestors = []): ?array
    {
        $seen = [];
        $depths = [];
        $walk = function (string $current, array $path) use (&$walk, &$seen, &$depths, $catalog): ?int {
            if (isset($path[$current]) || count($path) >= 128 || ! isset($catalog[$current])) {
                return null;
            }
            if (isset($depths[$current])) {
                return $depths[$current];
            }
            $path[$current] = true;
            $seen[$current] = true;
            $depth = 0;
            foreach ($catalog[$current]['prerequisites'] as $row) {
                if ($row['status'] !== 'missing' || ! in_array($row['type'], ['government_id', 'document'], true) || ($row['resolution'] ?? null) === 'reference_only') {
                    continue;
                }
                $child = $walk($row['type'].':'.$row['id'], $path);
                if ($child === null) {
                    return null;
                }
                $depth = max($depth, $child + 1);
            }

            return $depths[$current] = $depth;
        };
        $depth = $walk($key, $ancestors);
        if ($depth === null) {
            return null;
        }

        return [
            'missing_prerequisites' => count(array_filter($catalog[$key]['prerequisites'], fn ($row) => $row['status'] === 'missing')) + count(array_filter($catalog[$key]['pending'], fn ($row) => $row['kind'] === 'path')),
            'dependency_depth' => $depth, 'additional_steps' => max(0, count($seen) - 1),
            'processing_time' => $catalog[$key]['processing_time'] ?? null,
            'estimated_days' => $this->estimatedDays(array_keys($seen), $catalog),
            'ranking_is_estimate' => (bool) array_filter(array_keys($seen), fn ($node) => ! empty($catalog[$node]['pending'])),
        ];
    }
}
