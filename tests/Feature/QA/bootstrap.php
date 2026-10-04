<?php
require_once dirname(__DIR__, 3).'/vendor/autoload.php';
// Pure calculators need translation messages, never a business-data connection.
$app = new Illuminate\Container\Container;
Illuminate\Container\Container::setInstance($app);
$app->instance('translator', new Illuminate\Translation\Translator(new Illuminate\Translation\FileLoader(new Illuminate\Filesystem\Filesystem, dirname(__DIR__, 3).'/lang'), 'en'));

Illuminate\Support\Facades\Facade::setFacadeApplication($app);
$app->instance('validator', new Illuminate\Validation\Factory($app->make('translator'), $app));

if ($file=getenv('QA_MUTANT_FILE')) {
    if (!str_starts_with($file, dirname(__DIR__,3).'/.qa-runtime/mutation-replay/')) throw new RuntimeException('Invalid local mutation replay path');
    $class=getenv('QA_MUTANT_CLASS');spl_autoload_register(static function(string $name) use ($file,$class): void {if ($name===$class) require $file;},true,true);
}
