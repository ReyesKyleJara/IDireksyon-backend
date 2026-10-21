<?php

namespace Tests\Feature;

use App\Services\ReferenceData;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Explicit PHPUnit lifecycle: no RefreshDatabase/migrate:fresh, even in isolation. */
class ReferenceDataTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'database.connections.sqlite.url' => null]);
        DB::purge('sqlite');
        $this->artisan('migrate', ['--database' => 'sqlite', '--force' => true])->assertSuccessful();
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');
        parent::tearDown();
    }

    private function fixture(): array
    {
        DB::table('agencies')->insert(['id' => 1, 'name' => 'Reference Agency']);
        DB::table('government_ids')->insert(['id' => 1, 'name' => 'Reference ID', 'agency_id' => 1]);
        DB::table('government_id_requirement_sets')->insert(['id' => 10, 'government_id_id' => 1, 'application_type' => 'new', 'applicant_type' => 'all']);
        DB::table('government_id_requirement_groups')->insert(['id' => 20, 'requirement_set_id' => 10, 'rule' => 'all', 'sort_order' => 2]);
        DB::table('government_id_requirement_ways')->insert(['id' => 30, 'requirement_group_id' => 20, 'sort_order' => 3]);
        DB::table('government_id_requirement_items')->insert(['id' => 40, 'requirement_group_id' => 20, 'requirement_way_id' => 30, 'type' => 'custom', 'custom_name' => 'Public requirement', 'sort_order' => 4]);
        DB::table('government_id_application_steps')->insert(['id' => 50, 'requirement_set_id' => 10, 'title' => 'Prepare requirements', 'sort_order' => 5]);
        DB::table('government_id_application_step_blocks')->insert(['id' => 60, 'application_step_id' => 50, 'type' => 'custom', 'content' => json_encode(['body' => 'Public instructions']), 'sort_order' => 6]);
        DB::table('government_id_fees')->insert(['id' => 70, 'government_id_id' => 1, 'label' => 'Public fee', 'amount_min' => '25.50', 'sort_order' => 7]);
        DB::table('users')->insert(['id' => 900, 'name' => 'PRIVATE PERSON', 'email' => 'private@example.test', 'password' => 'PRIVATE HASH']);
        $agency = DB::table('agencies')->value('id');
        $id = DB::table('government_ids')->value('id');
        DB::table('government_ids')->where('id', $id)->update(['last_verified_by' => 900]);
        DB::table('documents')->insert(['id' => 50, 'name' => 'Reference Document', 'agency_id' => $agency]);
        DB::table('offices')->insert(['id' => 60, 'name' => 'Reference Office', 'agency_id' => $agency, 'last_verified_by' => 900]);
        DB::table('office_schedules')->insert(['id' => 70, 'office_id' => 60, 'day_of_week' => 1, 'status' => 'open']);
        DB::table('office_schedule_intervals')->insert(['id' => 80, 'office_schedule_id' => 70, 'opens_at' => '08:00:00', 'closes_at' => '17:00:00']);
        foreach (['government_id' => $id, 'document' => 50] as $prefix => $parent) {
            DB::table($prefix.'_office')->insert(['id' => 90, $prefix.'_id' => $parent, 'office_id' => 60, 'last_verified_by' => 900]);
        }
        DB::table('document_requirement_sets')->insert(['id' => 100, 'document_id' => 50, 'application_type' => 'new', 'applicant_type' => 'all']);
        DB::table('document_requirement_groups')->insert(['id' => 110, 'requirement_set_id' => 100, 'rule' => 'all', 'sort_order' => 3]);
        DB::table('document_requirement_ways')->insert(['id' => 120, 'requirement_group_id' => 110, 'sort_order' => 4]);
        DB::table('document_requirement_items')->insert(['id' => 130, 'requirement_group_id' => 110, 'requirement_way_id' => 120, 'type' => 'government_id', 'government_id_id' => $id, 'sort_order' => 5]);
        DB::table('government_id_requirement_items')->where('id', DB::table('government_id_requirement_items')->value('id'))->update(['type' => 'document', 'document_id' => 50]);
        DB::table('document_application_steps')->insert(['id' => 140, 'requirement_set_id' => 100, 'title' => 'Apply', 'sort_order' => 6]);
        DB::table('document_application_step_blocks')->insert(['id' => 150, 'application_step_id' => 140, 'type' => 'text', 'content' => json_encode(['body' => 'Public guidance', 'intro' => ['z' => 'last', 'a' => 'first']]), 'sort_order' => 7]);
        DB::table('document_fees')->insert(['id' => 160, 'document_id' => 50, 'label' => 'Fee', 'amount_min' => '12.30', 'sort_order' => 8]);
        $data = app(ReferenceData::class)->capture();
        $data['snapshot_state'] = 'exported';

        return $data;
    }

    private function newIsolatedDatabase(): void
    {
        DB::purge('sqlite');
        $this->artisan('migrate', ['--database' => 'sqlite', '--force' => true])->assertSuccessful();
    }

    public function test_round_trip_preserves_all_reference_content_and_repeated_import_is_safe(): void
    {
        $data = $this->fixture();
        $this->assertStringNotContainsString('PRIVATE', json_encode($data));
        $this->assertStringNotContainsString('private@example', json_encode($data));
        $this->newIsolatedDatabase();
        DB::table('users')->insert(['id' => 901, 'name' => 'Teammate', 'email' => 'teammate@example.test', 'password' => 'local']);
        DB::table('agencies')->insert(['id' => 9999, 'name' => 'Local extra agency']);
        $service = app(ReferenceData::class);
        $count = array_sum(array_map('count', $data['tables']));
        $this->assertSame(['inserted' => $count, 'skipped' => 0], $service->import($data));
        $this->assertSame(['inserted' => 0, 'skipped' => $count], $service->import($data));
        $roundTrip = $service->capture();
        $roundTrip['tables']['agencies'] = array_values(array_filter($roundTrip['tables']['agencies'], fn ($row) => $row['id'] !== 9999));
        $this->assertSame($data['tables'], $roundTrip['tables']);
        $this->assertSame('Teammate', DB::table('users')->value('name'));
        $this->assertSame(1, DB::table('users')->count());
        $this->assertSame(0, DB::table('content_change_logs')->count());
    }

    public function test_mysql_json_key_reordering_does_not_create_a_false_conflict(): void
    {
        $data = $this->fixture();
        $this->newIsolatedDatabase();
        $service = app(ReferenceData::class);
        $service->import($data);
        DB::table('document_application_step_blocks')->where('id', 150)->update([
            'content' => json_encode(['intro' => ['a' => 'first', 'z' => 'last'], 'body' => 'Public guidance']),
        ]);
        $this->assertSame(0, $service->import($data)['inserted']);
    }

    public function test_duplicate_ids_and_cross_group_ways_are_rejected(): void
    {
        $data = $this->fixture();
        $data['tables']['agencies'][] = $data['tables']['agencies'][0];
        try {
            app(ReferenceData::class)->validate($data);
            $this->fail('Expected duplicate ID');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('duplicate ID', $e->getMessage());
        }
        $data = app(ReferenceData::class)->capture();
        $data['tables']['document_requirement_groups'][] = [...$data['tables']['document_requirement_groups'][0], 'id' => 111];
        $data['tables']['document_requirement_ways'][0]['requirement_group_id'] = 111;
        $this->expectExceptionMessage('different group');
        app(ReferenceData::class)->validate($data);
    }

    public function test_late_conflict_rolls_back_prior_inserts_and_preserves_local_edit(): void
    {
        $data = $this->fixture();
        $this->newIsolatedDatabase();
        DB::table('offices')->insert(['id' => 60, 'name' => 'Local edit']);
        try {
            app(ReferenceData::class)->import($data);
            $this->fail('Expected conflict');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Conflict: offices #60', $exception->getMessage());
        }
        $this->assertSame(0, DB::table('agencies')->count());
        $this->assertSame(0, DB::table('government_ids')->count());
        $this->assertSame('Local edit', DB::table('offices')->value('name'));
    }

    public function test_duplicate_name_under_another_id_is_not_inserted(): void
    {
        $data = $this->fixture();
        $this->newIsolatedDatabase();
        DB::table('agencies')->insert(['id' => 999, 'name' => $data['tables']['agencies'][0]['name']]);
        $this->expectExceptionMessage('Identity conflict');
        app(ReferenceData::class)->import($data);
    }

    public function test_pending_development_snapshot_is_rejected(): void
    {
        $data = app(ReferenceData::class)->capture();
        $this->expectExceptionMessage('Development snapshot has not been exported yet');
        app(ReferenceData::class)->import($data);
    }

    public function test_private_tables_and_columns_are_rejected(): void
    {
        $data = $this->fixture();
        $data['tables']['users'] = [];
        try {
            app(ReferenceData::class)->import($data);
            $this->fail('Expected invalid table');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('allowlist', $e->getMessage());
        }
        unset($data['tables']['users']);
        $data['tables']['agencies'][0]['password'] = 'secret';
        $this->expectExceptionMessage('Invalid columns');
        app(ReferenceData::class)->import($data);
    }

    public function test_missing_dependencies_and_user_references_are_rejected(): void
    {
        $data = $this->fixture();
        $data['tables']['documents'] = [];
        try {
            app(ReferenceData::class)->validate($data);
            $this->fail('Expected missing dependency');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Missing dataset dependency', $e->getMessage());
        }
        $data = $this->fixtureWithoutReseeding();
        $data['tables']['offices'][0]['last_verified_by'] = 900;
        $this->expectExceptionMessage('User verification references must be null');
        app(ReferenceData::class)->validate($data);
    }

    private function fixtureWithoutReseeding(): array
    {
        return app(ReferenceData::class)->capture();
    }

    public function test_development_metadata_is_explicit_and_cannot_be_promoted_by_editing_flags(): void
    {
        $data = $this->fixture();
        $this->assertSame(2, $data['format_version']);
        $this->assertSame('development_only', $data['metadata']['purpose']);
        $this->assertSame('unverified', $data['metadata']['verification_status']);
        $this->assertFalse($data['metadata']['production_approved']);
        $this->assertStringContainsString('DEVELOPMENT ONLY', $data['metadata']['warning']);
        $this->assertArrayNotHasKey('approved', $data);
        foreach (['purpose' => 'production', 'verification_status' => 'verified', 'production_approved' => true] as $key => $value) {
            $modified = $data;
            $modified['metadata'][$key] = $value;
            try {
                app(ReferenceData::class)->validate($modified);
                $this->fail('Metadata promotion must be rejected');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('Invalid reference dataset', $e->getMessage());
            }
        }
    }

    public function test_legacy_approved_format_is_rejected_and_pending_placeholder_matches_new_format(): void
    {
        $references = app(ReferenceData::class);
        $placeholder = $references->capture();
        $references->validate($placeholder);
        $this->assertSame(ReferenceData::DEVELOPMENT_METADATA, $placeholder['metadata']);
        $this->assertSame('pending_export', $placeholder['snapshot_state']);
        $this->assertSame(0, array_sum(array_map('count', $placeholder['tables'])));
        $legacy = ['format_version' => 1, 'approved' => true, 'tables' => $placeholder['tables']];
        $this->expectExceptionMessage('Invalid reference dataset');
        $references->import($legacy);
    }

    public function test_import_and_seeder_are_blocked_outside_local_and_testing_even_with_force(): void
    {
        $data = $this->fixture();
        $this->newIsolatedDatabase();
        foreach (['production', 'staging', 'unknown'] as $environment) {
            $this->app->instance('env', $environment);
            try {
                app(ReferenceData::class)->import($data);
                $this->fail('Non-development import must be rejected');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('restricted to local/testing', $e->getMessage());
            }
            try {
                (new ReferenceDataSeeder)->run();
                $this->fail('Seeder must reject before reading the snapshot file');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('restricted to local/testing', $e->getMessage());
            }
        }
        $this->app->instance('env', 'production');
        try {
            $this->artisan('db:seed', ['--class' => 'ReferenceDataSeeder', '--force' => true])->run();
            $this->fail('--force must not bypass the environment restriction');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('restricted to local/testing', $e->getMessage());
        } finally {
            $this->app->instance('env', 'testing');
        }
        $this->assertSame(0, DB::table('government_ids')->count());
        $this->assertSame(0, DB::table('agencies')->count());
    }

    public function test_local_development_import_preserves_both_passport_records_and_links(): void
    {
        $data = $this->fixture();
        $template = $data['tables']['government_ids'][0];
        $data['tables']['government_ids'][] = [...$template, 'id' => 36, 'name' => 'Philippine Passport (ePassport)'];
        $data['tables']['government_ids'][] = [...$template, 'id' => 50, 'name' => 'Philippine Passport'];
        $data['tables']['document_requirement_items'][0]['government_id_id'] = 36;
        $this->newIsolatedDatabase();
        $this->app->instance('env', 'local');
        try {
            app(ReferenceData::class)->import($data);
            $this->assertSame('Philippine Passport (ePassport)', DB::table('government_ids')->where('id', 36)->value('name'));
            $this->assertSame('Philippine Passport', DB::table('government_ids')->where('id', 50)->value('name'));
            $this->assertSame(36, DB::table('document_requirement_items')->where('id', 130)->value('government_id_id'));
            $this->assertSame(0, app(ReferenceData::class)->import($data)['inserted']);
        } finally {
            $this->app->instance('env', 'testing');
        }
    }

    public function test_export_requires_development_authorization_and_blocks_production(): void
    {
        $data = $this->fixture();
        $path = tempnam(sys_get_temp_dir(), 'development-reference-test-');
        file_put_contents($path, 'unchanged');
        try {
            $this->artisan('idireksyon:export-reference-data', [
                '--write' => true, '--approve-hash' => app(ReferenceData::class)->fingerprint($data), '--output' => $path,
            ])->expectsOutputToContain('Writing requires --development')->assertFailed();
            $this->app->instance('env', 'production');
            $this->artisan('idireksyon:export-reference-data', [
                '--write' => true, '--development' => true, '--approve-hash' => app(ReferenceData::class)->fingerprint($data), '--output' => $path,
            ])->expectsOutputToContain('restricted to local/testing')->assertFailed();
            $this->assertSame('unchanged', file_get_contents($path));
        } finally {
            $this->app->instance('env', 'testing');
            unlink($path);
        }
    }

    public function test_export_preview_and_replacement_confirmation(): void
    {
        $data = $this->fixture();
        $path = tempnam(sys_get_temp_dir(), 'reference-test-');
        try {
            file_put_contents($path, 'original');
            $this->artisan('idireksyon:export-reference-data', ['--output' => $path])->expectsOutputToContain('PREVIEW ONLY')->assertSuccessful();
            $this->assertSame('original', file_get_contents($path));
            $this->artisan('idireksyon:export-reference-data', ['--write' => true, '--development' => true, '--approve-hash' => 'wrong', '--output' => $path])->assertFailed();
            $hash = app(ReferenceData::class)->fingerprint($data);
            $question = 'Replace the existing DEVELOPMENT ONLY snapshot at '.$path.'?';
            $this->artisan('idireksyon:export-reference-data', ['--write' => true, '--development' => true, '--approve-hash' => $hash, '--output' => $path])->expectsConfirmation($question, 'no')->assertFailed();
            $this->assertSame('original', file_get_contents($path));
            $this->artisan('idireksyon:export-reference-data', ['--write' => true, '--development' => true, '--approve-hash' => $hash, '--output' => $path])->expectsConfirmation($question, 'yes')->assertSuccessful();
            $this->assertSame($data, json_decode(file_get_contents($path), true));
        } finally {
            unlink($path);
        }
    }
}
