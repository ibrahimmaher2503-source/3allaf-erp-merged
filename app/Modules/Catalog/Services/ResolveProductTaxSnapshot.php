<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Platform\Models\TaxSetting;

final class ResolveProductTaxSnapshot
{
    /** @return array{tax_rate: numeric-string, tax_code: null|string, tax_inclusive: bool} */
    public function execute(Product $product): array
    {
        $effective = fn ($query) => $query
            ->where('status', 'active')
            ->whereNotNull('rate')
            ->where(fn ($dates) => $dates->whereNull('effective_from')->orWhere('effective_from', '<=', now()))
            ->where(fn ($dates) => $dates->whereNull('effective_to')->orWhere('effective_to', '>=', now()));

        $tax = $product->tax_setting_id === null
            ? null
            : $effective(TaxSetting::query()->whereKey($product->tax_setting_id))->first();

        $tax ??= $effective(TaxSetting::query()->where('is_default', true))->first();

        return [
            'tax_rate' => bcadd((string) ($tax?->rate ?? '0'), '0', 4),
            'tax_code' => $tax?->code,
            'tax_inclusive' => (bool) ($tax?->is_tax_inclusive ?? false),
        ];
    }
}
