<?php

use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\User;
use App\Services\CatalogRecordDeletion;
use App\Services\CatalogResidentReferences;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class);

function mysqlResidentSnapshot(?string $connection = null): array
{
    return collect(['resident_government_ids', 'resident_documents', 'resident_id_preparations',
        'resident_document_preparations', 'resident_journeys', 'resident_journey_completions', 'resident_journey_resolutions'])
        ->mapWithKeys(fn ($table) => [$table => DB::connection($connection)->table($table)->get()->toJson()])->all();
}

function startMysqlReviewWorker(array $input): array
{
    $process = proc_open([PHP_BINARY, base_path('tests/Support/catalog_mysql_worker.php'), json_encode($input)],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), [
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '',
            'SESSION_DRIVER' => 'array', 'CACHE_STORE' => 'array', 'MAIL_MAILER' => 'array',
        ]);
    fclose($pipes[0]);
    stream_set_timeout($pipes[1], 12);
    expect(trim(fgets($pipes[1])))->toBe('ready');

    return [$process, $pipes];
}

function finishMysqlReviewWorker(array $worker): int
{
    [$process, $pipes] = $worker;
    try {
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        expect(stream_get_meta_data($pipes[1])['timed_out'])->toBeFalse();
        $result = json_decode($output, true);
        expect($result)->toHaveKey('status');

        return $result['status'];
    } finally {
        proc_terminate($process);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
    }
}

function expectMysqlLockWait(): void
{
    $until = microtime(true) + 4;
    do {
        $waiting = (int) DB::connection('review_observer')->selectOne('SELECT COUNT(*) AS total FROM performance_schema.data_lock_waits')->total;
        if ($waiting > 0) {
            return;
        }
        usleep(20000);
    } while (microtime(true) < $until);
    test()->fail('The competing MySQL request did not reach an InnoDB lock wait.');
}

