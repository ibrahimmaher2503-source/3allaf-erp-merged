<?php

declare(strict_types=1);

namespace App\Modules\Customer\Actions;

use App\Models\User;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerScope;
use App\Modules\Customer\Support\CustomerPolicy;
use App\Modules\Customer\Support\PhoneNormalizer;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\Store;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class CreateCustomerAction
{
    /** @param array<string, mixed> $data */
    public function execute(User $actor, Store $store, array $data): Customer
    {
        Gate::forUser($actor)->authorize('customers.create');
        abort_unless($store->status === 'active' && $actor->canAccessStore((int) $store->id), 403);

        CustomerPolicy::phoneNormalization();
        $phone = PhoneNormalizer::normalize((string) ($data['phone'] ?? ''));
        $nameAr = trim((string) ($data['name_ar'] ?? ''));
        $nameEn = trim((string) ($data['name_en'] ?? ''));
        if ($nameAr === '' || $nameEn === '') {
            throw new InvalidArgumentException(__('Customer Arabic and English names are required.'));
        }
        $idempotencyKey = trim((string) ($data['idempotency_key'] ?? ''));
        if ($idempotencyKey === '') {
            throw new InvalidArgumentException(__('A customer idempotency key is required.'));
        }
        $consents = $data['consents'] ?? [];
        if (! is_array($consents) || $consents === []) {
            throw new InvalidArgumentException(__('At least one customer consent record is required.'));
        }

        try {
            return DB::transaction(function () use ($actor, $store, $data, $phone, $nameAr, $nameEn, $idempotencyKey, $consents): Customer {
                $existingByKey = Customer::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
                if ($existingByKey !== null) {
                    if ($existingByKey->phone_normalized !== $phone || $existingByKey->name_ar !== $nameAr || $existingByKey->name_en !== $nameEn) {
                        throw new InvalidArgumentException(__('This customer idempotency key was already used with a different payload.'));
                    }

                    return $existingByKey;
                }

                $duplicate = Customer::query()->where('phone_normalized', $phone)->first();
                if ($duplicate !== null) {
                    throw new InvalidArgumentException(__('A customer already exists for this phone number. Review the existing profile instead of creating a duplicate.'));
                }

                $customer = Customer::query()->create([
                    'phone_normalized' => $phone,
                    'phone_display' => trim((string) $data['phone']),
                    'name_ar' => $nameAr,
                    'name_en' => $nameEn,
                    'email' => filled($data['email'] ?? null) ? trim((string) $data['email']) : null,
                    'secondary_phone' => filled($data['secondary_phone'] ?? null) ? trim((string) $data['secondary_phone']) : null,
                    'address_ar' => filled($data['address_ar'] ?? null) ? trim((string) $data['address_ar']) : null,
                    'address_en' => filled($data['address_en'] ?? null) ? trim((string) $data['address_en']) : null,
                    'status' => 'active',
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                    'created_branch_id' => $store->branch_id,
                    'created_store_id' => $store->id,
                    'idempotency_key' => $idempotencyKey,
                    'lock_version' => 1,
                ]);

                CustomerScope::query()->create([
                    'customer_id' => $customer->id,
                    'branch_id' => $store->branch_id,
                    'store_id' => $store->id,
                    'created_by' => $actor->id,
                ]);

                app(RecordAuditEvent::class)->execute(
                    category: 'customer_value',
                    event: 'customer_created',
                    source: $customer,
                    after: $customer->only(['public_id', 'phone_normalized', 'name_ar', 'name_en', 'email', 'status', 'lock_version']),
                    branchId: (int) $store->branch_id,
                    storeId: (int) $store->id,
                    metadata: ['idempotency_key' => $idempotencyKey, 'purpose_scoped' => true, 'actor_id' => $actor->id],
                );

                $consentAction = app(RecordCustomerConsentAction::class);
                foreach (array_values($consents) as $index => $consent) {
                    if (! is_array($consent)) {
                        throw new InvalidArgumentException(__('Each customer consent value must be an object.'));
                    }
                    $consentAction->execute(
                        $actor,
                        $customer,
                        $store,
                        (string) ($consent['purpose'] ?? ''),
                        (string) ($consent['status'] ?? 'granted'),
                        (string) ($consent['source'] ?? 'profile_create'),
                        $idempotencyKey.':CONSENT:'.$index,
                    );
                }

                $children = $data['children'] ?? [];
                if ($children !== []) {
                    if (! is_array($children)) {
                        throw new InvalidArgumentException(__('Customer children must be an array.'));
                    }
                    $childAction = app(SaveCustomerChildAction::class);
                    foreach (array_values($children) as $child) {
                        if (! is_array($child)) {
                            throw new InvalidArgumentException(__('Each child profile must be an object.'));
                        }
                        $childAction->execute($actor, $customer, $store, $child, null, 'profile_create');
                    }
                }

                return $customer->fresh(['scopes', 'consents', 'children']);
            }, 5);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = Customer::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null && $existing->phone_normalized === $phone && $existing->name_ar === $nameAr && $existing->name_en === $nameEn) {
                return $existing;
            }

            $duplicate = Customer::query()->where('phone_normalized', $phone)->first();
            if ($duplicate !== null) {
                throw new InvalidArgumentException(__('A customer already exists for this phone number. Review the existing profile instead of creating a duplicate.'));
            }

            throw $exception;
        }
    }
}
