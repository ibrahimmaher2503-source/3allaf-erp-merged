<?php

namespace Tests\Support;

use App\Models\User;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\DocumentSequence;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Models\Store;
use Database\Seeders\CanonicalAuthorizationSeeder;
use Illuminate\Support\Facades\Hash;

/**
 * Deterministic Platform fixtures for the TSK-001..TSK-009 regression suite.
 *
 * The local demo seeder is deliberately not used: it only runs in the `local`
 * environment and its single-branch shape cannot express the cross-scope
 * isolation cases these tests must cover.
 */
trait PlatformFixtures
{
    protected function seedCanonicalAuthorization(): void
    {
        $this->seed(CanonicalAuthorizationSeeder::class);
    }

    protected function company(): Company
    {
        return Company::query()->firstOr(fn () => Company::query()->create([
            'code' => 'TEST-CO',
            'name_ar' => 'شركة اختبار',
            'name_en' => 'Test Company',
            // A local test fixture is an approved operational currency
            // context, not a production policy fallback. Shift opening must
            // exercise its documented currency guard rather than fail before
            // the lifecycle under test begins.
            'currency_code' => 'EGP',
            'currency_symbol' => 'EGP',
            'timezone' => 'UTC',
            'locale_default' => 'ar',
            'status' => 'active',
            'policy_notes' => 'Automated test fixture only.',
        ]));
    }

    protected function branch(string $code, string $status = 'active'): Branch
    {
        return Branch::query()->create([
            'company_id' => $this->company()->id,
            'code' => $code,
            'name_ar' => 'فرع '.$code,
            'name_en' => 'Branch '.$code,
            'timezone' => 'UTC',
            'status' => $status,
            'policy_notes' => 'Automated test fixture only.',
        ]);
    }

    protected function store(Branch $branch, string $code, string $type = 'selling', string $status = 'active'): Store
    {
        return Store::query()->create([
            'company_id' => $this->company()->id,
            'branch_id' => $branch->id,
            'code' => $code,
            'type' => $type,
            'name_ar' => 'متجر '.$code,
            'name_en' => 'Store '.$code,
            'status' => $status,
            'policy_notes' => 'Automated test fixture only.',
        ]);
    }

    /**
     * @param  array<int, string>  $roleCodes
     * @param  array<int, int>  $branchIds
     * @param  array<int, int>  $storeIds
     */
    protected function userWith(
        string $username,
        array $roleCodes = [],
        bool $superAdmin = false,
        array $branchIds = [],
        array $storeIds = [],
        string $password = 'TestOnly!2026',
    ): User {
        // `App\Models\User` limits `$fillable` to name/username/email/password,
        // so the privileged columns must be force-filled the same way the
        // seeders do (artisan `db:seed` runs unguarded).
        $user = User::query()->forceCreate([
            'name' => 'Test '.$username,
            'username' => $username,
            'email' => $username.'@toyjoy.test',
            'email_verified_at' => now(),
            'password' => Hash::make($password),
            'is_super_admin' => $superAdmin,
        ]);

        if ($roleCodes !== []) {
            $user->roles()->sync(Role::query()->whereIn('code', $roleCodes)->pluck('id')->all());
        }

        foreach ($branchIds as $branchId) {
            $user->branchScopes()->create(['branch_id' => $branchId, 'status' => 'active']);
        }

        foreach ($storeIds as $storeId) {
            $user->storeScopes()->create(['store_id' => $storeId, 'status' => 'active']);
        }

        return $user->fresh();
    }

    /**
     * Seed a document sequence.
     *
     * `AllocateDocumentNumber` deliberately refuses to invent a sequence, so a
     * document type that a test needs to number must exist up front. Production
     * numbering formats remain BLK-008; these are local test values only.
     */
    protected function documentSequence(string $documentType, ?string $prefix = null): DocumentSequence
    {
        return DocumentSequence::query()->firstOrCreate(
            ['document_type' => $documentType],
            [
                'prefix' => $prefix ?? strtoupper(str_replace('_', '-', $documentType)).'-',
                'padding_length' => 6,
                'next_value' => 1,
                'reset_rule' => 'never',
                'status' => 'active',
                'lock_version' => 1,
                'policy_notes' => 'LOCAL TEST ONLY. Production numbering remains PENDING (BLK-008).',
            ],
        );
    }

    protected function administrator(string $username = 'test-admin'): User
    {
        return $this->userWith($username, ['system-administrator'], true);
    }
}
