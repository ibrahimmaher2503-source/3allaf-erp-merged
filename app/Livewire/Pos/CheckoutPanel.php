<?php

declare(strict_types=1);

namespace App\Livewire\Pos;

use App\Models\User;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Platform\Models\Store;
use App\Modules\Retail\Models\PosShift;
use App\Modules\Retail\Services\PosCalculationService;
use App\Modules\Retail\Support\PosCartSnapshot;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;

final class CheckoutPanel extends Component
{
    public function mount(): void
    {
        Gate::authorize('pos_sales.view');
    }

    #[On('pos-cart-updated')]
    public function refreshCheckout(): void {}

    public function render(PosCartSnapshot $snapshot): View
    {
        /** @var User $user */ $user = auth()->user();
        $store = Store::query()->visibleTo($user)->where('type', 'selling')->where('status', 'active')->with(['branch', 'company'])->first();
        $shift = $store ? PosShift::query()->open()->where('store_id', $store->id)->where('cashier_id', $user->id)->first() : null;
        $data = $store ? $snapshot->build($store) : ['cart' => collect(), 'preview' => null, 'error' => __('No active selling store is assigned.')];
        $methods = PaymentMethod::query()->where('status', 'active')->orderBy('code')->get();
        $cashMethod = $methods->first(fn (PaymentMethod $method): bool => $method->isCash());
        $electronicMethods = $methods->reject(fn (PaymentMethod $method): bool => $method->isCash() || (string) $method->type === 'gift_card')->values();
        $giftCardMethods = $methods->filter(fn (PaymentMethod $method): bool => (string) $method->type === 'gift_card')->values();
        $token = request()->session()->get('pos.checkout_token');
        if (! is_string($token) || $token === '') {
            $token = (string) Str::uuid();
            request()->session()->put('pos.checkout_token', $token);
        }
        $rounding = $cashMethod && ($data['preview'] ?? null) ? app(PosCalculationService::class)->cashRoundingAdjustment($data['preview']['total']) : null;
        $total = ($data['preview'] ?? null) ? ($rounding === null ? $data['preview']['total'] : bcadd($data['preview']['total'], $rounding, 2)) : '0.00';

        return view('livewire.pos.checkout-panel', array_merge($data, compact('store', 'shift', 'cashMethod', 'electronicMethods', 'giftCardMethods', 'token', 'total')));
    }
}
