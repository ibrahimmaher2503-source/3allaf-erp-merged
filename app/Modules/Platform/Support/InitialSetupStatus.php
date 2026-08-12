<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support;

use App\Models\User;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductSupplier;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Customer\Models\Customer;
use App\Modules\Inventory\Models\InventoryAdjustment;
use App\Modules\Party\Models\PartyBooking;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\BranchSellingStore;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\DocumentSequence;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Platform\Models\PrinterConfiguration;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Models\TaxSetting;
use App\Modules\Pricing\Enums\PriceVersionState;
use App\Modules\Pricing\Models\PriceLine;
use Illuminate\Database\Eloquent\Builder;

final class InitialSetupStatus
{
    /**
     * @return array{
     *     steps: list<array{key: string, label: string, description: string, route: string, complete: bool, required: bool, status: string}>,
     *     completed_count: int,
     *     required_count: int,
     *     progress_percent: int,
     *     needs_attention: bool,
     *     complete: bool,
     * }
     */
    public function snapshot(): array
    {
        $steps = [
            $this->step('company', __('Company settings'), __('Enter the approved bilingual identity, legal details, currency, timezone, and contact information.'), 'admin.settings', $this->companyReady()),
            $this->step('branches-stores', __('Branches and stores'), __('Create active branches and stores, then assign the active selling store for each retail branch.'), 'admin.branches', $this->branchesAndStoresReady()),
            $this->step('users-scopes', __('Users, roles, and scopes'), __('Create the opening team, assign roles, and scope every non-administrator to approved branches or stores.'), 'admin.authorization-baseline', $this->usersAndScopesReady()),
            $this->step('operational-settings', __('Payments, tax, numbering, and printers'), __('Configure active payment methods, tax treatment, document sequences, and the approved printer profile.'), 'admin.settings', $this->operationalSettingsReady()),
            $this->step('catalog', __('Categories, brands, products, and variations'), __('Build the approved catalog. Variation families remain non-sellable; every retained combination has its own child SKU.'), 'catalog.products', $this->catalogReady()),
            $this->step('suppliers', __('Suppliers and supplier SKUs'), __('Create approved suppliers and connect each supplied sellable SKU to the supplier item code.'), 'suppliers.index', $this->suppliersReady()),
            $this->step('prices', __('Approved selling prices'), __('Create, submit, and approve an effective store price for every sellable SKU before POS use.'), 'pricing.index', $this->pricesReady()),
            $this->step('opening-inventory', __('Opening inventory'), __('Post owner-approved opening quantities and unit costs through controlled inventory adjustments. Never edit balances directly.'), 'inventory.adjustments.create', $this->openingInventoryReady()),
            $this->step('customers-party', __('Customers and Party data'), __('Create customers and Party bookings only when genuine business activity requires them; never preload fabricated personal data.'), 'customers.index', $this->customerOrPartyDataExists(), false),
        ];

        $requiredSteps = array_filter($steps, static fn (array $step): bool => $step['required']);
        $completedCount = count(array_filter($requiredSteps, static fn (array $step): bool => $step['complete']));
        $requiredCount = count($requiredSteps);

        return [
            'steps' => $steps,
            'completed_count' => $completedCount,
            'required_count' => $requiredCount,
            'progress_percent' => $requiredCount === 0 ? 100 : (int) round(($completedCount / $requiredCount) * 100),
            'needs_attention' => $completedCount < $requiredCount,
            'complete' => $completedCount === $requiredCount,
        ];
    }

