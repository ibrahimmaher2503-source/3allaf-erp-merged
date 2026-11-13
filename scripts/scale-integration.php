<?php

declare(strict_types=1);

require __DIR__.'/../app/Modules/Platform/Services/TmScaleConnection.php';

use App\Modules\Platform\Services\TmScaleConnection;

$address = getenv('SCALE_IP') ?: '192.168.0.150';
$port = (int) (getenv('SCALE_PORT') ?: 33581);
$number = (int) (getenv('SCALE_PLU') ?: 3999);
$scale = new TmScaleConnection;
$failures = [];
$assetBlocks = [];

$pass = static function (string $message): void {
    echo "[PASS] {$message}\n";
};
$fail = static function (string $message) use (&$failures): void {
    echo "[FAIL] {$message}\n";
    $failures[] = $message;
};

$initial = null;
try {
    $initial = $scale->readProduct($address, $port, $number);
    if ($initial === null) {
        $fail("PLU {$number} is empty. Set SCALE_PLU to an occupied disposable test PLU.");
    } elseif (! preg_match('/\A[ -~]{1,20}\z/', $initial['name'])) {
        $fail("PLU {$number} name is not an ASCII label accepted by the verified writer.");
    } else {
        $pass("TCP and PLU read succeeded for {$address}:{$port}, PLU {$number}");
        $scale->sendWeightedProduct($address, $port, $number, $initial['code'], 'ERP TEST', '12.34');
        $updated = $scale->readProduct($address, $port, $number);
        if ($updated === null || $updated['price'] !== '12.34' || $updated['code'] !== $initial['code'] || $updated['name'] !== 'ERP TEST') {
            $fail('PLU write readback did not match the test snapshot.');
        } else {
            $pass('PLU price/name write and readback succeeded.');
        }

        $scale->sendWeightedProduct($address, $port, $number, $initial['code'], 'ERP TEST', '13.57');
        $updated = $scale->readProduct($address, $port, $number);
        if ($updated === null || $updated['price'] !== '13.57') {
            $fail('PLU price-only update readback did not match.');
        } else {
            $pass('PLU price-only update succeeded.');
        }

        try {
            $scale->sendWeightedProduct($address, $port, $number, '9999999', 'ERP CONFLICT', '13.57');
            $fail('Conflicting PLU identity was accepted unexpectedly.');
        } catch (RuntimeException) {
            $pass('Conflicting PLU identity was rejected.');
        }
    }
} catch (Throwable $exception) {
    $fail('PLU workflow threw: '.$exception->getMessage());
} finally {
    if ($initial !== null && preg_match('/\A[ -~]{1,20}\z/', $initial['name'])) {
        try {
            $scale->sendWeightedProduct($address, $port, $number, $initial['code'], $initial['name'], $initial['price']);
            $restored = $scale->readProduct($address, $port, $number);
            if ($restored === null || $restored['price'] !== $initial['price'] || $restored['code'] !== $initial['code'] || $restored['name'] !== $initial['name']) {
                $fail('Could not restore the original PLU snapshot.');
            } else {
                $pass('Original PLU snapshot restored.');
            }
        } catch (Throwable $exception) {
            $fail('PLU restore threw: '.$exception->getMessage());
        }
    }
}

try {
    $report = $scale->readSalesReport($address, $port);
    if ($report === []) {
        $fail('Scale report returned no records.');
    } else {
        $pass('Scale report returned '.count($report).' transaction record(s).');
    }
} catch (Throwable $exception) {
    $fail('Scale report read threw: '.$exception->getMessage());
}

foreach (['KEY', 'D0', 'D1'] as $asset) {
    try {
        $reply = $scale->probeAsset($address, $port, $asset);
        if (preg_match('/DWL\t(?:'.$asset.'|\t)\t?\r\nEND\t(?:'.$asset.'|\t)\t?\r\n/', $reply) === 1) {
            $assetBlocks[] = $asset;
            echo "[BLOCKED] {$asset} readback is empty. No verified ERP write frame exists for this device asset.\n";
        } else {
            $pass("{$asset} returned a non-empty frame for protocol analysis.");
        }
    } catch (Throwable $exception) {
        $fail("{$asset} probe threw: ".$exception->getMessage());
    }
}

if ($assetBlocks !== []) {
    $failures[] = 'Device key/label assets are empty and their write protocol is not verified.';
}

try {
    $scale->readProduct('203.0.113.1', $port, $number);
    $fail('Invalid test endpoint was accepted unexpectedly.');
} catch (RuntimeException) {
    $pass('Invalid/private-network endpoint validation rejects an unsafe endpoint.');
}

if ($failures !== []) {
    fwrite(STDERR, "\n".count($failures)." failure/blocker(s) found.\n");
    exit(1);
}

echo "\nAll scale integration checks passed.\n";
