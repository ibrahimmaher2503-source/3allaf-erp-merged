<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Support;

use App\Modules\Inventory\Models\InventoryAdjustment;
use App\Modules\Inventory\Models\OpeningInventoryDocument;
use App\Modules\Inventory\Models\StockCount;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Platform\Support\AuditLogPresentation;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use App\Modules\Purchasing\Models\PurchaseReturn;
use App\Modules\Retail\Models\RetailReturn;
use App\Modules\Retail\Models\Sale;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

final class StockMovementSourceReferences
{
    /** @var array<string, array{field: string, label: string, route: string, permission: string}> */
    private const SOURCES = [
        Sale::class => ['field' => 'document_number', 'label' => 'Sale', 'route' => 'sales.show', 'permission' => 'pos_sales.view'],
        PurchaseInvoice::class => ['field' => 'invoice_number', 'label' => 'Purchase invoice', 'route' => 'purchasing.invoices', 'permission' => 'purchase_invoices_supplier_returns.view'],
        PurchaseReturn::class => ['field' => 'return_number', 'label' => 'Purchase return', 'route' => 'purchasing.returns.show', 'permission' => 'purchase_returns.view'],
        RetailReturn::class => ['field' => 'return_number', 'label' => 'Sales return', 'route' => 'returns.show', 'permission' => 'returns.view'],
        StockTransfer::class => ['field' => 'transfer_number', 'label' => 'Transfer', 'route' => 'inventory.transfers.dispatch-page', 'permission' => 'transfers.view'],
        StockCount::class => ['field' => 'count_number', 'label' => 'Stock count', 'route' => 'inventory.counts.entry', 'permission' => 'stock_counts.view'],
        InventoryAdjustment::class => ['field' => 'adjustment_number', 'label' => 'Inventory adjustment', 'route' => 'inventory.adjustments', 'permission' => 'inventory_stock_card.view'],
        OpeningInventoryDocument::class => ['field' => 'document_number', 'label' => 'Opening inventory', 'route' => 'inventory.opening.show', 'permission' => 'inventory_stock_card.view'],
    ];

    /** @var array<string, array<int, string>> */
    private array $numbers = [];

    /** @param Collection<int, StockMovement> $movements */
    public function __construct(Collection $movements)
    {
        foreach (self::SOURCES as $type => $source) {
            $ids = $movements->where('source_type', $type)->pluck('source_id')->filter()->unique()->values();
            if ($ids->isNotEmpty()) {
                $this->numbers[$type] = $type::query()->whereIn('id', $ids)->pluck($source['field'], 'id')->all();
            }
        }
    }

    public function label(StockMovement $movement): string
    {
        if ($movement->source_type === null) {
            return __('Opening balance');
        }

        $source = self::SOURCES[$movement->source_type] ?? null;
        $number = $this->numbers[$movement->source_type][$movement->source_id] ?? null;

        return $source !== null && $number !== null
            ? __($source['label']).' '.$number
            : app(AuditLogPresentation::class)->source($movement->source_type).' #'.$movement->source_id;
    }

    public function url(StockMovement $movement): ?string
    {
        $source = self::SOURCES[$movement->source_type] ?? null;
        if ($source === null || ! isset($this->numbers[$movement->source_type][$movement->source_id]) || ! Gate::allows($source['permission'])) {
            return null;
        }

        return match ($movement->source_type) {
            Sale::class => route($source['route'], $movement->source_id),
            PurchaseReturn::class => route($source['route'], $movement->source_id),
            RetailReturn::class => route($source['route'], $movement->source_id),
            StockTransfer::class => route($source['route'], $movement->source_id),
            StockCount::class => route($source['route'], $movement->source_id),
            OpeningInventoryDocument::class => route($source['route'], $movement->source_id),
            default => route($source['route']),
        };
    }
}
