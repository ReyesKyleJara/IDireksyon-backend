<?php

namespace App\Services;

use App\Models\CatalogAgencyVerification;
use App\Models\Document;
use App\Models\GovernmentId;

/**
 * Captures exactly the resident-facing information inside a verification's
 * confirmed scope (aspects x request paths) and fingerprints it.
 *
 * Any change inside that scope - including hiding a confirmed section or removing
 * a scoped request path - changes the fingerprint and makes the confirmation
 * outdated. Changes outside the scope do not. Only database row ids and
 * timestamps are ignored, because re-saving identical content may renumber them.
 */
final class CatalogVerificationFingerprint
{
    /** Bump only together with a reviewed change to this capture; older approvals then count as outdated. */
    public const VERSION = 1;

    private const ELIGIBILITY = ['eligibility', 'eligibility_age_type', 'eligibility_min_age', 'eligibility_max_age',
        'eligibility_citizenship', 'eligibility_residency', 'eligibility_residency_custom', 'eligibility_other_conditions', 'target_group'];

    private const REQUIREMENTS = ['requirements', 'prerequisite_notes'];

    private const FEES = ['fee', 'fee_type', 'fee_min', 'fee_max', 'fee_currency', 'fee_notes'];

    private const PROCESSING = ['processing_time', 'processing_time_note', 'processing_time_type', 'processing_time_min',
        'processing_time_max', 'processing_time_unit'];

    private const VALIDITY = ['validity', 'validity_type', 'validity_value', 'validity_unit'];

    private const PROCESS_PATH_FIELDS = ['label', 'is_request_method', 'channel', 'provider', 'portal_url', 'fulfillment',
        'appointment', 'applicability', 'preparation', 'application_guide_text', 'guide', 'troubleshooting'];

    /**
     * Keys that only identify database rows, plus an office's barangay coverage: that is
     * IDireksyon's own matching data, not something an agency confirms.
     */
    private const IGNORED_KEYS = ['id', 'created_at', 'updated_at', 'fee_ids', 'coverage', 'barangay'];

    public function snapshot(GovernmentId|Document $record, array $aspects, ?array $pathIds): array
    {
        // Always read the current saved state, never relations cached on the caller's instance.
        $fresh = $record->newQuery()->with(['agency', 'prerequisites'])->findOrFail($record->getKey());
        $detail = CatalogEncoding::detail($fresh);
        $paths = collect($detail['request_paths'])->keyBy('id');
        $scopedIds = $pathIds === null ? $paths->keys()->map(fn ($id) => (int) $id)->sort()->values()->all()
            : collect($pathIds)->map(fn ($id) => (int) $id)->unique()->sort()->values()->all();
        $scoped = collect($scopedIds)->mapWithKeys(fn ($id) => ["path:{$id}" => $paths[$id] ?? ['missing' => true]]);
        $aspects = collect($aspects)->unique()->sort()->values()->all();
        $columns = fn (array $names) => array_intersect_key($fresh->getAttributes(), array_flip($names));
        $fromPaths = fn (array $keys) => $scoped->map(fn ($path) => ($path['missing'] ?? false) ? $path : array_intersect_key($path, array_flip($keys)))->all();

        $captured = [
            'record_type' => $detail['record_type'],
            'name' => $fresh->name,
            'agency' => $fresh->agency?->only(['name', 'acronym']) ?? ['issued_by' => $fresh->issued_by],
            'confirmed_aspects' => $aspects,
            'scope_request_paths' => $pathIds === null ? 'all' : $scopedIds,
            'version' => self::VERSION,
        ];
        foreach ($aspects as $aspect) {
            $captured['aspects'][$aspect] = match ($aspect) {
                'eligibility' => $columns(self::ELIGIBILITY),
                'requirements' => [
                    'record' => $columns(self::REQUIREMENTS),
                    'prerequisites' => $fresh->prerequisites
                        ->map->only(['type', 'government_id_id', 'document_id', 'custom_name', 'condition', 'sort_order'])->values()->all(),
                    'paths' => $fromPaths(['requirement_sets']),
                ],
                'fees' => ['record' => $columns(self::FEES), 'paths' => $fromPaths(['fees'])],
                'processing_time' => ['record' => $columns(self::PROCESSING), 'paths' => $fromPaths(['processing'])],
                'validity' => ['record' => $columns(self::VALIDITY), 'note' => $detail['research_metadata']['validity_note'] ?? null],
                'application_process' => ['paths' => $fromPaths(self::PROCESS_PATH_FIELDS)],
                'offices' => ['record' => $detail['offices'], 'paths' => $fromPaths(['offices'])],
            };
        }

        return $this->canonical($captured);
    }

    public function fingerprint(array $snapshot): string
    {
        return hash('sha256', json_encode($this->canonical($snapshot),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR));
    }

    public function forVerification(CatalogAgencyVerification $verification): string
    {
        return $this->fingerprint($this->snapshot(
            $verification->verifiable,
            $verification->confirmed_aspects ?? [],
            $verification->scope_request_paths,
        ));
    }

    /** True only while the information inside the approved scope is unchanged. */
    public function isCurrent(CatalogAgencyVerification $verification): bool
    {
        return $verification->content_fingerprint !== null
            && $verification->fingerprint_version === self::VERSION
            && $verification->verifiable !== null
            && hash_equals($verification->content_fingerprint, $this->forVerification($verification));
    }

    private function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        $isList = array_is_list($value);
        $clean = [];
        foreach ($value as $key => $child) {
            if (! $isList && in_array($key, self::IGNORED_KEYS, true)) {
                continue;
            }
            $clean[$key] = $this->canonical($child);
        }
        if (! $isList) {
            ksort($clean);
        }

        return $isList ? array_values($clean) : $clean;
    }
}
