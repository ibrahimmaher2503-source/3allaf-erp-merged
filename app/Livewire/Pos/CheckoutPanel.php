<?php

declare(strict_types=1);

namespace App\Livewire\Pos;

use App\Models\User;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Retail\Exceptions\PosFinancialConfigurationException;
use App\Modules\Retail\Services\PosCalculationService;
use App\Modules\Retail\Support\PosCartSnapshot;
use App\Modules\Retail\Support\PosContextResolver;
use App\Support\UserSafeError;
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

    public function render(PosCartSnapshot $snapshot, PosContextResolver $contextResolver): View
    {
        /** @var User $user */ $user = auth()->user();
        $context = $contextResolver->resolve($user);
        $store = $context->store;
        $shift = $context->shift;
        $data = $snapshot->build($context);
        $methods = PaymentMethod::query()->where('status', 'active')->orderBy('code')->get();
        $cashMethod = $methods->first(fn (PaymentMethod $method): bool => $method->isCash());
        $electronicMethods = $methods->reject(fn (PaymentMethod $method): bool => $method->isCash() || (string) $method->type === 'gift_card')->values();
        $giftCardMethods = $methods->filter(fn (PaymentMethod $method): bool => (string) $method->type === 'gift_card')->values();
        $token = request()->session()->get('pos.checkout_token');
        if (! is_string($token) || $token === '') {
            $token = (string) Str::uuid();
            request()->session()->put('pos.checkout_token', $token);
        }
        $invoiceTotal = (string) (($data['preview']['total'] ?? null) ?: '0.00');
        $cashPayable = null;
        $cashRoundingError = null;
        if ($cashMethod && ($data['preview'] ?? null)) {
            try {
                $rounding = app(PosCalculationService::class)->cashRoundingAdjustment($invoiceTotal);
                $cashPayable = bcadd($invoiceTotal, $rounding, 2);
            } catch (PosFinancialConfigurationException $exception) {
                $cashRoundingError = UserSafeError::message($exception);
            }
        }

        return view('livewire.pos.checkout-panel', array_merge($data, compact(
            'context', 'store', 'shift', 'cashMethod', 'electronicMethods', 'giftCardMethods', 'token',
            'invoiceTotal', 'cashPayable', 'cashRoundingError',
        )));
    }
}
