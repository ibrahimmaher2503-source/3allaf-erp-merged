<?php
declare(strict_types=1);
$app = require __DIR__.'/bootstrap.php';
exit($app->make(Illuminate\Contracts\Console\Kernel::class)->handle(
    new Symfony\Component\Console\Input\ArgvInput(),
    new Symfony\Component\Console\Output\ConsoleOutput()
));
