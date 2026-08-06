<?php

use App\Models\User;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Purchasing\Models\FinancialSettingVersion;
use App\Modules\Purchasing\Models\SupplierReturnReason;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Supplier Return Settings')] class extends Component
{
    public string $code = '';

    public string $labelAr = '';

    public string $labelEn = '';

    public bool $isActive = true;

    public string $financialKey = 'purchasing.supplier_return.number_prefix';

    public string $financialValue = '';

    public string $effectiveFrom = '';

    public string $effectiveTo = '';

    public string $financialNotes = '';

    public function mount(): void
    {
        Gate::authorize('company_settings.view');
    }

    public function saveReason(): void
    {
        Gate::authorize('company_settings.edit');
        $this->validate(['code' => 'required|string|max:50|regex:/^[A-Z0-9_-]+$/', 'labelAr' => 'required|string|max:255', 'labelEn' => 'required|string|max:255']);
        $reason = SupplierReturnReason::query()->updateOrCreate(['code' => strtoupper($this->code)], ['label_ar' => $this->labelAr, 'label_en' => $this->labelEn, 'is_active' => $this->isActive]);
        app(RecordAuditEvent::class)->execute(category: 'settings', event: 'upsert_supplier_return_reason', source: $reason, after: $reason->only(['code', 'label_ar', 'label_en', 'is_active']), reasonCode: $reason->code);
        $this->reset(['code', 'labelAr', 'labelEn']);
        $this->isActive = true;
        $this->dispatch('supplier-return-settings-saved');
    }

    public function saveFinancialSetting(): void
    {
        Gate::authorize('company_settings.edit');

        $allowedKeys = [
            'purchasing.supplier_return.number_prefix',
            'purchasing.supplier_return.print_title',
            'purchasing.supplier_return.print_footer',
            'purchasing.supplier_return.approval_limit',
        ];

        $rules = [
            'financialKey' => ['required', 'string', 'in:'.implode(',', $allowedKeys)],
            'financialValue' => ['required', 'string', 'max:1000'],
            'effectiveFrom' => ['required', 'date'],
            'effectiveTo' => ['nullable', 'date', 'after:effectiveFrom'],
            'financialNotes' => ['nullable', 'string', 'max:2000'],
        ];

        if ($this->financialKey === 'purchasing.supplier_return.approval_limit') {
            $rules['financialValue'] = ['required', 'numeric', 'min:0', 'max:999999999999'];
        }

        $this->validate($rules);

        DB::transaction(function () use ($allowedKeys): void {
            $version = (int) FinancialSettingVersion::query()
                ->where('key', $this->financialKey)
                ->lockForUpdate()
                ->max('version') + 1;

            $setting = FinancialSettingVersion::query()->create([
                'key' => $this->financialKey,
                'value' => $this->financialValue,
                'value_type' => $this->financialKey === 'purchasing.supplier_return.approval_limit' ? 'decimal' : 'string',
                'effective_from' => $this->effectiveFrom,
                'effective_to' => $this->effectiveTo !== '' ? $this->effectiveTo : null,
                'created_by' => Auth::id(),
                'version' => $version,
                'notes' => $this->financialNotes !== '' ? $this->financialNotes : null,
            ]);

            app(RecordAuditEvent::class)->execute(
                category: 'settings',
                event: 'submit_supplier_return_financial_setting',
                source: $setting,
                after: $setting->only(['key', 'value_type', 'effective_from', 'effective_to', 'version', 'notes']),
                reasonCode: 'OWNER_SETUP_INPUT',
                metadata: ['allowed_keys' => $allowedKeys, 'approval_state' => 'pending'],
            );
        });

        $this->reset(['financialValue', 'effectiveFrom', 'effectiveTo', 'financialNotes']);
        $this->dispatch('supplier-return-settings-saved');
    }

    public function toggle(int $id): void
    {
        Gate::authorize('company_settings.edit');
        $reason = SupplierReturnReason::query()->findOrFail($id);
        $before = $reason->only(['is_active']);
        $reason->update(['is_active' => ! $reason->is_active]);
        app(RecordAuditEvent::class)->execute(category: 'settings', event: 'toggle_supplier_return_reason', source: $reason, before: $before, after: $reason->only(['is_active']), reasonCode: $reason->code);
    }

    public function render()
    {
        $versions = FinancialSettingVersion::query()->with('approvalRecord')->where(function ($q): void {
            $q->where('key', 'like', '%supplier_return%')->orWhere('key', 'like', '%purchase_return%');
        })->orderBy('key')->orderByDesc('version')->get()->groupBy('key')->map(static fn ($items) => $items->first());

        return view('purchasing.return-settings', ['reasons' => SupplierReturnReason::query()->orderBy('code')->get(), 'versions' => $versions]);
    }
};
?>

