#!/usr/bin/env php
<?php

declare(strict_types=1);
$root = dirname(__DIR__, 2);
$options = [];
$coverage = getenv('QA_PCOV_EXTENSION') ?: $root.'/.qa-runtime/pcov/pcov.so';
if (is_file($coverage)) {
    $options = ['-d', 'extension='.$coverage, '-d', 'pcov.enabled=1', '-d', 'pcov.directory='.$root.'/app'];
} elseif (extension_loaded('xdebug')) {
    $options = ['-d', 'xdebug.mode=coverage'];
}
$process = proc_open([PHP_BINARY, ...$options, $root.'/vendor/bin/phpunit', ...array_slice($argv, 1)], [0 => STDIN, 1 => ['pipe', 'w'], 2 => STDERR], $pipes, $root);
if (! is_resource($process)) {
    exit(1);
}
$output = stream_get_contents($pipes[1]);
fclose($pipes[1]);
$rc = proc_close($process);
echo $output;
if ($rc === 0 && ! preg_match('/OK\s*\(/', $output)) {
    foreach (explode("\n", $output) as $line) {
        $result = json_decode($line, true);
        if (is_array($result) && ($result['result'] ?? null) === 'passed') {
            printf("\nOK (%d tests, %d assertions)\n", $result['tests'], $result['assertions']);
            break;
        }
    }
}
exit($rc);
