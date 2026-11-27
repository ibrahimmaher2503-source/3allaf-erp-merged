<?php

declare(strict_types=1);

namespace App\Modules\Retail\Support;

use App\Modules\Catalog\Models\Product;
use App\Modules\Platform\Models\ScaleConfiguration;
use App\Modules\Pricing\Services\EffectivePriceResolver;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Reads the weight/price label a scale prints (e.g. EAN-13 "20 00001 01647 3": prefix 20,
 * PLU 1, 1.647 kg) using the barcode format saved in scale settings, and resolves it to a
 * product unit and quantity for the POS cart.
 */
final class ScaleLabelBarcode
{
    /** Same defaults as the scale barcode settings form. */
    public const DEFAULTS = [
        'symbology' => 'EAN13', 'prefix' => '20', 'item_source' => 'plu', 'item_digits' => 5,
        'value_type' => 'weight', 'value_digits' => 5, 'value_decimals' => 3,
    ];

    /**
     * @param  array<string, mixed>  $settings
     * @return array{item: string, value: string}|null null when the code is not a scale label in this format
     */
    public static function parse(string $code, array $settings): ?array
    {
        $code = trim($code);
        $settings = array_replace(self::DEFAULTS, $settings);
        $prefix = (string) $settings['prefix'];
        $itemDigits = (int) $settings['item_digits'];
        $valueDigits = (int) $settings['value_digits'];
        $decimals = (int) $settings['value_decimals'];
        $bodyLength = strlen($prefix) + $itemDigits + $valueDigits;
        $isEan = $settings['symbology'] === 'EAN13';
        if (preg_match('/\A\d+\z/', $code) !== 1 || ! str_starts_with($code, $prefix) || strlen($code) !== $bodyLength + ($isEan ? 1 : 0)) {
            return null;
        }
        if ($isEan && (int) $code[12] !== self::eanCheckDigit(substr($code, 0, 12))) {
            return null;
        }
        $item = substr($code, strlen($prefix), $itemDigits);
        $raw = substr($code, strlen($prefix) + $itemDigits, $valueDigits);
        $value = $decimals > 0
            ? ltrim(substr($raw, 0, $valueDigits - $decimals), '0').'.'.substr($raw, $valueDigits - $decimals)
            : ltrim($raw, '0');
        $value = str_starts_with($value, '.') ? '0'.$value : ($value === '' ? '0' : $value);

        return ['item' => $item, 'value' => $value];
    }

    /**
     * Resolves a scanned code to the cart line it represents, or null when it is not a scale label.
     * Throws a user-facing message when it is a valid label that cannot be sold (unmapped PLU, zero weight).
     *
     * @return array{product_id: int, product_unit_id: int|null, quantity: string}|null
     */
    public static function resolve(string $code, int $storeId, ?int $companyId): ?array
    {
        $device = ScaleConfiguration::query()->where('store_id', $storeId)->where('status', 'active')->first()
            ?? ($companyId === null ? null : ScaleConfiguration::query()->where('company_id', $companyId)->where('status', 'active')->first());
        $settings = array_replace(self::DEFAULTS, (array) ($device?->barcode_settings ?? []));
        $parsed = self::parse($code, $settings);
        if ($parsed === null) {
            return null;
        }
        if (bccomp($parsed['value'], '0', 3) <= 0) {
            throw new InvalidArgumentException(__('The scale label has no weight or price.'));
        }

        if ($settings['item_source'] === 'plu') {
            $plu = (int) $parsed['item'];
            $mapping = $device === null ? null : DB::table('scale_product_mappings')->where('scale_configuration_id', $device->id)->where('plu', $plu)->first();
            if ($mapping === null) {
                throw new InvalidArgumentException(__('Scale item :plu is not linked to a product. Link it in the scale settings (key grid).', ['plu' => $plu]));
            }
            $productId = (int) DB::table('product_units')->where('id', $mapping->product_unit_id)->value('product_id');
            $unitId = (int) $mapping->product_unit_id;
        } else {
            $item = ltrim($parsed['item'], '0');
            $product = Product::query()->sellable()->where(fn ($query) => $query->where('item_code', $parsed['item'])->orWhere('item_code', $item))->first();
            if ($product === null) {
                throw new InvalidArgumentException(__('No product has the item code :code from the scale label.', ['code' => $item]));
            }
            $productId = (int) $product->id;
            $unitId = $product->baseProductUnit?->id;
        }

        $quantity = $parsed['value'];
        if ($settings['value_type'] === 'price') {
            $unitPrice = app(EffectivePriceResolver::class)->resolve($productId, $storeId, productUnitId: $unitId)?->amount;
            if ($unitPrice === null || bccomp((string) $unitPrice, '0', 4) <= 0) {
                throw new InvalidArgumentException(__('The selected SKU unit has no positive selling price for this customer and store.'));
            }
            $quantity = bcdiv($parsed['value'], (string) $unitPrice, 3);
        }

        return ['product_id' => $productId, 'product_unit_id' => $unitId, 'quantity' => $quantity];
    }

    private static function eanCheckDigit(string $twelveDigits): int
    {
        $sum = 0;
        foreach (str_split($twelveDigits) as $index => $digit) {
            $sum += (int) $digit * ($index % 2 === 0 ? 1 : 3);
        }

        return (10 - ($sum % 10)) % 10;
    }
}
