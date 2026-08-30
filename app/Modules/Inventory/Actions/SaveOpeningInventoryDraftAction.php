<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\OpeningInventoryDocument;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Store;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class SaveOpeningInventoryDraftAction
{
    /** @param list<array{product_id:mixed,store_id:mixed,quantity:mixed}> $lines */
    public function execute(array $lines, ?int $id = null, ?string $notes = null): OpeningInventoryDocument
    {
        Gate::authorize($id === null ? 'inventory_stock_card.create' : 'inventory_stock_card.edit');
        if ($lines === []) throw new InvalidArgumentException(__('Opening inventory must contain at least one row.'));

        return DB::transaction(function () use ($lines, $id, $notes): OpeningInventoryDocument {
            $document = $id ? OpeningInventoryDocument::query()->with('lines')->lockForUpdate()->findOrFail($id) : null;
            if ($document && $document->status !== 'draft') throw new InvalidArgumentException(__('Only a draft opening inventory document can be changed.'));
            $companyId = (int) Company::query()->where('status', 'active')->value('id');
            if ($companyId < 1) throw new InvalidArgumentException(__('An active company is required.'));
            $seen = [];
            $normalized = [];
            foreach ($lines as $row) {
                $product = Product::query()->whereKey((int) ($row['product_id'] ?? 0))->where('status', 'active')->firstOrFail();
                if ($product->product_type === 'service' || $product->isFamily()) throw new InvalidArgumentException(__('Service products and variation families cannot receive opening inventory.'));
                if ($product->average_cost === null || bccomp((string) $product->average_cost, '0', 4) < 0) throw new InvalidArgumentException(__('The selected product needs a valid Product Card unit cost before opening inventory.'));
                $store = Store::query()->whereKey((int) ($row['store_id'] ?? 0))->where('company_id', $companyId)->where('status', 'active')->whereIn('type', ['warehouse', 'selling'])->firstOrFail();
                app(AssertInventoryStoreScope::class)->execute($store->id);
                $quantity = $this->quantity($row['quantity'] ?? null);
                if (bccomp($quantity, '0', 6) <= 0) throw new InvalidArgumentException(__('Opening quantity must be greater than zero.'));
                if (bccomp(bcmod($quantity, '1', 6), '0', 6) !== 0 && ! $product->fractional_quantity) throw new InvalidArgumentException(__('This product does not allow fractional quantities.'));
                $key = $product->id.':'.$store->id;
                if (isset($seen[$key])) throw new InvalidArgumentException(__('The same product and inventory location may appear only once.'));
                $seen[$key] = true;
                if (StockMovement::query()->where('product_id', $product->id)->where('store_id', $store->id)->exists()) throw new InvalidArgumentException(__('Opening inventory is blocked because this product and location already have inventory movements.'));
                $cost = bcadd((string) $product->average_cost, '0', 4);
                $normalized[] = ['product_id' => $product->id, 'store_id' => $store->id, 'quantity' => $quantity, 'unit_cost' => $cost, 'total_value' => bcmul($quantity, $cost, 4)];
            }
            if (! $document) {
                $document = OpeningInventoryDocument::query()->create(['company_id' => $companyId, 'document_number' => 'OPEN-'.now()->format('YmdHis').'-'.strtoupper(Str::random(4)), 'status' => 'draft', 'notes' => $notes, 'created_by' => Auth::id(), 'idempotency_key' => (string) Str::uuid(), 'lock_version' => 1]);
                $before = null;
            } else {
                $before = $document->only(['status', 'lock_version']);
                $document->update(['notes' => $notes, 'lock_version' => $document->lock_version + 1]);
                $document->lines()->delete();
            }
            $document->lines()->createMany($normalized);
            app(RecordAuditEvent::class)->execute('inventory', $id ? 'update_opening_inventory_draft' : 'create_opening_inventory_draft', $document, $before, $document->fresh()->only(['document_number', 'status', 'lock_version']), metadata: ['line_count' => count($normalized)]);
            return $document->fresh(['lines.product', 'lines.store']);
        });
    }

    private function quantity(mixed $value): string
    {
        $value = trim((string) $value);
        if (! preg_match('/^\d+(?:\.\d{1,6})?$/', $value)) throw new InvalidArgumentException(__('Opening quantity must be a valid decimal with up to six places.'));
        return bcadd($value, '0', 6);
    }
}
