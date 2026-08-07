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
