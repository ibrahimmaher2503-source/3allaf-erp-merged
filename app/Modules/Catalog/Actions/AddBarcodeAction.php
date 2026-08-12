<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\Barcode;
use App\Modules\Catalog\Models\BarcodeSequence;
use App\Modules\Catalog\Models\Product;
use App\Modules\Platform\Actions\RecordAuditEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class AddBarcodeAction
{
    public function addSupplierBarcode(int $productId, string $barcode): Barcode
    {
        Gate::authorize('products_categories_brands.edit');
        $value = trim($barcode);

        if ($value === '') {
            throw new InvalidArgumentException(__('A supplier barcode is required.'));
        }

        return DB::transaction(function () use ($productId, $value): Barcode {
            $product = Product::query()->lockForUpdate()->findOrFail($productId);

            if ($product->isFamily()) {
                throw new InvalidArgumentException(__('A variation family is not sellable and cannot own a barcode. Add the barcode to a child SKU.'));
            }

            if (Barcode::query()->where('barcode', $value)->exists()) {
                throw new InvalidArgumentException(__('This barcode is already assigned and cannot be silently reassigned.'));
            }

            $barcode = Barcode::query()->create([
                'product_id' => $product->id,
                'barcode' => $value,
                'source' => 'supplier',
                'status' => 'active',
                'is_primary' => ! $product->barcodes()->where('status', 'active')->exists(),
            ]);

            $this->syncProductMode($product);
            app(RecordAuditEvent::class)->execute(
                category: 'master_data',
                event: 'create_supplier_barcode',
                source: $barcode,
                after: $barcode->only(['product_id', 'barcode', 'source', 'status', 'is_primary']),
            );

            return $barcode;
        });
    }

    public function allocateLocalBarcode(int $productId, string $supplierCode, string $allocationKey): Barcode
    {
        Gate::authorize('products_categories_brands.edit');
        $supplierCode = trim($supplierCode);
        $allocationKey = trim($allocationKey);

        if (! preg_match('/^\d{4}$/', $supplierCode)) {
            throw new InvalidArgumentException(__('The supplier code must contain exactly four digits.'));
        }

        if ($allocationKey === '') {
            throw new InvalidArgumentException(__('A barcode allocation request key is required.'));
        }

        return DB::transaction(function () use ($productId, $supplierCode, $allocationKey): Barcode {
            $existing = Barcode::query()->where('allocation_key', $allocationKey)->lockForUpdate()->first();

            if ($existing !== null) {
                if ((int) $existing->product_id !== $productId) {
                    throw new InvalidArgumentException(__('This barcode allocation request key belongs to another product.'));
                }

                return $existing;
            }

            $product = Product::query()->lockForUpdate()->findOrFail($productId);

            if ($product->isFamily()) {
                throw new InvalidArgumentException(__('A variation family is not sellable and cannot own a barcode. Add the barcode to a child SKU.'));
            }
            $sequence = BarcodeSequence::query()->where('supplier_code', $supplierCode)->lockForUpdate()->first();
            $serial = $sequence?->next_serial ?? 1;

            while ($serial <= 999999) {
                $value = $supplierCode.str_pad((string) $serial, 6, '0', STR_PAD_LEFT);

                if (! Barcode::query()->where('barcode', $value)->exists()) {
                    break;
                }

                $serial++;
            }

            if ($serial > 999999) {
                throw new InvalidArgumentException(__('The local barcode serial range is exhausted for this supplier code.'));
            }

            if ($sequence === null) {
                BarcodeSequence::query()->create([
                    'supplier_code' => $supplierCode,
                    'next_serial' => $serial + 1,
                ]);
            } else {
                $sequence->update(['next_serial' => $serial + 1]);
            }

            $barcode = Barcode::query()->create([
                'product_id' => $product->id,
                'barcode' => $value,
                'source' => 'local',
                'supplier_code' => $supplierCode,
                'serial_value' => $serial,
                'status' => 'active',
                'is_primary' => ! $product->barcodes()->where('status', 'active')->exists(),
                'allocation_key' => $allocationKey,
            ]);

            $this->syncProductMode($product);
            app(RecordAuditEvent::class)->execute(
                category: 'master_data',
                event: 'allocate_local_barcode',
                source: $barcode,
                after: $barcode->only(['product_id', 'barcode', 'source', 'supplier_code', 'serial_value', 'status', 'is_primary']),
                metadata: ['allocation_key_hash' => hash('sha256', $allocationKey)],
            );

            return $barcode;
        });
    }

    public function deactivate(int $barcodeId): Barcode
    {
        Gate::authorize('products_categories_brands.edit');

        return DB::transaction(function () use ($barcodeId): Barcode {
            $barcode = Barcode::query()->lockForUpdate()->findOrFail($barcodeId);
            $before = $barcode->only(['status', 'is_primary']);
            $barcode->update(['status' => 'inactive', 'is_primary' => false]);
            $product = $barcode->product()->lockForUpdate()->firstOrFail();
            $this->syncProductMode($product);

            app(RecordAuditEvent::class)->execute(
                category: 'master_data',
                event: 'deactivate_barcode',
                source: $barcode,
                before: $before,
                after: $barcode->fresh()->only(['status', 'is_primary']),
            );

            return $barcode->fresh();
        });
    }

    private function syncProductMode(Product $product): void
    {
        $sources = $product->barcodes()->where('status', 'active')->pluck('source')->unique()->values()->all();
        $mode = match (true) {
            count($sources) === 0 => 'none',
            count($sources) === 1 && $sources[0] === 'supplier' => 'supplier',
            count($sources) === 1 && $sources[0] === 'local' => 'local',
            default => 'mixed',
        };

        $product->update(['barcode_mode' => $mode]);
    }
}
