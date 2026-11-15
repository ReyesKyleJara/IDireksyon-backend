<?php

namespace App\Services;

use App\Models\ContentChangeLog;
use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * "Last Updated" and "Last Reviewed" for IDs and documents. They are kept separate:
 * - Last Updated changes automatically whenever resident-facing information changes.
 * - Last Reviewed is set only by "Mark as reviewed": a team member checked the record
 *   against its sources. It is never an agency confirmation, and a later change does not
 *   erase it; the record is shown as "updated since the last review" instead.
 */
class CatalogReviewDates
{
    /** Saves that do not change what residents read. */
    public const NOT_INFORMATION = ['image_path', 'image_original_path', 'image', 'agency_validation_status',
        'content_updated_at', 'last_reviewed_at', 'last_reviewed_by', 'created_at', 'updated_at'];

    /** Research notes that describe where information came from, not the information itself. */
    private const RESEARCH_NOT_INFORMATION = ['sources', 'status'];

    /** Called for every change-log entry; stamps Last Updated when record information changed. */
    public static function noteChange(Model $record, string $action, array $fields): void
    {
        if (! ($record instanceof GovernmentId || $record instanceof Document) || ! $record->exists
            || ! in_array($action, ['created', 'updated'], true)) {
            return;
        }
        $fields = array_diff($fields, self::NOT_INFORMATION);
        if (in_array('research_metadata', $fields, true) && ! self::researchInformationChanged($record)) {
            $fields = array_diff($fields, ['research_metadata']);
        }
        if ($fields === []) {
            return;
        }
        $now = now();
        // A direct query: no model events, so no extra audit entry and no change to updated_at.
        $record->newQueryWithoutScopes()->whereKey($record->getKey())->toBase()->update(['content_updated_at' => $now]);
        $record->forceFill(['content_updated_at' => $now])->syncOriginalAttribute('content_updated_at');
    }

    /** True unless only the online sources or the encoding status changed. */
    private static function researchInformationChanged(Model $record): bool
    {
        if (! $record->wasChanged('research_metadata')) {
            return true; // Unknown which part changed: count it.
        }
        $before = $record->getPrevious()['research_metadata'] ?? null;
        $before = is_string($before) ? json_decode($before, true) : $before;
        // An empty value and a missing one look the same to residents (e.g. hidden_sections: []).
        $strip = fn ($value) => is_array($value) ? array_filter(array_diff_key($value, array_flip(self::RESEARCH_NOT_INFORMATION)),
            fn ($part) => $part !== null && $part !== '' && $part !== []) : [];

        return $strip($before) != $strip($record->research_metadata);
    }

    /** "Mark as reviewed": needs at least one online source with a link and the date it was checked. */
    public function markReviewed(User $user, GovernmentId|Document $record): void
    {
        $sources = collect($record->research_metadata['sources'] ?? [])
            ->filter(fn ($source) => is_array($source) && filled($source['url'] ?? null) && filled($source['checked_on'] ?? null));
        if ($sources->isEmpty()) {
            throw ValidationException::withMessages(['review' =>
                'Add at least one online source with the date you checked it (Edit details → Basic Information → Data Sources) before marking this as reviewed.']);
        }
        $now = now();
        $record->newQueryWithoutScopes()->whereKey($record->getKey())->toBase()
            ->update(['last_reviewed_at' => $now, 'last_reviewed_by' => $user->getKey()]);
        $record->forceFill(['last_reviewed_at' => $now, 'last_reviewed_by' => $user->getKey()])
            ->syncOriginalAttributes(['last_reviewed_at', 'last_reviewed_by']);
        ContentChangeLog::record($record, 'reviewed', ['last_reviewed_at']);
    }

    /** @return array{last_updated: ?Carbon, last_reviewed: ?Carbon, updated_since_review: bool} */
    public static function state(GovernmentId|Document $record): array
    {
        $updated = $record->content_updated_at;
        $reviewed = $record->last_reviewed_at;

        return ['last_updated' => $updated, 'last_reviewed' => $reviewed,
            'updated_since_review' => $reviewed !== null && $updated !== null && $updated->greaterThan($reviewed)];
    }

    /** Public API shape. The reviewer's identity is never sent to residents. */
    public static function publicSummary(GovernmentId|Document $record): array
    {
        $state = self::state($record);

        return ['last_updated_at' => $state['last_updated']?->toISOString(), 'last_reviewed_at' => $state['last_reviewed']?->toISOString(),
            'updated_since_review' => $state['updated_since_review']];
    }
}
