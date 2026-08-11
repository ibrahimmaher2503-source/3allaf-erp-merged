<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Customer\Actions\ApproveLoyaltyAdjustmentAction;
use App\Modules\Customer\Actions\PostPartyWalletEntryAction;
use App\Modules\Customer\Actions\PostProductWalletEntryAction;
use App\Modules\Customer\Actions\RequestPartyWalletAdjustmentAction;
use App\Modules\Customer\Actions\RequestProductWalletAdjustmentAction;
use App\Modules\Customer\Actions\CreateCustomerAction;
use App\Modules\Customer\Actions\ExpireLoyaltyAction;
use App\Modules\Customer\Actions\MergeCustomersAction;
use App\Modules\Customer\Actions\RecordCustomerConsentAction;
use App\Modules\Customer\Actions\RedeemLoyaltyAction;
use App\Modules\Customer\Actions\RejectLoyaltyAdjustmentAction;
use App\Modules\Customer\Actions\RequestLoyaltyAdjustmentAction;
use App\Modules\Customer\Actions\SaveCustomerChildAction;
use App\Modules\Customer\Actions\UpdateCustomerAction;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerConsent;
use App\Modules\Customer\Models\LoyaltyAdjustment;
use App\Modules\Customer\Models\LoyaltyLedger;
use App\Modules\Customer\Models\PartyWalletAdjustment;
use App\Modules\Customer\Models\PartyWalletLedger;
use App\Modules\Customer\Models\ProductWalletAdjustment;
use App\Modules\Customer\Models\ProductWalletLedger;
use App\Modules\Customer\Support\PartyWalletBalance;
use App\Modules\Customer\Support\ProductWalletBalance;
use App\Modules\Customer\Support\CustomerPolicy;
use App\Modules\Customer\Support\WalletPolicy;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\Store;
use App\Modules\Retail\Models\Sale;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

