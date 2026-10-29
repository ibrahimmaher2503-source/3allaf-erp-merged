<?php

declare(strict_types=1);

namespace App\Livewire\Pos;

use App\Models\User;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Retail\Exceptions\PosFinancialConfigurationException;
use App\Modules\Retail\Services\PosCalculationService;
use App\Modules\Retail\Support\PosCartSnapshot;
use App\Modules\Retail\Support\PosContextResolver;
use App\Modules\Retail\Support\PosOpenOrderManager;
use App\Support\UserSafeError;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Component;

final class CheckoutPanel extends Component
{
    public string $mode = 'cash';

    /** @var array<int, array<string, mixed>> */
    public array $paymentLines = [];

    /** @var array<string, array<int, array<string, mixed>>> */
    public array $savedModeLines = [];

    public string $notes = '';

    public function mount(PosOpenOrderManager $orders, PosContextResolver $contexts): void
    {
        Gate::authorize('pos_sales.view');
        /** @var User $user */
        $user = auth()->user();
        $context = $contexts->resolve($user);
        $order = $orders->active(request(), $user, $context, ! is_numeric(request()->session()->get('pos.completed_sale_id')));
        if ($order === null) {
            return;
        }
        $this->mode = in_array($order->payment_mode, ['cash', 'card', 'split', 'other'], true) ? $order->payment_mode : 'cash';
        $this->notes = (string) $order->notes;
        $this->paymentLines = $order->paymentDrafts()->orderBy('line_position')->get()->map(fn ($line): array => [
            'method_id' => $line->payment_method_id === null ? '' : (string) $line->payment_method_id,
            'amount' => (string) ($line->amount ?? ''),
            'tendered' => (string) ($line->tendered_amount ?? ''),
            'evidence_reference' => (string) ($line->safe_reference ?? ''),
            'gift_card_identifier' => (string) ($line->gift_card_identifier ?? ''),
        ])->values()->all();
        if ($this->paymentLines === []) {
            $this->prepareMode($this->mode);
        }
        $this->savedModeLines[$this->mode] = $this->paymentLines;
    }

    #[On('pos-cart-updated')]
    public function refreshCheckout(): void {}

    public function chooseMode(string $mode, PosOpenOrderManager $orders, PosContextResolver $contexts): void
    {
        Gate::authorize('pos_sales.create');
        if (! in_array($mode, ['cash', 'card', 'split', 'other'], true)) {
            return;
        }
        if ($this->mode !== $mode || $this->paymentLines === []) {
            $this->savedModeLines[$this->mode] = $this->paymentLines;
            $this->mode = $mode;
            if (($this->savedModeLines[$mode] ?? []) !== []) {
                $this->paymentLines = $this->savedModeLines[$mode];
            } else {
                $this->prepareMode($mode);
            }
        }
        $this->saveDraft($orders, $contexts);
    }

    public function switchCheckoutMode(string $mode, PosOpenOrderManager $orders, PosContextResolver $contexts): void
    {
        $this->chooseMode($mode, $orders, $contexts);
        $this->dispatch('open-pos-checkout');
    }

    public function startCheckout(string $mode, PosOpenOrderManager $orders, PosContextResolver $contexts): void
    {
        Gate::authorize('pos_sales.create');
        if (! in_array($mode, ['cash', 'card', 'split', 'other'], true)) {
            return;
        }
        if ($mode === 'cash' && ! PaymentMethod::query()->where('status', 'active')->get()->contains(fn (PaymentMethod $method): bool => $method->isCash())) {
            $mode = 'card';
        }
        $this->chooseMode($mode, $orders, $contexts);
        $this->dispatch('open-pos-checkout');
    }

