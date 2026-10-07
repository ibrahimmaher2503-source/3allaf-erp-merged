<?php

declare(strict_types=1);
use App\Models\User;

$root = dirname(__DIR__);
$requiredEnvironment = [
    'APP_ENV' => 'testing',
    'APP_LOCALE' => 'en',
    'DB_CONNECTION' => 'mysql',
    'DB_HOST' => '127.0.0.1',
    'DB_PORT' => '3306',
    'DB_URL' => '',
    'DB_SOCKET' => '',
    'DB_USERNAME' => 'root',
    'DB_PASSWORD' => '',
];

foreach ($requiredEnvironment as $name => $expectedValue) {
    if (getenv($name) !== $expectedValue) {
        throw new RuntimeException("Unsafe PHPUnit environment: {$name} must be '{$expectedValue}'.");
    }
}

$database = getenv('DB_DATABASE');

if (! is_string($database) || preg_match('/\Aqa_rajh_m1_[a-z0-9_]+_20261007\z/', $database) !== 1) {
    throw new RuntimeException('Unsafe PHPUnit database: DB_DATABASE must match qa_rajh_m1_<domain>_20261007.');
}

$tempPath = realpath(sys_get_temp_dir());
$storagePath = getenv('QA_STORAGE_PATH');

if (! is_string($storagePath) || $storagePath === '') {
    $storagePath = $tempPath.DIRECTORY_SEPARATOR.'rajeh-erp-qa'.DIRECTORY_SEPARATOR.$database;
}

if (basename($storagePath) !== $database && ! str_ends_with(strtolower($storagePath), strtolower(DIRECTORY_SEPARATOR.$database))) {
    throw new RuntimeException('Unsafe PHPUnit storage: QA_STORAGE_PATH must end with the selected QA database name.');
}

$normalizedTempPath = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) $tempPath), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
$normalizedStoragePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $storagePath);

if (! str_starts_with(strtolower($normalizedStoragePath.DIRECTORY_SEPARATOR), strtolower($normalizedTempPath))) {
    throw new RuntimeException('Unsafe PHPUnit storage: QA_STORAGE_PATH must be under the system temporary directory.');
}

if (! is_dir($storagePath) && ! mkdir($storagePath, 0777, true) && ! is_dir($storagePath)) {
    throw new RuntimeException('Unable to create the isolated PHPUnit storage directory.');
}

$resolvedStoragePath = realpath($storagePath);
$tempPrefix = rtrim((string) $tempPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

if (! is_string($resolvedStoragePath) || $resolvedStoragePath === $tempPath || ! str_starts_with(strtolower($resolvedStoragePath.DIRECTORY_SEPARATOR), strtolower($tempPrefix))) {
    throw new RuntimeException('Unsafe PHPUnit storage: QA_STORAGE_PATH must be a child of the system temporary directory.');
}

foreach (['app/private', 'app/public', 'bootstrap/cache', 'framework/cache/data', 'framework/sessions', 'framework/testing', 'framework/views', 'logs'] as $directory) {
    $path = $resolvedStoragePath.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $directory);

    if (! is_dir($path) && ! mkdir($path, 0777, true) && ! is_dir($path)) {
        throw new RuntimeException('Unable to create an isolated PHPUnit storage directory.');
    }
}

$setEnvironment = static function (string $name, string $value): void {
    putenv("{$name}={$value}");
    $_ENV[$name] = $value;
    $_SERVER[$name] = $value;
};

$setEnvironment('QA_STORAGE_PATH', $resolvedStoragePath);
$setEnvironment('LARAVEL_STORAGE_PATH', $resolvedStoragePath);

$cachePath = $resolvedStoragePath.DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'cache';

foreach ([
    'APP_CONFIG_CACHE' => 'config.php',
    'APP_ROUTES_CACHE' => 'routes-v7.php',
    'APP_EVENTS_CACHE' => 'events.php',
    'APP_SERVICES_CACHE' => 'services.php',
    'APP_PACKAGES_CACHE' => 'packages.php',
] as $environmentVariable => $filename) {
    $setEnvironment($environmentVariable, $cachePath.DIRECTORY_SEPARATOR.$filename);
}

require_once $root.'/vendor/autoload.php';

$userClassPath = realpath((new ReflectionClass(User::class))->getFileName());
$worktreeAppPath = realpath($root.'/app');
$worktreePrefix = rtrim((string) $worktreeAppPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

if (! is_string($userClassPath) || ! str_starts_with(strtolower($userClassPath), strtolower($worktreePrefix))) {
    throw new RuntimeException('Composer autoload does not resolve App classes from this worktree.');
}

require_once $root.'/tests/Feature/QA/bootstrap.php';
