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
            && ($step['guidance_mode'] ?? 'guided') !== 'informational_only';
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
