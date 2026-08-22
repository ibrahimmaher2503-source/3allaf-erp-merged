<?php

use App\Modules\Platform\Actions\PlatformSettingsApprovalAction;
use App\Modules\Platform\Actions\SaveLocalSettingsAction;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\DocumentSequence;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Platform\Models\PrinterConfiguration;
use App\Modules\Customer\Support\PhoneNormalizer;
use App\Modules\Platform\Models\TaxSetting;
use Flux\Flux;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('System Settings')] class extends Component
{
    // Active Tab
    #[Url(as: 'tab', except: 'company')]
    public string $activeTab = 'company';

    #[Url(as: 'section', except: 'printer-profiles')]
    public string $printerSection = 'printer-profiles';

    public bool $showCompanyPreview = false;

    public ?int $companyId = null;

    public bool $companyDirty = false;

    public bool $companyEditingBlocked = false;

    // Company Form Data
    public array $companyForm = [
        'code' => '',
        'name_ar' => '',
        'name_en' => '',
        'legal_name' => '',
        'tax_number' => '',
        'commercial_registration' => '',
        'currency_code' => '',
        'currency_symbol' => '',
        'timezone' => 'Africa/Cairo',
        'locale_default' => 'ar',
        'phone' => '',
        'email' => '',
        'address' => '',
        'status' => 'active',
        'policy_notes' => '',
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
        'policy_notes' => '',
    ];

    // Tax Setting Form Data
    public array $taxSettingForm = [
        'id' => null,
        'code' => '',
        'name_ar' => '',
        'name_en' => '',
        'rate' => '',
        'treatment' => 'standard',
        'is_default' => false,
        'is_tax_inclusive' => true,
        'tax_number' => '',
        'effective_from' => '',
        'effective_to' => '',
        'status' => 'active',
        'policy_notes' => '',
    ];

    // Document Sequence Form Data
    public array $documentSequenceForm = [
        'id' => null,
        'document_type' => 'sale',
        'scope_type' => 'company',
        'scope_id' => null,
        'prefix' => 'SALE-',
        'suffix' => '',
        'padding_length' => 6,
        'next_value' => 1,
        'reset_rule' => 'never',
        'status' => 'active',
        'policy_notes' => '',
    ];

    public array $sequenceOverride = [
        'sequence_id' => null,
        'next_value' => null,
        'expected_lock_version' => null,
        'reason' => '',
    ];

    // Printer Configuration Form Data
    public array $printerForm = [
        'id' => null,
        'name' => '',
        'scope_type' => 'global',
        'branch_id' => null,
        'store_id' => null,
        'printer_type' => 'thermal',
        'paper_size' => '80mm',
        'template_name' => 'default_thermal',
        'connection_type' => 'network',
        'ip_address' => '',
        'port' => 9100,
        'is_default' => false,
        'status' => 'active',
        'notes' => '',
    ];

    /**
     * Mount the component.
     */
    public function mount(Request $request): void
    {
        Gate::authorize('manage-settings');

        $tab = (string) $request->query('tab', 'company');
        if (in_array($tab, ['company', 'payments', 'tax', 'sequences', 'printers', 'audit'], true)) {
            $this->activeTab = $tab;
        }

        $section = (string) $request->query('section', 'printer-profiles');
        if ($this->activeTab === 'printers' && in_array($section, ['printer-profiles', 'print-templates'], true)) {
            $this->printerSection = $section;
        }

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

    public function rendering(): void
    {
        // URL hydration can run after mount; normalize it before any panel renders.
        if (! in_array($this->activeTab, ['company', 'payments', 'tax', 'sequences', 'printers', 'audit'], true)) {
            $this->activeTab = 'company';
        }

        if (! in_array($this->printerSection, ['printer-profiles', 'print-templates'], true)) {
            $this->printerSection = 'printer-profiles';
        }

        if ($this->activeTab === 'audit') {
            Gate::authorize('audit_logs.view');
        }
    }

    /**
     * Load the current settings.
     */
    public function loadSettings(): void
    {
        Gate::authorize('manage-settings');

        // Company baseline
        $companies = Company::query()->orderBy('id')->limit(2)->get();
        if ($companies->count() > 1) {
            $this->companyEditingBlocked = true;

            return;
        }

        $company = $companies->first();
        if ($company) {
            $this->companyId = $company->id;
            $this->companyForm = array_merge($this->companyForm, $company->toArray());
            $this->companyForm['timezone'] = $this->companyForm['timezone'] ?: 'Africa/Cairo';
        }

        // No business defaults are seeded here. Payment, tax, numbering, and printer
        // policy must remain owner-provided; an empty state is intentional.
    }

    /**
     * Validate and preview company identity before committing it.
     */
    public function previewCompany(): void
    {
        Gate::authorize('manage-settings');

        $this->validate($this->companyRules());
        $this->showCompanyPreview = true;
    }

    public function updatedCompanyForm(): void
    {
        $this->companyDirty = true;
    }

    /**
     * Save the company identity after explicit preview confirmation.
     */
    public function saveCompany(SaveLocalSettingsAction $action): void
    {
        Gate::authorize('manage-settings');

        $validated = $this->validate($this->companyRules());

        $res = $action->execute(['company' => $validated['companyForm']], $this->companyId);

        $this->companyForm = array_merge($this->companyForm, $res['company']->toArray());
        $this->companyId = $res['company']->id;
        $this->companyDirty = false;
        $this->showCompanyPreview = false;

        Flux::toast(variant: 'success', text: __('Company settings saved successfully.'));
    }

    /** @return array<string, array<int, string>> */
    private function companyRules(): array
    {
        return [
            'companyForm.code' => ['required', 'string', 'max:20'],
            'companyForm.name_ar' => ['required', 'string', 'max:255'],
            'companyForm.name_en' => ['required', 'string', 'max:255'],
            'companyForm.legal_name' => ['nullable', 'string', 'max:255'],
            'companyForm.tax_number' => ['nullable', 'string', 'max:50'],
            'companyForm.commercial_registration' => ['nullable', 'string', 'max:50'],
            'companyForm.currency_code' => ['required', 'string', 'max:10'],
            'companyForm.currency_symbol' => ['required', 'string', 'max:10'],
            'companyForm.timezone' => ['required', 'timezone'],
            'companyForm.locale_default' => ['required', 'string', 'in:ar,en'],
            'companyForm.phone' => ['nullable', 'string', 'max:50', PhoneNormalizer::validationRule()],
            'companyForm.email' => ['nullable', 'email', 'max:255'],
            'companyForm.address' => ['nullable', 'string', 'max:500'],
            'companyForm.status' => ['required', 'string', 'in:active,inactive'],
            'companyForm.policy_notes' => ['nullable', 'string', 'max:1000'],
        ];
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
            'paymentMethodForm.type' => ['required', 'string', 'in:cash,card,transfer,manual,manual_electronic,gift_card,cheque'],
            'paymentMethodForm.requires_evidence' => ['boolean'],
            'paymentMethodForm.offline_eligible' => ['boolean'],
            'paymentMethodForm.status' => ['required', 'string', 'in:active,inactive'],
            'paymentMethodForm.policy_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if ((bool) $validated['paymentMethodForm']['offline_eligible']
            && ! in_array($validated['paymentMethodForm']['type'], ['cash', 'manual_electronic'], true)) {
            throw ValidationException::withMessages([
                'paymentMethodForm.offline_eligible' => __('Only cash or electronic-wallet methods can be approved for offline POS use.'),
            ]);
        }

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
            'policy_notes' => '',
        ];
    }

    /**
     * Save tax setting.
     */
    public function saveTaxSetting(PlatformSettingsApprovalAction $approvalAction): void
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
            'taxSettingForm.treatment' => ['required', 'string', 'in:standard,zero_rated,exempt,out_of_scope'],
            'taxSettingForm.is_default' => ['boolean'],
            'taxSettingForm.is_tax_inclusive' => ['boolean'],
            'taxSettingForm.tax_number' => ['nullable', 'string', 'max:50'],
            'taxSettingForm.effective_from' => ['nullable', 'date'],
            'taxSettingForm.effective_to' => ['nullable', 'date', 'after_or_equal:taxSettingForm.effective_from'],
            'taxSettingForm.status' => ['required', 'string', 'in:active,inactive'],
            'taxSettingForm.policy_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $tax = $this->taxSettingForm['id'] ? TaxSetting::query()->findOrFail((int) $this->taxSettingForm['id']) : null;
        $approvalAction->request(
            resource: 'tax_setting',
            id: $tax?->id,
            proposed: $validated['taxSettingForm'],
            before: $tax?->getAttributes(),
            reason: $validated['taxSettingForm']['policy_notes'] ?? null,
        );

        $this->resetTaxSettingForm();

        Flux::toast(variant: 'success', text: auth()->user()?->canBypassApproval() ? __('Super Admin action completed without separate approval.') : __('Tax setting submitted for independent approval.'));
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
            'treatment' => 'standard',
            'is_default' => false,
            'is_tax_inclusive' => true,
            'tax_number' => '',
            'effective_from' => '',
            'effective_to' => '',
            'status' => 'active',
            'policy_notes' => '',
        ];
    }

    /**
     * Save document sequence.
     */
    public function saveDocumentSequence(PlatformSettingsApprovalAction $approvalAction): void
    {
        Gate::authorize('manage-settings');

        $validated = $this->validate([
            'documentSequenceForm.document_type' => [
                'required',
                'string',
                'max:50',
                Rule::unique('document_sequences', 'document_type')
                    ->where(function ($query): void {
                        $scopeType = (string) ($this->documentSequenceForm['scope_type'] ?? 'company');
                        $scopeId = $this->documentSequenceForm['scope_id'] ?? null;
                        $scopeKey = $scopeType === 'branch' && is_numeric($scopeId)
                            ? 'branch:'.(int) $scopeId
                            : 'company';
                        $query->where('scope_key', $scopeKey);
                    })
                    ->ignore($this->documentSequenceForm['id'] ?? null),
            ],
            'documentSequenceForm.scope_type' => ['required', 'string', 'in:company,branch'],
            'documentSequenceForm.scope_id' => [
                'nullable',
                'integer',
                Rule::requiredIf(fn (): bool => ($this->documentSequenceForm['scope_type'] ?? 'company') === 'branch'),
                Rule::exists('branches', 'id')->where(fn ($query) => $query->where('status', 'active')),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (($this->documentSequenceForm['scope_type'] ?? 'company') === 'branch'
                        && ! Branch::visibleTo(auth()->user())->whereKey($value)->where('status', 'active')->exists()) {
                        $fail(__('The selected branch is not active or does not exist.'));
                    }
                },
            ],
            'documentSequenceForm.prefix' => ['nullable', 'string', 'max:20'],
            'documentSequenceForm.suffix' => ['nullable', 'string', 'max:20'],
            'documentSequenceForm.padding_length' => ['required', 'integer', 'min:1', 'max:12'],
            'documentSequenceForm.next_value' => [$this->documentSequenceForm['id'] ? 'nullable' : 'required', 'integer', 'min:1'],
            'documentSequenceForm.reset_rule' => ['required', 'string', 'in:never,daily,yearly,monthly'],
            'documentSequenceForm.status' => ['required', 'string', 'in:active,inactive'],
            'documentSequenceForm.policy_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $sequence = $this->documentSequenceForm['id'] ? DocumentSequence::visibleTo(auth()->user())->findOrFail((int) $this->documentSequenceForm['id']) : null;
        $approvalAction->request(
            resource: 'document_sequence',
            id: $sequence?->id,
            proposed: $validated['documentSequenceForm'],
            before: $sequence?->getAttributes(),
            reason: $validated['documentSequenceForm']['policy_notes'] ?? null,
        );

        $this->resetDocumentSequenceForm();

        Flux::toast(variant: 'success', text: auth()->user()?->canBypassApproval() ? __('Super Admin action completed without separate approval.') : __('Document sequence submitted for independent approval.'));
    }

    public function editDocumentSequence(int $id): void
    {
        Gate::authorize('manage-settings');

        $seq = DocumentSequence::visibleTo(auth()->user())->findOrFail($id);
        $this->documentSequenceForm = $seq->toArray();
        $this->sequenceOverride = [
            'sequence_id' => $seq->id,
            'next_value' => $seq->next_value,
            'expected_lock_version' => $seq->lock_version,
            'reason' => '',
        ];
    }

    public function overrideSequenceCounter(PlatformSettingsApprovalAction $approvalAction): void
    {
        Gate::authorize('drawers_payments_tax_numbering_printers.override');
        $validated = $this->validate([
            'sequenceOverride.sequence_id' => ['required', 'integer', 'exists:document_sequences,id'],
            'sequenceOverride.next_value' => ['required', 'integer', 'min:1'],
            'sequenceOverride.expected_lock_version' => ['required', 'integer', 'min:1'],
            'sequenceOverride.reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);
        $sequence = DocumentSequence::visibleTo(auth()->user())->findOrFail($validated['sequenceOverride']['sequence_id']);
        $approvalAction->request(
            resource: 'document_sequence_override',
            id: $sequence->id,
            proposed: $validated['sequenceOverride'],
            before: $sequence->only(['document_type', 'next_value', 'lock_version']),
            reason: $validated['sequenceOverride']['reason'],
        );
        Flux::toast(variant: 'success', text: auth()->user()?->canBypassApproval() ? __('Super Admin action completed without separate approval.') : __('Sequence counter override submitted for independent approval.'));
    }

    public function resetDocumentSequenceForm(): void
    {
        $this->documentSequenceForm = [
            'id' => null,
            'document_type' => 'sale',
            'scope_type' => 'company',
            'scope_id' => null,
            'prefix' => 'SALE-',
            'suffix' => '',
            'padding_length' => 6,
            'next_value' => 1,
            'reset_rule' => 'never',
            'status' => 'active',
            'policy_notes' => '',
        ];
        $this->sequenceOverride = ['sequence_id' => null, 'next_value' => null, 'expected_lock_version' => null, 'reason' => ''];
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
            'printerForm.scope_type' => ['required', 'string', 'in:global,branch,store'],
            'printerForm.branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'printerForm.store_id' => ['nullable', 'integer', 'exists:stores,id'],
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

        $action->savePrinterConfiguration($validated['printerForm'], $this->printerForm['id'] ?? null, [
            'scope_type' => $validated['printerForm']['scope_type'],
            'branch_id' => $validated['printerForm']['branch_id'] ?? null,
            'store_id' => $validated['printerForm']['store_id'] ?? null,
        ]);

        $this->resetPrinterForm();

        Flux::toast(variant: 'success', text: __('Printer configuration saved successfully.'));
    }

    public function editPrinter(int $id): void
    {
        Gate::authorize('manage-settings');

        $printer = PrinterConfiguration::visibleTo(auth()->user())->findOrFail($id);
        $this->printerForm = $printer->toArray();
        $this->printerForm['scope_type'] = $printer->store_id !== null ? 'store' : ($printer->branch_id !== null ? 'branch' : 'global');
    }

    public function resetPrinterForm(): void
    {
        $this->printerForm = [
            'id' => null,
            'name' => '',
            'scope_type' => 'global',
            'branch_id' => null,
            'store_id' => null,
            'printer_type' => 'thermal',
            'paper_size' => '80mm',
            'template_name' => 'default_thermal',
            'connection_type' => 'network',
            'ip_address' => '',
            'port' => 9100,
            'is_default' => false,
            'status' => 'active',
            'notes' => '',
        ];
    }
}; ?>

<x-app.page
    :title="__('System Settings')"
    :description="__('Review and configure business settings.')"
    max-width="7xl"
    class="settings-screen space-y-6"
    data-guide="settings-header"
    data-unsaved-company-message="{{ __('company.unsaved_navigation') }}"
    x-data="{ dirty: $wire.entangle('companyDirty'), unsavedNavigationMessage: $el.dataset.unsavedCompanyMessage }"
    x-on:beforeunload.window="if (dirty) { $event.preventDefault(); $event.returnValue = '' }"
    x-on:livewire:navigate.document="if (dirty && !window.confirm(unsavedNavigationMessage)) $event.preventDefault()"
>
    <x-slot:actions>
        <flux:badge size="sm" color="zinc" icon="adjustments-horizontal">
            {{ __('Configuration') }}
        </flux:badge>
    </x-slot:actions>

    <div class="settings-screen__content space-y-6" x-data="{ dirty: $wire.entangle('companyDirty') }">

    <?php if ($errors->any()): ?>
        <flux:callout variant="danger" icon="exclamation-triangle" title="{{ __('Validation Errors') }}">
            <p class="text-sm font-medium">{{ __('Please review and correct the following validation errors:') }}</p>
            <ul class="mt-2 list-disc list-inside space-y-1 text-sm">
                <?php foreach ($errors->all() as $error): ?>
                    <li>{{ $error }}</li>
                <?php endforeach; ?>
            </ul>
        </flux:callout>
    <?php endif; ?>

    <?php
        $activeTab = in_array($activeTab, ['company', 'payments', 'tax', 'sequences', 'printers', 'audit'], true) ? $activeTab : 'company';
        $sectionMeta = [
            'company' => [__('Company Identity'), __('Company identity and contact details.')],
            'payments' => [__('Payment Methods'), __('Payment names, evidence, and offline eligibility.')],
            'tax' => [__('Tax rules'), __('Tax treatment and price display defaults.')],
            'sequences' => [__('Document numbering'), __('Prefixes, counters, and reset timing.')],
            'printers' => [__('Printers & Print Profiles'), __('Printer destinations and assigned layouts.')],
            'audit' => [__('Configuration Change History'), __('Read-only history of setting changes.')],
        ][$activeTab] ?? [__('Company Identity'), __('Company identity and contact details.')];
    ?>

    <!-- Navigation Tabs -->
    <div class="settings-screen__tabs border-b border-border">
        <nav class="-mb-px flex gap-6 overflow-x-auto text-sm font-medium" role="tablist" aria-label="{{ __('Settings Sections') }}" data-guide="settings-tabs">
            <button
                type="button"
                id="tab-company"
                role="tab"
                aria-selected="{{ $activeTab === 'company' ? 'true' : 'false' }}"
                aria-controls="panel-company"
                wire:click="$set('activeTab', 'company')"
                data-settings-tab="company"
                class="pb-3 border-b-2 transition-colors cursor-pointer {{ $activeTab === 'company' ? 'border-primary text-primary font-semibold' : 'border-transparent text-text-muted hover:text-text-primary' }}"
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
                data-settings-tab="payments"
                class="pb-3 border-b-2 transition-colors cursor-pointer {{ $activeTab === 'payments' ? 'border-primary text-primary font-semibold' : 'border-transparent text-text-muted hover:text-text-primary' }}"
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
                data-settings-tab="tax"
                class="pb-3 border-b-2 transition-colors cursor-pointer {{ $activeTab === 'tax' ? 'border-primary text-primary font-semibold' : 'border-transparent text-text-muted hover:text-text-primary' }}"
            >
                {{ __('Tax rules') }}
            </button>
            <button
                type="button"
                id="tab-sequences"
                role="tab"
                aria-selected="{{ $activeTab === 'sequences' ? 'true' : 'false' }}"
                aria-controls="panel-sequences"
                wire:click="$set('activeTab', 'sequences')"
                data-settings-tab="sequences"
                class="pb-3 border-b-2 transition-colors cursor-pointer {{ $activeTab === 'sequences' ? 'border-primary text-primary font-semibold' : 'border-transparent text-text-muted hover:text-text-primary' }}"
            >
                {{ __('Document numbering') }}
            </button>
            <button
                type="button"
                id="tab-printers"
                role="tab"
                aria-selected="{{ $activeTab === 'printers' ? 'true' : 'false' }}"
                aria-controls="panel-printers"
                wire:click="$set('activeTab', 'printers')"
                data-settings-tab="printers"
                class="pb-3 border-b-2 transition-colors cursor-pointer {{ $activeTab === 'printers' ? 'border-primary text-primary font-semibold' : 'border-transparent text-text-muted hover:text-text-primary' }}"
            >
                {{ __('Printers & Print Profiles') }}
            </button>
            <button
                type="button"
                id="tab-audit"
                role="tab"
                aria-selected="{{ $activeTab === 'audit' ? 'true' : 'false' }}"
                aria-controls="panel-audit"
                wire:click="$set('activeTab', 'audit')"
                data-settings-tab="audit"
                class="pb-3 border-b-2 transition-colors cursor-pointer {{ $activeTab === 'audit' ? 'border-primary text-primary font-semibold' : 'border-transparent text-text-muted hover:text-text-primary' }}"
            >
                {{ __('Configuration Change History') }}
            </button>
        </nav>
    </div>

    <flux:card class="settings-screen__summary space-y-1 border-s-4 border-primary bg-surface shadow-card" data-settings-section-summary="{{ $activeTab }}">
        <flux:heading size="lg">{{ $sectionMeta[0] }}</flux:heading>
        <flux:subheading>{{ $sectionMeta[1] }}</flux:subheading>
    </flux:card>

    <!-- TAB 1: Company Identity -->
    <?php if ($activeTab === 'company'): ?>
        <div id="panel-company" role="tabpanel" aria-labelledby="tab-company" class="space-y-6">
            <?php if ($companyEditingBlocked): ?>
                <flux:callout variant="danger" icon="exclamation-triangle" title="{{ __('Validation Errors') }}">
                    {{ __('company.duplicate_load') }}
                </flux:callout>
            <?php else: ?>
            <form
                wire:submit="previewCompany"
                x-on:input="dirty = true"
                class="space-y-6"
            >
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
                            required
                        />

                        <flux:input
                            wire:model="companyForm.name_en"
                            :label="__('Name (English)')"
                            placeholder="TOY & JOY Company"
                            required
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
                    <flux:heading size="lg">{{ __('Localization and currency') }}</flux:heading>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <flux:input
                            wire:model="companyForm.currency_code"
                            :label="__('Currency code')"
                            required
                        />

                        <flux:input
                            wire:model="companyForm.currency_symbol"
                            :label="__('Currency symbol')"
                            required
                        />

                        <flux:select wire:model="companyForm.locale_default" :label="__('Default Application Locale')">
                            <option value="ar">العربية (Arabic - RTL)</option>
                            <option value="en">{{ __('English (LTR)') }}</option>
                        </flux:select>

                        <flux:input
                            wire:model="companyForm.phone"
                            :label="__('Contact Phone')"
                            :placeholder="__('e.g. 01012345678 or +20 1012345678')"
                            dir="ltr"
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

                    <div class="flex justify-end">
                        <flux:button
                            type="submit"
                            variant="primary"
                            icon="eye"
                            x-bind:disabled="!dirty"
                            wire:loading.attr="disabled"
                            wire:target="previewCompany"
                            data-guide="settings-save-button"
                        >
                            {{ __('company.review_changes') }}
                        </flux:button>
                    </div>
                </flux:card>
            </form>

            <flux:modal
                wire:model.self="showCompanyPreview"
                aria-label="{{ __('company.review_title') }}"
                class="md:w-[min(96vw,46rem)]"
            >
                <div class="space-y-6">
                    <div class="space-y-1">
                        <flux:heading id="company-preview-title" size="lg">{{ __('company.review_title') }}</flux:heading>
                        <flux:subheading>{{ __('company.review_help') }}</flux:subheading>
                    </div>

                    <?php
                        $companyPreviewRows = [
                            __('Company Code') => $companyForm['code'],
                            __('Legal Name') => $companyForm['legal_name'],
                            __('Name (Arabic)') => $companyForm['name_ar'],
                            __('Name (English)') => $companyForm['name_en'],
                            __('Tax Identification Number (TIN)') => $companyForm['tax_number'],
                            __('Commercial Registration (CR)') => $companyForm['commercial_registration'],
                            __('Currency code') => $companyForm['currency_code'],
                            __('Currency symbol') => $companyForm['currency_symbol'],
                            __('Default Application Locale') => strtoupper($companyForm['locale_default']),
                            __('Contact Phone') => $companyForm['phone'],
                            __('Contact Email') => $companyForm['email'],
                        ];
                    ?>
                    <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2">
                        <?php foreach ($companyPreviewRows as $label => $value): ?>
                            <div class="min-w-0 border-b border-zinc-200 pb-3 dark:border-zinc-700">
                                <dt class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ $label }}</dt>
                                <dd class="mt-1 break-words text-sm font-semibold text-zinc-900 dark:text-zinc-100" dir="auto">
                                    {{ filled($value) ? $value : __('Not provided') }}
                                </dd>
                            </div>
                        <?php endforeach; ?>
                    </dl>

                    <?php if (filled($companyForm['address'])): ?>
                        <dl class="space-y-4">
                            <?php if (filled($companyForm['address'])): ?>
                                <div>
                                    <dt class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Address') }}</dt>
                                    <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100" dir="auto">{{ $companyForm['address'] }}</dd>
                                </div>
                            <?php endif; ?>
                        </dl>
                    <?php endif; ?>

                    <form wire:submit="saveCompany" class="flex flex-col-reverse gap-2 border-t border-zinc-200 pt-4 dark:border-zinc-700 sm:flex-row sm:justify-end">
                        <flux:button type="button" variant="subtle" wire:click="$set('showCompanyPreview', false)">
                            {{ __('Back to edit') }}
                        </flux:button>
                        <flux:button type="submit" variant="primary" icon="check" wire:loading.attr="disabled" wire:target="saveCompany">
                            {{ __('Confirm and save') }}
                        </flux:button>
                    </form>
                </div>
            </flux:modal>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- TAB 2: Payment Methods -->
    <?php if ($activeTab === 'payments'): ?>
        <div id="panel-payments" role="tabpanel" aria-labelledby="tab-payments" class="space-y-6">
            <flux:card class="space-y-4">
                <flux:heading size="lg">
                    {{ $paymentMethodForm['id'] ? __('Edit Payment Method') : __('Add Payment Method') }}
                </flux:heading>
                <flux:subheading>{{ __('Choose the business name customers and staff will recognize. The method type is the accounting classification; it does not rename the method.') }}</flux:subheading>

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

                        <flux:select wire:model="paymentMethodForm.type" :label="__('Underlying payment type')">
                            <option value="cash">{{ __('Cash') }}</option>
                            <option value="card">{{ __('Card or POS terminal') }}</option>
                            <option value="transfer">{{ __('Bank Transfer') }}</option>
                            <option value="manual">{{ __('Other / manual record') }}</option>
                            <option value="manual_electronic">{{ __('Electronic wallet / manual transfer') }}</option>
                            <option value="cheque">{{ __('Cheque') }}</option>
                            <option value="gift_card">{{ __('Gift card') }}</option>
                        </flux:select>

                        <flux:select wire:model="paymentMethodForm.status" :label="__('Status')">
                            <option value="active">{{ __('Active') }}</option>
                            <option value="inactive">{{ __('Inactive') }}</option>
                        </flux:select>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                            <flux:switch
                                wire:model="paymentMethodForm.requires_evidence"
                                :label="__('Requires payment evidence')"
                            />
                            <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">{{ __('When enabled, staff must attach or reference payment evidence before this payment can be approved.') }}</p>
                        </div>

                        <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                            <flux:switch
                                wire:model="paymentMethodForm.offline_eligible"
                                :label="__('Available for approved offline POS transactions')"
                            />
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3">
                        <?php if ($paymentMethodForm['id']): ?>
                            <flux:button type="button" wire:click="resetPaymentMethodForm" variant="subtle">
                                {{ __('Cancel Edit') }}
                            </flux:button>
                        <?php endif; ?>

                        <flux:button type="submit" variant="primary">
                            {{ $paymentMethodForm['id'] ? __('Update Method') : __('Save Method') }}
                        </flux:button>
                    </div>
                </form>
            </flux:card>

            <flux:card class="space-y-4">
                <flux:heading size="lg">{{ __('Configured Payment Methods') }}</flux:heading>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Review saved methods below. Add a new method using the form above.') }}</p>

                <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
                    <flux:table class="min-w-[880px]" aria-label="{{ __('Configured Payment Methods') }}">
                        <?php $paymentMethods = PaymentMethod::query()->orderBy('code')->limit(100)->get(); ?>

                        <flux:table.columns>
                            <flux:table.column class="min-w-24 whitespace-nowrap">{{ __('Code') }}</flux:table.column>
                            <flux:table.column class="min-w-56"><span class="block whitespace-normal leading-tight">{{ __('Name (AR/EN)') }}</span></flux:table.column>
                            <flux:table.column class="min-w-36"><span class="block whitespace-normal leading-tight">{{ __('Underlying type') }}</span></flux:table.column>
                            <flux:table.column class="min-w-36"><span class="block whitespace-normal leading-tight">{{ __('Payment evidence') }}</span></flux:table.column>
                            <flux:table.column class="min-w-40"><span class="block whitespace-normal leading-tight">{{ __('Offline POS use') }}</span></flux:table.column>
                            <flux:table.column class="min-w-24 whitespace-nowrap">{{ __('Status') }}</flux:table.column>
                            <flux:table.column class="min-w-24 whitespace-nowrap text-end">{{ __('Actions') }}</flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                        <?php if ($paymentMethods->isNotEmpty()): ?>
                            <?php foreach ($paymentMethods as $method): ?>
                            <flux:table.row key="method-{{ $method->id }}">
                                <flux:table.cell class="font-mono font-bold">{{ $method->code }}</flux:table.cell>
                                <flux:table.cell>
                                    <div>{{ $method->name_ar }}</div>
                                    <div class="text-xs text-zinc-500">{{ $method->name_en }}</div>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <?php
                                        $typeLabel = [
                                            'cash' => __('Cash'),
                                            'card' => __('Card or POS terminal'),
                                            'transfer' => __('Bank Transfer'),
                                            'manual' => __('Other / manual record'),
                                            'manual_electronic' => __('Electronic wallet / manual transfer'),
                                            'cheque' => __('Cheque'),
                                            'gift_card' => __('Gift card'),
                                        ][$method->type] ?? __('Other / manual record');
                                    ?>
                                    <flux:badge size="sm" color="zinc">{{ $typeLabel }}</flux:badge>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <?php if ($method->requires_evidence): ?>
                                        <flux:badge size="sm" color="amber">{{ __('Required') }}</flux:badge>
                                    <?php else: ?>
                                        <flux:badge size="sm" color="zinc">{{ __('Not required') }}</flux:badge>
                                    <?php endif; ?>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <?php if ($method->offline_eligible): ?>
                                        <flux:badge size="sm" color="green">{{ __('Allowed by policy') }}</flux:badge>
                                    <?php else: ?>
                                        <flux:badge size="sm" color="zinc">{{ __('Not allowed') }}</flux:badge>
                                    <?php endif; ?>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <?php if ($method->status === 'active'): ?>
                                        <flux:badge size="sm" color="green">{{ __('Active') }}</flux:badge>
                                    <?php else: ?>
                                        <flux:badge size="sm" color="red">{{ __('Inactive') }}</flux:badge>
                                    <?php endif; ?>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:button size="xs" variant="subtle" wire:click="editPaymentMethod({{ $method->id }})">
                                        {{ __('Edit') }}
                                    </flux:button>
                                </flux:table.cell>
                            </flux:table.row>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <flux:table.row>
                                <flux:table.cell colspan="7" class="text-center py-4">
                                    <x-state.empty
                                        :title="__('No Payment Methods Configured')"
                                        :description="__('No payment methods are configured yet. Add one using the form above.')"
                                        icon="credit-card"
                                    />
                                </flux:table.cell>
                            </flux:table.row>
                        <?php endif; ?>
                        </flux:table.rows>
                    </flux:table>
                </div>
            </flux:card>
        </div>
    <?php endif; ?>

    <!-- TAB 3: Tax Settings -->
    <?php if ($activeTab === 'tax'): ?>
        <div id="panel-tax" role="tabpanel" aria-labelledby="tab-tax" class="space-y-6">
            <flux:card class="space-y-4">
                <flux:heading size="lg">
                    {{ $taxSettingForm['id'] ? __('Edit Tax Rule') : __('Add Tax Rule') }}
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

                        <flux:select wire:model="taxSettingForm.treatment" :label="__('Tax Treatment')" required>
                            <option value="standard">{{ __('Standard') }}</option>
                            <option value="zero_rated">{{ __('Zero Rated') }}</option>
                            <option value="exempt">{{ __('Exempt') }}</option>
                            <option value="out_of_scope">{{ __('Out of Scope') }}</option>
                        </flux:select>

                        <flux:input
                            wire:model="taxSettingForm.rate"
                            :label="__('Tax Rate (%)')"
                            placeholder="15.00"
                            type="number"
                            step="0.01"
                        />

                        <flux:input
                            wire:model="taxSettingForm.tax_number"
                            :label="__('Specific Tax Reg No.')"
                        />

                        <flux:input wire:model="taxSettingForm.effective_from" :label="__('Effective From')" type="date" />
                        <flux:input wire:model="taxSettingForm.effective_to" :label="__('Effective To')" type="date" />

                        <flux:select wire:model="taxSettingForm.status" :label="__('Status')">
                            <option value="active">{{ __('Active') }}</option>
                            <option value="inactive">{{ __('Inactive') }}</option>
                        </flux:select>
                    </div>

                    <div class="grid gap-3 md:grid-cols-2">
                        <flux:switch
                            wire:model="taxSettingForm.is_default"
                            :label="__('Company Default Tax Rule')"
                        />
                        <flux:switch
                            wire:model="taxSettingForm.is_tax_inclusive"
                            :label="__('Default Prices Are Tax Inclusive')"
                        />
                    </div>
                    <flux:text class="text-sm text-text-muted">{{ __('Tax inclusive means the displayed price already includes the tax amount.') }}</flux:text>

                    <div class="flex items-center justify-end gap-3">
                        <?php if ($taxSettingForm['id']): ?>
                            <flux:button type="button" wire:click="resetTaxSettingForm" variant="subtle">
                                {{ __('Cancel Edit') }}
                            </flux:button>
                        <?php endif; ?>

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
                        <flux:table.column>{{ __('Treatment') }}</flux:table.column>
                        <flux:table.column>{{ __('Rate %') }}</flux:table.column>
                        <flux:table.column>{{ __('Inclusive') }}</flux:table.column>
                        <flux:table.column>{{ __('Default') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                        <flux:table.column>{{ __('Actions') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        <?php $taxRows = TaxSetting::query()->orderBy('code')->limit(100)->get(); if ($taxRows->isNotEmpty()): foreach ($taxRows as $tax): ?>
                            <flux:table.row key="tax-{{ $tax->id }}">
                                <flux:table.cell class="font-mono font-bold">{{ $tax->code }}</flux:table.cell>
                                <flux:table.cell>
                                    <div>{{ $tax->name_ar }}</div>
                                    <div class="text-xs text-zinc-500">{{ $tax->name_en }}</div>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge size="sm" color="zinc">{{ $tax->treatmentLabel() }}</flux:badge>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <?php if ($tax->rate !== null): ?>
                                        <span class="font-mono font-semibold">{{ number_format((float)$tax->rate, 2) }}%</span>
                                    <?php else: ?>
                                        <flux:badge size="sm" color="amber">{{ __('Not configured') }}</flux:badge>
                                    <?php endif; ?>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <?php if ($tax->is_tax_inclusive): ?>
                                        <flux:badge size="sm" color="zinc">{{ __('Inclusive') }}</flux:badge>
                                    <?php else: ?>
                                        <flux:badge size="sm" color="zinc">{{ __('Exclusive') }}</flux:badge>
                                    <?php endif; ?>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <?php if ($tax->is_default): ?>
                                        <flux:badge size="sm" color="green">{{ __('Default') }}</flux:badge>
                                    <?php else: ?>
                                        <flux:badge size="sm" color="zinc">{{ __('No') }}</flux:badge>
                                    <?php endif; ?>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <?php if ($tax->status === 'active'): ?>
                                        <flux:badge size="sm" color="green">{{ __('Active') }}</flux:badge>
                                    <?php else: ?>
                                        <flux:badge size="sm" color="red">{{ __('Inactive') }}</flux:badge>
                                    <?php endif; ?>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:button size="xs" variant="subtle" wire:click="editTaxSetting({{ $tax->id }})">
                                        {{ __('Edit') }}
                                    </flux:button>
                                </flux:table.cell>
                            </flux:table.row>
                        <?php endforeach; else: ?>
                            <flux:table.row>
                                <flux:table.cell colspan="8" class="text-center py-4">
                                    <x-state.empty
                                        :title="__('No Tax Rules Configured')"
                        :description="__('No tax rules are configured yet. Add one using the form above.')"
                                        icon="receipt-percent"
                                    />
                                </flux:table.cell>
                            </flux:table.row>
                        <?php endif; ?>
                    </flux:table.rows>
                </flux:table>
            </flux:card>
        </div>
    <?php endif; ?>

    <!-- TAB 4: Document Sequences -->
    <?php if ($activeTab === 'sequences'): ?>
        <div id="panel-sequences" role="tabpanel" aria-labelledby="tab-sequences" class="space-y-6">
            <flux:card class="space-y-4">
                <flux:heading size="lg">
                    {{ $documentSequenceForm['id'] ? __('Edit document-numbering rule') : __('Add document-numbering rule') }}
                </flux:heading>

                <flux:callout variant="info" icon="information-circle" title="{{ __('How numbering works') }}">
                    {{ __('A number is prefix + padded counter + suffix. Reset cycle starts a new period; existing documents keep their numbers.') }}
                </flux:callout>

                <form wire:submit="saveDocumentSequence" class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <flux:input
                            wire:model="documentSequenceForm.document_type"
                            :label="__('Document type')"
                            placeholder="sale / purchase_order / party_invoice"
                            required
                        />

                        <flux:select wire:model="documentSequenceForm.scope_type" :label="__('Scope for this document type')" required>
                            <option value="company">{{ __('Company-wide (shared)') }}</option>
                            <option value="branch">{{ __('Branch-specific') }}</option>
                        </flux:select>

                        <?php if (($documentSequenceForm['scope_type'] ?? 'company') === 'branch'): ?>
                            <?php $activeBranches = Branch::visibleTo(auth()->user())->where('status', 'active')->orderBy('code')->get(); ?>
                            <flux:select wire:model="documentSequenceForm.scope_id" :label="__('Branch')" required>
                                <option value="">{{ __('Select an active branch...') }}</option>
                                <?php foreach ($activeBranches as $branch): ?>
                                    <option value="{{ $branch->id }}">{{ $branch->code }} — {{ app()->getLocale() === 'ar' ? $branch->name_ar : $branch->name_en }}</option>
                                <?php endforeach; ?>
                            </flux:select>
                        <?php endif; ?>

                        <flux:input
                            wire:model="documentSequenceForm.prefix"
                            :label="__('Prefix (before the number)')"
                            :description="__('Text added before the padded counter, for example SALE-000001.')"
                            placeholder="SALE- / INV-"
                        />

                        <flux:input
                            wire:model="documentSequenceForm.suffix"
                            :label="__('Suffix (after the number)')"
                            :description="__('Text added after the padded counter, for example -2026.')"
                        />

                        <flux:input
                            wire:model="documentSequenceForm.padding_length"
                            :label="__('Counter digits (padding)')"
                            :description="__('How many digits the counter uses; 6 turns 42 into 000042.')"
                            type="number"
                            required
                        />

                        <flux:input
                            wire:model="documentSequenceForm.next_value"
                            :label="$documentSequenceForm['id'] ? __('Current next number (read-only)') : __('First number to use')"
                            type="number"
                            :readonly="(bool) $documentSequenceForm['id']"
                            :description="$documentSequenceForm['id'] ? __('Shown for information while editing. Use the separate authorized correction below to change it.') : __('First number allocated by this sequence.')"
                            :required="! $documentSequenceForm['id']"
                        />

                        <flux:select wire:model="documentSequenceForm.reset_rule" :label="__('Reset cycle')" :description="__('When to start the counter again. Existing documents keep their numbers.')">
                            <option value="never">{{ __('Never — keep counting') }}</option>
                            <option value="daily">{{ __('Daily — restart each day') }}</option>
                            <option value="monthly">{{ __('Monthly — restart each month') }}</option>
                            <option value="yearly">{{ __('Yearly — restart each year') }}</option>
                        </flux:select>
                    </div>

                    <?php
                        $previewValue = (int) ($documentSequenceForm['next_value'] ?? 1);
                        $previewNumber = (string) ($documentSequenceForm['prefix'] ?? '')
                            .str_pad((string) $previewValue, (int) ($documentSequenceForm['padding_length'] ?? 6), '0', STR_PAD_LEFT)
                            .(string) ($documentSequenceForm['suffix'] ?? '');
                    ?>
                    <div class="rounded-lg border border-border-subtle bg-surface-muted p-4" data-sequence-preview>
                        <flux:heading size="sm">{{ __('Example number') }}</flux:heading>
                        <flux:text class="mt-1 font-mono text-lg font-semibold text-primary">{{ $previewNumber }}</flux:text>
                        <flux:text class="mt-1 text-sm text-text-muted">{{ __('Example only — saving does not reserve a number.') }}</flux:text>
                    </div>

                    <flux:input
                        wire:model="documentSequenceForm.policy_notes"
                        :label="__('Notes')"
                    />

                    <div class="flex items-center justify-end gap-3">
                        <?php if ($documentSequenceForm['id']): ?>
                            <flux:button type="button" wire:click="resetDocumentSequenceForm" variant="subtle">
                                {{ __('Cancel Edit') }}
                            </flux:button>
                        <?php endif; ?>

                        <flux:button type="submit" variant="primary">
                            {{ $documentSequenceForm['id'] ? __('Update Sequence') : __('Save Sequence') }}
                        </flux:button>
                    </div>
                </form>

                <?php if ($documentSequenceForm['id']): ?>
                    <?php if (Gate::allows('drawers_payments_tax_numbering_printers.override')): ?>
                        <form wire:submit="overrideSequenceCounter" class="space-y-4 border-t border-border-subtle pt-5" aria-labelledby="sequence-override-heading">
                            <div>
                                <flux:heading id="sequence-override-heading" size="md">{{ __('Authorized counter correction') }}</flux:heading>
                                <flux:text class="mt-1 text-text-muted">{{ __('Use this separate action to change the current counter. Enter a reason; the change is audited.') }}</flux:text>
                            </div>
                            <div class="grid gap-4 md:grid-cols-2">
                                <flux:input wire:model="sequenceOverride.next_value" type="number" min="1" :label="__('Replacement next number')" required />
                                <flux:textarea wire:model="sequenceOverride.reason" :label="__('Reason for correction')" required />
                            </div>
                            <div class="flex justify-end"><flux:button type="submit" variant="danger" wire:confirm="{{ __('Change this document counter? The action is permanent and audited.') }}">{{ __('Submit counter correction') }}</flux:button></div>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </flux:card>

            <flux:card class="space-y-4">
                <flux:heading size="lg">{{ __('Document-numbering rules') }}</flux:heading>

                <flux:table aria-label="{{ __('Document-numbering rules') }}">
                    <?php $documentSequences = DocumentSequence::visibleTo(auth()->user())->with('scopeBranch')->orderBy('document_type')->orderBy('scope_key')->limit(100)->get(); ?>

                    <flux:table.columns>
                        <flux:table.column>{{ __('Document type') }}</flux:table.column>
                        <flux:table.column>{{ __('Numbering scope') }}</flux:table.column>
                        <flux:table.column>{{ __('Example number') }}</flux:table.column>
                        <flux:table.column>{{ __('Current next number') }}</flux:table.column>
                        <flux:table.column>{{ __('Reset cycle') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                        <flux:table.column>{{ __('Actions') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        <?php if ($documentSequences->isNotEmpty()): ?>
                            <?php foreach ($documentSequences as $seq): ?>
                            <flux:table.row key="seq-{{ $seq->id }}">
                                <flux:table.cell class="font-bold">{{ __(Str::headline($seq->document_type)) }}</flux:table.cell>
                                <flux:table.cell>
                                    <?php if ($seq->scope_type === 'branch'): ?>
                                        <flux:badge size="sm" color="blue">
                                            {{ __('Branch') }}: {{ app()->getLocale() === 'ar' ? ($seq->scopeBranch?->name_ar ?? __('Unknown branch')) : ($seq->scopeBranch?->name_en ?? __('Unknown branch')) }}
                                            <span class="text-xs opacity-70">({{ $seq->scopeBranch?->code ?? $seq->scope_id }})</span>
                                        </flux:badge>
                                    <?php else: ?>
                                        <flux:badge size="sm" color="zinc">{{ __('Company-wide') }}</flux:badge>
                                    <?php endif; ?>
                                </flux:table.cell>
                                <flux:table.cell class="font-mono text-xs text-primary">{{ $seq->formatValue((int) $seq->next_value) }}</flux:table.cell>
                                <flux:table.cell class="font-mono font-semibold">{{ $seq->next_value }}</flux:table.cell>
                                <flux:table.cell><flux:badge size="sm" color="zinc">{{ __(Str::headline($seq->reset_rule)) }}</flux:badge></flux:table.cell>
                                <flux:table.cell>
                                    <?php if ($seq->status === 'active'): ?>
                                        <flux:badge size="sm" color="green">{{ __('Active') }}</flux:badge>
                                    <?php else: ?>
                                        <flux:badge size="sm" color="red">{{ __('Inactive') }}</flux:badge>
                                    <?php endif; ?>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:button size="xs" variant="subtle" wire:click="editDocumentSequence({{ $seq->id }})">
                                        {{ __('Edit') }}
                                    </flux:button>
                                </flux:table.cell>
                            </flux:table.row>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <flux:table.row>
                                <flux:table.cell colspan="7" class="text-center py-4">
                                    <x-state.empty
                                        :title="__('No Document Sequences Configured')"
                        :description="__('No document sequences are configured yet. Add one using the form above.')"
                                        icon="numbered-list"
                                    />
                                </flux:table.cell>
                            </flux:table.row>
                        <?php endif; ?>
                    </flux:table.rows>
                </flux:table>
            </flux:card>
        </div>
    <?php endif; ?>

    <!-- TAB 5: Printer Configurations -->
    <?php if ($activeTab === 'printers'): ?>
        <div id="panel-printers" role="tabpanel" aria-labelledby="tab-printers" data-settings-active-section="{{ $printerSection }}" class="space-y-6">
            <flux:callout variant="info" icon="information-circle" title="{{ $printerSection === 'print-templates' ? __('Print-template assignments') : __('Printer profiles') }}">
                {{ $printerSection === 'print-templates'
                    ? __('Review the template key assigned to each printer profile. Template layout design and physical printer testing remain outside this workspace.')
                    : __('Printer profiles control the output destination. This workspace only assigns an existing template key to each profile; it does not create or edit template layouts.') }}
            </flux:callout>

            <div class="flex flex-wrap gap-2" aria-label="{{ __('Printer workspace') }}">
                <flux:button size="sm" href="{{ route('admin.settings', ['tab' => 'printers', 'section' => 'printer-profiles']) }}" :variant="$printerSection === 'printer-profiles' ? 'primary' : 'subtle'">
                    {{ __('Printer profiles') }}
                </flux:button>
                <flux:button size="sm" href="{{ route('admin.settings', ['tab' => 'printers', 'section' => 'print-templates']) }}" :variant="$printerSection === 'print-templates' ? 'primary' : 'subtle'">
                    {{ __('Print-template assignments') }}
                </flux:button>
            </div>

            <?php if ($printerSection === 'printer-profiles'): ?>
            <flux:card id="printer-profiles" class="scroll-mt-24 space-y-4">
                <flux:heading size="lg">
                    {{ $printerForm['id'] ? __('Edit Printer Profile') : __('Add Printer Profile') }}
                </flux:heading>
                <flux:subheading>{{ __('A printer profile describes where a document is sent. The print template key describes the layout selected for that profile; this workspace does not edit template layouts.') }}</flux:subheading>

                <form id="printer-profile-form" wire:submit="savePrinter" class="space-y-4">
                    <?php
                        $printerBranches = Branch::visibleTo(auth()->user())->where('status', 'active')->orderBy('code')->get(['id', 'code', 'name_ar', 'name_en']);
                        $printerStores = \App\Modules\Platform\Models\Store::visibleTo(auth()->user())->where('status', 'active')->orderBy('code')->get(['id', 'branch_id', 'code', 'name_ar', 'name_en']);
                    ?>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <flux:input
                            wire:model="printerForm.name"
                            :label="__('Printer Name')"
                            placeholder="Cashier Thermal Printer 1"
                            required
                        />

                        <flux:select wire:model="printerForm.scope_type" :label="__('Printer scope')" :description="__('Global applies everywhere; Branch limits selection to that branch; Location binds it to one active location.')">
                            <option value="global">{{ __('Global workspace') }}</option>
                            <option value="branch">{{ __('Branch') }}</option>
                            <option value="store">{{ __('Location / store') }}</option>
                        </flux:select>

                        <flux:select wire:model="printerForm.branch_id" :label="__('Branch')" :disabled="$printerForm['scope_type'] === 'global'">
                            <option value="">{{ __('Select branch') }}</option>
                            @foreach ($printerBranches as $branch)
                                <option value="{{ $branch->id }}">{{ $branch->code }} · {{ app()->getLocale() === 'ar' ? $branch->name_ar : $branch->name_en }}</option>
                            @endforeach
                        </flux:select>

                        <flux:select wire:model="printerForm.store_id" :label="__('Location / store')" :disabled="$printerForm['scope_type'] !== 'store'">
                            <option value="">{{ __('Select location') }}</option>
                            @foreach ($printerStores as $store)
                                <option value="{{ $store->id }}">{{ $store->code }} · {{ app()->getLocale() === 'ar' ? $store->name_ar : $store->name_en }}</option>
                            @endforeach
                        </flux:select>

                        <flux:select wire:model="printerForm.printer_type" :label="__('Printer Type')">
                            <option value="thermal">{{ __('Thermal Receipt') }}</option>
                            <option value="a4">{{ __('A4 Document') }}</option>
                            <option value="label">{{ __('Barcode / Label') }}</option>
                            <option value="pdf">{{ __('PDF Virtual') }}</option>
                        </flux:select>

                        <flux:select wire:model="printerForm.paper_size" :label="__('Paper Size')">
                            <option value="80mm">{{ __('80mm Thermal') }}</option>
                            <option value="58mm">{{ __('58mm Thermal') }}</option>
                            <option value="a4">{{ __('A4 Sheet') }}</option>
                            <option value="label">{{ __('Standard Label') }}</option>
                        </flux:select>

                        <flux:input
                            wire:model="printerForm.template_name"
                            :label="__('Print template key')"
                            placeholder="default_thermal"
                            :description="__('Use the existing approved template key compatible with this paper size. This is a relationship to a template, not a printer name.')"
                            required
                        />

                        <flux:select wire:model="printerForm.connection_type" :label="__('Connection Type')">
                            <option value="network">{{ __('Network / IP') }}</option>
                            <option value="usb">{{ __('USB') }}</option>
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
                        :label="__('Default printer profile')"
                    />
                    <p class="-mt-2 text-sm text-zinc-500 dark:text-zinc-400">{{ __('The default is used when an authorized print workflow does not choose another compatible profile. It does not test or install hardware.') }}</p>

                    <flux:input
                        wire:model="printerForm.notes"
                        :label="__('Business configuration note (optional)')"
                        :description="__('Use this note for context only; approval remains a separate decision.')"
                    />

                    <div class="flex items-center justify-end gap-3">
                        <?php if ($printerForm['id']): ?>
                            <flux:button type="button" wire:click="resetPrinterForm" variant="subtle">
                                {{ __('Cancel Edit') }}
                            </flux:button>
                        <?php endif; ?>

                        <flux:button type="submit" variant="primary">
                            {{ $printerForm['id'] ? __('Update Printer Profile') : __('Save Printer Profile') }}
                        </flux:button>
                    </div>
                </form>
            </flux:card>
            <?php endif; ?>

            <?php if ($printerSection === 'print-templates'): ?>
            <flux:card id="print-templates" class="scroll-mt-24 space-y-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <flux:heading size="lg">{{ __('Print-template assignments') }}</flux:heading>
                        <flux:subheading>{{ __('Review the template key assigned to each printer profile. Template layout design and physical printer testing remain outside this workspace.') }}</flux:subheading>
                    </div>
                    <flux:button size="sm" variant="subtle" href="{{ route('admin.settings', ['tab' => 'printers', 'section' => 'printer-profiles']) }}">
                        {{ __('Manage printer profiles') }}
                    </flux:button>
                </div>

                <flux:table aria-label="{{ __('Configured Printer Profiles') }}">
                    <?php $printers = PrinterConfiguration::visibleTo(auth()->user())->with(['branch', 'store'])->orderBy('name')->limit(100)->get(); ?>

                    <flux:table.columns>
                        <flux:table.column>{{ __('Name') }}</flux:table.column>
                        <flux:table.column>{{ __('Scope') }}</flux:table.column>
                        <flux:table.column>{{ __('Printer type') }}</flux:table.column>
                        <flux:table.column>{{ __('Paper Size') }}</flux:table.column>
                        <flux:table.column>{{ __('Print template key') }}</flux:table.column>
                        <flux:table.column>{{ __('Connection') }}</flux:table.column>
                        <flux:table.column>{{ __('Default') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        <?php if ($printers->isNotEmpty()): ?>
                            <?php foreach ($printers as $printer): ?>
                            <flux:table.row key="printer-{{ $printer->id }}">
                                <flux:table.cell class="font-medium">{{ $printer->name }}</flux:table.cell>
                                <flux:table.cell class="text-xs">
                                    @if ($printer->store)
                                        {{ __('Location') }}: {{ $printer->store->code }}
                                    @elseif ($printer->branch)
                                        {{ __('Branch') }}: {{ $printer->branch->code }}
                                    @else
                                        {{ __('Global workspace') }}
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell><flux:badge size="sm" color="zinc">{{ strtoupper($printer->printer_type) }}</flux:badge></flux:table.cell>
                                <flux:table.cell class="font-mono text-xs">{{ $printer->paper_size }}</flux:table.cell>
                                <flux:table.cell class="font-mono text-xs">{{ $printer->template_name }}</flux:table.cell>
                                <flux:table.cell class="font-mono text-xs">{{ $printer->connection_type }} ({{ $printer->ip_address ?? __('Not specified') }})</flux:table.cell>
                                <flux:table.cell>
                                    <?php if ($printer->is_default): ?>
                                        <flux:badge size="sm" color="green">{{ __('Default') }}</flux:badge>
                                    <?php else: ?>
                                        <flux:badge size="sm" color="zinc">{{ __('No') }}</flux:badge>
                                    <?php endif; ?>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <?php if ($printer->status === 'active'): ?>
                                        <flux:badge size="sm" color="green">{{ __('Active') }}</flux:badge>
                                    <?php else: ?>
                                        <flux:badge size="sm" color="red">{{ __('Inactive') }}</flux:badge>
                                    <?php endif; ?>
                                </flux:table.cell>
                            </flux:table.row>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <flux:table.row>
                                <flux:table.cell colspan="8" class="text-center py-4">
                                    <x-state.empty
                                        :title="__('No printer profiles configured')"
                                        :description="__('Create an active printer profile, choose its compatible print-template key, and mark one profile as default before using a print workflow.')"
                                        icon="printer"
                                    />
                                </flux:table.cell>
                            </flux:table.row>
                        <?php endif; ?>
                    </flux:table.rows>
                </flux:table>
            </flux:card>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- TAB 6: Settings Audit Trail -->
    <?php if ($activeTab === 'audit'): ?>
        <div id="panel-audit" role="tabpanel" aria-labelledby="tab-audit" class="space-y-6">
            <flux:card class="space-y-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <flux:heading size="lg">{{ __('Configuration Change History') }}</flux:heading>
                        <flux:subheading>{{ __('Read-only history. Edit settings from the tabs above.') }}</flux:subheading>
                    </div>
                    <flux:badge size="sm" color="zinc" icon="shield-check">
                        {{ __('Settings history') }}
                    </flux:badge>
                </div>

                <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
                <flux:table class="min-w-[120rem]" aria-label="{{ __('Settings Audit Trail') }}">
                    <flux:table.columns>
                        <flux:table.column class="min-w-40 whitespace-nowrap">{{ __('Timestamp') }}</flux:table.column>
                        <flux:table.column class="min-w-56"><span class="block whitespace-normal leading-tight">{{ __('Correlation ID') }}</span></flux:table.column>
                        <flux:table.column class="min-w-36"><span class="block whitespace-normal leading-tight">{{ __('User') }}</span></flux:table.column>
                        <flux:table.column class="min-w-48"><span class="block whitespace-normal leading-tight">{{ __('Configuration area') }}</span></flux:table.column>
                        <flux:table.column class="min-w-48"><span class="block whitespace-normal leading-tight">{{ __('Field/key') }}</span></flux:table.column>
                        <flux:table.column class="min-w-72"><span class="block whitespace-normal leading-tight">{{ __('Previous value') }}</span></flux:table.column>
                        <flux:table.column class="min-w-72"><span class="block whitespace-normal leading-tight">{{ __('New value') }}</span></flux:table.column>
                        <flux:table.column class="min-w-52"><span class="block whitespace-normal leading-tight">{{ __('Reason') }}</span></flux:table.column>
                        <flux:table.column class="min-w-40"><span class="block whitespace-normal leading-tight">{{ __('Branch/company scope') }}</span></flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        <?php
                            $settingSourceTypes = [Company::class, PaymentMethod::class, TaxSetting::class, DocumentSequence::class, PrinterConfiguration::class];
                            $settingAuditLogs = AuditLog::visibleTo(auth()->user())
                                ->where('category', 'master_data')
                                ->where(function ($query) use ($settingSourceTypes): void {
                                    $query->whereIn('source_type', $settingSourceTypes)
                                        ->orWhere('source_type', 'like', 'legacy_settings:%');
                                })
                                ->latest('id')
                                ->take(20)
                                ->get();
                        ?>
                        <?php if ($settingAuditLogs->isNotEmpty()): ?>
                            <?php foreach ($settingAuditLogs as $log): ?>
                            <?php
                                $before = is_array($log->before_values) ? $log->before_values : [];
                                $after = is_array($log->after_values) ? $log->after_values : [];
                                $fields = is_array($log->changed_fields) && $log->changed_fields !== []
                                    ? $log->changed_fields
                                    : array_values(array_unique(array_merge(array_keys($before), array_keys($after))));
                                $area = [
                                    Company::class => __('Company identity'),
                                    PaymentMethod::class => __('Payment methods'),
                                    TaxSetting::class => __('Tax rules'),
                                    DocumentSequence::class => __('Document numbering'),
                                    PrinterConfiguration::class => __('Printer profiles'),
                                ][$log->source_type] ?? __('Configuration');
                                $scope = $log->branch_id
                                    ? __('Branch').' #'.$log->branch_id
                                    : ($log->store_id ? __('Store').' #'.$log->store_id : __('Global/company'));
                                $reason = trim((string) ($log->reason_text ?: $log->reason_code));
                                $beforeText = json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                                $afterText = json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                            ?>
                            <flux:table.row key="log-{{ $log->id }}" class="align-top">
                                <flux:table.cell class="align-top whitespace-nowrap font-mono text-xs" dir="ltr">{{ $log->created_at->format('Y-m-d H:i:s') }}</flux:table.cell>
                                <flux:table.cell class="align-top min-w-56 whitespace-normal break-all font-mono text-xs leading-relaxed" dir="ltr" title="{{ $log->request_id }}">{{ $log->request_id ?: __('Not recorded') }}</flux:table.cell>
                                <flux:table.cell class="align-top min-w-36 whitespace-normal break-words text-xs leading-relaxed">{{ $log->actor_name ?? __('System') }}</flux:table.cell>
                                <flux:table.cell class="align-top min-w-48 whitespace-normal text-xs leading-relaxed"><flux:badge size="sm" class="bg-primary-soft text-primary">{{ $area }}</flux:badge><div class="mt-1 break-words text-xs leading-relaxed text-text-muted">{{ $log->event }}</div></flux:table.cell>
                                <flux:table.cell class="align-top min-w-48 whitespace-normal break-words text-xs leading-relaxed" title="{{ implode(', ', $fields) }}">{{ implode(', ', $fields) ?: __('Not recorded') }}</flux:table.cell>
                                <flux:table.cell class="align-top min-w-72 max-w-80 whitespace-normal break-all font-mono text-xs leading-relaxed" dir="ltr" title="{{ $beforeText }}">{{ $beforeText ?: __('Not recorded') }}</flux:table.cell>
                                <flux:table.cell class="align-top min-w-72 max-w-80 whitespace-normal break-all font-mono text-xs leading-relaxed" dir="ltr" title="{{ $afterText }}">{{ $afterText ?: __('Not recorded') }}</flux:table.cell>
                                <flux:table.cell class="align-top min-w-52 whitespace-normal break-words text-xs leading-relaxed" title="{{ $reason }}">{{ $reason ?: __('No reason recorded') }}</flux:table.cell>
                                <flux:table.cell class="align-top min-w-40 whitespace-normal break-words text-xs leading-relaxed">{{ $scope }}</flux:table.cell>
                            </flux:table.row>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <flux:table.row>
                                <flux:table.cell colspan="9" class="text-center py-4">
                                    <x-state.empty
                                        :title="__('No Audit Logs Recorded')"
                                        :description="__('Setting changes will appear here. This history is read-only.')"
                                        icon="shield-check"
                                    />
                                </flux:table.cell>
                            </flux:table.row>
                        <?php endif; ?>
                    </flux:table.rows>
                </flux:table>
                </div>
            </flux:card>
        </div>
    <?php endif; ?>
    </div>
</x-app.page>
