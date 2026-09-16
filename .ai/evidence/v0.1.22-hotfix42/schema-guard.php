<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$configuredRoot = getenv('HF42_APPLICATION_ROOT');
$root = $configuredRoot === false || $configuredRoot === '' ? dirname(__DIR__, 3) : $configuredRoot;
$root = realpath($root) ?: '';
$mode = $argv[1] ?? '';
$snapshotPath = $argv[2] ?? '';
$fail = static function (string $message): never {
    throw new RuntimeException($message);
};

try {
    if (! in_array($mode, ['before', 'after'], true) || $snapshotPath === '') {
        $fail('Usage: schema-guard.php before|after SNAPSHOT_PATH');
    }
    if ($root === '' || ! is_file($root.'/vendor/autoload.php') || ! is_file($root.'/bootstrap/app.php')) {
        $fail('Hotfix42 application root is unavailable.');
    }
    require $root.'/vendor/autoload.php';
    $app = require $root.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    if (DB::connection()->getDriverName() !== 'mysql') {
        $fail('Hotfix42 requires the configured MySQL/MariaDB connection.');
    }

    $database = (string) DB::connection()->getDatabaseName();
    $version = (string) (DB::selectOne('SELECT VERSION() AS version')->version ?? '');
    if ($version === '') {
        $fail('Database engine version is unavailable.');
    }
    $isMariaDb = stripos($version, 'MariaDB') !== false;
    $defaultMatchesExpected = require __DIR__.'/schema-default.php';
    $uniqueIndex = DB::table('information_schema.statistics')
        ->where('table_schema', $database)->where('table_name', 'products')->where('column_name', 'item_code')->where('non_unique', 0)
        ->orderBy('index_name')->orderBy('seq_in_index')->get(['index_name', 'seq_in_index', 'column_name'])->map(fn ($row) => (array) $row)->all();
    if ($uniqueIndex === []) {
        $fail('The existing unique products.item_code index is missing.');
    }
    $purchaseOrderLineIndexes = DB::table('information_schema.statistics')
        ->where('table_schema', $database)->where('table_name', 'purchase_order_lines')
        ->orderBy('index_name')->orderBy('seq_in_index')->get(['index_name', 'non_unique', 'seq_in_index', 'column_name'])->map(fn ($row) => (array) $row)->all();
    $purchaseOrderLineConstraints = DB::table('information_schema.key_column_usage')
        ->where('table_schema', $database)->where('table_name', 'purchase_order_lines')
        ->orderBy('constraint_name')->orderBy('ordinal_position')->get(['constraint_name', 'column_name', 'referenced_table_name', 'referenced_column_name'])->map(fn ($row) => (array) $row)->all();
    $columnDefinition = static function (string $table, string $column) use ($database): ?object {
        $metadata = DB::table('information_schema.columns')
            ->selectRaw('COLUMN_TYPE AS column_type, IS_NULLABLE AS is_nullable, COLUMN_DEFAULT AS column_default, COLUMN_DEFAULT IS NULL AS column_default_is_null')
            ->where('table_schema', $database)->where('table_name', $table)->where('column_name', $column)
            ->first();
        if ($metadata !== null) {
            foreach (['column_type', 'is_nullable', 'column_default', 'column_default_is_null'] as $property) {
                if (! property_exists($metadata, $property)) {
                    throw new RuntimeException("Required column metadata {$property} is missing.");
                }
            }
        }
        return $metadata;
    };
    $itemCode = $columnDefinition('products', 'item_code');
    if ($itemCode === null || strtolower((string) $itemCode->column_type) !== 'varchar(50)') {
        $fail('products.item_code is not varchar(50).');
    }

    $headers = DB::table('purchase_orders')->orderBy('id')->get(['id', 'subtotal', 'tax_amount', 'total_amount'])->map(fn ($row) => (array) $row)->all();
    $products = DB::table('products')->orderBy('id')->get(['id', 'item_code'])->map(fn ($row) => (array) $row)->all();
    $fingerprints = [
        'headers' => hash('sha256', json_encode($headers, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION)),
        'products' => hash('sha256', json_encode($products, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION)),
        'unique_index' => hash('sha256', json_encode($uniqueIndex, JSON_THROW_ON_ERROR)),
        'purchase_order_line_indexes' => hash('sha256', json_encode($purchaseOrderLineIndexes, JSON_THROW_ON_ERROR)),
        'purchase_order_line_constraints' => hash('sha256', json_encode($purchaseOrderLineConstraints, JSON_THROW_ON_ERROR)),
    ];

    $snapshotColumns = ['discount_type', 'discount_value', 'discount_amount', 'tax_rate', 'tax_code', 'tax_inclusive', 'tax_amount', 'line_total'];
    if ($mode === 'before') {
        foreach ($snapshotColumns as $column) {
            if (Schema::hasColumn('purchase_order_lines', $column)) {
                $fail("Migration 000115 prerequisite failed: {$column} already exists.");
            }
        }
        if (strtoupper((string) $itemCode->is_nullable) !== 'NO') {
            $fail('Migration 000116 prerequisite failed: products.item_code is already nullable.');
        }
        file_put_contents($snapshotPath, json_encode($fingerprints, JSON_THROW_ON_ERROR));
        echo "HOTFIX42_SCHEMA_GUARD=PASS mode=before engine=mysql version={$version} multiple_null_unique_semantics=supported\n";
        exit(0);
    }

    $before = json_decode((string) file_get_contents($snapshotPath), true, 512, JSON_THROW_ON_ERROR);
    if ($before !== $fingerprints) {
        $fail('Header totals, historical tax, existing product codes, or the retained unique index changed during migration.');
    }
    if (strtoupper((string) $itemCode->is_nullable) !== 'YES') {
        $fail('products.item_code is not nullable after migration 000116.');
    }

    $expected = [
        'discount_type' => ['varchar(20)', 'YES', null],
        'discount_value' => ['decimal(19,4)', 'NO', '0.0000'],
        'discount_amount' => ['decimal(19,4)', 'NO', '0.0000'],
        'tax_rate' => ['decimal(9,4)', 'NO', '0.0000'],
        'tax_code' => ['varchar(50)', 'YES', null],
        'tax_inclusive' => ['tinyint(1)', 'NO', '0'],
        'tax_amount' => ['decimal(19,4)', 'NO', '0.0000'],
        'line_total' => ['decimal(19,4)', 'NO', '0.0000'],
    ];
    foreach ($expected as $column => [$type, $nullable, $default]) {
        $actual = $columnDefinition('purchase_order_lines', $column);
        $defaultMatches = $actual !== null && $defaultMatchesExpected($actual, $default, $isMariaDb);
        if ($actual === null || strtolower((string) $actual->column_type) !== $type || strtoupper((string) $actual->is_nullable) !== $nullable || ! $defaultMatches) {
            $fail("Unexpected purchase_order_lines.{$column} definition.");
        }
    }
    $invalidBackfill = DB::table('purchase_order_lines')->where(function ($query): void {
        $query->where('discount_value', '<>', 0)->orWhere('discount_amount', '<>', 0)->orWhere('tax_rate', '<>', 0)
            ->orWhere('tax_inclusive', '<>', 0)->orWhere('tax_amount', '<>', 0)->orWhereColumn('line_total', '<>', 'subtotal');
    })->exists();
    if ($invalidBackfill) {
        $fail('Migration 000115 legacy line backfill is not exact.');
    }
    echo "HOTFIX42_SCHEMA_GUARD=PASS mode=after engine=mysql version={$version} unique_index=retained headers=unchanged product_codes=unchanged multiple_null_unique_semantics=supported\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'HOTFIX42_SCHEMA_GUARD=FAIL '.$exception->getMessage()."\n");
    exit(1);
}
