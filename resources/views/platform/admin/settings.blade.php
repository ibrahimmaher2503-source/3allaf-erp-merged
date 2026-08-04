<?php

use App\Modules\Platform\Actions\SaveLocalSettingsAction;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\DocumentSequence;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Platform\Models\PrinterConfiguration;
use App\Modules\Platform\Models\TaxSetting;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('System Settings')] class extends Component {
    // Active Tab
    public string $activeTab = 'company';

    // Company Form Data
    public array $companyForm = [
        'code' => 'TBD',
        'name_ar' => '',
        'name_en' => '',
        'legal_name' => '',
        'tax_number' => '',
        'commercial_registration' => '',
        'currency_code' => 'TBD',
        'currency_symbol' => 'TBD',
        'timezone' => 'UTC',
        'locale_default' => 'ar',
        'phone' => '',
        'email' => '',
        'address' => '',
        'status' => 'active',
        'policy_notes' => 'TBD: Production currency, tax, and company legal structure pending owner decision.',
    ];

    // Payment Method Form Data
    public array $paymentMethodForm = [
        'id' => null,
        'code' => '',
        'name_ar' => '',
        'name_en' => '',
        'type' => 'manual',
        'requires_evidence' => false,
        'offline_eligible' => false,
        'status' => 'active',
        'policy_notes' => 'TBD: Payment evidence and verification rules pending technical owner approval.',
    ];

    // Tax Setting Form Data
    public array $taxSettingForm = [
        'id' => null,
        'code' => '',
        'name_ar' => '',
        'name_en' => '',
        'rate' => '',
        'is_tax_inclusive' => false,
        'tax_number' => '',
        'status' => 'active',
        'policy_notes' => 'TBD: Production tax rate and inclusive policy pending owner decision.',
    ];

    // Document Sequence Form Data
    public array $documentSequenceForm = [
        'id' => null,
        'document_type' => 'sale',
        'prefix' => 'SALE-',
        'suffix' => '',
        'padding_length' => 6,
        'next_value' => 1,
        'reset_rule' => 'never',
        'status' => 'active',
        'policy_notes' => 'TBD: Numbering patterns pending business owner approval.',
    ];

    // Printer Configuration Form Data
    public array $printerForm = [
        'id' => null,
        'name' => '',
        'printer_type' => 'thermal',
        'paper_size' => '80mm',
        'template_name' => 'default_thermal',
        'connection_type' => 'network',
        'ip_address' => '',
        'port' => 9100,
        'is_default' => false,
        'status' => 'active',
        'notes' => 'TBD: Local device and network printer baseline.',
    ];

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        Gate::authorize('manage-settings');

        $this->loadSettings();
    }

    public function updatedActiveTab(string $tab): void
    {
        if (! in_array($tab, ['company', 'payments', 'tax', 'sequences', 'printers', 'audit'], true)) {
            $this->activeTab = 'company';

            return;
        }

        if ($tab === 'audit') {
            Gate::authorize('audit_logs.view');
        }
    }

    /**
     * Load existing settings or seed default local TBD records.
     */
    public function loadSettings(): void
    {
        Gate::authorize('manage-settings');

        // Company baseline
        $company = Company::first();
        if ($company) {
            $this->companyForm = array_merge($this->companyForm, $company->toArray());
        }

        // No business defaults are seeded here. Payment, tax, numbering, and printer
        // policy must remain owner-provided; an empty state is intentional.
    }

    /**
     * Save company identity baseline.
     */
    public function saveCompany(SaveLocalSettingsAction $action): void
    {
        Gate::authorize('manage-settings');

        $validated = $this->validate([
            'companyForm.code' => ['required', 'string', 'max:20'],
            'companyForm.name_ar' => ['nullable', 'string', 'max:255'],
            'companyForm.name_en' => ['nullable', 'string', 'max:255'],
            'companyForm.legal_name' => ['nullable', 'string', 'max:255'],
            'companyForm.tax_number' => ['nullable', 'string', 'max:50'],
            'companyForm.commercial_registration' => ['nullable', 'string', 'max:50'],
            'companyForm.currency_code' => ['required', 'string', 'max:10'],
            'companyForm.currency_symbol' => ['required', 'string', 'max:10'],
            'companyForm.timezone' => ['required', 'string', 'max:50'],
            'companyForm.locale_default' => ['required', 'string', 'in:ar,en'],
            'companyForm.phone' => ['nullable', 'string', 'max:30'],
            'companyForm.email' => ['nullable', 'email', 'max:255'],
            'companyForm.address' => ['nullable', 'string', 'max:500'],
            'companyForm.status' => ['required', 'string', 'in:active,inactive'],
            'companyForm.policy_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $res = $action->execute(['company' => $validated['companyForm']]);

        $this->companyForm = array_merge($this->companyForm, $res['company']->toArray());

        Flux::toast(variant: 'success', text: __('Company settings saved successfully.'));
    }

    /**
     * Save payment method.
     */
    public function savePaymentMethod(SaveLocalSettingsAction $action): void
    {
        Gate::authorize('manage-settings');

        $validated = $this->validate([
            'paymentMethodForm.code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('payment_methods', 'code')->ignore($this->paymentMethodForm['id'] ?? null),
            ],
            'paymentMethodForm.name_ar' => ['required', 'string', 'max:255'],
            'paymentMethodForm.name_en' => ['required', 'string', 'max:255'],
            'paymentMethodForm.type' => ['required', 'string', 'in:cash,card,transfer,manual'],
            'paymentMethodForm.requires_evidence' => ['boolean'],
            'paymentMethodForm.offline_eligible' => ['boolean'],
            'paymentMethodForm.status' => ['required', 'string', 'in:active,inactive'],
            'paymentMethodForm.policy_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $action->savePaymentMethod($validated['paymentMethodForm'], $this->paymentMethodForm['id'] ?? null);

        $this->resetPaymentMethodForm();

        Flux::toast(variant: 'success', text: __('Payment method saved successfully.'));
    }

    public function editPaymentMethod(int $id): void
    {
        Gate::authorize('manage-settings');

        $method = PaymentMethod::findOrFail($id);
        $this->paymentMethodForm = $method->toArray();
    }

    public function resetPaymentMethodForm(): void
    {
        $this->paymentMethodForm = [
            'id' => null,
            'code' => '',
            'name_ar' => '',
            'name_en' => '',
            'type' => 'manual',
            'requires_evidence' => false,
            'offline_eligible' => false,
            'status' => 'active',
            'policy_notes' => 'TBD: Payment evidence rules pending technical owner approval.',
        ];
    }

    /**
     * Save tax setting.
     */
    public function saveTaxSetting(SaveLocalSettingsAction $action): void
    {
        Gate::authorize('manage-settings');

        $this->taxSettingForm['rate'] = $this->taxSettingForm['rate'] === ''
            ? null
            : $this->taxSettingForm['rate'];

        $validated = $this->validate([
            'taxSettingForm.code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('tax_settings', 'code')->ignore($this->taxSettingForm['id'] ?? null),
            ],
            'taxSettingForm.name_ar' => ['required', 'string', 'max:255'],
            'taxSettingForm.name_en' => ['required', 'string', 'max:255'],
            'taxSettingForm.rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'taxSettingForm.is_tax_inclusive' => ['boolean'],
            'taxSettingForm.tax_number' => ['nullable', 'string', 'max:50'],
            'taxSettingForm.status' => ['required', 'string', 'in:active,inactive'],
            'taxSettingForm.policy_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $action->saveTaxSetting($validated['taxSettingForm'], $this->taxSettingForm['id'] ?? null);

        $this->resetTaxSettingForm();

        Flux::toast(variant: 'success', text: __('Tax setting saved successfully.'));
    }

    public function editTaxSetting(int $id): void
    {
        Gate::authorize('manage-settings');

        $tax = TaxSetting::findOrFail($id);
        $this->taxSettingForm = $tax->toArray();
    }

    public function resetTaxSettingForm(): void
    {
        $this->taxSettingForm = [
            'id' => null,
            'code' => '',
            'name_ar' => '',
            'name_en' => '',
            'rate' => '',
            'is_tax_inclusive' => false,
            'tax_number' => '',
            'status' => 'active',
            'policy_notes' => 'TBD: Production tax rate pending owner decision.',
        ];
    }

    /**
     * Save document sequence.
     */
    public function saveDocumentSequence(SaveLocalSettingsAction $action): void
    {
        Gate::authorize('manage-settings');

        $validated = $this->validate([
            'documentSequenceForm.document_type' => [
                'required',
                'string',
                'max:50',
                Rule::unique('document_sequences', 'document_type')->ignore($this->documentSequenceForm['id'] ?? null),
            ],
            'documentSequenceForm.prefix' => ['nullable', 'string', 'max:20'],
            'documentSequenceForm.suffix' => ['nullable', 'string', 'max:20'],
            'documentSequenceForm.padding_length' => ['required', 'integer', 'min:1', 'max:12'],
            'documentSequenceForm.next_value' => ['required', 'integer', 'min:1'],
            'documentSequenceForm.reset_rule' => ['required', 'string', 'in:never,yearly,monthly'],
            'documentSequenceForm.status' => ['required', 'string', 'in:active,inactive'],
            'documentSequenceForm.policy_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $action->saveDocumentSequence($validated['documentSequenceForm'], $this->documentSequenceForm['id'] ?? null);

        $this->resetDocumentSequenceForm();

        Flux::toast(variant: 'success', text: __('Document sequence saved successfully.'));
    }

    public function editDocumentSequence(int $id): void
    {
        Gate::authorize('manage-settings');

        $seq = DocumentSequence::findOrFail($id);
        $this->documentSequenceForm = $seq->toArray();
    }

    public function resetDocumentSequenceForm(): void
    {
        $this->documentSequenceForm = [
            'id' => null,
            'document_type' => 'sale',
            'prefix' => 'SALE-',
            'suffix' => '',
            'padding_length' => 6,
            'next_value' => 1,
            'reset_rule' => 'never',
            'status' => 'active',
            'policy_notes' => 'TBD: Sequence rules pending owner approval.',
        ];
    }

    /**
     * Save printer configuration.
     */
    public function savePrinter(SaveLocalSettingsAction $action): void
    {
        Gate::authorize('manage-settings');

        $this->printerForm['port'] = $this->printerForm['port'] === ''
            ? null
            : $this->printerForm['port'];

        $validated = $this->validate([
            'printerForm.name' => ['required', 'string', 'max:255'],
            'printerForm.printer_type' => ['required', 'string', 'in:thermal,a4,label,pdf'],
            'printerForm.paper_size' => ['required', 'string', 'in:80mm,58mm,a4,label'],
            'printerForm.template_name' => ['required', 'string', 'max:100'],
            'printerForm.connection_type' => ['required', 'string', 'in:network,usb,bluetooth,browser'],
            'printerForm.ip_address' => ['nullable', 'string', 'max:50'],
            'printerForm.port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'printerForm.is_default' => ['boolean'],
            'printerForm.status' => ['required', 'string', 'in:active,inactive'],
            'printerForm.notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $action->savePrinterConfiguration($validated['printerForm'], $this->printerForm['id'] ?? null);

        $this->resetPrinterForm();

        Flux::toast(variant: 'success', text: __('Printer configuration saved successfully.'));
    }

    public function editPrinter(int $id): void
    {
        Gate::authorize('manage-settings');

        $printer = PrinterConfiguration::findOrFail($id);
        $this->printerForm = $printer->toArray();
    }

    public function resetPrinterForm(): void
    {
        $this->printerForm = [
            'id' => null,
            'name' => '',
            'printer_type' => 'thermal',
            'paper_size' => '80mm',
            'template_name' => 'default_thermal',
            'connection_type' => 'network',
            'ip_address' => '',
            'port' => 9100,
            'is_default' => false,
            'status' => 'active',
            'notes' => 'TBD: Printer configuration baseline.',
        ];
    }
}; ?>

<section class="w-full space-y-6" data-guide="settings-header">
    <x-page-header
        :title="__('System Settings Baseline (TSK-005)')"
        :description="__('Local company identity, payment methods, tax rules, document sequence counters, printer profiles, and append-only settings audit trail.')"
    >
        <x-slot:actions>
            <flux:badge size="sm" color="amber" icon="exclamation-triangle">
                {{ __('Local TBD Policy Baseline') }}
            </flux:badge>
        </x-slot:actions>
    </x-page-header>

    <flux:callout variant="warning" icon="information-circle" title="{{ __('Unapproved Business Policy Guard') }}">
        {{ __('All unapproved tax rates, payment rules, numbering policies, currency settings, and printer templates carry explicit local TBD values. Production policy decisions remain blocked until owner sign-off.') }}
    </flux:callout>

    @if ($errors->any())
        <flux:callout variant="danger" icon="exclamation-triangle" title="{{ __('Validation Errors') }}">
            <p class="text-sm font-medium">{{ __('Please review and correct the following validation errors:') }}</p>
            <ul class="mt-2 list-disc list-inside space-y-1 text-sm">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </flux:callout>
    @endif

    <!-- Navigation Tabs -->
    <div class="border-b border-zinc-200 dark:border-zinc-700">
        <nav class="-mb-px flex gap-6 overflow-x-auto text-sm font-medium" role="tablist" aria-label="{{ __('Settings Sections') }}" data-guide="settings-tabs">
            <button
                type="button"
                id="tab-company"
                role="tab"
                aria-selected="{{ $activeTab === 'company' ? 'true' : 'false' }}"
                aria-controls="panel-company"
                wire:click="$set('activeTab', 'company')"
                class="pb-3 border-b-2 transition-colors cursor-pointer {{ $activeTab === 'company' ? 'border-teal-600 text-teal-600 dark:border-teal-400 dark:text-teal-400 font-semibold' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400' }}"
            >
                {{ __('Company Identity') }}
            </button>
            <button
                type="button"
                id="tab-payments"
                role="tab"
                aria-selected="{{ $activeTab === 'payments' ? 'true' : 'false' }}"
                aria-controls="panel-payments"
                wire:click="$set('activeTab', 'payments')"
                class="pb-3 border-b-2 transition-colors cursor-pointer {{ $activeTab === 'payments' ? 'border-teal-600 text-teal-600 dark:border-teal-400 dark:text-teal-400 font-semibold' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400' }}"
            >
                {{ __('Payment Methods') }}
            </button>
            <button
                type="button"
                id="tab-tax"
                role="tab"
                aria-selected="{{ $activeTab === 'tax' ? 'true' : 'false' }}"
                aria-controls="panel-tax"
                wire:click="$set('activeTab', 'tax')"
                class="pb-3 border-b-2 transition-colors cursor-pointer {{ $activeTab === 'tax' ? 'border-teal-600 text-teal-600 dark:border-teal-400 dark:text-teal-400 font-semibold' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400' }}"
            >
                {{ __('Tax Settings') }}
            </button>
            <button
                type="button"
                id="tab-sequences"
                role="tab"
                aria-selected="{{ $activeTab === 'sequences' ? 'true' : 'false' }}"
                aria-controls="panel-sequences"
                wire:click="$set('activeTab', 'sequences')"
                class="pb-3 border-b-2 transition-colors cursor-pointer {{ $activeTab === 'sequences' ? 'border-teal-600 text-teal-600 dark:border-teal-400 dark:text-teal-400 font-semibold' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400' }}"
            >
                {{ __('Document Sequences') }}
            </button>
            <button
                type="button"
                id="tab-printers"
                role="tab"
                aria-selected="{{ $activeTab === 'printers' ? 'true' : 'false' }}"
                aria-controls="panel-printers"
                wire:click="$set('activeTab', 'printers')"
                class="pb-3 border-b-2 transition-colors cursor-pointer {{ $activeTab === 'printers' ? 'border-teal-600 text-teal-600 dark:border-teal-400 dark:text-teal-400 font-semibold' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400' }}"
            >
                {{ __('Printers & Templates') }}
            </button>
            <button
                type="button"
                id="tab-audit"
                role="tab"
                aria-selected="{{ $activeTab === 'audit' ? 'true' : 'false' }}"
                aria-controls="panel-audit"
                wire:click="$set('activeTab', 'audit')"
                class="pb-3 border-b-2 transition-colors cursor-pointer {{ $activeTab === 'audit' ? 'border-teal-600 text-teal-600 dark:border-teal-400 dark:text-teal-400 font-semibold' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400' }}"
            >
                {{ __('Settings Audit Trail') }}
            </button>
        </nav>
    </div>

    <!-- TAB 1: Company Identity -->
    @if ($activeTab === 'company')
        <div id="panel-company" role="tabpanel" aria-labelledby="tab-company" class="space-y-6">
            <form wire:submit="saveCompany" class="space-y-6">
                <flux:card class="space-y-4" data-guide="settings-company-card">
                    <flux:heading size="lg">{{ __('Company Master Information') }}</flux:heading>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <flux:input
                            wire:model="companyForm.code"
                            :label="__('Company Code')"
                            required
                        />

                        <flux:input
                            wire:model="companyForm.legal_name"
                            :label="__('Legal Name')"
                            placeholder="TOY & JOY Commercial Co."
                        />

                        <flux:input
                            wire:model="companyForm.name_ar"
                            :label="__('Name (Arabic)')"
                            placeholder="شركة لعبة وفرحة"
                        />

                        <flux:input
                            wire:model="companyForm.name_en"
                            :label="__('Name (English)')"
                            placeholder="TOY & JOY Company"
                        />

                        <flux:input
                            wire:model="companyForm.tax_number"
                            :label="__('Tax Identification Number (TIN)')"
                            placeholder="300000000000003"
                        />

                        <flux:input
                            wire:model="companyForm.commercial_registration"
                            :label="__('Commercial Registration (CR)')"
                            placeholder="1010000000"
                        />
                    </div>
                </flux:card>

                <flux:card class="space-y-4" data-guide="settings-localization-card">
                    <flux:heading size="lg">{{ __('Localization & Currency Policy Baseline') }}</flux:heading>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <flux:input
                            wire:model="companyForm.currency_code"
                            :label="__('Currency Code (TBD)')"
                            required
                        />

                        <flux:input
                            wire:model="companyForm.currency_symbol"
                            :label="__('Currency Symbol (TBD)')"
                            required
                        />

                        <flux:select wire:model="companyForm.timezone" :label="__('Timezone')">
                            <option value="UTC">UTC (Coordinated Universal Time)</option>
                            <option value="Asia/Riyadh">Asia/Riyadh (GMT+3)</option>
                        </flux:select>

                        <flux:select wire:model="companyForm.locale_default" :label="__('Default Application Locale')">
                            <option value="ar">العربية (Arabic - RTL)</option>
                            <option value="en">English (LTR)</option>
                        </flux:select>

                        <flux:input
                            wire:model="companyForm.phone"
                            :label="__('Contact Phone')"
                        />

                        <flux:input
                            wire:model="companyForm.email"
                            :label="__('Contact Email')"
                            type="email"
                        />
                    </div>

                    <flux:input
                        wire:model="companyForm.address"
                        :label="__('Address')"
                    />

                    <flux:input
                        wire:model="companyForm.policy_notes"
                        :label="__('Policy & Baseline Notes')"
                    />

                    <div class="flex justify-end">
                        <flux:button type="submit" variant="primary" icon="check" data-guide="settings-save-button">
                            {{ __('Save Company Baseline') }}
                        </flux:button>
                    </div>
                </flux:card>
            </form>
        </div>
    @endif

    <!-- TAB 2: Payment Methods -->
    @if ($activeTab === 'payments')
        <div id="panel-payments" role="tabpanel" aria-labelledby="tab-payments" class="space-y-6">
            <flux:card class="space-y-4">
                <flux:heading size="lg">
                    {{ $paymentMethodForm['id'] ? __('Edit Payment Method') : __('Add New Payment Method Baseline') }}
                </flux:heading>

                <form wire:submit="savePaymentMethod" class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <flux:input
                            wire:model="paymentMethodForm.code"
                            :label="__('Method Code')"
                            placeholder="CASH / CARD / MADA"
                            required
                        />

                        <flux:input
                            wire:model="paymentMethodForm.name_ar"
                            :label="__('Name (Arabic)')"
                            required
                        />

                        <flux:input
                            wire:model="paymentMethodForm.name_en"
                            :label="__('Name (English)')"
                            required
                        />

                        <flux:select wire:model="paymentMethodForm.type" :label="__('Method Type')">
                            <option value="cash">{{ __('Cash') }}</option>
                            <option value="card">{{ __('Card / POS') }}</option>
                            <option value="transfer">{{ __('Bank Transfer') }}</option>
                            <option value="manual">{{ __('Manual / Other') }}</option>
                        </flux:select>

                        <flux:select wire:model="paymentMethodForm.status" :label="__('Status')">
                            <option value="active">{{ __('Active') }}</option>
                            <option value="inactive">{{ __('Inactive') }}</option>
                        </flux:select>
                    </div>

                    <div class="flex items-center gap-6">
                        <flux:switch
                            wire:model="paymentMethodForm.requires_evidence"
                            :label="__('Requires Verification Evidence')"
                        />

                        <flux:switch
                            wire:model="paymentMethodForm.offline_eligible"
                            :label="__('Eligible for Restricted Offline POS')"
                        />
                    </div>

                    <flux:input
                        wire:model="paymentMethodForm.policy_notes"
                        :label="__('Method Policy Notes (TBD)')"
                    />

                    <div class="flex items-center justify-end gap-3">
                        @if ($paymentMethodForm['id'])
                            <flux:button type="button" wire:click="resetPaymentMethodForm" variant="subtle">
                                {{ __('Cancel Edit') }}
                            </flux:button>
                        @endif

                        <flux:button type="submit" variant="primary">
                            {{ $paymentMethodForm['id'] ? __('Update Method') : __('Save Method') }}
                        </flux:button>
                    </div>
                </form>
            </flux:card>

            <flux:card class="space-y-4">
                <flux:heading size="lg">{{ __('Configured Payment Methods') }}</flux:heading>

                <flux:table aria-label="{{ __('Configured Payment Methods') }}">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Code') }}</flux:table.column>
                        <flux:table.column>{{ __('Name (AR/EN)') }}</flux:table.column>
                        <flux:table.column>{{ __('Type') }}</flux:table.column>
                        <flux:table.column>{{ __('Evidence Required') }}</flux:table.column>
                        <flux:table.column>{{ __('Offline Eligible') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                        <flux:table.column>{{ __('Actions') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse (PaymentMethod::all() as $method)
                            <flux:table.row key="method-{{ $method->id }}">
                                <flux:table.cell class="font-mono font-bold">{{ $method->code }}</flux:table.cell>
                                <flux:table.cell>
                                    <div>{{ $method->name_ar }}</div>
                                    <div class="text-xs text-zinc-500">{{ $method->name_en }}</div>
                                </flux:table.cell>
                                <flux:table.cell><flux:badge size="sm" color="zinc">{{ strtoupper($method->type) }}</flux:badge></flux:table.cell>
                                <flux:table.cell>
                                    @if ($method->requires_evidence)
                                        <flux:badge size="sm" color="blue">{{ __('Yes') }}</flux:badge>
                                    @else
                                        <flux:badge size="sm" color="zinc">{{ __('No') }}</flux:badge>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if ($method->offline_eligible)
                                        <flux:badge size="sm" color="green">{{ __('Yes') }}</flux:badge>
                                    @else
                                        <flux:badge size="sm" color="zinc">{{ __('No') }}</flux:badge>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if ($method->status === 'active')
                                        <flux:badge size="sm" color="green">{{ __('Active') }}</flux:badge>
                                    @else
                                        <flux:badge size="sm" color="red">{{ __('Inactive') }}</flux:badge>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:button size="xs" variant="subtle" wire:click="editPaymentMethod({{ $method->id }})">
                                        {{ __('Edit') }}
                                    </flux:button>
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="7" class="text-center py-4">
                                    <x-state.empty
                                        :title="__('No Payment Methods Configured')"
                                        :description="__('No payment methods exist in the system baseline yet. Use the form above to add a method.')"
                                        icon="credit-card"
                                    />
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </flux:card>
        </div>
    @endif

    <!-- TAB 3: Tax Settings -->
    @if ($activeTab === 'tax')
        <div id="panel-tax" role="tabpanel" aria-labelledby="tab-tax" class="space-y-6">
            <flux:card class="space-y-4">
                <flux:heading size="lg">
                    {{ $taxSettingForm['id'] ? __('Edit Tax Rule') : __('Add Tax Rule Baseline (TBD)') }}
                </flux:heading>

                <form wire:submit="saveTaxSetting" class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <flux:input
                            wire:model="taxSettingForm.code"
                            :label="__('Tax Rule Code')"
                            placeholder="VAT15 / EXCISE"
                            required
                        />

                        <flux:input
                            wire:model="taxSettingForm.name_ar"
                            :label="__('Name (Arabic)')"
                            required
                        />

                        <flux:input
                            wire:model="taxSettingForm.name_en"
                            :label="__('Name (English)')"
                            required
                        />

                        <flux:input
                            wire:model="taxSettingForm.rate"
                            :label="__('Tax Rate % (Nullable if TBD)')"
                            placeholder="15.00"
                            type="number"
                            step="0.01"
                        />

                        <flux:input
                            wire:model="taxSettingForm.tax_number"
                            :label="__('Specific Tax Reg No.')"
                        />

                        <flux:select wire:model="taxSettingForm.status" :label="__('Status')">
                            <option value="active">{{ __('Active') }}</option>
                            <option value="inactive">{{ __('Inactive') }}</option>
                        </flux:select>
                    </div>

                    <flux:switch
                        wire:model="taxSettingForm.is_tax_inclusive"
                        :label="__('Prices Are Tax Inclusive')"
                    />

                    <flux:input
                        wire:model="taxSettingForm.policy_notes"
                        :label="__('Tax Policy Notes (TBD)')"
                    />

                    <div class="flex items-center justify-end gap-3">
                        @if ($taxSettingForm['id'])
                            <flux:button type="button" wire:click="resetTaxSettingForm" variant="subtle">
                                {{ __('Cancel Edit') }}
                            </flux:button>
                        @endif

                        <flux:button type="submit" variant="primary">
                            {{ $taxSettingForm['id'] ? __('Update Tax Setting') : __('Save Tax Setting') }}
                        </flux:button>
                    </div>
                </form>
            </flux:card>

            <flux:card class="space-y-4">
                <flux:heading size="lg">{{ __('Configured Tax Settings') }}</flux:heading>

                <flux:table aria-label="{{ __('Configured Tax Settings') }}">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Code') }}</flux:table.column>
                        <flux:table.column>{{ __('Name (AR/EN)') }}</flux:table.column>
                        <flux:table.column>{{ __('Rate %') }}</flux:table.column>
                        <flux:table.column>{{ __('Inclusive') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                        <flux:table.column>{{ __('Actions') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse (TaxSetting::all() as $tax)
                            <flux:table.row key="tax-{{ $tax->id }}">
                                <flux:table.cell class="font-mono font-bold">{{ $tax->code }}</flux:table.cell>
                                <flux:table.cell>
                                    <div>{{ $tax->name_ar }}</div>
                                    <div class="text-xs text-zinc-500">{{ $tax->name_en }}</div>
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if ($tax->rate !== null)
                                        <span class="font-mono font-semibold">{{ number_format((float)$tax->rate, 2) }}%</span>
                                    @else
                                        <flux:badge size="sm" color="amber">{{ __('TBD') }}</flux:badge>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if ($tax->is_tax_inclusive)
                                        <flux:badge size="sm" color="blue">{{ __('Inclusive') }}</flux:badge>
                                    @else
                                        <flux:badge size="sm" color="zinc">{{ __('Exclusive') }}</flux:badge>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if ($tax->status === 'active')
                                        <flux:badge size="sm" color="green">{{ __('Active') }}</flux:badge>
                                    @else
                                        <flux:badge size="sm" color="red">{{ __('Inactive') }}</flux:badge>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:button size="xs" variant="subtle" wire:click="editTaxSetting({{ $tax->id }})">
                                        {{ __('Edit') }}
                                    </flux:button>
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="6" class="text-center py-4">
                                    <x-state.empty
                                        :title="__('No Tax Rules Configured')"
                                        :description="__('No tax settings exist in the system baseline yet. Use the form above to add a tax rule.')"
                                        icon="receipt-percent"
                                    />
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </flux:card>
        </div>
    @endif

    <!-- TAB 4: Document Sequences -->
    @if ($activeTab === 'sequences')
        <div id="panel-sequences" role="tabpanel" aria-labelledby="tab-sequences" class="space-y-6">
            <flux:card class="space-y-4">
                <flux:heading size="lg">
                    {{ $documentSequenceForm['id'] ? __('Edit Document Sequence') : __('Add Document Sequence') }}
                </flux:heading>

                <form wire:submit="saveDocumentSequence" class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <flux:input
                            wire:model="documentSequenceForm.document_type"
                            :label="__('Document Type')"
                            placeholder="sale / purchase_order / party_invoice"
                            required
                        />

                        <flux:input
                            wire:model="documentSequenceForm.prefix"
                            :label="__('Prefix')"
                            placeholder="SALE- / INV-"
                        />

                        <flux:input
                            wire:model="documentSequenceForm.suffix"
                            :label="__('Suffix')"
                        />

                        <flux:input
                            wire:model="documentSequenceForm.padding_length"
                            :label="__('Padding Length')"
                            type="number"
                            required
                        />

                        <flux:input
                            wire:model="documentSequenceForm.next_value"
                            :label="__('Next Value')"
                            type="number"
                            required
                        />

                        <flux:select wire:model="documentSequenceForm.reset_rule" :label="__('Reset Rule')">
                            <option value="never">{{ __('Never (Continuous)') }}</option>
                            <option value="yearly">{{ __('Yearly') }}</option>
                            <option value="monthly">{{ __('Monthly') }}</option>
                        </flux:select>
                    </div>

                    <flux:input
                        wire:model="documentSequenceForm.policy_notes"
                        :label="__('Sequence Policy Notes (TBD)')"
                    />

                    <div class="flex items-center justify-end gap-3">
                        @if ($documentSequenceForm['id'])
                            <flux:button type="button" wire:click="resetDocumentSequenceForm" variant="subtle">
                                {{ __('Cancel Edit') }}
                            </flux:button>
                        @endif

                        <flux:button type="submit" variant="primary">
                            {{ $documentSequenceForm['id'] ? __('Update Sequence') : __('Save Sequence') }}
                        </flux:button>
                    </div>
                </form>
            </flux:card>

            <flux:card class="space-y-4">
                <flux:heading size="lg">{{ __('Configured Document Sequences') }}</flux:heading>

                <flux:table aria-label="{{ __('Configured Document Sequences') }}">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Type') }}</flux:table.column>
                        <flux:table.column>{{ __('Pattern Example') }}</flux:table.column>
                        <flux:table.column>{{ __('Next Value') }}</flux:table.column>
                        <flux:table.column>{{ __('Reset Rule') }}</flux:table.column>
                        <flux:table.column>{{ __('Lock Version') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                        <flux:table.column>{{ __('Actions') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse (DocumentSequence::all() as $seq)
                            <flux:table.row key="seq-{{ $seq->id }}">
                                <flux:table.cell class="font-mono font-bold">{{ $seq->document_type }}</flux:table.cell>
                                <flux:table.cell class="font-mono text-xs text-teal-600 dark:text-teal-400">
                                    {{ $seq->prefix }}{{ str_pad((string)$seq->next_value, $seq->padding_length, '0', STR_PAD_LEFT) }}{{ $seq->suffix }}
                                </flux:table.cell>
                                <flux:table.cell class="font-mono font-semibold">{{ $seq->next_value }}</flux:table.cell>
                                <flux:table.cell><flux:badge size="sm" color="zinc">{{ strtoupper($seq->reset_rule) }}</flux:badge></flux:table.cell>
                                <flux:table.cell class="font-mono text-xs">v{{ $seq->lock_version }}</flux:table.cell>
                                <flux:table.cell>
                                    @if ($seq->status === 'active')
                                        <flux:badge size="sm" color="green">{{ __('Active') }}</flux:badge>
                                    @else
                                        <flux:badge size="sm" color="red">{{ __('Inactive') }}</flux:badge>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:button size="xs" variant="subtle" wire:click="editDocumentSequence({{ $seq->id }})">
                                        {{ __('Edit') }}
                                    </flux:button>
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="7" class="text-center py-4">
                                    <x-state.empty
                                        :title="__('No Document Sequences Configured')"
                                        :description="__('No document numbering sequences exist in the system baseline yet. Use the form above to add a sequence.')"
                                        icon="numbered-list"
                                    />
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </flux:card>
        </div>
    @endif

    <!-- TAB 5: Printer Configurations -->
    @if ($activeTab === 'printers')
        <div id="panel-printers" role="tabpanel" aria-labelledby="tab-printers" class="space-y-6">
            <flux:card class="space-y-4">
                <flux:heading size="lg">
                    {{ $printerForm['id'] ? __('Edit Printer Configuration') : __('Add Printer Configuration') }}
                </flux:heading>

                <form wire:submit="savePrinter" class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <flux:input
                            wire:model="printerForm.name"
                            :label="__('Printer Name')"
                            placeholder="Cashier Thermal Printer 1"
                            required
                        />

                        <flux:select wire:model="printerForm.printer_type" :label="__('Printer Type')">
                            <option value="thermal">{{ __('Thermal Receipt') }}</option>
                            <option value="a4">{{ __('A4 Document') }}</option>
                            <option value="label">{{ __('Barcode / Label') }}</option>
                            <option value="pdf">{{ __('PDF Virtual') }}</option>
                        </flux:select>

                        <flux:select wire:model="printerForm.paper_size" :label="__('Paper Size')">
                            <option value="80mm">80mm Thermal</option>
                            <option value="58mm">58mm Thermal</option>
                            <option value="a4">A4 Sheet</option>
                            <option value="label">Standard Label</option>
                        </flux:select>

                        <flux:input
                            wire:model="printerForm.template_name"
                            :label="__('Template Name (TBD)')"
                            placeholder="default_thermal"
                            required
                        />

                        <flux:select wire:model="printerForm.connection_type" :label="__('Connection Type')">
                            <option value="network">{{ __('Network / IP') }}</option>
                            <option value="usb">{{ __('USB Local') }}</option>
                            <option value="bluetooth">{{ __('Bluetooth') }}</option>
                            <option value="browser">{{ __('Browser Print') }}</option>
                        </flux:select>

                        <flux:input
                            wire:model="printerForm.ip_address"
                            :label="__('IP Address (Network)')"
                            placeholder="192.168.1.100"
                        />
                    </div>

                    <flux:switch
                        wire:model="printerForm.is_default"
                        :label="__('Default Printer for Device Profile')"
                    />

                    <flux:input
                        wire:model="printerForm.notes"
                        :label="__('Printer Notes (TBD)')"
                    />

                    <div class="flex items-center justify-end gap-3">
                        @if ($printerForm['id'])
                            <flux:button type="button" wire:click="resetPrinterForm" variant="subtle">
                                {{ __('Cancel Edit') }}
                            </flux:button>
                        @endif

                        <flux:button type="submit" variant="primary">
                            {{ $printerForm['id'] ? __('Update Printer') : __('Save Printer') }}
                        </flux:button>
                    </div>
                </form>
            </flux:card>

            <flux:card class="space-y-4">
                <flux:heading size="lg">{{ __('Configured Printer Profiles') }}</flux:heading>

                <flux:table aria-label="{{ __('Configured Printer Profiles') }}">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Name') }}</flux:table.column>
                        <flux:table.column>{{ __('Type') }}</flux:table.column>
                        <flux:table.column>{{ __('Paper Size') }}</flux:table.column>
                        <flux:table.column>{{ __('Connection') }}</flux:table.column>
                        <flux:table.column>{{ __('Default') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                        <flux:table.column>{{ __('Actions') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse (PrinterConfiguration::all() as $printer)
                            <flux:table.row key="printer-{{ $printer->id }}">
                                <flux:table.cell class="font-medium">{{ $printer->name }}</flux:table.cell>
                                <flux:table.cell><flux:badge size="sm" color="zinc">{{ strtoupper($printer->printer_type) }}</flux:badge></flux:table.cell>
                                <flux:table.cell class="font-mono text-xs">{{ $printer->paper_size }}</flux:table.cell>
                                <flux:table.cell class="font-mono text-xs">{{ $printer->connection_type }} ({{ $printer->ip_address ?? 'Local' }})</flux:table.cell>
                                <flux:table.cell>
                                    @if ($printer->is_default)
                                        <flux:badge size="sm" color="green">{{ __('Default') }}</flux:badge>
                                    @else
                                        <flux:badge size="sm" color="zinc">{{ __('No') }}</flux:badge>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if ($printer->status === 'active')
                                        <flux:badge size="sm" color="green">{{ __('Active') }}</flux:badge>
                                    @else
                                        <flux:badge size="sm" color="red">{{ __('Inactive') }}</flux:badge>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:button size="xs" variant="subtle" wire:click="editPrinter({{ $printer->id }})">
                                        {{ __('Edit') }}
                                    </flux:button>
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="7" class="text-center py-4">
                                    <x-state.empty
                                        :title="__('No Printer Profiles Configured')"
                                        :description="__('No printer profiles exist in the system baseline yet. Use the form above to add a printer profile.')"
                                        icon="printer"
                                    />
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </flux:card>
        </div>
    @endif

    <!-- TAB 6: Settings Audit Trail -->
    @if ($activeTab === 'audit')
        <div id="panel-audit" role="tabpanel" aria-labelledby="tab-audit" class="space-y-6">
            <flux:card class="space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <flux:heading size="lg">{{ __('Settings Audit Trail') }}</flux:heading>
                        <flux:subheading>{{ __('Append-only unified audit events for current platform settings.') }}</flux:subheading>
                    </div>
                    <flux:badge size="sm" color="zinc" icon="shield-check">
                        {{ __('Append-Only') }}
                    </flux:badge>
                </div>

                <flux:table aria-label="{{ __('Settings Audit Trail') }}">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Timestamp') }}</flux:table.column>
                        <flux:table.column>{{ __('Correlation ID') }}</flux:table.column>
                        <flux:table.column>{{ __('User') }}</flux:table.column>
                        <flux:table.column>{{ __('Action') }}</flux:table.column>
                        <flux:table.column>{{ __('Source') }}</flux:table.column>
                        <flux:table.column>{{ __('Changes Summary') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @php
                            $settingSourceTypes = [Company::class, PaymentMethod::class, TaxSetting::class, DocumentSequence::class, PrinterConfiguration::class];
                            $settingAuditLogs = AuditLog::query()
                                ->where('category', 'master_data')
                                ->where(function ($query) use ($settingSourceTypes): void {
                                    $query->whereIn('source_type', $settingSourceTypes)
                                        ->orWhere('source_type', 'like', 'legacy_settings:%');
                                })
                                ->latest('id')
                                ->take(20)
                                ->get();
                        @endphp
                        @forelse ($settingAuditLogs as $log)
                            <flux:table.row key="log-{{ $log->id }}">
                                <flux:table.cell class="font-mono text-xs">{{ $log->created_at->format('Y-m-d H:i:s') }}</flux:table.cell>
                                <flux:table.cell class="font-mono text-xs text-blue-600 dark:text-blue-400">{{ $log->request_id }}</flux:table.cell>
                                <flux:table.cell class="font-medium text-xs">{{ $log->actor_name ?? __('System') }}</flux:table.cell>
                                <flux:table.cell><flux:badge size="sm" color="teal">{{ $log->event }}</flux:badge></flux:table.cell>
                                <flux:table.cell class="font-mono text-xs">{{ class_basename((string) $log->source_type) }}</flux:table.cell>
                                <flux:table.cell class="font-mono text-xs max-w-xs truncate" title="{{ json_encode($log->after_values) }}">
                                    {{ json_encode($log->after_values) }}
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="6" class="text-center py-4">
                                    <x-state.empty
                                        :title="__('No Audit Logs Recorded')"
                                        :description="__('No settings modifications have been recorded yet. Successful updates generate append-only audit entries here.')"
                                        icon="shield-check"
                                    />
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </flux:card>
        </div>
    @endif
</section>
