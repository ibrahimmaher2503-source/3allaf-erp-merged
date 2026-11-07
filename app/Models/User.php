<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Models\UserBranchScope;
use App\Modules\Platform\Models\UserStoreScope;
use App\Modules\Platform\Models\UserUiPreference;
use App\Modules\Platform\Support\CompanyOwnerPermissionProfile;
use App\Modules\Platform\Support\RequestPermissionLookup;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string|null $username
 * @property string $email
 * @property string $status
 * @property bool $is_super_admin
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'username', 'email', 'password', 'status'])]
#[Hidden(['password', 'is_super_admin', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    private ?bool $systemAdministratorRole = null;

    private ?bool $companyOwnerRole = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_super_admin' => 'boolean',
            'status' => 'string',
            'password' => 'hashed',
        ];
    }

    /**
     * A disabled account is never privileged, even when the immutable
     * database flag records its former Super Admin designation.
     */
    protected function isSuperAdmin(): Attribute
    {
        return Attribute::get(fn (mixed $value): bool => (bool) $value && $this->status === 'active');
    }

    public function canBypassApproval(): bool
    {
        return $this->is_super_admin || $this->isSystemAdministrator() || $this->isCompanyOwner();
    }

    public function isCompanyOwner(): bool
    {
        return $this->companyOwnerRole ??= $this->status === 'active'
            && $this->roles()
                ->where('roles.code', CompanyOwnerPermissionProfile::ROLE_CODE)
                ->where('roles.status', 'active')
                ->exists();
    }

    /** @return array<int, int> */
    public function operationalCompanyIds(): array
    {
        if (! $this->canBypassApproval()) {
            return [];
        }

        $branchCompanyIds = Branch::query()
            ->whereIn('id', $this->branchScopes()->where('status', 'active')->select('branch_id'))
            ->where('status', 'active')
            ->whereNotNull('company_id')
            ->pluck('company_id');
        $storeCompanyIds = Store::query()
            ->whereIn('id', $this->storeScopes()->where('status', 'active')->select('store_id'))
            ->where('status', 'active')
            ->pluck('company_id');
        $companyIds = Company::query()
            ->where('status', 'active')
            ->whereIn('id', $branchCompanyIds->merge($storeCompanyIds)->unique())
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        if ($companyIds === [] && ($this->is_super_admin || $this->isSystemAdministrator())) {
            $companyIds = Company::query()->where('status', 'active')->limit(2)->pluck('id')->all();

            return count($companyIds) === 1 ? array_map('intval', $companyIds) : [];
        }

        return count($companyIds) === 1 ? array_values($companyIds) : [];
    }

    public function canOperateInCompany(int $companyId): bool
    {
        return $companyId > 0 && in_array($companyId, $this->operationalCompanyIds(), true);
    }

    public function canBypassPurchaseInvoiceApproval(): bool
    {
        return $this->canBypassApproval() || $this->isSystemAdministrator();
    }

    public function canBypassShiftCloseApproval(): bool
    {
        return $this->canBypassApproval() || $this->isSystemAdministrator();
    }

    public function isSystemAdministrator(): bool
    {
        return $this->systemAdministratorRole ??= $this->status === 'active'
            && $this->roles()
                ->where('roles.code', 'system-administrator')
                ->where('roles.status', 'active')
                ->exists();
    }

    /**
     * System Administrator keeps its existing application-wide permissions;
     * operational self-approval and company scope use canBypassApproval().
     */
    public function hasGlobalApplicationAccess(): bool
    {
        return $this->status === 'active'
            && ($this->is_super_admin || $this->isSystemAdministrator());
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }

    public function assignedCashDrawers(): HasMany
    {
        return $this->hasMany(CashDrawer::class, 'assigned_user_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeAssignableToCashDrawer(Builder $query, int $branchId, ?int $storeId): Builder
    {
        if ($branchId < 1) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->where('users.status', 'active')
            ->where(function (Builder $eligible): void {
                $eligible->where('users.is_super_admin', true)
                    ->orWhereHas('roles', fn (Builder $roles): Builder => $roles
                        ->where('roles.status', 'active')
                        ->where(function (Builder $role): void {
                            $role->whereIn('roles.code', ['cashier', 'system-administrator'])
                                ->orWhereHas('permissions', fn (Builder $permissions): Builder => $permissions
                                    ->where('permissions.code', 'pos_sales.create')
                                    ->where('permissions.status', 'active'));
                        }));
            })
            ->where(function (Builder $scope) use ($branchId): void {
                $scope->where('users.is_super_admin', true)
                    ->orWhereHas('roles', fn (Builder $roles): Builder => $roles
                        ->where('roles.code', 'system-administrator')
                        ->where('roles.status', 'active'))
                    ->orWhereHas('branchScopes', fn (Builder $branchScopes): Builder => $branchScopes
                        ->where('branch_id', $branchId)
                        ->where('status', 'active'))
                    ->orWhereHas('storeScopes', fn (Builder $storeScopes): Builder => $storeScopes
                        ->whereHas('store', fn (Builder $store): Builder => $store->where('branch_id', $branchId))
                        ->where('status', 'active'));
            })
            ->when($storeId !== null && $storeId > 0, function (Builder $query) use ($branchId, $storeId): void {
                $query->where(function (Builder $scope) use ($branchId, $storeId): void {
                    $scope->where('users.is_super_admin', true)
                        ->orWhereHas('roles', fn (Builder $roles): Builder => $roles
                            ->where('roles.code', 'system-administrator')
                            ->where('roles.status', 'active'))
                        ->orWhereHas('storeScopes', fn (Builder $storeScopes): Builder => $storeScopes
                            ->where('store_id', $storeId)
                            ->where('status', 'active'))
                        ->orWhereHas('branchScopes', fn (Builder $branchScopes): Builder => $branchScopes
                            ->where('branch_id', $branchId)
                            ->where('status', 'active'));
                });
            });
    }

    public function hasPermission(string $code): bool
    {
        if ($this->status === 'inactive') {
            return false;
        }

        if ($code === 'products_categories_brands.cost_view' && $this->isSystemAdministrator()) {
            return true;
        }

        return isset($this->activePermissionLookup()[$code]);
    }

    /** @return array<string, true> */
    private function activePermissionLookup(): array
    {
        return app(RequestPermissionLookup::class)->for($this);
    }

    public function canAccessBranch(int $branchId): bool
    {
        if ($this->status === 'inactive') {
            return false;
        }

        if ($this->canBypassApproval()) {
            $companyId = Branch::query()->whereKey($branchId)->value('company_id');

            return $companyId !== null && $this->canOperateInCompany((int) $companyId);
        }

        if ($this->hasGlobalApplicationAccess()) {
            return true;
        }

        return $this->branchScopes()
            ->where('branch_id', $branchId)
            ->where('status', 'active')
            ->exists();
    }

    public function canAccessStore(int $storeId): bool
    {
        if ($this->status === 'inactive') {
            return false;
        }

        if ($this->canBypassApproval()) {
            $companyId = Store::query()->whereKey($storeId)->value('company_id');

            return $companyId !== null && $this->canOperateInCompany((int) $companyId);
        }

        if ($this->hasGlobalApplicationAccess()) {
            return true;
        }

        if ($this->storeScopes()
            ->where('store_id', $storeId)
            ->where('status', 'active')
            ->exists()) {
            return true;
        }

        $branchId = Store::query()->whereKey($storeId)->value('branch_id');

        return $branchId !== null && $this->canAccessBranch((int) $branchId);
    }

    public function branchScopes(): HasMany
    {
        return $this->hasMany(UserBranchScope::class);
    }

    public function storeScopes(): HasMany
    {
        return $this->hasMany(UserStoreScope::class);
    }

    public function uiPreference(): HasOne
    {
        return $this->hasOne(UserUiPreference::class);
    }
}
