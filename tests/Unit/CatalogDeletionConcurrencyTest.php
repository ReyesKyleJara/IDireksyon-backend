<?php

use App\Models\GovernmentId;
use App\Models\User;
use App\Services\CatalogRecordDeletion;
use App\Services\CatalogResidentReferences;
use App\Services\RoadmapPlanner;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class);

/** A separate PHP process gives this test a genuinely independent connection. */
function concurrentCatalogOperation(string $database, int $record, int $resident, int $set, string $action): string
{
    $script = <<<'PHP'
    require $argv[1].'/vendor/autoload.php';
    $app = require $argv[1].'/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    config(['database.default' => 'sqlite', 'database.connections.sqlite' => [
        'driver' => 'sqlite', 'database' => $argv[2], 'prefix' => '',
        'foreign_key_constraints' => true, 'busy_timeout' => 50,
    ]]);
    Illuminate\Support\Facades\DB::purge('sqlite');
    try {
        if ($argv[6] === 'delete') {
            app(App\Services\CatalogRecordDeletion::class)->delete(App\Models\GovernmentId::findOrFail($argv[3]));
            echo 'deleted';
        } else {
            app(App\Services\RoadmapPlanner::class)->build(App\Models\User::findOrFail($argv[4]), [(int) $argv[3]], [], [
                'key' => 'government_id:'.$argv[3].':path', 'set_id' => (int) $argv[5],
            ]);
            echo 'saved';
        }
    } catch (Illuminate\Validation\ValidationException $exception) {
        echo 'blocked';
    } catch (Illuminate\Database\QueryException $exception) {
        echo str_contains($exception->getMessage(), 'locked') ? 'contended' : 'unexpected database error';
    } catch (PDOException $exception) {
        // SQLite may report contention at COMMIT rather than at the write.
        echo str_contains($exception->getMessage(), 'locked') ? 'contended' : 'unexpected commit error';
    } catch (Throwable $exception) {
        echo 'unexpected '.get_class($exception);
    }
    PHP;
    $process = proc_open([PHP_BINARY, '-r', $script, base_path(), $database, (string) $record, (string) $resident, (string) $set, $action],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), [
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $database,
            'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'MAIL_MAILER' => 'array',
        ]);
    if (! is_resource($process)) {
        throw new RuntimeException('Could not start isolated database worker.');
    }
    fclose($pipes[0]);
    stream_set_timeout($pipes[1], 5);
    try {
        $result = stream_get_contents($pipes[1]);
        if (stream_get_meta_data($pipes[1])['timed_out']) {
            throw new RuntimeException('Isolated database worker timed out.');
        }

        return $result;
    } finally {
        proc_terminate($process);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
    }
}

beforeEach(function () {
    if (! function_exists('proc_open')) {
        $this->markTestSkipped('Independent database worker requires proc_open.');
    }
    $this->scratchDatabase = tempnam(sys_get_temp_dir(), 'catalog-deletion-test-');
    $this->originalConnection = config('database.default');
    config(['database.default' => 'catalog_deletion_test', 'database.connections.catalog_deletion_test' => [
        'driver' => 'sqlite', 'database' => $this->scratchDatabase, 'prefix' => '',
        'foreign_key_constraints' => true, 'busy_timeout' => 50,
    ]]);
    // Only this newly created disposable database receives test schema migrations.
    $this->artisan('migrate', ['--database' => 'catalog_deletion_test', '--force' => true])->assertExitCode(0);
    $this->entry = GovernmentId::create(['name' => 'Concurrent target']);
    $this->resident = User::factory()->create();
    $this->path = $this->entry->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'all']);
    $this->entry->requirementSets()->create(['application_type' => 'renewal', 'applicant_type' => 'all']);
});

afterEach(function () {
    if (isset($this->scratchDatabase)) {
        DB::purge('catalog_deletion_test');
        config(['database.default' => $this->originalConnection]);
        @unlink($this->scratchDatabase);
        @unlink($this->scratchDatabase.'-journal');
        @unlink($this->scratchDatabase.'-wal');
        @unlink($this->scratchDatabase.'-shm');
    }
});

test('a concurrent deletion cannot destroy an uncommitted resident resolution', function () {
    CatalogResidentReferences::transaction(function () {
        app(RoadmapPlanner::class)->build($this->resident, [$this->entry->id], [], [
            'key' => 'government_id:'.$this->entry->id.':path', 'set_id' => $this->path->id,
        ]);
        expect(concurrentCatalogOperation($this->scratchDatabase, $this->entry->id, $this->resident->id, $this->path->id, 'delete'))
            ->toBe('contended');
        $this->assertModelExists($this->entry);
    });

    expect(DB::table('resident_journey_resolutions')->count())->toBe(1);
    expect(fn () => app(CatalogRecordDeletion::class)->delete($this->entry))->toThrow(ValidationException::class);
    $this->assertModelExists($this->entry);
});

test('a concurrent resolution cannot be saved after deletion wins the transaction', function () {
    CatalogResidentReferences::transaction(function () {
        expect(concurrentCatalogOperation($this->scratchDatabase, $this->entry->id, $this->resident->id, $this->path->id, 'save'))
            ->toBe('contended');
        app(CatalogRecordDeletion::class)->delete($this->entry);
    }, exclusive: true);

    expect(concurrentCatalogOperation($this->scratchDatabase, $this->entry->id, $this->resident->id, $this->path->id, 'save'))
        ->toBe('blocked');
    $this->assertModelMissing($this->entry);
    expect(DB::table('resident_journey_resolutions')->count())->toBe(0);
});