    /** @return array{key: string, label: string, description: string, route: string, complete: bool, required: bool, status: string} */
    private function step(string $key, string $label, string $description, string $routeName, bool $complete, bool $required = true): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'description' => $description,
            'route' => route($routeName),
            'complete' => $complete,
            'required' => $required,
            'status' => $complete ? 'complete' : ($required ? 'required' : 'optional'),
        ];
    }

    private function companyReady(): bool
    {
        return Company::query()
            ->where('status', 'active')
            ->whereNotIn('code', ['', 'TBD'])
            ->whereNotNull('name_ar')->where('name_ar', '!=', '')
            ->whereNotNull('name_en')->where('name_en', '!=', '')
            ->whereNotIn('currency_code', ['', 'TBD'])
            ->whereNotIn('currency_symbol', ['', 'TBD'])
            ->whereNotNull('timezone')->where('timezone', '!=', '')
            ->exists();
    }

    private function branchesAndStoresReady(): bool
    {
        return Branch::query()->where('status', 'active')->exists()
            && Store::query()->where('status', 'active')->whereHas('branch', fn (Builder $query): Builder => $query->where('status', 'active'))->exists()
            && BranchSellingStore::query()->where('status', 'active')->where(function (Builder $query): void {
                $query->whereNull('effective_from')->orWhere('effective_from', '<=', now());
            })->where(function (Builder $query): void {
                $query->whereNull('effective_to')->orWhere('effective_to', '>', now());
            })->exists();
    }

    private function usersAndScopesReady(): bool
    {
        $administratorExists = User::query()
            ->where('status', 'active')
            ->where('is_super_admin', true)
            ->whereHas('roles', fn (Builder $query): Builder => $query->where('roles.code', 'system-administrator')->where('roles.status', 'active'))
            ->exists();

        $unscopedOperatorExists = User::query()
            ->where('status', 'active')
            ->where('is_super_admin', false)
            ->where(function (Builder $query): void {
                $query->whereDoesntHave('roles', fn (Builder $role): Builder => $role->where('roles.status', 'active'))
                    ->orWhere(function (Builder $scope): void {
                        $scope->whereDoesntHave('branchScopes', fn (Builder $branch): Builder => $branch->where('status', 'active'))
                            ->whereDoesntHave('storeScopes', fn (Builder $store): Builder => $store->where('status', 'active'));
                    });
            })->exists();

        return $administratorExists && ! $unscopedOperatorExists;
    }

    private function operationalSettingsReady(): bool
    {
        return PaymentMethod::query()->where('status', 'active')->exists()
            && TaxSetting::query()->where('status', 'active')->exists()
            && DocumentSequence::query()->where('status', 'active')->exists()
            && PrinterConfiguration::query()->where('status', 'active')->exists();
    }

    private function catalogReady(): bool
    {
        $brokenFamilyExists = Product::query()
            ->whereNull('parent_product_id')
            ->where('has_variations', true)
            ->where('status', 'active')
            ->whereDoesntHave('variants', fn (Builder $query): Builder => $query->where('status', 'active'))
            ->exists();

        return Category::query()->where('status', 'active')->exists()
            && Brand::query()->where('status', 'active')->exists()
            && Product::query()->sellable()->exists()
            && ! $brokenFamilyExists;
    }

    private function suppliersReady(): bool
    {
        return Supplier::query()->where('status', 'active')->exists()
            && ProductSupplier::query()
                ->whereHas('supplier', fn (Builder $query): Builder => $query->where('status', 'active'))
                ->whereHas('product', fn (Builder $query): Builder => $query->sellable())
                ->whereNotNull('supplier_item_code')->where('supplier_item_code', '!=', '')
                ->exists();
    }

    private function pricesReady(): bool
    {
        return PriceLine::query()
            ->whereHas('version', function (Builder $query): void {
                $query->where('state', PriceVersionState::Approved->value)
                    ->where(fn (Builder $from): Builder => $from->whereNull('effective_from')->orWhere('effective_from', '<=', now()))
                    ->where(fn (Builder $to): Builder => $to->whereNull('effective_to')->orWhere('effective_to', '>', now()));
            })
            ->whereHas('product', fn (Builder $query): Builder => $query->sellable())
            ->whereHas('store', fn (Builder $query): Builder => $query->where('status', 'active'))
            ->exists();
    }

    private function openingInventoryReady(): bool
    {
        return InventoryAdjustment::query()
            ->where('status', 'approved')
            ->where('adjustment_type', 'entry')
            ->whereIn('reason_code', ['opening_stock', 'opening_inventory'])
            ->whereNull('reversal_of_id')
            ->exists();
    }

    private function customerOrPartyDataExists(): bool
    {
        return Customer::query()->exists() || PartyBooking::query()->exists();
    }
}
