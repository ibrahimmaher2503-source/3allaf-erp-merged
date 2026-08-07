<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Catalog\Models\Product;
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
