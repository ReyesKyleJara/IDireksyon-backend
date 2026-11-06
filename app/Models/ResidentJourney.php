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
        return in_array($step['type'], ['government_id', 'document'], true)
            && ! ($step['blocked'] ?? false)
            && ! ($step['already_owned'] ?? false)
            && ($step['guidance_mode'] ?? 'guided') !== 'informational_only';
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
        $steps = array_values(array_filter($this->plan['steps'], self::actionable(...)));
        $identities = array_map(self::identity(...), $steps);
        $count = count(array_intersect($identities, $completed));

        return [
            'id' => $this->id,
            'plan' => $this->plan,
            'completed_step_identities' => $completed,
            'completed_steps' => $count,
            'total_steps' => count($steps),
            'progress_percentage' => count($steps) ? (int) round(100 * $count / count($steps)) : 0,
        ];
    }
}
