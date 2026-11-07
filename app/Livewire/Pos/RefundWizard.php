<?php

declare(strict_types=1);

namespace App\Livewire\Pos;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Customer\Support\PhoneNormalizer;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Pricing\Services\EffectivePriceResolver;
use App\Modules\Retail\Actions\CreateUnverifiedReturnAction;
use App\Modules\Retail\Actions\RetailReturnAction;
use App\Modules\Retail\Models\GiftReceipt;
use App\Modules\Retail\Models\RetailReturn;
use App\Modules\Retail\Models\Sale;
use App\Modules\Retail\Support\PosContextResolver;
use App\Modules\Retail\Support\RetailRefundCalculator;
use App\Support\UserSafeError;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

final class RefundWizard extends Component
{
    use WithPagination;

    public bool $open = false;

    public int $step = 1;

    public string $lookupTerm = '';

    public string $searchMode = 'customer';

    public string $customerTerm = '';

    public string $saleDate = '';

    public string $branchFilter = '';

    public string $amountFilter = '';

    public string $productFilter = '';

    public string $cashierFilter = '';

    public bool $searched = false;

    public string $unverifiedProductQuery = '';

    public ?int $unverifiedProductId = null;

    public string $unverifiedQuantity = '1';

    public string $unverifiedAmount = '';

    /** @var array<int, array{product_id: int, item_code: string, name: string, quantity: string, unit_price: string}> */
    public array $unverifiedLines = [];

    public string $unverifiedMethodId = '';

    public string $unverifiedReference = '';

    public string $unverifiedReason = '';

    public string $unverifiedNote = '';

    public bool $unverifiedNoSaleConfirmed = false;

    public ?int $saleId = null;

    public ?int $giftReceiptId = null;

    /** @var array<int, string> */
    public array $quantities = [];

    /** @var array<int, string> */
    public array $conditions = [];

    /** @var array<int, string> */
    public array $dispositions = [];

    /** @var array<int, string> */
    public array $returnStoreIds = [];

    /** @var array<int, array<string, string|int|null>> */
    public array $allocations = [];

    /** @var array<string, string> */
    public array $totals = [];

    public string $refundMethod = 'original_tender';

    public string $reason = '';

    public string $reasonNote = '';

    public string $idempotencyKey = '';

    public bool $submitting = false;

    public ?int $resultReturnId = null;

    public string $resultStatus = '';

    public function mount(): void
    {
        Gate::authorize('returns.create');
        $this->idempotencyKey = (string) Str::uuid();
    }

    #[On('open-pos-refund')]
    public function openWizard(?string $invoiceNumber = null): void
    {
        Gate::authorize('returns.create');
        Gate::authorize('returns.submit');
        $this->reset([
            'lookupTerm', 'customerTerm', 'saleDate', 'branchFilter', 'amountFilter', 'productFilter', 'cashierFilter', 'searched', 'saleId', 'giftReceiptId', 'quantities', 'conditions', 'dispositions',
            'returnStoreIds', 'allocations', 'totals', 'refundMethod', 'reason', 'resultReturnId',
            'resultStatus', 'submitting', 'reasonNote', 'unverifiedProductQuery', 'unverifiedProductId',
            'unverifiedQuantity', 'unverifiedAmount', 'unverifiedLines', 'unverifiedMethodId', 'unverifiedReference',
            'unverifiedReason', 'unverifiedNote', 'unverifiedNoSaleConfirmed',
        ]);
        $this->open = true;
        $this->step = 1;
        $this->searchMode = 'customer';
        $this->lookupTerm = $invoiceNumber ?? '';
        $this->idempotencyKey = (string) Str::uuid();
        $this->dispatch('focus-refund-lookup');
    }

    public function close(): void
    {
        $this->open = false;
        $this->resetErrorBag();
    }

