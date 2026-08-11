<?php

declare(strict_types=1);

namespace App\Modules\Customer\Actions;

use App\Models\User;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Support\CustomerPolicy;
use App\Modules\Customer\Support\PhoneNormalizer;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\Store;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class UpdateCustomerAction
{
    /** @param array<string, mixed> $data */
    public function execute(User $actor, Customer $customer, Store $store, array $data): Customer
    {
        Gate::forUser($actor)->authorize('customers.edit');
        abort_unless($store->status === 'active' && $actor->canAccessStore((int) $store->id), 403);
        abort_unless(Customer::query()->visibleFrom($actor, (int) $store->branch_id, (int) $store->id)->whereKey($customer->id)->exists(), 404);
        abort_unless($customer->status === 'active', 404);

        CustomerPolicy::phoneNormalization();
        $phoneDisplay = trim((string) ($data['phone'] ?? $customer->phone_display));
        $phone = PhoneNormalizer::normalize($phoneDisplay);
        $nameAr = trim((string) ($data['name_ar'] ?? $customer->name_ar));
        $nameEn = trim((string) ($data['name_en'] ?? $customer->name_en));
        if ($nameAr === '' || $nameEn === '') {
            throw new InvalidArgumentException(__('Customer Arabic and English names are required.'));
        }
        $sensitiveFields = ['email', 'address_ar', 'address_en'];
        $sensitiveChangeRequested = collect($sensitiveFields)->contains(fn (string $field): bool => array_key_exists($field, $data));
        abort_unless(! $sensitiveChangeRequested || $actor->can('customers.sensitive'), 403);

        return DB::transaction(function () use ($actor, $customer, $store, $data, $phone, $phoneDisplay, $nameAr, $nameEn): Customer {
            $locked = Customer::query()->lockForUpdate()->findOrFail($customer->id);
            if ($locked->status !== 'active') {
                throw new InvalidArgumentException(__('This customer profile is no longer editable.'));
            }
            $duplicate = Customer::query()
                ->where('phone_normalized', $phone)
                ->where('id', '<>', $locked->id)
                ->first();
            if ($duplicate !== null) {
                throw new InvalidArgumentException(__('A customer already exists for this phone number. Review the existing profile instead of creating a duplicate.'));
            }

            $before = $locked->only(['phone_display', 'phone_normalized', 'name_ar', 'name_en', 'email', 'secondary_phone', 'address_ar', 'address_en', 'lock_version']);
            $locked->mutateMaster([
                'phone_normalized' => $phone,
                'phone_display' => $phoneDisplay,
                'name_ar' => $nameAr,
                'name_en' => $nameEn,
                'email' => array_key_exists('email', $data) ? (filled($data['email']) ? trim((string) $data['email']) : null) : $locked->email,
                'secondary_phone' => filled($data['secondary_phone'] ?? null) ? trim((string) $data['secondary_phone']) : null,
                'address_ar' => array_key_exists('address_ar', $data) ? (filled($data['address_ar']) ? trim((string) $data['address_ar']) : null) : $locked->address_ar,
                'address_en' => array_key_exists('address_en', $data) ? (filled($data['address_en']) ? trim((string) $data['address_en']) : null) : $locked->address_en,
                'updated_by' => $actor->id,
                'lock_version' => ((int) $locked->lock_version) + 1,
            ]);

            $saved = $locked->fresh();
            app(RecordAuditEvent::class)->execute(
                category: 'master_data',
                event: 'customer_updated',
                source: $saved,
                before: $before,
                after: $saved->only(['phone_display', 'phone_normalized', 'name_ar', 'name_en', 'email', 'secondary_phone', 'address_ar', 'address_en', 'lock_version']),
                branchId: (int) $store->branch_id,
                storeId: (int) $store->id,
                metadata: ['actor_id' => $actor->id, 'phone_duplicate_checked' => true],
            );

            return $saved;
        });
    }
}
