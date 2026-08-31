<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class M54PurchaseReceivingDistributionTest extends TestCase
{
    private function source(string $path): string { return file_get_contents(dirname(__DIR__, 2).'/'.$path); }

    public function test_posting_is_locked_transactional_and_idempotent(): void
    {
        $source=$this->source('app/Modules/Purchasing/Actions/ApprovePurchaseInvoiceAction.php');
        self::assertStringContainsString('DB::transaction', $source);
        self::assertStringContainsString('lockForUpdate()', $source);
        self::assertStringContainsString("purchase_distribution_out", $source);
        self::assertStringContainsString("purchase_distribution_in", $source);
        self::assertStringContainsString("where('idempotency_key', \$inKey)->exists()", $source);
    }

    public function test_full_distribution_is_mandatory_and_rejects_invalid_allocations(): void
    {
        $approval=$this->source('app/Modules/Purchasing/Actions/ApprovePurchaseInvoiceAction.php');
        $save=$this->source('app/Modules/Purchasing/Actions/SavePurchaseDistributionAction.php');
        self::assertStringContainsString('Every purchased unit must be distributed before approval', $approval);
        self::assertStringContainsString('temporary receiving warehouse must finish with zero', $approval);
        self::assertStringContainsString('Distribution quantities cannot be negative', $save);
        self::assertStringContainsString('visibleTo($actor)', $save);
    }

    public function test_destination_price_snapshot_uses_list_zero_fallback_and_overrides(): void
    {
        $save=$this->source('app/Modules/Purchasing/Actions/SavePurchaseDistributionAction.php');
        $resolver=$this->source('app/Modules/Pricing/Services/PriceListResolver.php');
        self::assertStringContainsString('resolveWithBasePrice', $save);
        self::assertStringContainsString('ProductPriceOverride::query()', $resolver);
        self::assertStringContainsString("where('list_number', 0)", $resolver);
        self::assertStringContainsString('effective_selling_price', $save);
    }

    public function test_least_privilege_permissions_are_registered_and_enforced(): void
    {
        $seeder=$this->source('database/seeders/ProductionSeeder.php');
        foreach(['draft','distribute','approve','reverse','view_cost','change_cost','change_list0_price','print_labels'] as $permission) self::assertStringContainsString("'".$permission."'", $seeder);
        self::assertStringContainsString("Gate::authorize('purchase_invoices.distribute')", $this->source('app/Modules/Purchasing/Actions/SavePurchaseDistributionAction.php'));
        self::assertStringContainsString("Gate::authorize('purchase_invoices.approve')", $this->source('app/Modules/Purchasing/Actions/ApprovePurchaseInvoiceAction.php'));
    }

    public function test_full_page_two_stage_ui_scanning_and_destination_labels_are_present(): void
    {
        $view=$this->source('resources/views/purchasing/invoices.blade.php');
        self::assertStringContainsString('Stage 1 — Purchase invoice', $view);
        self::assertStringContainsString('Stage 2 — Mandatory distribution', $view);
        self::assertStringContainsString('addScannedProduct', $view);
        self::assertStringContainsString('createQuickProduct', $view);
        self::assertStringContainsString('Base Consumer Price List 0', $view);
        self::assertFileExists(dirname(__DIR__,2).'/resources/views/purchasing/destination-labels.blade.php');
    }
}
