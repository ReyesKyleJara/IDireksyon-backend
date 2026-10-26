<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Fixed-ID reference snapshots. Never reads account tables or writes existing rows. */
class ReferenceData
{
    public const DEVELOPMENT_METADATA = [
        'purpose' => 'development_only',
        'verification_status' => 'unverified',
        'production_approved' => false,
        'warning' => 'DEVELOPMENT ONLY: includes draft, incomplete, and unverified CMS reference content. Not approved for production.',
    ];

    /** Fail closed for production, staging, and unknown environments; no force bypass. */
    public function assertDevelopmentEnvironment(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Reference snapshots are restricted to local/testing. Production imports and exports are blocked; a separate production approval process is required.');
        }
    }

    public const COLUMNS = [
        'agencies' => 'id name acronym official_website',
        'government_ids' => 'id name level category issued_by office_location description eligibility requirements fee processing_time validity agency_id office_hours eligibility_age_type eligibility_citizenship eligibility_residency eligibility_residency_custom validity_type validity_unit processing_time_type processing_time_unit fee_type fee_currency purpose eligibility_other_conditions prerequisite_notes application_process renewal_process replacement_process fee_notes eligibility_min_age eligibility_max_age validity_value processing_time_min processing_time_max fee_min fee_max official_link official_sources last_verified_at last_verified_by',
        'documents' => 'id name level category issued_by office_location description eligibility requirements fee processing_time validity agency_id office_hours eligibility_age_type eligibility_citizenship eligibility_residency eligibility_residency_custom validity_type validity_unit processing_time_type processing_time_unit fee_type fee_currency purpose eligibility_other_conditions prerequisite_notes application_process renewal_process replacement_process fee_notes eligibility_min_age eligibility_max_age validity_value processing_time_min processing_time_max fee_min fee_max',
        'offices' => 'id name agency_id address municipality province latitude longitude status source_url last_verified_at last_verified_by notes',
        'office_schedules' => 'id office_id day_of_week status',
        'office_schedule_intervals' => 'id office_schedule_id opens_at closes_at',
        'government_id_requirement_sets' => 'id government_id_id application_type application_type_custom applicant_type applicant_type_custom min_age max_age',
        'government_id_requirement_groups' => 'id requirement_set_id title rule condition_type condition_custom sort_order',
        'government_id_requirement_ways' => 'id requirement_group_id required_count qualification_type qualification_scope qualification_custom sort_order',
        'government_id_requirement_items' => 'id requirement_group_id type government_id_id document_id custom_name submission_format submission_format_custom copies instructions sort_order requirement_way_id quantity',
        'government_id_application_steps' => 'id requirement_set_id title short_description type sort_order',
        'government_id_application_step_blocks' => 'id application_step_id type section_title content sort_order',
        'government_id_fees' => 'id government_id_id label type amount_min amount_max currency is_optional notes sort_order',
        'government_id_office' => 'id government_id_id office_id new_application_status renewal_status replacement_status service_notes source_url last_verified_at last_verified_by',
        'document_requirement_sets' => 'id document_id application_type application_type_custom applicant_type applicant_type_custom min_age max_age',
        'document_requirement_groups' => 'id requirement_set_id title rule condition_type condition_custom sort_order',
        'document_requirement_ways' => 'id requirement_group_id required_count qualification_type qualification_scope qualification_custom sort_order',
        'document_requirement_items' => 'id requirement_group_id type government_id_id document_id custom_name submission_format submission_format_custom copies instructions sort_order requirement_way_id quantity',
        'document_application_steps' => 'id requirement_set_id title short_description type sort_order',
        'document_application_step_blocks' => 'id application_step_id type section_title content sort_order',
        'document_fees' => 'id document_id label type amount_min amount_max currency is_optional notes sort_order',
        'document_office' => 'id document_id office_id new_application_status renewal_status replacement_status service_notes source_url last_verified_at last_verified_by',
        'government_id_fee_options' => 'id government_id_fee_id label type amount_min amount_max currency description notes sort_order',
        'document_fee_options' => 'id document_fee_id label type amount_min amount_max currency description notes sort_order',
    ];

    public function capture(): array
    {
        $this->assertDevelopmentEnvironment();

        return DB::transaction(function () {
            $tables = [];
            foreach (self::COLUMNS as $table => $columns) {
                $tables[$table] = DB::table($table)->select(explode(' ', $columns))->orderBy('id')->get()
                    ->map(function ($row) use ($table) {
                        $row = (array) $row;
                        if (array_key_exists('last_verified_by', $row)) {
                            $row['last_verified_by'] = null;
                        }

                        return $this->normalize($table, $row);
                    })->all();
            }
            $data = ['format_version' => 2, 'metadata' => self::DEVELOPMENT_METADATA, 'snapshot_state' => 'pending_export', 'tables' => $tables];
            $this->validate($data);

            return $data;
        });
    }

    public function fingerprint(array $data): string
    {
        return hash('sha256', json_encode($data['tables'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    public function validate(array $data): void
    {
        if (array_diff(array_keys($data), ['format_version', 'metadata', 'snapshot_state', 'tables'])
            || ($data['format_version'] ?? null) !== 2
            || ($data['metadata'] ?? null) !== self::DEVELOPMENT_METADATA
            || ! in_array($data['snapshot_state'] ?? null, ['pending_export', 'exported'], true)
            || ! is_array($data['tables'] ?? null)
            || array_keys($data['tables']) !== array_values(array_diff(array_keys(self::COLUMNS), array_diff(['government_id_fee_options', 'document_fee_options'], array_keys($data['tables']))))) {
            throw new RuntimeException('Invalid reference dataset format or table allowlist.');
        }
        $ids = [];
        $names = [];
        foreach (self::COLUMNS as $table => $columns) {
            // Older snapshots predate child options; their missing optional tables are empty.
            if (!array_key_exists($table, $data['tables']) && in_array($table, ['government_id_fee_options', 'document_fee_options'], true)) continue;
            if (! is_array($data['tables'][$table]) || ! array_is_list($data['tables'][$table])) {
                throw new RuntimeException("Invalid rows: $table");
            }
            foreach ($data['tables'][$table] as $row) {
                if (! is_array($row) || count($row) !== count(explode(' ', $columns))
                    || array_diff(explode(' ', $columns), array_keys($row))
                    || ! is_int($row['id'] ?? null) || $row['id'] < 1 || isset($ids[$table][$row['id']])) {
                    throw new RuntimeException("Invalid columns or duplicate ID: $table");
                }
                if (($row['last_verified_by'] ?? null) !== null) {
                    throw new RuntimeException('User verification references must be null.');
                }
                foreach ($row as $field => $value) {
                    if (is_array($value) && $field !== 'content') {
                        throw new RuntimeException("Invalid value: $table.$field");
                    }
                    if (is_object($value)) {
                        throw new RuntimeException('Objects are not permitted.');
                    }
                }
                if (str_ends_with($table, '_step_blocks') && ! is_array($row['content'])) {
                    throw new RuntimeException('Guide content must be decoded JSON.');
                }
                if (isset($row['name'])) {
                    $name = mb_strtolower(trim($row['name']));
                    if (isset($names[$table][$name])) {
                        throw new RuntimeException("Ambiguous duplicate directory name: $table #{$row['id']}");
                    }
                    $names[$table][$name] = true;
                }
                $ids[$table][$row['id']] = $row;
            }
        }
        foreach ($data['tables'] as $table => $rows) {
            foreach ($rows as $row) {
                foreach ($this->foreignKeys($table) as $field => $target) {
                    $value = $row[$field];
                    if ($value !== null && (! is_int($value) || ! isset($ids[$target][$value]))) {
                        throw new RuntimeException("Missing dataset dependency: $table #{$row['id']} $field");
                    }
                }
                if (str_ends_with($table, '_requirement_items') && $row['requirement_way_id'] !== null) {
                    $wayTable = str_replace('_items', '_ways', $table);
                    if ($ids[$wayTable][$row['requirement_way_id']]['requirement_group_id'] !== $row['requirement_group_id']) {
                        throw new RuntimeException('Requirement way belongs to a different group.');
                    }
                }
            }
        }
    }

    private function foreignKeys(string $table): array
    {
        $keys = [];
        foreach (explode(' ', self::COLUMNS[$table]) as $field) {
            $target = match ($field) {
                'agency_id' => 'agencies', 'government_id_id' => 'government_ids', 'document_id' => 'documents',
                'government_id_fee_id' => 'government_id_fees', 'document_fee_id' => 'document_fees',
                'office_id' => 'offices', 'office_schedule_id' => 'office_schedules',
                'requirement_set_id' => (str_starts_with($table, 'document_') ? 'document' : 'government_id').'_requirement_sets',
                'requirement_group_id' => str_replace('_requirement_items', '_requirement_groups', str_replace('_requirement_ways', '_requirement_groups', $table)),
                'requirement_way_id' => str_replace('_requirement_items', '_requirement_ways', $table),
                'application_step_id' => str_replace('_application_step_blocks', '_application_steps', $table),
                default => null,
            };
            if ($target) {
                $keys[$field] = $target;
            }
        }

        return $keys;
    }

    private function normalize(string $table, array $row): array
    {
        $normalized = [];
        foreach (explode(' ', self::COLUMNS[$table]) as $field) {
            $value = $row[$field];
            if ($field === 'content' && is_string($value)) {
                $value = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            }
            if ($field === 'content' && is_array($value)) {
                $value = $this->canonicalJson($value);
            }
            if ($value !== null && ($field === 'id' || str_ends_with($field, '_id') || in_array($field, ['last_verified_by', 'sort_order', 'day_of_week', 'min_age', 'max_age', 'required_count', 'copies', 'quantity', 'eligibility_min_age', 'eligibility_max_age', 'validity_value', 'processing_time_min', 'processing_time_max']))) {
                $value = (int) $value;
            }
            if ($value !== null && in_array($field, ['fee_min', 'fee_max', 'amount_min', 'amount_max', 'latitude', 'longitude'])) {
                $value = number_format((float) $value, in_array($field, ['latitude', 'longitude']) ? 7 : 2, '.', '');
            }
            if ($field === 'is_optional') {
                $value = (bool) $value;
            }
            $normalized[$field] = $value;
        }

        return $normalized;
    }

    private function canonicalJson(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value);
        }
        foreach ($value as &$child) {
            if (is_array($child)) {
                $child = $this->canonicalJson($child);
            }
        }
        unset($child);

        return $value;
    }

    public function import(array $data): array
    {
        $this->assertDevelopmentEnvironment();
        $this->validate($data);
        if ($data['snapshot_state'] !== 'exported') {
            throw new RuntimeException('Development snapshot has not been exported yet. Explicit export authorization is required.');
        }

        return DB::transaction(function () use ($data) {
            $counts = ['inserted' => 0, 'skipped' => 0];
            foreach ($data['tables'] as $table => $rows) {
                foreach ($rows as $row) {
                    $existing = DB::table($table)->where('id', $row['id'])->lockForUpdate()->first();
                    if ($existing) {
                        if ($this->normalize($table, (array) $existing) !== $this->normalize($table, $row)) {
                            throw new RuntimeException("Conflict: $table #{$row['id']}; local record was preserved.");
                        }
                        $counts['skipped']++;

                        continue;
                    }
                    // Names are not globally unique in the CMS: fail rather than guess a match.
                    if (isset($row['name']) && DB::table($table)->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($row['name']))])->exists()) {
                        throw new RuntimeException("Identity conflict: $table #{$row['id']}; name already exists under another ID.");
                    }
                    $insert = $row;
                    if (array_key_exists('content', $insert)) {
                        $insert['content'] = json_encode($insert['content'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
                    }
                    $insert['created_at'] = now();
                    $insert['updated_at'] = now();
                    DB::table($table)->insert($insert);
                    $counts['inserted']++;
                }
            }

            return $counts;
        });
    }
}
