<?php

use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;

require_once dirname(__DIR__, 3).'/vendor/autoload.php';

function qa_reset_pure_calculator_container(): void
{
    // Raw calculator tests need translation and validation, never app or database boot.
    $app = new Container;
    Container::setInstance($app);
    $app->instance('translator', new Translator(new FileLoader(new Filesystem, dirname(__DIR__, 3).'/lang'), 'en'));

    Facade::clearResolvedInstances();
    Facade::setFacadeApplication($app);
    $app->instance('validator', new Factory($app->make('translator'), $app));
}

qa_reset_pure_calculator_container();

if ($file = getenv('QA_MUTANT_FILE')) {
    if (! str_starts_with($file, dirname(__DIR__, 3).'/.qa-runtime/mutation-replay/')) {
        throw new RuntimeException('Invalid local mutation replay path');
    }
    $class = getenv('QA_MUTANT_CLASS');
    spl_autoload_register(static function (string $name) use ($file, $class): void {
        if ($name === $class) {
            require $file;
        }
    }, true, true);
}
