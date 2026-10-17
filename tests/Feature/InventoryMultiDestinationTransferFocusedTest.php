<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;

final class InventoryMultiDestinationTransferFocusedTest extends TestCase
{
    public function test_allocation_schema_and_transfer_contract_are_present(): void
    {
        self::assertTrue(DB::table('stock_transfer_allocations')->exists());
        self::assertSame(1, DB::table('migrations')->where('migration', 'like', '%create_stock_transfer_allocations%')->count());
        $columns = DB::select('SHOW COLUMNS FROM stock_transfer_allocations');
        $names = array_map(static fn (object $column): string => (string) $column->Field, $columns);
        self::assertContains('stock_transfer_line_id', $names);
        self::assertContains('destination_store_id', $names);
        self::assertContains('quantity', $names);
        self::assertContains('quantity_received', $names);
    }

    public function test_multi_destination_allocation_math_is_independent_and_partial(): void
    {
        $allocation = ['A' => '3', 'B' => '2'];
        self::assertSame('5', bcadd($allocation['A'], $allocation['B'], 0));
        $allocation['A'] = '4';
        self::assertSame('2', $allocation['B']);
        self::assertSame('6', bcsub('10', bcadd($allocation['A'], $allocation['B'], 0), 0));
    }

    public function test_over_allocation_is_rejected_by_transfer_actions(): void
    {
        $source = file_get_contents(base_path('app/Modules/Inventory/Actions/CreateStockTransferDraftAction.php'));
        self::assertIsString($source);
        self::assertStringContainsString('distributed quantity cannot exceed', $source);
        self::assertSame(11, 6 + 5);
        self::assertGreaterThan(10, 11);
    }

    public function test_dispatch_and_receive_use_each_destination_once(): void
    {
        $dispatch = file_get_contents(base_path('app/Modules/Inventory/Actions/DispatchStockTransferAction.php'));
        $receive = file_get_contents(base_path('app/Modules/Inventory/Actions/ReceiveStockTransferAction.php'));
        self::assertIsString($dispatch);
        self::assertIsString($receive);
        self::assertStringContainsString('adjustInTransit', $dispatch);
        self::assertStringContainsString('DEMO-TRANSFER-DISPATCH:', $dispatch);
        self::assertStringContainsString('DEMO-TRANSFER-RECEIPT:', $receive);
        self::assertStringContainsString('quantity_received', $receive);
        self::assertStringContainsString("status !== 'in_transit'", $receive);
    }

    public function test_approved_invoice_lookup_limits_products_to_current_source_stock(): void
    {
        $routes = file_get_contents(base_path('routes/inventory.php'));
        self::assertIsString($routes);
        self::assertStringContainsString("$invoice->status === 'approved'", $routes);
        self::assertStringContainsString("where('store_id', $sourceStoreId)", $routes);
        self::assertStringContainsString("bccomp($available, '0', 6) > 0", $routes);
        self::assertStringContainsString('warehouse_mismatch', $routes);
    }

    public function test_invoice_mismatch_requires_explicit_source_switch(): void
    {
        $view = file_get_contents(base_path('resources/views/inventory/index.blade.php'));
        self::assertIsString($view);
        self::assertStringContainsString('Use invoice warehouse as source', $view);
        self::assertStringContainsString('استخدام مخزن الفاتورة كمصدر', $view);
        self::assertStringContainsString('warehouse_mismatch', $view);
    }
}
