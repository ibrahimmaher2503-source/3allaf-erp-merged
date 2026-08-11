<?php

declare(strict_types=1);

namespace Tests\Feature\Contracts;

use Database\Seeders\CanonicalAuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

/**
 * Contract coverage for the implemented Inventory/POS route surface.
 * Requirements: INV-01..INV-09, POS-01..POS-02, CSH-01, NFR-03.
 */
final class InventoryPosContractTest extends TestCase
{
    use PlatformFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CanonicalAuthorizationSeeder::class);
    }

    public function test_inventory_and_pos_routes_keep_the_required_names_and_server_middleware(): void
    {
        $contracts = [
            'inventory.index' => ['GET', 'auth', 'verified', 'can:inventory_stock_card.view'],
            'inventory.balances' => ['GET', 'auth', 'verified', 'can:inventory_stock_card.view'],
            'inventory.movements' => ['GET', 'auth', 'verified', 'can:inventory_stock_card.view'],
            'inventory.export' => ['GET', 'auth', 'verified', 'can:inventory_stock_card.export'],
            'inventory.transfers' => ['GET', 'auth', 'verified', 'can:transfers.view'],
            'inventory.transfers.create' => ['GET', 'auth', 'verified', 'can:transfers.create'],
            'inventory.transfers.store' => ['POST', 'auth', 'verified', 'can:transfers.create'],
            'inventory.transfers.edit' => ['GET', 'auth', 'verified', 'can:transfers.edit'],
            'inventory.transfers.update' => ['POST', 'auth', 'verified', 'can:transfers.edit'],
            'inventory.transfers.submit' => ['POST', 'auth', 'verified', 'can:transfers.submit'],
            'inventory.transfers.approve' => ['POST', 'auth', 'verified', 'can:transfers.approve'],
            'inventory.transfers.dispatch' => ['POST', 'auth', 'verified', 'can:transfers.dispatch'],
            'inventory.transfers.receive' => ['POST', 'auth', 'verified', 'can:transfers.receive'],
            'inventory.transfers.differences.resolve' => ['POST', 'auth', 'verified', 'can:transfers.difference'],
            'inventory.adjustments.create' => ['GET', 'auth', 'verified', 'can:inventory_stock_card.create'],
            'inventory.adjustments.store' => ['POST', 'auth', 'verified', 'can:inventory_stock_card.create'],
            'inventory.adjustments.edit' => ['GET', 'auth', 'verified', 'can:inventory_stock_card.edit'],
            'inventory.adjustments.update' => ['POST', 'auth', 'verified', 'can:inventory_stock_card.edit'],
            'inventory.adjustments.reverse' => ['POST', 'auth', 'verified', 'can:inventory_stock_card.reverse'],
            'inventory.counts.create' => ['GET', 'auth', 'verified', 'can:stock_counts.create'],
            'inventory.counts.store' => ['POST', 'auth', 'verified', 'can:stock_counts.create'],
            'inventory.counts.entry.save' => ['POST', 'auth', 'verified', 'can:stock_counts.edit'],
            'inventory.counts.entry.recount' => ['POST', 'auth', 'verified', 'can:stock_counts.edit'],
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
            'pos.shift.print.thermal' => ['GET', 'auth', 'verified', 'can:shifts_cash_movements.print'],
            'pos.shift.print.a4' => ['GET', 'auth', 'verified', 'can:shifts_cash_movements.print'],
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

    public function test_inventory_balances_destination_is_a_focused_balance_surface(): void
    {
        $this->actingAs($this->administrator('inventory-balance-focus'));

        $this->get(route('inventory.balances'))
            ->assertOk()
            ->assertSee('Inventory balances')
            ->assertSee('data-inventory-focus="balances"', escape: false)
            ->assertDontSee('Transfers and receipt differences')
            ->assertDontSee('Entries, exits, and adjustments')
            ->assertDontSee('Stock counts and reconciliation')
            ->assertDontSee('Inventory movement ledger');
    }
}
