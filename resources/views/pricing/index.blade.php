<?php

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Platform\Models\Store;
use App\Modules\Pricing\Actions\ApprovePriceProposalAction;
use App\Modules\Pricing\Actions\CreatePriceProposalAction;
use App\Modules\Pricing\Actions\ImportPriceProposalsAction;
use App\Modules\Pricing\Actions\RejectPriceProposalAction;
use App\Modules\Pricing\Actions\SubmitPriceProposalAction;
use App\Modules\Pricing\Enums\PriceVersionState;
use App\Modules\Pricing\Models\PriceLine;
use App\Modules\Pricing\Models\PriceVersion;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Pricing Workspace')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = 'all';

    public bool $showProposalForm = false;

    public bool $showImportForm = false;

    public string $importCsv = "item_code,store_code,amount,effective_from,source_reference\n";

    public ?int $compareVersionId = null;

    public string $rejectionReason = '';

    public array $form = [
        'price_list_code' => 'LOCAL-RETAIL',
        'price_list_name_ar' => 'قائمة أسعار العرض المحلي',
        'price_list_name_en' => 'Local Demo Retail Price List',
        'product_id' => '',
        'store_id' => '',
        'amount' => '',
        'reference_amount' => '',
        'open_price_minimum' => '',
        'open_price_maximum' => '',
        'source_type' => 'product_card',
        'source_reference' => '',
        'effective_from' => '',
        'effective_to' => '',
        'reason_text' => '',
        'open_price_allowed' => false,
    ];

    public function mount(): void
    {
        Gate::authorize('pricing_labels.view');
    }

    public function updatingSearch(string $value): void
    {
        $this->search = Str::limit($value, 100, '');
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function openProposalForm(): void
    {
        Gate::authorize('pricing_labels.create');
        $this->resetValidation();
        $this->showProposalForm = true;
    }

    public function openImportForm(): void
    {
        Gate::authorize('pricing_labels.create');
        $this->resetValidation();
        $this->showImportForm = true;
    }

    public function importProposals(): void
    {
        Gate::authorize('pricing_labels.create');
        $data = $this->validate([
            'importCsv' => ['required', 'string', 'max:100000'],
        ]);

        app(ImportPriceProposalsAction::class)->execute(
            csv: $data['importCsv'],
            priceListCode: 'LOCAL-RETAIL',
            priceListNameAr: 'قائمة أسعار العرض المحلي',
            priceListNameEn: 'Local Demo Retail Price List',
        );

        $this->showImportForm = false;
        $this->resetPage();
        session()->flash('status', __('CSV proposals imported as Draft; approval is still required.'));
    }

    public function openDiff(int $versionId): void
    {
        Gate::authorize('pricing_labels.view');
        $this->compareVersionId = $versionId;
    }

    public function closeDiff(): void
    {
        $this->compareVersionId = null;
    }

    public function saveProposal(): void
    {
        Gate::authorize('pricing_labels.create');
        $data = $this->validate([
            'form.price_list_code' => ['required', 'string', 'max:80', 'regex:/^[A-Za-z0-9_-]+$/'],
            'form.price_list_name_ar' => ['required', 'string', 'max:255'],
            'form.price_list_name_en' => ['required', 'string', 'max:255'],
            'form.product_id' => ['required', 'integer', 'exists:products,id'],
            'form.store_id' => ['required', 'integer', 'exists:stores,id'],
            'form.amount' => ['required', 'numeric', 'gt:0', 'decimal:0,3'],
            'form.reference_amount' => ['nullable', 'numeric', 'gte:0', 'decimal:0,3'],
            'form.open_price_minimum' => ['nullable', 'numeric', 'gte:0', 'decimal:0,4', 'required_if:form.open_price_allowed,true'],
            'form.open_price_maximum' => ['nullable', 'numeric', 'gte:0', 'decimal:0,4', 'required_if:form.open_price_allowed,true', 'gte:form.open_price_minimum'],
            'form.source_type' => ['required', Rule::in(['product_card', 'import', 'purchase_context', 'branch_exception'])],
            'form.source_reference' => ['nullable', 'string', 'max:120'],
            'form.effective_from' => ['nullable', 'date'],
            'form.effective_to' => ['nullable', 'date', 'after:form.effective_from'],
            'form.reason_text' => ['nullable', 'string', 'max:1000'],
            'form.open_price_allowed' => ['boolean'],
        ]);

        app(CreatePriceProposalAction::class)->execute(
            product: Product::query()->findOrFail((int) $data['form']['product_id']),
            store: Store::query()->findOrFail((int) $data['form']['store_id']),
            priceListCode: $data['form']['price_list_code'],
            priceListNameAr: $data['form']['price_list_name_ar'],
            priceListNameEn: $data['form']['price_list_name_en'],
            amount: $data['form']['amount'],
            sourceType: $data['form']['source_type'],
            sourceReference: $data['form']['source_reference'] ?: null,
            effectiveFrom: $data['form']['effective_from'] ?: null,
            effectiveTo: $data['form']['effective_to'] ?: null,
            reasonText: $data['form']['reason_text'] ?: null,
            referenceAmount: $data['form']['reference_amount'] ?: null,
            openPriceAllowed: (bool) $data['form']['open_price_allowed'],
            openPriceMinimum: $data['form']['open_price_minimum'] ?: null,
            openPriceMaximum: $data['form']['open_price_maximum'] ?: null,
        );

        $this->showProposalForm = false;
        $this->resetPage();
        session()->flash('status', __('Draft price proposal created.'));
    }

    public function submitProposal(int $versionId): void
    {
        app(SubmitPriceProposalAction::class)->execute(PriceVersion::query()->findOrFail($versionId));
        session()->flash('status', __('Price proposal submitted for approval.'));
    }

    public function approveProposal(int $versionId): void
    {
        app(ApprovePriceProposalAction::class)->execute(PriceVersion::query()->findOrFail($versionId));
        session()->flash('status', __('Price version approved and effective where its date allows.'));
    }

    public function rejectProposal(int $versionId): void
    {
        Gate::authorize('pricing_labels.approve');
        $this->validate(['rejectionReason' => ['required', 'string', 'max:1000']]);
        app(RejectPriceProposalAction::class)->execute(PriceVersion::query()->findOrFail($versionId), $this->rejectionReason);
        $this->rejectionReason = '';
        session()->flash('status', __('Price proposal rejected with an audit reason.'));
    }

    public function render(): mixed
    {
        $query = PriceVersion::query()->with(['priceList', 'lines.product', 'lines.store', 'approvalRecord.requester', 'approvalRecord.approver'])->latest('id');
        if ($this->statusFilter !== 'all') {
            $query->where('state', $this->statusFilter);
        }
        if ($this->search !== '') {
            $query->where(function ($scope): void {
                $scope->whereHas('priceList', fn ($list) => $list->where('code', 'like', '%'.$this->search.'%'))
                    ->orWhereHas('lines.product', fn ($product) => $product->where('item_code', 'like', '%'.$this->search.'%')->orWhere('name_en', 'like', '%'.$this->search.'%')->orWhere('name_ar', 'like', '%'.$this->search.'%'));
            });
        }

        /** @var User $user */
        $user = request()->user();
        $versions = $query->paginate(12);
        $products = Product::query()->active()->orderBy('item_code')->limit(500)->get(['id', 'item_code', 'name_ar', 'name_en']);
        $stores = Store::query()->visibleTo($user)->where('status', 'active')->orderBy('code')->get(['id', 'code', 'name_ar', 'name_en']);
        $storeIds = $stores->pluck('id');
        $pricedProductIds = $storeIds->isEmpty() ? collect() : PriceLine::query()
            ->whereIn('store_id', $storeIds)
            ->whereHas('version', function ($version): void {
                $version->where('state', PriceVersionState::Approved->value)
                    ->where(function ($dates): void {
                        $dates->whereNull('effective_from')->orWhere('effective_from', '<=', now());
                    })
                    ->where(function ($dates): void {
                        $dates->whereNull('effective_to')->orWhere('effective_to', '>=', now());
                    });
            })
            ->pluck('product_id')
            ->unique();
        $unpricedProducts = Product::query()->active()->whereNotIn('id', $pricedProductIds)->orderBy('item_code')->limit(12)->get(['id', 'item_code', 'name_ar', 'name_en']);
        $diffVersion = $this->compareVersionId === null ? null : PriceVersion::query()->with(['priceList', 'lines.product', 'lines.store'])->find($this->compareVersionId);
        $diffPrevious = $diffVersion === null ? null : PriceVersion::query()->with(['lines.product', 'lines.store'])->where('price_list_id', $diffVersion->price_list_id)->where('version', '<', $diffVersion->version)->latest('version')->first();

        return view('pricing.index', compact('versions', 'products', 'stores', 'unpricedProducts', 'diffVersion', 'diffPrevious'));
    }
};
?>
<div class="mx-auto max-w-7xl space-y-6 p-4 sm:p-6" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
    <div class="flex flex-col gap-4 rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Pricing workspace') }}</flux:heading>
            <flux:text class="mt-1 max-w-3xl">{{ __('Create immutable price proposals, submit them through approval, and resolve only approved effective prices. Cost changes never rewrite sale prices.') }}</flux:text>
        </div>
        <x-tables.resource-toolbar filter-target="pricing-filters">
            @can('pricing_labels.create')
                <flux:button variant="subtle" wire:click="openImportForm">{{ __('Import CSV') }}</flux:button>
                <flux:button variant="primary" wire:click="openProposalForm">{{ __('New proposal') }}</flux:button>
            @endcan
        </x-tables.resource-toolbar>
    </div>

    @if (session('status'))
        <flux:callout variant="success" icon="check-circle">{{ session('status') }}</flux:callout>
    @endif

    <flux:callout variant="warning" icon="exclamation-triangle" heading="{{ __('Local/Dev boundary') }}">
        {{ __('This workspace is a reversible Local/Dev implementation. Production price authority, branch exceptions, open-price limits, rounding, labels, UAT, and release approval remain pending.') }}
    </flux:callout>

    <div id="pricing-filters" class="scroll-mt-24 grid gap-4 lg:grid-cols-[1fr_auto]">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="{{ __('Search list, item code, or product') }}" />
        <flux:select wire:model.live="statusFilter" class="min-w-48">
            <option value="all">{{ __('All states') }}</option>
            @foreach (PriceVersionState::cases() as $state)
                <option value="{{ $state->value }}">{{ __(ucfirst($state->value)) }}</option>
            @endforeach
        </flux:select>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <flux:card><flux:text>{{ __('Total versions') }}</flux:text><flux:heading size="lg">{{ $versions->total() }}</flux:heading></flux:card>
        <flux:card><flux:text>{{ __('Pending approval') }}</flux:text><flux:heading size="lg">{{ $versions->where('state', 'submitted')->count() }}</flux:heading></flux:card>
        <flux:card><flux:text>{{ __('Owner-configurable') }}</flux:text><flux:heading size="lg">{{ __('Open') }}</flux:heading></flux:card>
    </div>

    @if ($unpricedProducts->isNotEmpty())
        <flux:card>
            <div class="flex items-start justify-between gap-4">
                <div>
                    <flux:heading size="lg">{{ __('Unpriced products') }}</flux:heading>
                    <flux:text class="mt-1">{{ __('These active products have no approved effective price in the visible stores. Receiving may continue, but POS sale and label generation remain blocked until pricing is approved.') }}</flux:text>
                </div>
                <flux:badge color="amber">{{ __('Pricing pending') }}</flux:badge>
            </div>
            <div class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($unpricedProducts as $unpricedProduct)
                    <div class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm dark:border-amber-900 dark:bg-amber-950/30"><span class="font-semibold">{{ $unpricedProduct->item_code }}</span><span class="text-zinc-600 dark:text-zinc-300"> · {{ app()->getLocale() === 'ar' ? $unpricedProduct->name_ar : $unpricedProduct->name_en }}</span></div>
                @endforeach
            </div>
        </flux:card>
    @endif

    <flux:card class="overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                <thead class="bg-zinc-50 text-start dark:bg-zinc-800/60"><tr><th class="px-4 py-3">{{ __('Version') }}</th><th class="px-4 py-3">{{ __('Product / location') }}</th><th class="px-4 py-3">{{ __('Amount') }}</th><th class="px-4 py-3">{{ __('Source') }}</th><th class="px-4 py-3">{{ __('State') }}</th><th class="px-4 py-3">{{ __('Actions') }}</th></tr></thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($versions as $version)
                        @php($line = $version->lines->first())
                        <tr wire:key="price-version-{{ $version->id }}" class="align-top">
                            <td class="px-4 py-4"><div class="font-semibold">{{ $version->priceList->code }} · v{{ $version->version }}</div><div class="text-xs text-zinc-500">{{ optional($version->effective_from)->format('Y-m-d H:i') ?: __('Immediate') }}</div></td>
                            <td class="px-4 py-4"><div class="font-medium">{{ $line?->product?->item_code }} · {{ app()->getLocale() === 'ar' ? $line?->product?->name_ar : $line?->product?->name_en }}</div><div class="text-xs text-zinc-500">{{ $line?->store?->code }} · {{ app()->getLocale() === 'ar' ? $line?->store?->name_ar : $line?->store?->name_en }}</div></td>
                            <td class="px-4 py-4 font-semibold">{{ $line?->amount }}</td>
                            <td class="px-4 py-4"><div>{{ __(str_replace('_', ' ', ucfirst($version->source_type))) }}</div><div class="text-xs text-zinc-500">{{ $version->source_reference ?: '—' }}</div></td>
                            <td class="px-4 py-4"><flux:badge :color="$version->state === PriceVersionState::Approved ? 'green' : ($version->state === PriceVersionState::Rejected ? 'red' : 'amber')">{{ __(ucfirst($version->state->value)) }}</flux:badge><div class="mt-1 text-xs text-zinc-500">{{ $version->approvalRecord ? __('Approval') . ': ' . __(ucfirst($version->approvalRecord->approval_state->value)) : __('No approval yet') }}</div></td>
                            <td class="space-y-2 px-4 py-4">
                                @can('pricing_labels.view')<flux:button size="sm" variant="subtle" wire:click="openDiff({{ $version->id }})">{{ __('Compare history') }}</flux:button>@endcan
                                @can('pricing_labels.submit') @if ($version->state === PriceVersionState::Draft)<flux:button size="sm" wire:click="submitProposal({{ $version->id }})">{{ __('Submit') }}</flux:button>@endif @endcan
                                @can('pricing_labels.approve') @if ($version->state === PriceVersionState::Submitted)<flux:button size="sm" variant="primary" wire:click="approveProposal({{ $version->id }})">{{ __('Approve') }}</flux:button><flux:input size="sm" wire:model="rejectionReason" placeholder="{{ __('Rejection reason if rejecting') }}" /><flux:button size="sm" variant="danger" wire:click="rejectProposal({{ $version->id }})">{{ __('Reject') }}</flux:button>@endif @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-12 text-center text-zinc-500">{{ __('No price proposals yet. Create a Local/Dev proposal to begin the approval workflow.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-zinc-200 p-4 dark:border-zinc-700">{{ $versions->links() }}</div>
    </flux:card>

    @if ($showImportForm)
        <flux:modal wire:model="showImportForm" name="price-import" class="md:w-[48rem]">
            <div class="space-y-5">
                <flux:heading size="lg">{{ __('Import price proposals') }}</flux:heading>
                <flux:callout variant="warning">{{ __('Local/Dev CSV only. Every imported row becomes Draft and must be approved; no production master data is created.') }}</flux:callout>
                <flux:textarea wire:model="importCsv" label="{{ __('CSV rows') }}" rows="10" />
                <flux:text class="text-xs">{{ __('Columns: item_code, store_code, amount, effective_from, source_reference. Header optional; maximum 200 rows.') }}</flux:text>
                <div class="flex justify-end gap-2"><flux:button wire:click="$set('showImportForm', false)">{{ __('Cancel') }}</flux:button><flux:button variant="primary" wire:click="importProposals">{{ __('Import as Draft') }}</flux:button></div>
            </div>
        </flux:modal>
    @endif

    @if ($diffVersion)
        <flux:modal wire:model="compareVersionId" name="price-diff" class="md:w-[52rem]">
            <div class="space-y-5">
                <div class="flex items-start justify-between gap-4"><div><flux:heading size="lg">{{ __('Price history comparison') }}</flux:heading><flux:text>{{ $diffVersion->priceList->code }} · v{{ $diffVersion->version }}</flux:text></div><flux:button size="sm" wire:click="closeDiff">{{ __('Close') }}</flux:button></div>
                @php($newLine = $diffVersion->lines->first())
                @php($oldLine = $diffPrevious?->lines->first())
                @if ($diffPrevious)
                    <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700"><table class="min-w-full text-sm"><thead class="bg-zinc-50 text-start dark:bg-zinc-800/60"><tr><th class="px-3 py-2">{{ __('Field') }}</th><th class="px-3 py-2">{{ __('Previous v') }}{{ $diffPrevious->version }}</th><th class="px-3 py-2">{{ __('Current v') }}{{ $diffVersion->version }}</th></tr></thead><tbody class="divide-y divide-zinc-200 dark:divide-zinc-700"><tr><td class="px-3 py-2">{{ __('Amount') }}</td><td class="px-3 py-2">{{ $oldLine?->amount ?: '—' }}</td><td class="px-3 py-2 font-semibold">{{ $newLine?->amount ?: '—' }}</td></tr><tr><td class="px-3 py-2">{{ __('State') }}</td><td class="px-3 py-2">{{ __(ucfirst($diffPrevious->state->value)) }}</td><td class="px-3 py-2">{{ __(ucfirst($diffVersion->state->value)) }}</td></tr><tr><td class="px-3 py-2">{{ __('Effective from') }}</td><td class="px-3 py-2">{{ optional($diffPrevious->effective_from)->format('Y-m-d H:i') ?: __('Immediate') }}</td><td class="px-3 py-2">{{ optional($diffVersion->effective_from)->format('Y-m-d H:i') ?: __('Immediate') }}</td></tr><tr><td class="px-3 py-2">{{ __('Source') }}</td><td class="px-3 py-2">{{ $diffPrevious->source_reference ?: '—' }}</td><td class="px-3 py-2">{{ $diffVersion->source_reference ?: '—' }}</td></tr></tbody></table></div>
                @else
                    <flux:callout>{{ __('This is the first version in the price list; no previous version exists.') }}</flux:callout>
                @endif
            </div>
        </flux:modal>
    @endif

    @if ($showProposalForm)
        <flux:modal wire:model="showProposalForm" name="price-proposal" class="md:w-[48rem]">
            <div class="space-y-5">
                <flux:heading size="lg">{{ __('New price proposal') }}</flux:heading>
                <flux:text>{{ __('A proposal is Draft until submitted and approved. It cannot change historical sales or activate by itself.') }}</flux:text>
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="form.price_list_code" label="{{ __('Price list code') }}" />
                    <flux:input wire:model="form.price_list_name_en" label="{{ __('Price list name') }}" />
                    <flux:input wire:model="form.price_list_name_ar" label="{{ __('Price list name (Arabic)') }}" />
                    <flux:select wire:model="form.source_type" label="{{ __('Source') }}"><option value="product_card">{{ __('Product card') }}</option><option value="import">{{ __('Import') }}</option><option value="purchase_context">{{ __('Purchase context') }}</option>@can('pricing_labels.override')<option value="branch_exception">{{ __('Branch exception') }}</option>@endcan</flux:select>
                    <flux:select wire:model="form.product_id" label="{{ __('Product') }}"><option value="">{{ __('Select product') }}</option>@foreach ($products as $product)<option value="{{ $product->id }}">{{ $product->item_code }} · {{ $product->name_en }}</option>@endforeach</flux:select>
                    <flux:select wire:model="form.store_id" label="{{ __('Store') }}"><option value="">{{ __('Select store') }}</option>@foreach ($stores as $store)<option value="{{ $store->id }}">{{ $store->code }} · {{ $store->name_en }}</option>@endforeach</flux:select>
                    <flux:input wire:model="form.amount" label="{{ __('Proposed amount') }}" type="number" step="0.001" />
                    <flux:input wire:model="form.reference_amount" label="{{ __('Reference amount (optional)') }}" type="number" step="0.001" />
                    <flux:input wire:model="form.effective_from" label="{{ __('Effective from (optional)') }}" type="datetime-local" />
                    <flux:input wire:model="form.effective_to" label="{{ __('Effective to (optional)') }}" type="datetime-local" />
                    <flux:input wire:model="form.source_reference" label="{{ __('Source reference') }}" />
                    <flux:checkbox wire:model="form.open_price_allowed" label="{{ __('Allow open-price context (still requires bounds and permission)') }}" />
                    <div class="grid gap-3 sm:grid-cols-2">
                        <flux:input wire:model="form.open_price_minimum" label="{{ __('Open-price minimum') }}" type="number" step="0.0001" />
                        <flux:input wire:model="form.open_price_maximum" label="{{ __('Open-price maximum') }}" type="number" step="0.0001" />
                    </div>
                </div>
                <flux:textarea wire:model="form.reason_text" label="{{ __('Proposal reason / audit note') }}" rows="3" />
                <div class="flex justify-end gap-2"><flux:button wire:click="$set('showProposalForm', false)">{{ __('Cancel') }}</flux:button><flux:button variant="primary" wire:click="saveProposal">{{ __('Save draft') }}</flux:button></div>
            </div>
        </flux:modal>
    @endif
</div>
