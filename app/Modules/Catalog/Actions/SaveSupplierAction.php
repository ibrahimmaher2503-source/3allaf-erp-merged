<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Exceptions\StaleCatalogRecordException;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Catalog\Models\SupplierGroup;
use App\Modules\Customer\Support\PhoneNormalizer;
use App\Modules\Platform\Actions\RecordAuditEvent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class SaveSupplierAction
{
    /** @param array<string, mixed> $data */
    public function execute(array $data, ?int $id = null, ?int $expectedVersion = null, ?int $companyId = null): Supplier
    {
        Gate::authorize($id ? 'suppliers.edit' : 'suppliers.create');

        return DB::transaction(function () use ($data, $id, $expectedVersion): Supplier {
            $userId = Auth::id();
            $supplier = $id === null ? null : Supplier::query()->lockForUpdate()->findOrFail($id);

            if ($supplier !== null && $expectedVersion !== null && $supplier->lock_version !== $expectedVersion) {
                throw new StaleCatalogRecordException(__('This supplier master record changed in another session. Reload it before saving.'));
            }

            $status = (string) ($data['status'] ?? 'active');
            if (! in_array($status, ['active', 'inactive'], true)) {
                throw new InvalidArgumentException(__('The selected supplier status is not supported.'));
            }

            if ($supplier !== null && $supplier->status === 'active' && $status === 'inactive') {
                if ($supplier->productSuppliers()->where('is_preferred', true)->exists()) {
                    throw new InvalidArgumentException(__('Cannot deactivate a supplier that is set as preferred for active products.'));
                }
            }

            $supplierGroupId = filled($data['supplier_group_id'] ?? null) ? (int) $data['supplier_group_id'] : null;
            $supplierGroup = $supplierGroupId === null
                ? null
                : SupplierGroup::query()->when($companyId !== null, fn ($query) => $query->forCompany($companyId))
                    ->active()->lockForUpdate()->find($supplierGroupId);
            if ($supplierGroupId !== null && $supplierGroup === null) {
                throw new InvalidArgumentException(__('The selected supplier group is not available in the active company.'));
            }

            $attributes = [
                'code' => strtoupper(trim((string) $data['code'])),
                'name_ar' => trim((string) $data['name_ar']),
                'name_en' => trim((string) ($data['name_en'] ?? '')),
                'contact_name' => ! empty($data['contact_name']) ? trim((string) $data['contact_name']) : null,
                'email' => ! empty($data['email']) ? trim((string) $data['email']) : null,
                'phone' => filled($data['phone'] ?? null) ? PhoneNormalizer::normalize((string) $data['phone']) : null,
                'tax_number' => ! empty($data['tax_number']) ? trim((string) $data['tax_number']) : null,
                'payment_terms' => ! empty($data['payment_terms']) ? trim((string) $data['payment_terms']) : null,
                'address' => ! empty($data['address']) ? trim((string) $data['address']) : null,
                'status' => $status,
                'supplier_group_id' => $supplierGroup?->id,
                'updated_by' => $userId,
            ];

            if ($supplier === null) {
                $attributes['created_by'] = $userId;
                $attributes['lock_version'] = 0;
                $supplier = Supplier::query()->create($attributes);
                $event = 'create_supplier';
                $before = null;
            } else {
                $before = $supplier->only(['code', 'name_ar', 'name_en', 'contact_name', 'email', 'phone', 'tax_number', 'payment_terms', 'address', 'status', 'supplier_group_id', 'lock_version']);
                $supplier->update([
                    ...$attributes,
                    'lock_version' => $supplier->lock_version + 1,
                ]);
                $event = 'update_supplier';
            }

            app(RecordAuditEvent::class)->execute(
                category: 'master_data',
                event: $event,
                source: $supplier,
                before: $before,
                after: $supplier->fresh()->only(['code', 'name_ar', 'name_en', 'contact_name', 'email', 'phone', 'tax_number', 'payment_terms', 'address', 'status', 'supplier_group_id', 'lock_version']),
            );

            return $supplier->fresh();
        });
    }
}
