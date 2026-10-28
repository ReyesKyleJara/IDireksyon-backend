<?php

namespace App\Services;

use App\Models\ContentChangeLog;
use App\Models\Document;
use App\Models\DocumentPrerequisite;
use App\Models\GovernmentId;
use App\Models\GovernmentIdPrerequisite;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CatalogPrerequisites
{
    public const FIELDS = ['type', 'government_id_id', 'document_id', 'custom_name', 'condition'];

    // Snapshot validation uses the same shape rules before catalog targets are inserted.
    public static function validateRows(array $rows, string $ownerType, int $ownerId, bool $checkDatabase = true): array
    {
        array_walk_recursive($rows, function (&$value) {
            if (is_string($value)) $value = trim($value) === '' ? null : trim($value);
        });
        $validator = Validator::make(['prerequisites' => $rows], [
            'prerequisites' => ['array', 'list', 'max:100'],
            'prerequisites.*' => ['array:id,type,government_id_id,document_id,custom_name,condition'],
            'prerequisites.*.id' => ['nullable', 'integer', 'min:1', 'distinct'],
            'prerequisites.*.type' => ['required', 'in:government_id,document,custom'],
            'prerequisites.*.government_id_id' => ['nullable', 'integer', ...($checkDatabase ? ['exists:government_ids,id'] : [])],
            'prerequisites.*.document_id' => ['nullable', 'integer', ...($checkDatabase ? ['exists:documents,id'] : [])],
            'prerequisites.*.custom_name' => ['nullable', 'string', 'max:255'],
            'prerequisites.*.condition' => ['nullable', 'string', 'max:1000'],
        ]);
        $validator->after(function ($validator) use ($rows, $ownerType, $ownerId) {
            $seen = [];
            foreach ($rows as $index => $row) {
                if (! is_array($row) || ! is_string($row['type'] ?? null)) continue;
                $field = ['government_id' => 'government_id_id', 'document' => 'document_id', 'custom' => 'custom_name'][$row['type'] ?? ''] ?? null;
                $sources = array_filter(Arr::only($row, ['government_id_id', 'document_id', 'custom_name']), fn ($value) => $value !== null && $value !== '');
                if (! $field || count($sources) !== 1 || ! array_key_exists($field, $sources)) {
                    $validator->errors()->add("prerequisites.$index.type", 'Choose exactly one matching prerequisite source.');
                    continue;
                }
                if (($row['type'] ?? null) === $ownerType && is_numeric($row[$field]) && (int) $row[$field] === $ownerId) {
                    $validator->errors()->add("prerequisites.$index.$field", 'An entry cannot depend on itself.');
                }
                if (! is_scalar($row[$field]) || ! is_scalar($row['condition'] ?? '')) continue;
                $source = $field === 'custom_name' ? $row[$field] : (string) (int) $row[$field];
                $identity = json_encode([$row['type'], $source, $row['condition'] ?? null]);
                if (isset($seen[$identity])) $validator->errors()->add("prerequisites.$index.type", 'This prerequisite and condition are already listed.');
                $seen[$identity] = true;
            }
        });
        $validated = $validator->validate()['prerequisites'];
        if ($checkDatabase) self::validateCycles($validated, $ownerType, $ownerId);
        return $validated;
    }

    private static function validateCycles(array $rows, string $ownerType, int $ownerId): void
    {
        $graph = [];
        foreach (['government_id' => GovernmentIdPrerequisite::class, 'document' => DocumentPrerequisite::class] as $type => $model) {
            // Locking reads see the latest committed edges after the catalog locks.
            foreach ($model::query()->orderBy('id')->lockForUpdate()->get() as $edge) {
                if ($edge->type === 'custom') continue;
                $graph[$type.':'.$edge->owner_id][] = $edge->type.':'.$edge->{$edge->type.'_id'};
            }
        }
        $owner = $ownerType.':'.$ownerId;
        foreach ($rows as $index => $row) {
            if ($row['type'] === 'custom') continue;
            $pending = [$row['type'].':'.$row[$row['type'].'_id']];
            $visited = [];
            while ($pending) {
                $node = array_pop($pending);
                if ($node === $owner) {
                    throw ValidationException::withMessages([
                        "prerequisites.$index.type" => 'This prerequisite would create a circular dependency.',
                    ]);
                }
                if (isset($visited[$node])) continue;
                $visited[$node] = true;
                foreach ($graph[$node] ?? [] as $target) $pending[] = $target;
            }
        }
    }

    public function save(GovernmentId|Document $owner, array $rows): void
    {
        DB::transaction(function () use ($owner, $rows) {
            // Serialize graph edits across both catalog types, in a fixed order.
            // Owner-only locks would let simultaneous A -> B and B -> A both pass.
            GovernmentId::query()->orderBy('id')->lockForUpdate()->get(['id']);
            Document::query()->orderBy('id')->lockForUpdate()->get(['id']);
            $owner->newQuery()->whereKey($owner->id)->lockForUpdate()->firstOrFail();
            $rows = self::validateRows($rows, $owner instanceof Document ? 'document' : 'government_id', $owner->id);
            $existing = $owner->prerequisites()->lockForUpdate()->get()->keyBy('id');
            foreach ($rows as $row) {
                if (!empty($row['id']) && !$existing->has((int) $row['id'])) {
                    throw ValidationException::withMessages(['prerequisites' => 'A prerequisite does not belong to this entry. Reload before saving.']);
                }
            }
            $retained = [];
            $changed = false;
            foreach ($rows as $index => $row) {
                $saved = !empty($row['id']) ? $existing->get((int) $row['id']) : $owner->prerequisites()->make();
                $saved->fill(array_replace(array_fill_keys(self::FIELDS, null), Arr::only($row, self::FIELDS), ['sort_order' => $index]));
                if (!$saved->exists || $saved->isDirty()) { $saved->save(); $changed = true; }
                $retained[] = $saved->id;
            }
            $removed = $owner->prerequisites()->whereNotIn('id', $retained)->delete();
            if ($changed || $removed) ContentChangeLog::record($owner, 'updated', ['prerequisites']);
            $owner->unsetRelation('prerequisites');
        });
    }

    public static function present(GovernmentId|Document $owner): array
    {
        $owner->loadMissing(['prerequisites.governmentId', 'prerequisites.document']);
        return $owner->prerequisites->map(fn ($row) => [
            'type' => $row->type,
            'id' => match ($row->type) { 'government_id' => $row->government_id_id, 'document' => $row->document_id, default => null },
            'name' => $row->display_name,
            'condition' => $row->condition,
            'sort_order' => $row->sort_order,
        ])->values()->all();
    }
}
