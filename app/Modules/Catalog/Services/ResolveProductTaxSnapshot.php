<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Platform\Models\TaxSetting;
use InvalidArgumentException;

final class ResolveProductTaxSnapshot
{
    /** @return array{tax_rate: numeric-string, tax_code: null|string, tax_inclusive: bool} */
    public function execute(Product $product, int $companyId): array
    {
        $effective = fn ($query) => $query
            ->where('status', 'active')
            ->whereNotNull('rate')
            ->where(fn ($dates) => $dates->whereNull('effective_from')->orWhere('effective_from', '<=', now()))
            ->where(fn ($dates) => $dates->whereNull('effective_to')->orWhere('effective_to', '>=', now()));

        $tax = null;
        if ($product->tax_setting_id !== null) {
            $tax = $effective(TaxSetting::query()->whereKey($product->tax_setting_id)->where('company_id', $companyId))->first();
            if ($tax === null) {
                throw new InvalidArgumentException(__('The assigned product tax is not active and valid for the authorized company.'));
            }
        }

        if ($tax === null) {
            $defaults = $effective(TaxSetting::query()->where('company_id', $companyId)->where('is_default', true))->limit(2)->get();
            if ($defaults->count() > 1) {
                throw new InvalidArgumentException(__('The authorized company has more than one effective default tax rule.'));
            }
            $tax = $defaults->first();
            if ($tax === null) {
                throw new InvalidArgumentException(__('The authorized company has no effective default tax rule with an explicit rate.'));
            }
        }

        return [
            'tax_rate' => bcadd((string) ($tax?->rate ?? '0'), '0', 4),
            'tax_code' => $tax?->code,
            'tax_inclusive' => (bool) ($tax?->is_tax_inclusive ?? false),
        ];
    }
}
