<?php

declare(strict_types=1);

namespace App\Modules\Customer\Actions;

use App\Models\User;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerGroup;
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
        $secondaryPhone = filled($data['secondary_phone'] ?? null)
            ? PhoneNormalizer::normalize((string) $data['secondary_phone'])
            : null;
        [$firstNameAr, $lastNameAr, $firstNameEn, $lastNameEn, $nameAr, $nameEn] = self::normaliseNames($data);
        $email = filled($data['email'] ?? null) ? strtolower(trim((string) $data['email'])) : null;
        if ($firstNameAr === '' || $lastNameAr === '') {
            throw new InvalidArgumentException(__('Customer Arabic first and last names are required.'));
        }
        $idempotencyKey = trim((string) ($data['idempotency_key'] ?? ''));
        if ($idempotencyKey === '') {
            throw new InvalidArgumentException(__('A customer idempotency key is required.'));
        }
        $consents = $data['consents'] ?? [];
        if (! is_array($consents) || $consents === []) {
            throw new InvalidArgumentException(__('At least one customer consent record is required.'));
        }

        $customerGroupId = filled($data['customer_group_id'] ?? null) ? (int) $data['customer_group_id'] : null;

        try {
            return DB::transaction(function () use ($actor, $store, $data, $phone, $secondaryPhone, $firstNameAr, $lastNameAr, $firstNameEn, $lastNameEn, $nameAr, $nameEn, $email, $idempotencyKey, $consents, $customerGroupId): Customer {
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

                if ($email !== null && Customer::query()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
                    throw new InvalidArgumentException(__('A customer already exists for this email address. Review the existing profile instead of creating a duplicate.'));
                }

                $customerGroup = $customerGroupId === null
                    ? null
                    : CustomerGroup::query()->forCompany((int) $store->company_id)->active()->lockForUpdate()->find($customerGroupId);
                if ($customerGroupId !== null && $customerGroup === null) {
                    throw new InvalidArgumentException(__('The selected customer group is not available in this company.'));
                }

                $customer = Customer::query()->create([
                    'phone_normalized' => $phone,
                    'phone_display' => trim((string) $data['phone']),
                    'first_name_ar' => $firstNameAr,
                    'last_name_ar' => $lastNameAr,
                    'first_name_en' => $firstNameEn !== '' ? $firstNameEn : null,
                    'last_name_en' => $lastNameEn !== '' ? $lastNameEn : null,
                    'name_ar' => $nameAr,
                    'name_en' => $nameEn,
                    'email' => $email,
                    'secondary_phone' => $secondaryPhone,
                    'address_ar' => filled($data['address_ar'] ?? null) ? trim((string) $data['address_ar']) : null,
                    'address_en' => filled($data['address_en'] ?? null) ? trim((string) $data['address_en']) : null,
                    'status' => 'active',
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                    'created_branch_id' => $store->branch_id,
                    'created_store_id' => $store->id,
                    'customer_group_id' => $customerGroup?->id,
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
                    after: $customer->only(['public_id', 'phone_normalized', 'name_ar', 'name_en', 'email', 'customer_group_id', 'status', 'lock_version']),
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

    /** @param array<string, mixed> $data @return array{0:string,1:string,2:string,3:string,4:string,5:string} */
    private static function normaliseNames(array $data): array
    {
        $legacyAr = trim((string) ($data['name_ar'] ?? ''));
        $legacyEn = trim((string) ($data['name_en'] ?? ''));
        $firstAr = trim((string) ($data['first_name_ar'] ?? ''));
        $lastAr = trim((string) ($data['last_name_ar'] ?? ''));
        if ($firstAr === '' && $lastAr === '' && $legacyAr !== '') {
            [$firstAr, $lastAr] = array_pad(preg_split('/\s+/u', $legacyAr, 2) ?: [$legacyAr], 2, '');
        }
        $firstEn = trim((string) ($data['first_name_en'] ?? ''));
        $lastEn = trim((string) ($data['last_name_en'] ?? ''));
        if ($firstEn === '' && $lastEn === '' && $legacyEn !== '') {
            [$firstEn, $lastEn] = array_pad(preg_split('/\s+/u', $legacyEn, 2) ?: [$legacyEn], 2, '');
        }
        $nameAr = trim($firstAr.' '.$lastAr);
        $englishFull = trim($firstEn.' '.$lastEn);
        return [$firstAr, $lastAr, $firstEn, $lastEn, $nameAr, $englishFull !== '' ? $englishFull : $nameAr];
    }
}
