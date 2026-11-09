<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
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
            && ($step['manual_preparation'] ?? false) === true
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
                && (! self::manualPreparation($item) || ! in_array(self::identity($item), $completed, true))) {
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
            ->pluck('completed_at', 'step_identity');
        $completedAt = $completed;
        $completed = $completed->keys()->all();
        $plan = [...$this->plan, 'steps' => $this->withCatalogNotices($this->roadmapSteps())];
        $choices = collect($plan['steps'])->filter(fn ($step) => $step['type'] === 'unresolved_requirement' && ! self::manualPreparation($step))->map(self::identity(...))->all();
        // Old manual completion must never complete a newly restored choice.
        $completed = array_values(array_diff($completed, $choices));
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
            // Set only when every step is done: the moment the last one finished.
            'completed_at' => $count > 0 && $count === count($steps)
                ? Carbon::parse($completedAt->only($identities)->max())->toIso8601String()
                : null,
        ];
    }

    /** Warns about catalog changes on read; the saved steps and progress are not altered. */
    private function withCatalogNotices(array $steps): array
    {
        $models = ['government_id' => GovernmentId::class, 'document' => Document::class];
        $ids = [];
        foreach ($steps as $step) {
            if (isset($models[$step['type']]) && ! ($step['already_owned'] ?? false)) {
                $ids[$step['type']][] = $step['id'];
            }
        }
        if ($ids === []) {
            return $steps;
        }
        $records = $changed = [];
        foreach ($ids as $type => $list) {
            $records[$type] = $models[$type]::whereKey($list)->get(['id', 'application_availability', 'guidance_mode'])->keyBy('id');
            foreach (ContentChangeLog::where('entity_type', $type)->whereIn('entity_id', $list)
                ->where('created_at', '>', $this->updated_at)->get() as $log) {
                if (preg_grep('/requirement|prerequisite|research/', $log->changed_fields ?? [])) {
                    $changed[$type.':'.$log->entity_id] = true;
                }
            }
        }
        foreach ($steps as &$step) {
            if (! isset($ids[$step['type']]) || ! in_array($step['id'], $ids[$step['type']], true)) {
                continue;
            }
            $record = $records[$step['type']][$step['id']] ?? null;
            $notice = match (true) {
                $record === null => 'This item is no longer listed. Review this Roadmap.',
                ! in_array($record->application_availability, ['available', 'limited_conditional'], true) => 'This item is now marked unavailable. Check with the issuing agency.',
                $record->guidance_mode === 'informational_only' => 'Guidance for this item has changed. Review this Roadmap.',
                isset($changed[$step['type'].':'.$step['id']]) => 'Requirements for this item changed after you saved this Roadmap.',
                default => null,
            };
            if ($notice !== null) {
                $step['catalog_notice'] = $notice;
            }
        }
        unset($step);

        return $steps;
    }

    /** Hide old method-only prompts on read; keep the stored acquisition snapshot intact. */
    public function roadmapSteps(): array
    {
        $steps = array_values(array_filter($this->plan['steps'], function ($step) {
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
        $unresolved = array_filter($steps, fn ($step) => $step['type'] === 'unresolved_requirement'
            && isset($step['owner_key'], $step['resolution_key']));
        if ($unresolved && (! empty($this->plan['target_government_id_ids']) || ! empty($this->plan['target_document_ids']))) {
            // Only refresh choices, never recalculate stored acquisition steps on read.
            $current = app(\App\Services\RoadmapPlanner::class)->build(
                $this->resident(), $this->plan['target_government_id_ids'] ?? [], $this->plan['target_document_ids'] ?? []);
            $currentChoices = collect($current['steps'])->where('type', 'unresolved_requirement')->keyBy('resolution_key');
            foreach ($steps as &$step) {
                if ($step['type'] === 'unresolved_requirement' && isset($step['resolution_key'])
                    && $currentChoices->has($step['resolution_key'])) {
                    $step = $currentChoices[$step['resolution_key']];
                } elseif ($step['type'] === 'unresolved_requirement' && isset($step['owner_key'])) {
                    // It may already be covered or the CMS checklist may have
                    // changed. Require an explicit review, never guess 'manual'.
                    $step['manual_preparation'] = false;
                    $step['resolution_ready'] = ! $current['blocked'];
                    $step['suggestions'] = [];
                }
            }
            unset($step);
        }

        return $steps;
    }

    private function resident(): \App\Models\User
    {
        return \App\Models\User::findOrFail($this->user_id);
    }
}