Route::middleware(['auth', 'verified'])->group(function (): void {
    $sellingStore = static function (User $user): Store {
        return Store::query()->visibleTo($user)->where('type', 'selling')->where('status', 'active')->orderBy('id')->firstOrFail();
    };

    Route::get('customers', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('customers.view'), 403);
        $mode = (string) $request->string('mode', 'master');
        abort_unless(in_array($mode, ['master', 'history', 'loyalty'], true), 404);
        abort_if($mode === 'loyalty' && ! $user->can('loyalty.view'), 403);

        $query = Customer::query()->visibleTo($user)->active()->with(['scopes.store', 'scopes.branch'])->withCount(['consents', 'children']);
        $term = trim((string) $request->string('q'));
        if ($term !== '') {
            $digits = preg_replace('/[^0-9]+/', '', $term);
            $query->where(function ($scope) use ($term, $digits): void {
                $scope->where('name_ar', 'like', '%'.$term.'%')
                    ->orWhere('name_en', 'like', '%'.$term.'%');
                if (is_string($digits) && $digits !== '') {
                    $scope->orWhere('phone_normalized', 'like', '%'.$digits.'%');
                }
            });
        }
        $query->when($request->filled('status'), fn ($builder) => $builder->where('status', (string) $request->string('status')));
        $customers = $query->latest('id')->paginate(20)->withQueryString();

        return view('pages.customers.index', ['customers' => $customers, 'term' => $term, 'mode' => $mode]);
    })->middleware('can:customers.view')->name('customers.index');

    Route::get('customers/export', function (Request $request) use ($sellingStore) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('customers.export'), 403);
        $store = $sellingStore($user);
        $term = trim((string) $request->string('q'));
        $query = Customer::query()->visibleFrom($user, (int) $store->branch_id, (int) $store->id)->where('status', 'active')->limit(500);
        if ($term !== '') {
            $digits = preg_replace('/[^0-9]+/', '', $term);
            $query->where(function ($scope) use ($term, $digits): void {
                $scope->where('name_ar', 'like', '%'.$term.'%')->orWhere('name_en', 'like', '%'.$term.'%');
                if (is_string($digits) && $digits !== '') {
                    $scope->orWhere('phone_normalized', 'like', '%'.$digits.'%');
                }
            });
        }
        $rows = $query->orderBy('id')->get(['id', 'public_id', 'phone_display', 'name_ar', 'name_en', 'email', 'status']);
        app(RecordAuditEvent::class)->execute(category: 'reporting', event: 'customer_exported', branchId: (int) $store->branch_id, storeId: (int) $store->id, metadata: ['row_count' => $rows->count(), 'filter' => $term, 'permission' => 'customers.export', 'scope_limited' => true]);

        return response()->streamDownload(static function () use ($rows): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, ['public_id', 'phone', 'name_ar', 'name_en', 'email', 'status']);
            foreach ($rows as $customer) {
                fputcsv($handle, [$customer->public_id, $customer->phone_display, $customer->name_ar, $customer->name_en, $customer->email, $customer->status]);
            }
            fclose($handle);
        }, 'customers.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    })->middleware('can:customers.export')->name('customers.export');

    Route::get('customers/create', function (Request $request) use ($sellingStore) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('customers.create'), 403);
        $store = $sellingStore($user);
        $consentPurposes = [];
        $childPurposes = [];
        $policyError = null;
        try {
            $consentPurposes = CustomerPolicy::allowedPurposes('customer.consent.purpose')['value'];
            $childPurposes = CustomerPolicy::childPurposes()['value'];
        } catch (InvalidArgumentException $exception) {
            $policyError = $exception->getMessage();
        }

        return view('pages.customers.create', compact('store', 'consentPurposes', 'childPurposes', 'policyError'));
    })->middleware('can:customers.create')->name('customers.create');

    Route::post('customers', function (Request $request, CreateCustomerAction $action) use ($sellingStore) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('customers.create'), 403);
        $validated = $request->validate([
            'idempotency_key' => ['required', 'uuid'],
            'phone' => ['required', 'string', 'max:64'],
            'name_ar' => ['required', 'string', 'max:190'],
            'name_en' => ['required', 'string', 'max:190'],
            'email' => ['nullable', 'email', 'max:190'],
            'secondary_phone' => ['nullable', 'string', 'max:64'],
            'address_ar' => ['nullable', 'string', 'max:4000'],
            'address_en' => ['nullable', 'string', 'max:4000'],
            'consent_purpose' => ['required', 'string', 'max:80'],
            'consent_status' => ['required', Rule::in(['granted', 'withdrawn', 'denied'])],
            'child_name_ar' => ['nullable', 'string', 'max:190'],
            'child_name_en' => ['nullable', 'string', 'max:190'],
            'child_birth_date' => ['nullable', 'date'],
            'child_purpose' => ['nullable', 'string', 'max:80'],
        ]);
        $child = filled($validated['child_name_ar'] ?? null) || filled($validated['child_name_en'] ?? null)
            ? [[
                'name_ar' => $validated['child_name_ar'] ?? '',
                'name_en' => $validated['child_name_en'] ?? '',
                'birth_date' => $validated['child_birth_date'] ?? null,
                'purpose' => $validated['child_purpose'] ?? '',
            ]]
            : [];

        try {
            $customer = $action->execute($user, $sellingStore($user), $validated + [
                'consents' => [[
                    'purpose' => $validated['consent_purpose'],
                    'status' => $validated['consent_status'],
                    'source' => 'profile_create',
                ]],
                'children' => $child,
            ]);
        } catch (InvalidArgumentException|UniqueConstraintViolationException $exception) {
            return back()->withInput()->withErrors(['customer' => $exception->getMessage()]);
        }

        return to_route('customers.show', $customer)->with('success', __('Customer profile created.'));
    })->middleware('can:customers.create')->name('customers.store');

    Route::get('customers/{customerId}', function (Request $request, int $customerId) use ($sellingStore) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('customers.view'), 403);
        $store = $sellingStore($user);
        $customer = Customer::query()->visibleTo($user)->whereKey($customerId)->where('status', 'active')->firstOrFail();
        $customer->load(['scopes.store', 'scopes.branch']);
        $historyIds = Customer::query()->where(fn ($query) => $query->whereKey($customer->id)->orWhere('merged_into_id', $customer->id))->pluck('id');
        $consents = collect();
        if ($user->can('customers.sensitive')) {
            $consents = CustomerConsent::query()->visibleTo($user)->whereIn('customer_id', $historyIds)->latest('id')->get();
            $customer->load(['children' => fn ($query) => $query->visibleTo($user)->where('status', 'active')->latest('id')]);
        }
        $sales = Sale::query()->visibleTo($user)->whereIn('customer_id', $historyIds)->approved()->with('store')->latest('approved_at')->paginate(10, ['*'], 'sales_page')->withQueryString();
        $balance = (int) LoyaltyLedger::query()->visibleTo($user)->whereIn('customer_id', $historyIds)->sum('points');
        $dueExpiry = (int) LoyaltyLedger::query()->visibleTo($user)->whereIn('customer_id', $historyIds)->where('points', '>', 0)->whereNotNull('expires_at')->where('expires_at', '<=', now())->sum('points');
        $adjustments = $user->can('loyalty.view') ? LoyaltyAdjustment::query()->visibleTo($user)->whereIn('customer_id', $historyIds)->latest('id')->limit(10)->get() : collect();
        $productWalletBalance = $user->can('product_wallet.view') ? app(ProductWalletBalance::class)->forCustomer($customer, $user) : null;
        $partyWalletBalance = $user->can('party_wallet.view') ? app(PartyWalletBalance::class)->forCustomer($customer, $user) : null;

        return view('pages.customers.show', compact('customer', 'store', 'sales', 'balance', 'dueExpiry', 'adjustments', 'consents', 'productWalletBalance', 'partyWalletBalance'));
    })->middleware('can:customers.view')->name('customers.show');

    Route::put('customers/{customerId}', function (Request $request, int $customerId, UpdateCustomerAction $action) use ($sellingStore) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('customers.edit'), 403);
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:64'],
            'name_ar' => ['required', 'string', 'max:190'],
            'name_en' => ['required', 'string', 'max:190'],
            'email' => ['nullable', 'email', 'max:190'],
            'secondary_phone' => ['nullable', 'string', 'max:64'],
            'address_ar' => ['nullable', 'string', 'max:4000'],
            'address_en' => ['nullable', 'string', 'max:4000'],
        ]);
        $store = $sellingStore($user);
        $customer = Customer::query()->visibleTo($user)->whereKey($customerId)->where('status', 'active')->firstOrFail();
        try {
            $saved = $action->execute($user, $customer, $store, $validated);
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['customer' => $exception->getMessage()]);
        }

        return to_route('customers.show', $saved)->with('success', __('Customer profile updated.'));
    })->middleware('can:customers.edit')->name('customers.update');

    Route::post('customers/{customerId}/consents', function (Request $request, int $customerId, RecordCustomerConsentAction $action) use ($sellingStore) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('customers.sensitive'), 403);
        $validated = $request->validate(['purpose' => ['required', 'string', 'max:80'], 'status' => ['required', Rule::in(['granted', 'withdrawn', 'denied'])], 'idempotency_key' => ['required', 'uuid']]);
        $customer = Customer::query()->visibleTo($user)->whereKey($customerId)->where('status', 'active')->firstOrFail();
        try {
            $action->execute($user, $customer, $sellingStore($user), $validated['purpose'], $validated['status'], 'profile', 'CONSENT:'.$validated['idempotency_key']);
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['consent' => $exception->getMessage()]);
        }

        return back()->with('success', __('Consent history recorded.'));
    })->middleware('can:customers.sensitive')->name('customers.consents.store');

    Route::post('customers/{customerId}/children', function (Request $request, int $customerId, SaveCustomerChildAction $action) use ($sellingStore) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('customers.sensitive'), 403);
        $validated = $request->validate(['name_ar' => ['required', 'string', 'max:190'], 'name_en' => ['required', 'string', 'max:190'], 'birth_date' => ['nullable', 'date'], 'purpose' => ['required', 'string', 'max:80']]);
        $customer = Customer::query()->visibleTo($user)->whereKey($customerId)->where('status', 'active')->firstOrFail();
        try {
            $action->execute($user, $customer, $sellingStore($user), $validated);
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['child' => $exception->getMessage()]);
        }

        return back()->with('success', __('Child profile recorded.'));
    })->middleware('can:customers.sensitive')->name('customers.children.store');

    Route::post('customers/{customerId}/merge', function (Request $request, int $customerId, MergeCustomersAction $action) use ($sellingStore) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('customers.merge'), 403);
        $validated = $request->validate(['survivor_id' => ['required', 'integer'], 'reason' => ['required', 'string', 'max:1000'], 'idempotency_key' => ['required', 'uuid']]);
        $store = $sellingStore($user);
        $duplicate = Customer::query()->visibleTo($user)->whereKey($customerId)->where('status', 'active')->firstOrFail();
        $survivor = Customer::query()->visibleTo($user)->whereKey($validated['survivor_id'])->where('status', 'active')->firstOrFail();
        try {
            $merged = $action->execute($user, $duplicate, $survivor, $store, $validated['reason'], 'MERGE:'.$validated['idempotency_key']);
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['merge' => $exception->getMessage()]);
        }

        return to_route('customers.show', $merged)->with('success', __('Customer profiles merged with history preserved.'));
    })->middleware('can:customers.merge')->name('customers.merge');

    Route::get('customers/{customerId}/loyalty', function (Request $request, int $customerId) use ($sellingStore) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('loyalty.view'), 403);
        $store = $sellingStore($user);
        $customer = Customer::query()->visibleTo($user)->whereKey($customerId)->where('status', 'active')->firstOrFail();
        $historyIds = Customer::query()->where(fn ($query) => $query->whereKey($customer->id)->orWhere('merged_into_id', $customer->id))->pluck('id');
        $entries = LoyaltyLedger::query()->visibleTo($user)->whereIn('customer_id', $historyIds)->latestFirst()->paginate(20)->withQueryString();
        $balance = (int) LoyaltyLedger::query()->visibleTo($user)->whereIn('customer_id', $historyIds)->sum('points');
        $dueExpiry = (int) LoyaltyLedger::query()->visibleTo($user)->whereIn('customer_id', $historyIds)->where('points', '>', 0)->whereNotNull('expires_at')->where('expires_at', '<=', now())->sum('points');
        $adjustments = LoyaltyAdjustment::query()->visibleTo($user)->whereIn('customer_id', $historyIds)->with('approvalRecord')->latest('id')->limit(20)->get();
        $approvedSales = Sale::query()->visibleTo($user)->whereIn('customer_id', $historyIds)->approved()->latest('approved_at')->limit(20)->get(['id', 'document_number', 'approved_at', 'total']);
        $pendingApprovals = $user->can('loyalty.approve')
            ? ApprovalRecord::query()->visibleTo($user)->where('source_type', 'loyalty_adjustments')->whereIn('source_id', $adjustments->pluck('id')->map(static fn (mixed $id): string => (string) $id)->all())->where('approval_state', 'pending')->where('decision_permission', 'loyalty.approve')->latest('id')->limit(20)->get()
            : collect();

        return view('pages.customers.loyalty', compact('customer', 'store', 'entries', 'balance', 'dueExpiry', 'adjustments', 'approvedSales', 'pendingApprovals'));
    })->middleware('can:loyalty.view')->name('customers.loyalty');

    Route::get('customers/{customerId}/loyalty/export', function (Request $request, int $customerId) use ($sellingStore) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('loyalty.export'), 403);
        $store = $sellingStore($user);
        $customer = Customer::query()->visibleTo($user)->whereKey($customerId)->where('status', 'active')->firstOrFail();
        $historyIds = Customer::query()->where(fn ($query) => $query->whereKey($customer->id)->orWhere('merged_into_id', $customer->id))->pluck('id');
        $rows = LoyaltyLedger::query()
            ->visibleTo($user)
            ->whereIn('customer_id', $historyIds)
            ->latestFirst()
            ->limit(500)
            ->get(['customer_id', 'activity', 'event_type', 'points', 'balance_before', 'balance_after', 'effective_at', 'expires_at', 'source_type', 'source_id', 'source_reference', 'rule_key', 'rule_version', 'reason', 'branch_id', 'store_id']);

        app(RecordAuditEvent::class)->execute(
            category: 'reporting',
            event: 'loyalty_exported',
            branchId: (int) $store->branch_id,
            storeId: (int) $store->id,
            metadata: ['customer_id' => $customer->id, 'row_count' => $rows->count(), 'permission' => 'loyalty.export', 'scope_limited' => true],
        );

        return response()->streamDownload(static function () use ($rows): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, ['customer_id', 'activity', 'event', 'points', 'balance_before', 'balance_after', 'effective_at', 'expires_at', 'source_type', 'source_id', 'source_reference', 'rule_key', 'rule_version', 'reason', 'branch_id', 'store_id']);
            foreach ($rows as $entry) {
                fputcsv($handle, [$entry->customer_id, $entry->activity, $entry->event_type, $entry->points, $entry->balance_before, $entry->balance_after, $entry->effective_at?->format('c'), $entry->expires_at?->format('c'), $entry->source_type, $entry->source_id, $entry->source_reference, $entry->rule_key, $entry->rule_version, $entry->reason, $entry->branch_id, $entry->store_id]);
            }
            fclose($handle);
        }, 'customer-loyalty.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    })->middleware('can:loyalty.export')->name('customers.loyalty.export');

    Route::post('customers/{customerId}/loyalty/redeem', function (Request $request, int $customerId, RedeemLoyaltyAction $action) use ($sellingStore) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('loyalty.redeem'), 403);
        $validated = $request->validate(['source_sale_id' => ['required', 'integer'], 'points' => ['required', 'integer', 'min:1'], 'idempotency_key' => ['required', 'uuid']]);
        $store = $sellingStore($user);
        $customer = Customer::query()->visibleTo($user)->whereKey($customerId)->where('status', 'active')->firstOrFail();
        $sale = Sale::query()->visibleTo($user)->approved()->where('customer_id', $customer->id)->whereKey($validated['source_sale_id'])->firstOrFail();
        try {
            $action->execute($user, $customer, $store, $sale, (int) $validated['points'], 'REDEEM:'.$validated['idempotency_key']);
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['redeem' => $exception->getMessage()]);
        }

        return back()->with('success', __('Loyalty redemption recorded against the approved sale.'));
    })->middleware('can:loyalty.redeem')->name('customers.loyalty.redeem');

    Route::post('customers/{customerId}/loyalty/adjustments', function (Request $request, int $customerId, RequestLoyaltyAdjustmentAction $action) use ($sellingStore) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('loyalty.adjust'), 403);
        $validated = $request->validate(['points' => ['required', 'integer', 'not_in:0'], 'reason' => ['required', 'string', 'max:1000'], 'source_reference' => ['nullable', 'string', 'max:190'], 'idempotency_key' => ['required', 'uuid']]);
        $customer = Customer::query()->visibleTo($user)->whereKey($customerId)->where('status', 'active')->firstOrFail();
        try {
            $action->execute($user, $customer, $sellingStore($user), (int) $validated['points'], $validated['reason'], 'ADJUST:'.$validated['idempotency_key'], $validated['source_reference'] ?? null);
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['adjustment' => $exception->getMessage()]);
        }

        return back()->with('success', __('Loyalty adjustment submitted for approval.'));
    })->middleware('can:loyalty.adjust')->name('customers.loyalty.adjustments.store');

    Route::post('customers/{customerId}/loyalty/expire', function (Request $request, int $customerId, ExpireLoyaltyAction $action) use ($sellingStore) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('loyalty.expire'), 403);
        $customer = Customer::query()->visibleTo($user)->whereKey($customerId)->where('status', 'active')->firstOrFail();
        $count = $action->execute($user, $customer, $sellingStore($user));

        return back()->with('success', __(':count loyalty expiry entries posted.', ['count' => $count]));
    })->middleware('can:loyalty.expire')->name('customers.loyalty.expire');

    Route::post('loyalty/adjustments/{approvalId}/approve', function (Request $request, int $approvalId, ApproveLoyaltyAdjustmentAction $action) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('loyalty.approve'), 403);
        $approval = ApprovalRecord::query()->visibleTo($user)->whereKey($approvalId)->where('source_type', 'loyalty_adjustments')->firstOrFail();
        $store = Store::query()->visibleTo($user)->whereKey($approval->store_id)->where('status', 'active')->firstOrFail();
        try {
            $action->execute($user, $approval, $store);
        } catch (InvalidArgumentException|ValidationException $exception) {
            return back()->withErrors(['approval' => $exception->getMessage()]);
        }

        return back()->with('success', __('Loyalty adjustment approved and posted.'));
    })->middleware('can:loyalty.approve')->name('loyalty.adjustments.approve');

    Route::post('loyalty/adjustments/{approvalId}/reject', function (Request $request, int $approvalId, RejectLoyaltyAdjustmentAction $action) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('loyalty.approve'), 403);
        $validated = $request->validate(['decision_note' => ['required', 'string', 'min:3', 'max:1000']]);
        $approval = ApprovalRecord::query()->visibleTo($user)->whereKey($approvalId)->where('source_type', 'loyalty_adjustments')->firstOrFail();
        try {
            $action->execute($user, $approval, $validated['decision_note']);
        } catch (ValidationException|InvalidArgumentException $exception) {
            return back()->withErrors(['approval' => $exception->getMessage()]);
        }

        return back()->with('success', __('Loyalty adjustment rejected and audited.'));
    })->middleware('can:loyalty.approve')->name('loyalty.adjustments.reject');

    Route::get('customers/{customerId}/product-wallet', function (Request $request, int $customerId) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('product_wallet.view'), 403);
        $customer = Customer::query()->visibleTo($user)->whereKey($customerId)->where('status', 'active')->firstOrFail();
        $entries = ProductWalletLedger::query()->visibleTo($user)->where('customer_id', $customer->id)->with(['customer', 'store'])->latestFirst()->paginate(20)->withQueryString();
        $store = Store::query()->visibleTo($user)->where('status', 'active')->with('company')->orderBy('id')->firstOrFail();
        $policyError = null;
        try {
            WalletPolicy::for('product');
        } catch (InvalidArgumentException $exception) {
            $policyError = $exception->getMessage();
        }
        $adjustmentIds = ProductWalletAdjustment::query()->visibleTo($user)->where('customer_id', $customer->id)->pluck('id')->map(static fn (mixed $id): string => (string) $id)->all();
        $pendingAdjustments = $user->can('product_wallet.approve') && $adjustmentIds !== []
            ? ApprovalRecord::query()->visibleTo($user)->where('source_type', 'product_wallet_adjustments')->whereIn('source_id', $adjustmentIds)->where('approval_state', 'pending')->where('decision_permission', 'product_wallet.approve')->latest('id')->limit(20)->get()
            : collect();

        return view('pages.wallets.ledger', [
            'title' => __('Product Wallet'), 'description' => __('Retail-only customer balance derived from a separate immutable, source-linked ledger.'),
            'wallet' => 'product', 'customer' => $customer, 'ledgerTable' => 'product_wallet_ledger', 'entries' => $entries,
            'balance' => app(ProductWalletBalance::class)->forCustomer($customer, $user), 'currencyCode' => strtoupper((string) $store->company?->currency_code), 'policyError' => $policyError,
            'pendingAdjustments' => $pendingAdjustments, 'canSettle' => $user->can('product_wallet.settle'), 'canAdjust' => $user->can('product_wallet.adjust'), 'canApprove' => $user->can('product_wallet.approve'),
            'otherRoute' => 'wallets.party', 'otherCustomerRoute' => 'customers.party-wallet', 'otherPermission' => 'party_wallet.view', 'otherLabel' => __('Open Party Wallet'),
            'exportRoute' => $user->can('product_wallet.export') ? route('customers.product-wallet.export', $customer) : null,
            'settlementRoute' => route('customers.product-wallet.settle', $customer), 'adjustmentRoute' => route('customers.product-wallet.adjustments.store', $customer),
            'approveRoute' => static fn (int $approvalId): string => route('wallets.product.adjustments.approve', $approvalId), 'rejectRoute' => static fn (int $approvalId): string => route('wallets.product.adjustments.reject', $approvalId),
            'guidePrefix' => 'product-wallet',
        ]);
    })->middleware('can:product_wallet.view')->name('customers.product-wallet');

    Route::get('customers/{customerId}/party-wallet', function (Request $request, int $customerId) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('party_wallet.view'), 403);
        $customer = Customer::query()->visibleTo($user)->whereKey($customerId)->where('status', 'active')->firstOrFail();
        $entries = PartyWalletLedger::query()->visibleTo($user)->where('customer_id', $customer->id)->with(['customer', 'store'])->latestFirst()->paginate(20)->withQueryString();
        $store = Store::query()->visibleTo($user)->where('status', 'active')->with('company')->orderBy('id')->firstOrFail();
        $policyError = null;
        try {
            WalletPolicy::for('party');
        } catch (InvalidArgumentException $exception) {
            $policyError = $exception->getMessage();
        }
        $adjustmentIds = PartyWalletAdjustment::query()->visibleTo($user)->where('customer_id', $customer->id)->pluck('id')->map(static fn (mixed $id): string => (string) $id)->all();
        $pendingAdjustments = $user->can('party_wallet.approve') && $adjustmentIds !== []
            ? ApprovalRecord::query()->visibleTo($user)->where('source_type', 'party_wallet_adjustments')->whereIn('source_id', $adjustmentIds)->where('approval_state', 'pending')->where('decision_permission', 'party_wallet.approve')->latest('id')->limit(20)->get()
            : collect();

        return view('pages.wallets.ledger', [
            'title' => __('Party Wallet'), 'description' => __('Party-only customer balance derived from a separate immutable, source-linked ledger.'),
            'wallet' => 'party', 'customer' => $customer, 'ledgerTable' => 'party_wallet_ledger', 'entries' => $entries,
            'balance' => app(PartyWalletBalance::class)->forCustomer($customer, $user), 'currencyCode' => strtoupper((string) $store->company?->currency_code), 'policyError' => $policyError,
            'pendingAdjustments' => $pendingAdjustments, 'canSettle' => $user->can('party_wallet.settle'), 'canAdjust' => $user->can('party_wallet.adjust'), 'canApprove' => $user->can('party_wallet.approve'),
            'otherRoute' => 'wallets.product', 'otherCustomerRoute' => 'customers.product-wallet', 'otherPermission' => 'product_wallet.view', 'otherLabel' => __('Open Product Wallet'),
            'exportRoute' => $user->can('party_wallet.export') ? route('customers.party-wallet.export', $customer) : null,
            'settlementRoute' => route('customers.party-wallet.settle', $customer), 'adjustmentRoute' => route('customers.party-wallet.adjustments.store', $customer),
            'approveRoute' => static fn (int $approvalId): string => route('wallets.party.adjustments.approve', $approvalId), 'rejectRoute' => static fn (int $approvalId): string => route('wallets.party.adjustments.reject', $approvalId),
            'guidePrefix' => 'party-wallet',
        ]);
    })->middleware('can:party_wallet.view')->name('customers.party-wallet');

    Route::get('customers/{customerId}/product-wallet/export', function (Request $request, int $customerId) use ($sellingStore) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('product_wallet.export'), 403);
        $customer = Customer::query()->visibleTo($user)->whereKey($customerId)->where('status', 'active')->firstOrFail();
        $rows = ProductWalletLedger::query()->visibleTo($user)->where('customer_id', $customer->id)->latestFirst()->limit(500)->get();
        $store = $sellingStore($user);
        app(RecordAuditEvent::class)->execute(category: 'reporting', event: 'product_wallet_customer_exported', branchId: (int) $store->branch_id, storeId: (int) $store->id, metadata: ['wallet' => 'product', 'customer_id' => $customer->id, 'row_count' => $rows->count(), 'scope_limited' => true]);

        return response()->streamDownload(static function () use ($rows): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, ['entry_type', 'amount', 'balance_before', 'balance_after', 'currency_code', 'source_type', 'source_id', 'created_at']);
            foreach ($rows as $entry) {
                fputcsv($handle, [$entry->entry_type, $entry->amount, $entry->balance_before, $entry->balance_after, $entry->currency_code, $entry->source_type, $entry->source_id, $entry->created_at?->format('c')]);
            }
            fclose($handle);
        }, 'customer-product-wallet.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    })->middleware('can:product_wallet.export')->name('customers.product-wallet.export');

    Route::get('customers/{customerId}/party-wallet/export', function (Request $request, int $customerId) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('party_wallet.export'), 403);
        $customer = Customer::query()->visibleTo($user)->whereKey($customerId)->where('status', 'active')->firstOrFail();
        $rows = PartyWalletLedger::query()->visibleTo($user)->where('customer_id', $customer->id)->latestFirst()->limit(500)->get();
        $store = Store::query()->visibleTo($user)->where('status', 'active')->firstOrFail();
        app(RecordAuditEvent::class)->execute(category: 'reporting', event: 'party_wallet_customer_exported', branchId: (int) $store->branch_id, storeId: (int) $store->id, metadata: ['wallet' => 'party', 'customer_id' => $customer->id, 'row_count' => $rows->count(), 'scope_limited' => true]);

        return response()->streamDownload(static function () use ($rows): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, ['entry_type', 'amount', 'balance_before', 'balance_after', 'currency_code', 'source_type', 'source_id', 'created_at']);
            foreach ($rows as $entry) {
                fputcsv($handle, [$entry->entry_type, $entry->amount, $entry->balance_before, $entry->balance_after, $entry->currency_code, $entry->source_type, $entry->source_id, $entry->created_at?->format('c')]);
            }
            fclose($handle);
        }, 'customer-party-wallet.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    })->middleware('can:party_wallet.export')->name('customers.party-wallet.export');

    Route::post('customers/{customerId}/product-wallet/settle', function (Request $request, int $customerId, PostProductWalletEntryAction $action) use ($sellingStore) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('product_wallet.settle'), 403);
        $validated = $request->validate(['direction' => ['required', Rule::in(['credit', 'debit'])], 'amount' => ['required', 'string', 'max:30'], 'source_type' => ['required', 'string', 'max:120'], 'source_id' => ['required', 'string', 'max:120'], 'source_line_id' => ['nullable', 'string', 'max:120'], 'reference' => ['nullable', 'string', 'max:190'], 'reason' => ['nullable', 'string', 'max:1000'], 'idempotency_key' => ['required', 'uuid']]);
        $store = $sellingStore($user);
        $customer = Customer::query()->visibleTo($user)->whereKey($customerId)->where('status', 'active')->firstOrFail();
        try {
            app($action::class)->settle($user, $customer, $store, $validated['amount'], $validated['direction'], $validated['source_type'], $validated['source_id'], $validated['idempotency_key'], $validated['source_line_id'] ?? null, $validated['reference'] ?? null, $validated['reason'] ?? null);
        } catch (InvalidArgumentException|ValidationException $exception) {
            return back()->withInput()->withErrors(['wallet' => $exception->getMessage()]);
        }

        return back()->with('success', __('Product Wallet settlement posted.'));
    })->middleware('can:product_wallet.settle')->name('customers.product-wallet.settle');

    Route::post('customers/{customerId}/party-wallet/settle', function (Request $request, int $customerId, PostPartyWalletEntryAction $action) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('party_wallet.settle'), 403);
        $validated = $request->validate(['direction' => ['required', Rule::in(['credit', 'debit'])], 'amount' => ['required', 'string', 'max:30'], 'source_type' => ['required', 'string', 'max:120'], 'source_id' => ['required', 'string', 'max:120'], 'source_line_id' => ['nullable', 'string', 'max:120'], 'reference' => ['nullable', 'string', 'max:190'], 'reason' => ['nullable', 'string', 'max:1000'], 'idempotency_key' => ['required', 'uuid']]);
        $store = Store::query()->visibleTo($user)->where('status', 'active')->orderBy('id')->firstOrFail();
        $customer = Customer::query()->visibleTo($user)->whereKey($customerId)->where('status', 'active')->firstOrFail();
        try {
            $action->settle($user, $customer, $store, $validated['amount'], $validated['direction'], $validated['source_type'], $validated['source_id'], $validated['idempotency_key'], $validated['source_line_id'] ?? null, $validated['reference'] ?? null, $validated['reason'] ?? null);
        } catch (InvalidArgumentException|ValidationException $exception) {
            return back()->withInput()->withErrors(['wallet' => $exception->getMessage()]);
        }

        return back()->with('success', __('Party Wallet settlement posted.'));
    })->middleware('can:party_wallet.settle')->name('customers.party-wallet.settle');

    Route::post('customers/{customerId}/product-wallet/adjustments', function (Request $request, int $customerId, RequestProductWalletAdjustmentAction $action) use ($sellingStore) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('product_wallet.adjust'), 403);
        $validated = $request->validate(['operation' => ['required', Rule::in(['adjustment', 'correction'])], 'amount' => ['required', 'string', 'max:30'], 'target_ledger_id' => ['nullable', 'integer', 'min:1'], 'source_type' => ['required', 'string', 'max:120'], 'source_id' => ['required', 'string', 'max:120'], 'source_line_id' => ['nullable', 'string', 'max:120'], 'source_reference' => ['nullable', 'string', 'max:190'], 'reason' => ['required', 'string', 'min:3', 'max:1000'], 'idempotency_key' => ['required', 'uuid']]);
        $customer = Customer::query()->visibleTo($user)->whereKey($customerId)->where('status', 'active')->firstOrFail();
        try {
            $action->execute($user, $customer, $sellingStore($user), $validated['operation'], $validated['amount'], $validated['source_type'], $validated['source_id'], $validated['reason'], $validated['idempotency_key'], $validated['target_ledger_id'] ?? null, $validated['source_line_id'] ?? null, $validated['source_reference'] ?? null);
        } catch (InvalidArgumentException|ValidationException $exception) {
            return back()->withInput()->withErrors(['wallet' => $exception->getMessage()]);
        }

        return back()->with('success', __('Product Wallet adjustment submitted for approval.'));
    })->middleware('can:product_wallet.adjust')->name('customers.product-wallet.adjustments.store');

    Route::post('customers/{customerId}/party-wallet/adjustments', function (Request $request, int $customerId, RequestPartyWalletAdjustmentAction $action) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('party_wallet.adjust'), 403);
        $validated = $request->validate(['operation' => ['required', Rule::in(['adjustment', 'correction'])], 'amount' => ['required', 'string', 'max:30'], 'target_ledger_id' => ['nullable', 'integer', 'min:1'], 'source_type' => ['required', 'string', 'max:120'], 'source_id' => ['required', 'string', 'max:120'], 'source_line_id' => ['nullable', 'string', 'max:120'], 'source_reference' => ['nullable', 'string', 'max:190'], 'reason' => ['required', 'string', 'min:3', 'max:1000'], 'idempotency_key' => ['required', 'uuid']]);
        $customer = Customer::query()->visibleTo($user)->whereKey($customerId)->where('status', 'active')->firstOrFail();
        $store = Store::query()->visibleTo($user)->where('status', 'active')->orderBy('id')->firstOrFail();
        try {
            $action->execute($user, $customer, $store, $validated['operation'], $validated['amount'], $validated['source_type'], $validated['source_id'], $validated['reason'], $validated['idempotency_key'], $validated['target_ledger_id'] ?? null, $validated['source_line_id'] ?? null, $validated['source_reference'] ?? null);
        } catch (InvalidArgumentException|ValidationException $exception) {
            return back()->withInput()->withErrors(['wallet' => $exception->getMessage()]);
        }

        return back()->with('success', __('Party Wallet adjustment submitted for approval.'));
    })->middleware('can:party_wallet.adjust')->name('customers.party-wallet.adjustments.store');

    Route::post('pos/customer/select', function (Request $request) use ($sellingStore) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('pos_sales.create') && $user->can('customers.view'), 403);
        $validated = $request->validate(['customer_id' => ['required', 'integer']]);
        $store = $sellingStore($user);
        $customer = Customer::query()->visibleFrom($user, (int) $store->branch_id, (int) $store->id)->whereKey($validated['customer_id'])->where('status', 'active')->firstOrFail();
        $request->session()->put('pos.customer_id', $customer->id);

        return back()->with('success', __('Customer selected for this sale.'));
    })->middleware(['can:pos_sales.create', 'can:customers.view'])->name('pos.customer.select');

    Route::post('pos/customer/clear', function (Request $request) {
        abort_unless($request->user()?->can('pos_sales.create'), 403);
        $request->session()->forget('pos.customer_id');

        return back();
    })->middleware('can:pos_sales.create')->name('pos.customer.clear');

    Route::post('pos/customer/create', function (Request $request, CreateCustomerAction $action) use ($sellingStore) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('pos_sales.create') && $user->can('customers.create'), 403);
        $validated = $request->validate([
            'idempotency_key' => ['required', 'uuid'], 'phone' => ['required', 'string', 'max:64'],
            'name_ar' => ['required', 'string', 'max:190'], 'name_en' => ['required', 'string', 'max:190'],
            'consent_purpose' => ['required', 'string', 'max:80'],
        ]);
        try {
            $customer = $action->execute($user, $sellingStore($user), $validated + ['consents' => [[
                'purpose' => $validated['consent_purpose'], 'status' => 'granted', 'source' => 'pos',
            ]]]);
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['customer' => $exception->getMessage()]);
        }
        $request->session()->put('pos.customer_id', $customer->id);

        return back()->with('success', __('Customer registered and selected for this sale.'));
    })->middleware(['can:pos_sales.create', 'can:customers.create'])->name('pos.customer.create');
});
