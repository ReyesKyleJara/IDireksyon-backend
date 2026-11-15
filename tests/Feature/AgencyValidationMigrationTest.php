<?php

use App\Models\Agency;
use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\Office;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Feature 2, Step 1: schema only. These tests use fake data in the temporary
 * in-memory SQLite database. No real agency confirmation is recorded anywhere.
 */

const AGENCY_VERIFICATION_MIGRATION = 'database/migrations/2026_11_13_000000_create_catalog_agency_verifications_table.php';
const AGENCY_STATUS_MIGRATION = 'database/migrations/2026_11_13_000100_add_agency_validation_status_to_catalog_records.php';

/** Keys from the new schema that must never reach residents through the API. */
const PRIVATE_VALIDATION_KEYS = [
    'agency_validation_status', 'verifiable_type', 'verifiable_id',
    'evidence_reference', 'evidence_location', 'contact_role', 'content_snapshot',
    'content_fingerprint', 'fingerprint_version', 'recorded_by', 'recorded_by_name',
    'approved_by', 'approved_by_name', 'review_state', 'returned_reason', 'revocation_reason',
];

function migrationFile(string $path)
{
    return require base_path($path);
}

function apiKeys(mixed $value): array
{
    if (! is_array($value)) {
        return [];
    }
    $keys = [];
    foreach ($value as $key => $child) {
        if (is_string($key)) {
            $keys[] = $key;
        }
        array_push($keys, ...apiKeys($child));
    }

    return $keys;
}

