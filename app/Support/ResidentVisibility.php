<?php

namespace App\Support;

use App\Models\Document;
use App\Models\GovernmentId;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Hide Entry / Show Entry: one switch for everything residents can reach.
 *
 * While a resident-facing request runs (the app API and the public share pages), hidden IDs and
 * documents are left out of every query: lists, search, details, suggestions, Roadmap and direct
 * access. The CMS never turns the switch on, so hidden records stay fully editable there.
 */
class ResidentVisibility
{
    public const SCOPE = 'resident-visible';

    private static int $depth = 0;

    /** @var array<string, array<int, string>>|null hidden record names by type, for this request */
    private static ?array $hidden = null;

    public static function active(): bool
    {
        return self::$depth > 0;
    }

    /** Runs a resident-facing request with hidden records left out, then always switches back. */
    public static function during(callable $callback): mixed
    {
        self::$depth++;
        try {
            return $callback();
        } finally {
            if (--self::$depth === 0) {
                self::$hidden = null;
            }
        }
    }

    /** The name of a hidden record, or null when the record is visible (or unknown). */
    public static function hiddenName(?string $type, mixed $id): ?string
    {
        if ($id === null || ! in_array($type, ['government_id', 'document'], true)) {
            return null;
        }

        return self::hidden()[$type][(int) $id] ?? null;
    }

    /** @return array<int, int> ids of hidden records of one type */
    public static function hiddenIds(string $type): array
    {
        return array_keys(self::hidden()[$type] ?? []);
    }

    /**
     * Saves a resident's inventory without losing what they saved for a hidden record: hidden
     * items cannot be added, and ones already saved are kept so they return when it is shown again.
     */
    public static function syncOwned(BelongsToMany $relation, array $ids, string $type): void
    {
        $hidden = self::hiddenIds($type);
        $saved = $relation->newPivotQuery()->pluck($relation->getRelatedPivotKeyName())->map(fn ($id) => (int) $id)->all();
        $relation->sync([
            ...array_values(array_diff(array_map('intval', $ids), $hidden)),
            ...array_values(array_intersect($saved, $hidden)),
        ]);
    }

    private static function hidden(): array
    {
        return self::$hidden ??= [
            'government_id' => GovernmentId::withoutGlobalScope(self::SCOPE)->whereNotNull('hidden_at')->pluck('name', 'id')->all(),
            'document' => Document::withoutGlobalScope(self::SCOPE)->whereNotNull('hidden_at')->pluck('name', 'id')->all(),
        ];
    }

    /** Clears the per-request cache after a record is hidden or shown. */
    public static function forget(): void
    {
        self::$hidden = null;
    }
}
