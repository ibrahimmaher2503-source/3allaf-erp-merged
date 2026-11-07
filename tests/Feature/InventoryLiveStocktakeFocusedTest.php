<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Barcode;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Actions\AddAllStockCountProductsAction;
use App\Modules\Inventory\Actions\AssertInventoryStoreScope;
use App\Modules\Inventory\Actions\CompleteStockCountLineAction;
use App\Modules\Inventory\Actions\CreateStockTransferDraftAction;
use App\Modules\Inventory\Actions\DispatchStockTransferAction;
use App\Modules\Inventory\Actions\ImportStockCountWorkbookAction;
use App\Modules\Inventory\Actions\OpenStockCountSessionAction;
use App\Modules\Inventory\Actions\PostInventoryMovement;
use App\Modules\Inventory\Actions\ReceiveStockTransferAction;
use App\Modules\Inventory\Actions\ReconcileStockCountAction;
use App\Modules\Inventory\Actions\RecordStockCountContributionAction;
use App\Modules\Inventory\Actions\SaveStockCountAction;
use App\Modules\Inventory\Actions\SetStockCountQuantityAction;
use App\Modules\Inventory\Actions\SubmitStockCountAction;
use App\Modules\Inventory\Models\ProductStoreAssignment;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockCount;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Inventory\Support\StockCountReconciliation;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Models\UserStoreScope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

final class InventoryLiveStocktakeFocusedTest extends TestCase
{
    use DatabaseTransactions;

    public function test_stocktake_create_manual_hierarchy_and_independent_location(): void
    {
        $setup = $this->setupInventory();
        $this->get(route('inventory.counts.create'))->assertOk()
            ->assertSee('data-stocktake-primary-fields', false)
            ->assertSee('data-stocktake-location-picker', false)
            ->assertSee('value="manual"', false)
            ->assertSee('data-stocktake-branch', false)
            ->assertSee('name="manager_id"', false)
            ->assertSee('name="member_ids[]"', false)
            ->assertSee('data-stocktake-notes-field', false);

        $before = StockCount::query()->count();
        $response = $this->post(route('inventory.counts.store'), [
            'branch_id' => null, 'location_ids' => [$setup['branchless']->id],
            'count_type' => 'full', 'scope_type' => 'full', 'execution_method' => 'manual',
            'manager_id' => $setup['admin']->id, 'member_ids' => [$setup['admin']->id], 'notes' => 'Manual acceptance',
        ]);
        $response->assertRedirect();
        self::assertSame($before + 1, StockCount::query()->count());
        self::assertStringContainsString('mode=manual', $response->headers->get('Location'));
        self::assertSame((int) $setup['branchless']->id, (int) StockCount::query()->latest('id')->firstOrFail()->store_id);
    }

    /** @return array{admin:User, user:User, store:Store, branchless:Store, product:Product} */
    private function setupInventory(): array
    {
        require_once base_path('scripts/qa/fixture.php');
        $fixture = qaFixture();
        $admin = $fixture['admin'];
        $this->actingAs($admin)->withoutVite();
        $branchless = Store::query()->create(['company_id' => $fixture['company']->id, 'branch_id' => null,
            'code' => 'QA-COUNT-'.Str::upper(Str::random(6)), 'type' => 'warehouse', 'name_ar' => 'مخزن جرد',
            'name_en' => 'Count warehouse', 'status' => 'active', 'allows_negative_stock' => false]);
        $product = Product::factory()->create(['category_id' => $fixture['category']->id, 'sale_price' => 20]);
        Barcode::query()->create(['product_id' => $product->id, 'barcode' => 'QA'.Str::upper(Str::random(12)),
            'source' => 'local', 'status' => 'active', 'is_primary' => true]);

        return ['admin' => $admin, 'user' => $fixture['user'], 'store' => $fixture['store'], 'branchless' => $branchless, 'product' => $product];
    }

    private function openCount(Store $store, User $admin, string $scope = 'partial'): StockCount
    {
        $count = app(SaveStockCountAction::class)->execute(['branch_id' => $store->branch_id,
            'location_ids' => [$store->id], 'member_ids' => [$admin->id], 'manager_id' => $admin->id,
            'count_type' => $scope === 'full' ? 'full' : 'partial', 'scope_type' => $scope,
            'notes' => 'Focused live stocktake']);
        app(OpenStockCountSessionAction::class)->execute($count->id);

        return $count->fresh();
    }

