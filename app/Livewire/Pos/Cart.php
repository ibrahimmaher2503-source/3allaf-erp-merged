<?php

declare(strict_types=1);

namespace App\Livewire\Pos;

use App\Models\User;
use App\Modules\Retail\Actions\PosCartAction;
use App\Modules\Retail\Support\PosCartSnapshot;
use App\Modules\Retail\Support\PosContextResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Component;

final class Cart extends Component
{
    public function mount(): void
    {
        Gate::authorize('pos_sales.view');
    }

    #[On('pos-cart-updated')]
    public function refreshCart(): void {}

    public function quantity(int $productId, string $quantity, PosCartAction $cart): void
    {
        Gate::authorize('pos_sales.create');
        try { /** @var User $user */ $user = auth()->user();
            $cart->quantity(request(), $user, $productId, $quantity);
            $this->dispatch('pos-cart-updated');
        } catch (\Throwable $exception) {
            $this->addError('cart', $exception->getMessage());
        }
    }

    public function remove(int $productId, PosCartAction $cart): void
    {
        Gate::authorize('pos_sales.create');
        $cart->remove(request(), $productId);
        $this->dispatch('pos-cart-updated');
    }

    public function clear(PosCartAction $cart): void
    {
        Gate::authorize('pos_sales.create');
        $cart->clear(request());
        $this->dispatch('pos-cart-updated');
    }

    public function render(PosCartSnapshot $snapshot, PosContextResolver $contextResolver): View
    {
        /** @var User $user */ $user = auth()->user();
        $context = $contextResolver->resolve($user);
        $store = $context->store;
        $data = $snapshot->build($context);

        return view('livewire.pos.cart', [...$data, 'store' => $store, 'context' => $context]);
    }
}