    public function lookup(RetailRefundCalculator $calculator): void
    {
        Gate::authorize('returns.create');
        $term = trim($this->lookupTerm);
        if ($term === '') {
            $this->addError('lookupTerm', __('Enter an invoice, order, receipt, or barcode code.'));

            return;
        }

        /** @var User $actor */
        $actor = auth()->user();
        $receipt = GiftReceipt::query()->visibleTo($actor)->where('reference', $term)->where('status', 'active')->first();
        $base = Sale::query()->visibleTo($actor)->approved()
            ->with(['store.company', 'lines.product', 'payments.paymentMethod']);
        $sale = (clone $base)->where('document_number', $term)->first();
        if ($sale === null && $receipt !== null) {
            $sale = (clone $base)->whereKey($receipt->sale_id)->first();
        }
        if ($sale === null && ctype_digit($term)) {
            $sale = (clone $base)->whereKey((int) $term)->first();
        }
        if ($sale === null) {
            app(RecordAuditEvent::class)->execute('retail', 'pos_refund_lookup_rejected', Sale::class, metadata: ['lookup_hash' => hash('sha256', $term)], explicitSourceId: 'unresolved');
            $this->addError('lookupTerm', __('No posted sale matching this code is available in your authorized scope.'));

            return;
        }

        $context = app(PosContextResolver::class)->resolve($actor);
        if ($context->isReady() && ((int) $context->branch?->id !== (int) $sale->branch_id || (int) $context->store?->id !== (int) $sale->store_id)) {
            app(RecordAuditEvent::class)->execute('retail', 'pos_refund_lookup_rejected', $sale, branchId: (int) $sale->branch_id, storeId: (int) $sale->store_id, metadata: ['reason' => 'active_outlet_mismatch']);
            $this->addError('lookupTerm', __('The sale belongs to another outlet and cannot be refunded from this POS context.'));

            return;
        }

        $this->saleId = (int) $sale->id;
        $this->giftReceiptId = $receipt?->id;
        $this->quantities = [];
        $this->conditions = [];
        $this->dispositions = [];
        $this->returnStoreIds = [];
        foreach ($sale->lines as $line) {
            $remaining = $calculator->remainingQuantity($line);
            $this->quantities[$line->id] = '0';
            $this->conditions[$line->id] = 'uninspected';
            $this->dispositions[$line->id] = 'restock';
            $this->returnStoreIds[$line->id] = (string) $sale->store_id;
            if (bccomp($remaining, '0', 6) < 0) {
                $this->quantities[$line->id] = '0';
            }
        }
        $this->refundMethod = 'original_tender';
        $this->allocations = [];
        $this->totals = [];
        $this->step = 2;
        $this->resetErrorBag();
        app(RecordAuditEvent::class)->execute('retail', 'pos_refund_sale_verified', $sale, branchId: (int) $sale->branch_id, storeId: (int) $sale->store_id, metadata: ['gift_receipt_id' => $receipt?->id]);
    }

