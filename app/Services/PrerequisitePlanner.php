<?php

namespace App\Services;

use App\Models\GovernmentId;
use App\Models\GovernmentIdPrerequisite;
use App\Models\GovernmentIdRequirementSet;
use App\Support\ApplicantContext;
use App\Support\PrerequisitePlan;
use App\Support\PrerequisitePlanNode;
use App\Support\ProfileSelections;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class PrerequisitePlanner
{
    public function __construct(
        private readonly GovernmentIdApplicability $applicability,
        private readonly PrerequisiteGraph $graph,
    ) {}

    public function plan(GovernmentId $target, ApplicantContext $context, ?int $selectedProfileId = null, ?ProfileSelections $profileSelections = null): PrerequisitePlan
    {
        $selections = $profileSelections ?? new ProfileSelections;
        // Re-read on every plan: never reuse a stale Eloquent relationship after a CMS edit.
        if ($selectedProfileId !== null) {
            $target->requirementSets()->findOrFail($selectedProfileId);
            $mappedRoot = $selections->forGovernmentId($target->id);
            if ($mappedRoot !== null && $mappedRoot !== $selectedProfileId) {
                throw ValidationException::withMessages(['selected_profile_id' => 'The root profile selection must agree with profile_selections.']);
            }
            try {
                $selections = $selections->withSelection($target->id, $selectedProfileId);
            } catch (InvalidArgumentException $exception) {
                throw ValidationException::withMessages(['profile_selections' => $exception->getMessage()]);
            }
        }
        $this->validateSelections($selections);
        $rows = GovernmentIdPrerequisite::with(['profile', 'prerequisiteGovernmentId', 'prerequisiteDocument'])
            ->orderBy('sort_order')->orderBy('id')->get();
        try {
            $this->graph->validate($rows->map->attributesToArray()->all(), GovernmentIdRequirementSet::pluck('government_id_id', 'id')->all());
        } catch (ValidationException $exception) {
            return new PrerequisitePlan($target->id, 'configuration_error', [], [], errors: [
                ['code' => 'invalid_prerequisite_graph', 'messages' => $exception->errors()],
            ]);
        }
        $byOwner = $rows->groupBy('government_id_id');
        $nodes = [];
        $ordered = [];
        $edges = [];
        $pending = [];
        $errors = [];
        $resolutions = [];
        $visit = function ($record, string $kind, int $depth) use (&$visit, &$nodes, &$ordered, &$edges, &$pending, &$errors, &$resolutions, $target, $context, $selections, $byOwner): void {
            $key = $kind.':'.$record->id;
            if (isset($nodes[$key])) {
                return;
            }
            $owned = $kind === 'document' ? $context->hasDocument($record->id) : $context->hasGovernmentId($record->id);
            $nodes[$key] = new PrerequisitePlanNode($kind, $record->id, $record->name, $key === 'government_id:'.$target->id, $owned, $depth, null);
            // Owning a prerequisite satisfies that entire branch; Documents are always leaves.
            if (! $owned && $kind === 'government_id') {
                $links = $byOwner->get($record->id, collect());
                $resolution = null;
                $manualProfile = $selections->forGovernmentId($record->id);
                if ($links->contains(fn ($link) => $link->requirement_set_id !== null)
                    || $manualProfile !== null) {
                    // Reuse the established profile resolver, which delegates every rule to ApplicabilityEvaluator.
                    // Its Requirements projection is never traversed as a dependency graph.
                    $resolution = $this->applicability->evaluate($record->fresh(), $context, $manualProfile)['applicability'];
                    $resolutions[] = [
                        'government_id' => $record->only(['id', 'name']),
                        'applicability' => $resolution,
                    ];
                }
                $selected = $resolution['selected_profile']['id'] ?? null;
                $pendingLinks = [];
                foreach ($links as $link) {
                    if ($link->requirement_set_id !== null && $selected !== $link->requirement_set_id) {
                        $potential = array_column(array_merge($resolution['pending_profiles'], $resolution['matched_profiles']), 'id');
                        if ($selected === null && in_array($link->requirement_set_id, $potential, true)) {
                            $candidate = $link->prerequisiteGovernmentId ?? $link->prerequisiteDocument;
                            if ($candidate === null) {
                                $errors[] = ['code' => 'missing_prerequisite_record', 'prerequisite_id' => $link->id];

                                continue;
                            }
                            $isId = $link->prerequisite_government_id_id !== null;
                            $pendingLinks[] = [
                                'prerequisite_id' => $link->id,
                                'applicability_profile_id' => $link->requirement_set_id,
                                'kind' => $isId ? 'government_id' : 'document',
                                'id' => $candidate?->id, 'name' => $candidate?->name,
                                'satisfied' => $candidate !== null && ($isId ? $context->hasGovernmentId($candidate->id) : $context->hasDocument($candidate->id)),
                                'sort_order' => $link->sort_order, 'note' => $link->note,
                            ];
                        }

                        continue;
                    }
                    $next = $link->prerequisiteGovernmentId ?? $link->prerequisiteDocument;
                    $nextKind = $link->prerequisite_government_id_id !== null ? 'government_id' : 'document';
                    if (! $next) {
                        $errors[] = ['code' => 'missing_prerequisite_record', 'prerequisite_id' => $link->id];

                        continue;
                    }
                    $edges[] = [
                        'prerequisite_id' => $link->id, 'from' => $key, 'to' => $nextKind.':'.$next->id,
                        'applicability_profile_id' => $link->requirement_set_id, 'note' => $link->note,
                        'sort_order' => $link->sort_order,
                    ];
                    $visit($next, $nextKind, $depth + 1);
                }
                if ($pendingLinks !== []) {
                    $pending[] = [
                        'government_id_id' => $record->id, 'prerequisite_ids' => array_column($pendingLinks, 'prerequisite_id'),
                        'prerequisites' => $pendingLinks,
                        'status' => $resolution['status'], 'missing_context' => $resolution['missing_context'],
                        'available_profiles' => $resolution['available_profiles'],
                        'pending_profiles' => $resolution['pending_profiles'],
                        'matched_profiles' => $resolution['matched_profiles'],
                    ];
                }
            }
            if (! $owned) {
                $node = $nodes[$key];
                $nodes[$key] = new PrerequisitePlanNode($node->kind, $node->id, $node->name, $node->isTarget, false, $node->depth, count($ordered) + 1);
                $ordered[] = $nodes[$key];
            }
        };
        $visit($target->fresh(), 'government_id', 0);

        return new PrerequisitePlan(
            $target->id,
            $errors !== [] ? 'configuration_error' : ($pending !== [] ? 'manual_confirmation_required' : 'ready'),
            array_values($nodes), $errors === [] ? $ordered : [], $edges, $pending, $errors, $resolutions,
        );
    }

    /** Validate every supplied choice once, including choices for owned or currently unvisited IDs. */
    private function validateSelections(ProfileSelections $selections): void
    {
        $map = $selections->all();
        if ($map === []) {
            return;
        }
        $ids = GovernmentId::whereKey(array_keys($map))->pluck('id')->map(fn ($id) => (int) $id)->all();
        $owners = GovernmentIdRequirementSet::whereKey(array_values($map))->pluck('government_id_id', 'id')->map(fn ($id) => (int) $id)->all();
        $errors = [];
        foreach ($map as $id => $profile) {
            if (! in_array($id, $ids, true)) {
                $errors['profile_selections.'.$id] = 'The selected Government ID does not exist.';
            } elseif (($owners[$profile] ?? null) !== $id) {
                $errors['profile_selections.'.$id] = 'The selected Applicability Profile must exist and belong to this Government ID.';
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
