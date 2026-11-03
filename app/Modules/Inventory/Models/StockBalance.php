<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Catalog\Models\Product;
use App\Modules\Platform\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class StockBalance extends Model
{
    /**
     * Inventory location rows, including eligible products with no persisted balance.
     * The stock balance remains the only quantity source; missing rows read as zero.
     *
     * @param  list<int>  $storeIds
     */
    public static function reportRows(array $storeIds): Builder
    {
        $products = Product::query()->sellable()->where('products.product_type', 'standard')->select('products.id');
        $rows = DB::table('stores')->crossJoin('products')
            ->leftJoin('stock_balances as actual', fn ($join) => $join->on('actual.store_id', '=', 'stores.id')->on('actual.product_id', '=', 'products.id'))
            ->whereIn('stores.id', $storeIds)->whereNotNull('stores.branch_id')->where('stores.status', 'active')
            ->where(fn ($query) => $query->whereNotNull('actual.id')->orWhereIn('products.id', $products))
            ->where(function ($query): void {
                $query->where(fn ($legacy) => $legacy
                    ->whereNotExists(fn ($assignments) => $assignments->selectRaw('1')->from('product_store_assignments')->whereColumn('product_store_assignments.product_id', 'products.id'))
                    ->where(fn ($tax) => $tax->whereNull('products.tax_setting_id')
                        ->orWhereExists(fn ($settings) => $settings->selectRaw('1')->from('tax_settings')
                            ->whereColumn('tax_settings.id', 'products.tax_setting_id')->whereColumn('tax_settings.company_id', 'stores.company_id'))))
                    ->orWhereExists(fn ($assignments) => $assignments->selectRaw('1')->from('product_store_assignments')->whereColumn('product_store_assignments.product_id', 'products.id')
                        ->whereColumn('product_store_assignments.store_id', 'stores.id')->where('product_store_assignments.status', 'active'));
            })
            ->selectRaw('COALESCE(actual.id, 0) as id, products.id as product_id, stores.id as store_id, COALESCE(actual.on_hand, 0) as on_hand, COALESCE(actual.reserved, 0) as reserved, COALESCE(actual.in_transit, 0) as in_transit, COALESCE(actual.average_cost, 0) as average_cost, COALESCE(actual.total_value, 0) as total_value');

        return static::query()->fromSub($rows, 'stock_balances');
    }

    protected $fillable = [
        'product_id',
        'store_id',
        'on_hand',
        'reserved',
        'in_transit',
        'average_cost',
        'total_value',
        'version',
    ];

    protected $casts = [
        'on_hand' => 'decimal:6',
        'reserved' => 'decimal:6',
        'in_transit' => 'decimal:6',
        'average_cost' => 'decimal:4',
        'total_value' => 'decimal:4',
        'version' => 'integer',
    ];

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
