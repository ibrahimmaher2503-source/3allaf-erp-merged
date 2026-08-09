<?php

declare(strict_types=1);

namespace Tests\Feature\Contracts;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Contract coverage for the implemented Inventory/POS route surface.
 * Requirements: INV-01..INV-09, POS-01..POS-02, CSH-01, NFR-03.
 */
final class InventoryPosContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_and_pos_routes_keep_the_required_names_and_server_middleware(): void
    {
        $contracts = [
            'inventory.index' => ['GET', 'auth', 'verified', 'can:inventory_stock_card.view'],
            'inventory.movements' => ['GET', 'auth', 'verified', 'can:inventory_stock_card.view'],
            'inventory.transfers' => ['GET', 'auth', 'verified', 'can:transfers.view'],
            'inventory.transfers.approve' => ['POST', 'auth', 'verified', 'can:transfers.approve'],
            'inventory.transfers.dispatch' => ['POST', 'auth', 'verified', 'can:transfers.dispatch'],
            'inventory.transfers.receive' => ['POST', 'auth', 'verified', 'can:transfers.receive'],
            'inventory.transfers.differences.resolve' => ['POST', 'auth', 'verified', 'can:transfers.difference'],
            'inventory.counts.submit' => ['POST', 'auth', 'verified', 'can:stock_counts.submit'],
            'inventory.counts.reconcile' => ['POST', 'auth', 'verified', 'can:stock_counts.reconcile'],
            'pos' => ['GET', 'auth', 'verified', 'can:pos_sales.view'],
            'pos.checkout' => ['POST', 'auth', 'verified', 'can:pos_sales.create'],
            'pos.suspend' => ['POST', 'auth', 'verified', 'can:pos_sales.create'],
            'pos.shift' => ['GET', 'auth', 'verified', 'can:shifts_cash_movements.view'],
            'pos.shift.open' => ['POST', 'auth', 'verified', 'can:shifts_cash_movements.create'],
            'pos.shift.cash-movement' => ['POST', 'auth', 'verified', 'can:shifts_cash_movements.create'],
            'pos.shift.blind-close' => ['POST', 'auth', 'verified', 'can:shifts_cash_movements.submit'],
            'pos.shift-variance' => ['GET', 'auth', 'verified', 'can:shifts_cash_movements.approve'],
            'admin.approvals' => ['GET', 'auth', 'verified'],
            'pos.offline-readiness' => ['GET', 'auth', 'verified', 'can:pos_sales.view'],
        ];

        foreach ($contracts as $name => $contract) {
            $method = array_shift($contract);
            $middleware = $contract;
            $route = Route::getRoutes()->getByName($name);
            self::assertInstanceOf(LaravelRoute::class, $route, 'Missing route contract: '.$name);
            self::assertContains($method, $route->methods(), 'Wrong method contract: '.$name);
            foreach ($middleware as $requiredMiddleware) {
                self::assertContains($requiredMiddleware, $route->gatherMiddleware(), 'Missing middleware contract: '.$name);
            }
        }
    }
}
