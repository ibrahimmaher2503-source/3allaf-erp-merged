<?php

use Illuminate\Http\Request;

$root = dirname(__DIR__, 2);
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (is_string($path) && preg_match('#^/(build/|images/|favicon\.|manifest\.json|sw\.js)#', $path)) {
    $file = realpath($root.'/public'.$path);
    if ($file && str_starts_with($file, $root.'/public/') && is_file($file)) {
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        header('Content-Type: '.(['js' => 'application/javascript', 'css' => 'text/css', 'json' => 'application/json', 'svg' => 'image/svg+xml', 'png' => 'image/png'][$ext] ?? 'application/octet-stream'));
        readfile($file);

        return;
    }
}
if (getenv('APP_ENV') !== 'qa' || ! str_starts_with((string) getenv('DB_SOCKET'), '/tmp/rajeh-qa-mariadb.')) {
    http_response_code(500);
    exit('Unsafe QA runtime');
}
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->loadEnvironmentFrom('.env.qa-does-not-exist');
$app->useStoragePath(getenv('QA_STORAGE'));
$app->handleRequest(Request::capture());
