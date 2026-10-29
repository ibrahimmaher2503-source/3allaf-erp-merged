<?php

declare(strict_types=1);

namespace App\Modules\Retail\Actions;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\Store;
use App\Modules\Pricing\Services\EffectivePriceResolver;
use App\Modules\Retail\Data\PosContext;
use App\Modules\Retail\Support\PosCartSnapshot;
use App\Modules\Retail\Support\PosContextResolver;
use App\Modules\Retail\Support\PosOpenOrderManager;
use App\Support\ProductQuantity;
use Illuminate\Http\Request;
use InvalidArgumentException;

final class PosCartAction
{
    public function __construct(
        private readonly EffectivePriceResolver $prices,
        private readonly PosContextResolver $contextResolver,
        private readonly PosOpenOrderManager $openOrders,
        private readonly PosCartSnapshot $snapshot,
    ) {}

    public function add(Request $request, User $user, int $productId, string $quantity): void
    {
        $this->assertQuantity($quantity);
        $context = $this->context($request, $user);
        $store = $this->store($context);
        $product = Product::query()->sellable()->with('parent')->find($productId);
        if (! $product?->isSellable()) {
            throw new InvalidArgumentException(__('Select an active simple product or a fully resolved variation SKU. Product families cannot be added.'));
        }
        if ($this->prices->resolve($product->id, $store->id) === null) {
            throw new InvalidArgumentException(__('The selected SKU has no positive selling price for this store.'));
        }

        $cart = collect($request->session()->get('pos.cart', []));
        $key = $cart->search(fn (array $line): bool => (int) ($line['product_id'] ?? 0) === $product->id);
        $existing = $key === false ? '0' : (string) ($cart[$key]['quantity'] ?? '0');
        $requestedTotal = bcadd($existing, $quantity, 6);
        $balance = StockBalance::query()->where('store_id', $store->id)->where('product_id', $product->id)->first();
        $available = $balance ? bcsub((string) $balance->on_hand, (string) $balance->reserved, 6) : '0';
        if (bccomp($available, $requestedTotal, 6) < 0) {
            throw new InvalidArgumentException(__('Only :quantity units of this product are available.', ['quantity' => ProductQuantity::format($available)]));
        }

        if ($key === false) {
            $cart->push(['product_id' => $product->id, 'quantity' => $quantity]);
        } else {
            $line = $cart[$key];
            $line['quantity'] = $requestedTotal;
            $cart[$key] = $line;
        }
        $request->session()->put('pos.cart', $cart->values()->all());
        $this->persist($request, $user, $context);
    }

    public function quantity(Request $request, User $user, int $productId, string $quantity): void
    {
        $quantity = (string) ProductQuantity::normalize($quantity);
        $this->assertQuantity($quantity);
        $context = $this->context($request, $user);
        $store = $this->store($context);
        $product = Product::query()->sellable()->find($productId);
        if (! $product) {
            throw new InvalidArgumentException(__('The cart SKU is no longer sellable. The cart was preserved.'));
        }
        $balance = StockBalance::query()->where('store_id', $store->id)->where('product_id', $productId)->first();
        $available = $balance ? bcsub((string) $balance->on_hand, (string) $balance->reserved, 6) : '0';
        if (bccomp($available, $quantity, 6) < 0) {
            throw new InvalidArgumentException(__('Only :quantity units of this product are available.', ['quantity' => ProductQuantity::format($available)]));
        }

        $cart = collect($request->session()->get('pos.cart', []));
        $index = $cart->search(fn (array $line): bool => (int) ($line['product_id'] ?? 0) === $productId);
        if ($index === false) {
            throw new InvalidArgumentException(__('The requested cart line was not found.'));
        }
        $line = $cart[$index];
        $before = (string) ($line['quantity'] ?? '0');
        $line['quantity'] = $quantity;
        $cart[$index] = $line;
        $request->session()->put('pos.cart', $cart->values()->all());
        $this->persist($request, $user, $context);
        app(RecordAuditEvent::class)->execute(category: 'retail', event: 'pos_cart_quantity_updated', explicitSourceId: 'cart:'.$productId, before: ['quantity' => $before], after: ['quantity' => $quantity], branchId: $store->branch_id, storeId: $store->id, metadata: ['product_id' => $productId, 'actor_id' => $user->id]);
    }

    public function remove(Request $request, User $user, int $productId): void
    {
        $context = $this->context($request, $user);
        $cart = collect($request->session()->get('pos.cart', []))->reject(fn (array $line): bool => (int) ($line['product_id'] ?? 0) === $productId);
        $request->session()->put('pos.cart', $cart->values()->all());
        $this->persist($request, $user, $context);
    }

    public function clear(Request $request, User $user): void
    {
        $context = $this->context($request, $user);
        $request->session()->forget('pos.cart');
        $this->persist($request, $user, $context);
    }

    private function persist(Request $request, User $user, PosContext $context): void
    {
        $order = $this->openOrders->persistSession($request, $user, $context);
        $data = $this->snapshot->build($context);
        $this->openOrders->updateTotals($order, $data['preview'] ?? null);
    }

    private function context(Request $request, User $user): PosContext
    {
        $context = $this->contextResolver->resolve($user);
        $order = $this->openOrders->active($request, $user, $context);
        if ($order !== null) {
            $this->openOrders->hydrateSession($request, $order);
        }

        return $context;
    }

    private function store(PosContext $context): Store
    {
        return $context->store
            ?? throw new InvalidArgumentException($context->disabledReason ?? __('POS is disabled until you open an assigned cashier shift.'));
    }

    private function assertQuantity(string $quantity): void
    {
        if (! preg_match('/^\d+$/', $quantity) || bccomp($quantity, '0', 0) <= 0 || bccomp($quantity, '999999', 0) > 0) {
            throw new InvalidArgumentException(__('Quantity must be greater than zero and within the allowed limit.'));
        }
    }
}
