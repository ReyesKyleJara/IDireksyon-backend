<?php

namespace App\Support;

/** Partial orders are provisional whenever status is not ready. Edges retain profile provenance. */
final readonly class PrerequisitePlan
{
    /**
     * @param  list<PrerequisitePlanNode>  $nodes  All discovered nodes, including owned/satisfied nodes
     * @param  list<PrerequisitePlanNode>  $orderedMissing  Prerequisites first, target last
     * @param  list<array>  $edges  Explicit prerequisite ID, source/target keys, profile and note
     * @param  list<array>  $pendingApplicability  Owner, pending edges, available profiles and missing facts
     * @param  list<array>  $errors  Configuration errors; no executable order is returned on error
     * @param  list<array>  $applicabilityResolutions  Existing per-ID applicability decisions for profile-scoped links
     */
    public function __construct(
        public int $targetGovernmentIdId,
        public string $status,
        public array $nodes,
        public array $orderedMissing,
        public array $edges = [],
        public array $pendingApplicability = [],
        public array $errors = [],
        public array $applicabilityResolutions = [],
    ) {}

    /** Detailed resolution state; the original foundation status remains backward compatible. */
    public function resolutionStatus(): string
    {
        if ($this->status === 'configuration_error') {
            return 'configuration_error';
        }
        $states = array_map(fn ($resolution) => $resolution['applicability']['status'], $this->applicabilityResolutions);
        // Missing facts precede manual profiles, as in the existing applicability API.
        foreach (['insufficient_context', 'manual_confirmation_required', 'multiple_matches', 'no_match'] as $state) {
            if (in_array($state, $states, true)) {
                return $state;
            }
        }

        return $this->status;
    }
}
