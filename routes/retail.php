<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Customer\Actions\SaveCustomerPolicySettingAction;
use App\Modules\Customer\Models\CustomerPolicySettingVersion;
use App\Modules\Customer\Models\PartyWalletLedger;
use App\Modules\Customer\Models\ProductWalletLedger;
use App\Modules\Customer\Support\CustomerPolicySettingRegistry;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Models\TaxSetting;
use App\Modules\Pricing\Services\EffectivePriceResolver;
use App\Modules\Retail\Actions\RetailSaleAction;
use App\Modules\Retail\Models\PosShift;
use App\Modules\Retail\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('pos/returns-readiness', function (Request $request) {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user->can('returns_exchanges_gift_instruments.view'), 403);

        return view('pages.returns.readiness', [
            'title' => __('Returns and Exchanges Readiness'),
            'description' => __('Review source, condition, approval, settlement, and print prerequisites without creating a return, refund, exchange, or stock movement.'),
            'items' => [
                ['title' => __('Source reference'), 'body' => __('Original invoice or price-free Gift Receipt requirement and no-reference exception policy remain PENDING.')],
                ['title' => __('Eligibility window'), 'body' => __('Return window, quantity, duplicate/excess checks, and out-of-window exception rules remain PENDING.')],
                ['title' => __('Condition and disposition'), 'body' => __('Sellable, non-saleable, damaged, and manager-review outcomes remain PENDING; no stock movement is posted.')],
                ['title' => __('Approval and settlement'), 'body' => __('Approval/SoD, cash/original-method refund, Gift Card settlement, and exchange difference rules remain PENDING.')],
                ['title' => __('Audit and print'), 'body' => __('Immutable source history, numbering, privacy, evidence, and return/exchange output format remain PENDING.')],
            ],
        ]);
    })->name('returns.readiness');

    Route::get('party/readiness', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('party_bookings_invoices.view'), 403);

        return view('pages.party.readiness', [
            'title' => __('Party Booking and Working Invoice Readiness'),
            'description' => __('Review party-only stores, services, schedule, privacy, cancellation, pricing, and final-close prerequisites without creating a booking, customer, child, invoice, payment, or final receipt.'),
            'items' => [
                ['title' => __('Party store scope'), 'body' => __('Party stores and operational context remain separate from retail products and supplier returns.')],
                ['title' => __('Services and packages'), 'body' => __('Service/package catalog, rental assets, consumables, and planned lines remain owner-configurable.')],
                ['title' => __('Schedule and location'), 'body' => __('Date/time/location, timezone, conflict, reschedule, and contact rules remain PENDING.')],
                ['title' => __('Customer, child, and privacy'), 'body' => __('Required contact, child, consent, privacy, and notes fields remain PENDING; no record is created.')],
                ['title' => __('Cancellation and responsibility'), 'body' => __('Cancellation, reschedule, assigned responsibility, and operational ownership rules remain PENDING.')],
                ['title' => __('Working invoice and final close'), 'body' => __('Editable-before-close, immutable-after-close, pricing, deposit, payment-on-account, and checklist rules remain PENDING.')],
            ],
        ]);
    })->middleware('can:party_bookings_invoices.view')->name('party.readiness');

    Route::get('quotations-readiness', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('dashboard_reports.view'), 403);

        return view('pages.quotations.readiness', [
            'title' => __('Quotations and Proposals Readiness'),
            'description' => __('Review typed retail/party quotation fields, customer, validity, status, prices, terms, approval, numbering, print/share, and future conversion boundaries without creating a quotation or operational/financial effect.'),
            'items' => [
                ['title' => __('Typed activity and customer'), 'body' => __('Retail and party activity types remain separate; customer/source linkage and mixed-line blocking remain PENDING.')],
                ['title' => __('Validity, expiry, and status'), 'body' => __('Draft, issued, expired, cancelled, superseded, validity, and closure rules remain PENDING; no status changes are enabled.')],
                ['title' => __('Prices and terms'), 'body' => __('Price source/snapshot, terms, notes, conditions, and owner-configurable wording remain PENDING; no price is approved or rendered.')],
                ['title' => __('Approval, audit, and numbering'), 'body' => __('Approval separation, reasons, idempotency, audit, unique identity, and document sequence rules remain PENDING; no number is allocated.')],
                ['title' => __('Print and share boundary'), 'body' => __('Privacy, print, and share output rules remain PENDING; no output or attachment is generated.')],
                ['title' => __('Future conversion exclusion'), 'body' => __('A quotation may retain a future source reference only; Phase 1 conversion to sale, party invoice, inventory, wallet, payment, or financial effect is blocked.')],
            ],
        ]);
    })->middleware('can:dashboard_reports.view')->name('quotations.readiness');

    Route::get('party/final-close-readiness', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('party_bookings_invoices.view'), 403);

        return view('pages.party.final-close-readiness', [
            'title' => __('Party Final Close and Settlement Readiness'),
            'description' => __('Review booking, operating, return, payment, Party Wallet, invoice freeze, credit, receipt, approval, numbering, and close prerequisites without creating a final invoice, receipt, wallet entry, or settlement.'),
            'items' => [
                ['title' => __('Final readiness checklist'), 'body' => __('Booking, operating order, consumables, rental return/inspection, payment, and outstanding-operation checks remain PENDING; no close is enabled.')],
                ['title' => __('Working invoice and freeze'), 'body' => __('Editable-before-close, immutable-after-close, controlled corrections, and no mixed retail lines remain PENDING; no invoice is frozen.')],
                ['title' => __('Payment reconciliation and residual'), 'body' => __('Multiple payments on account, evidence, duplicates, residual, underpayment, overpayment, and reconciliation rules remain PENDING; no amount is calculated.')],
                ['title' => __('Party Wallet and credit separation'), 'body' => __('Party Wallet-only settlement, Product Wallet exclusion, credit enablement, and explicit source linkage remain PENDING; no wallet entry is created.')],
                ['title' => __('Final invoice and receipt'), 'body' => __('Immutable final invoice, exact receipt wording, privacy, numbering, reprint, and correction references remain PENDING; no document is generated.')],
                ['title' => __('Approval, idempotency, audit, and print'), 'body' => __('Close approval/SoD, double-close prevention, retry/concurrency, audit, document sequence, and print rules remain PENDING.')],
            ],
        ]);
    })->middleware('can:party_bookings_invoices.view')->name('party.final-close.readiness');

    Route::get('party/asset-events-readiness', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('party_bookings_invoices.view'), 403);

        return view('pages.party.asset-events-readiness', [
            'title' => __('Asset Damage, Loss, Maintenance and Depreciation Readiness'),
            'description' => __('Review source-linked damage, loss, maintenance, assessment, responsibility, evidence, cost privacy, approval, depreciation, and correction prerequisites without creating an event or changing an asset state.'),
            'items' => [
                ['title' => __('Damage and loss event'), 'body' => __('Asset, party/source, reason, assessment, responsible user, final status, and evidence rules remain PENDING; no event is created.')],
                ['title' => __('Maintenance lifecycle'), 'body' => __('Maintenance reason, owner, inspection, release, final state, and evidence rules remain PENDING; no maintenance event is recorded.')],
                ['title' => __('Assessment and responsibility'), 'body' => __('Owner-configurable checklist, assessment method, party/source, actor, reviewer, and scope rules remain PENDING.')],
                ['title' => __('Evidence and privacy'), 'body' => __('Attachment purpose, source reference, access, retention, privacy, and cost visibility rules remain PENDING; no file is uploaded.')],
                ['title' => __('Approval and cost boundary'), 'body' => __('Optional cost impact, approval limits, SoD, and finance separation remain PENDING; no amount is calculated or posted.')],
                ['title' => __('Depreciation and correction'), 'body' => __('Operational-only depreciation method/amount, immutable history, and referenced correction rules remain PENDING; no ledger posting occurs.')],
            ],
        ]);
    })->middleware('can:party_bookings_invoices.view')->name('party.asset-events.readiness');

    Route::get('party/assets-readiness', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('party_bookings_invoices.view'), 403);

        return view('pages.party.assets-readiness', [
            'title' => __('Rental Assets and Calendar Readiness'),
            'description' => __('Review unique asset identity, consumable separation, availability states, reservations, checkout, return, condition, approval, audit, and print prerequisites without creating an asset or calendar allocation.'),
            'items' => [
                ['title' => __('Rental asset identity'), 'body' => __('Unique code, name, category, location, status, condition, and immutable history remain PENDING; no asset is created.')],
                ['title' => __('Asset and consumable separation'), 'body' => __('Unique rental assets remain separate from consumables and retail products; no mixed resource is created.')],
                ['title' => __('Availability and lifecycle states'), 'body' => __('Available, reserved, checked out, inspection, damaged, maintenance, retired, and lost states remain PENDING.')],
                ['title' => __('Reservation interval and concurrency'), 'body' => __('Party source, timezone, buffer, overlap lock, retry, cancellation, reschedule, and conflict rules remain PENDING; no reservation is created.')],
                ['title' => __('Checkout, return, and condition'), 'body' => __('Pre/post condition, location, inspector, responsible user, missing/damaged status, and evidence rules remain PENDING.')],
                ['title' => __('Approval, audit, cost privacy, and print'), 'body' => __('State-transition authorization, immutable history, cost redaction, calendar, reservation, checkout, return, and print rules remain PENDING.')],
            ],
        ]);
    })->middleware('can:party_bookings_invoices.view')->name('party.assets.readiness');

    Route::get('party/operating-readiness', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('party_bookings_invoices.view'), 403);

        return view('pages.party.operating-readiness', [
            'title' => __('Party Operating Orders and Consumables Readiness'),
            'description' => __('Review Party-only operating-order, party-store, consumable, issue, return, reconciliation, approval, audit, and print prerequisites without creating stock or operating movements.'),
            'items' => [
                ['title' => __('Operating-order lifecycle'), 'body' => __('Draft, release, execute, complete, immutable history, and source-link rules remain PENDING; no order is created.')],
                ['title' => __('Party store and resource scope'), 'body' => __('Party-only store, services, rental resources, responsibilities, and source scope remain PENDING; no stock is reserved.')],
                ['title' => __('Consumables and UOM'), 'body' => __('Catalog, unit, fraction, availability, and party-store mapping rules remain PENDING; no quantity is rendered.')],
                ['title' => __('Issue and actual consumption'), 'body' => __('Issue, actual, controlled additions/removals, operator evidence, and completion rules remain PENDING; no issue is posted.')],
                ['title' => __('Unused return movement'), 'body' => __('Eligible unused return, referenced movement, condition, approval, and excess handling remain PENDING; no return is posted.')],
                ['title' => __('Reconciliation, approval, audit, and print'), 'body' => __('Source/balance reconciliation, concurrency, SoD, idempotency, immutable history, privacy, and print rules remain PENDING.')],
            ],
        ]);
    })->middleware('can:party_bookings_invoices.view')->name('party.operating.readiness');

    Route::get('party/payments-readiness', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('party_bookings_invoices.view'), 403);

        return view('pages.party.payments-readiness', [
            'title' => __('Party Payments and Balance Readiness'),
            'description' => __('Review Party-only payment methods, deposits, evidence, idempotency, receipt wording, balance visibility, and Party Wallet boundaries without posting money or creating a receipt.'),
            'items' => [
                ['title' => __('Party payment methods'), 'body' => __('Allowed source, method, actor, and scope rules remain PENDING; no payment is posted.')],
                ['title' => __('Deposit and payment on account'), 'body' => __('Multiple/partial payment, deposit, source invoice, and exact receipt-label rules remain PENDING.')],
                ['title' => __('Evidence and privacy'), 'body' => __('Evidence, attachment, source reference, privacy, and retention rules remain PENDING; no file is uploaded.')],
                ['title' => __('Idempotency and reversal'), 'body' => __('Duplicate, retry, concurrent, cancellation, reversal, and audit rules remain PENDING.')],
                ['title' => __('Overpayment and balance'), 'body' => __('Underpayment, overpayment, residual, credit, and source-linked balance rules remain PENDING; no amount is calculated.')],
                ['title' => __('Receipt and Party Wallet settlement'), 'body' => __('Numbering/reprint, approval/SoD, Party Wallet-only settlement, and Product Wallet exclusion remain PENDING.')],
            ],
        ]);
    })->middleware('can:party_bookings_invoices.view')->name('party.payments.readiness');

    Route::get('gift-receipts', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('returns_exchanges_gift_instruments.view'), 403);

        return view('pages.gift-instruments.readiness', [
            'kind' => 'gift-receipts',
            'title' => __('Gift Receipts'),
            'description' => __('Price-free source-reference readiness; issue, validate, reprint, and use remain disabled.'),
            'boundary' => __('Gift Receipt policy values are configurable from Settings, but no source sale lines or receipt references are loaded until eligibility, privacy, numbering, and format are approved.'),
            'items' => [
                ['title' => __('Source eligibility'), 'detail' => __('Eligible approved-sale lines, return context, and source linkage remain PENDING.')],
                ['title' => __('Price-free output'), 'detail' => __('A Gift Receipt must exclude unit price, discount, tax, total, and any price-inference field.')],
                ['title' => __('Reprint and privacy'), 'detail' => __('Reprint reason, authorization, privacy scope, and immutable history remain PENDING.')],
                ['title' => __('Format and numbering'), 'detail' => __('Reference format, numbering, and print template remain PENDING; no artifact is generated.')],
            ],
            'emptyTitle' => __('No Gift Receipt references yet'),
            'emptyBody' => __('The empty state is intentional. Add owner-approved policy values first; do not create a Gift Receipt from this screen.'),
        ]);
    })->middleware('can:returns_exchanges_gift_instruments.view')->name('gift.receipts');

    Route::get('gift-cards', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('returns_exchanges_gift_instruments.view'), 403);

        return view('pages.gift-instruments.readiness', [
            'kind' => 'gift-cards',
            'title' => __('Gift Cards'),
            'description' => __('Gift Card ledger readiness; issue, balance, redeem, void, and expiry remain disabled.'),
            'boundary' => __('Gift Card policy values are configurable from Settings, but no identifier, balance, holder, payment, ledger entry, or redemption is loaded until source and validity policies are approved.'),
            'items' => [
                ['title' => __('Unique identifier'), 'detail' => __('Identifier format, uniqueness, concurrency, and source reference remain PENDING.')],
                ['title' => __('Validity and expiry'), 'detail' => __('Validity period and expired-use blocking remain PENDING; no card is active.')],
                ['title' => __('Holder and privacy'), 'detail' => __('Holder/reference purpose and role-safe visibility remain PENDING; no holder data is loaded.')],
                ['title' => __('Redemption and void'), 'detail' => __('Partial/full use, overuse, void reason, approval, and immutable ledger rules remain PENDING.')],
            ],
            'emptyTitle' => __('No Gift Cards yet'),
            'emptyBody' => __('The empty state is intentional. Add owner-approved policy values first; do not issue or redeem a Gift Card from this screen.'),
        ]);
    })->middleware('can:returns_exchanges_gift_instruments.view')->name('gift.cards');

    Route::get('wallets/product', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('product_wallet.view'), 403);

        return view('pages.wallets.ledger', [
            'title' => app()->getLocale() === 'ar' ? 'محفظة المنتجات' : 'Product Wallet',
            'description' => app()->getLocale() === 'ar' ? 'سجل مستقل للقيود المرتبطة بالمنتج، مع إبقاء المصدر والسياسة والصلاحية خارج نطاق هذه الشريحة.' : 'A separate ledger for product-linked entries while source, policy, and authorization remain outside this slice.',
            'ledgerTable' => 'product_wallet_ledger',
            'entries' => ProductWalletLedger::query()->latestFirst()->paginate(20),
            'otherRoute' => 'wallets.party',
            'otherPermission' => 'party_wallet.view',
            'otherLabel' => app()->getLocale() === 'ar' ? 'فتح Party Wallet' : 'Open Party Wallet',
            'guidePrefix' => 'product-wallet',
        ]);
    })->middleware('can:product_wallet.view')->name('wallets.product');

    Route::get('wallets/party', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('party_wallet.view'), 403);

        return view('pages.wallets.ledger', [
            'title' => app()->getLocale() === 'ar' ? 'محفظة الأطراف' : 'Party Wallet',
            'description' => app()->getLocale() === 'ar' ? 'سجل مستقل للقيود المرتبطة بالطرف، مع إبقاء المصدر والسياسة والصلاحية خارج نطاق هذه الشريحة.' : 'A separate ledger for party-linked entries while source, policy, and authorization remain outside this slice.',
            'ledgerTable' => 'party_wallet_ledger',
            'entries' => PartyWalletLedger::query()->latestFirst()->paginate(20),
            'otherRoute' => 'wallets.product',
            'otherPermission' => 'product_wallet.view',
            'otherLabel' => app()->getLocale() === 'ar' ? 'فتح Product Wallet' : 'Open Product Wallet',
            'guidePrefix' => 'party-wallet',
        ]);
    })->middleware('can:party_wallet.view')->name('wallets.party');

    Route::get('pos', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('pos_sales.view'), 403);
        $store = Store::query()->visibleTo($user)->where('type', 'selling')->where('status', 'active')->with('company')->orderBy('id')->first();
        $shift = $store !== null ? PosShift::query()->open()->where('store_id', $store->id)->where('cashier_id', $user->id)->with('cashDrawer')->latest('opened_at')->first() : null;
        /** @var array<int, array{product_id: int, quantity: numeric-string}> $sessionCart */
        $sessionCart = $request->session()->get('pos.cart', []);
        $cart = collect($sessionCart);
        $productIds = $cart->pluck('product_id')->map(fn ($id): int => (int) $id)->all();
        $cartProducts = Product::query()->whereIn('id', $productIds)->get()->keyBy('id');
        $availableProducts = Product::query()->active()->orderBy('item_code')->limit(24)->get();
        $priceMap = $store === null ? collect() : $availableProducts->mapWithKeys(fn (Product $product): array => [$product->id => app(EffectivePriceResolver::class)->resolve($product->id, $store->id)]);
        $suspendedCount = Sale::query()->visibleTo($user)->where('status', 'suspended')->count();

        return view('pages.pos.index', compact('store', 'shift', 'cart', 'cartProducts', 'availableProducts', 'priceMap', 'suspendedCount'));
    })->middleware('can:pos_sales.view')->name('pos');

    Route::get('pos/shift-readiness', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('pos_sales.view'), 403);

        return view('pages.pos.shift-readiness', [
            'activeDrawerCount' => CashDrawer::query()->visibleTo($user)->where('status', 'active')->count(),
            'openShiftCount' => PosShift::query()->where('cashier_id', $user->id)->where('status', 'open')->count(),
        ]);
    })->middleware('can:pos_sales.view')->name('pos.shift-readiness');

    Route::get('pos/offline-readiness', function (Request $request) {
        abort_unless($request->user()?->can('pos_sales.view'), 403);

        return view('pages.pos.offline-readiness');
    })->middleware('can:pos_sales.view')->name('pos.offline-readiness');

    Route::get('customers/loyalty-readiness', function (Request $request) {
        abort_unless($request->user()?->can('pos_sales.view'), 403);

        $definitions = CustomerPolicySettingRegistry::all();
        $latest = CustomerPolicySettingVersion::query()
            ->whereIn('key', array_keys($definitions))
            ->orderByDesc('version')
            ->get()
            ->groupBy('key')
            ->map(static fn ($versions) => $versions->first());

        $decisionSettings = collect($definitions)->mapWithKeys(static fn (array $definition, string $key): array => [
            $key => [
                ...$definition,
                'record' => $latest->get($key),
            ],
        ]);

        return view('pages.customers.loyalty-readiness', compact('decisionSettings'));
    })->middleware('can:pos_sales.view')->name('customers.loyalty-readiness');

    Route::get('admin/settings/customer-loyalty', function (Request $request) {
        abort_unless($request->user()?->can('company_settings.view'), 403);

        $definitions = CustomerPolicySettingRegistry::all();
        $latest = CustomerPolicySettingVersion::query()
            ->whereIn('key', array_keys($definitions))
            ->orderByDesc('version')
            ->get()
            ->groupBy('key')
            ->map(static fn ($versions) => $versions->first());

        $settings = collect($definitions)->mapWithKeys(static fn (array $definition, string $key): array => [
            $key => [
                ...$definition,
                'record' => $latest->get($key),
            ],
        ]);

        return view('pages.admin.customer-loyalty-settings', compact('settings'));
    })->middleware('can:company_settings.view')->name('admin.settings.customer-loyalty');

    Route::post('admin/settings/customer-loyalty', function (Request $request, SaveCustomerPolicySettingAction $action) {
        abort_unless($request->user()?->can('company_settings.edit'), 403);

        $data = $request->validate([
            'key' => ['required', 'string', 'max:120'],
            'value' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $action->execute($data['key'], $data['value'] ?? null, $data['notes'] ?? null);

        return to_route('admin.settings.customer-loyalty')->with('status', 'Customer policy setting version saved locally; owner approval is still required.');
    })->middleware('can:company_settings.edit')->name('admin.settings.customer-loyalty.save');

    Route::get('pos/financial-readiness', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('pos_sales.view'), 403);

        return view('pages.pos.financial-readiness', [
            'activePaymentMethods' => PaymentMethod::query()->where('status', 'active')->count(),
            'activeTaxSettings' => TaxSetting::query()->where('status', 'active')->count(),
        ]);
    })->middleware('can:pos_sales.view')->name('pos.financial-readiness');

    Route::post('pos/cart/add', function (Request $request) {
        abort_unless($request->user()?->can('pos_sales.create'), 403);
        $data = $request->validate(['product_id' => ['required', 'integer', 'exists:products,id'], 'quantity' => ['required', 'numeric', 'min:0.000001', 'max:999999']]);
        /** @var array<int, array{product_id: int, quantity: numeric-string}> $sessionCart */
        $sessionCart = $request->session()->get('pos.cart', []);
        $cart = collect($sessionCart);
        /** @var int $productId */
        $productId = (int) $data['product_id'];
        /** @var numeric-string $quantity */
        $quantity = (string) $data['quantity'];
        $key = $cart->search(fn (array $line): bool => (int) $line['product_id'] === $productId);
        if ($key === false) {
            $cart->push(['product_id' => $productId, 'quantity' => $quantity]);
        } else {
            /** @var numeric-string $existingQuantity */
            $existingQuantity = $cart[$key]['quantity'];
            $cart[$key]['quantity'] = bcadd($existingQuantity, $quantity, 6);
        }
        $request->session()->put('pos.cart', $cart->values()->all());

        return back()->with('success', __('Product added to cart.'));
    })->middleware('can:pos_sales.create')->name('pos.cart.add');

    Route::post('pos/cart/remove', function (Request $request) {
        abort_unless($request->user()?->can('pos_sales.create'), 403);
        $data = $request->validate(['product_id' => ['required', 'integer']]);
        /** @var array<int, array{product_id: int, quantity: numeric-string}> $sessionCart */
        $sessionCart = $request->session()->get('pos.cart', []);
        $cart = collect($sessionCart)->reject(fn (array $line): bool => (int) $line['product_id'] === (int) $data['product_id']);
        $request->session()->put('pos.cart', $cart->values()->all());

        return back();
    })->middleware('can:pos_sales.create')->name('pos.cart.remove');

    Route::post('pos/cart/clear', function (Request $request) {
        abort_unless($request->user()?->can('pos_sales.create'), 403);
        $request->session()->forget('pos.cart');

        return back();
    })->middleware('can:pos_sales.create')->name('pos.cart.clear');

    Route::post('pos/suspend', function (Request $request, RetailSaleAction $action) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('pos_sales.create'), 403);
        $store = Store::query()->visibleTo($user)->where('type', 'selling')->where('status', 'active')->firstOrFail();
        /** @var array<int, array{product_id: int, quantity: numeric-string}> $cart */
        $cart = $request->session()->get('pos.cart', []);
        $sale = $action->create($user, $store, $cart, 'SUSPEND:'.$user->id.':'.hash('sha256', json_encode($cart).microtime(true)), true);
        $request->session()->forget('pos.cart');

        return redirect()->route('pos')->with('success', __('Sale suspended. Resume code: :code', ['code' => $sale->suspendedSale?->getAttribute('resume_code')]));
    })->middleware('can:pos_sales.create')->name('pos.suspend');

    Route::post('pos/checkout', function (Request $request, RetailSaleAction $action) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('pos_sales.create'), 403);
        $store = Store::query()->visibleTo($user)->where('type', 'selling')->where('status', 'active')->firstOrFail();
        /** @var array<int, array{product_id: int, quantity: numeric-string}> $cart */
        $cart = $request->session()->get('pos.cart', []);
        $sale = $action->create($user, $store, $cart, 'CHECKOUT:'.$user->id.':'.hash('sha256', json_encode($cart).microtime(true)));
        $request->session()->forget('pos.cart');

        return redirect()->route('sales.show', $sale)->with('success', __('Sale completed successfully.'));
    })->middleware('can:pos_sales.create')->name('pos.checkout');

    Route::get('pos/suspended', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('suspended_sales.view'), 403);
        $sales = Sale::query()->visibleTo($user)->with(['lines', 'suspendedSale', 'store'])->where('status', 'suspended')->latest()->paginate(20);

        return view('pages.pos.suspended', compact('sales'));
    })->middleware('can:suspended_sales.view')->name('pos.suspended');

    Route::post('pos/suspended/{sale}/resume', function (Request $request, Sale $sale, RetailSaleAction $action) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('pos_sales.create'), 403);
        abort_unless(Sale::query()->visibleTo($user)->whereKey($sale->id)->exists(), 404);
        $sale = $action->finalizeSuspended($user, $sale);

        return redirect()->route('sales.show', $sale)->with('success', __('Suspended sale resumed and completed.'));
    })->middleware('can:pos_sales.create')->name('pos.suspended.resume');

    Route::get('sales', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('pos_sales.view'), 403);
        $sales = Sale::query()->visibleTo($user)->with(['store', 'cashier'])->approved()->latest('approved_at')->paginate(20);

        return view('pages.sales.index', compact('sales'));
    })->middleware('can:pos_sales.view')->name('sales.index');

    Route::get('sales/{sale}', function (Request $request, Sale $sale) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('pos_sales.view'), 403);
        abort_unless(Sale::query()->visibleTo($user)->whereKey($sale->id)->exists(), 404);
        $sale->load(['store', 'cashier', 'lines.product', 'shift.cashDrawer']);

        return view('pages.sales.show', compact('sale'));
    })->middleware('can:pos_sales.view')->name('sales.show');

    Route::get('sales/{sale}/print', function (Request $request, Sale $sale) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('pos_sales.print'), 403);
        abort_unless(Sale::query()->visibleTo($user)->whereKey($sale->id)->exists(), 404);
        $sale->load(['store', 'cashier', 'lines']);

        return view('pages.sales.print', compact('sale'));
    })->middleware('can:pos_sales.print')->name('sales.print');
});
