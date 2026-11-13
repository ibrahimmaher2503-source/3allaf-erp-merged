<?php

declare(strict_types=1);

require __DIR__.'/../app/Modules/Platform/Services/TmScaleConnection.php';

use App\Modules\Platform\Services\TmScaleConnection;

$address = getenv('SCALE_IP') ?: '192.168.0.150';
$port = (int) (getenv('SCALE_PORT') ?: 33581);
$plus = getenv('SCALE_PLUS') ?: '3999,3999';
$scale = new TmScaleConnection;
$snapshot = [
    'endpoint' => "{$address}:{$port}",
    'captured_at' => date(DATE_ATOM),
    'clock' => null,
    'plu' => [],
    'report' => [],
    'assets' => [],
    'errors' => [],
];

try {
    $snapshot['clock'] = $scale->readClock($address, $port);
    echo "[PASS] Clock reply verified.\n";
} catch (Throwable $exception) {
    $snapshot['errors'][] = 'clock: '.$exception->getMessage();
    echo "[FAIL] Clock: {$exception->getMessage()}\n";
}

[$first, $last] = array_pad(array_map('intval', explode(',', $plus, 2)), 2, 0);
if ($first < 1 || $last < $first || $last > 4000) {
    fwrite(STDERR, "SCALE_PLUS must be FIRST,LAST between 1 and 4000.\n");
    exit(2);
}

for ($number = $first; $number <= $last; $number++) {
    try {
        $product = $scale->readProduct($address, $port, $number);
        if ($product !== null) {
            $snapshot['plu'][] = $product;
        }
    } catch (Throwable $exception) {
        $snapshot['errors'][] = "plu {$number}: ".$exception->getMessage();
        echo "[FAIL] PLU {$number}: {$exception->getMessage()}\n";
    }
}
echo '[PASS] Read '.count($snapshot['plu'])." populated PLU(s) from {$first}-{$last}.\n";

try {
    $snapshot['report'] = $scale->readSalesReport($address, $port);
    echo '[PASS] Read '.count($snapshot['report'])." scale transaction record(s).\n";
} catch (Throwable $exception) {
    $snapshot['errors'][] = 'report: '.$exception->getMessage();
    echo "[FAIL] Report: {$exception->getMessage()}\n";
}

foreach (['KEY', 'D0', 'D1'] as $asset) {
    try {
        $reply = $scale->probeAsset($address, $port, $asset);
        $snapshot['assets'][$asset] = [
            'reply' => $reply,
            'has_payload' => ! preg_match('/DWL\t(?:'.$asset.'|\t)\t?\r\nEND\t(?:'.$asset.'|\t)\t?\r\n/', $reply),
        ];
        echo '[INFO] '.$asset.($snapshot['assets'][$asset]['has_payload'] ? " returned data.\n" : " is empty.\n");
    } catch (Throwable $exception) {
        $snapshot['errors'][] = "{$asset}: ".$exception->getMessage();
        echo "[FAIL] {$asset}: {$exception->getMessage()}\n";
    }
}

$output = getenv('SCALE_SNAPSHOT') ?: 'storage/app/scale-device-inventory.json';
if (! is_dir(dirname($output))) {
    mkdir(dirname($output), 0775, true);
}
file_put_contents($output, json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
echo "[PASS] Snapshot saved to {$output}.\n";

exit($snapshot['errors'] === [] ? 0 : 1);
