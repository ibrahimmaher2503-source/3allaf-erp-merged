<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Exceptions\ImmutableItemCodeChangeException;
use App\Modules\Catalog\Exceptions\StaleCatalogRecordException;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Platform\Actions\RecordAuditEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class SaveProductAction
{
    /** @var array<int, string> */
    private const PRODUCT_TYPES = ['standard', 'composite', 'service'];

    /** @param array<string, mixed> $data */
    public function execute(array $data, ?int $id = null, ?int $expectedVersion = null): Product
    {
        Gate::authorize($id ? 'products_categories_brands.edit' : 'products_categories_brands.create');

        try {
            return DB::transaction(function () use ($data, $id, $expectedVersion): Product {
                $itemCode = strtoupper(trim((string) ($data['item_code'] ?? '')));
                $nameAr = trim((string) ($data['name_ar'] ?? ''));
                $nameEn = trim((string) ($data['name_en'] ?? ''));
                $productType = trim((string) ($data['product_type'] ?? 'standard'));
                $status = trim((string) ($data['status'] ?? 'active'));

                if ($nameAr === '' || $nameEn === '') {
                    throw new InvalidArgumentException(__('Both Arabic and English names are required for the product card.'));
                }

                if (! in_array($productType, self::PRODUCT_TYPES, true)) {
                    throw new InvalidArgumentException(__('The selected product type is not supported.'));
                }

                if (! in_array($status, ['active', 'inactive'], true)) {
                    throw new InvalidArgumentException(__('The selected product status is not supported.'));
                }

                $category = Category::query()->whereKey((int) ($data['category_id'] ?? 0))->first();
                $brand = ! empty($data['brand_id']) ? Brand::query()->find((int) $data['brand_id']) : null;

                if ($category === null || $category->status !== 'active') {
                    throw new InvalidArgumentException(__('The selected category must exist and be active.'));
                }

                if (! empty($data['brand_id']) && ($brand === null || $brand->status !== 'active')) {
                    throw new InvalidArgumentException(__('The selected brand must exist and be active.'));
                }

                $product = $id === null ? null : Product::query()->lockForUpdate()->findOrFail($id);

                if ($product !== null && $product->item_code !== $itemCode) {
                    throw new ImmutableItemCodeChangeException(__('The internal item code is immutable after product creation.'));
                }

                if ($product !== null && $expectedVersion !== null && $product->lock_version !== $expectedVersion) {
                    throw new StaleCatalogRecordException(__('This product changed in another session. Reload it before saving.'));
                }

                if ($product !== null && ! array_key_exists('product_type', $data)) {
                    $productType = $product->product_type;
                }

                $attributes = [
                    'item_code' => $itemCode,
                    'name_ar' => $nameAr,
                    'name_en' => $nameEn,
                    'description_ar' => $this->nullableString($data['description_ar'] ?? null),
                    'description_en' => $this->nullableString($data['description_en'] ?? null),
                    'model_number' => $this->nullableString($data['model_number'] ?? null),
                    'product_type' => $productType,
                    'unit_of_measure' => $this->nullableString($data['unit_of_measure'] ?? null),
                    'category_id' => (int) $data['category_id'],
                    'brand_id' => ! empty($data['brand_id']) ? (int) $data['brand_id'] : null,
                    'status' => $status,
                    'reorder_threshold' => $productType === 'service' ? null : $this->nullableNumeric($data['reorder_threshold'] ?? null),
                    'dimension_length' => $this->nullableNumeric($data['dimension_length'] ?? null),
                    'dimension_width' => $this->nullableNumeric($data['dimension_width'] ?? null),
                    'dimension_height' => $this->nullableNumeric($data['dimension_height'] ?? null),
                    'dimension_unit' => $this->nullableString($data['dimension_unit'] ?? null),
                    'weight' => $this->nullableNumeric($data['weight'] ?? null),
                    'target_age' => $this->nullableString($data['target_age'] ?? null),
                    'suitable_gender' => $this->nullableString($data['suitable_gender'] ?? null),
                    'colour' => $this->nullableString($data['colour'] ?? null),
                    'size' => $this->nullableString($data['size'] ?? null),
                    'character' => $this->nullableString($data['character'] ?? null),
                    'key_points_ar' => $this->nullableString($data['key_points_ar'] ?? null),
                    'key_points_en' => $this->nullableString($data['key_points_en'] ?? null),
                    'keywords_ar' => $this->nullableString($data['keywords_ar'] ?? null),
                    'keywords_en' => $this->nullableString($data['keywords_en'] ?? null),
                    'fractional_quantity' => (bool) ($data['fractional_quantity'] ?? false),
                ];

                // DEC-038 has no catalog cost-field grant. Keep the field available in the
                // contract for later approved work, but never accept a normal forged mutation.
                if ($product === null) {
                    $attributes['average_cost'] = auth()->user()?->hasPermission('products_categories_brands.cost_view')
                        ? $this->nullableNumeric($data['average_cost'] ?? null)
                        : null;
                } elseif (auth()->user()?->hasPermission('products_categories_brands.cost_view')) {
                    $attributes['average_cost'] = $this->nullableNumeric($data['average_cost'] ?? $product->average_cost);
                }

                if ($product !== null) {
                    foreach ([
                        'description_ar', 'description_en', 'model_number', 'unit_of_measure', 'average_cost',
                        'reorder_threshold', 'dimension_length', 'dimension_width', 'dimension_height',
                        'dimension_unit', 'weight', 'target_age', 'suitable_gender', 'colour', 'size', 'character',
                        'key_points_ar', 'key_points_en', 'keywords_ar', 'keywords_en', 'fractional_quantity',
                    ] as $optionalField) {
                        if (! array_key_exists($optionalField, $data)) {
                            unset($attributes[$optionalField]);
                        }
                    }
                }

                if ($product === null) {
                    $attributes['barcode_mode'] = 'none';
                    $attributes['lock_version'] = 0;
                    $product = Product::query()->create($attributes);
                    $event = 'create_product_card';
                    $before = null;
                } else {
                    $before = $this->auditValues($product);
                    $previousType = $product->product_type;
                    $product->update([
                        ...$attributes,
                        'lock_version' => $product->lock_version + 1,
                    ]);
                    $event = $previousType !== $productType ? 'change_product_type' : 'update_product_card';
                }

                app(RecordAuditEvent::class)->execute(
                    category: 'master_data',
                    event: $event,
                    source: $product,
                    before: $before,
                    after: $this->auditValues($product->fresh()),
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
                    after: ['item_code' => strtoupper(trim((string) ($data['item_code'] ?? '')))],
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

    /** @return array<string, mixed> */
    private function auditValues(Product $product): array
    {
        return $product->only([
            'item_code', 'name_ar', 'name_en', 'description_ar', 'description_en', 'model_number',
            'product_type', 'unit_of_measure', 'category_id', 'brand_id', 'status', 'barcode_mode',
            'average_cost', 'reorder_threshold', 'dimension_length', 'dimension_width', 'dimension_height',
            'dimension_unit', 'weight', 'target_age', 'suitable_gender', 'colour', 'size', 'character',
            'key_points_ar', 'key_points_en', 'keywords_ar', 'keywords_en', 'fractional_quantity',
            'lock_version',
        ]);
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function nullableNumeric(mixed $value): float|int|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value) || (float) $value < 0) {
            throw new InvalidArgumentException(__('Numeric product values must be zero or greater.'));
        }

        return (float) $value;
    }
}
