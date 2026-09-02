<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$basePath = rtrim((string) (getenv('BENCHMARK_BASE_PATH') ?: dirname(__DIR__)), '/');
require $basePath.'/vendor/autoload.php';

$app = require $basePath.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$path = (string) ($argv[1] ?? '/dashboard');
$locale = (string) ($argv[2] ?? 'ar');
$user = User::query()->where('username', 'admin')->firstOrFail();

$request = Request::create($path, 'GET', [], ['locale' => $locale]);
$request->headers->set('Accept', 'text/html');
$app->instance('request', $request);
Auth::setUser($user);
$request->setUserResolver(static fn (): User => $user);
$queries = [];
DB::listen(static function (QueryExecuted $query) use (&$queries): void {
    $queries[] = ['sql' => $query->toRawSql(), 'time_ms' => round($query->time, 3)];
});

$started = hrtime(true);
$response = $kernel->handle($request);
$elapsedMs = (hrtime(true) - $started) / 1_000_000;
$kernel->terminate($request, $response);

$normalized = [];
foreach ($queries as $query) {
    $fingerprint = preg_replace(['/\b\d+(?:\.\d+)?\b/', "/'[^']*'/"], ['?', "'?'"], $query['sql']);
    $normalized[$fingerprint] = ($normalized[$fingerprint] ?? 0) + 1;
}

arsort($normalized);
$duplicates = array_filter($normalized, static fn (int $count): bool => $count > 1);
usort($queries, static fn (array $left, array $right): int => $right['time_ms'] <=> $left['time_ms']);

echo json_encode([
    'path' => $path,
    'locale' => $locale,
    'status' => $response->getStatusCode(),
    'total_ms' => round($elapsedMs, 3),
    'app_ms' => round(max(0, $elapsedMs - array_sum(array_column($queries, 'time_ms'))), 3),
    'query_count' => count($queries),
    'sql_ms' => round(array_sum(array_column($queries, 'time_ms')), 3),
    'response_bytes' => strlen((string) $response->getContent()),
    'error' => $response->getStatusCode() >= 500 ? substr(strip_tags((string) $response->getContent()), 0, 500) : null,
    'slowest_queries' => array_slice($queries, 0, 5),
    'duplicate_queries' => array_slice($duplicates, 0, 10, true),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL;
