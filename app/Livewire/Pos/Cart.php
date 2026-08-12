<?php

declare(strict_types=1);

namespace App\Livewire\Pos;

use App\Models\User;
use App\Modules\Platform\Models\Store;
use App\Modules\Retail\Actions\PosCartAction;
use App\Modules\Retail\Support\PosCartSnapshot;
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

    public function render(PosCartSnapshot $snapshot): View
    {
        /** @var User $user */ $user = auth()->user();
        $store = Store::query()->visibleTo($user)->where('type', 'selling')->where('status', 'active')->with('branch')->first();
        $data = $store ? $snapshot->build($store) : ['cart' => collect(), 'lines' => [], 'preview' => null, 'error' => __('No active selling store is assigned.')];

        return view('livewire.pos.cart', [...$data, 'store' => $store]);
    }
}
