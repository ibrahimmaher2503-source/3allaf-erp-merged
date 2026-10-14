<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Exceptions\StaleCatalogRecordException;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Catalog\Models\SupplierGroup;
use App\Modules\Catalog\Support\SupplierSettlementMethod;
use App\Modules\Catalog\Support\SupplierTaxIdentity;
use App\Modules\Customer\Support\PhoneNormalizer;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\PaymentMethod;
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

        return DB::transaction(function () use ($data, $id, $expectedVersion, $companyId): Supplier {
            $userId = Auth::id();
            $supplier = $id === null ? null : Supplier::query()->lockForUpdate()->findOrFail($id);

            if ($supplier !== null && $expectedVersion !== null && $supplier->lock_version !== $expectedVersion) {
                throw new StaleCatalogRecordException(__('This supplier master record changed in another session. Reload it before saving.'));
            }

            $status = (string) ($data['status'] ?? 'active');
            if (! in_array($status, ['active', 'inactive'], true)) {
                throw new InvalidArgumentException(__('The selected supplier status is not supported.'));
            }
            $englishName = trim((string) ($data['name_en'] ?? ''));
            if ($englishName === '') {
                throw new InvalidArgumentException(__('Supplier English name is required.'));
            }

            $barcodePrefix = strtoupper(trim((string) ($data['barcode_prefix'] ?? '')));
            if ($barcodePrefix !== '' && ! preg_match('/^[A-Z0-9]{1,12}$/', $barcodePrefix)) {
                throw new InvalidArgumentException(__('The barcode prefix may contain only uppercase English letters and digits, up to 12 characters.'));
            }
            $prefixQuery = Supplier::query()->where('barcode_prefix', $barcodePrefix);
            if ($supplier !== null) {
                $prefixQuery->whereKeyNot($supplier->id);
            }
            if ($barcodePrefix !== '' && $prefixQuery->exists()) {
                throw new InvalidArgumentException(__('The barcode prefix is already assigned to another supplier.'));
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

            $settlementMethod = trim((string) ($data['settlement_method'] ?? ''));
            if (! SupplierSettlementMethod::isValid($settlementMethod)) {
                throw new InvalidArgumentException(__('Select an active supplier financial-settlement method.'));
            }
            $settlementOther = filled($data['settlement_other_description'] ?? null) ? trim((string) $data['settlement_other_description']) : null;
            if ($settlementMethod === 'other' && $settlementOther === null) {
                throw new InvalidArgumentException(__('Describe the supplier settlement method when Other is selected.'));
            }

            $preferredPaymentMethodId = filled($data['preferred_payment_method_id'] ?? null)
                ? (int) $data['preferred_payment_method_id']
                : null;
            if ($preferredPaymentMethodId !== null
                && ! PaymentMethod::query()->whereKey($preferredPaymentMethodId)->where('status', 'active')->exists()) {
                throw new InvalidArgumentException(__('Select an active supplier preferred payment method.'));
            }

            $countryCode = SupplierTaxIdentity::normalizeCountry($data['country_code'] ?? null);
            $taxNumber = SupplierTaxIdentity::normalizeTaxNumber($data['tax_number'] ?? null, $countryCode);
            $taxQuery = Supplier::query()->where('tax_number', $taxNumber);
            if ($supplier !== null) {
                $taxQuery->whereKeyNot($supplier->id);
            }
            if ($taxNumber !== null && $taxQuery->exists()) {
                throw new InvalidArgumentException(__('This supplier Tax ID is already used by another supplier.'));
            }

            $attributes = [
                'code' => strtoupper(trim((string) $data['code'])),
                'barcode_prefix' => $barcodePrefix !== '' ? $barcodePrefix : null,
                'name_ar' => trim((string) $data['name_ar']),
                'name_en' => $englishName,
                'contact_name' => ! empty($data['contact_name']) ? trim((string) $data['contact_name']) : null,
                'email' => ! empty($data['email']) ? trim((string) $data['email']) : null,
                'phone' => filled($data['phone'] ?? null) ? PhoneNormalizer::normalize((string) $data['phone']) : null,
                'tax_number' => $taxNumber,
                'country_code' => $countryCode,
                'payment_terms' => ! empty($data['payment_terms']) ? trim((string) $data['payment_terms']) : null,
                'preferred_payment_method_id' => $preferredPaymentMethodId,
                'settlement_method' => $settlementMethod,
                'settlement_other_description' => $settlementMethod === 'other' ? $settlementOther : null,
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
                $before = $supplier->only(['code', 'barcode_prefix', 'name_ar', 'name_en', 'contact_name', 'email', 'phone', 'tax_number', 'country_code', 'payment_terms', 'preferred_payment_method_id', 'settlement_method', 'settlement_other_description', 'address', 'status', 'supplier_group_id', 'lock_version']);
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
                after: $supplier->fresh()->only(['code', 'barcode_prefix', 'name_ar', 'name_en', 'contact_name', 'email', 'phone', 'tax_number', 'country_code', 'payment_terms', 'preferred_payment_method_id', 'settlement_method', 'settlement_other_description', 'address', 'status', 'supplier_group_id', 'lock_version']),
            );

            return $supplier->fresh();
        });
    }
}