function mysqlWaitingTable(): string
{
    expectMysqlLockWait();

    return DB::connection('review_observer')->selectOne('SELECT requested.OBJECT_NAME AS table_name
        FROM performance_schema.data_lock_waits AS waiting
        JOIN performance_schema.data_locks AS requested ON requested.ENGINE_LOCK_ID = waiting.REQUESTING_ENGINE_LOCK_ID
        LIMIT 1')->table_name;
}

function mysqlSaveRequest(string $kind, string $dependency, $entry, $path, $item): array
{
    $inventory = ['government_id_ids' => [], 'document_ids' => []];
    $inventory[$kind === 'government_id' ? 'government_id_ids' : 'document_ids'] = [$entry->id];
    $targets = ['target_government_id_ids' => [], 'target_document_ids' => []];
    $targets['target_'.$kind.'_ids'] = [$entry->id];

    return match ($dependency) {
        'inventory' => ['url' => '/api/auth/inventory', 'method' => 'PUT', 'payload' => $inventory],
        'preparation' => ['url' => '/api/auth/requirement-preparation/'.$kind.'/'.$entry->id, 'method' => 'PUT', 'payload' => ['item_id' => $item->id, 'prepared' => false]],
        'journey' => ['url' => '/api/auth/journeys', 'method' => 'POST', 'payload' => $targets],
        'resolution' => ['url' => '/api/auth/roadmap-plan', 'method' => 'POST', 'payload' => [...$targets, 'resolution' => [
            'key' => $kind.':'.$entry->id.':path', 'set_id' => $path->id,
        ]]],
    };
}

beforeEach(function () {
    $socket = getenv('IDIREKSYON_TEST_MYSQL_SOCKET');
    if (! $socket) {
        $this->markTestSkipped('An explicitly isolated MySQL review socket is required.');
    }
    if (! preg_match('#^/private/tmp/idireksyon-mysql-review\.[A-Za-z0-9]+/mysql\.sock$#', $socket)) {
        throw new RuntimeException('Refusing a non-disposable MySQL socket.');
    }
    $this->reviewSocket = $socket;
    $this->reviewDatabase = 'catalog_review_'.bin2hex(random_bytes(8));
    $connection = [
        'driver' => 'mysql', 'url' => null, 'host' => '127.0.0.1', 'port' => 0, 'unix_socket' => $socket,
        'database' => null, 'username' => 'root', 'password' => '', 'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true,
    ];
    config(['database.connections.review_observer' => $connection]);
    $server = DB::connection('review_observer')->selectOne('SELECT @@datadir AS directory, @@skip_networking AS isolated, VERSION() AS version');
    expect(rtrim($server->directory, '/'))->toBe(dirname($socket).'/mysql-data');
    expect((int) $server->isolated)->toBe(1);
    expect($server->version)->not->toContain('MariaDB');
    DB::connection('review_observer')->statement('CREATE DATABASE `'.$this->reviewDatabase.'`');
    config(['database.default' => 'review_mysql', 'database.connections.review_mysql' => [...$connection, 'database' => $this->reviewDatabase]]);
    config(['database.connections.review_current' => [...$connection, 'database' => $this->reviewDatabase]]);
    $this->artisan('migrate', ['--database' => 'review_mysql', '--force' => true])->assertSuccessful();
    $this->reviewStaff = User::factory()->create(['role' => 'super_admin']);
    $this->reviewResident = User::factory()->create();
});

afterEach(function () {
    if (isset($this->reviewDatabase)) {
        DB::purge('review_mysql');
        DB::purge('review_current');
        DB::connection('review_observer')->statement('DROP DATABASE `'.$this->reviewDatabase.'`');
        DB::purge('review_observer');
        config(['database.default' => 'sqlite']);
    }
});

test('MySQL resident save wins and blocked deletion preserves every resident table', function ($model, $kind, $dependency) {
    $entry = $model::create(['name' => 'Synthetic concurrency entry']);
    $path = $entry->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'all']);
    $item = $path->groups()->create(['rule' => 'all'])->items()->create(['type' => 'custom', 'custom_name' => 'Form']);
    if ($dependency === 'resolution') {
        $entry->requirementSets()->create(['application_type' => 'renewal', 'applicant_type' => 'all']);
    }
    $request = mysqlSaveRequest($kind, $dependency, $entry, $path, $item);
    $worker = null;
    CatalogResidentReferences::transaction(function () use ($entry, $kind, $request, &$worker, &$before) {
        $this->actingAs($this->reviewResident)->json($request['method'], $request['url'], $request['payload'])->assertSuccessful();
        $before = mysqlResidentSnapshot();
        $worker = startMysqlReviewWorker([
            'socket' => $this->reviewSocket, 'database' => $this->reviewDatabase, 'user' => $this->reviewStaff->id,
            'url' => '/admin/'.($kind === 'government_id' ? 'government-ids' : 'documents').'/'.$entry->id,
            'method' => 'DELETE', 'payload' => [],
        ]);
        expectMysqlLockWait();
    });
    expect(finishMysqlReviewWorker($worker))->toBe(422);
    expect(mysqlResidentSnapshot())->toBe($before);
    $this->assertModelExists($entry);
})->with([[GovernmentId::class, 'government_id'], [Document::class, 'document']])->with(['inventory', 'preparation', 'journey', 'resolution']);

test('MySQL deletion wins and stale resident saves cannot create dangling references', function ($model, $kind, $dependency) {
    $entry = $model::create(['name' => 'Synthetic stale entry']);
    $path = $entry->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'all']);
    $item = $path->groups()->create(['rule' => 'all'])->items()->create(['type' => 'custom', 'custom_name' => 'Form']);
    if ($dependency === 'resolution') {
        $entry->requirementSets()->create(['application_type' => 'renewal', 'applicant_type' => 'all']);
    }
    $request = mysqlSaveRequest($kind, $dependency, $entry, $path, $item);
    $before = mysqlResidentSnapshot();
    $worker = null;
    CatalogResidentReferences::transaction(function () use ($entry, $request, &$worker) {
        app(CatalogRecordDeletion::class)->delete($entry);
        $worker = startMysqlReviewWorker([
            'socket' => $this->reviewSocket, 'database' => $this->reviewDatabase, 'user' => $this->reviewResident->id, ...$request,
        ]);
        expectMysqlLockWait();
    }, exclusive: true);
    expect(finishMysqlReviewWorker($worker))->toBeIn([404, 422]);
    expect(mysqlResidentSnapshot())->toBe($before);
    $this->assertModelMissing($entry);
})->with([[GovernmentId::class, 'government_id'], [Document::class, 'document']])->with(['inventory', 'preparation', 'journey', 'resolution']);

