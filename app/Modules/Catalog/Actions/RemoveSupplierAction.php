<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Models\User;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Platform\Actions\RecordAuditEvent;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class RemoveSupplierAction
{
    /** @return array{outcome:string,dependencies:array<string,int>} */
    public function execute(int $supplierId): array
    {
        Gate::authorize('suppliers.logical_delete');
        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);

        return DB::transaction(function () use ($supplierId, $actor): array {
            $supplier = Supplier::query()->lockForUpdate()->findOrFail($supplierId);
            $dependencies = $this->dependencies($supplierId);

            if ($dependencies !== []) {
                $reason = collect($dependencies)
                    ->map(fn (int $count, string $label): string => $label.' ('.$count.')')
                    ->implode(', ');

                throw ValidationException::withMessages([
                    'supplier' => __('Supplier :code cannot be deleted because it is linked to :reason. Deactivate it instead; products, invoices, balances, and history are preserved.', [
                        'code' => $supplier->code,
                        'reason' => $reason,
                    ]),
                ]);
            }

            $identity = $supplier->only(['id', 'code', 'name_ar', 'name_en', 'status', 'lock_version']);
            try {
                $supplier->delete();
            } catch (QueryException $exception) {
                if ((string) $exception->getCode() !== '23000') {
                    throw $exception;
                }

                throw ValidationException::withMessages([
                    'supplier' => __('Supplier :code cannot be deleted because it is linked to :reason. Deactivate it instead; products, invoices, balances, and history are preserved.', [
                        'code' => $supplier->code,
                        'reason' => __('linked records'),
                    ]),
                ]);
            }

            app(RecordAuditEvent::class)->execute(
                category: 'master_data',
                event: 'delete_unused_supplier',
                source: $supplier,
                before: $identity,
                metadata: ['actor_id' => $actor->id, 'dependencies' => []],
            );

            return ['outcome' => 'deleted', 'dependencies' => []];
        });
    }

    /** @return array<string,int> */
    private function dependencies(int $supplierId): array
    {
        $checks = [
            __('linked product records') => ['product_suppliers', 'supplier_id'],
            __('products with this originating supplier') => ['products', 'originating_supplier_id'],
            __('purchase orders') => ['purchase_orders', 'supplier_id'],
            __('purchase invoices') => ['purchase_invoices', 'supplier_id'],
            __('purchase returns') => ['purchase_returns', 'supplier_id'],
            __('stock-count records') => ['stock_counts', 'supplier_id'],
            __('supplier contacts') => ['supplier_contacts', 'supplier_id'],
            __('supplier communication destinations') => ['supplier_communication_destinations', 'supplier_id'],
            __('supplier import records') => ['supplier_import_rows', 'supplier_id'],
        ];

        $dependencies = [];
        foreach ($checks as $label => [$table, $column]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            $count = (int) DB::table($table)->where($column, $supplierId)->count();
            if ($count > 0) {
                $dependencies[$label] = $count;
            }
        }

        return $dependencies;
    }
}
