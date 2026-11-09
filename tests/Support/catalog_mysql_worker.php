<?php

// This worker accepts only a disposable review instance, never application DB credentials.
require dirname(__DIR__, 2).'/vendor/autoload.php';

use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$input = json_decode($argv[1], true, 512, JSON_THROW_ON_ERROR);
if (! preg_match('#^/private/tmp/idireksyon-mysql-review\.[A-Za-z0-9]+/mysql\.sock$#', $input['socket'])
    || ! preg_match('/^catalog_review_[a-f0-9]+$/', $input['database'])) {
    throw new RuntimeException('Only a disposable catalog review instance is permitted.');
}
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['database.default' => 'review_mysql', 'database.connections.review_mysql' => [
    'driver' => 'mysql', 'url' => null, 'host' => '127.0.0.1', 'port' => 0,
    'unix_socket' => $input['socket'], 'database' => $input['database'],
    'username' => 'root', 'password' => '', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '', 'strict' => true,
], 'session.driver' => 'array', 'cache.default' => 'array']);
DB::statement('SET SESSION innodb_lock_wait_timeout = 8');
$user = User::findOrFail($input['user']);
Auth::guard('web')->setUser($user);
Auth::guard('sanctum')->setUser($user);
$request = Request::create($input['url'], $input['method'], [], [], [], [
    'HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json',
], json_encode($input['payload']));
echo "ready\n";
flush();
try {
    $kernel = $app->make(Kernel::class);
    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);
    echo json_encode(['status' => $response->getStatusCode()]);
} catch (Throwable $exception) {
    echo json_encode(['exception' => get_class($exception)]);
}
