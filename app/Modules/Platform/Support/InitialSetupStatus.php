<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support;

use App\Models\User;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductSupplier;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Catalog\Models\SupplierGroup;
use App\Modules\Customer\Models\CustomerGroup;
use App\Modules\Customer\Models\CustomerPolicySettingVersion;
use App\Modules\Inventory\Models\InventoryAdjustment;
use App\Modules\Party\Models\PartyBooking;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\BranchSellingStore;
use App\Modules\Platform\Models\CashDrawer;
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
    /** @return array{steps: list<array<string, mixed>>, owner_decisions: list<array<string, mixed>>, completed_count: int, required_count: int, progress_percent: int, needs_attention: bool, complete: bool} */
    public function snapshot(): array
    {
        $companyReady = $this->companyReady();
        $branchesReady = $this->branchesAndStoresReady();
        $catalogReady = $this->catalogReady();
        $activeCompanyId = (int) Company::query()->where('status', 'active')->value('id');
        $activeCustomerGroups = CustomerGroup::query()->forCompany($activeCompanyId)->active();
        $activeSupplierGroups = SupplierGroup::query()->forCompany($activeCompanyId)->active();
        $customerConsentPolicyRecords = $this->customerConsentPolicyRecords();
        $partyPolicyRecords = $this->partyPolicyRecords();

        $steps = [
            $this->step('company', __('Company identity'), __('Use the approved bilingual identity, legal details, currency, timezone, and contact information.'), 'admin.settings', 'company_settings.view', $companyReady, Company::query()->count(), routeParameters: ['tab' => 'company'], destinationKey: 'company-identity'),
            $this->step('branches-stores', __('Branches and stores'), __('Create active branches and stores, then assign an active selling store for each retail branch.'), 'admin.branches', 'branches_stores.view', $branchesReady, Branch::query()->count() + Store::query()->count()),
            $this->step('warehouses', __('Warehouses'), __('Maintain at least one active warehouse store linked to an active branch.'), 'admin.stores', 'branches_stores.view', $this->warehousesReady(), Store::query()->where('type', 'warehouse')->count()),
            $this->step('pos-selling-location', __('POS selling-location linkage'), __('Map each retail branch to its active selling store before opening a cashier shift. Opening a shift remains an operational action, not a setup requirement.'), 'admin.branches', 'branches_stores.view', $this->posSellingLocationReady(), BranchSellingStore::query()->where('status', 'active')->count(), routeParameters: ['section' => 'selling-store-mapping'], destinationKey: 'selling-store-mapping'),
            $this->step('cash-drawers', __('Cash drawers'), __('Assign active cash drawers to the stores that will receive controlled payments.'), 'admin.cash-drawers', 'drawers_payments_tax_numbering_printers.view', $this->cashDrawersReady(), CashDrawer::query()->count()),
            $this->step('users-scopes', __('Users, roles, and scopes'), __('Create the opening team, assign roles, and scope every non-administrator to approved branches or stores.'), 'admin.authorization-baseline', 'users_roles_permissions.view', $this->usersAndScopesReady(), User::query()->where('status', 'active')->count()),
            $this->step('payment-methods', __('Payment methods'), __('Define the persisted payment methods staff may recognize and reconcile.'), 'admin.settings', 'company_settings.view', $this->paymentMethodsReady(), PaymentMethod::query()->count(), routeParameters: ['tab' => 'payments'], destinationKey: 'payment-methods'),
            $this->step('taxes', __('Taxes'), __('Review the saved tax treatment before any invoice or POS tax choice is enabled.'), 'admin.settings', 'company_settings.view', $this->taxesReady(), TaxSetting::query()->count(), routeParameters: ['tab' => 'tax'], destinationKey: 'tax-settings'),
            $this->step('document-sequences', __('Document sequences'), __('Configure persisted prefixes, counters, scope, and reset rules without changing posted history.'), 'admin.settings', 'company_settings.view', $this->documentSequencesReady(), DocumentSequence::query()->count(), routeParameters: ['tab' => 'sequences'], destinationKey: 'document-sequences'),
            $this->step('printers', __('Printer profiles'), __('Review an active printer profile and its saved destination; hardware acceptance remains separate.'), 'admin.settings', 'company_settings.view', $this->printersReady(), PrinterConfiguration::query()->count(), routeParameters: ['tab' => 'printers', 'section' => 'printer-profiles'], destinationKey: 'printer-profiles'),
            $this->step('print-templates', __('Print-template assignments'), __('Review the existing template key assigned to each printer profile. Template layouts are not edited in this workspace.'), 'admin.settings', 'company_settings.view', $this->printTemplatesReady(), PrinterConfiguration::query()->where('status', 'active')->whereNotNull('template_name')->where('template_name', '!=', '')->count(), routeParameters: ['tab' => 'printers', 'section' => 'print-templates'], destinationKey: 'print-templates'),
            $this->step('categories', __('Categories'), __('Create the approved active category hierarchy before adding product masters.'), 'catalog.categories', 'products_categories_brands.view', Category::query()->where('status', 'active')->exists(), Category::query()->count()),
            $this->step('brands', __('Brand masters'), __('Brand masters are optional. Create active brands only where the catalog needs branded products.'), 'catalog.brands', 'products_categories_brands.view', Brand::query()->where('status', 'active')->exists(), Brand::query()->count(), required: false),
            $this->step('customer-groups', __('Customer groups'), __('Maintain the persisted customer-group hierarchy before customer registration.'), 'customers.groups.index', 'customers.view', $activeCustomerGroups->exists(), $activeCustomerGroups->count()),
            $this->step('customers', __('Customers'), __('Add genuine customer data only after non-empty latest consent purpose, wording, and retention policies are saved; never preload fabricated personal data.'), 'customers.index', 'customers.view', $customerConsentPolicyRecords === 3, $customerConsentPolicyRecords, required: false),
            $this->step('party-readiness', __('Party readiness and policies'), __('Save the separate Party workflow, privacy, service, scheduling, and invoice policy configuration before taking Party bookings. Readiness remains incomplete until the owner defines the exact mandatory Party policy subset.'), 'party.readiness', 'party_bookings_invoices.view', false, max($partyPolicyRecords, PartyBooking::query()->count()), __('Party policy completion remains pending an owner decision; persisted policy values do not authorize bookings.'), required: false),
            $this->step('supplier-groups', __('Supplier groups'), __('Maintain the persisted supplier-group hierarchy before supplier registration.'), 'catalog.suppliers', 'suppliers.view', $activeSupplierGroups->exists(), $activeSupplierGroups->count(), routeParameters: ['section' => 'supplier-groups'], destinationKey: 'supplier-groups'),
            $this->step('suppliers', __('Suppliers and supplier SKUs'), __('Create approved suppliers and connect each supplied sellable SKU to its supplier item code.'), 'suppliers.index', 'suppliers.view', $this->suppliersReady(), Supplier::query()->count(), routeParameters: ['section' => 'supplier-masters'], destinationKey: 'supplier-masters'),
            $this->step('product-masters', __('Product masters'), __('Build active sellable products, including unbranded products, and valid variation families from the approved catalog. Supplier SKU linkage follows on its own supplier step.'), 'catalog.products', 'products_categories_brands.view', $catalogReady, Product::query()->where('status', 'active')->count(), Category::query()->where('status', 'active')->exists() ? null : __('Create at least one active category before product masters.')),
            $this->step('product-import', __('Product import (optional)'), __('Manual product entry is sufficient. Use the reviewed import workspace only when a validated source file is available.'), 'catalog.products.import', 'products_categories_brands.create', true, 0, __('Manual entry is sufficient; import is optional and never a setup prerequisite.'), false),
            $this->step('prices', __('Approved selling prices'), __('Create and approve an effective store price for every sellable SKU before POS use.'), 'pricing.index', 'pricing_labels.view', $this->pricesReady(), PriceLine::query()->count()),
            $this->step('opening-configuration', __('Opening inventory'), __('Start an opening-stock entry through the controlled adjustment form. Choose the approved opening reason; balances are never edited directly. Production zero-start path remains subject to owner approval.'), 'inventory.adjustments.create', 'inventory_stock_card.create', $this->openingInventoryReady(), InventoryAdjustment::query()->where('adjustment_type', 'entry')->count(), $branchesReady && $catalogReady ? null : __('Complete active branches, stores, categories, and product masters before opening inventory.')),
        ];

        $required = array_values(array_filter($steps, static fn (array $step): bool => $step['required']));
        $completed = count(array_filter($required, static fn (array $step): bool => $step['complete']));
        $count = count($required);

        return ['steps' => $steps, 'owner_decisions' => $this->ownerDecisions(), 'completed_count' => $completed, 'required_count' => $count, 'progress_percent' => $count === 0 ? 100 : (int) round(($completed / $count) * 100), 'needs_attention' => $completed < $count, 'complete' => $completed === $count];
    }

    /** @return array<string, mixed> */
    private function step(string $key, string $label, string $description, ?string $routeName, ?string $permission, bool $complete, int $records, ?string $blockedReason = null, bool $required = true, array $routeParameters = [], ?string $destinationKey = null): array
    {
        $status = $blockedReason !== null && $required ? 'blocked' : ($complete ? ($required ? 'completed' : 'ready') : ($records === 0 ? 'not_started' : 'incomplete'));
        $route = $routeName === null ? null : route($routeName, $routeParameters);
        $canAccess = $permission !== null && auth()->check() && auth()->user()->can($permission);

        return ['key' => $key, 'destination_key' => $destinationKey ?? $key, 'label' => $label, 'description' => $description, 'reason' => $blockedReason ?? ($complete ? __('Persisted data meets the current readiness rule.') : ($records === 0 ? __('No matching persisted records were found yet.') : __('Some persisted records exist, but the readiness rule is not satisfied.'))), 'route' => $route, 'route_name' => $routeName, 'permission' => $permission, 'can_access' => $canAccess, 'complete' => $complete, 'required' => $required, 'records' => $records, 'status' => $status, 'status_label' => __(match ($status) {
            'not_started' => 'Not started', 'incomplete' => 'Incomplete', 'ready' => 'Ready', 'blocked' => 'Blocked', default => 'Completed',
        }), 'cta_label' => __($complete ? 'Review' : 'Configure')];
    }

    private function companyReady(): bool
    {
        return Company::query()->where('status', 'active')->whereNotIn('code', ['', 'TBD'])->whereNotNull('name_ar')->where('name_ar', '!=', '')->whereNotNull('name_en')->where('name_en', '!=', '')->whereNotIn('currency_code', ['', 'TBD'])->whereNotIn('currency_symbol', ['', 'TBD'])->whereNotNull('timezone')->where('timezone', '!=', '')->exists();
    }

    private function branchesAndStoresReady(): bool
    {
        return Branch::query()->where('status', 'active')->exists()
            && Store::query()->where('status', 'active')->whereHas('branch', fn (Builder $query): Builder => $query->where('status', 'active'))->exists()
            && $this->retailBranchesHaveOneCurrentValidSellingStoreMapping();
    }

    private function warehousesReady(): bool { return Store::query()->where('type', 'warehouse')->where('status', 'active')->whereHas('branch', fn (Builder $query): Builder => $query->where('status', 'active'))->exists(); }
    private function posSellingLocationReady(): bool
    {
        return $this->retailBranchesHaveOneCurrentValidSellingStoreMapping();
    }

    private function retailBranchesHaveOneCurrentValidSellingStoreMapping(): bool
    {
        $retailBranchIds = Branch::query()
            ->where('status', 'active')
            ->whereHas('stores', fn (Builder $query): Builder => $query->where('status', 'active')->where('type', 'selling'))
            ->pluck('id');

        $currentMappingCounts = BranchSellingStore::query()
            ->whereIn('branch_id', $retailBranchIds)
            ->where('status', 'active')
            ->where(fn (Builder $query): Builder => $query->whereNull('effective_from')->orWhere('effective_from', '<=', now()))
            ->where(fn (Builder $query): Builder => $query->whereNull('effective_to')->orWhere('effective_to', '>', now()))
            ->whereHas('store', fn (Builder $query): Builder => $query
                ->where('status', 'active')
                ->where('type', 'selling')
                ->whereColumn('stores.branch_id', 'branch_selling_stores.branch_id'))
            ->selectRaw('branch_id, count(*) as mappings')
            ->groupBy('branch_id')
            ->pluck('mappings', 'branch_id');

        return $retailBranchIds->count() === $currentMappingCounts->count()
            && $currentMappingCounts->every(static fn (int|string $count): bool => (int) $count === 1);
    }
    private function cashDrawersReady(): bool { return CashDrawer::query()->where('status', 'active')->whereHas('store', fn (Builder $query): Builder => $query->where('status', 'active'))->exists(); }
    private function paymentMethodsReady(): bool { return PaymentMethod::query()->where('status', 'active')->exists(); }
    private function taxesReady(): bool { return TaxSetting::query()->where('status', 'active')->exists(); }
    private function documentSequencesReady(): bool { return DocumentSequence::query()->where('status', 'active')->exists(); }
    private function printersReady(): bool { return PrinterConfiguration::query()->where('status', 'active')->exists(); }
    private function printTemplatesReady(): bool { return PrinterConfiguration::query()->where('status', 'active')->whereNotNull('template_name')->where('template_name', '!=', '')->exists(); }

    /** @return list<array<string, mixed>> */
    private function ownerDecisions(): array
    {
        return [
            $this->ownerDecision('warehouse-taxonomy', __('Warehouse taxonomy'), __('Confirm which stores are physical warehouses and which are selling or service locations before inventory routing.'), __('Review stores'), 'admin.stores', 'branches_stores.view'),
            $this->ownerDecision('timezone-provenance', __('Timezone provenance and branch override'), __('Confirm the company timezone source and whether each branch may override it.'), __('Review branches'), 'admin.branches', 'branches_stores.view'),
            $this->ownerDecision('payment-offline-policy', __('Payment and offline policy'), __('Confirm allowed tender types, evidence requirements, and which methods may be used by the local offline flow.'), __('Review payment settings'), 'admin.settings', 'company_settings.view', ['tab' => 'payments']),
            $this->ownerDecision('tax-treatment-policy', __('Tax treatment and zero-tax distinctions'), __('Confirm standard, zero-rated, exempt, and out-of-scope treatment plus the effective default before operational use.'), __('Review tax settings'), 'admin.settings', 'company_settings.view', ['tab' => 'tax']),
            $this->ownerDecision('document-numbering-policy', __('Document numbering policy'), __('Confirm sequence scope, daily reset behavior, prefix, suffix, padding, and any authorized correction process.'), __('Review numbering'), 'admin.settings', 'company_settings.view', ['tab' => 'sequences']),
            $this->ownerDecision('printer-output-policy', __('Printer and template policy'), __('Confirm the intended branch or location printer profile and template; physical hardware acceptance remains separate.'), __('Review printers'), 'admin.settings', 'company_settings.view', ['tab' => 'printers']),
            $this->ownerDecision('customer-policy', __('Customer consent, loyalty, and wallet policy'), __('Confirm consent purposes, child-profile handling, loyalty rules, and Product/Party Wallet boundaries before publishing policy versions.'), __('Review customer policy settings'), 'admin.settings.customer-loyalty', 'company_settings.view'),
            $this->ownerDecision('customer-data-entry', __('Customer and child data entry'), __('Use the customer screens to review the owner-approved consent purpose and child-profile capture before collecting genuine data.'), __('Review customer screens'), 'customers.index', 'customers.view'),
            $this->ownerDecision('supplier-payment-recipient', __('Supplier payment terms and recipient policy'), __('Confirm supplier payment terms and the intended order/invoice recipient before purchasing communication is used.'), __('Review suppliers'), 'suppliers.index', 'suppliers.view'),
        ];
    }

    /** @param array<string, mixed> $parameters @return array<string, mixed> */
    private function ownerDecision(string $key, string $title, string $description, string $ctaLabel, string $routeName, string $permission, array $parameters = []): array
    {
        return [
            'key' => $key,
            'title' => $title,
            'description' => $description,
            'status' => 'requires_owner_decision',
            'status_label' => __('Requires owner decision'),
            'cta_label' => $ctaLabel,
            'route_name' => $routeName,
            'route' => route($routeName, $parameters),
            'permission' => $permission,
            'can_access' => auth()->check() && auth()->user()->can($permission),
        ];
    }

    private function usersAndScopesReady(): bool
    {
        $administrator = User::query()->where('status', 'active')->where('is_super_admin', true)->whereHas('roles', fn (Builder $query): Builder => $query->where('roles.code', 'system-administrator')->where('roles.status', 'active'))->exists();
        $unscoped = User::query()->where('status', 'active')->where('is_super_admin', false)->where(fn (Builder $query): Builder => $query->whereDoesntHave('roles', fn (Builder $role): Builder => $role->where('roles.status', 'active'))->orWhere(fn (Builder $scope): Builder => $scope->whereDoesntHave('branchScopes', fn (Builder $branch): Builder => $branch->where('status', 'active'))->whereDoesntHave('storeScopes', fn (Builder $store): Builder => $store->where('status', 'active'))))->exists();
        return $administrator && ! $unscoped;
    }

    private function catalogReady(): bool
    {
        $broken = Product::query()->whereNull('parent_product_id')->where('has_variations', true)->where('status', 'active')->whereDoesntHave('variants', fn (Builder $query): Builder => $query->where('status', 'active'))->exists();
        return Category::query()->where('status', 'active')->exists() && Product::query()->sellable()->exists() && ! $broken;
    }

    private function suppliersReady(): bool
    {
        return Supplier::query()->where('status', 'active')->exists() && ProductSupplier::query()->whereHas('supplier', fn (Builder $query): Builder => $query->where('status', 'active'))->whereHas('product', fn (Builder $query): Builder => $query->sellable())->whereNotNull('supplier_item_code')->where('supplier_item_code', '!=', '')->exists();
    }

    private function pricesReady(): bool
    {
        return PriceLine::query()->whereHas('version', function (Builder $query): void { $query->where('state', PriceVersionState::Approved->value)->where(fn (Builder $from): Builder => $from->whereNull('effective_from')->orWhere('effective_from', '<=', now()))->where(fn (Builder $to): Builder => $to->whereNull('effective_to')->orWhere('effective_to', '>', now())); })->whereHas('product', fn (Builder $query): Builder => $query->sellable())->whereHas('store', fn (Builder $query): Builder => $query->where('status', 'active'))->exists();
    }

    private function openingInventoryReady(): bool { return InventoryAdjustment::query()->where('status', 'approved')->where('adjustment_type', 'entry')->whereIn('reason_code', ['opening_stock', 'opening_inventory'])->whereNull('reversal_of_id')->exists(); }
    private function customerConsentPolicyRecords(): int
    {
        return collect(['customer.consent.purpose', 'customer.consent.wording', 'customer.consent.retention'])
            ->filter(fn (string $key): bool => trim((string) CustomerPolicySettingVersion::query()->where('key', $key)->latest('version')->value('value')) !== '')
            ->count();
    }

    private function partyPolicyRecords(): int
    {
        return CustomerPolicySettingVersion::query()
            ->where('key', 'like', 'party.%')
            ->orderBy('key')
            ->orderByDesc('version')
            ->get(['key', 'value'])
            ->unique('key')
            ->filter(static fn (CustomerPolicySettingVersion $setting): bool => trim((string) $setting->value) !== '')
            ->count();
    }
}
