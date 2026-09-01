<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support;

use App\Modules\Catalog\Models\Product;
use App\Modules\Platform\Models\Company;
use App\Modules\Pricing\Models\PriceList;
use App\Modules\Pricing\Models\ProductPriceOverride;
final class ProductPricingReadiness
{
    /** @return array<string,mixed> */
    public function snapshot(?int $companyId = null, int $displayLimit = 8): array
    {
        $companyId ??= (int) Company::query()->where('status', 'active')->value('id');
        $products = Product::query()->sellable()->with('category:id,status')->orderBy('item_code')->get([
            'id','item_code','name_ar','name_en','model_number','category_id','sale_price','average_cost','barcode_registration_type','product_type','status','parent_product_id','has_variations',
        ]);
        $assessment = $this->assessProducts($products, $displayLimit);
        $base = $companyId > 0 ? PriceList::query()->where('company_id', $companyId)->where('list_number', 0)->first() : null;
        $overrideCount = $companyId > 0 ? ProductPriceOverride::query()->where('company_id', $companyId)
            ->where(fn ($query) => $query->whereNull('effective_from')->orWhere('effective_from', '<=', today()))
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>=', today()))->count() : 0;

        return [
            ...$assessment,
            'company_id' => $companyId,
            'base_list_complete' => (bool) $base?->isEffective(),
            'pricing_prerequisite_complete' => $assessment['product_cards_complete'],
            'inherited_count' => $assessment['valid_price_count'],
            'effective_count' => $assessment['valid_price_count'],
            'manual_override_count' => $overrideCount,
        ];
    }

    /** @param iterable<int,Product|array<string,mixed>> $products @return array<string,mixed> */
    public function assessProducts(iterable $products, int $displayLimit = 8): array
    {
        $rows = collect($products)->map(function (Product|array $product): array {
            $value = static fn (string $key) => is_array($product) ? ($product[$key] ?? null) : $product->{$key};
            $categoryActive = is_array($product) ? (bool) ($product['category_active'] ?? true) : ($product->category?->status === 'active');
            $price = $value('sale_price');
            $issues = [];
            if ($price === null || ! is_numeric($price) || (float) $price <= 0) $issues[] = 'invalid_base_price';
            if (trim((string) $value('item_code')) === '') $issues[] = 'missing_item_code';
            if (trim((string) $value('name_ar')) === '') $issues[] = 'missing_arabic_name';
            if (trim((string) $value('model_number')) === '') $issues[] = 'missing_model_number';
            if (! $value('category_id') || ! $categoryActive) $issues[] = 'missing_active_category';
            if (! in_array((string) $value('barcode_registration_type'), ['international','local'], true)) $issues[] = 'missing_barcode_mode';

            return [
                'id' => (int) $value('id'),
                'item_code' => (string) $value('item_code'),
                'name_ar' => (string) $value('name_ar'),
                'name_en' => (string) $value('name_en'),
                'sale_price' => $price,
                'issues' => $issues,
                'issue_message' => in_array('invalid_base_price', $issues, true) ? __('Base consumer price is required and must be greater than zero.') : null,
            ];
        })->values();
        $affected = $rows->filter(fn (array $row): bool => $row['issues'] !== [])->values();
        $invalidPrices = $rows->filter(fn (array $row): bool => in_array('invalid_base_price', $row['issues'], true))->values();
        $complete = $rows->isNotEmpty() && $affected->isEmpty();

        return [
            'applicable_count' => $rows->count(),
            'affected_count' => $affected->count(),
            'invalid_price_count' => $invalidPrices->count(),
            'affected_products' => $affected->take($displayLimit)->all(),
            'has_more_affected' => $affected->count() > $displayLimit,
            'all_affected_products' => $affected->all(),
            'valid_price_count' => $rows->count() - $invalidPrices->count(),
            'product_cards_complete' => $complete,
            'pricing_products_complete' => $complete,
        ];
    }
}