    public function addPaymentLine(PosOpenOrderManager $orders, PosContextResolver $contexts): void
    {
        Gate::authorize('pos_sales.create');
        if (count($this->paymentLines) >= 12) {
            $this->addError('paymentLines', __('A split payment supports at most twelve portions.'));

            return;
        }
        if ($this->mode !== 'card') {
            $this->mode = 'split';
        }
        foreach ($this->paymentLines as $index => $existing) {
            if (($existing['auto_remaining'] ?? false) === true) {
                $this->paymentLines[$index]['amount'] = '';
                $this->paymentLines[$index]['auto_remaining'] = false;
            }
        }
        $line = $this->emptyLine();
        $line['amount'] = $this->remainingForNewLine($orders, $contexts);
        $line['auto_remaining'] = true;
        $this->paymentLines[] = $line;
        $this->savedModeLines[$this->mode] = $this->paymentLines;
        $this->saveDraft($orders, $contexts);
        $this->dispatch('open-pos-checkout');
    }

    public function removePaymentLine(int $index, PosOpenOrderManager $orders, PosContextResolver $contexts): void
    {
        Gate::authorize('pos_sales.create');
        if (count($this->paymentLines) <= 1) {
            return;
        }
        unset($this->paymentLines[$index]);
        $this->paymentLines = array_values($this->paymentLines);
        if ($this->mode !== 'card') {
            if (count($this->paymentLines) > 1) {
                $this->mode = 'split';
            } else {
                $method = PaymentMethod::query()->whereKey((int) ($this->paymentLines[0]['method_id'] ?? 0))->where('status', 'active')->first();
                $this->mode = $method?->isCash() ? 'cash' : 'card';
            }
        }
        $this->saveDraft($orders, $contexts);
        $this->dispatch('open-pos-checkout');
    }

    public function updatedPaymentLines(mixed $value = null, ?string $key = null): void
    {
        if ($key !== null && str_ends_with($key, '.amount')) {
            $index = (int) explode('.', $key)[0];
            $this->paymentLines[$index]['auto_remaining'] = false;
        }
        foreach ($this->paymentLines as $index => $line) {
            if (($line['auto_remaining'] ?? false) === true) {
                $this->paymentLines[$index]['amount'] = $this->remainingForNewLine(app(PosOpenOrderManager::class), app(PosContextResolver::class), $index);
            }
        }
        $this->savedModeLines[$this->mode] = $this->paymentLines;
        $this->persistDraftUpdate();
    }

    public function updatedNotes(): void
    {
        $this->persistDraftUpdate();
    }

    private function persistDraftUpdate(): void
    {
        try {
            $this->saveDraft(app(PosOpenOrderManager::class), app(PosContextResolver::class));
        } catch (\Throwable $exception) {
            $this->addError('paymentLines', UserSafeError::message($exception));
        }
    }

    public function render(PosCartSnapshot $snapshot, PosContextResolver $contextResolver, PosOpenOrderManager $orders): View
    {
        /** @var User $user */
        $user = auth()->user();
        $context = $contextResolver->resolve($user);
        $store = $context->store;
        $shift = $context->shift;
        $order = $orders->active(request(), $user, $context, ! is_numeric(request()->session()->get('pos.completed_sale_id')));
        $data = $snapshot->build($context);
        $orders->updateTotals($order, $data['preview'] ?? null);
        $methods = PaymentMethod::query()->where('status', 'active')->orderBy('code')->limit(100)->get();
        $cashMethod = $methods->first(fn (PaymentMethod $method): bool => $method->isCash());
        $electronicMethods = $methods->reject(fn (PaymentMethod $method): bool => $method->isCash() || (string) $method->type === 'gift_card')->values();
        $invoiceTotal = (string) (($data['preview']['total'] ?? null) ?: '0.00');
        $hasCashDraft = collect($this->paymentLines)->contains(fn (array $line): bool => $cashMethod !== null && (int) ($line['method_id'] ?? 0) === (int) $cashMethod->id);
        $cashPayable = null;
        $cashAdjustment = '0.00';
        $cashRoundingError = null;
        if (($this->mode === 'cash' || $hasCashDraft) && ($data['preview'] ?? null)) {
            try {
                $cashAdjustment = app(PosCalculationService::class)->cashRoundingAdjustment($invoiceTotal);
                $cashPayable = bcadd($invoiceTotal, $cashAdjustment, 2);
            } catch (PosFinancialConfigurationException $exception) {
                $cashRoundingError = UserSafeError::message($exception);
            }
        }
        $payable = (string) ($cashPayable ?? $invoiceTotal);

        return view('livewire.pos.checkout-panel', array_merge($data, compact(
            'context', 'store', 'shift', 'order', 'methods', 'cashMethod', 'electronicMethods',
            'invoiceTotal', 'cashPayable', 'cashAdjustment', 'cashRoundingError', 'payable',
        )));
    }

