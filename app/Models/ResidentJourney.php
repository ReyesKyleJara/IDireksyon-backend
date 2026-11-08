<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ResidentJourney extends Model
{
    protected $fillable = ['user_id', 'journey_key', 'plan'];

    protected function casts(): array
    {
        return ['plan' => 'array'];
    }

    public static function identity(array $step): string
    {
        return $step['type'] === 'unresolved_requirement'
            ? $step['resolution_key'] : $step['type'].':'.$step['id'];
    }

    public static function actionable(array $step): bool
    {
        return (in_array($step['type'], ['government_id', 'document'], true) || self::manualPreparation($step))
            && ! ($step['blocked'] ?? false)
            && ! ($step['already_owned'] ?? false)
            && ($step['guidance_mode'] ?? 'guided') !== 'informational_only';
    }

    public static function manualPreparation(array $step): bool
    {
        return $step['type'] === 'unresolved_requirement'
            && ($step['kind'] ?? null) === 'requirement'
            && isset($step['suggestions']) && $step['suggestions'] === [];
    }

    public static function countsForProgress(array $step): bool
    {
        // Old saved plans may still contain guided choices. Never hide those
        // from the denominator, even though they cannot be marked complete.
        return $step['type'] === 'unresolved_requirement' || self::actionable($step);
    }

    /** Actionable steps this step still depends on. Unrelated steps can finish in any order. */
    public static function waitingOn(array $step, array $steps, array $completed): array
    {
        $actionable = [];
        foreach ($steps as $item) {
            if (self::actionable($item)) {
                $actionable[self::identity($item)] = $item;
            }
        }
        $waiting = [];
        foreach ($steps as $item) {
            if ($item['type'] === 'unresolved_requirement'
                && ($item['owner_key'] ?? null) === self::identity($step)
                && ! in_array(self::identity($item), $completed, true)) {
                $waiting[] = $item;
            }
        }
        foreach ($step['prerequisites'] ?? [] as $row) {
            if (($row['status'] ?? null) !== 'missing' || ($row['resolution'] ?? null) === 'reference_only'
                || ! in_array($row['type'] ?? null, ['government_id', 'document'], true)) {
                continue;
            }
            $identity = $row['type'].':'.$row['id'];
            if (isset($actionable[$identity]) && ! in_array($identity, $completed, true)) {
                $waiting[] = $actionable[$identity];
            }
        }

        return $waiting;
    }

    public function present(): array
    {
        $completed = DB::table('resident_journey_completions')
            ->where('resident_journey_id', $this->id)->orderBy('step_identity')
            ->pluck('step_identity')->all();
        $plan = [...$this->plan, 'steps' => $this->roadmapSteps()];
        $steps = array_values(array_filter($plan['steps'], self::countsForProgress(...)));
        $identities = array_map(self::identity(...), array_filter($steps, self::actionable(...)));
        $count = count(array_intersect($identities, $completed));
        $percentage = count($steps) ? (int) round(100 * $count / count($steps)) : 0;
        if ($count < count($steps)) {
            $percentage = min(99, $percentage);
        }

        return [
            'id' => $this->id,
            'plan' => $plan,
            'completed_step_identities' => $completed,
            'completed_steps' => $count,
            'total_steps' => count($steps),
            'progress_percentage' => $percentage,
        ];
    }

    /** Hide old method-only prompts on read; keep the stored acquisition snapshot intact. */
    public function roadmapSteps(): array
    {
        return array_values(array_filter($this->plan['steps'], function ($step) {
            if (($step['kind'] ?? null) !== 'path' || empty($step['suggestions'])) {
                return true;
            }
            [$type, $id] = array_pad(explode(':', $step['owner_key'] ?? ''), 2, null);
            $model = match ($type) {
                'document' => Document::class,
                'government_id' => GovernmentId::class,
                default => null,
            };
            $owner = $model ? $model::find($id) : null;
            if (! $owner) {
                return true;
            }
            $methodIds = $owner->requirementSets->filter(fn ($set) => \App\Support\ResidentRequirements::isRequestMethod($set))->pluck('id')->all();

            return ! collect($step['suggestions'])->every(fn ($option) => in_array($option['set_id'] ?? null, $methodIds, true));
        }));
    }
}
