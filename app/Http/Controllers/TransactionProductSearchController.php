<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TransactionProductSearchController extends Controller
{
    private const ABILITIES = [
        'purchase_orders.create', 'purchase_orders.edit',
        'purchase_invoices.draft', 'purchase_invoices_supplier_returns.create', 'purchase_invoices_supplier_returns.edit',
        'purchase_returns.create', 'purchase_returns.edit',
        'transfers.create', 'transfers.edit', 'inventory_stock_card.create', 'inventory_stock_card.edit', 'stock_counts.participate',
        'pos_sales.create', 'returns.create',
        'quotations.create', 'quotations.edit',
        'party_bookings_invoices.create', 'party_bookings_invoices.edit',
        'party_operating_orders_consumables.create', 'party_operating_orders_consumables.edit',
    ];

    public function __invoke(Request $request): JsonResponse
    {
        /** @var User|null $actor */
        $actor = $request->user();
        abort_unless($actor instanceof User && ($actor->is_super_admin || collect(self::ABILITIES)->contains(fn (string $ability): bool => $actor->can($ability))), 403);

        $term = trim((string) $request->query('q', ''));
        if ($term === '' || mb_strlen($term) > 190) {
            return response()->json(['data' => []]);
        }

        $escaped = addcslashes($term, '\\%_');
        $prefix = $escaped.'%';
        $contained = '%'.$escaped.'%';
        $tokens = collect(preg_split('/\s+/u', $term) ?: [])->filter()->take(4);

        $products = Product::query()->sellable()
            ->with(['barcodes' => fn ($query) => $query->active()->orderByDesc('is_primary')])
            ->where(function ($query) use ($term, $prefix, $contained, $tokens): void {
                $query->where('item_code', $term)->orWhere('model_number', $term)
                    ->orWhere('item_code', 'like', $prefix)->orWhere('model_number', 'like', $prefix)
                    ->orWhereHas('barcodes', fn ($barcodes) => $barcodes->active()->where(fn ($barcode) => $barcode->where('barcode', 'like', $prefix)->orWhere('barcode', 'like', $contained)))
                    ->orWhere(function ($names) use ($tokens): void {
                        foreach ($tokens as $token) {
                            $value = addcslashes((string) $token, '\\%_');
                            $names->orWhere('name_ar', 'like', '%'.$value.'%')->orWhere('name_en', 'like', '%'.$value.'%');
                        }
                    })
                    ->orWhere('item_code', 'like', $contained)->orWhere('model_number', 'like', $contained);
            })
            ->orderByRaw('CASE WHEN item_code = ? OR model_number = ? OR EXISTS (SELECT 1 FROM barcodes WHERE barcodes.product_id = products.id AND barcodes.status = ? AND barcodes.barcode = ?) THEN 0 WHEN item_code LIKE ? OR model_number LIKE ? THEN 1 ELSE 2 END', [$term, $term, 'active', $term, $prefix, $prefix])
            ->orderBy('item_code')
            ->limit(20)
            ->get();

        $mayViewCost = $actor->can('products_categories_brands.cost_view')
            || collect(['purchase_orders.create', 'purchase_orders.edit', 'purchase_invoices.draft', 'purchase_returns.create', 'purchase_returns.edit', 'inventory_stock_card.create', 'inventory_stock_card.edit', 'transfers.create', 'transfers.edit'])
                ->contains(fn (string $ability): bool => $actor->can($ability));

        return response()->json(['data' => $products->map(function (Product $product) use ($term, $mayViewCost): array {
            $barcodes = $product->barcodes->pluck('barcode')->filter()->values();
            $identifiers = collect([$product->item_code, $product->model_number])->merge($barcodes)->filter()->map(fn ($value) => mb_strtolower(trim((string) $value)))->unique()->values();

            return [
                'id' => $product->id,
                'item_code' => $product->item_code,
                'model_number' => $product->model_number,
                'name_ar' => $product->name_ar,
                'name_en' => $product->name_en,
                'barcodes' => $barcodes,
                'unit_cost' => $mayViewCost ? $product->average_cost : null,
                'unit_price' => $product->sale_price,
                'exact' => $identifiers->contains(mb_strtolower($term)),
            ];
        })->values()]);
    }
}