<x-app.page :title="__('Supplier Return Settings')" :description="__('Owner-configurable reasons, numbering, and approval setting visibility. No production values are invented.')" max-width="6xl" class="space-y-6">
    <x-slot:actions><flux:button href="{{ route('purchasing.returns') }}" variant="subtle" icon="arrow-left">{{ __('Supplier returns') }}</flux:button></x-slot:actions>
    <flux:callout variant="info">{{ __('Reason labels and active state are configurable. Financial limits are versioned and only take effect when an approved financial_setting_versions row exists.') }}</flux:callout>
    @can('company_settings.edit')
        <flux:card class="space-y-4">
            <flux:heading size="lg">{{ __('Add or update reason') }}</flux:heading>
            <div class="grid gap-4 md:grid-cols-3">
                <flux:input wire:model="code" :label="__('Code')" placeholder="DAMAGED" />
                <flux:input wire:model="labelAr" :label="__('Arabic label')" />
                <flux:input wire:model="labelEn" :label="__('English label')" />
            </div>
            <flux:checkbox wire:model="isActive" :label="__('Active')" />
            <flux:button wire:click="saveReason" variant="primary">{{ __('Save reason') }}</flux:button>
        </flux:card>
        <flux:card class="space-y-4">
            <flux:heading size="lg">{{ __('Submit pending financial setting') }}</flux:heading>
            <flux:text>{{ __('Owner input is saved as pending. It becomes active only after a separate approved financial version exists.') }}</flux:text>
            <div class="grid gap-4 md:grid-cols-2">
                <flux:select wire:model="financialKey" :label="__('Setting key')">
                    <flux:select.option value="purchasing.supplier_return.number_prefix">{{ __('Number prefix') }}</flux:select.option>
                    <flux:select.option value="purchasing.supplier_return.print_title">{{ __('Print title') }}</flux:select.option>
                    <flux:select.option value="purchasing.supplier_return.print_footer">{{ __('Print footer') }}</flux:select.option>
                    <flux:select.option value="purchasing.supplier_return.approval_limit">{{ __('Approval limit') }}</flux:select.option>
                </flux:select>
                <flux:input wire:model="financialValue" :label="__('Value')" />
                <flux:input type="date" wire:model="effectiveFrom" :label="__('Effective from')" />
                <flux:input type="date" wire:model="effectiveTo" :label="__('Effective to')" />
            </div>
            <flux:textarea wire:model="financialNotes" :label="__('Notes')" />
            <flux:button wire:click="saveFinancialSetting" variant="primary">{{ __('Save pending setting') }}</flux:button>
        </flux:card>
    @endcan
    <flux:card>
        <flux:heading size="lg">{{ __('Reason catalog') }}</flux:heading>
        <flux:table class="mt-4"><flux:table.columns><flux:table.column>{{ __('Code') }}</flux:table.column><flux:table.column>{{ __('Arabic') }}</flux:table.column><flux:table.column>{{ __('English') }}</flux:table.column><flux:table.column>{{ __('Status') }}</flux:table.column><flux:table.column>{{ __('Action') }}</flux:table.column></flux:table.columns><flux:table.rows>
            @forelse($reasons as $reason)<flux:table.row :key="$reason->id"><flux:table.cell>{{ $reason->code }}</flux:table.cell><flux:table.cell>{{ $reason->label_ar }}</flux:table.cell><flux:table.cell>{{ $reason->label_en }}</flux:table.cell><flux:table.cell>{{ $reason->is_active ? __('Active') : __('Inactive') }}</flux:table.cell><flux:table.cell>@can('company_settings.edit')<flux:button size="sm" variant="subtle" wire:click="toggle({{ $reason->id }})">{{ $reason->is_active ? __('Deactivate') : __('Activate') }}</flux:button>@endcan</flux:table.cell></flux:table.row>@empty<flux:table.row><flux:table.cell colspan="5">{{ __('No reasons configured yet.') }}</flux:table.cell></flux:table.row>@endforelse
        </flux:table.rows></flux:table>
    </flux:card>
    <flux:card>
        <flux:heading size="lg">{{ __('Return financial setting versions') }}</flux:heading>
        <flux:text class="mt-2">{{ __('Numeric limits remain owner-configurable through the versioned financial settings contract.') }}</flux:text>
        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead><tr class="text-start"><th class="px-3 py-2 text-start">Key</th><th class="px-3 py-2 text-start">Value type</th><th class="px-3 py-2 text-start">Version</th><th class="px-3 py-2 text-start">Effective</th><th class="px-3 py-2 text-start">{{ __('Status') }}</th></tr></thead>
                <tbody>
                    @forelse($versions as $version)
                        @php($approvalState = $version->approvalRecord?->approval_state?->value ?? 'pending')
                        <tr class="border-t">
                            <td class="px-3 py-2 font-mono">{{ $version->key }}</td>
                            <td class="px-3 py-2">{{ $version->value_type }}</td>
                            <td class="px-3 py-2">{{ $version->version }}</td>
                            <td class="px-3 py-2">{{ $version->effective_from?->toIso8601String() }}</td>
                            <td class="px-3 py-2">{{ $approvalState === 'approved' ? __('Approved') : __('Awaiting approval') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-3 py-4">{{ __('No approved return-specific financial versions exist.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </flux:card>
</x-app.page>
