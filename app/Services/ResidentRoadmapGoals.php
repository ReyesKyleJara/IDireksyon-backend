<?php

namespace App\Services;

use App\Models\GovernmentIdRequirementSet;
use App\Models\ResidentRoadmapGoal;
use App\Support\ProfileSelections;
use InvalidArgumentException;

/** Goals retain decisions only. Current CMS profiles and inventory remain authoritative. */
class ResidentRoadmapGoals
{
    public function currentSelections(mixed $stored): ProfileSelections
    {
        try {
            $selections = new ProfileSelections(is_array($stored) ? $stored : []);
        } catch (InvalidArgumentException) {
            return new ProfileSelections;
        }
        $owners = GovernmentIdRequirementSet::whereHas('governmentId')->whereKey(array_values($selections->all()))
            ->pluck('government_id_id', 'id')->all();
        $valid = array_filter($selections->all(), fn ($profile, $id) => (int) ($owners[$profile] ?? 0) === $id, ARRAY_FILTER_USE_BOTH);

        return new ProfileSelections($valid);
    }

    public function present(ResidentRoadmapGoal $goal, array $ownedIds, bool $discarded = false): array
    {
        $stored = $goal->profile_selections;
        $valid = $this->currentSelections($stored);
        // MySQL may reorder JSON object keys; ordering alone is not a discarded choice.
        $lostChoices = $stored !== null && (! is_array($stored) || count($valid->all()) !== count($stored));

        return [
            'government_id' => $goal->governmentId->only(['id', 'name']),
            'application_type' => $goal->application_type,
            'profile_selections' => (object) $valid->all(),
            'discarded_profile_selections' => $discarded || $lostChoices,
            'is_completed' => in_array($goal->government_id_id, $ownedIds, true),
            'created_at' => $goal->created_at?->toIso8601String(),
            'updated_at' => $goal->updated_at?->toIso8601String(),
        ];
    }
}
