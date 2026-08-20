<?php

declare(strict_types=1);

namespace Tests\Feature\ClientFeedback;

use App\Modules\Purchasing\Actions\SavePurchaseOrderAction;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Catalog\Actions\SaveSupplierAction;
use App\Modules\Catalog\Actions\SaveCategoryAction;
use App\Modules\Catalog\Actions\SaveProductAction;
use App\Modules\Platform\Models\DocumentSequence;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

final class PurchaseOrderPaymentTermsHookTest extends TestCase
{
    use PlatformFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCanonicalAuthorization();
    }

    public function test_supplier_terms_autofill_then_manual_override_is_preserved(): void
    {
        $this->actingAs($this->administrator('po-terms-admin'));
        $branch = $this->branch('BR-PO-TERMS');
        $store = $this->store($branch, 'ST-PO-TERMS');
        $supplier = app(SaveSupplierAction::class)->execute(['code' => 'SUP-PO-TERMS', 'name_ar' => 'مورد', 'name_en' => 'Supplier', 'status' => 'active']);
        $category = app(SaveCategoryAction::class)->execute(['code' => 'CAT-PO-TERMS', 'name_ar' => 'تصنيف', 'name_en' => 'Category', 'parent_id' => null, 'status' => 'active', 'sort_order' => 0]);
        $product = app(SaveProductAction::class)->execute(['item_code' => 'ITEM-PO-TERMS', 'name_ar' => 'منتج', 'name_en' => 'Product', 'category_id' => $category->id, 'product_type' => 'standard', 'status' => 'active']);
        DocumentSequence::query()->create(['document_type' => 'purchase_order', 'prefix' => 'PO-TERMS-', 'padding_length' => 5, 'next_value' => 1, 'reset_rule' => 'never', 'status' => 'active', 'lock_version' => 1]);
        $supplier->update(['payment_terms' => 'Net 30']);
        $second = Supplier::query()->create(['code' => 'TERMS-SECOND', 'name_ar' => 'مورد ثان', 'name_en' => 'Second supplier', 'payment_terms' => 'Net 60', 'status' => 'active']);

        $component = Livewire::test('purchasing::orders')->call('openCreateModal');
        $component->set('orderForm.supplier_id', (string) $supplier->id);
        self::assertSame('Net 30', $component->get('orderForm.payment_terms'));
        $component->set('orderForm.payment_terms', 'Due on receipt');
        $component->set('orderForm.supplier_id', (string) $second->id);
        self::assertSame('Due on receipt', $component->get('orderForm.payment_terms'));

        $saved = app(SavePurchaseOrderAction::class)->execute(
            ['supplier_id' => $supplier->id, 'store_id' => $store->id, 'payment_terms' => 'Due on receipt'],
            [['product_id' => $product->id, 'quantity_ordered' => 1, 'unit_cost' => 10]],
        );
        self::assertSame('Due on receipt', $saved->payment_terms);

        $this->actingAs($this->userWith('po-terms-cashier-update', ['cashier'], branchIds: [$store->branch_id], storeIds: [$store->id]));
        try {
            app(SavePurchaseOrderAction::class)->execute(
                ['supplier_id' => $supplier->id, 'store_id' => $store->id, 'payment_terms' => 'Unauthorized'],
                [['product_id' => $product->id, 'quantity_ordered' => 1, 'unit_cost' => 10]],
                $saved->id,
                $saved->lock_version,
            );
            self::fail('A cashier must not update purchase-order terms.');
        } catch (AuthorizationException) {
            self::addToAssertionCount(1);
        }
    }

}
