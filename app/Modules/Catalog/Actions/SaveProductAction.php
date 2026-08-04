<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Exceptions\ImmutableItemCodeChangeException;
use App\Modules\Catalog\Exceptions\StaleCatalogRecordException;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Platform\Actions\RecordAuditEvent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class SaveProductAction
{
    /** @param array<string, mixed> $data */
    public function execute(array $data, ?int $id = null, ?int $expectedVersion = null): Product
    {
        Gate::authorize($id ? 'products_categories_brands.edit' : 'products_categories_brands.create');

        try {
            return DB::transaction(function () use ($data, $id, $expectedVersion): Product {
                $itemCode = strtoupper(trim((string) $data['item_code']));
                $category = Category::query()->whereKey((int) $data['category_id'])->first();
                $brand = ! empty($data['brand_id']) ? Brand::query()->find((int) $data['brand_id']) : null;

                if ($category === null || $category->status !== 'active') {
                    throw new InvalidArgumentException(__('The selected category must exist and be active.'));
                }

                if ($data['brand_id'] !== null && $data['brand_id'] !== '' && ($brand === null || $brand->status !== 'active')) {
                    throw new InvalidArgumentException(__('The selected brand must exist and be active.'));
                }

                $product = $id === null ? null : Product::query()->lockForUpdate()->findOrFail($id);

                if ($product !== null && $product->item_code !== $itemCode) {
                    throw new ImmutableItemCodeChangeException(__('The internal item code is immutable after product creation.'));
                }

                if ($product !== null && $expectedVersion !== null && $product->lock_version !== $expectedVersion) {
                    throw new StaleCatalogRecordException(__('This product changed in another session. Reload it before saving.'));
                }

                $attributes = [
                    'item_code' => $itemCode,
                    'name_ar' => trim((string) $data['name_ar']),
                    'name_en' => trim((string) $data['name_en']),
                    'category_id' => (int) $data['category_id'],
                    'brand_id' => ! empty($data['brand_id']) ? (int) $data['brand_id'] : null,
                    'status' => (string) ($data['status'] ?? 'active'),
                ];

                if ($product === null) {
                    $attributes['barcode_mode'] = 'none';
                    $attributes['lock_version'] = 0;
                    $product = Product::query()->create($attributes);
                    $event = 'create_product';
                    $before = null;
                } else {
                    $before = $product->only(['item_code', 'name_ar', 'name_en', 'category_id', 'brand_id', 'status', 'barcode_mode', 'lock_version']);
                    $product->update([
                        ...$attributes,
                        'lock_version' => $product->lock_version + 1,
                    ]);
                    $event = 'update_product_identity';
                }

                app(RecordAuditEvent::class)->execute(
                    category: 'master_data',
                    event: $event,
                    source: $product,
                    before: $before,
                    after: $product->fresh()->only(['item_code', 'name_ar', 'name_en', 'category_id', 'brand_id', 'status', 'barcode_mode', 'lock_version']),
                );

                return $product->fresh();
            });
        } catch (ImmutableItemCodeChangeException $exception) {
            $product = $id === null ? null : Product::query()->find($id);

            if ($product !== null) {
                app(RecordAuditEvent::class)->execute(
                    category: 'master_data',
                    event: 'attempted_immutable_item_code_change',
                    source: $product,
                    before: ['item_code' => $product->item_code],
                    after: ['item_code' => strtoupper(trim((string) $data['item_code']))],
                    metadata: ['outcome' => 'denied'],
                );
            }

            throw $exception;
        }
    }

    public function toggleStatus(int $id): Product
    {
        Gate::authorize('products_categories_brands.edit');

        return DB::transaction(function () use ($id): Product {
            $product = Product::query()->lockForUpdate()->findOrFail($id);
            $before = ['status' => $product->status, 'lock_version' => $product->lock_version];
            $product->update([
                'status' => $product->status === 'active' ? 'inactive' : 'active',
                'lock_version' => $product->lock_version + 1,
            ]);

            app(RecordAuditEvent::class)->execute(
                category: 'master_data',
                event: 'toggle_product_status',
                source: $product,
                before: $before,
                after: ['status' => $product->status, 'lock_version' => $product->lock_version],
            );

            return $product->fresh();
        });
    }
}