/** A fake verification row for schema tests only. */
function fakeVerificationRow(array $overrides = []): array
{
    $record = GovernmentId::create(['name' => 'Fixture Passport']);
    $user = User::factory()->create(['role' => 'researcher']);

    return array_merge([
        'verifiable_type' => GovernmentId::class,
        'verifiable_id' => $record->id,
        'agency_name' => 'Fixture Agency',
        'method' => 'written_letter',
        'confirmed_aspects' => json_encode(['requirements']),
        'scope' => 'Fixture scope for schema tests only',
        'recorded_by' => $user->id,
        'recorded_by_name' => $user->username ?? 'fixture-researcher',
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides);
}

it('runs only against the temporary in-memory SQLite database', function () {
    expect(DB::connection()->getDriverName())->toBe('sqlite')
        ->and(DB::connection()->getDatabaseName())->toBe(':memory:');
});

it('creates an empty verification table with the approved columns', function () {
    expect(Schema::hasTable('catalog_agency_verifications'))->toBeTrue()
        ->and(DB::table('catalog_agency_verifications')->count())->toBe(0)
        ->and(Schema::hasColumns('catalog_agency_verifications', [
            'verifiable_type', 'verifiable_id', 'agency_id', 'agency_name', 'office_id', 'office_label',
            'method', 'requested_on', 'responded_on', 'outcome', 'confirmed_aspects', 'not_covered_aspects',
            'scope_request_paths', 'scope', 'not_covered_note', 'corrections_summary', 'corrections_applied_at',
            'corrections_applied_by', 'content_snapshot', 'content_fingerprint', 'fingerprint_version',
            'evidence_reference', 'evidence_location', 'contact_role', 'notes',
            'review_state', 'recorded_by', 'recorded_by_name', 'submitted_at', 'returned_at', 'returned_by',
            'returned_reason', 'approved_at', 'approved_by', 'approved_by_name', 'revoked_at', 'revoked_by',
            'revocation_reason', 'created_at', 'updated_at',
        ]))->toBeTrue();
});

it('keeps every index and foreign key name within the MySQL 64-character limit', function () {
    $table = 'catalog_agency_verifications';
    $indexes = collect(Schema::getIndexes($table))->pluck('name');
    $foreignKeys = collect(['agency_id', 'office_id', 'corrections_applied_by', 'recorded_by', 'returned_by', 'approved_by', 'revoked_by'])
        ->map(fn ($column) => "{$table}_{$column}_foreign"); // Laravel's generated names, used by MySQL
    $statusIndexes = collect(['government_ids', 'documents'])->map(fn ($name) => "{$name}_agency_validation_status_index");

    expect($indexes)->toContain('agency_verifications_verifiable_index');
    foreach ($indexes->merge($foreignKeys)->merge($statusIndexes) as $name) {
        expect(strlen($name))->toBeLessThanOrEqual(64, $name);
    }
});

it('starts new verification rows as unapproved pending drafts', function () {
    $id = DB::table('catalog_agency_verifications')->insertGetId(fakeVerificationRow());
    $row = DB::table('catalog_agency_verifications')->find($id);

    expect($row->outcome)->toBe('pending')
        ->and($row->review_state)->toBe('draft')
        ->and($row->approved_by)->toBeNull()
        ->and($row->approved_at)->toBeNull()
        ->and($row->content_fingerprint)->toBeNull();
});

it('requires the recorder and the core content columns', function (string $column) {
    $row = fakeVerificationRow();
    unset($row[$column]);

    expect(fn () => DB::table('catalog_agency_verifications')->insert($row))->toThrow(QueryException::class);
})->with(['recorded_by', 'confirmed_aspects', 'scope', 'agency_name']);

it('stores no UREC clearance reference', function () {
    expect(Schema::hasColumn('catalog_agency_verifications', 'ethics_clearance_reference'))->toBeFalse();
});

it('drops the old clearance column from a database that has it, keeping every row', function () {
    Schema::table('catalog_agency_verifications', fn ($table) => $table->string('ethics_clearance_reference', 100)->default(''));
    DB::table('catalog_agency_verifications')->insert(fakeVerificationRow());
    $before = DB::table('catalog_agency_verifications')->get()->map(fn ($row) => array_diff_key((array) $row, ['ethics_clearance_reference' => 1]));

    $migration = require database_path('migrations/2026_11_15_000000_drop_ethics_clearance_reference_from_catalog_agency_verifications.php');
    $migration->up();
    $migration->up(); // running it again is harmless

    expect(Schema::hasColumn('catalog_agency_verifications', 'ethics_clearance_reference'))->toBeFalse()
        ->and(DB::table('catalog_agency_verifications')->get()->map(fn ($row) => (array) $row))->toEqual($before);
});

it('adds agency_validation_status with needs_review as the default on both record types', function () {
    foreach (['government_ids' => GovernmentId::class, 'documents' => Document::class] as $table => $model) {
        $record = $model::create(['name' => 'Fixture record']);

        expect(Schema::hasColumn($table, 'agency_validation_status'))->toBeTrue($table)
            ->and($record->fresh()->agency_validation_status)->toBe('needs_review', $table);
    }
});

it('keeps existing records unchanged and never marks them confirmed', function () {
    $status = migrationFile(AGENCY_STATUS_MIGRATION);
    $status->down();

    // Existing data, including a saved public reference, before the column exists.
    $passport = GovernmentId::create(['name' => 'Philippine Passport', 'description' => 'Saved description',
        'research_metadata' => ['status' => 'draft', 'hidden_sections' => [], 'sources' => [[
            'title' => 'Public reference: DFA – Passport General Information',
            'url' => 'https://consular.dfa.gov.ph/passport-general-information/',
            'checked_on' => '2026-11-13',
        ]]]]);
    $nbi = Document::create(['name' => 'NBI Clearance', 'description' => 'Saved document']);
    $before = [
        'government_ids' => (array) DB::table('government_ids')->find($passport->id),
        'documents' => (array) DB::table('documents')->find($nbi->id),
    ];

    $status->up();

    foreach ($before as $table => $row) {
        $after = (array) DB::table($table)->find($row['id']);
        expect($after['agency_validation_status'])->toBe('needs_review')
            ->and(array_diff_key($after, ['agency_validation_status' => true]))->toBe($row);
    }
    expect(GovernmentId::where('agency_validation_status', '!=', 'needs_review')->count())->toBe(0)
        ->and(Document::where('agency_validation_status', '!=', 'needs_review')->count())->toBe(0)
        ->and(DB::table('catalog_agency_verifications')->count())->toBe(0);
});

it('does not allow agency_validation_status to be set through mass assignment', function () {
    $record = GovernmentId::create(['name' => 'Fixture record', 'agency_validation_status' => 'agency_confirmed']);
    $document = Document::create(['name' => 'Fixture document', 'agency_validation_status' => 'agency_confirmed']);

    expect($record->fresh()->agency_validation_status)->toBe('needs_review')
        ->and($document->fresh()->agency_validation_status)->toBe('needs_review');
});

it('never exposes the new status or verification data through the public catalog API', function () {
    $row = fakeVerificationRow(['evidence_reference' => 'PRIVATE-EVIDENCE', 'contact_role' => 'PRIVATE-ROLE']);
    DB::table('catalog_agency_verifications')->insert($row);
    $id = GovernmentId::findOrFail($row['verifiable_id']);
    $document = Document::create(['name' => 'Fixture document']);
    Office::create(['name' => 'Fixture office', 'status' => 'active']);

    foreach (['/api/government-ids', '/api/government-ids/'.$id->id, '/api/documents',
        '/api/documents/'.$document->id, '/api/offices'] as $url) {
        $response = $this->getJson($url)->assertOk();
        expect(array_intersect(PRIVATE_VALIDATION_KEYS, apiKeys($response->json())))->toBe([], $url)
            ->and($response->getContent())->not->toContain('PRIVATE-EVIDENCE')
            ->and($response->getContent())->not->toContain('TEST-ONLY');
    }
});

it('protects the audit trail when users or agencies are deleted', function () {
    $agency = Agency::create(['name' => 'Fixture Agency']);
    $row = fakeVerificationRow(['agency_id' => $agency->id]);
    DB::table('catalog_agency_verifications')->insert($row);

    expect(fn () => DB::table('users')->where('id', $row['recorded_by'])->delete())->toThrow(QueryException::class)
        ->and(fn () => DB::table('agencies')->where('id', $agency->id)->delete())->toThrow(QueryException::class)
        ->and(DB::table('catalog_agency_verifications')->count())->toBe(1);
});

it('keeps the office label when an office is deleted', function () {
    $office = Office::create(['name' => 'Fixture office', 'status' => 'active']);
    $id = DB::table('catalog_agency_verifications')->insertGetId(
        fakeVerificationRow(['office_id' => $office->id, 'office_label' => 'Fixture office label']),
    );

    DB::table('offices')->where('id', $office->id)->delete();
    $row = DB::table('catalog_agency_verifications')->find($id);

    expect($row->office_id)->toBeNull()->and($row->office_label)->toBe('Fixture office label');
});

it('rolls both migrations back cleanly and can apply them again', function () {
    $status = migrationFile(AGENCY_STATUS_MIGRATION);
    $verifications = migrationFile(AGENCY_VERIFICATION_MIGRATION);

    $status->down();
    $verifications->down();
    expect(Schema::hasTable('catalog_agency_verifications'))->toBeFalse()
        ->and(Schema::hasColumn('government_ids', 'agency_validation_status'))->toBeFalse()
        ->and(Schema::hasColumn('documents', 'agency_validation_status'))->toBeFalse();

    $verifications->up();
    $status->up();
    expect(Schema::hasTable('catalog_agency_verifications'))->toBeTrue()
        ->and(DB::table('catalog_agency_verifications')->count())->toBe(0)
        ->and(Schema::hasColumn('government_ids', 'agency_validation_status'))->toBeTrue()
        ->and(Schema::hasColumn('documents', 'agency_validation_status'))->toBeTrue();
});
