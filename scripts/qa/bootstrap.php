<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2).'/vendor/autoload.php';
if (getenv('APP_ENV') !== 'qa' || !preg_match('/^qa_rajh_[a-zA-Z0-9_]{1,50}$/', (string)getenv('DB_DATABASE')) || getenv('DB_HOST') !== '127.0.0.1') {
    throw new RuntimeException('QA bootstrap requires an explicit isolated qa_rajh_* database and loopback host.');
}
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->loadEnvironmentFrom('.env.qa-does-not-exist');
if ($storage = getenv('QA_STORAGE')) $app->useStoragePath($storage);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
// Fixtures intentionally exercise relationships outside a web request.
Illuminate\Database\Eloquent\Model::preventLazyLoading(false);
set_exception_handler(static function (Throwable $e): void {fwrite(STDERR, get_class($e).': '.$e->getMessage()."\n");exit(1);});
return $app;
