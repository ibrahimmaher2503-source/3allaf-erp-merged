<?php

declare(strict_types=1);

namespace App\Livewire\Pos;

use App\Models\User;
use App\Modules\Retail\Actions\PosCartAction;
use App\Modules\Retail\Services\PosCalculationService;
use App\Modules\Retail\Support\InvoiceDiscount;
use App\Modules\Retail\Support\PosCartSnapshot;
use App\Modules\Retail\Support\PosContextResolver;
use App\Modules\Retail\Support\PosOpenOrderManager;
use App\Support\ProductQuantity;
use App\Support\UserSafeError;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Component;

final class Cart extends Component
{
    public string $invoiceDiscountType = 'fixed';

    public string $invoiceDiscountValue = '';

    public function saveInvoiceDiscount(PosContextResolver $contexts, PosOpenOrderManager $orders, PosCartSnapshot $snapshot, PosCalculationService $calculator): void
    {
        Gate::authorize('pos_sales.apply_discount');
        try {
            /** @var User $user */
            $user = auth()->user();
            $context = $contexts->resolve($user);
            $lines = $snapshot->build($context)['lines'];
            $base = $calculator->calculate(array_map(static fn (array $line): array => ['quantity' => $line['quantity'], 'unit_price' => $line['unit_price'], 'discount_amount' => $line['discount_amount']], $lines));
            InvoiceDiscount::amount($this->invoiceDiscountType, $this->invoiceDiscountValue, $base['taxable_base']);
            $orders->setInvoiceDiscount(request(), $user, $context, $this->invoiceDiscountType, $this->invoiceDiscountValue);
            $this->dispatch('pos-cart-updated');
        } catch (\Throwable $exception) {
            $this->addError('invoiceDiscountValue', UserSafeError::message($exception));
        }
    }

    public function removeInvoiceDiscount(PosContextResolver $contexts, PosOpenOrderManager $orders): void
    {
        Gate::authorize('pos_sales.apply_discount');
        /** @var User $user */
        $user = auth()->user();
        $orders->setInvoiceDiscount(request(), $user, $contexts->resolve($user), null, '0.00');
        $this->invoiceDiscountValue = '';
        $this->dispatch('pos-cart-updated');
    }

    public function mount(): void
    {
        Gate::authorize('pos_sales.view');
    }

    #[On('pos-cart-updated')]
    public function refreshCart(): void {}

    public function quantity(int $productId, string $quantity, PosCartAction $cart): string
    {
        Gate::authorize('pos_sales.create');
        $this->resetErrorBag('cart');
        try {
            /** @var User $user */
            $user = auth()->user();
            $cart->quantity(request(), $user, $productId, $quantity);
            $this->dispatch('pos-cart-updated');
        } catch (\Throwable $exception) {
            $this->addError('cart', UserSafeError::message($exception));
        }

        $line = collect(request()->session()->get('pos.cart', []))->first(fn (array $item): bool => (int) ($item['product_id'] ?? 0) === $productId);

        return ProductQuantity::format((string) ($line['quantity'] ?? '1'));
    }

    public function remove(int $productId, PosCartAction $cart): void
    {
        Gate::authorize('pos_sales.create');
        try {
            /** @var User $user */
            $user = auth()->user();
            $cart->remove(request(), $user, $productId);
            $this->dispatch('pos-cart-updated');
        } catch (\Throwable $exception) {
            $this->addError('cart', UserSafeError::message($exception));
        }
    }

    public function clear(PosCartAction $cart): void
    {
        Gate::authorize('pos_sales.create');
        try {
            /** @var User $user */
            $user = auth()->user();
            $cart->clear(request(), $user);
            $this->dispatch('pos-cart-updated');
        } catch (\Throwable $exception) {
            $this->addError('cart', UserSafeError::message($exception));
        }
    }

    public function render(PosCartSnapshot $snapshot, PosContextResolver $contextResolver, PosOpenOrderManager $orders): View
    {
        /** @var User $user */
        $user = auth()->user();
        $context = $contextResolver->resolve($user);
        $store = $context->store;
        $data = $snapshot->build($context);
        $activeOrder = $orders->active(request(), $user, $context, false);
        $openOrders = $orders->orders($user, $context);

        return view('livewire.pos.cart', [...$data, 'store' => $store, 'context' => $context, 'activeOrder' => $activeOrder, 'openOrders' => $openOrders]);
    }
}
