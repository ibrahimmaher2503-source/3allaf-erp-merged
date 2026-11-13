<?php

declare(strict_types=1);

require __DIR__.'/../app/Modules/Platform/Services/TmScaleConnection.php';

use App\Modules\Platform\Services\TmScaleConnection;

$input = $argv[1] ?? null;
$apply = in_array('--apply', $argv, true);
$address = getenv('SCALE_IP') ?: '192.168.0.150';
$port = (int) (getenv('SCALE_PORT') ?: 33581);

if ($input === null || ! is_file($input)) {
    fwrite(STDERR, "Usage: php scripts/scale-batch-sync.php path/to/products.json [--apply]\n");
    exit(2);
}

$products = json_decode((string) file_get_contents($input), true, 512, JSON_THROW_ON_ERROR);
if (! is_array($products)) {
    throw new RuntimeException('The input must be a JSON array.');
}

$scale = new TmScaleConnection;
$results = [];
foreach ($products as $index => $product) {
    $label = "item {$index}";
    try {
        if (! is_array($product)
            || ! isset($product['plu'], $product['code'], $product['name'], $product['price'])
            || ! is_int($product['plu'])
            || ! is_string($product['code'])
            || ! is_string($product['name'])
            || ! is_string($product['price'])) {
            throw new InvalidArgumentException('Expected plu integer, code/name/price strings.');
        }
        $label = "PLU {$product['plu']}";
        $before = $scale->readProduct($address, $port, $product['plu']);
        $entry = ['plu' => $product['plu'], 'before' => $before, 'status' => 'preview'];
        if ($apply) {
            $scale->sendWeightedProduct($address, $port, $product['plu'], $product['code'], $product['name'], $product['price']);
            $after = $scale->readProduct($address, $port, $product['plu']);
            if ($after === null || $after['code'] !== $product['code'] || $after['name'] !== $product['name'] || $after['price'] !== number_format((float) $product['price'], 2, '.', '')) {
                throw new RuntimeException('Readback did not match the requested payload.');
            }
            $entry['after'] = $after;
            $entry['status'] = 'confirmed';
        }
        $results[] = $entry;
        echo '['.strtoupper($entry['status'])."] {$label}\n";
    } catch (Throwable $exception) {
        $results[] = ['plu' => $product['plu'] ?? null, 'status' => 'failed', 'error' => $exception->getMessage()];
        echo "[FAILED] {$label}: {$exception->getMessage()}\n";
    }
}

$output = getenv('SCALE_SYNC_RESULT') ?: 'storage/app/scale-batch-sync-result.json';
if (! is_dir(dirname($output))) {
    mkdir(dirname($output), 0775, true);
}
file_put_contents($output, json_encode([
    'endpoint' => "{$address}:{$port}",
    'mode' => $apply ? 'apply' : 'preview',
    'created_at' => date(DATE_ATOM),
    'results' => $results,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
echo "[PASS] Result saved to {$output}.\n";

exit(count(array_filter($results, static fn (array $result): bool => $result['status'] === 'failed')) === 0 ? 0 : 1);