    public function searchSales(): void
    {
        Gate::authorize('returns.create');
        Gate::authorize('returns.submit');
        $this->resetErrorBag();
        if ($this->searchMode === 'customer' && mb_strlen(trim($this->customerTerm)) < 3) {
            $this->addError('customerTerm', __('Enter at least three characters of the customer name or phone.'));

            return;
        }
        if ($this->searchMode === 'date' && (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->saleDate) || ! checkdate((int) substr($this->saleDate, 5, 2), (int) substr($this->saleDate, 8, 2), (int) substr($this->saleDate, 0, 4)))) {
            $this->addError('saleDate', __('Select a valid sale date.'));

            return;
        }
        $this->searched = true;
        $this->resetPage();
    }

    public function setSearchMode(string $mode): void
    {
        if (! in_array($mode, ['customer', 'date'], true)) {
            return;
        }
        $this->searchMode = $mode;
        $this->searched = false;
        $this->resetPage();
    }

    public function openUnverified(): void
    {
        Gate::authorize('returns.unverified');
        Gate::authorize('returns.approve');
        Gate::authorize('returns.complete');
        $this->step = 8;
        $this->unverifiedLines = [];
        $this->idempotencyKey = (string) Str::uuid();
        $this->resetErrorBag();
        $this->dispatch('focus-unverified-product');
    }

    public function chooseUnverifiedProduct(int $productId): void
    {
        Gate::authorize('returns.unverified');
        /** @var User $actor */
        $actor = auth()->user();
        $context = app(PosContextResolver::class)->resolve($actor);
        if (! $context->isReady()) {
            $this->addError('unverifiedProductId', __('An active POS location and shift are required.'));

            return;
        }
        $product = Product::query()->active()->where('has_variations', false)->availableToCompany((int) $context->store?->company_id, (int) $context->branch?->id, (int) $context->store?->id)->whereKey($productId)->first();
        if ($product === null) {
            $this->addError('unverifiedProductId', __('Select an active product available to this selling location.'));

            return;
        }
        if (count($this->unverifiedLines) >= 50) {
            $this->addError('unverifiedLines', __('A return supports at most fifty items.'));

            return;
        }
        if (collect($this->unverifiedLines)->contains(fn (array $line): bool => (int) $line['product_id'] === (int) $product->id)) {
            $this->addError('unverifiedLines', __('This product is already in the return. Edit its quantity instead.'));

            return;
        }
        $price = app(EffectivePriceResolver::class)->resolveForStore([(int) $product->id], (int) $context->store->id)->get((int) $product->id)?->amount;
        $this->unverifiedLines[] = [
            'product_id' => (int) $product->id,
            'item_code' => (string) $product->item_code,
            'name' => (string) (str_starts_with(app()->getLocale(), 'ar') ? $product->name_ar : $product->name_en),
            'quantity' => '1',
            'unit_price' => bcadd((string) ($price ?? $product->sale_price ?? '0.00'), '0', 2),
        ];
        $this->unverifiedProductId = (int) $product->id;
        $this->unverifiedProductQuery = '';
    }

    public function removeUnverifiedLine(int $index): void
    {
        Gate::authorize('returns.unverified');
        unset($this->unverifiedLines[$index]);
        $this->unverifiedLines = array_values($this->unverifiedLines);
    }

    public function unverifiedTotal(): string
    {
        $total = '0.00';
        foreach ($this->unverifiedLines as $line) {
            if (is_numeric($line['quantity'] ?? null) && is_numeric($line['unit_price'] ?? null)) {
                $total = bcadd($total, bcmul((string) $line['quantity'], (string) $line['unit_price'], 2), 2);
            }
        }

        return $total;
    }

    public function updatedUnverifiedProductQuery(): void
    {
        $this->unverifiedProductId = null;
    }

    public function submitUnverified(CreateUnverifiedReturnAction $action): void
    {
        Gate::authorize('returns.unverified');
        Gate::authorize('returns.approve');
        Gate::authorize('returns.complete');
        if ($this->submitting) {
            return;
        }
        $this->resetErrorBag();
        if (! $this->unverifiedNoSaleConfirmed) {
            $this->addError('unverified', __('Confirm that barcode, invoice, customer, and date searches found no original sale.'));

            return;
        }
        $this->submitting = true;
        try {
            /** @var User $actor */
            $actor = auth()->user();
            $document = $action->execute($actor, [
                'lines' => $this->unverifiedLines,
                'payment_method_id' => $this->unverifiedMethodId,
                'safe_reference' => $this->unverifiedReference,
                'reason' => $this->unverifiedReason,
                'note' => $this->unverifiedNote,
                'confirmed_no_sale' => true,
            ], $this->idempotencyKey);
            $this->resultReturnId = (int) $document->id;
            $this->resultStatus = (string) $document->status;
            $this->step = 7;
        } catch (\Throwable $exception) {
            $this->addError('unverified', UserSafeError::message($exception));
        } finally {
            $this->submitting = false;
        }
    }

    public function chooseSale(int $saleId, RetailRefundCalculator $calculator): void
    {
        Gate::authorize('returns.create');
        /** @var User $actor */
        $actor = auth()->user();
        $sale = Sale::query()->visibleTo($actor)->approved()->whereKey($saleId)->first();
        if ($sale === null) {
            $this->addError('lookupTerm', __('This sale is no longer available in your authorized scope.'));

            return;
        }
        $this->lookupTerm = (string) $sale->document_number;
        $this->lookup($calculator);
    }

    private function searchResults(): ?LengthAwarePaginator
    {
        if (! $this->searched || $this->step !== 1) {
            return null;
        }
        if (($this->searchMode === 'customer' && mb_strlen(trim($this->customerTerm)) < 3)
            || ($this->searchMode === 'date' && (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->saleDate)
                || ! checkdate((int) substr($this->saleDate, 5, 2), (int) substr($this->saleDate, 8, 2), (int) substr($this->saleDate, 0, 4))))) {
            return null;
        }
        /** @var User $actor */
        $actor = auth()->user();
        $query = Sale::query()->visibleTo($actor)->approved();
        if ($this->searchMode === 'customer') {
            $term = trim($this->customerTerm);
            $name = addcslashes($term, '%_\\');
            $phone = PhoneNormalizer::digits($term);
            $phoneVariants = [$phone];
            if (preg_match('/^01[0125][0-9]{8}$/', $phone) === 1) {
                $phoneVariants[] = '20'.substr($phone, 1);
            }
            $query->whereHas('customer', function ($customer) use ($name, $phoneVariants): void {
                $customer->where('name_ar', 'like', $name.'%')->orWhere('name_en', 'like', $name.'%');
                foreach ($phoneVariants as $candidate) {
                    if ($candidate !== '' && preg_match('/^\d+$/', $candidate)) {
                        $prefix = addcslashes($candidate, '%_\\').'%';
                        $customer->orWhere('phone_normalized', 'like', $prefix)
                            ->orWhere('secondary_phone_normalized', 'like', $prefix);
                    }
                }
            });
        } else {
            $context = app(PosContextResolver::class)->resolve($actor);
            $timezone = $context->store?->company?->timezone ?: 'Africa/Cairo';
            $start = Carbon::parse($this->saleDate, $timezone)->startOfDay();
            $query->where('approved_at', '>=', $start->copy()->utc()->format('Y-m-d H:i:s'))
                ->where('approved_at', '<', $start->addDay()->utc()->format('Y-m-d H:i:s'));
            if (ctype_digit($this->branchFilter)) {
                $query->where('branch_id', (int) $this->branchFilter);
            }
            if (is_numeric($this->amountFilter) && (float) $this->amountFilter > 0) {
                $query->whereBetween('payable_total', [bcsub($this->amountFilter, '5', 2), bcadd($this->amountFilter, '5', 2)]);
            }
            if (ctype_digit($this->cashierFilter)) {
                $query->where('cashier_id', (int) $this->cashierFilter);
            }
            if (mb_strlen(trim($this->productFilter)) >= 2) {
                $product = addcslashes(trim($this->productFilter), '%_\\');
                $query->whereHas('lines', fn ($lines) => $lines->where('item_code', 'like', $product.'%')
                    ->orWhereHas('product.barcodes', fn ($barcodes) => $barcodes->where('barcode', $product)->where('status', 'active')));
            }
        }

        return $query->with(['customer', 'branch', 'store.company', 'cashier', 'payments.paymentMethod'])
            ->orderByDesc('approved_at')->orderByDesc('id')->paginate(20);
    }

    public function setAllRefundable(RetailRefundCalculator $calculator): void
    {
        $sale = $this->sale();
        if ($sale === null) {
            return;
        }
        foreach ($sale->lines as $line) {
            $this->quantities[$line->id] = $calculator->remainingQuantity($line);
        }
    }

    public function next(RetailRefundCalculator $calculator): void
    {
        $this->resetErrorBag();
        if ($this->step === 1) {
            $this->lookup($calculator);

            return;
        }
        if ($this->step === 2) {
            $this->step = 3;

            return;
        }
        if ($this->step === 3 || $this->step === 4) {
            if (! $this->refreshPreview($calculator)) {
                return;
            }
            $this->step++;
            if ($this->step === 5) {
                $this->prepareAllocations($calculator);
            }

            return;
        }
        if ($this->step === 5) {
            if (! $this->refreshPreview($calculator) || ! $this->validateRefundMethod()) {
                return;
            }
            $this->step = 6;
        }
    }

    public function back(): void
    {
        if ($this->step > 1 && $this->resultReturnId === null) {
            $this->step--;
            $this->resetErrorBag();
        }
    }

    public function chooseRefundMethod(string $method, RetailRefundCalculator $calculator): void
    {
        if (! in_array($method, ['original_tender', 'cash_refund', 'gift_card'], true)) {
            return;
        }
        if ($this->isRefundOverride($method) && ! Gate::allows('returns.approve')) {
            $this->addError('refundMethod', __('Changing the original refund method requires return approval permission.'));

            return;
        }
        $this->refundMethod = $method;
        $this->prepareAllocations($calculator);
    }

    public function submit(RetailRefundCalculator $calculator, RetailReturnAction $action): void
    {
        Gate::authorize('returns.create');
        Gate::authorize('returns.submit');
        if ($this->submitting) {
            return;
        }
        $this->submitting = true;
        $this->resetErrorBag();

        try {
            $sale = $this->sale();
            if ($sale === null || ! $this->refreshPreview($calculator) || ! $this->validateRefundMethod()) {
                return;
            }
            /** @var User $actor */
            $actor = auth()->user();
            $document = $action->create($actor, [
                'source_sale_id' => $sale->id,
                'source_gift_receipt_id' => $this->giftReceiptId,
                'settlement_type' => $this->refundMethod,
                'reason' => $this->reasonNote === '' ? $this->reason : $this->reason.' — '.trim($this->reasonNote),
                'lines' => $this->selectedLines(),
            ], $this->idempotencyKey);
            $document = $action->submit($actor, $document);

            if ($actor->canBypassApproval() && Gate::forUser($actor)->allows('returns.approve') && Gate::forUser($actor)->allows('returns.complete')) {
                $document = $action->approve($actor, $document);
                $document = $action->complete($actor, $document, $this->idempotencyKey.':complete', allocations: $this->refundMethod === 'gift_card' ? [] : $this->allocations);
            }

            $this->resultReturnId = (int) $document->id;
            $this->resultStatus = (string) $document->status;
            $this->step = 7;
        } catch (\Throwable $exception) {
            $this->addError('refund', UserSafeError::message($exception));
        } finally {
            $this->submitting = false;
        }
    }

    public function render(RetailRefundCalculator $calculator): View
    {
        /** @var User $actor */
        $actor = auth()->user();
        $sale = $this->sale();
        $cashMethod = PaymentMethod::query()->where('status', 'active')->get()->first(fn (PaymentMethod $method): bool => $method->isCash());
        $context = app(PosContextResolver::class)->resolve($actor);
        $cashAvailable = $sale !== null && $cashMethod !== null && $context->isReady()
            && (int) $context->branch?->id === (int) $sale->branch_id
            && (int) $context->store?->id === (int) $sale->store_id;
        $cashRefundAllowed = $cashAvailable && (! $this->isRefundOverride('cash_refund') || Gate::forUser($actor)->allows('returns.approve'));
        $remaining = [];
        $originalTax = [];
        if ($sale !== null) {
            foreach ($sale->lines as $line) {
                $remaining[$line->id] = $calculator->remainingQuantity($line);
                $originalTax[$line->id] = $calculator->originalTaxForLine($sale, $line);
            }
        }
        $result = $this->resultReturnId === null ? null : RetailReturn::query()->visibleTo($actor)->whereKey($this->resultReturnId)->first();

        $searchResults = $this->searchResults();
        $searchBranches = $this->step === 1 ? Branch::query()->visibleTo($actor)->where('status', 'active')->orderBy('code')->limit(100)->get(['id', 'code', 'name_ar', 'name_en']) : collect();
        $unverifiedProducts = collect();
        $unverifiedMethods = collect();
        if ($this->step === 8) {
            $context = app(PosContextResolver::class)->resolve($actor);
            if ($context->isReady() && mb_strlen(trim($this->unverifiedProductQuery)) >= 2) {
                $term = addcslashes(trim($this->unverifiedProductQuery), '%_\\');
                $unverifiedProducts = Product::query()->active()->where('has_variations', false)->availableToCompany((int) $context->store?->company_id, (int) $context->branch?->id, (int) $context->store?->id)
                    ->where(fn ($query) => $query->where('item_code', 'like', $term.'%')
                        ->orWhere('name_ar', 'like', $term.'%')->orWhere('name_en', 'like', $term.'%')
                        ->orWhereHas('barcodes', fn ($barcodes) => $barcodes->where('barcode', $term)->where('status', 'active')))
                    ->orderBy('item_code')->limit(10)->get(['id', 'item_code', 'name_ar', 'name_en']);
            }
            $unverifiedMethods = PaymentMethod::query()->where('status', 'active')->where('type', '!=', 'gift_card')->orderBy('code')->limit(30)->get();
        }

        return view('livewire.pos.refund-wizard', compact('sale', 'cashMethod', 'cashAvailable', 'cashRefundAllowed', 'remaining', 'originalTax', 'result', 'searchResults', 'searchBranches', 'unverifiedProducts', 'unverifiedMethods'));
    }

    private function refreshPreview(RetailRefundCalculator $calculator): bool
    {
        $sale = $this->sale();
        if ($sale === null) {
            $this->addError('refund', __('Find and verify the original sale first.'));

            return false;
        }
        try {
            /** @var User $actor */
            $actor = auth()->user();
            $receipt = $this->giftReceiptId === null ? null : GiftReceipt::query()->visibleTo($actor)->whereKey($this->giftReceiptId)->first();
            $prepared = $calculator->prepare($actor, $sale, $this->selectedLines(), $receipt);
            $this->totals = $prepared['totals'];

            return true;
        } catch (\Throwable $exception) {
            $this->addError('refund', UserSafeError::message($exception));

            return false;
        }
    }

    private function prepareAllocations(RetailRefundCalculator $calculator): void
    {
        $sale = $this->sale();
        $total = (string) ($this->totals['settlement_value'] ?? '0.00');
        if ($sale === null || bccomp($total, '0.00', 2) <= 0) {
            $this->allocations = [];

            return;
        }
        if ($this->refundMethod === 'original_tender') {
            try {
                $this->allocations = $calculator->defaultAllocations($sale, $total);
            } catch (\Throwable $exception) {
                $this->allocations = [];
                $this->addError('refundMethod', UserSafeError::message($exception));
            }
        } elseif ($this->refundMethod === 'cash_refund') {
            $cash = PaymentMethod::query()->where('status', 'active')->get()->first(fn (PaymentMethod $method): bool => $method->isCash());
            $this->allocations = $cash === null ? [] : [['method_id' => $cash->id, 'original_payment_id' => null, 'amount' => $total, 'safe_reference' => '']];
        } else {
            $this->allocations = [];
        }
    }

    private function validateRefundMethod(): bool
    {
        if (! in_array($this->reason, ['defective', 'wrong_item', 'damaged', 'size_color', 'changed_mind', 'sale_error', 'other'], true)) {
            $this->addError('reason', __('A refund reason is required.'));

            return false;
        }
        /** @var User $actor */
        $actor = auth()->user();
        if ($this->isRefundOverride($this->refundMethod) && ! Gate::forUser($actor)->allows('returns.approve')) {
            $this->addError('refundMethod', __('Changing the original refund method requires return approval permission.'));

            return false;
        }
        $sale = $this->sale();
        $containsCash = $this->refundMethod === 'cash_refund';
        if ($sale !== null && $this->refundMethod === 'original_tender') {
            $cashPaymentIds = $sale->payments->filter(fn ($payment): bool => $payment->paymentMethod?->isCash() ?? false)->pluck('id')->map(fn ($id): int => (int) $id)->all();
            $containsCash = collect($this->allocations)->contains(fn (array $line): bool => in_array((int) ($line['original_payment_id'] ?? 0), $cashPaymentIds, true));
        }
        if ($containsCash) {
            $context = app(PosContextResolver::class)->resolve($actor);
            if ($sale === null || ! $context->isReady() || (int) $context->store?->id !== (int) $sale->store_id || (int) $context->branch?->id !== (int) $sale->branch_id) {
                $this->addError('refundMethod', __('Cash refund is unavailable without an open authorized shift and drawer at the original outlet. Choose an eligible non-cash refund method.'));

                return false;
            }
        }
        if ($this->refundMethod !== 'gift_card') {
            $sum = collect($this->allocations)->reduce(fn (string $carry, array $line): string => bcadd($carry, (string) ($line['amount'] ?? '0'), 2), '0.00');
            if (bccomp($sum, (string) ($this->totals['settlement_value'] ?? '0.00'), 2) !== 0) {
                $this->addError('allocations', __('Refund payment allocations must equal the final refundable amount exactly.'));

                return false;
            }
        }

        return true;
    }

    private function isRefundOverride(string $method): bool
    {
        if ($method === 'original_tender') {
            return false;
        }
        $sale = $this->sale();

        return $method !== 'cash_refund' || $sale === null || $sale->payments->isEmpty()
            || ! $sale->payments->every(fn ($payment): bool => $payment->paymentMethod?->isCash() ?? false);
    }

    /** @return array<int, array<string, mixed>> */
    private function selectedLines(): array
    {
        $lines = [];
        foreach ($this->quantities as $lineId => $quantity) {
            if (! is_numeric($quantity) || bccomp((string) $quantity, '0', 6) <= 0) {
                continue;
            }
            $condition = (string) ($this->conditions[$lineId] ?? '');
            $lines[] = [
                'sale_line_id' => (int) $lineId,
                'quantity' => (string) $quantity,
                'condition' => $condition,
                'disposition' => (string) ($this->dispositions[$lineId] ?? ($condition === 'sellable' ? 'restock' : 'quarantine')),
                'return_store_id' => (int) ($this->returnStoreIds[$lineId] ?? 0),
            ];
        }

        return $lines;
    }

    private function sale(): ?Sale
    {
        if ($this->saleId === null) {
            return null;
        }
        /** @var User $actor */
        $actor = auth()->user();

        return Sale::query()->visibleTo($actor)->approved()->whereKey($this->saleId)
            ->with(['store.company', 'branch', 'customer', 'lines.product', 'payments.paymentMethod'])
            ->first();
    }
}