    /** @param list<array{0:string,1:string}> $rows */
    private function stocktakeWorkbook(array $rows): UploadedFile
    {
        $base = tempnam(sys_get_temp_dir(), 'qa-stocktake-');
        $path = $base.'.xlsx';
        unlink($base);
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(['product_identifier', 'actual_quantity']));
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        $writer->close();
        $upload = UploadedFile::fake()->createWithContent('stocktake-counts.xlsx', file_get_contents($path));
        unlink($path);

        return $upload;
    }

    public function test_random_count_keeps_only_selected_products_and_full_count_snapshots_all_without_stock_posting(): void
    {
        $setup = $this->setupInventory();
        $this->get(route('inventory.counts.create'))->assertOk()->assertSee('data-stocktake-settings', false)
            ->assertSee('value="random"', false);
        $random = $this->openCount($setup['branchless'], $setup['admin'], 'random');
        self::assertSame('random', $random->scope_type);
        self::assertSame(0, $random->lines()->count());

        try {
            app(ImportStockCountWorkbookAction::class)->execute($random->id, $setup['branchless']->id,
                $this->stocktakeWorkbook([[$setup['product']->item_code, '2']]));
            self::fail('Random count accepted an Excel import.');
        } catch (InvalidArgumentException $exception) {
            self::assertNotSame('', $exception->getMessage());
        }

        $full = $this->openCount($setup['branchless'], $setup['admin'], 'full');
        self::assertGreaterThan(0, $full->lines()->count());
        self::assertTrue($full->lines()->where('product_id', $setup['product']->id)->exists());
        self::assertSame(0, StockMovement::query()->count());
    }

    public function test_stocktake_excel_import_matches_sku_and_barcode_and_is_retry_safe_without_posting(): void
    {
        $setup = $this->setupInventory();
        $count = $this->openCount($setup['branchless'], $setup['admin']);
        $barcode = $setup['product']->barcodes()->firstOrFail()->barcode;
        $second = Product::factory()->create(['category_id' => $setup['product']->category_id, 'sale_price' => 20]);
        Barcode::query()->create(['product_id' => $second->id, 'barcode' => 'QA'.Str::upper(Str::random(12)), 'source' => 'local', 'status' => 'active', 'is_primary' => true]);
        $url = route('inventory.counts.import', $count);
        $rows = [[$setup['product']->item_code, '4'], [$barcode, '9'], [$second->barcodes()->firstOrFail()->barcode, '7'], ['UNKNOWN-QA-CODE', '3'], [$second->item_code, '-2']];

        $this->from(route('inventory.counts.entry', $count))->post($url, [
            'store_id' => $setup['branchless']->id, 'import_file' => $this->stocktakeWorkbook($rows),
        ])->assertRedirect(route('inventory.counts.entry', $count))->assertSessionHas('stocktake_import_summary', fn ($summary) => $summary['imported'] === 2 && $summary['rejected'] === 3);
        self::assertSame(2, $count->lines()->count());
        self::assertSame('4.000000', (string) $count->lines()->where('product_id', $setup['product']->id)->value('counted_quantity'));
        self::assertSame('7.000000', (string) $count->lines()->where('product_id', $second->id)->value('counted_quantity'));
        self::assertSame(2, $count->contributions()->count());
        self::assertSame(0, StockMovement::query()->count());

        self::assertNull($count->fresh()->reconciled_at);
        self::assertSame($setup['admin']->id, (int) $count->lines()->where('product_id', $second->id)->value('counted_by'));

        $this->post($url, ['store_id' => $setup['branchless']->id, 'import_file' => $this->stocktakeWorkbook($rows)])
            ->assertRedirect()->assertSessionHas('stocktake_import_summary', fn ($summary) => $summary['imported'] === 2);
        self::assertSame(2, $count->lines()->count());
        self::assertSame(2, $count->contributions()->count());
        self::assertSame(0, StockMovement::query()->count());

        $this->post($url, ['store_id' => $setup['branchless']->id,
            'import_file' => UploadedFile::fake()->createWithContent('stocktake-counts.csv', "product_identifier,actual_quantity\n{$setup['product']->item_code},6\n"),
        ])->assertRedirect()->assertSessionHas('stocktake_import_summary', fn ($summary) => $summary['imported'] === 1 && $summary['rejected'] === 0);
        self::assertSame('6.000000', (string) $count->lines()->where('product_id', $setup['product']->id)->value('counted_quantity'));
        self::assertSame(2, $count->lines()->count());
        self::assertSame(0, StockMovement::query()->count());
    }

    public function test_stocktake_excel_import_rejects_wrong_warehouse_cross_company_and_unauthorized_actor(): void
    {
        $setup = $this->setupInventory();
        $count = $this->openCount($setup['branchless'], $setup['admin']);
        $otherCompany = Company::query()->create(['code' => 'QA-OTHER-'.Str::upper(Str::random(5)), 'name_ar' => 'شركة أخرى', 'name_en' => 'Other company', 'status' => 'active', 'currency_code' => 'EGP', 'timezone' => 'Africa/Cairo']);
        $otherBranch = Branch::query()->create(['company_id' => $otherCompany->id, 'code' => 'QA-OTHER-BR-'.Str::upper(Str::random(5)), 'name_ar' => 'فرع آخر', 'name_en' => 'Other branch', 'status' => 'active', 'timezone' => 'Africa/Cairo']);
        $otherStore = Store::query()->create(['company_id' => $otherCompany->id, 'branch_id' => $otherBranch->id, 'code' => 'QA-OTHER-ST-'.Str::upper(Str::random(5)), 'type' => 'warehouse', 'name_ar' => 'مخزن آخر', 'name_en' => 'Other warehouse', 'status' => 'active', 'allows_negative_stock' => false]);
        UserStoreScope::query()->create(['user_id' => $setup['admin']->id, 'store_id' => $setup['branchless']->id, 'status' => 'active']);
        $otherProduct = Product::factory()->create(['category_id' => $setup['product']->category_id, 'sale_price' => 20]);
        ProductStoreAssignment::query()->create(['company_id' => $otherCompany->id, 'branch_id' => $otherBranch->id, 'store_id' => $otherStore->id, 'product_id' => $otherProduct->id, 'status' => 'active', 'created_by' => $setup['admin']->id, 'updated_by' => $setup['admin']->id]);
        $url = route('inventory.counts.import', $count);
        $this->post($url, ['store_id' => $setup['branchless']->id, 'import_file' => $this->stocktakeWorkbook([[$otherProduct->item_code, '3']])])
            ->assertRedirect()->assertSessionHas('stocktake_import_summary', fn ($summary) => $summary['imported'] === 0 && $summary['rejected'] === 1);
        $this->post($url, ['store_id' => $setup['store']->id, 'import_file' => $this->stocktakeWorkbook([[$setup['product']->item_code, '3']])])->assertNotFound();
        $this->actingAs($setup['user'])->post($url, ['store_id' => $setup['branchless']->id, 'import_file' => $this->stocktakeWorkbook([[$setup['product']->item_code, '3']])])->assertForbidden();
        self::assertSame(0, $count->lines()->count());
        self::assertSame(0, StockMovement::query()->count());
    }

    public function test_manual_add_form_posts_and_rejects_duplicate_hidden_and_unknown_products(): void
    {
        $setup = $this->setupInventory();
        $count = $this->openCount($setup['branchless'], $setup['admin']);
        $url = route('inventory.counts.contribute', $count);
        $response = $this->get(route('inventory.counts.entry', $count))->assertOk();
        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($response->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new \DOMXPath($document);
        $forms = $xpath->query('//form[@action="'.$url.'" and @data-product-line-editor]');
        self::assertCount(1, $forms);
        self::assertSame('POST', $forms->item(0)->getAttribute('method'));
        self::assertCount(1, $xpath->query('.//input[@name="_token"]', $forms->item(0)));

        $requestId = (string) Str::uuid();
        $payload = ['store_id' => $setup['branchless']->id, 'lookup' => $setup['product']->item_code,
            'quantity' => '2', 'request_id' => $requestId, 'device_id' => 'qa-manual', 'input_method' => 'manual'];
        $this->from(route('inventory.counts.entry', $count))->post($url, $payload)
            ->assertRedirect(route('inventory.counts.entry', $count))->assertSessionHas('success');
        $this->get(route('inventory.counts.entry', $count))->assertOk()->assertSee($setup['product']->item_code);
        self::assertSame(1, $count->lines()->count());
        self::assertSame('2.000000', (string) $count->lines()->firstOrFail()->counted_quantity);

        $this->post($url, $payload)->assertRedirect()->assertSessionHas('success');
        self::assertSame(1, $count->lines()->count());
        self::assertSame(1, $count->contributions()->count());

        $hidden = Product::factory()->inactive()->create(['category_id' => $setup['product']->category_id]);
        foreach ([$hidden->item_code, 'QA-UNKNOWN-PRODUCT'] as $lookup) {
            $this->post($url, [...$payload, 'lookup' => $lookup, 'request_id' => (string) Str::uuid()])
                ->assertRedirect()->assertSessionHas('error');
        }
        self::assertSame(1, $count->lines()->count());
        self::assertSame(1, $count->contributions()->count());

        $restricted = User::query()->create(['name' => 'QA Restricted Counter',
            'email' => 'qa-counter-'.Str::random(8).'@example.test', 'password' => Str::random(32),
            'status' => 'active', 'is_super_admin' => false]);
        $this->actingAs($restricted)->post($url, [...$payload, 'request_id' => (string) Str::uuid()])
            ->assertForbidden();
        self::assertSame(1, $count->contributions()->count());
    }

    public function test_live_count_uses_each_products_count_boundary_and_settles_only_variances(): void
    {
        $setup = $this->setupInventory();
        $store = $setup['branchless'];
        $product = $setup['product'];
        app(PostInventoryMovement::class)->execute($product->id, $store->id, '100', 'opening_inventory', '10', 'qa-opening-'.Str::uuid());
        $matched = Product::factory()->create(['category_id' => $product->category_id, 'sale_price' => 20]);
        Barcode::query()->create(['product_id' => $matched->id, 'barcode' => 'QA'.Str::upper(Str::random(12)), 'source' => 'local', 'status' => 'active', 'is_primary' => true]);
        app(PostInventoryMovement::class)->execute($matched->id, $store->id, '5', 'opening_inventory', '10', 'qa-opening-'.Str::uuid());
        $count = $this->openCount($store, $setup['admin']);
        self::assertNull($count->branch_id);
        self::assertSame(0, $count->lines()->count());

        app(PostInventoryMovement::class)->execute($product->id, $store->id, '-10', 'sale', null, 'qa-sale-'.Str::uuid());
        app(PostInventoryMovement::class)->execute($product->id, $store->id, '2', 'sale_return', null, 'qa-return-'.Str::uuid());
        app(PostInventoryMovement::class)->execute($product->id, $store->id, '5', 'purchase_receipt', null, 'qa-receipt-'.Str::uuid());
        $a = app(SetStockCountQuantityAction::class)->execute($count->id, $store->id, $product->item_code, '95', (string) Str::uuid(), 'qa-browser', 'Physical count');
        self::assertSame('97.000000', bcadd((string) $a->reference_on_hand,
            (string) StockMovement::query()->where('product_id', $product->id)->where('store_id', $store->id)->where('id', '>', $a->reference_movement_id)->where('id', '<=', $a->counted_movement_id)->sum('quantity'), 6));
        app(PostInventoryMovement::class)->execute($product->id, $store->id, '-3', 'sale', null, 'qa-after-count-'.Str::uuid());
        $values = app(StockCountReconciliation::class)->calculate($count, $a->fresh());
        self::assertSame('94.000000', $values['expected_quantity']);
        self::assertSame('92.000000', $values['normalized_closing_quantity']);
        self::assertSame('-2.000000', $values['variance_quantity']);
        self::assertSame('94.000000', (string) StockBalance::query()->where('store_id', $store->id)->where('product_id', $product->id)->value('on_hand'));

        $b = app(SetStockCountQuantityAction::class)->execute($count->id, $store->id, $matched->item_code, '5', (string) Str::uuid(), 'qa-browser', null);
        app(CompleteStockCountLineAction::class)->execute($a->id);
        app(CompleteStockCountLineAction::class)->execute($b->id);
        app(SubmitStockCountAction::class)->execute($count->id);
        self::assertSame('94.000000', (string) StockBalance::query()->where('store_id', $store->id)->where('product_id', $product->id)->value('on_hand'));

        $approver = User::query()->create(['name' => 'QA Count Approver', 'email' => 'qa-approver-'.Str::random(8).'@example.test', 'password' => Str::random(32), 'status' => 'active', 'is_super_admin' => true]);
        $approver->forceFill(['is_super_admin' => true])->save();
        Auth::login($approver);
        app(ReconcileStockCountAction::class)->execute($count->id);
        self::assertSame('92.000000', (string) StockBalance::query()->where('store_id', $store->id)->where('product_id', $product->id)->value('on_hand'));
        self::assertSame(1, StockMovement::query()->where('source_type', StockCount::class)->where('source_id', $count->id)->count());
        self::assertSame(0, StockMovement::query()->where('source_type', StockCount::class)->where('source_id', $count->id)->where('product_id', $matched->id)->count());
        $this->expectException(InvalidArgumentException::class);
        app(ReconcileStockCountAction::class)->execute($count->id);
    }

    public function test_scan_increment_override_unknown_code_and_retry_are_non_destructive(): void
    {
        $setup = $this->setupInventory();
        $count = $this->openCount($setup['branchless'], $setup['admin']);
        $product = $setup['product'];
        $barcode = $product->barcodes()->firstOrFail()->barcode;
        $firstId = (string) Str::uuid();
        $action = app(RecordStockCountContributionAction::class);
        $action->execute($count->id, $setup['branchless']->id, $barcode, '1', $firstId, 'qa-keyboard');
        $action->execute($count->id, $setup['branchless']->id, $barcode, '1', $firstId, 'qa-keyboard');
        $action->execute($count->id, $setup['branchless']->id, $barcode, '1', (string) Str::uuid(), 'qa-camera');
        self::assertSame(1, $count->lines()->count());
        self::assertSame('2.000000', (string) $count->lines()->firstOrFail()->counted_quantity);
        $line = app(SetStockCountQuantityAction::class)->execute($count->id, $setup['branchless']->id, $barcode, '10', (string) Str::uuid(), 'qa-browser', 'Checked twice');
        self::assertSame('10.000000', (string) $line->counted_quantity);
        self::assertSame('Checked twice', $line->notes);
        try {
            $action->execute($count->id, $setup['branchless']->id, 'UNKNOWN-NOT-A-BARCODE', '1', (string) Str::uuid(), 'qa-keyboard');
            self::fail('Unknown barcode should be rejected.');
        } catch (InvalidArgumentException) {
            self::assertSame('10.000000', (string) $line->fresh()->counted_quantity);
            self::assertSame(1, $count->lines()->count());
        }
    }

    public function test_open_count_does_not_block_transfer_and_failed_form_keeps_old_rows(): void
    {
        $setup = $this->setupInventory();
        $source = $setup['branchless'];
        $product = $setup['product'];
        $destinationA = $setup['store'];
        $destinationB = Store::query()->create(['company_id' => $source->company_id, 'branch_id' => null,
            'code' => 'QA-DST-'.Str::upper(Str::random(6)), 'type' => 'warehouse', 'name_ar' => 'الوجهة',
            'name_en' => 'Destination', 'status' => 'active', 'allows_negative_stock' => false]);
        app(PostInventoryMovement::class)->execute($product->id, $source->id, '100', 'opening_inventory', '10', 'qa-opening-'.Str::uuid());
        $count = $this->openCount($source, $setup['admin']);
        $lines = [['product_id' => $product->id, 'quantity_requested' => '5', 'allocations' => [$destinationA->id => '3', $destinationB->id => '2']]];
        $transfer = app(CreateStockTransferDraftAction::class)->execute($source->id, $destinationA->id, $lines, 'replenishment', 'Keep these notes', 'qa-transfer-'.Str::uuid());
        self::assertSame('draft', $transfer->status);
        self::assertSame(0, StockMovement::query()->where('source_type', $transfer::class)->where('source_id', $transfer->id)->count());
        self::assertSame(2, $transfer->lines()->firstOrFail()->allocations()->count());
        self::assertSame('in_progress', $count->fresh()->status);
        $destinationCount = $this->openCount($destinationB, $setup['admin']);
        $transfer->update(['status' => 'approved', 'approved_by' => $setup['admin']->id, 'approved_at' => now()]);
        app(DispatchStockTransferAction::class)->execute($transfer->id);
        $line = $transfer->fresh(['lines.allocations'])->lines->firstOrFail();
        $a = $line->allocations->firstWhere('destination_store_id', $destinationA->id);
        $b = $line->allocations->firstWhere('destination_store_id', $destinationB->id);
        app(ReceiveStockTransferAction::class)->execute($transfer->id, [$line->id.'.'.$a->id => '3', $line->id.'.'.$b->id => '2'], null, null);
        self::assertSame('in_progress', $count->fresh()->status);
        self::assertSame('in_progress', $destinationCount->fresh()->status);
        self::assertSame('95.000000', (string) StockBalance::query()->where('store_id', $source->id)->where('product_id', $product->id)->value('on_hand'));
        self::assertSame(1, StockMovement::query()->where('source_id', $transfer->id)->where('movement_type', 'transfer_dispatch')->count());

        $badLines = [['product_id' => $product->id, 'quantity_requested' => '101', 'allocations' => [$destinationA->id => '60', $destinationB->id => '42']]];
        $this->from(route('inventory.transfers.create'))->post(route('inventory.transfers.store'), [
            'source_store_id' => $source->id, 'destination_store_id' => $destinationA->id,
            'destination_store_ids' => [$destinationA->id, $destinationB->id], 'reason_code' => 'replenishment',
            'reason_notes' => 'Keep these notes', 'invoice_reference' => 'PIN-QA-1', 'lines' => $badLines,
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect(route('inventory.transfers.create'))
            ->assertSessionHasInput('source_store_id', $source->id)
            ->assertSessionHasInput('destination_store_ids', [$destinationA->id, $destinationB->id])
            ->assertSessionHasInput('lines', $badLines)
            ->assertSessionHasInput('reason_notes', 'Keep these notes')
            ->assertSessionHasInput('invoice_reference', 'PIN-QA-1');

        $retryKey = (string) Str::uuid();
        $validLines = [['product_id' => $product->id, 'quantity_requested' => '2', 'allocations' => [$destinationA->id => '1', $destinationB->id => '1']]];
        $validRequest = ['source_store_id' => $source->id, 'destination_store_id' => $destinationA->id,
            'destination_store_ids' => [$destinationA->id, $destinationB->id], 'reason_code' => 'replenishment',
            'reason_notes' => 'Keep these notes', 'invoice_reference' => 'PIN-QA-1', 'lines' => $validLines,
            'idempotency_key' => $retryKey];
        $this->post(route('inventory.transfers.store'), $validRequest)->assertRedirect();
        $saved = StockTransfer::query()->where('idempotency_key', $retryKey)->firstOrFail();
        self::assertSame('PIN-QA-1', $saved->invoice_reference);
        self::assertSame(2, $saved->lines()->firstOrFail()->allocations()->count());
        $this->post(route('inventory.transfers.store'), $validRequest)->assertRedirect();
        self::assertSame(1, StockTransfer::query()->where('idempotency_key', $retryKey)->count());
    }

    public function test_bulk_load_and_paginated_entry_remain_bounded_for_hundreds_of_products(): void
    {
        $setup = $this->setupInventory();
        $categoryId = $setup['product']->category_id;
        Product::factory()->count(320)->create(['category_id' => $categoryId, 'sale_price' => 20])->each(function (Product $product): void {
            Barcode::query()->create(['product_id' => $product->id, 'barcode' => 'QA'.Str::upper(Str::random(14)),
                'source' => 'local', 'status' => 'active', 'is_primary' => true]);
        });
        $count = $this->openCount($setup['branchless'], $setup['admin']);
        DB::enableQueryLog();
        $added = app(AddAllStockCountProductsAction::class)->execute($count->id, $setup['branchless']->id);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();
        DB::flushQueryLog();
        self::assertGreaterThanOrEqual(321, $added);
        self::assertSame($added, $count->lines()->count());
        self::assertLessThan(30, $queries);
        $this->get(route('inventory.counts.entry', $count))->assertOk()->assertSee((string) $added);
    }

    public function test_hidden_warehouse_and_settlement_require_authorization(): void
    {
        $setup = $this->setupInventory();
        $count = $this->openCount($setup['branchless'], $setup['admin']);
        $other = User::query()->create(['name' => 'QA Count Restricted', 'email' => 'qa-restricted-'.Str::random(8).'@example.test',
            'password' => Str::random(32), 'status' => 'active', 'is_super_admin' => false]);
        Auth::login($other);
        try {
            app(AssertInventoryStoreScope::class)->execute($setup['branchless']->id);
            self::fail('Hidden warehouse should be denied.');
        } catch (AuthorizationException) {
            self::assertTrue(true);
        }
        $this->get(route('inventory.counts.entry', $count))->assertForbidden();
        $this->post(route('inventory.counts.reconcile', $count))->assertForbidden();
    }
}
