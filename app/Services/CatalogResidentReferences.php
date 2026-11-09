<?php

namespace App\Services;

use App\Models\Document;
use App\Models\GovernmentId;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Coordinates resident saves with catalog deletion without changing resident data. */
class CatalogResidentReferences
{
    /**
     * Lock in a consistent order, before reading dependencies or building plans.
     * JSON references have no foreign keys, so resident writers must participate.
     * Shared locks allow concurrent resident saves; deletion takes exclusive locks.
     * SQLite ignores row locks and instead serializes writes (or rejects contention).
     */
    public static function transaction(Closure $callback, bool $exclusive = false): mixed
    {
        return DB::transaction(function () use ($callback, $exclusive) {
            foreach ([GovernmentId::class, Document::class] as $model) {
                $query = $model::query()->select('id')->orderBy('id');
                ($exclusive ? $query->lockForUpdate() : $query->sharedLock())->get();
            }

            return $callback();
        }, attempts: 3);
    }

    public function exists(GovernmentId|Document $record, bool $lock = false): bool
    {
        $kind = $record instanceof GovernmentId ? 'government_id' : 'document';
        $column = $kind === 'government_id' ? 'government_id_id' : 'document_id';
        $inventory = $kind === 'government_id' ? 'resident_government_ids' : 'resident_documents';

        if ($this->read(DB::table($inventory)->where($column, $record->id), $lock)->exists()) {
            return true;
        }

        // Both the entry's own checklist and items referring to it must survive.
        // A saved false value is still resident progress, not an unused row.
        foreach (['government_id' => 'resident_id_preparations', 'document' => 'resident_document_preparations'] as $owner => $preparations) {
            $query = DB::table($preparations.' as preparation')
                ->join($owner.'_requirement_items as item', 'item.id', '=', 'preparation.requirement_item_id')
                ->join($owner.'_requirement_groups as grp', 'grp.id', '=', 'item.requirement_group_id')
                ->join($owner.'_requirement_sets as requirement_set', 'requirement_set.id', '=', 'grp.requirement_set_id')
                ->where(function ($query) use ($owner, $kind, $column, $record) {
                    $query->where('item.'.$column, $record->id);
                    if ($owner === $kind) {
                        $query->orWhere('requirement_set.'.$column, $record->id);
                    }
                });
            if ($this->read($query, $lock)->exists()) {
                return true;
            }
        }

        foreach ($this->read(DB::table('resident_journeys')->select('plan')->orderBy('id'), $lock)->cursor() as $journey) {
            if ($this->mentions($this->decode($journey->plan), $kind, $record->id)) {
                return true;
            }
        }

        // Completion history can outlive a step removed from a saved plan.
        $identity = $kind.':'.$record->id;
        $query = DB::table('resident_journey_completions')->where(function ($query) use ($identity) {
            $query->where('step_identity', $identity)->orWhere('step_identity', 'like', $identity.':%')
                ->orWhere('step_identity', 'like', 'unresolved:'.$identity.':%');
        });
        if ($this->read($query, $lock)->exists()) {
            return true;
        }

        foreach ($this->read(DB::table('resident_journey_resolutions')->select('user_id', 'journey_key', 'selections')->orderBy('id'), $lock)->cursor() as $resolution) {
            $selections = $this->decode($resolution->selections);
            if ($this->mentions($selections, $kind, $record->id)) {
                return true;
            }

            if (! $this->hasTargets($selections, $resolution->journey_key)) {
                // Older selections contain only a hash of their target list.
                // A matching saved journey can supply it; otherwise fail closed.
                $plan = $this->read(DB::table('resident_journeys')->where('user_id', $resolution->user_id)
                    ->where('journey_key', $resolution->journey_key), $lock)->value('plan');
                $targets = $plan === null ? null : $this->decode($plan);
                if (! $this->hasTargets($targets, $resolution->journey_key)
                    || $this->mentions($targets, $kind, $record->id)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** Current reads avoid a stale MySQL REPEATABLE READ snapshot in outer transactions. */
    private function read(Builder $query, bool $lock): Builder
    {
        return $lock ? $query->sharedLock() : $query;
    }

    /** Caller holds the owner row lock; any cascade loss rolls back the CMS transaction. */
    public function preservePreparations(GovernmentId|Document $owner, Closure $callback): mixed
    {
        $kind = $owner instanceof GovernmentId ? 'government_id' : 'document';
        $preparations = $kind === 'government_id' ? 'resident_id_preparations' : 'resident_document_preparations';
        $column = $kind === 'government_id' ? 'government_id_id' : 'document_id';
        $items = DB::table($kind.'_requirement_items as item')
            ->join($kind.'_requirement_groups as grp', 'grp.id', '=', 'item.requirement_group_id')
            ->join($kind.'_requirement_sets as requirement_set', 'requirement_set.id', '=', 'grp.requirement_set_id')
            ->where('requirement_set.'.$column, $owner->id)
            ->whereExists(fn ($query) => $query->selectRaw('1')->from($preparations.' as preparation')
                ->whereColumn('preparation.requirement_item_id', 'item.id'))
            ->sharedLock()->pluck('item.id')->all();
        $result = $callback();
        if ($items !== [] && DB::table($kind.'_requirement_items')->whereIn('id', $items)->sharedLock()->count() !== count($items)) {
            throw ValidationException::withMessages(['catalog_deletion' => CatalogRecordDeletion::BLOCKED_MESSAGE]);
        }

        return $result;
    }

    private function decode(mixed $json): ?array
    {
        if (is_array($json)) {
            return $json;
        }

        $value = is_string($json) ? json_decode($json, true) : null;

        return is_array($value) ? $value : null;
    }

    private function hasTargets(?array $data, string $key): bool
    {
        if (! is_array($data['target_government_id_ids'] ?? null) || ! is_array($data['target_document_ids'] ?? null)) {
            return false;
        }
        $keys = [];
        foreach (['government_id', 'document'] as $kind) {
            foreach ($data['target_'.$kind.'_ids'] as $id) {
                if (! is_int($id) && ! (is_string($id) && ctype_digit($id))) {
                    return false;
                }
                $keys[] = $kind.':'.$id;
            }
        }
        sort($keys);

        return $keys !== [] && hash_equals($key, hash('sha256', json_encode($keys)));
    }

    /** Inspect typed nested references and identity keys, never names or bare IDs. */
    private function mentions(?array $data, string $kind, int $id): bool
    {
        // An unreadable resident snapshot cannot establish that deletion is safe.
        if ($data === null) {
            return true;
        }
        if (($data['type'] ?? null) === $kind && (string) ($data['id'] ?? '') === (string) $id) {
            return true;
        }
        $targets = $data['target_'.$kind.'_ids'] ?? [];
        if (! is_array($targets)) {
            return true;
        }
        foreach ($targets as $target) {
            if ((string) $target === (string) $id) {
                return true;
            }
        }
        if ((string) ($data[$kind === 'government_id' ? 'government_id_id' : 'document_id'] ?? '') === (string) $id) {
            return true;
        }
        $identity = '/^(?:unresolved:)?'.$kind.':'.$id.'(?::|$)/';
        foreach ($data as $key => $value) {
            if ((is_string($key) && preg_match($identity, $key))
                || (is_string($value) && preg_match($identity, $value))
                || (is_array($value) && $this->mentions($value, $kind, $id))) {
                return true;
            }
        }

        return false;
    }
}