test('MySQL deletion sees committed dependencies even inside an older repeatable-read snapshot', function ($model, $kind, $dependency) {
    $entry = $model::create(['name' => 'Synthetic snapshot entry']);
    $path = $entry->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'all']);
    $item = $path->groups()->create(['rule' => 'all'])->items()->create(['type' => 'custom', 'custom_name' => 'Form']);
    if ($dependency === 'resolution') {
        $entry->requirementSets()->create(['application_type' => 'renewal', 'applicant_type' => 'all']);
    }
    $request = mysqlSaveRequest($kind, $dependency, $entry, $path, $item);
    DB::transaction(function () use ($entry, $request) {
        // A consistent read predating the resident commit is a real MySQL hazard.
        DB::table('resident_journeys')->count();
        $worker = startMysqlReviewWorker([
            'socket' => $this->reviewSocket, 'database' => $this->reviewDatabase, 'user' => $this->reviewResident->id, ...$request,
        ]);
        expect(finishMysqlReviewWorker($worker))->toBeIn([200, 201]);
        $before = mysqlResidentSnapshot('review_current');
        expect(fn () => app(CatalogRecordDeletion::class)->delete($entry))->toThrow(ValidationException::class);
        expect(mysqlResidentSnapshot('review_current'))->toBe($before);
    });
    $this->assertModelExists($entry);
})->with([[GovernmentId::class, 'government_id'], [Document::class, 'document']])->with(['inventory', 'preparation', 'journey', 'resolution']);

test('MySQL completion holds catalog protection before locking its saved journey', function () {
    $entry = GovernmentId::create(['name' => 'Synthetic completion entry']);
    $path = $entry->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'all']);
    $group = $path->groups()->create(['rule' => 'choose_one']);
    $way = $group->ways()->create(['required_count' => 1]);
    foreach (['Manual form A', 'Manual form B'] as $name) {
        $group->items()->make(['type' => 'custom', 'custom_name' => $name])
            ->forceFill(['requirement_way_id' => $way->id])->save();
    }
    $reference = Document::create(['name' => 'Reference-only proof', 'guidance_mode' => 'informational_only']);
    $group->items()->make(['type' => 'document', 'document_id' => $reference->id])
        ->forceFill(['requirement_way_id' => $way->id])->save();
    $this->actingAs($this->reviewResident);
    $journey = $this->postJson('/api/auth/journeys', ['target_government_id_ids' => [$entry->id]])->assertCreated()->json('data');
    $identity = collect($journey['plan']['steps'])->firstWhere('type', 'unresolved_requirement')['resolution_key'];
    $worker = null;
    $waitingTable = null;
    DB::listen(function ($query) use ($entry, &$worker, &$waitingTable) {
        if ($worker === null && str_contains($query->sql, 'from `resident_journeys`') && str_contains($query->sql, 'for update')) {
            $worker = startMysqlReviewWorker([
                'socket' => $this->reviewSocket, 'database' => $this->reviewDatabase, 'user' => $this->reviewStaff->id,
                'url' => '/admin/government-ids/'.$entry->id, 'method' => 'DELETE', 'payload' => [],
            ]);
            $waitingTable = mysqlWaitingTable();
            // Capture the lock order, then stop this competing request before any deadlock.
            proc_terminate($worker[0]);
        }
    });
    $this->putJson('/api/auth/journeys/'.$journey['id'].'/completion', ['step_identity' => $identity])->assertOk();
    expect($waitingTable)->toBeIn(['government_ids', 'documents']);
    foreach (array_slice($worker[1], 1) as $pipe) {
        fclose($pipe);
    }
    proc_close($worker[0]);
    expect(DB::table('resident_journey_completions')->count())->toBe(1);
});
