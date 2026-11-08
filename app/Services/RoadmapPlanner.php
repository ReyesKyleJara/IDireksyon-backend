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
                    'fees.options',
                    'requirementSets.applicationSteps.blocks',
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
                    '_option_facts' => RoadmapOptionFacts::forRecord($record),
                    'application_availability' => $record->application_availability,
                    'guidance_mode' => $record->guidance_mode,
                    'include_in_smart_sequencing' => $record->include_in_smart_sequencing,
                    'requirement_sets' => array_map(fn ($set) => [
                        ...$set,
                        'path_label' => $record->requirementSets->firstWhere('id', $set['id'])?->request_channel['label'] ?? null,
                    ], ResidentRequirements::generalForId($record)),
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
        $checklists = [];
        foreach ($catalog as $key => &$record) {
            $checklists[$key] = $record['requirement_sets'];
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

        // Resolve shared accepted evidence against the whole acquisition graph,
        // before traversal order can turn a target into an unnecessary prompt.
        $this->reuseRoadmapRequirements($catalog, $checklists, $selected, $owned, $age, $selections);
        $included = $this->includedKeys(array_keys($selected), $catalog, $owned);

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
            $included,
            $checklists,
            $age,
            $selections,
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
                unset($nodes[$key]['pending'], $nodes[$key]['_option_facts']);
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
                    $metrics = $this->optionMetrics($option, $pending, $catalog, $checklists, $selected, $owned, $included, $age, $selections,
                        array_fill_keys(array_keys(array_filter($states, fn ($state) => $state === 'visiting')), true));
                    if ($metrics === null) {
                        continue;
                    } // Never offer a path back to its dependent target.
                    // Already planned here, so using it adds no extra acquisition.
                    $options[] = [...$option, ...$metrics, 'in_journey' => isset($included[$option['type'].':'.$option['id']]), 'already_owned' => isset($owned[$option['type'].':'.$option['id']])];
                }
                if ($pending['kind'] !== 'path') {
                    $options = $this->rankOptions($options);
                }
                $nodes[$pendingKey] = [
                    ...$pending, 'type' => 'unresolved_requirement', 'id' => $pending['group_id'] ?? $record['id'],
                    'resolution_key' => $pending['key'], 'name' => $pending['name'], 'suggestions' => $options,
                    'manual_preparation' => $pending['kind'] === 'requirement' && $options === [],
                    'selected' => false, 'blocked' => false, 'issues' => [], 'prerequisites' => [],
                    'prerequisite_readiness' => ['satisfied' => 0, 'total' => 0, 'percentage' => null, 'manual' => 0],
                ];
                $ordered[] = $pendingKey;
            }
            unset($nodes[$key]['pending'], $nodes[$key]['_option_facts']);
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

    /** Actual acquisitions only: accepted alternatives are not dependencies yet. */
    private function includedKeys(array $targets, array $catalog, array $owned): array
    {
        $included = [];
        $walk = function (string $key) use (&$walk, &$included, $catalog, $owned): void {
            if (isset($included[$key]) || isset($owned[$key])) {
                return;
            }
            $included[$key] = true;
            foreach ($catalog[$key]['prerequisites'] ?? [] as $row) {
                if ($row['status'] === 'missing' && in_array($row['type'], ['government_id', 'document'], true)
                    && ($row['resolution'] ?? null) !== 'reference_only') {
                    $walk($row['type'].':'.$row['id']);
                }
            }
        };
        foreach ($targets as $key) {
            $walk($key);
        }

        return $included;
    }

    /** Derived choices stay in memory; Inventory and explicit saved choices win. */
    private function reuseRoadmapRequirements(array &$catalog, array $checklists, array $selected, array $owned, ?int $age, array $selections): void
    {
        while (true) {
            $included = $this->includedKeys(array_keys($selected), $catalog, $owned);
            $choices = [];
            foreach ($included as $owner => $_) {
                foreach ($catalog[$owner]['pending'] ?? [] as $pending) {
                    if ($pending['kind'] !== 'requirement') {
                        continue;
                    }
                    $set = collect($checklists[$owner])->firstWhere('id', $pending['set_id']);
                    $group = collect($set['groups'])->firstWhere('id', $pending['group_id']);
                    foreach ($group['ways'] as $way) {
                        if ($way['qualification_type'] !== 'none' || $way['required_count'] < 1) {
                            continue;
                        }
                        // A partially saved choice locks the OR way. Only ways
                        // still offered by the resolver can be reused here.
                        if (! collect($pending['suggestions'])->contains(fn ($option) => $option['way_id'] === $way['id'])) {
                            continue;
                        }
                        $available = [];
                        $ownedCount = 0;
                        foreach ($way['items'] as $item) {
                            $key = JourneyRequirements::itemKey($item);
                            if ($key === null || isset($available[$key])) {
                                continue;
                            }
                            if (isset($owned[$key])) {
                                $available[$key] = ['owned' => true, 'pending' => 0];
                                $ownedCount++;
                            } elseif (isset($included[$key], $catalog[$key]) && JourneyRequirements::suggestible($catalog[$key])) {
                                $metrics = $this->metrics($key, $catalog, [$owner => true]);
                                if ($metrics === null) {
                                    continue; // Adding owner -> key would form a cycle.
                                }
                                $chain = $this->includedKeys([$key], $catalog, $owned);
                                if (collect(array_keys($chain))->contains(fn ($node) => ! in_array($catalog[$node]['application_availability'] ?? null, ['available', 'limited_conditional'], true))) {
                                    continue;
                                }
                                $available[$key] = ['owned' => false,
                                    'pending' => array_sum(array_map(fn ($node) => count($catalog[$node]['pending'] ?? []), array_keys($chain)))];
                            }
                        }
                        // Never combine OR ways, count duplicates, or silently
                        // assume an unverified custom/qualified item is supplied.
                        if (count($available) < $way['required_count']) {
                            continue;
                        }
                        uksort($available, fn ($a, $b) => [! $available[$a]['owned'], $available[$a]['pending'], $a]
                            <=> [! $available[$b]['owned'], $available[$b]['pending'], $b]);
                        $items = array_slice(array_keys($available), 0, $way['required_count']);
                        $previous = $selections['groups'][$pending['key']] ?? [];
                        // Keep valid explicit selections in their original order.
                        if (($previous['way_id'] ?? null) === $way['id']) {
                            $explicit = array_values(array_filter($previous['items'] ?? [], fn ($key) => isset($available[$key])));
                            $items = array_values(array_unique([...$explicit, ...$items]));
                        }
                        $choices[] = ['owner' => $owner, 'pending' => $pending,
                            'selection' => ['way_id' => $way['id'], 'items' => $items],
                            'rank' => [-min($ownedCount, $way['required_count']),
                                array_sum(array_map(fn ($key) => $available[$key]['pending'], $items)),
                                count($items), $owner, $pending['key'], $way['id']]];
                    }
                }
            }
            if (! $choices) {
                return;
            }
            usort($choices, fn ($a, $b) => $a['rank'] <=> $b['rank']);
            $choice = $choices[0];
            $owner = $choice['owner'];
            $selections['groups'][$choice['pending']['key']] = $choice['selection'];
            $resolved = app(JourneyRequirements::class)->resolve($owner, $checklists[$owner], $owned, $age, $selections, $catalog);
            foreach ($resolved['rows'] as &$row) {
                if (! in_array($row['type'], ['government_id', 'document'], true)) {
                    continue;
                }
                $previous = collect($catalog[$owner]['prerequisites'])->first(fn ($old) => $old['type'] === $row['type'] && $old['id'] === $row['id'] && filled($old['condition'] ?? null));
                if ($previous) {
                    $row['condition'] = $previous['condition'];
                }
            }
            unset($row);
            $catalog[$owner]['prerequisites'] = $resolved['rows'];
            $catalog[$owner]['pending'] = $resolved['pending'];
            // Each pass resolves a complete group, then checks the updated graph
            // again so two individually safe choices cannot form a mutual cycle.
        }
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

    /** Evaluate choosing an option without saving the hypothetical choice or adding ownership. */
    private function optionMetrics(array $option, array $pending, array $catalog, array $checklists, array $selected, array $owned, array $included, ?int $age, array $selections, array $ancestors): ?array
    {
        $key = $option['type'].':'.$option['id'];
        $owner = $pending['owner_key'];
        $previous = $selections['groups'][$pending['key']] ?? [];
        $items = ($previous['way_id'] ?? null) === $option['way_id'] ? ($previous['items'] ?? []) : [];
        $selections['groups'][$pending['key']] = ['way_id' => $option['way_id'], 'items' => array_values(array_unique([...$items, $key]))];
        $resolved = app(JourneyRequirements::class)->resolve($owner, $checklists[$owner], $owned, $age, $selections, $catalog);
        $catalog[$owner]['prerequisites'] = $resolved['rows'];
        $catalog[$owner]['pending'] = $resolved['pending'];
        // Pin owner -> option first. Otherwise a trial could use the owner to
        // obtain its own prerequisite and appear to solve the ID paradox.
        $this->reuseRoadmapRequirements($catalog, $checklists, [...$selected, $key => true], $owned, $age, $selections);
        $metrics = $this->metrics($key, $catalog, $ancestors);
        if ($metrics === null || ! $this->hasAcquisitionPath($key, $catalog, $checklists, $owned, $age, $selections, $ancestors)) {
            return null;
        }
        $chain = $this->includedKeys([$key], $catalog, $owned);
        $new = array_values(array_diff(array_keys($chain), array_keys($included), array_keys($owned)));
        $additional = array_values(array_diff($new, [$key]));
        $combinationRemaining = max(0, ($option['remaining_count'] ?? 1) - 1);
        $unresolved = $combinationRemaining;
        $manual = $reference = 0;
        $covered = $ownedDocuments = $plannedDocuments = [];
        foreach (array_keys($chain) as $node) {
            foreach ($catalog[$node]['pending'] ?? [] as $choice) {
                $unresolved += $choice['kind'] === 'path' ? 1 : max(1, $choice['remaining_count'] ?? 1);
            }
            foreach ($catalog[$node]['prerequisites'] as $row) {
                if ($row['status'] === 'manual') {
                    $manual++;
                }
                if (! in_array($row['type'], ['government_id', 'document'], true)) {
                    if ($row['type'] === 'requirement' && $row['status'] === 'satisfied') {
                        foreach ($checklists[$node] as $set) {
                            foreach ($set['groups'] as $group) {
                                if ($group['id'] !== $row['id']) continue;
                                foreach ($group['ways'] as $way) {
                                    $matches = [];
                                    foreach ($way['items'] as $item) {
                                        $identity = JourneyRequirements::itemKey($item);
                                        if ($identity !== null && isset($owned[$identity])) $matches[$identity] = true;
                                    }
                                    if ($way['qualification_type'] === 'none' && count($matches) >= $way['required_count']) {
                                        foreach (array_slice(array_keys($matches), 0, $way['required_count']) as $identity) {
                                            $covered[$identity] = true;
                                            if (str_starts_with($identity, 'document:')) $ownedDocuments[$identity] = true;
                                        }
                                        break;
                                    }
                                }
                            }
                        }
                    }
                    continue;
                }
                $identity = $row['type'].':'.$row['id'];
                if (isset($owned[$identity]) || isset($included[$identity])) {
                    $covered[$identity] = true;
                    if ($row['type'] === 'document') {
                        if (isset($owned[$identity])) $ownedDocuments[$identity] = true;
                        else $plannedDocuments[$identity] = true;
                    }
                }
                if ($row['status'] === 'missing' && ($row['resolution'] ?? null) === 'reference_only') $reference++;
            }
        }
        $idSteps = count(array_filter($additional, fn ($node) => $catalog[$node]['type'] === 'government_id'));
        $directMissing = count(array_filter($catalog[$key]['prerequisites'], fn ($row) => $row['status'] === 'missing'
            && (! in_array($row['type'], ['government_id', 'document'], true) || ! isset($included[$row['type'].':'.$row['id']]))));
        $flowComplete = $unresolved === 0 && $manual === 0 && $reference === 0;
        foreach (array_unique([$key, ...$new]) as $node) {
            $flowComplete = $flowComplete && $this->hasGuide($node, $catalog, $checklists, $age, $selections);
        }

        return [...$metrics,
            'missing_prerequisites' => $directMissing,
            'additional_steps' => count($additional), 'additional_id_steps' => $idSteps,
            'combination_remaining' => $combinationRemaining,
            'unresolved_choices' => $unresolved, 'manual_review_steps' => $manual + $reference,
            'preparation_steps' => count($additional) + $unresolved + $manual + $reference,
            'covered_prerequisites' => count($covered),
            'uses_owned_documents' => (bool) $ownedDocuments, 'uses_roadmap_documents' => (bool) $plannedDocuments,
            'no_additional_id_required' => $idSteps === 0 && $unresolved === 0 && $manual === 0 && $reference === 0,
            'estimated_days' => $new && $unresolved === 0 && $manual === 0 && $reference === 0 ? $this->estimatedDays($new, $catalog) : null,
            'known_cost' => $unresolved === 0 && $manual === 0 && $reference === 0 ? $this->knownAdditionalCost($new, $catalog) : null,
            'acquisition_flow_complete' => $flowComplete,
            'ranking_is_estimate' => $unresolved > 0 || $manual > 0 || $reference > 0,
        ];
    }

    /** Find complete escape routes, including explicit application-path alternatives. */
    private function hasAcquisitionPath(string $key, array $catalog, array $checklists, array $owned, ?int $age, array $selections, array $forbidden): bool
    {
        $variants = [];
        foreach ($catalog as $candidate => $record) {
            $path = collect($record['pending'] ?? [])->firstWhere('kind', 'path');
            if ($path === null) {
                $variants[$candidate] = [['rows' => $record['prerequisites'], 'pending' => $record['pending'] ?? []]];
                continue;
            }
            $variants[$candidate] = [];
            foreach ($path['suggestions'] as $option) {
                $trial = $selections;
                $trial['paths'][$candidate] = $option['set_id'];
                $variants[$candidate][] = app(JourneyRequirements::class)->resolve($candidate, $checklists[$candidate], $owned, $age, $trial, $catalog);
            }
        }
        // A least fixed point avoids exponentially expanding mutually accepted IDs.
        $possible = $owned;
        do {
            $changed = false;
            foreach ($catalog as $candidate => $record) {
                if (isset($possible[$candidate]) || isset($forbidden[$candidate])
                    || ! in_array($record['application_availability'], ['available', 'limited_conditional'], true)) continue;
                foreach ($variants[$candidate] as $variant) {
                    if ($this->viableRequirements($variant, $checklists[$candidate], $catalog, $owned, $possible)) {
                        $possible[$candidate] = true;
                        $changed = true;
                        break;
                    }
                }
            }
        } while ($changed && ! isset($possible[$key]));

        return isset($possible[$key]);
    }

    private function viableRequirements(array $resolved, array $sets, array $catalog, array $owned, array $possible): bool
    {
        foreach ($resolved['rows'] as $row) {
            if ($row['status'] === 'missing' && in_array($row['type'], ['government_id', 'document'], true)
                && ($row['resolution'] ?? null) !== 'reference_only'
                && ! isset($possible[$row['type'].':'.$row['id']])) return false;
        }
        foreach ($resolved['pending'] as $pending) {
            if ($pending['kind'] === 'path') return false;
            $set = collect($sets)->firstWhere('id', $pending['set_id']);
            $group = collect($set['groups'])->firstWhere('id', $pending['group_id']);
            $wayIds = array_column($pending['suggestions'], 'way_id');
            $escape = false;
            foreach ($group['ways'] as $way) {
                if ($way['qualification_type'] !== 'none' || $way['required_count'] < 1
                    || ($wayIds && ! in_array($way['id'], $wayIds, true))) continue;
                $available = [];
                foreach ($way['items'] as $item) {
                    $identity = JourneyRequirements::itemKey($item);
                    if ($identity === null) {
                        // Only a real custom item is manual work. Missing or
                        // unavailable linked IDs must never be relabelled manual.
                        $available['manual:'.$item['id']] = true;
                    } elseif (isset($owned[$identity]) || (isset($possible[$identity], $catalog[$identity])
                        && JourneyRequirements::suggestible($catalog[$identity]))) {
                        $available[$identity] = true;
                    }
                }
                if (count($available) >= $way['required_count']) {
                    $escape = true;
                    break;
                }
            }
            if (! $escape) return false;
        }

        return true;
    }

    private function hasGuide(string $key, array $catalog, array $checklists, ?int $age, array $selections): bool
    {
        $facts = $catalog[$key]['_option_facts'];
        if ($facts['has_legacy_guide']) return true;
        $chosen = $selections['paths'][$key] ?? null;
        if ($chosen !== null) return in_array($chosen, $facts['guide_set_ids'], true);
        $applicable = array_filter($checklists[$key], fn ($set) => $set['applicant_type'] !== 'custom'
            && ($set['applicant_type'] === 'all' || ($age !== null
                && ($set['min_age'] === null || $age >= $set['min_age']) && ($set['max_age'] === null || $age <= $set['max_age']))));

        return count($applicable) === 1 && in_array(array_values($applicable)[0]['id'], $facts['guide_set_ids'], true);
    }

    private function knownAdditionalCost(array $keys, array $catalog): ?array
    {
        if (! $keys) return null;
        $total = ['min' => 0.0, 'max' => 0.0, 'currency' => null];
        foreach ($keys as $key) {
            $cost = $catalog[$key]['_option_facts']['cost'];
            if ($cost === null || ($total['currency'] !== null && $total['currency'] !== $cost['currency'])) return null;
            $total['min'] += $cost['min'];
            $total['max'] += $cost['max'];
            $total['currency'] = $cost['currency'];
        }
        $total['min'] = round($total['min'], 2);
        $total['max'] = round($total['max'], 2);

        return $total;
    }

    private function practicalRank(array $option): array
    {
        return [$option['combination_remaining'], ! $option['in_journey'], $option['preparation_steps'], $option['unresolved_choices'],
            $option['additional_id_steps'], $option['missing_prerequisites'], $option['dependency_depth'], -$option['covered_prerequisites']];
    }

    private function rankOptions(array $options): array
    {
        // Never invent an exchange rate to compare unlike currencies.
        $currencies = array_unique(array_filter(array_map(fn ($option) => $option['known_cost']['currency'] ?? null, $options)));
        $rank = fn ($option) => [...$this->practicalRank($option), $option['estimated_days'] ?? PHP_INT_MAX,
            ...(count($currencies) <= 1 ? [$option['known_cost'] === null, $option['known_cost']['max'] ?? 0] : []),
            ! $option['acquisition_flow_complete'], $option['name'], $option['type'], $option['id'], $option['way_id']];
        usort($options, fn ($a, $b) => $rank($a) <=> $rank($b));
        $shortlisted = [];
        foreach ($options as &$option) {
            $reasons = [];
            if ($option['uses_owned_documents']) $reasons[] = 'Uses documents you already have';
            if ($option['uses_roadmap_documents']) $reasons[] = 'Uses documents already in your Roadmap';
            if ($option['no_additional_id_required']) $reasons[] = 'No additional ID required';
            if (collect($options)->contains(fn ($other) => $other['preparation_steps'] > $option['preparation_steps'])) $reasons[] = 'Fewer preparation steps';
            $comparable = array_filter($options, fn ($other) => $this->practicalRank($other) === $this->practicalRank($option));
            if ($option['estimated_days'] !== null && collect($comparable)->contains(fn ($other) => $other['estimated_days'] !== null && $other['estimated_days'] > $option['estimated_days'])) $reasons[] = 'Shorter known processing time';
            if (count($currencies) <= 1 && $option['known_cost'] !== null && collect($comparable)->contains(fn ($other) => $other['known_cost'] !== null
                && $other['known_cost']['currency'] === $option['known_cost']['currency'] && $option['known_cost']['max'] < $other['known_cost']['min'])) $reasons[] = 'Lower known cost';
            if ($option['acquisition_flow_complete']) $reasons[] = 'Guided steps available';
            $identity = $option['type'].':'.$option['id'];
            $option['recommended'] = count($shortlisted) < 3 && ! isset($shortlisted[$identity]);
            if ($option['recommended']) $shortlisted[$identity] = true;
            $option['recommendation_reasons'] = $reasons;
        }
        unset($option);

        return $options;
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
