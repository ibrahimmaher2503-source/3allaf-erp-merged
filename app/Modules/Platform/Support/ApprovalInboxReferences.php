<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support;

use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\InventoryAdjustment;
use App\Modules\Inventory\Models\StockCount;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Models\PurchaseReturn;
use App\Support\HumanName;
use Illuminate\Support\Collection;

final class ApprovalInboxReferences
{
    /** @var array<string, array{model: class-string, number: string, amount: string|null}> */
    private const SOURCES = [
        'purchase_orders' => ['model' => PurchaseOrder::class, 'number' => 'po_number', 'amount' => 'total_amount'],
        'purchase_invoices' => ['model' => PurchaseInvoice::class, 'number' => 'invoice_number', 'amount' => 'total_amount'],
        'purchase_returns' => ['model' => PurchaseReturn::class, 'number' => 'return_number', 'amount' => 'total_amount'],
        'stock_transfers' => ['model' => StockTransfer::class, 'number' => 'transfer_number', 'amount' => null],
        'stock_counts' => ['model' => StockCount::class, 'number' => 'count_number', 'amount' => null],
        'inventory_adjustments' => ['model' => InventoryAdjustment::class, 'number' => 'adjustment_number', 'amount' => null],
    ];

    /** @var array<string, array<int, object>> */
    private array $sources = [];

    /** @var array<int, Product> */
    private array $products = [];

    /** @param Collection<int, ApprovalRecord> $approvals */
    public function __construct(Collection $approvals)
    {
        foreach (self::SOURCES as $type => $source) {
            $ids = $approvals->where('source_type', $type)->pluck('source_id')->filter()->unique();
            if ($ids->isEmpty()) {
                continue;
            }
            $columns = array_filter(['id', $source['number'], $source['amount']]);
            $this->sources[$type] = $source['model']::query()->whereIn('id', $ids)->get($columns)->keyBy('id')->all();
        }
        $productIds = $approvals->where('source_type', 'pos_discount')
            ->map(fn (ApprovalRecord $approval): int => (int) ($approval->limit_context['product_id'] ?? 0))
            ->filter()->unique();
        if ($productIds->isNotEmpty()) {
            $this->products = Product::query()->whereIn('id', $productIds)->get(['id', 'item_code', 'name_ar', 'name_en'])->keyBy('id')->all();
        }
    }

    public function label(ApprovalRecord $approval): string
    {
        if ($approval->source_type === 'pos_discount') {
            $product = $this->products[(int) ($approval->limit_context['product_id'] ?? 0)] ?? null;

            return __('POS discount request').($product ? ' · '.HumanName::name($product).' ('.$product->item_code.')' : '');
        }
        $source = self::SOURCES[$approval->source_type] ?? null;
        $record = $this->sources[$approval->source_type][(int) $approval->source_id] ?? null;
        if ($source !== null && $record !== null) {
            return ApprovalPresentation::source($approval->source_type).' · '.$record->{$source['number']};
        }

        return ApprovalPresentation::source($approval->source_type, $approval->source_id);
    }

    public function amount(ApprovalRecord $approval): ?string
    {
        if ($approval->source_type === 'pos_discount') {
            $amount = $approval->limit_context['discount_amount'] ?? null;

            return is_scalar($amount) ? (string) $amount : null;
        }
        $source = self::SOURCES[$approval->source_type] ?? null;
        $record = $this->sources[$approval->source_type][(int) $approval->source_id] ?? null;
        if ($source === null || $source['amount'] === null || $record === null) {
            return null;
        }

        return (string) $record->{$source['amount']};
    }
}
