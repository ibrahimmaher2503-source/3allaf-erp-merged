<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Modules\Catalog\Actions\SaveCategoryAction;
use App\Modules\Catalog\Actions\SaveProductAction;
use App\Modules\Catalog\Actions\SaveSupplierAction;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Models\DocumentSequence;
use App\Modules\Purchasing\Actions\ApprovePurchaseOrderAction;
use App\Modules\Purchasing\Actions\CancelPurchaseOrderAction;
use App\Modules\Purchasing\Actions\ClosePurchaseOrderAction;
use App\Modules\Purchasing\Actions\SavePurchaseOrderAction;
use App\Modules\Purchasing\Actions\SubmitPurchaseOrderAction;
use App\Modules\Purchasing\Models\PurchaseOrder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

final class PurchaseOrderLifecycleTest extends TestCase
{
    use PlatformFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCanonicalAuthorization();
        $this->actingAs($this->administrator('po-lifecycle-admin'));
    }

    public function test_draft_edit_calculates_fixed_precision_and_rejects_excess_precision(): void
    {
        [$supplier, $product, $store] = $this->masterData('precision');
        $this->sequence();

        $order = app(SavePurchaseOrderAction::class)->execute(
            ['supplier_id' => $supplier->id, 'store_id' => $store->id],
            [['product_id' => $product->id, 'quantity_ordered' => '0.123456', 'unit_cost' => '12.3456']],
        );

        $line = $order->lines->firstOrFail();
        self::assertSame('0.123456', (string) $line->quantity_ordered);
        self::assertSame('12.3456', (string) $line->unit_cost);
        self::assertSame('1.5241', (string) $line->subtotal);
        self::assertSame('1.5241', (string) $order->total_amount);

        $edited = app(SavePurchaseOrderAction::class)->execute(
            ['supplier_id' => $supplier->id, 'store_id' => $store->id],
            [['product_id' => $product->id, 'quantity_ordered' => '2.000000', 'unit_cost' => '10.0000']],
            $order->id,
            $order->lock_version,
        );
        self::assertSame('20.0000', (string) $edited->total_amount);
        self::assertSame($order->lock_version + 1, $edited->lock_version);

        $this->expectException(InvalidArgumentException::class);
        app(SavePurchaseOrderAction::class)->execute(
            ['supplier_id' => $supplier->id, 'store_id' => $store->id],
            [['product_id' => $product->id, 'quantity_ordered' => '0.1234567', 'unit_cost' => '1.0000']],
        );
    }

    public function test_lifecycle_enforces_stale_versions_separation_immutability_cancel_reason_close_and_audit(): void
    {
        [$supplier, $product, $store] = $this->masterData('lifecycle');
        $this->sequence();
        $creator = $this->administrator('po-requester-lifecycle');
        $approver = $this->administrator('po-approver-lifecycle');

        $this->actingAs($creator);
        $order = app(SavePurchaseOrderAction::class)->execute(
            ['supplier_id' => $supplier->id, 'store_id' => $store->id],
            [['product_id' => $product->id, 'quantity_ordered' => '3', 'unit_cost' => '12.50']],
        );
        $edited = app(SavePurchaseOrderAction::class)->execute(
            ['supplier_id' => $supplier->id, 'store_id' => $store->id, 'notes' => 'edited draft'],
            [['product_id' => $product->id, 'quantity_ordered' => '3', 'unit_cost' => '12.50']],
            $order->id,
            $order->lock_version,
        );

        $this->expectException(InvalidArgumentException::class);
        app(SubmitPurchaseOrderAction::class)->execute($edited->id, $order->lock_version);
        $this->assertSame('draft', $edited->fresh()->status);

        $submitted = app(SubmitPurchaseOrderAction::class)->execute($edited->id, $edited->lock_version);
        self::assertSame('submitted', $submitted->status);

        $this->actingAs($creator);
        try {
            app(ApprovePurchaseOrderAction::class)->execute($submitted->id, $submitted->lock_version);
            self::fail('A requester must not approve their own purchase order.');
        } catch (ValidationException) {
            self::addToAssertionCount(1);
        }

        $this->actingAs($approver);
        $approved = app(ApprovePurchaseOrderAction::class)->execute($submitted->id, $submitted->lock_version);
        self::assertSame('approved', $approved->status);
        self::assertNotNull(AuditLog::query()->where('event', 'approve_purchase_order')->where('source_id', (string) $approved->id)->first());

        $this->expectException(InvalidArgumentException::class);
        app(SavePurchaseOrderAction::class)->execute(
            ['supplier_id' => $supplier->id, 'store_id' => $store->id],
            [['product_id' => $product->id, 'quantity_ordered' => '4', 'unit_cost' => '12.50']],
            $approved->id,
            $approved->lock_version,
        );
    }

    public function test_cancellation_requires_reason_and_close_is_limited_to_approved(): void
    {
        [$supplier, $product, $store] = $this->masterData('terminal');
        $this->sequence();
        $creator = $this->administrator('po-cancel-creator');
        $approver = $this->administrator('po-close-approver');
        $this->actingAs($creator);

        $cancelled = app(SavePurchaseOrderAction::class)->execute(
            ['supplier_id' => $supplier->id, 'store_id' => $store->id],
            [['product_id' => $product->id, 'quantity_ordered' => '1', 'unit_cost' => '4']],
        );
        try {
            app(CancelPurchaseOrderAction::class)->execute($cancelled->id, '   ', $cancelled->lock_version);
            self::fail('Cancellation without a reason must fail.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('reason', strtolower($exception->getMessage()));
        }
        $cancelled = app(CancelPurchaseOrderAction::class)->execute($cancelled->id, 'Supplier discontinued the item.', $cancelled->lock_version);
        self::assertSame('cancelled', $cancelled->status);
        self::assertNotNull(AuditLog::query()->where('event', 'cancel_purchase_order')->where('source_id', (string) $cancelled->id)->first());

        $submitted = app(SavePurchaseOrderAction::class)->execute(
            ['supplier_id' => $supplier->id, 'store_id' => $store->id],
            [['product_id' => $product->id, 'quantity_ordered' => '1', 'unit_cost' => '4']],
        );
        $submitted = app(SubmitPurchaseOrderAction::class)->execute($submitted->id, $submitted->lock_version);
        try {
            app(ClosePurchaseOrderAction::class)->execute($submitted->id, $submitted->lock_version);
            self::fail('A submitted order must not be closed.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('approved', strtolower($exception->getMessage()));
        }

        $this->actingAs($approver);
        $approved = app(ApprovePurchaseOrderAction::class)->execute($submitted->id, $submitted->lock_version);
        $closed = app(ClosePurchaseOrderAction::class)->execute($approved->id, $approved->lock_version);
        self::assertSame('closed', $closed->status);
        self::assertNotNull(AuditLog::query()->where('event', 'close_purchase_order')->where('source_id', (string) $closed->id)->first());
    }

    public function test_scoped_actor_cannot_create_or_transition_a_foreign_store_order(): void
    {
        [$supplier, $product, $foreignStore] = $this->masterData('foreign');
        $ownBranch = $this->branch('BR-own-scope');
        $ownStore = $this->store($ownBranch, 'ST-own-scope');
        $this->sequence();
        $scopedAdmin = $this->userWith('po-scoped-admin', ['system-administrator'], false, [$ownBranch->id]);
        $this->actingAs($scopedAdmin);

        try {
            app(SavePurchaseOrderAction::class)->execute(
                ['supplier_id' => $supplier->id, 'store_id' => $foreignStore->id],
                [['product_id' => $product->id, 'quantity_ordered' => '1', 'unit_cost' => '5']],
            );
            self::fail('A scoped actor must not create an order for a foreign store.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('store', strtolower($exception->getMessage()));
        }

        $this->actingAs($this->administrator('po-foreign-owner'));
        $foreignDraft = app(SavePurchaseOrderAction::class)->execute(
            ['supplier_id' => $supplier->id, 'store_id' => $foreignStore->id],
            [['product_id' => $product->id, 'quantity_ordered' => '1', 'unit_cost' => '5']],
        );
        $foreignSubmitted = app(SubmitPurchaseOrderAction::class)->execute($foreignDraft->id, $foreignDraft->lock_version);

        $this->actingAs($scopedAdmin);
        foreach ([
            fn () => app(SubmitPurchaseOrderAction::class)->execute($foreignDraft->id, $foreignDraft->lock_version),
            fn () => app(CancelPurchaseOrderAction::class)->execute($foreignDraft->id, 'Out of scope.', $foreignDraft->lock_version),
            fn () => app(ApprovePurchaseOrderAction::class)->execute($foreignSubmitted->id, $foreignSubmitted->lock_version),
        ] as $attempt) {
            try {
                $attempt();
                self::fail('A scoped actor must not transition a foreign-store purchase order.');
            } catch (InvalidArgumentException $exception) {
                self::assertStringContainsString('store', strtolower($exception->getMessage()));
            }
        }

        $own = app(SavePurchaseOrderAction::class)->execute(
            ['supplier_id' => $supplier->id, 'store_id' => $ownStore->id],
            [['product_id' => $product->id, 'quantity_ordered' => '1', 'unit_cost' => '5']],
        );
        self::assertSame($ownStore->id, $own->store_id);
    }

    public function test_no_access_actor_is_denied_before_purchase_order_mutation(): void
    {
        [$supplier, $product, $store] = $this->masterData('denied');
        $this->sequence();
        $this->actingAs($this->userWith('po-no-access', []));

        $this->expectException(AuthorizationException::class);
        app(SavePurchaseOrderAction::class)->execute(
            ['supplier_id' => $supplier->id, 'store_id' => $store->id],
            [['product_id' => $product->id, 'quantity_ordered' => '1', 'unit_cost' => '5']],
        );
        self::assertSame(0, PurchaseOrder::query()->count());
    }

    /** @return array{0: Supplier, 1: Product, 2: Store} */
    private function masterData(string $suffix): array
    {
        $branch = $this->branch('BR-PO-'.$suffix);
        $store = $this->store($branch, 'ST-PO-'.$suffix);
        $supplier = app(SaveSupplierAction::class)->execute([
            'code' => 'SUP-PO-'.$suffix,
            'name_ar' => 'مورد '.$suffix,
            'name_en' => 'Supplier '.$suffix,
            'status' => 'active',
        ]);
        $category = app(SaveCategoryAction::class)->execute([
            'code' => 'CAT-PO-'.$suffix,
            'name_ar' => 'تصنيف '.$suffix,
            'name_en' => 'Category '.$suffix,
            'parent_id' => null,
            'status' => 'active',
            'sort_order' => 0,
        ]);
        $product = app(SaveProductAction::class)->execute([
            'item_code' => 'ITEM-PO-'.$suffix,
            'name_ar' => 'منتج '.$suffix,
            'name_en' => 'Product '.$suffix,
            'category_id' => $category->id,
            'product_type' => 'standard',
            'status' => 'active',
        ]);

        return [$supplier, $product, $store];
    }

    private function sequence(): void
    {
        DocumentSequence::query()->create([
            'document_type' => 'purchase_order',
            'prefix' => 'PO-TEST-',
            'padding_length' => 5,
            'next_value' => 1,
            'reset_rule' => 'never',
            'status' => 'active',
            'lock_version' => 1,
        ]);
    }
}