    private function prepareMode(string $mode): void
    {
        $methods = PaymentMethod::query()->where('status', 'active')->orderBy('code')->limit(100)->get();
        $cash = $methods->first(fn (PaymentMethod $method): bool => $method->isCash());
        $card = $methods->first(fn (PaymentMethod $method): bool => ! $method->isCash() && (string) $method->type !== 'gift_card');
        if ($mode === 'cash' && $cash !== null) {
            $this->paymentLines = [$this->emptyLine((string) $cash->id)];
        } elseif ($mode === 'card' && $card !== null) {
            $this->paymentLines = [$this->emptyLine((string) $card->id)];
        } elseif ($mode === 'split') {
            if (count($this->paymentLines) < 2) {
                $first = $this->paymentLines[0] ?? ($cash ? $this->emptyLine((string) $cash->id) : null);
                $firstIsCash = $cash !== null && (int) ($first['method_id'] ?? 0) === (int) $cash->id;
                $second = $firstIsCash && $card ? $this->emptyLine((string) $card->id) : ($cash ? $this->emptyLine((string) $cash->id) : null);
                $this->paymentLines = array_values(array_filter([$first, $second]));
                if (isset($this->paymentLines[1])) {
                    $this->paymentLines[1]['amount'] = $this->remainingForNewLine(app(PosOpenOrderManager::class), app(PosContextResolver::class), 1);
                    $this->paymentLines[1]['auto_remaining'] = true;
                }
            }
        } elseif ($this->paymentLines === []) {
            $this->paymentLines = [$this->emptyLine()];
        }
    }

    /** @return array<string, string> */
    private function emptyLine(string $methodId = ''): array
    {
        return ['method_id' => $methodId, 'amount' => '', 'tendered' => '', 'evidence_reference' => '', 'gift_card_identifier' => ''];
    }

    private function remainingForNewLine(PosOpenOrderManager $orders, PosContextResolver $contexts, ?int $excludeIndex = null): string
    {
        /** @var User $user */
        $user = auth()->user();
        $order = $orders->active(request(), $user, $contexts->resolve($user));
        $remaining = (string) ($order?->total ?? '0.00');
        $methods = PaymentMethod::query()->whereIn('id', array_filter(array_map(fn (array $line): int => (int) ($line['method_id'] ?? 0), $this->paymentLines)))->get()->keyBy('id');
        foreach ($this->paymentLines as $index => $line) {
            if ($index === $excludeIndex) {
                continue;
            }
            $method = $methods->get((int) ($line['method_id'] ?? 0));
            $amount = $method?->isCash() ? ($line['tendered'] ?? '') : ($line['amount'] ?? '');
            if (is_numeric($amount) && bccomp((string) $amount, '0', 2) > 0) {
                $remaining = bcsub($remaining, (string) $amount, 2);
            }
        }

        return bccomp($remaining, '0', 2) > 0 ? $remaining : '0.00';
    }

    private function saveDraft(PosOpenOrderManager $orders, PosContextResolver $contexts): void
    {
        /** @var User $user */
        $user = auth()->user();
        $orders->savePaymentDraft(request(), $user, $contexts->resolve($user), $this->mode, $this->notes, $this->paymentLines);
    }
}
