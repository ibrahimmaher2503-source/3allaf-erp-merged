<?php

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductUnit;
use App\Modules\Customer\Support\PhoneNormalizer;
use App\Modules\Platform\Actions\PlatformSettingsApprovalAction;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Actions\SaveListDisplayPreference;
use App\Modules\Platform\Actions\SaveLocalSettingsAction;
use App\Modules\Platform\Actions\SavePrintTemplateAction;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\DocumentSequence;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Platform\Models\PrinterConfiguration;
use App\Modules\Platform\Models\PrintTemplate;
use App\Modules\Platform\Models\ScaleConfiguration;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Models\TaxSetting;
use App\Modules\Platform\Services\TmScaleConnection;
use App\Modules\Pricing\Services\EffectivePriceResolver;
use App\Modules\Platform\Support\AuthorizedCompanyContext;
use App\Modules\Platform\Support\DocumentTypeCatalog;
use Flux\Flux;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('System Settings')] class extends Component
{
    use WithPagination;

    // Active Tab
    #[Url(as: 'tab', except: 'company')]
    public string $activeTab = 'company';

    public string $masterListSearch = '';

    #[Url(as: 'setup', except: false)]
    public bool $setupMode = false;

    #[Url(as: 'section', except: 'printer-profiles')]
    public string $printerSection = 'printer-profiles';

    #[Locked]
    public ?int $companyId = null;

    public bool $companyDirty = false;

    public bool $companyEditingBlocked = false;

    public bool $showSearchFiltersByDefault = false;

    public bool $paymentModalOpen = false;

    public bool $taxModalOpen = false;

    public bool $sequenceModalOpen = false;

    public bool $printerModalOpen = false;

    public array $scaleForm = ['name' => '', 'ip_address' => '', 'port' => 33581, 'status' => 'active', 'store_id' => null];

    public array $scaleMapping = ['code' => '', 'plu' => '', 'label' => ''];

    #[Locked]
    public array $scalePricePreviews = [];

    #[Locked]
    public array $scaleSendResults = [];

    public ?string $scaleConnectionResult = null;

    public string $scaleProductNumber = '';

    public ?array $scaleProduct = null;

    public ?string $scaleProductResult = null;

    public array $scaleReport = [];

    public ?string $scaleReportResult = null;

    /** @var array<int, string> mapping id => confirmed|needs_update|ineligible, filled by checkScalePrices() */
    public array $scaleStatuses = [];

    public array $scaleKeyForm = ['layer' => 0, 'key_number' => 1, 'mapping_id' => null];

    /** @var array<int, string> key assignment id => confirmed|unverified, set by sendScaleKey() */
    public array $scaleKeyResults = [];

    public array $scaleLabelForm = ['name' => 'Default label', 'width_mm' => 58, 'height_mm' => 40, 'barcode_type' => 'EAN13', 'fields' => ['name', 'weight', 'unit_price', 'total', 'date']];

    // Scale workspace (inside the printers tab): products | keys | labels | operations
    public string $scaleTab = 'products';

    public bool $scaleEditing = false;

    public string $scaleProductSearch = '';

    public string $scaleMappingSearch = '';

    public int $scaleKeyLayer = 0;

    public ?int $scaleKeySelected = null;

    public string $scaleKeyPickerSearch = '';

    /** @var array<int, array<int, int>>|null layer => key => PLU as read back from the device (read-only) */
    public ?array $scaleDeviceKeys = null;

    public ?string $scaleDeviceKeysResult = null;

    /** Weight/price barcode printed on scale labels: saved ERP definition, device upload not verified. */
    public array $scaleBarcodeForm = [
        'symbology' => 'EAN13', 'prefix' => '20', 'item_source' => 'plu', 'item_digits' => 5,
        'value_type' => 'weight', 'value_digits' => 5, 'value_decimals' => 3, 'print_human_readable' => true,
    ];

    /** @var list<string> */
    public array $scaleDeviceBarcodeFormats = [];

    public ?string $scaleDeviceBarcodeResult = null;

    public bool $templateModalOpen = false;

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
        'document_type' => 'retail_sale',
        'scope_type' => 'branch',
        'scope_id' => null,
        'prefix' => '',
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
        'print_template_id' => null,
        'connection_type' => 'network',
        'ip_address' => '',
        'port' => 9100,
        'is_default' => false,
        'status' => 'active',
        'notes' => '',
    ];

    public array $templateForm = [
        'id' => null, 'code' => '', 'name_ar' => '', 'name_en' => '',
        'document_type' => 'sales_invoice', 'paper_size' => '80mm', 'language' => 'bilingual',
        'header_text' => '', 'footer_text' => '', 'layout_settings' => ['width_mm' => 50, 'height_mm' => 30, 'dpi' => 203, 'orientation' => 'portrait', 'margin_mm' => 1, 'gap_mm' => 2, 'horizontal_gap_mm' => 2, 'vertical_gap_mm' => 2, 'a4_rows' => 8, 'a4_columns' => 3, 'symbology' => 'auto', 'show_name' => true, 'show_price' => true, 'show_item_code' => true, 'show_list_code' => true, 'show_outlet' => true], 'status' => 'active',
    ];

    protected function validationAttributes(): array
    {
        return [
            'companyForm.address' => __('Address'),
            'taxSettingForm.rate' => __('Tax Rate (%)'),
        ];
    }

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
        $this->showSearchFiltersByDefault = auth()->user()?->uiPreference?->showSearchFiltersByDefault() ?? false;
    }

    public function updatedActiveTab(string $tab): void
    {
        $this->masterListSearch = '';
        $this->resetPage();

        if (! in_array($tab, ['company', 'payments', 'tax', 'sequences', 'printers', 'audit'], true)) {
            $this->activeTab = 'company';

            return;
        }

        if ($tab === 'audit') {
            Gate::authorize('audit_logs.view');
        }
    }

    public function updatingMasterListSearch(): void
    {
        $this->resetPage();
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
        $company = app(AuthorizedCompanyContext::class)->resolve(auth()->user(), request()->input('company_id'));
        if ($company) {
            $this->companyId = $company->id;
            $this->companyForm = array_merge($this->companyForm, $company->toArray());
            $this->companyForm['timezone'] = $this->companyForm['timezone'] ?: 'Africa/Cairo';
            if ($scale = ScaleConfiguration::query()->where('company_id', $company->id)->first()) {
                $this->scaleForm = $scale->only(['name', 'ip_address', 'port', 'status', 'store_id']);
                $this->scaleBarcodeForm = array_replace($this->scaleBarcodeForm, array_intersect_key((array) $scale->barcode_settings, $this->scaleBarcodeForm));
            } else {
                $this->scaleEditing = true;
            }
        }

        // No business defaults are seeded here. Payment, tax, numbering, and printer
        // policy must remain owner-provided; an empty state is intentional.
    }

    public function updatedCompanyForm(): void
    {
        $this->companyDirty = true;
    }

    public function saveListDisplayPreference(SaveListDisplayPreference $action): void
    {
        Gate::authorize('manage-settings');
        $user = auth()->user();
        abort_unless($user instanceof User, 403);
        $action->execute($user, $this->showSearchFiltersByDefault);
        $user->unsetRelation('uiPreference');
        Flux::toast(variant: 'success', text: __('List display preference saved.'));
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

        $this->paymentModalOpen = false;

        Flux::toast(variant: 'success', text: __('Payment method saved successfully.'));
    }

    public function editPaymentMethod(int $id): void
    {
        Gate::authorize('manage-settings');

        $method = PaymentMethod::findOrFail($id);
        $this->paymentMethodForm = $method->toArray();
        $this->resetValidation();
        $this->paymentModalOpen = true;
    }

    public function openPaymentMethodModal(): void
    {
        Gate::authorize('manage-settings');
        $this->resetPaymentMethodForm();
        $this->resetValidation();
        $this->paymentModalOpen = true;
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

        $companyId = (int) $this->companyId;
        $validated = $this->validate([
            'taxSettingForm.code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('tax_settings', 'code')->where('company_id', $companyId)->ignore($this->taxSettingForm['id'] ?? null),
            ],
            'taxSettingForm.name_ar' => ['required', 'string', 'max:255'],
            'taxSettingForm.name_en' => ['required', 'string', 'max:255'],
            'taxSettingForm.rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'taxSettingForm.treatment' => ['required', 'string', 'in:standard,zero_rated,exempt,out_of_scope'],
            'taxSettingForm.is_default' => ['boolean'],
            'taxSettingForm.is_tax_inclusive' => ['boolean'],
            'taxSettingForm.tax_number' => ['nullable', 'string', 'max:50'],
            'taxSettingForm.effective_from' => ['nullable', 'date'],
            'taxSettingForm.effective_to' => ['nullable', 'date', 'after_or_equal:taxSettingForm.effective_from'],
            'taxSettingForm.status' => ['required', 'string', 'in:active,inactive'],
            'taxSettingForm.policy_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $validated['taxSettingForm']['company_id'] = $companyId;
        $tax = $this->taxSettingForm['id'] ? TaxSetting::query()->forCompany($companyId)->findOrFail((int) $this->taxSettingForm['id']) : null;
        $approvalAction->request(
            resource: 'tax_setting',
            id: $tax?->id,
            proposed: $validated['taxSettingForm'],
            before: $tax?->getAttributes(),
            reason: $validated['taxSettingForm']['policy_notes'] ?? null,
        );

        $this->resetTaxSettingForm();

        $this->taxModalOpen = false;

        Flux::toast(variant: 'success', text: auth()->user()?->canBypassApproval() ? __('Super Admin action completed without separate approval.') : __('Tax setting submitted for independent approval.'));
    }

    public function editTaxSetting(int $id): void
    {
        Gate::authorize('manage-settings');

        $tax = TaxSetting::query()->forCompany((int) $this->companyId)->findOrFail($id);
        $this->taxSettingForm = $tax->toArray();
        $this->resetValidation();
        $this->taxModalOpen = true;
    }

    public function openTaxSettingModal(): void
    {
        Gate::authorize('manage-settings');
        $this->resetTaxSettingForm();
        $this->resetValidation();
        $this->taxModalOpen = true;
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

        $isLegacyCompanyRule = filled($this->documentSequenceForm['id'])
            && ($this->documentSequenceForm['scope_type'] ?? null) === 'company';
        if (! $isLegacyCompanyRule) {
            $branch = Branch::visibleTo(auth()->user())
                ->where('status', 'active')
                ->find((int) ($this->documentSequenceForm['scope_id'] ?? 0));
            if ($branch === null) {
                throw ValidationException::withMessages(['documentSequenceForm.scope_id' => __('The selected branch is not active or does not exist.')]);
            }
            $this->documentSequenceForm['scope_type'] = 'branch';
            $this->documentSequenceForm['prefix'] = DocumentTypeCatalog::prefix($branch->code, (string) $this->documentSequenceForm['document_type']);
            $this->documentSequenceForm['suffix'] = '';
            $this->documentSequenceForm['padding_length'] = 6;
            $this->documentSequenceForm['reset_rule'] = 'never';
        }

        $validated = $this->validate([
            'documentSequenceForm.document_type' => [
                'required',
                'string',
                Rule::in(array_keys(DocumentTypeCatalog::NUMBERING_TYPES)),
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
            'documentSequenceForm.scope_type' => ['required', 'string', Rule::in($isLegacyCompanyRule ? ['company'] : ['branch'])],
            'documentSequenceForm.scope_id' => [
                'nullable',
                'integer',
                Rule::requiredIf(fn (): bool => ($this->documentSequenceForm['scope_type'] ?? 'company') === 'branch'),
                Rule::exists('branches', 'id')->where(fn ($query) => $query->where('status', 'active')),
                function (string $attribute, mixed $value, Closure $fail): void {
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
            'documentSequenceForm.reset_rule' => ['required', 'string', Rule::in($isLegacyCompanyRule ? ['never', 'daily', 'yearly', 'monthly'] : ['never'])],
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

        $this->sequenceModalOpen = false;

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
        $this->resetValidation();
        $this->sequenceModalOpen = true;
    }

    public function openDocumentSequenceModal(): void
    {
        Gate::authorize('manage-settings');
        $this->resetDocumentSequenceForm();
        $this->resetValidation();
        $this->sequenceModalOpen = true;
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
            'document_type' => 'retail_sale',
            'scope_type' => 'branch',
            'scope_id' => null,
            'prefix' => '',
            'suffix' => '',
            'padding_length' => 6,
            'next_value' => 1,
            'reset_rule' => 'never',
            'status' => 'active',
            'policy_notes' => '',
        ];
        $this->sequenceOverride = ['sequence_id' => null, 'next_value' => null, 'expected_lock_version' => null, 'reason' => ''];
    }

    /** @return array{name: string, ip_address: string, port: int, status: string} */
    private function validatedScaleForm(): array
    {
        $data = $this->validate([
            'scaleForm.name' => ['required', 'string', 'max:255'],
            'scaleForm.ip_address' => ['required', 'ipv4'],
            'scaleForm.port' => ['required', 'integer', 'between:1,65535'],
            'scaleForm.status' => ['required', 'in:active,inactive'],
        ])['scaleForm'];

        if (! preg_match('/^(10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[01])\.)/', $data['ip_address'])) {
            throw ValidationException::withMessages(['scaleForm.ip_address' => __('Use a private network IP address for the scale.')]);
        }

        return $data;
    }

    public function saveScale(): void
    {
        Gate::authorize('company_settings.edit');
        abort_unless($this->companyId, 403);
        $data = $this->validatedScaleForm();
        $storeId = $this->validate(['scaleForm.store_id' => ['required', 'integer']])['scaleForm']['store_id'];
        $data['store_id'] = $this->scaleOutlets()->findOrFail($storeId)->id;

        DB::transaction(function () use ($data): void {
            Company::query()->whereKey($this->companyId)->lockForUpdate()->firstOrFail();
            $scale = ScaleConfiguration::query()->firstOrNew(['company_id' => $this->companyId]);
            $before = $scale->exists ? $scale->getAttributes() : null;
            $scale->fill($data);
            if ($scale->exists && $scale->isDirty(['ip_address', 'port', 'store_id'])) {
                DB::table('scale_product_mappings')->where('scale_configuration_id', $scale->id)
                    ->update(['confirmed_price' => null, 'sync_status' => 'pending', 'updated_at' => now()]);
            }
            $scale->save();
            app(RecordAuditEvent::class)->execute('master_data', $before ? 'update_scale_configuration' : 'create_scale_configuration', $scale, $before, $scale->getAttributes());
        });

        $this->scaleConnectionResult = null;
        $this->scaleEditing = false;
        $this->reset('scalePricePreviews', 'scaleSendResults');
        Flux::toast(variant: 'success', text: __('Scale configuration saved.'));
    }

    private function scaleOutlets(): \Illuminate\Database\Eloquent\Builder
    {
        return Store::query()->visibleTo(auth()->user())->where('company_id', $this->companyId)
            ->where('type', 'selling')->where('status', 'active');
    }

    public function saveScaleMapping(): void
    {
        Gate::authorize('company_settings.edit');
        abort_unless($this->companyId, 403);
        $data = $this->validate([
            'scaleMapping.code' => ['required', 'string', 'max:100'],
            'scaleMapping.plu' => ['required', 'integer', 'between:1,4000'],
            'scaleMapping.label' => ['required', 'regex:/\A[ -~]{1,20}\z/'],
        ])['scaleMapping'];

        $mappingId = DB::transaction(function () use ($data): int {
            $device = ScaleConfiguration::query()->where('company_id', $this->companyId)->where('status', 'active')->lockForUpdate()->firstOrFail();
            $store = $this->scaleOutlets()->findOrFail($device->store_id);
            $product = Product::query()->sellable()->availableToCompany($this->companyId, $store->branch_id, $store->id)
                ->where('item_code', $data['code'])->first();
            $unit = $product?->productUnits()->where('is_sale_unit', true)
                ->whereHas('unit', fn ($query) => $query->where('code', 'KG')->where('dimension', 'weight')->where('status', 'active'))->first();
            if ($unit === null) {
                throw ValidationException::withMessages(['scaleMapping.code' => __('Select a sellable product with a kilogram sale unit.')]);
            }

            $mappings = DB::table('scale_product_mappings')->where('scale_configuration_id', $device->id);
            if ((clone $mappings)->where(fn ($query) => $query->where('plu', $data['plu'])->orWhere('product_unit_id', $unit->id))->exists()) {
                throw ValidationException::withMessages(['scaleMapping.plu' => __('This PLU or product unit is already mapped on the scale.')]);
            }

            return DB::table('scale_product_mappings')->insertGetId([
                'scale_configuration_id' => $device->id, 'product_unit_id' => $unit->id, 'plu' => $data['plu'],
                'label' => trim($data['label']), 'created_at' => now(), 'updated_at' => now(),
            ]);
        });

        $this->reset('scaleMapping');
        $this->previewScalePrice($mappingId, app(EffectivePriceResolver::class));
    }

    public function previewScalePrice(int $id, EffectivePriceResolver $prices): void
    {
        Gate::authorize('company_settings.edit');
        abort_unless($this->companyId, 403);
        if (count($this->scalePricePreviews) >= 15 && ! isset($this->scalePricePreviews[$id])) {
            throw ValidationException::withMessages(['scaleMapping.plu' => __('Preview up to 15 scale products at a time.')]);
        }
        $device = ScaleConfiguration::query()->where('company_id', $this->companyId)->where('status', 'active')->firstOrFail();
        $this->scalePricePreviews[$id] = $this->scalePriceSnapshot($device, $id, $prices);
        unset($this->scaleSendResults[$id]);
    }

    /**
     * Compares each mapped product's current outlet price with the last price confirmed on the scale.
     * Runs in its own request (wire:init) so the per-row price resolution never inflates other requests.
     */
    public function checkScalePrices(): void
    {
        Gate::authorize('company_settings.edit');
        $device = ScaleConfiguration::query()->where('company_id', $this->companyId)->where('status', 'active')->first();
        $this->scaleStatuses = [];
        if (! $device) {
            return;
        }
        $prices = app(EffectivePriceResolver::class);
        foreach (DB::table('scale_product_mappings')->where('scale_configuration_id', $device->id)->orderBy('plu')->limit(12)->get() as $mapping) {
            try {
                $snapshot = $this->scalePriceSnapshot($device, (int) $mapping->id, $prices);
                $this->scaleStatuses[(int) $mapping->id] = $snapshot['status'] === 'confirmed' && $snapshot['confirmed'] === $snapshot['price'] ? 'confirmed' : 'needs_update';
            } catch (ValidationException | \Illuminate\Database\Eloquent\ModelNotFoundException) {
                $this->scaleStatuses[(int) $mapping->id] = 'ineligible';
            }
        }
    }

    public function removeScalePreview(int $id): void
    {
        Gate::authorize('company_settings.edit');
        unset($this->scalePricePreviews[$id], $this->scaleSendResults[$id]);
    }

    private function scalePriceSnapshot(ScaleConfiguration $device, int $id, EffectivePriceResolver $prices): array
    {
        $store = $this->scaleOutlets()->findOrFail($device->store_id);
        $mapping = DB::table('scale_product_mappings')->where('scale_configuration_id', $device->id)->where('id', $id)->first();
        if (! $mapping) {
            throw ValidationException::withMessages(['scaleMapping.plu' => __('Scale product is no longer eligible for sending.')]);
        }
        $unit = ProductUnit::query()->where('is_sale_unit', true)
            ->whereHas('unit', fn ($query) => $query->where('code', 'KG')->where('dimension', 'weight')->where('status', 'active'))
            ->whereHas('product', fn ($query) => $query->sellable()->availableToCompany($this->companyId, $store->branch_id, $store->id))
            ->findOrFail($mapping->product_unit_id);
        $price = $prices->resolve($unit->product_id, $store->id, productUnitId: $unit->id);
        if ($price === null || $price->open_price_allowed || ! preg_match('/\A[0-9]{1,4}(?:\.[0-9]{1,2}0*)?\z/', (string) $price->amount)
            || bccomp((string) $price->amount, '0', 2) <= 0) {
            throw ValidationException::withMessages(['scaleMapping.plu' => __('No fixed outlet price compatible with the scale was found.')]);
        }

        return [
            'id' => $id, 'device_id' => $device->id, 'endpoint' => $device->ip_address.':'.$device->port,
            'store_id' => $store->id, 'plu' => (int) $mapping->plu, 'label' => $mapping->label,
            'product_code' => $unit->product->item_code, 'product_name' => str_starts_with(app()->getLocale(), 'ar') ? $unit->product->name_ar : ($unit->product->name_en ?: $unit->product->name_ar),
            'unit' => $unit->unit->code,
            'price' => bcadd((string) $price->amount, '0', 2), 'source' => $prices->identity($price), 'source_label' => (string) $price->price_label,
            'confirmed' => $mapping->confirmed_price, 'status' => $mapping->sync_status,
        ];
    }

    public function sendScalePrice(int $id, EffectivePriceResolver $prices, TmScaleConnection $connection): string
    {
        Gate::authorize('company_settings.edit');
        abort_unless($this->companyId && isset($this->scalePricePreviews[$id]), 403);
        $status = DB::transaction(function () use ($id, $prices, $connection): string {
            $device = ScaleConfiguration::query()->where('company_id', $this->companyId)->where('status', 'active')->lockForUpdate()->first();
            if (! $device) {
                return 'ineligible';
            }
            try {
                $snapshot = $this->scalePriceSnapshot($device, $id, $prices);
            } catch (ValidationException | \Illuminate\Database\Eloquent\ModelNotFoundException) {
                return 'ineligible';
            }
            if ($snapshot !== $this->scalePricePreviews[$id]) {
                $this->scalePricePreviews[$id] = $snapshot;

                return 'changed';
            }

            $query = DB::table('scale_product_mappings')->where('scale_configuration_id', $device->id)->where('id', $snapshot['id']);
            try {
                $connection->sendWeightedProduct($device->ip_address, $device->port, $snapshot['plu'], (string) $snapshot['plu'], $snapshot['label'], $snapshot['price']);
            } catch (RuntimeException) {
                $query->update(['sync_status' => 'unverified', 'updated_at' => now()]);
                $this->scalePricePreviews[$id] = array_replace($snapshot, ['status' => 'unverified']);

                return 'unverified';
            }

            $query->update(['confirmed_price' => $snapshot['price'], 'sync_status' => 'confirmed', 'updated_at' => now()]);
            app(RecordAuditEvent::class)->execute('master_data', 'confirm_scale_price', $device, null, [
                'plu' => $snapshot['plu'], 'price' => $snapshot['price'], 'store_id' => $snapshot['store_id'],
            ]);
            $this->scalePricePreviews[$id] = array_replace($snapshot, ['confirmed' => $snapshot['price'], 'status' => 'confirmed']);

            return 'confirmed';
        });
        $this->scaleSendResults[$id] = $status;
        $this->scaleStatuses[$id] = match ($status) {
            'confirmed' => 'confirmed',
            'ineligible' => 'ineligible',
            default => 'needs_update',
        };

        return $status;
    }

    public function testScaleConnection(): void
    {
        Gate::authorize('company_settings.edit');
        abort_unless($this->companyId, 403);
        $data = $this->validatedScaleForm();
        $connection = @stream_socket_client("tcp://{$data['ip_address']}:{$data['port']}", $errorCode, $errorMessage, 10);
        $this->scaleConnectionResult = 'unreachable';
        if ($connection === false) {
            return;
        }

        $this->scaleConnectionResult = 'unrecognized';
        try {
            stream_set_timeout($connection, 3);
            $request = "UPL\tTIM\t\r\n";
            if (@fwrite($connection, $request) !== strlen($request)) {
                return;
            }

            $response = '';
            // A TM-series clock reply has at most four lines, including the leading blank line.
            for ($line = 0; $line < 4 && ($part = @fgets($connection, 256)) !== false; $line++) {
                $response .= $part;
                if ($part === "END\tTIM\t\r\n") {
                    break;
                }
            }

            if (preg_match('/\A(?:\r\n)?DWL\tTIM\t\r\nTIM(?:\t[0-9]{1,4}){6}\t\r\nEND\tTIM\t\r\n\z/', $response) === 1) {
                $this->scaleConnectionResult = 'connected';
            }
        } finally {
            fclose($connection);
        }
    }

    public function saveScaleKeyAssignment(): void
    {
        Gate::authorize('company_settings.edit');
        abort_unless($this->companyId, 403);
        $data = $this->validate([
            'scaleKeyForm.layer' => ['required', 'integer', 'between:0,2'],
            'scaleKeyForm.key_number' => ['required', 'integer', 'between:1,63'],
            'scaleKeyForm.mapping_id' => ['nullable', 'integer'],
        ])['scaleKeyForm'];
        $device = ScaleConfiguration::query()->where('company_id', $this->companyId)->where('status', 'active')->firstOrFail();
        if ($data['mapping_id'] !== null && ! DB::table('scale_product_mappings')->where('id', $data['mapping_id'])->where('scale_configuration_id', $device->id)->exists()) {
            throw ValidationException::withMessages(['scaleKeyForm.mapping_id' => __('Select a product mapped to this scale.')]);
        }
        DB::table('scale_key_assignments')->updateOrInsert(
            ['scale_configuration_id' => $device->id, 'layer' => $data['layer'], 'key_number' => $data['key_number']],
            ['scale_product_mapping_id' => $data['mapping_id'], 'sync_status' => 'pending', 'confirmed_value' => null, 'confirmed_at' => null, 'updated_at' => now(), 'created_at' => now()]
        );
        // A reassigned key must not keep showing the previous send result.
        $assignmentId = DB::table('scale_key_assignments')->where('scale_configuration_id', $device->id)
            ->where('layer', $data['layer'])->where('key_number', $data['key_number'])->value('id');
        unset($this->scaleKeyResults[(int) $assignmentId]);
        Flux::toast(variant: 'success', text: __('Scale key layout saved.'));
    }

    public function sendScaleKey(int $id, TmScaleConnection $connection): string
    {
        Gate::authorize('company_settings.edit');
        abort_unless($this->companyId, 403);
        $device = ScaleConfiguration::query()->where('company_id', $this->companyId)->where('status', 'active')->first();
        $assignment = $device ? DB::table('scale_key_assignments')->where('scale_configuration_id', $device->id)->where('id', $id)->first() : null;
        if (! $assignment) {
            return 'ineligible';
        }
        $value = 0;
        if ($assignment->scale_product_mapping_id !== null) {
            $mapping = DB::table('scale_product_mappings')->where('scale_configuration_id', $device->id)->where('id', $assignment->scale_product_mapping_id)->first();
            if (! $mapping) {
                return 'ineligible';
            }
            $value = (int) $mapping->plu;
        }

        $query = DB::table('scale_key_assignments')->where('id', $assignment->id);
        try {
            $connection->sendShortcutKey($device->ip_address, $device->port, (int) $assignment->layer, (int) $assignment->key_number, $value);
        } catch (RuntimeException) {
            $query->update(['sync_status' => 'unverified', 'updated_at' => now()]);
            $this->scaleKeyResults[$id] = 'unverified';

            return 'unverified';
        }

        $query->update(['sync_status' => 'confirmed', 'confirmed_value' => $value, 'confirmed_at' => now(), 'updated_at' => now()]);
        app(RecordAuditEvent::class)->execute('master_data', 'confirm_scale_key', $device, null, [
            'layer' => (int) $assignment->layer, 'key' => (int) $assignment->key_number, 'plu' => $value,
        ]);
        $this->scaleKeyResults[$id] = 'confirmed';

        return 'confirmed';
    }

    public function saveScaleLabelTemplate(): void
    {
        Gate::authorize('company_settings.edit');
        abort_unless($this->companyId, 403);
        $data = $this->validate([
            'scaleLabelForm.name' => ['required', 'string', 'max:100'],
            'scaleLabelForm.width_mm' => ['required', 'integer', 'between:1,200'],
            'scaleLabelForm.height_mm' => ['required', 'integer', 'between:1,200'],
            'scaleLabelForm.barcode_type' => ['nullable', 'in:EAN13,Code128,none'],
            'scaleLabelForm.fields' => ['required', 'array', 'min:1'],
            'scaleLabelForm.fields.*' => ['in:name,weight,unit_price,total,date,plu,barcode'],
        ])['scaleLabelForm'];
        $device = ScaleConfiguration::query()->where('company_id', $this->companyId)->where('status', 'active')->firstOrFail();
        DB::table('scale_label_templates')->insert([
            'scale_configuration_id' => $device->id, 'name' => $data['name'], 'width_mm' => $data['width_mm'],
            'height_mm' => $data['height_mm'], 'fields' => json_encode($data['fields'], JSON_THROW_ON_ERROR),
            'barcode_type' => $data['barcode_type'], 'status' => 'draft', 'created_at' => now(), 'updated_at' => now(),
        ]);
        Flux::toast(variant: 'success', text: __('Scale label template saved for review.'));
    }

    public function deleteScaleLabelTemplate(int $id): void
    {
        Gate::authorize('company_settings.edit');
        abort_unless($this->companyId, 403);
        $device = ScaleConfiguration::query()->where('company_id', $this->companyId)->firstOrFail();
        $deleted = DB::table('scale_label_templates')->where('scale_configuration_id', $device->id)
            ->where('id', $id)->where('status', 'draft')->delete();
        if ($deleted) {
            app(RecordAuditEvent::class)->execute('master_data', 'delete_scale_label_template', $device, ['template_id' => $id], null);
            Flux::toast(variant: 'success', text: __('Scale label template deleted.'));
        }
    }

    public function setScaleTab(string $tab): void
    {
        $this->scaleTab = in_array($tab, ['products', 'keys', 'labels', 'operations'], true) ? $tab : 'products';
        $this->scaleKeySelected = null;
    }

    private function activeScaleDevice(): ?ScaleConfiguration
    {
        return $this->companyId
            ? ScaleConfiguration::query()->where('company_id', $this->companyId)->where('status', 'active')->first()
            : null;
    }

    /** Sellable products with an active kilogram sale unit at the scale outlet, matching code or name (max 8). */
    public function scaleProductMatches(): \Illuminate\Support\Collection
    {
        $term = trim($this->scaleProductSearch);
        $device = $this->activeScaleDevice();
        $store = $device ? $this->scaleOutlets()->find($device->store_id) : null;
        if (mb_strlen($term) < 2 || ! $store) {
            return collect();
        }
        $pattern = '%'.addcslashes($term, '%_\\').'%';

        return Product::query()->sellable()->availableToCompany($this->companyId, $store->branch_id, $store->id)
            ->whereHas('productUnits', fn ($query) => $query->where('is_sale_unit', true)
                ->whereHas('unit', fn ($unit) => $unit->where('code', 'KG')->where('dimension', 'weight')->where('status', 'active')))
            ->where(fn ($query) => $query->where('item_code', 'like', $pattern)->orWhere('name_ar', 'like', $pattern)->orWhere('name_en', 'like', $pattern))
            ->orderBy('item_code')->limit(8)->get(['id', 'item_code', 'name_ar', 'name_en']);
    }

    /** Product ids already linked on the active scale, used to flag search results. */
    public function scaleMappedProductIds(): array
    {
        $device = $this->activeScaleDevice();
        if (! $device) {
            return [];
        }

        return ProductUnit::query()->whereIn('id', DB::table('scale_product_mappings')->where('scale_configuration_id', $device->id)->pluck('product_unit_id'))
            ->pluck('product_id')->map(fn ($id) => (int) $id)->all();
    }

    public function pickScaleProduct(int $productId): void
    {
        Gate::authorize('company_settings.edit');
        $device = $this->activeScaleDevice();
        abort_unless($device, 403);
        $store = $this->scaleOutlets()->findOrFail($device->store_id);
        $product = Product::query()->sellable()->availableToCompany($this->companyId, $store->branch_id, $store->id)
            ->whereHas('productUnits', fn ($query) => $query->where('is_sale_unit', true)
                ->whereHas('unit', fn ($unit) => $unit->where('code', 'KG')->where('dimension', 'weight')->where('status', 'active')))
            ->find($productId, ['id', 'item_code', 'name_ar', 'name_en']);
        abort_unless($product, 404);

        $label = trim(substr(preg_replace('/[^ -~]/', '', Str::ascii((string) ($product->name_en ?: $product->item_code))) ?? '', 0, 20));
        $used = DB::table('scale_product_mappings')->where('scale_configuration_id', $device->id)->pluck('plu')->map(fn ($plu) => (int) $plu)->flip();
        $nextPlu = null;
        for ($plu = 1; $plu <= 4000; $plu++) {
            if (! $used->has($plu)) {
                $nextPlu = $plu;
                break;
            }
        }

        $this->scaleMapping = ['code' => (string) $product->item_code, 'plu' => $nextPlu ?? '', 'label' => $label !== '' ? $label : (string) $product->item_code];
        $this->scaleProductSearch = '';
        $this->resetValidation('scaleMapping.*');
    }

    public function setScaleKeyLayer(int $layer): void
    {
        $this->scaleKeyLayer = max(0, min(2, $layer));
        $this->scaleKeySelected = null;
    }

    public function selectScaleKey(int $key): void
    {
        Gate::authorize('company_settings.edit');
        abort_unless($key >= 1 && $key <= 63, 422);
        $this->scaleKeySelected = $this->scaleKeySelected === $key ? null : $key;
        $this->scaleKeyPickerSearch = '';
    }

    public function assignScaleKey(?int $mappingId = null): void
    {
        Gate::authorize('company_settings.edit');
        abort_unless($this->scaleKeySelected !== null, 422);
        $this->scaleKeyForm = ['layer' => $this->scaleKeyLayer, 'key_number' => $this->scaleKeySelected, 'mapping_id' => $mappingId];
        $this->saveScaleKeyAssignment();
        $this->scaleKeySelected = null;
        $this->scaleKeyPickerSearch = '';
    }

    /** Read-only: loads the shortcut-key table from the device so the grid can flag ERP/device differences. */
    public function readScaleKeysFromDevice(TmScaleConnection $scale): void
    {
        Gate::authorize('company_settings.edit');
        $device = $this->activeScaleDevice();
        $this->scaleDeviceKeys = null;
        if (! $device) {
            $this->scaleDeviceKeysResult = 'inactive';

            return;
        }
        try {
            $this->scaleDeviceKeys = $scale->readShortcutKeys($device->ip_address, $device->port);
            $this->scaleDeviceKeysResult = 'read';
        } catch (RuntimeException) {
            $this->scaleDeviceKeysResult = 'failed';
        }
    }

    public function saveScaleBarcodeSettings(): void
    {
        Gate::authorize('company_settings.edit');
        abort_unless($this->companyId, 403);
        $data = $this->validate([
            'scaleBarcodeForm.symbology' => ['required', 'in:EAN13,Code128'],
            'scaleBarcodeForm.prefix' => ['required', 'regex:/\A[0-9]{1,2}\z/'],
            'scaleBarcodeForm.item_source' => ['required', 'in:plu,item_code'],
            'scaleBarcodeForm.item_digits' => ['required', 'integer', 'between:4,7'],
            'scaleBarcodeForm.value_type' => ['required', 'in:weight,price'],
            'scaleBarcodeForm.value_digits' => ['required', 'integer', 'between:4,6'],
            'scaleBarcodeForm.value_decimals' => ['required', 'integer', 'between:0,3'],
            'scaleBarcodeForm.print_human_readable' => ['boolean'],
        ])['scaleBarcodeForm'];
        $data = array_replace($data, [
            'item_digits' => (int) $data['item_digits'], 'value_digits' => (int) $data['value_digits'],
            'value_decimals' => (int) $data['value_decimals'], 'print_human_readable' => (bool) ($data['print_human_readable'] ?? false),
        ]);
        if ($data['symbology'] === 'EAN13' && strlen($data['prefix']) + $data['item_digits'] + $data['value_digits'] !== 12) {
            throw ValidationException::withMessages(['scaleBarcodeForm.value_digits' => __('EAN-13 needs prefix + item code + value digits to total 12; the 13th digit is the check digit.')]);
        }
        if ($data['value_decimals'] >= $data['value_digits']) {
            throw ValidationException::withMessages(['scaleBarcodeForm.value_decimals' => __('Decimals must be fewer than the value digits.')]);
        }

        DB::transaction(function () use ($data): void {
            $device = ScaleConfiguration::query()->where('company_id', $this->companyId)->lockForUpdate()->firstOrFail();
            $before = $device->barcode_settings;
            $device->update(['barcode_settings' => $data]);
            app(RecordAuditEvent::class)->execute('master_data', 'update_scale_barcode_settings', $device, ['barcode_settings' => $before], ['barcode_settings' => $data]);
        });
        $this->scaleBarcodeForm = $data;
        Flux::toast(variant: 'success', text: __('Scale barcode settings saved.'));
    }

    /**
     * Builds a sample label barcode from the current form (unsaved edits included) so the layout can be checked
     * before saving: one segment per part, the final code, and an SVG when the code is encodable.
     */
    public function scaleBarcodePreview(): array
    {
        $form = $this->scaleBarcodeForm;
        $prefix = preg_replace('/\D/', '', (string) $form['prefix']) ?? '';
        $itemDigits = max(1, min(7, (int) $form['item_digits']));
        $valueDigits = max(1, min(6, (int) $form['value_digits']));
        $decimals = max(0, min(3, (int) $form['value_decimals']));
        $isWeight = $form['value_type'] === 'weight';

        $device = $this->activeScaleDevice();
        $sample = $device ? DB::table('scale_product_mappings')->where('scale_configuration_id', $device->id)->orderBy('plu')->first() : null;
        $itemValue = (string) ($sample?->plu ?? 1);
        if ($form['item_source'] === 'item_code' && $sample) {
            $itemValue = (string) (ProductUnit::query()->with('product:id,item_code')->find($sample->product_unit_id)?->product?->item_code ?? $itemValue);
        }
        $itemOverflow = ! ctype_digit($itemValue) || strlen($itemValue) > $itemDigits;
        $item = str_pad(substr(preg_replace('/\D/', '', $itemValue) ?: '1', -$itemDigits), $itemDigits, '0', STR_PAD_LEFT);

        $sampleValue = $isWeight ? '1.250' : '31.25';
        $raw = (string) (int) round((float) $sampleValue * (10 ** $decimals));
        $valueOverflow = strlen($raw) > $valueDigits;
        $value = str_pad(substr($raw, -$valueDigits), $valueDigits, '0', STR_PAD_LEFT);

        $segments = [
            ['key' => 'prefix', 'label' => __('Prefix'), 'digits' => $prefix],
            ['key' => 'item', 'label' => $form['item_source'] === 'plu' ? __('PLU') : __('Item code'), 'digits' => $item],
            ['key' => 'value', 'label' => $isWeight ? __('Weight') : __('Price'), 'digits' => $value],
        ];
        $body = $prefix.$item.$value;
        $code = $body;
        $valid = $prefix !== '';
        if ($form['symbology'] === 'EAN13') {
            $valid = $valid && strlen($body) === 12;
            if ($valid) {
                $sum = 0;
                for ($i = 0; $i < 12; $i++) {
                    $sum += (int) $body[$i] * ($i % 2 === 0 ? 1 : 3);
                }
                $check = (string) ((10 - ($sum % 10)) % 10);
                $segments[] = ['key' => 'check', 'label' => __('Check digit'), 'digits' => $check];
                $code = $body.$check;
            }
        }

        $svg = null;
        if ($valid) {
            try {
                $svg = app(\App\Modules\Pricing\Services\BarcodeSvgRenderer::class)->render($code, $form['symbology'] === 'EAN13' ? 'ean13' : 'code128', 56);
            } catch (\InvalidArgumentException) {
                $valid = false;
            }
        }

        return [
            'segments' => $segments, 'code' => $code, 'valid' => $valid, 'svg' => $svg,
            'length' => strlen($body) + ($form['symbology'] === 'EAN13' ? 1 : 0),
            'sample_value' => $sampleValue.' '.($isWeight ? 'kg' : ''), 'sample_item' => $itemValue,
            'item_overflow' => $itemOverflow, 'value_overflow' => $valueOverflow,
        ];
    }

    /** Read-only: shows the raw BAR section from the device. Its meaning is not verified, so it is never parsed. */
    public function readScaleBarcodeFormats(TmScaleConnection $scale): void
    {
        Gate::authorize('company_settings.edit');
        $device = $this->activeScaleDevice();
        $this->scaleDeviceBarcodeFormats = [];
        if (! $device) {
            $this->scaleDeviceBarcodeResult = 'inactive';

            return;
        }
        try {
            $this->scaleDeviceBarcodeFormats = $scale->readBarcodeFormats($device->ip_address, $device->port);
            $this->scaleDeviceBarcodeResult = $this->scaleDeviceBarcodeFormats === [] ? 'empty' : 'read';
        } catch (RuntimeException) {
            $this->scaleDeviceBarcodeResult = 'failed';
        }
    }

    public function readScaleProduct(TmScaleConnection $scale): void
    {
        Gate::authorize('company_settings.edit');
        abort_unless($this->companyId, 403);
        $this->scaleProduct = null;
        $this->scaleProductResult = null;
        $validated = $this->validate(['scaleProductNumber' => ['required', 'integer', 'between:1,4000']]);
        $device = ScaleConfiguration::query()->where('company_id', $this->companyId)->where('status', 'active')->first();
        if ($device === null) {
            $this->scaleProductResult = 'inactive';

            return;
        }

        try {
            $this->scaleProduct = $scale->readProduct($device->ip_address, $device->port, (int) $validated['scaleProductNumber']);
            $this->scaleProductResult = $this->scaleProduct === null ? 'empty' : 'read';
        } catch (RuntimeException) {
            $this->scaleProductResult = 'failed';
        }
    }

    public function readScaleReport(TmScaleConnection $scale): void
    {
        Gate::authorize('company_settings.edit');
        abort_unless($this->companyId, 403);
        $this->scaleReport = [];
        $this->scaleReportResult = null;
        $device = ScaleConfiguration::query()->where('company_id', $this->companyId)->where('status', 'active')->first();
        if ($device === null) {
            $this->scaleReportResult = 'inactive';

            return;
        }

        try {
            $this->scaleReport = $scale->readSalesReport($device->ip_address, $device->port);
            $this->scaleReportResult = $this->scaleReport === [] ? 'empty' : 'read';
        } catch (RuntimeException) {
            $this->scaleReportResult = 'failed';
        }
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
            'printerForm.paper_size' => ['required', 'string', 'in:80mm,58mm,a4,a5,label_50x30mm,label_40x25mm'],
            'printerForm.template_name' => ['required', 'string', 'max:100'],
            'printerForm.print_template_id' => ['nullable', 'integer', 'exists:print_templates,id'],
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

        $this->printerModalOpen = false;

        Flux::toast(variant: 'success', text: __('Printer configuration saved successfully.'));
    }

    public function editPrinter(int $id): void
    {
        Gate::authorize('manage-settings');

        $this->printerSection = 'printer-profiles';

        $printer = PrinterConfiguration::visibleTo(auth()->user())->findOrFail($id);
        $this->printerForm = $printer->toArray();
        $this->printerForm['scope_type'] = $printer->store_id !== null ? 'store' : ($printer->branch_id !== null ? 'branch' : 'global');
        $this->resetValidation();
        $this->printerModalOpen = true;
    }

    public function openPrinterModal(): void
    {
        Gate::authorize('manage-settings');
        $this->resetPrinterForm();
        $this->resetValidation();
        $this->printerModalOpen = true;
    }

    public function updatedPrinterFormScopeType(string $scope): void
    {
        if ($scope === 'global') {
            $this->printerForm['branch_id'] = null;
            $this->printerForm['store_id'] = null;
        } elseif ($scope === 'branch') {
            $this->printerForm['store_id'] = null;
        }
    }

    public function updatedPrinterFormBranchId(): void
    {
        $this->printerForm['store_id'] = null;
    }

    public function updatedPrinterFormPrinterType(string $type): void
    {
        $this->printerForm['paper_size'] = match ($type) {
            'thermal' => '80mm', 'label' => 'label_50x30mm', default => 'a4',
        };
        $this->printerForm['print_template_id'] = null;
    }

    public function updatedPrinterFormPaperSize(): void
    {
        $this->printerForm['print_template_id'] = null;
    }

    public function savePrintTemplate(SavePrintTemplateAction $action): void
    {
        $validated = $this->validate([
            'templateForm.code' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_-]+$/'],
            'templateForm.name_ar' => ['required', 'string', 'max:255'],
            'templateForm.name_en' => ['required', 'string', 'max:255'],
            'templateForm.document_type' => ['required', Rule::in(SavePrintTemplateAction::DOCUMENT_TYPES)],
            'templateForm.paper_size' => ['required', Rule::in(SavePrintTemplateAction::PAPER_SIZES)],
            'templateForm.language' => ['required', Rule::in(SavePrintTemplateAction::LANGUAGES)],
            'templateForm.header_text' => ['nullable', 'string', 'max:2000'],
            'templateForm.footer_text' => ['nullable', 'string', 'max:2000'],
            'templateForm.layout_settings.width_mm' => ['required_if:templateForm.document_type,barcode_label', 'numeric', 'min:20', 'max:210'],
            'templateForm.layout_settings.height_mm' => ['required_if:templateForm.document_type,barcode_label', 'numeric', 'min:15', 'max:297'],
            'templateForm.layout_settings.dpi' => ['required_if:templateForm.document_type,barcode_label', 'integer', 'in:203,300'],
            'templateForm.layout_settings.orientation' => ['required_if:templateForm.document_type,barcode_label', 'in:portrait,landscape'],
            'templateForm.layout_settings.margin_mm' => ['nullable', 'numeric', 'min:0', 'max:20'],
            'templateForm.layout_settings.gap_mm' => ['nullable', 'numeric', 'min:0', 'max:20'],
            'templateForm.layout_settings.horizontal_gap_mm' => ['nullable', 'numeric', 'min:0', 'max:20'],
            'templateForm.layout_settings.vertical_gap_mm' => ['nullable', 'numeric', 'min:0', 'max:20'],
            'templateForm.layout_settings.a4_rows' => ['nullable', 'integer', 'min:1', 'max:20'],
            'templateForm.layout_settings.a4_columns' => ['nullable', 'integer', 'min:1', 'max:10'],
            'templateForm.layout_settings.symbology' => ['required_if:templateForm.document_type,barcode_label', 'in:auto,ean13,code128'],
            'templateForm.layout_settings.show_name' => ['boolean'], 'templateForm.layout_settings.show_price' => ['boolean'], 'templateForm.layout_settings.show_item_code' => ['boolean'], 'templateForm.layout_settings.show_list_code' => ['boolean'], 'templateForm.layout_settings.show_outlet' => ['boolean'],
            'templateForm.status' => ['required', 'in:active,inactive'],
        ]);
        $action->execute($validated['templateForm'], $this->templateForm['id'] ?? null);
        $this->resetPrintTemplateForm();
        $this->templateModalOpen = false;
        Flux::toast(variant: 'success', text: __('Print template saved successfully.'));
    }

    public function editPrintTemplate(int $id): void
    {
        Gate::authorize('manage-settings');

        $this->templateForm = PrintTemplate::visibleTo(auth()->user())->findOrFail($id)->toArray();
        $this->templateForm['layout_settings'] = array_replace(['width_mm' => 50, 'height_mm' => 30, 'dpi' => 203, 'orientation' => 'portrait', 'margin_mm' => 1, 'gap_mm' => 2, 'horizontal_gap_mm' => 2, 'vertical_gap_mm' => 2, 'a4_rows' => 8, 'a4_columns' => 3, 'symbology' => 'auto', 'show_name' => true, 'show_price' => true, 'show_item_code' => true, 'show_list_code' => true, 'show_outlet' => true], $this->templateForm['layout_settings'] ?? []);
        $this->resetValidation();
        $this->templateModalOpen = true;
    }

    public function openPrintTemplateModal(): void
    {
        Gate::authorize('manage-settings');
        $this->resetPrintTemplateForm();
        $this->resetValidation();
        $this->templateModalOpen = true;
    }

    public function resetPrintTemplateForm(): void
    {
        $this->templateForm = ['id' => null, 'code' => '', 'name_ar' => '', 'name_en' => '', 'document_type' => 'sales_invoice', 'paper_size' => '80mm', 'language' => 'bilingual', 'header_text' => '', 'footer_text' => '', 'layout_settings' => ['width_mm' => 50, 'height_mm' => 30, 'dpi' => 203, 'orientation' => 'portrait', 'margin_mm' => 1, 'gap_mm' => 2, 'horizontal_gap_mm' => 2, 'vertical_gap_mm' => 2, 'a4_rows' => 8, 'a4_columns' => 3, 'symbology' => 'auto', 'show_name' => true, 'show_price' => true, 'show_item_code' => true, 'show_list_code' => true, 'show_outlet' => true], 'status' => 'active'];
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
            'print_template_id' => null,
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
        <x-context-help :title="__('General settings help')" :label="__('Open general settings help')">
            <ul>
                <li>{{ __('Company identity and timezone are shared defaults for the application. Branch screens do not edit timezone.') }}</li>
                <li>{{ __('Payment, tax, numbering, printer, and template settings remain in their dedicated sections.') }}</li>
                <li>{{ __('Search and filters remain available on list screens even when their panel is closed by default.') }}</li>
                <li>{{ __('Validation, approval, and transactional warnings always remain visible.') }}</li>
            </ul>
        </x-context-help>
        <flux:badge size="sm" color="zinc" icon="adjustments-horizontal">{{ __('Configuration') }}</flux:badge>
    </x-slot:actions>

    <div class="settings-screen__content space-y-6" x-data="{ dirty: $wire.entangle('companyDirty') }">

    <?php if ($errors->any()) { ?>
        <flux:callout variant="danger" icon="exclamation-triangle" title="{{ __('Validation Errors') }}">
            <p class="text-sm font-medium">{{ __('Please review and correct the following validation errors:') }}</p>
            <ul class="mt-2 list-disc list-inside space-y-1 text-sm">
                <?php foreach ($errors->all() as $error) { ?>
                    <li>{{ $error }}</li>
                <?php } ?>
            </ul>
        </flux:callout>
    <?php } ?>

    <?php
        $activeTab = in_array($activeTab, ['company', 'payments', 'tax', 'sequences', 'printers', 'audit'], true) ? $activeTab : 'company';
$sectionMeta = [
    'company' => [__('Company Identity'), __('Company identity and contact details.')],
    'payments' => [__('Payment Methods'), __('Payment names, evidence, and offline eligibility.')],
    'tax' => [__('Tax rules'), __('Tax treatment and price display defaults.')],
    'sequences' => [__('Document numbering'), __('Prefixes, counters, and reset timing.')],
    'printers' => [__('Printers and connected devices'), __('Printer, scale, and barcode scanner setup.')],
    'audit' => [__('Configuration Change History'), __('Read-only history of setting changes.')],
][$activeTab] ?? [__('Company Identity'), __('Company identity and contact details.')];
?>

    <flux:card class="settings-screen__summary space-y-1 border-s-4 border-primary bg-surface shadow-card" data-settings-section-summary="{{ $activeTab }}">
        <flux:heading size="lg">{{ $sectionMeta[0] }}</flux:heading>
        <flux:subheading>{{ $sectionMeta[1] }}</flux:subheading>
    </flux:card>

    <!-- TAB 1: Company Identity -->
    <?php if ($activeTab === 'company') { ?>
        <div id="panel-company" role="tabpanel" aria-labelledby="tab-company" class="space-y-6">
            <?php if ($companyEditingBlocked) { ?>
                <flux:callout variant="danger" icon="exclamation-triangle" title="{{ __('Validation Errors') }}">
                    {{ __('company.duplicate_load') }}
                </flux:callout>
            <?php } else { ?>
            <form
                wire:submit="saveCompany"
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
                            placeholder="3allaf Commercial Co."
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
                            placeholder="3allaf Company"
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

                        <flux:input wire:model="companyForm.timezone" :label="__('Timezone')" placeholder="Africa/Cairo" required dir="ltr" />

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

                    <div class="flex flex-wrap items-center justify-end gap-3">
                        <flux:button type="button" :href="route('admin.settings', ['tab' => 'payments'])" variant="subtle" wire:navigate>{{ __('Next') }}</flux:button>
                        <x-actions.loading-button type="submit" action="saveCompany" :label="__('Save')" x-bind:disabled="!dirty" data-guide="settings-save-button" />
                    </div>
                </flux:card>

            </form>

            <flux:card class="space-y-4">
                <div class="flex items-center gap-2"><flux:heading size="lg">{{ __('List display') }}</flux:heading><x-context-help :title="__('List display')" :label="__('Open contextual help')"><p>{{ __('Choose whether list search and filter panels open automatically.') }}</p></x-context-help></div>
                <div><flux:switch wire:model="showSearchFiltersByDefault" :label="__('Show search and filters by default')" /><x-context-help :title="__('Show search and filters by default')" :label="__('Open contextual help')"><p>{{ __('When off, use the compact Filter / Search control to open them when needed.') }}</p></x-context-help></div>
                <div class="flex justify-end"><flux:button type="button" variant="primary" wire:click="saveListDisplayPreference" wire:loading.attr="disabled" wire:target="saveListDisplayPreference">{{ __('Save list display') }}</flux:button></div>
            </flux:card>

            <?php } ?>
        </div>
    <?php } ?>

    <!-- TAB 2: Payment Methods -->
    <?php if ($activeTab === 'payments') { ?>
        <div id="panel-payments" role="tabpanel" aria-labelledby="tab-payments" class="space-y-6">
            @can('company_settings.view')
                <flux:button :href="route('pos.financial-readiness', ['company_id' => $companyId])" variant="subtle" icon="cog-6-tooth">{{ __('Cash rounding denomination') }}</flux:button>
            @endcan
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2"><flux:heading size="lg">{{ __('Payment Methods') }}</flux:heading><x-context-help :title="__('Payment-method help')" :label="__('Open payment-method help')"><p>{{ __('Choose the business name customers and staff recognize. The method type remains its accounting classification.') }}</p></x-context-help></div>
                <flux:button type="button" variant="primary" icon="plus" wire:click="openPaymentMethodModal">{{ __('Add Payment Method') }}</flux:button>
            </div>
            <flux:modal wire:model="paymentModalOpen" class="max-w-2xl">
            <flux:card class="space-y-4">
                <flux:heading size="lg">
                    {{ $paymentMethodForm['id'] ? __('Edit Payment Method') : __('Add Payment Method') }}
                </flux:heading>
                <form wire:submit="savePaymentMethod" class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <flux:input
                            autofocus
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
                            <x-context-help :title="__('Requires payment evidence')" :label="__('Open contextual help')"><p>{{ __('When enabled, staff must attach or reference payment evidence before this payment can be approved.') }}</p></x-context-help>
                        </div>

                    </div>

                    <div class="flex flex-wrap items-center justify-end gap-3">
                        <?php if ($paymentMethodForm['id']) { ?>
                            <flux:button type="button" wire:click="resetPaymentMethodForm" variant="subtle">
                                {{ __('Cancel Edit') }}
                            </flux:button>
                        <?php } ?>

                        <flux:button type="button" variant="subtle" wire:click="$set('paymentModalOpen', false)">{{ __('Cancel') }}</flux:button>
                        <x-actions.loading-button type="submit" action="savePaymentMethod" :label="__('Save')" />
                    </div>
                </form>
            </flux:card>
            </flux:modal>

            <flux:card class="space-y-4">
                <div class="flex items-center gap-2"><flux:heading size="lg">{{ __('Configured Payment Methods') }}</flux:heading><x-context-help :title="__('Configured Payment Methods')" :label="__('Open contextual help')"><p>{{ __('Review saved methods below. Add a new method using the form above.') }}</p></x-context-help></div>

                <x-tables.filter-bar>
                    <flux:input wire:model.live.debounce.300ms="masterListSearch" icon="magnifying-glass" :label="__('Search')" :placeholder="__('Search by code or name...')" clearable />
                </x-tables.filter-bar>

                <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
                    <flux:table class="min-w-[880px]" aria-label="{{ __('Configured Payment Methods') }}">
                        <?php
                        $searchTerm = trim($masterListSearch);
        $paymentMethods = PaymentMethod::query()
            ->when($searchTerm !== '', function ($query) use ($searchTerm): void {
                $query->where(function ($query) use ($searchTerm): void {
                    $pattern = '%'.$searchTerm.'%';
                    $query->where('code', 'like', $pattern)
                        ->orWhere('name_ar', 'like', $pattern)
                        ->orWhere('name_en', 'like', $pattern);
                });
            })
            ->orderBy('code')
            ->paginate(25);
        ?>

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
                        <?php if ($paymentMethods->isNotEmpty()) { ?>
                            <?php foreach ($paymentMethods as $method) { ?>
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
                                    <?php if ($method->requires_evidence) { ?>
                                        <flux:badge size="sm" color="amber"><span style="color: light-dark(#78350f, #fde68a)">{{ __('Required') }}</span></flux:badge>
                                    <?php } else { ?>
                                        <flux:badge size="sm" color="zinc">{{ __('Not required') }}</flux:badge>
                                    <?php } ?>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <?php if ($method->offline_eligible) { ?>
                                        <flux:badge size="sm" color="green">{{ __('Allowed by policy') }}</flux:badge>
                                    <?php } else { ?>
                                        <flux:badge size="sm" color="zinc">{{ __('Not allowed') }}</flux:badge>
                                    <?php } ?>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <?php if ($method->status === 'active') { ?>
                                        <flux:badge size="sm" color="green">{{ __('Active') }}</flux:badge>
                                    <?php } else { ?>
                                        <flux:badge size="sm" color="red">{{ __('Inactive') }}</flux:badge>
                                    <?php } ?>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:button size="xs" variant="subtle" wire:click="editPaymentMethod({{ $method->id }})">
                                        {{ __('Edit') }}
                                    </flux:button>
                                </flux:table.cell>
                            </flux:table.row>
                            <?php } ?>
                        <?php } else { ?>
                            <flux:table.row>
                                <flux:table.cell colspan="7" class="text-center py-4">
                                    <x-state.empty
                                        :title="$searchTerm === '' ? __('No Payment Methods Configured') : __('No results')"
                                        :description="$searchTerm === '' ? __('No payment methods are configured yet. Add one using the form above.') : ''"
                                        icon="credit-card"
                                    />
                                </flux:table.cell>
                            </flux:table.row>
                        <?php } ?>
                        </flux:table.rows>
                    </flux:table>
                </div>
                <x-tables.pagination :paginator="$paymentMethods" :page-sizes="[25]" />
            </flux:card>
        </div>
    <?php } ?>

    <!-- TAB 3: Tax Settings -->
    <?php if ($activeTab === 'tax') { ?>
        <div id="panel-tax" role="tabpanel" aria-labelledby="tab-tax" class="space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2"><flux:heading size="lg">{{ __('Taxes') }}</flux:heading><x-context-help :title="__('Tax configuration help')" :label="__('Open tax configuration help')"><ul class="list-disc space-y-2 ps-5"><li>{{ __('Company default tax applies when a product has no specific tax assignment.') }}</li><li>{{ __('A product-specific tax can be selected on the product card and overrides only that product default assignment.') }}</li><li>{{ __('Tax-inclusive pricing contains tax in the displayed price; tax-exclusive pricing adds tax to the displayed price.') }}</li><li>{{ __('Historical document tax snapshots do not change when a tax rule changes.') }}</li></ul></x-context-help></div>
                <flux:button type="button" variant="primary" icon="plus" wire:click="openTaxSettingModal">{{ __('Add Tax') }}</flux:button>
            </div>
            <flux:modal wire:model="taxModalOpen" class="max-w-2xl">
            <flux:card class="space-y-4">
                <flux:heading size="lg">
                    {{ $taxSettingForm['id'] ? __('Edit Tax Rule') : __('Add Tax Rule') }}
                </flux:heading>

                <form wire:submit="saveTaxSetting" class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <flux:input
                            autofocus
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

                        <flux:select wire:model="taxSettingForm.status" :label="__('Status')">
                            <option value="active">{{ __('Active') }}</option>
                            <option value="inactive">{{ __('Inactive') }}</option>
                        </flux:select>
                    </div>

                    <div class="grid gap-3 md:grid-cols-2">
                        <flux:switch
                            wire:model="taxSettingForm.is_default"
                            align="left"
                            :label="__('Company Default Tax Rule')"
                        />
                        <flux:switch
                            wire:model="taxSettingForm.is_tax_inclusive"
                            align="left"
                            :label="__('Default Prices Are Tax Inclusive')"
                        />
                    </div>
                    <x-context-help :title="__('Default Prices Are Tax Inclusive')" :label="__('Open contextual help')"><p>{{ __('Tax inclusive means the displayed price already includes the tax amount.') }}</p></x-context-help>

                    <div class="flex flex-wrap items-center justify-end gap-3">
                        <?php if ($taxSettingForm['id']) { ?>
                            <flux:button type="button" wire:click="resetTaxSettingForm" variant="subtle">
                                {{ __('Cancel Edit') }}
                            </flux:button>
                        <?php } ?>

                        <flux:button type="button" variant="subtle" wire:click="$set('taxModalOpen', false)">{{ __('Cancel') }}</flux:button>
                        <x-actions.loading-button type="submit" action="saveTaxSetting" :label="__('Save')" />
                    </div>
                </form>
            </flux:card>
            </flux:modal>

            <flux:card class="space-y-4">
                <flux:heading size="lg">{{ __('Configured Tax Settings') }}</flux:heading>

                <x-tables.filter-bar>
                    <flux:input wire:model.live.debounce.300ms="masterListSearch" icon="magnifying-glass" :label="__('Search')" :placeholder="__('Search by code or name...')" clearable />
                </x-tables.filter-bar>

                <?php
                    $searchTerm = trim($masterListSearch);
        $taxRows = TaxSetting::query()
            ->forCompany((int) $companyId)
            ->when($searchTerm !== '', function ($query) use ($searchTerm): void {
                $query->where(function ($query) use ($searchTerm): void {
                    $pattern = '%'.$searchTerm.'%';
                    $query->where('code', 'like', $pattern)
                        ->orWhere('name_ar', 'like', $pattern)
                        ->orWhere('name_en', 'like', $pattern);
                });
            })
            ->orderBy('code')
            ->paginate(25);
        ?>

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
                        <?php if ($taxRows->isNotEmpty()) {
                            foreach ($taxRows as $tax) { ?>
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
                                    <?php if ($tax->rate !== null) { ?>
                                        <span class="font-mono font-semibold">{{ number_format((float)$tax->rate, 2) }}%</span>
                                    <?php } else { ?>
                                        <flux:badge size="sm" color="amber"><span style="color: light-dark(#78350f, #fde68a)">{{ __('Not configured') }}</span></flux:badge>
                                    <?php } ?>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <?php if ($tax->is_tax_inclusive) { ?>
                                        <flux:badge size="sm" color="zinc">{{ __('Inclusive') }}</flux:badge>
                                    <?php } else { ?>
                                        <flux:badge size="sm" color="zinc">{{ __('Exclusive') }}</flux:badge>
                                    <?php } ?>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <?php if ($tax->is_default) { ?>
                                        <flux:badge size="sm" color="green">{{ __('Default') }}</flux:badge>
                                    <?php } else { ?>
                                        <flux:badge size="sm" color="zinc">{{ __('No') }}</flux:badge>
                                    <?php } ?>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <?php if ($tax->status === 'active') { ?>
                                        <flux:badge size="sm" color="green">{{ __('Active') }}</flux:badge>
                                    <?php } else { ?>
                                        <flux:badge size="sm" color="red">{{ __('Inactive') }}</flux:badge>
                                    <?php } ?>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:button size="xs" variant="subtle" wire:click="editTaxSetting({{ $tax->id }})">
                                        {{ __('Edit') }}
                                    </flux:button>
                                </flux:table.cell>
                            </flux:table.row>
                        <?php }
                            } else { ?>
                            <flux:table.row>
                                <flux:table.cell colspan="8" class="text-center py-4">
                                    <x-state.empty
                                        :title="$searchTerm === '' ? __('No Tax Rules Configured') : __('No results')"
                                        :description="$searchTerm === '' ? __('No tax rules are configured yet. Add one using the form above.') : ''"
                                        icon="receipt-percent"
                                    />
                                </flux:table.cell>
                            </flux:table.row>
                        <?php } ?>
                    </flux:table.rows>
                </flux:table>
                <x-tables.pagination :paginator="$taxRows" :page-sizes="[25]" />
            </flux:card>
        </div>
    <?php } ?>

    <!-- TAB 4: Document Sequences -->
    <?php if ($activeTab === 'sequences') { ?>
        <div id="panel-sequences" role="tabpanel" aria-labelledby="tab-sequences" class="space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-3"><div class="flex items-center gap-2"><flux:heading size="lg">{{ __('Document Numbering') }}</flux:heading><x-context-help :title="__('Document Numbering')" :label="__('Open contextual help')"><p>{{ __('Each branch and document type keeps its own continuous counter.') }}</p></x-context-help></div><flux:button type="button" variant="primary" icon="plus" wire:click="openDocumentSequenceModal">{{ __('Add Numbering Rule') }}</flux:button></div>
            <flux:modal wire:model="sequenceModalOpen" class="max-w-3xl">
            <flux:card class="space-y-6">
                <div class="flex items-center gap-2">
                    <flux:heading size="lg">
                        {{ $documentSequenceForm['id'] ? __('Edit document-numbering rule') : __('Add document-numbering rule') }}
                    </flux:heading>
                    <x-context-help :title="$documentSequenceForm['id'] ? __('Edit document-numbering rule') : __('Add document-numbering rule')" :label="__('Open contextual help')"><p>{{ __('Set the document code, where it applies, and how its next number is displayed.') }}</p></x-context-help>
                </div>

                <form wire:submit="saveDocumentSequence" class="space-y-5">
                    <section class="space-y-4 rounded-xl border border-border-subtle bg-surface-muted/40 p-4" aria-labelledby="sequence-basics-heading">
                        <div class="flex items-center gap-2">
                            <flux:heading id="sequence-basics-heading" size="sm">{{ __('Rule details') }}</flux:heading>
                            <x-context-help :title="__('Rule details')" :label="__('Open contextual help')"><p>{{ __('Choose the document and the level that uses this numbering rule.') }}</p></x-context-help>
                        </div>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 {{ ($documentSequenceForm['scope_type'] ?? 'company') === 'branch' ? 'xl:grid-cols-3' : '' }}">
                        <flux:select autofocus wire:model.live="documentSequenceForm.document_type" :label="__('Document type')" required>
                            @foreach (DocumentTypeCatalog::NUMBERING_TYPES as $type => $definition)
                                <option value="{{ $type }}">{{ __($definition['label']) }}</option>
                            @endforeach
                        </flux:select>

                        <?php if (($documentSequenceForm['scope_type'] ?? 'branch') === 'branch') { ?>
                            <?php $activeBranches = Branch::visibleTo(auth()->user())->where('status', 'active')->orderBy('code')->get(); ?>
                            <flux:select wire:model="documentSequenceForm.scope_id" :label="__('Branch')" required>
                                <option value="">{{ __('Select an active branch...') }}</option>
                                <?php foreach ($activeBranches as $branch) { ?>
                                    <option value="{{ $branch->id }}">{{ $branch->code }} — {{ str_starts_with(app()->getLocale(), 'ar') ? $branch->name_ar : $branch->name_en }}</option>
                                <?php } ?>
                            </flux:select>
                        <?php } else { ?><flux:input :label="__('Branch')" :value="__('Legacy company-wide rule')" readonly /><?php } ?>
                        </div>
                    </section>

                    <section class="space-y-4" aria-labelledby="sequence-format-heading">
                        <div class="flex items-center gap-2">
                            <flux:heading id="sequence-format-heading" size="sm">{{ __('Number format') }}</flux:heading>
                            <x-context-help :title="__('Number format')" :label="__('Open contextual help')"><p>{{ __('Arrange the text and digits exactly as they should appear on the document.') }}</p></x-context-help>
                        </div>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <flux:input wire:model="documentSequenceForm.padding_length" :label="__('Counter digits (padding)')" type="number" readonly />
                        <flux:input :label="__('Sequence policy')" :value="__('Continuous — never resets')" readonly />
                        </div>
                    </section>

                    <section class="space-y-4 rounded-xl border border-border-subtle p-4" aria-labelledby="sequence-counter-heading">
                        <div class="flex items-center gap-2">
                            <flux:heading id="sequence-counter-heading" size="sm">{{ __('Counter settings') }}</flux:heading>
                            <x-context-help :title="__('Counter settings')" :label="__('Open contextual help')"><p>{{ __('Choose the first number and whether counting restarts on a schedule.') }}</p></x-context-help>
                        </div>
                        <div class="grid grid-cols-1 gap-4">
                        <div><flux:input
                            wire:model="documentSequenceForm.next_value"
                            :label="$documentSequenceForm['id'] ? __('Current next number (read-only)') : __('First number to use')"
                            type="number"
                            :readonly="(bool) $documentSequenceForm['id']"
                            :required="! $documentSequenceForm['id']"
                        /><x-context-help :title="$documentSequenceForm['id'] ? __('Current next number (read-only)') : __('First number to use')" :label="__('Open contextual help')"><p>{{ $documentSequenceForm['id'] ? __('Shown for information while editing. Use the separate authorized correction below to change it.') : __('First number allocated by this sequence.') }}</p></x-context-help></div>
                        </div>
                    </section>

                    <?php
                            $previewValue = (int) ($documentSequenceForm['next_value'] ?? 1);
        $previewPrefix = ($documentSequenceForm['scope_type'] ?? 'branch') === 'branch'
            ? (($previewBranch = $activeBranches->firstWhere('id', (int) ($documentSequenceForm['scope_id'] ?? 0)))
                ? DocumentTypeCatalog::prefix($previewBranch->code, (string) $documentSequenceForm['document_type'])
                : __('BRANCH-TYPE-'))
            : (string) ($documentSequenceForm['prefix'] ?? '');
        $previewNumber = $previewPrefix.str_pad((string) $previewValue, 6, '0', STR_PAD_LEFT);
        ?>
                    <div class="rounded-xl border border-primary/25 bg-primary/5 px-5 py-4" data-sequence-preview>
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <flux:heading size="sm">{{ __('Preview of the next document number') }}</flux:heading>
                                <x-context-help :title="__('Preview of the next document number')" :label="__('Open contextual help')"><p>{{ __('Changes here do not reserve a number until you save the rule.') }}</p></x-context-help>
                            </div>
                            <output class="rounded-lg bg-surface px-4 py-2 font-mono text-lg font-semibold tracking-wide text-primary shadow-sm" aria-live="polite">{{ $previewNumber }}</output>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center justify-end gap-3">
                        <?php if ($documentSequenceForm['id']) { ?>
                            <flux:button type="button" wire:click="resetDocumentSequenceForm" variant="subtle">
                                {{ __('Cancel Edit') }}
                            </flux:button>
                        <?php } ?>

                        <flux:button type="button" variant="subtle" wire:click="$set('sequenceModalOpen', false)">{{ __('Cancel') }}</flux:button>
                        <x-actions.loading-button type="submit" action="saveDocumentSequence" :label="__('Save')" />
                    </div>
                </form>

                <?php if ($documentSequenceForm['id']) { ?>
                    <?php if (Gate::allows('drawers_payments_tax_numbering_printers.override')) { ?>
                        <form wire:submit="overrideSequenceCounter" class="space-y-4 border-t border-border-subtle pt-5" aria-labelledby="sequence-override-heading">
                            <div class="flex items-center gap-2">
                                <flux:heading id="sequence-override-heading" size="md">{{ __('Authorized counter correction') }}</flux:heading>
                                <x-context-help :title="__('Authorized counter correction')" :label="__('Open contextual help')"><p>{{ __('Use this separate action to change the current counter. Enter a reason; the change is audited.') }}</p></x-context-help>
                            </div>
                            <div class="grid gap-4 md:grid-cols-2">
                                <flux:input wire:model="sequenceOverride.next_value" type="number" min="1" :label="__('Replacement next number')" required />
                                <flux:textarea wire:model="sequenceOverride.reason" :label="__('Reason for correction')" required />
                            </div>
                            <div class="flex justify-end"><flux:button type="submit" variant="danger" wire:confirm="{{ __('Change this document counter? The action is permanent and audited.') }}" wire:loading.attr="disabled" wire:target="overrideSequenceCounter"><span wire:loading.remove wire:target="overrideSequenceCounter">{{ __('Submit counter correction') }}</span><span wire:loading wire:target="overrideSequenceCounter">{{ __('Saving...') }}</span></flux:button></div>
                        </form>
                    <?php } ?>
                <?php } ?>
            </flux:card>
            </flux:modal>

            <flux:card class="space-y-4">
                <flux:heading size="lg">{{ __('Document-numbering rules') }}</flux:heading>

                <x-tables.filter-bar>
                    <flux:input wire:model.live.debounce.300ms="masterListSearch" icon="magnifying-glass" :label="__('Search')" :placeholder="__('Search')" clearable />
                </x-tables.filter-bar>

                <?php
        $searchTerm = trim($masterListSearch);
        $documentSequences = DocumentSequence::visibleTo(auth()->user())
            ->with('scopeBranch')
            ->when($searchTerm !== '', function ($query) use ($searchTerm): void {
                $query->where(function ($query) use ($searchTerm): void {
                    $pattern = '%'.$searchTerm.'%';
                    $query->where('document_type', 'like', $pattern)
                        ->orWhere('scope_key', 'like', $pattern)
                        ->orWhere('prefix', 'like', $pattern)
                        ->orWhere('suffix', 'like', $pattern)
                        ->orWhereHas('scopeBranch', function ($branchQuery) use ($pattern): void {
                            $branchQuery->where('code', 'like', $pattern)
                                ->orWhere('name_ar', 'like', $pattern)
                                ->orWhere('name_en', 'like', $pattern);
                        });
                });
            })
            ->orderBy('document_type')
            ->orderBy('scope_key')
            ->paginate(25);
        ?>

                <flux:table aria-label="{{ __('Document-numbering rules') }}">
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
                        <?php if ($documentSequences->isNotEmpty()) { ?>
                            <?php foreach ($documentSequences as $seq) { ?>
                            <flux:table.row key="seq-{{ $seq->id }}">
                                <flux:table.cell class="font-bold">{{ DocumentTypeCatalog::numberingLabel($seq->document_type) }}</flux:table.cell>
                                <flux:table.cell>
                                    <?php if ($seq->scope_type === 'branch') { ?>
                                        <flux:badge size="sm" color="blue">
                                            {{ __('Branch') }}: {{ str_starts_with(app()->getLocale(), 'ar') ? ($seq->scopeBranch?->name_ar ?? __('Unknown branch')) : ($seq->scopeBranch?->name_en ?? __('Unknown branch')) }}
                                            <span class="text-xs opacity-70">({{ $seq->scopeBranch?->code ?? $seq->scope_id }})</span>
                                        </flux:badge>
                                    <?php } else { ?>
                                        <flux:badge size="sm" color="zinc">{{ __('Company-wide') }}</flux:badge>
                                    <?php } ?>
                                </flux:table.cell>
                                <flux:table.cell class="font-mono text-xs text-primary">{{ $seq->formatValue((int) $seq->next_value) }}</flux:table.cell>
                                <flux:table.cell class="font-mono font-semibold">{{ $seq->next_value }}</flux:table.cell>
                                <flux:table.cell><flux:badge size="sm" color="zinc">{{ __(Str::headline($seq->reset_rule)) }}</flux:badge></flux:table.cell>
                                <flux:table.cell>
                                    <?php if ($seq->status === 'active') { ?>
                                        <flux:badge size="sm" color="green">{{ __('Active') }}</flux:badge>
                                    <?php } else { ?>
                                        <flux:badge size="sm" color="red">{{ __('Inactive') }}</flux:badge>
                                    <?php } ?>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:button size="xs" variant="subtle" wire:click="editDocumentSequence({{ $seq->id }})">
                                        {{ __('Edit') }}
                                    </flux:button>
                                </flux:table.cell>
                            </flux:table.row>
                            <?php } ?>
                        <?php } else { ?>
                            <flux:table.row>
                                <flux:table.cell colspan="7" class="text-center py-4">
                                    <x-state.empty
                                        :title="$searchTerm === '' ? __('No Document Sequences Configured') : __('No results')"
                                        :description="$searchTerm === '' ? __('No document sequences are configured yet. Add one using the form above.') : ''"
                                        icon="numbered-list"
                                    />
                                </flux:table.cell>
                            </flux:table.row>
                        <?php } ?>
                    </flux:table.rows>
                </flux:table>
                <x-tables.pagination :paginator="$documentSequences" :page-sizes="[25]" />
            </flux:card>
        </div>
    <?php } ?>

    <!-- TAB 5: Printer Configurations -->
    <?php if ($activeTab === 'printers') { ?>
        <div id="panel-printers" role="tabpanel" aria-labelledby="tab-printers" data-settings-active-section="{{ $printerSection }}" class="space-y-6">
            <div class="flex items-center gap-2 rounded-xl border border-border-subtle bg-surface-muted/40 p-5">
                <flux:heading size="lg">{{ __('Printers and print settings') }}</flux:heading>
                <x-context-help :title="__('Printers and print settings')" :label="__('Open contextual help')"><p>{{ __('Add the printers available at your branch, then choose where each document is printed.') }}</p></x-context-help>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3" aria-label="{{ __('Printer workspace') }}">
                <x-context-help :title="__('Printer and template help')" :label="__('Open printer and template help')"><ul class="list-disc space-y-2 ps-5"><li>{{ __('Printer identifies the output device and connection.') }}</li><li>{{ __('Template controls document type, paper size, language, and layout.') }}</li><li>{{ __('Scope limits a printer to the company, a branch, or a point of sale. Relevant printers are active visible printers assigned to an active paper-compatible template.') }}</li></ul></x-context-help>
                <div class="flex flex-wrap gap-2"><flux:button type="button" size="sm" variant="primary" icon="plus" wire:click="openPrinterModal">{{ __('Add printer') }}</flux:button><flux:button type="button" size="sm" variant="primary" icon="plus" wire:click="openPrintTemplateModal">{{ __('Add print template') }}</flux:button></div>
            </div>

            @php
                $scaleDevice = ScaleConfiguration::query()->where('company_id', $companyId)->first();
                $activeScale = $scaleDevice && $scaleDevice->status === 'active' ? $scaleDevice : null;
                $scaleOutlet = $scaleDevice?->store_id ? $this->scaleOutlets()->whereKey($scaleDevice->store_id)->first() : null;
                $scaleReady = $activeScale && $scaleOutlet;
                $scaleCounts = ['mapped' => 0, 'needs_update' => 0, 'keys' => 0, 'keys_pending' => 0, 'templates' => 0];
                if ($scaleDevice) {
                    $scaleCounts = [
                        'mapped' => DB::table('scale_product_mappings')->where('scale_configuration_id', $scaleDevice->id)->count(),
                        'needs_update' => DB::table('scale_product_mappings')->where('scale_configuration_id', $scaleDevice->id)->where('sync_status', '!=', 'confirmed')->count(),
                        'keys' => DB::table('scale_key_assignments')->where('scale_configuration_id', $scaleDevice->id)->whereNotNull('scale_product_mapping_id')->count(),
                        'keys_pending' => DB::table('scale_key_assignments')->where('scale_configuration_id', $scaleDevice->id)->where('sync_status', '!=', 'confirmed')->count(),
                        'templates' => DB::table('scale_label_templates')->where('scale_configuration_id', $scaleDevice->id)->count(),
                    ];
                }
                $outletName = $scaleOutlet ? $scaleOutlet->code.' · '.(str_starts_with(app()->getLocale(), 'ar') ? $scaleOutlet->name_ar : ($scaleOutlet->name_en ?: $scaleOutlet->name_ar)) : null;
            @endphp

            <section class="space-y-4" aria-labelledby="scale-workspace-heading" data-scale-workspace x-data="{ sending: false, interrupted: false }">
                {{-- Status bar: device identity, connection state and counts; the settings form opens on demand. --}}
                <flux:card class="space-y-4 border-s-4 {{ $activeScale ? 'border-s-emerald-500' : 'border-s-amber-500' }}">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="flex min-w-0 items-start gap-3">
                            <div class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-surface-muted text-text-primary">
                                <flux:icon.scale class="size-6" />
                            </div>
                            <div class="min-w-0 space-y-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <flux:heading size="lg" id="scale-workspace-heading">{{ $scaleDevice?->name ?: __('Network scale') }}</flux:heading>
                                    @if (! $scaleDevice)
                                        <flux:badge size="sm" color="zinc">{{ __('Not configured') }}</flux:badge>
                                    @elseif ($activeScale)
                                        <flux:badge size="sm" color="green">{{ __('Active') }}</flux:badge>
                                    @else
                                        <flux:badge size="sm" color="amber">{{ __('Inactive') }}</flux:badge>
                                    @endif
                                    @if ($scaleConnectionResult === 'connected')
                                        <flux:badge size="sm" color="green" icon="signal">{{ __('Connected') }}</flux:badge>
                                    @elseif ($scaleConnectionResult === 'unrecognized')
                                        <flux:badge size="sm" color="amber" icon="exclamation-triangle">{{ __('Reply not verified') }}</flux:badge>
                                    @elseif ($scaleConnectionResult === 'unreachable')
                                        <flux:badge size="sm" color="red" icon="signal-slash">{{ __('Unreachable') }}</flux:badge>
                                    @elseif ($scaleDevice)
                                        <flux:badge size="sm" color="zinc">{{ __('Connection not checked') }}</flux:badge>
                                    @endif
                                </div>
                                @if ($scaleDevice)
                                    <flux:text class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                        <span dir="ltr" class="font-mono">{{ $scaleDevice->ip_address }}:{{ $scaleDevice->port }}</span>
                                        <span>{{ $outletName ?? __('No selling outlet') }}</span>
                                    </flux:text>
                                @else
                                    <flux:text>{{ __('Add the scale address and selling outlet to start linking products, keys and labels.') }}</flux:text>
                                @endif
                            </div>
                        </div>
                        @can('company_settings.edit')
                            <div class="flex flex-wrap gap-2">
                                @if ($scaleDevice)
                                    <flux:button type="button" size="sm" icon="signal" wire:click="testScaleConnection" wire:loading.attr="disabled" wire:target="testScaleConnection">{{ __('Check connection') }}</flux:button>
                                @endif
                                <flux:button type="button" size="sm" :variant="$scaleEditing ? 'primary' : 'subtle'" icon="cog-6-tooth" wire:click="$toggle('scaleEditing')" x-bind:disabled="sending">{{ $scaleEditing ? __('Close settings') : __('Scale settings') }}</flux:button>
                            </div>
                        @endcan
                    </div>

                    @if ($scaleDevice)
                        <dl class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5">
                            @foreach ([
                                ['label' => __('Linked products'), 'value' => $scaleCounts['mapped'], 'tone' => 'text-text-primary'],
                                ['label' => __('Prices need update'), 'value' => $scaleCounts['needs_update'], 'tone' => $scaleCounts['needs_update'] ? 'text-amber-600 dark:text-amber-400' : 'text-text-primary'],
                                ['label' => __('Assigned keys'), 'value' => $scaleCounts['keys'], 'tone' => 'text-text-primary'],
                                ['label' => __('Keys not confirmed'), 'value' => $scaleCounts['keys_pending'], 'tone' => $scaleCounts['keys_pending'] ? 'text-amber-600 dark:text-amber-400' : 'text-text-primary'],
                                ['label' => __('Label templates'), 'value' => $scaleCounts['templates'], 'tone' => 'text-text-primary'],
                            ] as $stat)
                                <div class="rounded-lg border border-border-subtle bg-surface-muted/40 px-3 py-2">
                                    <dt class="text-xs text-text-muted">{{ $stat['label'] }}</dt>
                                    <dd class="text-lg font-semibold tabular-nums {{ $stat['tone'] }}">{{ number_format($stat['value']) }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    @endif

                    @can('company_settings.edit')
                        @if ($scaleEditing)
                            <form wire:submit="saveScale" class="space-y-4 border-t border-border-subtle pt-4">
                                <flux:text>{{ __('Save the scale address and check whether this application server can reach its TCP port. This does not read or verify weight.') }}</flux:text>
                                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                                    <flux:input wire:model="scaleForm.name" :label="__('Scale name')" required />
                                    <flux:input wire:model="scaleForm.ip_address" :label="__('Scale IP address')" placeholder="192.168.0.150" dir="ltr" required />
                                    <flux:input wire:model="scaleForm.port" :label="__('TCP port')" type="number" min="1" max="65535" dir="ltr" required />
                                    <flux:select wire:model="scaleForm.store_id" :label="__('Selling outlet')" required>
                                        <option value="">{{ __('Select a selling outlet') }}</option>
                                        @foreach ($this->scaleOutlets()->orderBy('code')->get() as $outlet)
                                            <option value="{{ $outlet->id }}">{{ $outlet->code }} · {{ str_starts_with(app()->getLocale(), 'ar') ? $outlet->name_ar : ($outlet->name_en ?: $outlet->name_ar) }}</option>
                                        @endforeach
                                    </flux:select>
                                    <flux:select wire:model="scaleForm.status" :label="__('Status')"><option value="active">{{ __('Active') }}</option><option value="inactive">{{ __('Inactive') }}</option></flux:select>
                                </div>
                                <div class="flex flex-wrap items-center gap-3">
                                    <x-actions.loading-button type="submit" action="saveScale" :label="__('Save scale')" />
                                    <flux:button type="button" variant="subtle" wire:click="testScaleConnection">{{ __('Check connection') }}</flux:button>
                                    @if ($scaleConnectionResult === 'connected')
                                        <flux:badge color="green">{{ __('The scale responded to a clock read request.') }}</flux:badge>
                                    @elseif ($scaleConnectionResult === 'unrecognized')
                                        <flux:badge color="amber">{{ __('The port opened, but the scale clock reply could not be verified.') }}</flux:badge>
                                    @elseif ($scaleConnectionResult === 'unreachable')
                                        <flux:badge color="red">{{ __('Scale TCP connection failed from the application server.') }}</flux:badge>
                                    @endif
                                </div>
                                <flux:text class="text-xs">{{ __('Changing the address or outlet marks every linked price as needing an update.') }}</flux:text>
                            </form>
                        @endif
                    @endcan
                </flux:card>

                @can('company_settings.edit')
                    @if (! $scaleReady)
                        @if ($scaleDevice)
                            <flux:callout icon="information-circle" color="amber" :heading="__('Scale management is paused')" :text="__('Activate the scale and choose a selling outlet you can access to manage products, keys and labels.')" />
                        @endif
                    @else
                        {{-- Internal tabs: server-side state; switching is blocked while a batch send is running. --}}
                        <div role="tablist" aria-label="{{ __('Scale workspace') }}" class="flex gap-1 overflow-x-auto rounded-xl border border-border-subtle bg-surface-muted/40 p-1">
                            @foreach ([
                                'products' => [__('Products and prices'), 'tag', $scaleCounts['needs_update']],
                                'keys' => [__('Shortcut keys'), 'squares-2x2', $scaleCounts['keys_pending']],
                                'labels' => [__('Labels and barcode'), 'qr-code', 0],
                                'operations' => [__('Operations'), 'arrow-path-rounded-square', 0],
                            ] as $tabKey => [$tabLabel, $tabIcon, $tabCount])
                                <button type="button" role="tab" id="scale-tab-{{ $tabKey }}" aria-controls="scale-panel" aria-selected="{{ $scaleTab === $tabKey ? 'true' : 'false' }}"
                                    wire:click="setScaleTab('{{ $tabKey }}')" x-bind:disabled="sending" wire:key="scale-tab-{{ $tabKey }}"
                                    @class([
                                        'flex shrink-0 items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition disabled:cursor-not-allowed disabled:opacity-50',
                                        'bg-surface text-text-primary shadow-sm ring-1 ring-border-subtle' => $scaleTab === $tabKey,
                                        'text-text-muted hover:bg-surface/60 hover:text-text-primary' => $scaleTab !== $tabKey,
                                    ])>
                                    <flux:icon :name="$tabIcon" class="size-4" />
                                    <span>{{ $tabLabel }}</span>
                                    @if ($tabCount > 0)
                                        <span class="rounded-full bg-amber-100 px-1.5 text-xs font-semibold tabular-nums text-amber-800 dark:bg-amber-900/50 dark:text-amber-200">{{ $tabCount }}</span>
                                    @endif
                                </button>
                            @endforeach
                        </div>

                        <div id="scale-panel" role="tabpanel" aria-labelledby="scale-tab-{{ $scaleTab }}" class="space-y-4">
                            {{-- ───────────── Products and prices ───────────── --}}
                            @if ($scaleTab === 'products')
                                <div class="grid gap-4 xl:grid-cols-5">
                                    <flux:card class="space-y-4 xl:col-span-2">
                                        <div>
                                            <flux:heading size="md">{{ __('Link a product to the scale') }}</flux:heading>
                                            <flux:text class="mt-1">{{ __('Search sellable products that have a kilogram sale unit, pick one, then confirm the PLU and the label printed by the scale.') }}</flux:text>
                                        </div>
                                        <fieldset class="space-y-4" x-bind:disabled="sending">
                                            <div class="relative">
                                                <flux:input wire:model.live.debounce.300ms="scaleProductSearch" icon="magnifying-glass" :label="__('Find product')" :placeholder="__('Code or name, at least 2 characters')" clearable autocomplete="off" />
                                                @if (mb_strlen(trim($scaleProductSearch)) >= 2)
                                                    @php
                                                        $productMatches = $this->scaleProductMatches();
                                                        $mappedProductIds = $this->scaleMappedProductIds();
                                                    @endphp
                                                    <ul class="mt-2 max-h-72 divide-y divide-border-subtle overflow-y-auto rounded-lg border border-border-subtle bg-surface" role="listbox" aria-label="{{ __('Matching products') }}">
                                                        @forelse ($productMatches as $match)
                                                            @php
                                                                $isMapped = in_array((int) $match->id, $mappedProductIds, true);
                                                            @endphp
                                                            <li wire:key="scale-match-{{ $match->id }}">
                                                                <button type="button" role="option" wire:click="pickScaleProduct({{ $match->id }})" @disabled($isMapped)
                                                                    class="flex w-full items-center justify-between gap-3 px-3 py-2 text-start text-sm hover:bg-surface-muted disabled:cursor-not-allowed disabled:opacity-60">
                                                                    <span class="min-w-0">
                                                                        <span class="block truncate font-medium text-text-primary">{{ str_starts_with(app()->getLocale(), 'ar') ? $match->name_ar : ($match->name_en ?: $match->name_ar) }}</span>
                                                                        <span class="block font-mono text-xs text-text-muted" dir="ltr">{{ $match->item_code }}</span>
                                                                    </span>
                                                                    @if ($isMapped)
                                                                        <flux:badge size="sm" color="zinc">{{ __('Already linked') }}</flux:badge>
                                                                    @else
                                                                        <flux:icon.plus-circle class="size-5 shrink-0 text-text-muted" />
                                                                    @endif
                                                                </button>
                                                            </li>
                                                        @empty
                                                            <li class="px-3 py-4 text-center text-sm text-text-muted">{{ __('No sellable product with a kilogram sale unit matches this search.') }}</li>
                                                        @endforelse
                                                    </ul>
                                                @endif
                                            </div>

                                            <form wire:submit="saveScaleMapping" class="space-y-3 rounded-lg border border-dashed border-border-subtle p-3">
                                                <div class="grid gap-3 sm:grid-cols-3">
                                                    <flux:input wire:model="scaleMapping.code" :label="__('Product code')" dir="ltr" required />
                                                    <flux:input wire:model="scaleMapping.plu" :label="__('Scale product number (PLU)')" type="number" min="1" max="4000" dir="ltr" required />
                                                    <flux:input wire:model="scaleMapping.label" :label="__('Scale label (ASCII)')" maxlength="20" dir="ltr" required />
                                                </div>
                                                <flux:text class="text-xs">{{ __('The next free PLU is suggested automatically. The label is limited to 20 Latin characters because Arabic printing on the scale is not verified.') }}</flux:text>
                                                <x-actions.loading-button type="submit" action="saveScaleMapping" :label="__('Link kilogram unit')" />
                                            </form>
                                        </fieldset>
                                    </flux:card>

                                    <flux:card class="space-y-4 xl:col-span-3">
                                        <div class="flex flex-wrap items-end justify-between gap-3">
                                            <div>
                                                <flux:heading size="md">{{ __('Products on the scale') }}</flux:heading>
                                                <flux:text class="mt-1">{{ __('Preview the current outlet price, then send. Each send is confirmed by reading the PLU back from the scale.') }}</flux:text>
                                            </div>
                                            <div class="w-full sm:w-64">
                                                <flux:input wire:model.live.debounce.300ms="scaleMappingSearch" icon="magnifying-glass" :placeholder="__('Filter by PLU, label or product')" size="sm" clearable />
                                            </div>
                                        </div>
                                        @php
                                            $mappingTerm = trim($scaleMappingSearch);
                                            $mappingPattern = '%'.addcslashes($mappingTerm, '%_\\').'%';
                                            $scaleMappings = DB::table('scale_product_mappings as m')
                                                ->join('product_units as pu', 'pu.id', '=', 'm.product_unit_id')
                                                ->join('products as p', 'p.id', '=', 'pu.product_id')
                                                ->where('m.scale_configuration_id', $activeScale->id)
                                                ->when($mappingTerm !== '', fn ($query) => $query->where(fn ($inner) => $inner
                                                    ->where('m.label', 'like', $mappingPattern)->orWhere('p.item_code', 'like', $mappingPattern)
                                                    ->orWhere('p.name_ar', 'like', $mappingPattern)->orWhere('p.name_en', 'like', $mappingPattern)
                                                    ->when(ctype_digit($mappingTerm), fn ($plu) => $plu->orWhere('m.plu', (int) $mappingTerm))))
                                                ->orderBy('m.plu')
                                                ->select('m.*', 'p.item_code', 'p.name_ar', 'p.name_en')
                                                ->simplePaginate(15, ['*'], 'scaleMappingsPage');
                                        @endphp
                                        <div class="overflow-x-auto rounded-lg border border-border-subtle" wire:init="checkScalePrices">
                                            <table class="min-w-full text-sm">
                                                <thead class="bg-surface-muted/60 text-xs text-text-muted">
                                                    <tr>
                                                        <th class="px-3 py-2 text-start font-medium">{{ __('PLU') }}</th>
                                                        <th class="px-3 py-2 text-start font-medium">{{ __('Product') }}</th>
                                                        <th class="px-3 py-2 text-start font-medium">{{ __('Status') }}</th>
                                                        <th class="px-3 py-2 text-end font-medium">{{ __('Actions') }}</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-border-subtle">
                                                    @forelse ($scaleMappings as $mapping)
                                                        @php
                                                            $listStatus = $scaleStatuses[(int) $mapping->id] ?? ($mapping->sync_status === 'confirmed' ? 'confirmed' : 'needs_update');
                                                            $inPreview = isset($scalePricePreviews[(int) $mapping->id]);
                                                        @endphp
                                                        <tr wire:key="scale-mapping-{{ $mapping->id }}" @class(['bg-sky-50/60 dark:bg-sky-950/30' => $inPreview])>
                                                            <td class="px-3 py-2 font-mono font-semibold" dir="ltr">{{ $mapping->plu }}</td>
                                                            <td class="px-3 py-2">
                                                                <div class="font-medium text-text-primary">{{ str_starts_with(app()->getLocale(), 'ar') ? $mapping->name_ar : ($mapping->name_en ?: $mapping->name_ar) }}</div>
                                                                <div class="font-mono text-xs text-text-muted" dir="ltr">{{ $mapping->item_code }} · {{ $mapping->label }}</div>
                                                            </td>
                                                            <td class="px-3 py-2">
                                                                @if ($mapping->sync_status === 'unverified')
                                                                    <flux:badge size="sm" color="red">{{ __('Not verified') }}</flux:badge>
                                                                @elseif ($listStatus === 'confirmed')
                                                                    <flux:badge size="sm" color="green">{{ __('Confirmed') }}</flux:badge>
                                                                @elseif ($listStatus === 'ineligible')
                                                                    <flux:badge size="sm" color="red">{{ __('Not eligible') }}</flux:badge>
                                                                @else
                                                                    <flux:badge size="sm" color="amber">{{ __('Needs update') }}</flux:badge>
                                                                @endif
                                                            </td>
                                                            <td class="px-3 py-2 text-end">
                                                                @if ($inPreview)
                                                                    <flux:button type="button" size="xs" variant="subtle" icon="x-mark" wire:click="removeScalePreview({{ $mapping->id }})" x-bind:disabled="sending">{{ __('Remove preview') }}</flux:button>
                                                                @else
                                                                    <flux:button type="button" size="xs" icon="eye" wire:click="previewScalePrice({{ $mapping->id }})" x-bind:disabled="sending">{{ __('Preview price') }}</flux:button>
                                                                @endif
                                                            </td>
                                                        </tr>
                                                    @empty
                                                        <tr><td colspan="4" class="px-3 py-8 text-center text-text-muted">{{ $mappingTerm !== '' ? __('No linked product matches this filter.') : __('No products are linked to this scale yet.') }}</td></tr>
                                                    @endforelse
                                                </tbody>
                                            </table>
                                        </div>
                                        {{ $scaleMappings->links() }}
                                    </flux:card>
                                </div>

                                @if ($scalePricePreviews !== [])
                                    @php
                                        $pendingScaleIds = [];
                                    @endphp
                                    <flux:card class="space-y-4">
                                        <div class="flex flex-wrap items-center justify-between gap-3">
                                            <flux:heading size="md">{{ __('Price preview before sending') }}</flux:heading>
                                            <flux:text class="text-xs">{{ __('Prices are checked again at send time; a changed price stops that item.') }}</flux:text>
                                        </div>
                                        <fieldset class="grid gap-3 md:grid-cols-2 xl:grid-cols-3" x-bind:disabled="sending">
                                            @foreach ($scalePricePreviews as $preview)
                                                @php
                                                    $result = $scaleSendResults[$preview['id']] ?? null;
                                                    $pending = $result !== 'ineligible' && ($preview['status'] !== 'confirmed' || $preview['confirmed'] !== $preview['price']);
                                                    if ($pending) {
                                                        $pendingScaleIds[] = $preview['id'];
                                                    }
                                                @endphp
                                                <div wire:key="scale-preview-{{ $preview['id'] }}" class="space-y-2 rounded-lg border border-border-subtle p-3">
                                                    <div class="flex items-start justify-between gap-2">
                                                        <div class="min-w-0">
                                                            <div class="truncate font-medium text-text-primary">{{ $preview['product_name'] }}</div>
                                                            <div class="font-mono text-xs text-text-muted" dir="ltr">PLU {{ $preview['plu'] }} · {{ $preview['product_code'] }} · {{ $preview['label'] }}</div>
                                                        </div>
                                                        <div class="text-end">
                                                            <div class="text-lg font-semibold tabular-nums" dir="ltr">{{ $preview['price'] }}</div>
                                                            <div class="text-xs text-text-muted" dir="ltr">/ {{ $preview['unit'] }}</div>
                                                        </div>
                                                    </div>
                                                    <div class="text-xs text-text-muted">{{ __('Price source') }}: {{ $preview['source_label'] }}@if ($preview['confirmed'] !== null) · {{ __('On scale') }}: <span dir="ltr">{{ $preview['confirmed'] }}</span>@endif</div>
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        @if ($pending)
                                                            <flux:badge size="sm" color="amber">{{ __('Needs update') }}</flux:badge>
                                                            <x-actions.loading-button type="button" size="sm" :action="'sendScalePrice('.$preview['id'].')'" :label="__('Send to scale')" />
                                                        @elseif ($result !== 'ineligible')
                                                            <flux:badge size="sm" color="green">{{ __('Price confirmed on the scale') }}</flux:badge>
                                                        @endif
                                                        <flux:button type="button" size="xs" variant="ghost" icon="x-mark" wire:click="removeScalePreview({{ $preview['id'] }})" :aria-label="__('Remove preview')" />
                                                    </div>
                                                    @if ($result === 'changed')
                                                        <p role="status" class="text-xs text-amber-700 dark:text-amber-300">{{ __('Scale data changed. Review the new preview before sending.') }}</p>
                                                    @elseif ($result === 'unverified')
                                                        <p role="status" class="text-xs text-red-700 dark:text-red-300">{{ __('Scale update could not be verified.') }}</p>
                                                    @elseif ($result === 'ineligible')
                                                        <p role="status" class="text-xs text-red-700 dark:text-red-300">{{ __('Scale product is no longer eligible for sending.') }}</p>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </fieldset>
                                        @if (count($scalePricePreviews) > 1)
                                            {{-- Keep the button mounted while a run is in progress; it used to vanish after the first confirmed item and abort the batch. --}}
                                            <div class="flex flex-wrap items-center gap-3">
                                                <flux:button type="button" variant="primary" icon="paper-airplane" x-bind:disabled="sending || {{ count($pendingScaleIds) < 2 ? 'true' : 'false' }}"
                                                    x-on:click="
                                                        const ids = @js($pendingScaleIds);
                                                        sending = true;
                                                        interrupted = false;
                                                        try {
                                                            for (const id of ids) {
                                                                const result = await $wire.sendScalePrice(id);
                                                                if (! ['confirmed', 'changed', 'ineligible'].includes(result)) {
                                                                    interrupted = true;
                                                                    break;
                                                                }
                                                            }
                                                        } catch {
                                                            interrupted = true;
                                                        } finally {
                                                            sending = false;
                                                        }
                                                    "
                                                >{{ __('Send previewed prices') }} ({{ count($pendingScaleIds) }})</flux:button>
                                                <p role="status" x-show="sending" x-cloak class="text-sm text-text-muted">{{ __('Sending prices to the scale…') }}</p>
                                                <p role="status" x-show="interrupted" x-cloak class="text-sm text-amber-700 dark:text-amber-300">{{ __('Sending stopped. Review the results before retrying.') }}</p>
                                            </div>
                                        @endif
                                    </flux:card>
                                @endif
                            @endif

                            {{-- ───────────── Shortcut keys ───────────── --}}
                            @if ($scaleTab === 'keys')
                                @php
                                    $keyRows = DB::table('scale_key_assignments as k')
                                        ->leftJoin('scale_product_mappings as m', 'm.id', '=', 'k.scale_product_mapping_id')
                                        ->where('k.scale_configuration_id', $activeScale->id)
                                        ->get(['k.*', 'm.plu as mapping_plu', 'm.label as mapping_label']);
                                    $keyGrid = [];
                                    foreach ($keyRows as $row) {
                                        $keyGrid[(int) $row->layer][(int) $row->key_number] = $row;
                                    }
                                    $pendingKeyIds = $keyRows->filter(fn ($row) => ($scaleKeyResults[$row->id] ?? $row->sync_status) !== 'confirmed')->pluck('id')->values()->all();
                                    $layerAssigned = collect($keyGrid[$scaleKeyLayer] ?? [])->filter(fn ($row) => $row->scale_product_mapping_id !== null)->count();
                                    $selectedRow = $scaleKeySelected !== null ? ($keyGrid[$scaleKeyLayer][$scaleKeySelected] ?? null) : null;
                                    $deviceLayer = $scaleDeviceKeys[$scaleKeyLayer] ?? null;
                                @endphp
                                <flux:card class="space-y-4">
                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <flux:heading size="md">{{ __('Shortcut key layout') }}</flux:heading>
                                            <flux:text class="mt-1">{{ __('Click a key to assign a linked product or clear it. Changes are saved in ERP first, then sent and confirmed by reading the key table back from the scale.') }}</flux:text>
                                        </div>
                                        <div class="flex flex-wrap gap-2">
                                            <flux:button type="button" size="sm" icon="arrow-down-tray" wire:click="readScaleKeysFromDevice" wire:loading.attr="disabled" wire:target="readScaleKeysFromDevice" x-bind:disabled="sending">{{ __('Compare with scale') }}</flux:button>
                                            <flux:button type="button" size="sm" variant="primary" icon="paper-airplane" x-bind:disabled="sending || {{ $pendingKeyIds === [] ? 'true' : 'false' }}"
                                                x-on:click="
                                                    const ids = @js($pendingKeyIds);
                                                    sending = true;
                                                    interrupted = false;
                                                    try {
                                                        for (const id of ids) {
                                                            const result = await $wire.sendScaleKey(id);
                                                            if (! ['confirmed', 'ineligible'].includes(result)) {
                                                                interrupted = true;
                                                                break;
                                                            }
                                                        }
                                                    } catch {
                                                        interrupted = true;
                                                    } finally {
                                                        sending = false;
                                                    }
                                                "
                                            >{{ __('Send unconfirmed keys') }} ({{ count($pendingKeyIds) }})</flux:button>
                                        </div>
                                    </div>

                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                        <div class="inline-flex rounded-lg border border-border-subtle bg-surface-muted/40 p-1" role="group" aria-label="{{ __('Key layer') }}">
                                            @foreach ([0, 1, 2] as $layer)
                                                <button type="button" wire:click="setScaleKeyLayer({{ $layer }})" x-bind:disabled="sending" aria-pressed="{{ $scaleKeyLayer === $layer ? 'true' : 'false' }}"
                                                    @class([
                                                        'rounded-md px-3 py-1.5 text-sm font-medium transition disabled:opacity-50',
                                                        'bg-surface text-text-primary shadow-sm' => $scaleKeyLayer === $layer,
                                                        'text-text-muted hover:text-text-primary' => $scaleKeyLayer !== $layer,
                                                    ])>{{ $layer === 0 ? __('Layer 0 (direct press)') : __('Layer').' '.$layer }}</button>
                                            @endforeach
                                        </div>
                                        <flux:text class="text-xs">{{ __(':count of 63 keys assigned on this layer', ['count' => $layerAssigned]) }}</flux:text>
                                    </div>

                                    <div role="status" x-show="sending" x-cloak class="text-sm text-text-muted">{{ __('Sending keys to the scale…') }}</div>
                                    <div role="status" x-show="interrupted" x-cloak class="text-sm text-amber-700 dark:text-amber-300">{{ __('Sending stopped. Review the results before retrying.') }}</div>
                                    @if ($scaleDeviceKeysResult === 'failed')
                                        <flux:callout color="red" icon="exclamation-triangle" :text="__('Could not read the key table from the scale.')" />
                                    @elseif ($scaleDeviceKeysResult === 'read')
                                        <flux:text class="text-xs">{{ __('Keys marked with a red corner differ between ERP and the scale.') }}</flux:text>
                                    @endif

                                    <div class="grid gap-4 lg:grid-cols-3">
                                        <div class="lg:col-span-2">
                                            <div class="grid grid-cols-4 gap-2 sm:grid-cols-7 xl:grid-cols-9" dir="ltr" aria-label="{{ __('Shortcut keys') }}">
                                                @for ($key = 1; $key <= 63; $key++)
                                                    @php
                                                        $cell = $keyGrid[$scaleKeyLayer][$key] ?? null;
                                                        $cellState = $cell ? ($scaleKeyResults[$cell->id] ?? $cell->sync_status) : null;
                                                        $erpValue = $cell && $cell->mapping_plu !== null ? (int) $cell->mapping_plu : 0;
                                                        $differs = $deviceLayer !== null && isset($deviceLayer[$key]) && (int) $deviceLayer[$key] !== $erpValue;
                                                        $isSelected = $scaleKeySelected === $key;
                                                    @endphp
                                                    <button type="button" wire:key="scale-key-cell-{{ $scaleKeyLayer }}-{{ $key }}" wire:click="selectScaleKey({{ $key }})" x-bind:disabled="sending"
                                                        aria-pressed="{{ $isSelected ? 'true' : 'false' }}"
                                                        aria-label="{{ __('Key number') }} {{ $key }}{{ $cell && $cell->mapping_plu !== null ? ' · PLU '.$cell->mapping_plu.' '.$cell->mapping_label : ' · '.__('Empty key') }}"
                                                        @class([
                                                            'relative flex aspect-square min-h-16 flex-col justify-between overflow-hidden rounded-lg border p-1.5 text-start transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary disabled:opacity-60',
                                                            'border-primary bg-primary/10 ring-2 ring-primary' => $isSelected,
                                                            'border-border-subtle bg-surface hover:border-primary/60 hover:shadow-sm' => ! $isSelected && $cell && $cell->mapping_plu !== null,
                                                            'border-dashed border-border-subtle bg-surface-muted/30 text-text-muted hover:border-primary/60' => ! $isSelected && ! ($cell && $cell->mapping_plu !== null),
                                                        ])>
                                                        <span class="flex items-center justify-between">
                                                            <span class="font-mono text-[11px] font-semibold text-text-muted">{{ $key }}</span>
                                                            @if ($cell)
                                                                <span @class([
                                                                    'size-2 rounded-full',
                                                                    'bg-emerald-500' => $cellState === 'confirmed',
                                                                    'bg-red-500' => $cellState === 'unverified',
                                                                    'bg-amber-500' => ! in_array($cellState, ['confirmed', 'unverified'], true),
                                                                ]) title="{{ $cellState === 'confirmed' ? __('Confirmed') : ($cellState === 'unverified' ? __('Not verified') : __('Not sent yet')) }}"></span>
                                                            @endif
                                                        </span>
                                                        @if ($cell && $cell->mapping_plu !== null)
                                                            <span class="min-w-0">
                                                                <span class="block font-mono text-sm font-bold leading-tight text-text-primary">{{ $cell->mapping_plu }}</span>
                                                                <span class="block truncate text-[10px] leading-tight text-text-muted">{{ $cell->mapping_label }}</span>
                                                            </span>
                                                        @else
                                                            <span class="text-center text-lg leading-none text-text-muted/60">+</span>
                                                        @endif
                                                        @if ($differs)
                                                            <span class="absolute end-0 top-0 size-0 border-s-[10px] border-t-[10px] border-s-transparent border-t-red-500" title="{{ __('Scale has PLU :plu on this key', ['plu' => (int) $deviceLayer[$key]]) }}"></span>
                                                        @endif
                                                    </button>
                                                @endfor
                                            </div>
                                            <div class="mt-3 flex flex-wrap gap-4 text-xs text-text-muted">
                                                <span class="flex items-center gap-1.5"><span class="size-2 rounded-full bg-emerald-500"></span>{{ __('Confirmed') }}</span>
                                                <span class="flex items-center gap-1.5"><span class="size-2 rounded-full bg-amber-500"></span>{{ __('Not sent yet') }}</span>
                                                <span class="flex items-center gap-1.5"><span class="size-2 rounded-full bg-red-500"></span>{{ __('Not verified') }}</span>
                                                <span>{{ __('Key numbering follows the scale table; the physical keyboard arrangement may differ.') }}</span>
                                            </div>
                                        </div>

                                        <div class="rounded-xl border border-border-subtle bg-surface-muted/30 p-4">
                                            @if ($scaleKeySelected === null)
                                                <div class="flex h-full flex-col items-center justify-center gap-2 py-8 text-center">
                                                    <flux:icon.cursor-arrow-rays class="size-8 text-text-muted" />
                                                    <flux:text>{{ __('Select a key from the grid to assign a product.') }}</flux:text>
                                                </div>
                                            @else
                                                @php
                                                    $pickerTerm = trim($scaleKeyPickerSearch);
                                                    $pickerPattern = '%'.addcslashes($pickerTerm, '%_\\').'%';
                                                    $pickerMappings = DB::table('scale_product_mappings')->where('scale_configuration_id', $activeScale->id)
                                                        ->when($pickerTerm !== '', fn ($query) => $query->where(fn ($inner) => $inner->where('label', 'like', $pickerPattern)
                                                            ->when(ctype_digit($pickerTerm), fn ($plu) => $plu->orWhere('plu', 'like', $pickerTerm.'%'))))
                                                        ->orderBy('plu')->limit(40)->get(['id', 'plu', 'label']);
                                                    $selectedState = $selectedRow ? ($scaleKeyResults[$selectedRow->id] ?? $selectedRow->sync_status) : null;
                                                @endphp
                                                <div class="space-y-3">
                                                    <div class="flex items-start justify-between gap-2">
                                                        <div>
                                                            <flux:heading size="sm">{{ __('Key number') }} {{ $scaleKeySelected }} · {{ $scaleKeyLayer === 0 ? __('Layer 0 (direct press)') : __('Layer').' '.$scaleKeyLayer }}</flux:heading>
                                                            <flux:text class="text-xs">
                                                                {{ __('Current') }}:
                                                                @if ($selectedRow && $selectedRow->mapping_plu !== null)
                                                                    <span dir="ltr" class="font-mono">{{ $selectedRow->mapping_plu }} · {{ $selectedRow->mapping_label }}</span>
                                                                @else
                                                                    {{ __('Empty key') }}
                                                                @endif
                                                            </flux:text>
                                                        </div>
                                                        <flux:button type="button" size="xs" variant="ghost" icon="x-mark" wire:click="selectScaleKey({{ $scaleKeySelected }})" :aria-label="__('Close')" />
                                                    </div>
                                                    @if ($selectedRow && $selectedState !== 'confirmed')
                                                        <div class="flex items-center gap-2">
                                                            <x-actions.loading-button type="button" size="sm" :action="'sendScaleKey('.$selectedRow->id.')'" :label="__('Send key to scale')" />
                                                            @if ($selectedState === 'unverified')
                                                                <flux:badge size="sm" color="red">{{ __('Not verified') }}</flux:badge>
                                                            @endif
                                                        </div>
                                                    @elseif ($selectedState === 'confirmed')
                                                        <flux:badge size="sm" color="green">{{ __('Key confirmed on the scale') }}</flux:badge>
                                                    @endif
                                                    <flux:input wire:model.live.debounce.250ms="scaleKeyPickerSearch" icon="magnifying-glass" size="sm" :placeholder="__('Search PLU or label')" clearable autocomplete="off" />
                                                    <div class="max-h-80 space-y-1 overflow-y-auto">
                                                        <button type="button" wire:click="assignScaleKey(null)" class="flex w-full items-center gap-2 rounded-md border border-dashed border-border-subtle px-2 py-1.5 text-start text-sm text-text-muted hover:bg-surface">
                                                            <flux:icon.no-symbol class="size-4" /> {{ __('Empty key') }}
                                                        </button>
                                                        @forelse ($pickerMappings as $option)
                                                            @php
                                                                $isCurrent = $selectedRow && (int) $selectedRow->scale_product_mapping_id === (int) $option->id;
                                                            @endphp
                                                            <button type="button" wire:key="scale-key-option-{{ $option->id }}" wire:click="assignScaleKey({{ $option->id }})"
                                                                @class([
                                                                    'flex w-full items-center justify-between gap-2 rounded-md px-2 py-1.5 text-start text-sm transition',
                                                                    'bg-primary/10 font-semibold text-text-primary' => $isCurrent,
                                                                    'hover:bg-surface' => ! $isCurrent,
                                                                ])>
                                                                <span class="truncate" dir="ltr"><span class="font-mono font-semibold">{{ $option->plu }}</span> · {{ $option->label }}</span>
                                                                @if ($isCurrent)<flux:icon.check class="size-4 shrink-0" />@endif
                                                            </button>
                                                        @empty
                                                            <p class="px-2 py-3 text-center text-xs text-text-muted">{{ $pickerTerm !== '' ? __('No linked product matches this filter.') : __('Link products in the Products and prices tab first.') }}</p>
                                                        @endforelse
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </flux:card>
                            @endif

                            {{-- ───────────── Labels and barcode ───────────── --}}
                            @if ($scaleTab === 'labels')
                                @php
                                    $barcodePreview = $this->scaleBarcodePreview();
                                    $segmentTone = [
                                        'prefix' => 'bg-sky-100 text-sky-900 ring-sky-300 dark:bg-sky-900/40 dark:text-sky-100 dark:ring-sky-700',
                                        'item' => 'bg-violet-100 text-violet-900 ring-violet-300 dark:bg-violet-900/40 dark:text-violet-100 dark:ring-violet-700',
                                        'value' => 'bg-emerald-100 text-emerald-900 ring-emerald-300 dark:bg-emerald-900/40 dark:text-emerald-100 dark:ring-emerald-700',
                                        'check' => 'bg-zinc-100 text-zinc-800 ring-zinc-300 dark:bg-zinc-800 dark:text-zinc-100 dark:ring-zinc-600',
                                    ];
                                    $savedBarcode = (array) ($scaleDevice->barcode_settings ?? []);
                                @endphp
                                <flux:card class="space-y-5">
                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <flux:heading size="md">{{ __('Barcode print settings') }}</flux:heading>
                                            <flux:text class="mt-1">{{ __('Define the weight or price barcode the scale prints on each label, so the point of sale can read the item and value from it.') }}</flux:text>
                                        </div>
                                        @if ($savedBarcode === [])
                                            <flux:badge size="sm" color="amber">{{ __('Not saved yet') }}</flux:badge>
                                        @elseif (array_intersect_key($scaleBarcodeForm, $savedBarcode) != $savedBarcode)
                                            <flux:badge size="sm" color="amber">{{ __('Unsaved changes') }}</flux:badge>
                                        @else
                                            <flux:badge size="sm" color="green">{{ __('Saved in ERP') }}</flux:badge>
                                        @endif
                                    </div>

                                    <div class="grid gap-6 xl:grid-cols-2">
                                        <form wire:submit="saveScaleBarcodeSettings" class="space-y-4">
                                            <div class="grid gap-4 sm:grid-cols-2">
                                                <flux:select wire:model.live="scaleBarcodeForm.symbology" :label="__('Barcode type')">
                                                    <option value="EAN13">EAN-13</option>
                                                    <option value="Code128">Code 128</option>
                                                </flux:select>
                                                <flux:input wire:model.live.debounce.300ms="scaleBarcodeForm.prefix" :label="__('Prefix')" maxlength="2" inputmode="numeric" dir="ltr" :description="__('Usually 20–29 for in-store weight labels.')" />
                                                <flux:select wire:model.live="scaleBarcodeForm.item_source" :label="__('Item identifier')">
                                                    <option value="plu">{{ __('Scale PLU') }}</option>
                                                    <option value="item_code">{{ __('ERP product code') }}</option>
                                                </flux:select>
                                                <flux:input wire:model.live.debounce.300ms="scaleBarcodeForm.item_digits" :label="__('Item digits')" type="number" min="4" max="7" dir="ltr" />
                                                <flux:select wire:model.live="scaleBarcodeForm.value_type" :label="__('Embedded value')">
                                                    <option value="weight">{{ __('Weight') }}</option>
                                                    <option value="price">{{ __('Total price') }}</option>
                                                </flux:select>
                                                <flux:input wire:model.live.debounce.300ms="scaleBarcodeForm.value_digits" :label="__('Value digits')" type="number" min="4" max="6" dir="ltr" />
                                                <flux:input wire:model.live.debounce.300ms="scaleBarcodeForm.value_decimals" :label="__('Decimal places')" type="number" min="0" max="3" dir="ltr" :description="$scaleBarcodeForm['value_type'] === 'weight' ? __('3 means grams (1.250 kg → 01250).') : __('2 means piasters (31.25 → 03125).')" />
                                                <div class="flex items-end pb-2">
                                                    <flux:switch wire:model.live="scaleBarcodeForm.print_human_readable" :label="__('Print digits under the bars')" />
                                                </div>
                                            </div>
                                            <div class="flex flex-wrap items-center gap-3">
                                                <x-actions.loading-button type="submit" action="saveScaleBarcodeSettings" :label="__('Save barcode settings')" />
                                                <flux:text class="text-xs">{{ __('Saved in ERP only. Uploading this format to the scale is not verified yet, so set the same layout on the scale.') }}</flux:text>
                                            </div>
                                        </form>

                                        <div class="space-y-4 rounded-xl border border-border-subtle bg-surface-muted/30 p-4">
                                            <div class="flex items-center justify-between gap-2">
                                                <flux:heading size="sm">{{ __('Live preview') }}</flux:heading>
                                                <span class="text-xs text-text-muted">{{ __('Sample') }}: <span dir="ltr">{{ $barcodePreview['sample_item'] }} · {{ $barcodePreview['sample_value'] }}</span></span>
                                            </div>
                                            <div class="flex flex-wrap gap-1.5" dir="ltr">
                                                @foreach ($barcodePreview['segments'] as $segment)
                                                    <div class="flex flex-col items-center gap-1">
                                                        <span class="rounded-md px-2 py-1 font-mono text-base font-semibold tracking-widest ring-1 {{ $segmentTone[$segment['key']] }}">{{ $segment['digits'] !== '' ? $segment['digits'] : '—' }}</span>
                                                        <span class="text-[11px] text-text-muted">{{ $segment['label'] }}</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                            <div class="flex flex-col items-center gap-1 rounded-lg bg-white p-3 text-black">
                                                @if ($barcodePreview['valid'] && $barcodePreview['svg'])
                                                    <div class="w-full max-w-xs [&>svg]:h-auto [&>svg]:w-full">{!! $barcodePreview['svg'] !!}</div>
                                                    @if ($scaleBarcodeForm['print_human_readable'])
                                                        <span class="font-mono text-sm tracking-[0.2em]" dir="ltr">{{ $barcodePreview['code'] }}</span>
                                                    @endif
                                                @else
                                                    <span class="py-6 text-sm text-red-700">{{ __('This layout cannot produce a valid barcode.') }}</span>
                                                @endif
                                            </div>
                                            <div class="space-y-1 text-xs">
                                                <div class="text-text-muted">{{ __('Length') }}: <span dir="ltr" class="font-mono">{{ $barcodePreview['length'] }}</span>@if ($scaleBarcodeForm['symbology'] === 'EAN13') / 13 @endif</div>
                                                @if ($scaleBarcodeForm['symbology'] === 'EAN13' && $barcodePreview['length'] !== 13)
                                                    <p class="text-red-700 dark:text-red-300">{{ __('EAN-13 needs prefix + item code + value digits to total 12; the 13th digit is the check digit.') }}</p>
                                                @endif
                                                @if ($barcodePreview['item_overflow'])
                                                    <p class="text-amber-700 dark:text-amber-300">{{ __('The sample item identifier does not fit the item digits and would be cut.') }}</p>
                                                @endif
                                                @if ($barcodePreview['value_overflow'])
                                                    <p class="text-amber-700 dark:text-amber-300">{{ __('The sample value does not fit the value digits and would be cut.') }}</p>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    <div class="space-y-2 border-t border-border-subtle pt-4">
                                        <div class="flex flex-wrap items-center gap-3">
                                            <flux:button type="button" size="sm" icon="arrow-down-tray" wire:click="readScaleBarcodeFormats" wire:loading.attr="disabled" wire:target="readScaleBarcodeFormats">{{ __('Read barcode format from scale') }}</flux:button>
                                            <flux:text class="text-xs">{{ __('Read-only. The scale format is shown as received because its fields are not decoded yet.') }}</flux:text>
                                        </div>
                                        @if ($scaleDeviceBarcodeResult === 'read')
                                            <div class="flex flex-wrap gap-2" dir="ltr">
                                                @foreach ($scaleDeviceBarcodeFormats as $format)
                                                    <code class="rounded bg-surface-muted px-2 py-1 text-xs">{{ $format }}</code>
                                                @endforeach
                                            </div>
                                        @elseif ($scaleDeviceBarcodeResult === 'empty')
                                            <p role="status" class="text-sm text-text-muted">{{ __('The scale returned no barcode formats.') }}</p>
                                        @elseif ($scaleDeviceBarcodeResult === 'failed')
                                            <p role="status" class="text-sm text-red-700 dark:text-red-300">{{ __('Could not read the barcode format from the scale.') }}</p>
                                        @endif
                                    </div>
                                </flux:card>

                                @php
                                    $labelFieldOptions = ['name' => __('Product name'), 'weight' => __('Weight'), 'unit_price' => __('Unit price'), 'total' => __('Total'), 'date' => __('Date'), 'plu' => __('PLU'), 'barcode' => __('Barcode')];
                                    $labelSamples = ['name' => 'TOMATO', 'weight' => '1.250 kg', 'unit_price' => '25.00 /kg', 'total' => '31.25', 'date' => now()->format('d/m/Y'), 'plu' => 'PLU 12'];
                                    $labelWidth = max(1, (int) $scaleLabelForm['width_mm']);
                                    $labelHeight = max(1, (int) $scaleLabelForm['height_mm']);
                                    $labelTemplates = DB::table('scale_label_templates')->where('scale_configuration_id', $activeScale->id)->orderByDesc('id')->limit(20)->get();
                                @endphp
                                <flux:card class="space-y-5">
                                    <div>
                                        <flux:heading size="md">{{ __('Scale label template') }}</flux:heading>
                                        <flux:text class="mt-1">{{ __('Save dimensions and supported fields. Upload and physical print confirmation stay disabled until the device label protocol is verified.') }}</flux:text>
                                    </div>
                                    <div class="grid gap-6 xl:grid-cols-2">
                                        <form wire:submit="saveScaleLabelTemplate" class="space-y-4">
                                            <div class="grid gap-4 sm:grid-cols-2">
                                                <flux:input wire:model="scaleLabelForm.name" :label="__('Template name')" required class="sm:col-span-2" />
                                                <flux:input wire:model.live.debounce.300ms="scaleLabelForm.width_mm" :label="__('Width (mm)')" type="number" min="1" max="200" dir="ltr" required />
                                                <flux:input wire:model.live.debounce.300ms="scaleLabelForm.height_mm" :label="__('Height (mm)')" type="number" min="1" max="200" dir="ltr" required />
                                                <flux:select wire:model.live="scaleLabelForm.barcode_type" :label="__('Barcode')" required>
                                                    <option value="EAN13">EAN-13</option>
                                                    <option value="Code128">Code 128</option>
                                                    <option value="none">{{ __('None') }}</option>
                                                </flux:select>
                                            </div>
                                            <fieldset class="space-y-2">
                                                <legend class="text-sm font-medium text-text-primary">{{ __('Fields on the label') }}</legend>
                                                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                                    @foreach ($labelFieldOptions as $fieldKey => $fieldLabel)
                                                        <flux:checkbox wire:model.live="scaleLabelForm.fields" value="{{ $fieldKey }}" :label="$fieldLabel" wire:key="scale-label-field-{{ $fieldKey }}" />
                                                    @endforeach
                                                </div>
                                                <flux:error name="scaleLabelForm.fields" />
                                            </fieldset>
                                            <x-actions.loading-button type="submit" action="saveScaleLabelTemplate" :label="__('Save label template')" />
                                        </form>

                                        <div class="space-y-2">
                                            <flux:heading size="sm">{{ __('Label preview') }} <span class="font-normal text-text-muted" dir="ltr">({{ $labelWidth }} × {{ $labelHeight }} mm)</span></flux:heading>
                                            <div class="flex justify-center rounded-xl border border-border-subtle bg-surface-muted/30 p-4">
                                                <div class="flex w-full max-w-xs flex-col justify-between gap-1 overflow-hidden rounded-md border border-zinc-300 bg-white p-2 font-mono text-[11px] text-black shadow-sm" style="aspect-ratio: {{ $labelWidth }} / {{ $labelHeight }}" dir="ltr">
                                                    @foreach (['name', 'plu', 'date', 'weight', 'unit_price', 'total'] as $fieldKey)
                                                        @if (in_array($fieldKey, $scaleLabelForm['fields'], true))
                                                            <div @class(['flex justify-between gap-2', 'text-sm font-bold' => in_array($fieldKey, ['name', 'total'], true)])>
                                                                <span class="truncate">{{ $fieldKey === 'name' ? $labelSamples['name'] : $labelFieldOptions[$fieldKey] }}</span>
                                                                @if ($fieldKey !== 'name')<span>{{ $labelSamples[$fieldKey] }}</span>@endif
                                                            </div>
                                                        @endif
                                                    @endforeach
                                                    @if (in_array('barcode', $scaleLabelForm['fields'], true) && $scaleLabelForm['barcode_type'] !== 'none')
                                                        <div class="mt-auto [&>svg]:h-8 [&>svg]:w-full">
                                                            @if ($barcodePreview['valid'] && $barcodePreview['svg'])
                                                                {!! $barcodePreview['svg'] !!}
                                                            @else
                                                                <div class="h-8 bg-[repeating-linear-gradient(90deg,#000_0_2px,transparent_2px_4px)]"></div>
                                                            @endif
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                            <flux:text class="text-xs">{{ __('The preview shows field order and proportions only; the scale controls the final layout.') }}</flux:text>
                                        </div>
                                    </div>

                                    <div class="space-y-2 border-t border-border-subtle pt-4">
                                        <flux:heading size="sm">{{ __('Saved label templates') }}</flux:heading>
                                        @forelse ($labelTemplates as $template)
                                            <div wire:key="scale-label-template-{{ $template->id }}" class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border-subtle px-3 py-2">
                                                <div class="min-w-0">
                                                    <div class="font-medium text-text-primary">{{ $template->name }}</div>
                                                    <div class="text-xs text-text-muted" dir="ltr">{{ $template->width_mm }} × {{ $template->height_mm }} mm · {{ $template->barcode_type ?: '—' }} · {{ collect(json_decode($template->fields, true) ?: [])->map(fn ($field) => $labelFieldOptions[$field] ?? $field)->implode(', ') }}</div>
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <flux:badge size="sm" :color="$template->status === 'draft' ? 'zinc' : 'green'">{{ $template->status === 'draft' ? __('Draft') : __($template->status) }}</flux:badge>
                                                    @if ($template->status === 'draft')
                                                        <flux:button type="button" size="xs" variant="ghost" icon="trash" wire:click="deleteScaleLabelTemplate({{ $template->id }})" wire:confirm="{{ __('Delete this label template draft?') }}" :aria-label="__('Delete')" />
                                                    @endif
                                                </div>
                                            </div>
                                        @empty
                                            <p class="text-sm text-text-muted">{{ __('No label templates saved yet.') }}</p>
                                        @endforelse
                                    </div>
                                </flux:card>
                            @endif

                            {{-- ───────────── Operations ───────────── --}}
                            @if ($scaleTab === 'operations')
                                <div class="grid gap-4 lg:grid-cols-2">
                                    <flux:card class="space-y-4">
                                        <div>
                                            <flux:heading size="md">{{ __('Read a product from the scale') }}</flux:heading>
                                            <flux:text class="mt-1">{{ __('Read-only check of what is stored at a PLU on the device.') }}</flux:text>
                                        </div>
                                        <form wire:submit="readScaleProduct" class="flex flex-wrap items-end gap-3">
                                            <flux:input wire:model="scaleProductNumber" :label="__('Scale product number (PLU)')" type="number" min="1" max="4000" dir="ltr" required />
                                            <x-actions.loading-button type="submit" action="readScaleProduct" :label="__('Read product')" />
                                        </form>
                                        <div role="status">
                                            @if ($scaleProductResult === 'read' && $scaleProduct)
                                                <dl class="grid grid-cols-3 gap-2 rounded-lg border border-border-subtle p-3 text-sm">
                                                    <div><dt class="text-xs text-text-muted">{{ __('Product') }}</dt><dd class="font-medium" dir="ltr">{{ $scaleProduct['name'] }}</dd></div>
                                                    <div><dt class="text-xs text-text-muted">{{ __('Code') }}</dt><dd class="font-mono" dir="ltr">{{ $scaleProduct['code'] }}</dd></div>
                                                    <div><dt class="text-xs text-text-muted">{{ __('Unit price') }}</dt><dd class="font-mono" dir="ltr">{{ $scaleProduct['price'] }}</dd></div>
                                                </dl>
                                            @elseif ($scaleProductResult === 'empty')
                                                <p class="text-sm text-text-muted">{{ __('No product is stored at this PLU.') }}</p>
                                            @elseif ($scaleProductResult === 'inactive')
                                                <p class="text-sm text-amber-700 dark:text-amber-300">{{ __('Save an active scale configuration first.') }}</p>
                                            @elseif ($scaleProductResult === 'failed')
                                                <p class="text-sm text-red-700 dark:text-red-300">{{ __('Could not verify the product reply from the scale.') }}</p>
                                            @endif
                                        </div>
                                    </flux:card>

                                    <flux:card class="space-y-4">
                                        <div>
                                            <flux:heading size="md">{{ __('Connection') }}</flux:heading>
                                            <flux:text class="mt-1">{{ __('Checks that this application server can reach the scale and read its clock.') }}</flux:text>
                                        </div>
                                        <div class="flex flex-wrap items-center gap-3">
                                            <x-actions.loading-button type="button" action="testScaleConnection" :label="__('Check connection')" />
                                            @if ($scaleConnectionResult === 'connected')
                                                <flux:badge color="green">{{ __('The scale responded to a clock read request.') }}</flux:badge>
                                            @elseif ($scaleConnectionResult === 'unrecognized')
                                                <flux:badge color="amber">{{ __('The port opened, but the scale clock reply could not be verified.') }}</flux:badge>
                                            @elseif ($scaleConnectionResult === 'unreachable')
                                                <flux:badge color="red">{{ __('Scale TCP connection failed from the application server.') }}</flux:badge>
                                            @endif
                                        </div>
                                    </flux:card>
                                </div>

                                <flux:card class="space-y-4">
                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <flux:heading size="md">{{ __('Scale transaction records') }}</flux:heading>
                                            <flux:text class="mt-1">{{ __('These values are imported from the scale report and are shown as a transaction record. They do not create an invoice or change stock.') }}</flux:text>
                                        </div>
                                        <form wire:submit="readScaleReport">
                                            <x-actions.loading-button type="submit" action="readScaleReport" :label="__('Read scale report')" />
                                        </form>
                                    </div>
                                    @if ($scaleReportResult === 'read')
                                        <div class="overflow-x-auto rounded-lg border border-border-subtle">
                                            <table class="min-w-full text-sm">
                                                <thead class="bg-surface-muted/60 text-xs text-text-muted"><tr><th class="px-3 py-2 text-start">{{ __('PLU') }}</th><th class="px-3 py-2 text-start">{{ __('Code') }}</th><th class="px-3 py-2 text-start">{{ __('Product') }}</th><th class="px-3 py-2 text-start">{{ __('Weight') }}</th><th class="px-3 py-2 text-start">{{ __('Unit price') }}</th><th class="px-3 py-2 text-start">{{ __('Total') }}</th><th class="px-3 py-2 text-start">{{ __('Recorded at') }}</th></tr></thead>
                                                <tbody class="divide-y divide-border-subtle">
                                                    @foreach ($scaleReport as $record)
                                                        <tr wire:key="scale-report-{{ $loop->index }}"><td class="px-3 py-2" dir="ltr">{{ $record['plu'] }}</td><td class="px-3 py-2" dir="ltr">{{ $record['code'] }}</td><td class="px-3 py-2">{{ $record['name'] }}</td><td class="px-3 py-2" dir="ltr">{{ $record['weight'] }} kg</td><td class="px-3 py-2" dir="ltr">{{ $record['unit_price'] }}</td><td class="px-3 py-2" dir="ltr">{{ $record['total'] }}@unless ($record['consistent']) <flux:badge size="sm" color="amber">{{ __('Total does not match price times weight') }}</flux:badge>@endunless</td><td class="px-3 py-2" dir="ltr">{{ $record['recorded_at'] }}</td></tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @elseif ($scaleReportResult === 'empty')
                                        <p role="status" class="text-sm text-text-muted">{{ __('The scale returned no transaction records.') }}</p>
                                    @elseif ($scaleReportResult === 'inactive')
                                        <p role="status" class="text-sm text-amber-700 dark:text-amber-300">{{ __('Save an active scale configuration first.') }}</p>
                                    @elseif ($scaleReportResult === 'failed')
                                        <p role="status" class="text-sm text-red-700 dark:text-red-300">{{ __('Could not verify the scale report.') }}</p>
                                    @endif
                                </flux:card>
                            @endif
                        </div>
                    @endif
                @endcan
            </section>

            <flux:card class="space-y-4" x-data="{ scanned: '' }" x-on:barcode:scanned.window="scanned = $event.detail.code">
                <div>
                    <flux:heading size="lg">{{ __('Barcode scanner') }}</flux:heading>
                    <flux:subheading>{{ __('USB keyboard scanners need no driver or saved port. Scan a barcode here to verify the code received by this browser.') }}</flux:subheading>
                </div>
                <flux:input data-scan-target x-model="scanned" :label="__('Last scanned barcode')" autocomplete="off" dir="ltr" />
            </flux:card>

            <?php if (true) { ?>
            <flux:card id="printer-profiles" class="scroll-mt-24 space-y-6">
                <flux:modal wire:model="printerModalOpen" class="max-w-3xl">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <flux:heading size="lg">{{ $printerForm['id'] ? __('Edit printer') : __('Add printer') }}</flux:heading>
                        <x-context-help :title="$printerForm['id'] ? __('Edit printer') : __('Add printer')" :label="__('Open contextual help')"><p>{{ __('Enter the printer details used by the team. Advanced connection settings are optional.') }}</p></x-context-help>
                    </div>
        <?php if ($printerForm['id']) { ?>
            <flux:button
                href="{{ route('admin.settings.printer-preview', ['printer' => $printerForm['id']]) }}"
                target="_blank"
                variant="subtle"
                icon="printer"
            >
                {{ __('Test print') }}
            </flux:button>
        <?php } else { ?>
            <flux:button type="button" variant="subtle" icon="printer" disabled>
                {{ __('Test print') }}
            </flux:button>
        <?php } ?>
                </div>

                <form id="printer-profile-form" wire:submit="savePrinter" class="space-y-4">
                    <?php
                $printerBranches = Branch::visibleTo(auth()->user())->where('status', 'active')->orderBy('code')->get(['id', 'code', 'name_ar', 'name_en']);
                $printerStores = Store::visibleTo(auth()->user())->where('status', 'active')->orderBy('code')->get(['id', 'branch_id', 'code', 'name_ar', 'name_en']);
                ?>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <flux:input autofocus
                            wire:model="printerForm.name"
                            :label="__('Printer Name')"
                            placeholder="Cashier Thermal Printer 1"
                            required
                        />

                        <flux:select wire:model.live="printerForm.scope_type" :label="__('Printer location')">
                            <option value="global">{{ __('Entire company') }}</option>
                            <option value="branch">{{ __('Branch') }}</option>
                            <option value="store">{{ __('Store / point of sale') }}</option>
                        </flux:select>

                        <flux:select wire:model.live="printerForm.branch_id" :label="__('Branch')" :disabled="$printerForm['scope_type'] === 'global'">
                            <option value="">{{ __('Select branch') }}</option>
                            @foreach ($printerBranches as $branch)
                                <option value="{{ $branch->id }}">{{ $branch->code }} · {{ str_starts_with(app()->getLocale(), 'ar') ? $branch->name_ar : $branch->name_en }}</option>
                            @endforeach
                        </flux:select>

                        <flux:select wire:model="printerForm.store_id" :label="__('Store / point of sale')" :disabled="$printerForm['scope_type'] !== 'store'">
                            <option value="">{{ __('Select a store or point of sale') }}</option>
                            @foreach ($printerStores->when(filled($printerForm['branch_id']), fn ($stores) => $stores->where('branch_id', (int) $printerForm['branch_id'])) as $store)
                                <option value="{{ $store->id }}">{{ $store->code }} · {{ str_starts_with(app()->getLocale(), 'ar') ? $store->name_ar : $store->name_en }}</option>
                            @endforeach
                        </flux:select>

                        <flux:select wire:model.live="printerForm.printer_type" :label="__('Printer Type')">
                            <option value="thermal">{{ __('Thermal Receipt') }}</option>
                            <option value="a4">{{ __('A4 Document') }}</option>
                            <option value="label">{{ __('Barcode / Label') }}</option>
                            <option value="pdf">{{ __('PDF Virtual') }}</option>
                        </flux:select>

                        <flux:select wire:model.live="printerForm.paper_size" :label="__('Paper Size')">
                            <?php if (($printerForm['printer_type'] ?? 'thermal') === 'thermal') { ?>
                                <option value="80mm">{{ __('80mm Thermal') }}</option>
                                <option value="58mm">{{ __('58mm Thermal') }}</option>
                            <?php } elseif (($printerForm['printer_type'] ?? '') === 'label') { ?>
                                <option value="label_50x30mm">{{ __('50 × 30 mm label') }}</option>
                                <option value="label_40x25mm">{{ __('40 × 25 mm label') }}</option>
                            <?php } else { ?>
                                <option value="a4">{{ __('A4 Sheet') }}</option>
                                <option value="a5">{{ __('A5 Sheet') }}</option>
                            <?php } ?>
                        </flux:select>

                        @php($compatibleTemplates = PrintTemplate::visibleTo(auth()->user())->where('status', 'active')->where('paper_size', $printerForm['paper_size'])->orderBy(str_starts_with(app()->getLocale(), 'ar') ? 'name_ar' : 'name_en')->get())
                        <flux:select wire:model="printerForm.print_template_id" :label="__('Print template')">
                            <option value="">{{ __('No template assigned') }}</option>
                            @foreach ($compatibleTemplates as $template)
                                <option value="{{ $template->id }}">{{ str_starts_with(app()->getLocale(), 'ar') ? $template->name_ar : $template->name_en }}</option>
                            @endforeach
                        </flux:select>

                        <flux:select wire:model="printerForm.connection_type" :label="__('Connection')"><option value="network">{{ __('Network') }}</option><option value="usb">USB</option><option value="bluetooth">Bluetooth</option><option value="browser">{{ __('Browser print') }}</option></flux:select>
                        <flux:input wire:model="printerForm.ip_address" :label="__('IP address')" dir="ltr" :disabled="$printerForm['connection_type'] !== 'network'" />
                        <flux:input wire:model="printerForm.port" :label="__('Port')" type="number" min="1" max="65535" :disabled="$printerForm['connection_type'] !== 'network'" />

                    </div>

                    <input type="hidden" wire:model="printerForm.template_name">

                    <flux:switch
                        wire:model="printerForm.is_default"
                        align="start"
                        :label="__('Make this the default printer for this location')"
                    />
                    <x-context-help :title="__('Make this the default printer for this location')" :label="__('Open contextual help')"><p>{{ __('Use one default printer per location and printer type.') }}</p></x-context-help>

                    <div class="flex items-center justify-end gap-3">
                        <?php if ($printerForm['id']) { ?>
                            <flux:button type="button" wire:click="resetPrinterForm" variant="subtle">
                                {{ __('Cancel Edit') }}
                            </flux:button>
                        <?php } ?>

                        <x-actions.loading-button type="submit" action="savePrinter" :label="__('Save')" />
                    </div>
                </form>
                </flux:modal>

                <div class="border-t border-border-subtle pt-5">
                    <div class="mb-4 flex items-center gap-2">
                        <flux:heading size="md">{{ __('Configured printers') }}</flux:heading>
                        <x-context-help :title="__('Configured printers')" :label="__('Open contextual help')"><p>{{ __('Review availability and update the printer that is used at each location.') }}</p></x-context-help>
                    </div>

                    <x-tables.filter-bar class="mb-4">
                        <flux:input wire:model.live.debounce.300ms="masterListSearch" icon="magnifying-glass" :label="__('Search')" :placeholder="__('Search')" clearable />
                    </x-tables.filter-bar>

                    <?php
                    $searchTerm = trim($masterListSearch);
                $printers = PrinterConfiguration::visibleTo(auth()->user())
                    ->with(['branch', 'store', 'printTemplate'])
                    ->when($searchTerm !== '', function ($query) use ($searchTerm): void {
                        $query->where(function ($query) use ($searchTerm): void {
                            $pattern = '%'.$searchTerm.'%';
                            $query->where('name', 'like', $pattern)
                                ->orWhere('printer_type', 'like', $pattern)
                                ->orWhere('paper_size', 'like', $pattern)
                                ->orWhere('template_name', 'like', $pattern)
                                ->orWhereHas('branch', function ($branchQuery) use ($pattern): void {
                                    $branchQuery->where('code', 'like', $pattern)
                                        ->orWhere('name_ar', 'like', $pattern)
                                        ->orWhere('name_en', 'like', $pattern);
                                })
                                ->orWhereHas('store', function ($storeQuery) use ($pattern): void {
                                    $storeQuery->where('code', 'like', $pattern)
                                        ->orWhere('name_ar', 'like', $pattern)
                                        ->orWhere('name_en', 'like', $pattern);
                                })
                                ->orWhereHas('printTemplate', function ($templateQuery) use ($pattern): void {
                                    $templateQuery->where('code', 'like', $pattern)
                                        ->orWhere('name_ar', 'like', $pattern)
                                        ->orWhere('name_en', 'like', $pattern);
                                });
                        });
                    })
                    ->orderBy('name')
                    ->orderBy('id')
                    ->paginate(25);
                ?>

                    <flux:table aria-label="{{ __('Configured printers') }}">
                        <flux:table.columns>
                            <flux:table.column>{{ __('Printer name') }}</flux:table.column>
                            <flux:table.column>{{ __('Template') }}</flux:table.column>
                            <flux:table.column>{{ __('Scope') }}</flux:table.column>
                            <flux:table.column>{{ __('Type') }}</flux:table.column>
                            <flux:table.column>{{ __('Status') }}</flux:table.column>
                            <flux:table.column>{{ __('Actions') }}</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            <?php foreach ($printers as $printer) { ?>
                                <flux:table.row key="physical-printer-{{ $printer->id }}">
                                    <flux:table.cell class="font-medium">{{ $printer->name }}</flux:table.cell>
                                    <flux:table.cell>{{ $printer->printTemplate ? (str_starts_with(app()->getLocale(), 'ar') ? $printer->printTemplate->name_ar : $printer->printTemplate->name_en) : __('No template assigned') }}<div class="text-xs text-text-muted">{{ $printer->paper_size }}</div></flux:table.cell>
                                    <flux:table.cell>{{ $printer->store ? (str_starts_with(app()->getLocale(), 'ar') ? $printer->store->name_ar : $printer->store->name_en) : ($printer->branch ? (str_starts_with(app()->getLocale(), 'ar') ? $printer->branch->name_ar : $printer->branch->name_en) : __('Entire company')) }}</flux:table.cell>
                                    <flux:table.cell>{{ $printer->printTemplate ? DocumentTypeCatalog::printLabel($printer->printTemplate->document_type) : __(match ($printer->printer_type) { 'thermal' => 'Thermal receipt printer', 'label' => 'Barcode / label printer', 'a4' => 'A4 printer', default => 'PDF printer' }) }}</flux:table.cell>
                                    <flux:table.cell><flux:badge size="sm" :color="$printer->status === 'active' ? 'green' : 'zinc'">{{ $printer->status === 'active' ? __('Available') : __('Inactive') }}</flux:badge></flux:table.cell>
                                    <flux:table.cell class="whitespace-nowrap">
                                        <div class="flex flex-wrap gap-2">
                            <flux:button
                                size="sm"
                                variant="subtle"
                                href="{{ route('admin.settings.printer-preview', ['printer' => $printer->id]) }}"
                                target="_blank"
                                icon="printer"
                            >
                                {{ __('Test print') }}
                            </flux:button>
                                            <flux:button size="sm" variant="subtle" type="button" wire:click="editPrinter({{ $printer->id }})">{{ __('Edit') }}</flux:button>
                                        </div>
                                    </flux:table.cell>
                                </flux:table.row>
                            <?php } ?>
                            <?php if ($printers->isEmpty()) { ?>
                                <flux:table.row>
                                    <flux:table.cell colspan="6" class="py-8 text-center">
                                <x-state.empty :title="$searchTerm === '' ? __('No printers added yet') : __('No results')" :description="$searchTerm === '' ? __('Add your first printer to start setting up invoices, receipts, and reports.') : ''" icon="printer" />
                                    </flux:table.cell>
                                </flux:table.row>
                            <?php } ?>
                        </flux:table.rows>
                    </flux:table>
                    <x-tables.pagination :paginator="$printers" :page-sizes="[25]" />
                </div>
            </flux:card>
            <?php } ?>

            <?php if (true) { ?>
            <flux:card id="print-templates" class="scroll-mt-24 space-y-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="flex items-center gap-2"><flux:heading size="lg">{{ __('Printing Template Library') }}</flux:heading><x-context-help :title="__('Printing Template Library')" :label="__('Open contextual help')"><p>{{ __('Create reusable document layouts by document type, paper size, and language, then assign compatible templates to printers.') }}</p></x-context-help></div>
                </div>
                <flux:modal wire:model="templateModalOpen" class="max-w-3xl"><form wire:submit="savePrintTemplate" class="grid gap-4 rounded-xl border border-border-subtle p-4 md:grid-cols-2 xl:grid-cols-3">
                    <flux:input autofocus wire:model="templateForm.code" :label="__('Code')" required />
                    <flux:input wire:model="templateForm.name_ar" :label="__('Arabic name')" dir="rtl" required />
                    <flux:input wire:model="templateForm.name_en" :label="__('English name')" dir="ltr" required />
                    <flux:select wire:model.live="templateForm.document_type" :label="__('Document type')">
                        @foreach (SavePrintTemplateAction::DOCUMENT_TYPES as $type)<option value="{{ $type }}">{{ DocumentTypeCatalog::printLabel($type) }}</option>@endforeach
                    </flux:select>
                    <flux:select wire:model="templateForm.paper_size" :label="__('Paper size')">
                        @foreach (SavePrintTemplateAction::PAPER_SIZES as $size)<option value="{{ $size }}">{{ __(match($size) {'58mm'=>'58 mm','80mm'=>'80 mm','a4'=>'A4','a5'=>'A5','label_50x30mm'=>'50 × 30 mm','label_40x25mm'=>'40 × 25 mm'}) }}</option>@endforeach
                    </flux:select>
                    <flux:select wire:model="templateForm.language" :label="__('Language')"><option value="ar">{{ __('Arabic') }}</option><option value="en">{{ __('English') }}</option><option value="bilingual">{{ __('Arabic and English') }}</option></flux:select>
                    <flux:textarea wire:model="templateForm.header_text" :label="__('Header text')" />
                    <flux:textarea wire:model="templateForm.footer_text" :label="__('Footer text')" />
                    <flux:select wire:model="templateForm.status" :label="__('Status')"><option value="active">{{ __('Active') }}</option><option value="inactive">{{ __('Inactive') }}</option></flux:select>
                    @if($templateForm['document_type']==='barcode_label')<div class="md:col-span-2 xl:col-span-3 grid gap-3 sm:grid-cols-3"><flux:input wire:model="templateForm.layout_settings.width_mm" type="number" step="0.1" :label="__('Label width (mm)')"/><flux:input wire:model="templateForm.layout_settings.height_mm" type="number" step="0.1" :label="__('Label height (mm)')"/><flux:select wire:model="templateForm.layout_settings.dpi" :label="__('Printer DPI')"><option value="203">203 DPI</option><option value="300">300 DPI</option></flux:select><flux:select wire:model="templateForm.layout_settings.orientation" :label="__('Orientation')"><option value="portrait">{{__('Portrait')}}</option><option value="landscape">{{__('Landscape')}}</option></flux:select><flux:input wire:model="templateForm.layout_settings.margin_mm" type="number" step="0.1" :label="__('Margin (mm)')"/><flux:input wire:model="templateForm.layout_settings.horizontal_gap_mm" type="number" step="0.1" :label="__('Horizontal gap (mm)')"/><flux:input wire:model="templateForm.layout_settings.vertical_gap_mm" type="number" step="0.1" :label="__('Vertical gap (mm)')"/><flux:input wire:model="templateForm.layout_settings.a4_rows" type="number" step="1" :label="__('A4 rows')"/><flux:input wire:model="templateForm.layout_settings.a4_columns" type="number" step="1" :label="__('A4 columns')"/><flux:select wire:model="templateForm.layout_settings.symbology" :label="__('Barcode symbology')"><option value="auto">{{__('Automatic EAN-13 / Code 128')}}</option><option value="ean13">EAN-13</option><option value="code128">Code 128</option></flux:select></div><div class="md:col-span-2 xl:col-span-3 flex flex-wrap gap-4"><flux:checkbox wire:model="templateForm.layout_settings.show_name" :label="__('Product name')"/><flux:checkbox wire:model="templateForm.layout_settings.show_price" :label="__('Price')"/><flux:checkbox wire:model="templateForm.layout_settings.show_item_code" :label="__('Item code')"/><flux:checkbox wire:model="templateForm.layout_settings.show_list_code" :label="__('Price-list code')"/><flux:checkbox wire:model="templateForm.layout_settings.show_outlet" :label="__('Outlet')"/></div>@endif
                    <div class="flex items-end justify-end gap-2 md:col-span-2 xl:col-span-3">@if($templateForm['id'])<flux:button type="button" wire:click="resetPrintTemplateForm" variant="subtle">{{ __('Cancel Edit') }}</flux:button>@endif<x-actions.loading-button type="submit" action="savePrintTemplate" :label="__('Save')" /></div>
                </form></flux:modal>
                @php($templates = PrintTemplate::visibleTo(auth()->user())->with(['printers' => fn ($query) => $query->visibleTo(auth()->user())->where('status', 'active')->with(['branch', 'store'])->orderBy('name')])->orderBy('document_type')->orderBy('code')->paginate(25, ['*'], 'templates_page'))
                <flux:table aria-label="{{ __('Print templates and assigned printers') }}">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Template') }}</flux:table.column>
                        <flux:table.column>{{ __('Document type') }}</flux:table.column>
                        <flux:table.column>{{ __('Paper / language') }}</flux:table.column>
                        <flux:table.column>{{ __('Assigned printers and scopes') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                        <flux:table.column>{{ __('Actions') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse($templates as $template)
                            @php($assignedPrinters = $template->printers->where('paper_size', $template->paper_size))
                            <flux:table.row key="print-template-{{ $template->id }}">
                                <flux:table.cell><strong>{{ str_starts_with(app()->getLocale(), 'ar') ? $template->name_ar : $template->name_en }}</strong><div class="font-mono text-xs text-text-muted">{{ $template->code }}</div></flux:table.cell>
                                <flux:table.cell>{{ DocumentTypeCatalog::printLabel($template->document_type) }}</flux:table.cell>
                                <flux:table.cell>{{ __(strtoupper($template->paper_size)) }}<div class="text-xs text-text-muted">{{ __($template->language) }}</div></flux:table.cell>
                                <flux:table.cell>
                                    @forelse($assignedPrinters as $assignedPrinter)
                                        <div class="mb-1 last:mb-0"><span class="font-medium">{{ $assignedPrinter->name }}</span><span class="text-xs text-text-muted"> · {{ $assignedPrinter->store ? (str_starts_with(app()->getLocale(), 'ar') ? $assignedPrinter->store->name_ar : $assignedPrinter->store->name_en) : ($assignedPrinter->branch ? (str_starts_with(app()->getLocale(), 'ar') ? $assignedPrinter->branch->name_ar : $assignedPrinter->branch->name_en) : __('Entire company')) }}</span></div>
                                    @empty
                                        <span class="text-sm text-text-muted">{{ __('No active compatible printer assigned') }}</span>
                                    @endforelse
                                </flux:table.cell>
                                <flux:table.cell><flux:badge size="sm" :color="$template->status==='active'?'green':'zinc'">{{ \App\Modules\Platform\Support\UiLabel::status($template->status) }}</flux:badge></flux:table.cell>
                                <flux:table.cell><div class="flex flex-wrap gap-2"><flux:button size="sm" type="button" wire:click="editPrintTemplate({{ $template->id }})">{{ __('Edit') }}</flux:button><flux:button size="sm" variant="subtle" href="{{ route('admin.settings.template-preview', $template) }}" target="_blank">{{ __('Preview') }}</flux:button></div></flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row><flux:table.cell colspan="6" class="py-8"><x-state.empty :title="__('No print templates yet')" :description="__('Add the first reusable print template.')" icon="document-text" /></flux:table.cell></flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
                {{ $templates->links() }}
                @if(false)
                <?php
                $printers = PrinterConfiguration::visibleTo(auth()->user())
                    ->with(['branch', 'store'])
                    ->orderBy('name')
                    ->limit(100)
                    ->get();
                $printMappings = [
                    'default_thermal' => ['document' => 'Sales invoice', 'template' => 'Thermal sales invoice'],
                    'gift_receipt' => ['document' => 'Gift receipt', 'template' => 'Thermal gift receipt'],
                    'return_receipt' => ['document' => 'Sales return', 'template' => 'Thermal return receipt'],
                    'shift_closing' => ['document' => 'Shift closing', 'template' => 'Thermal shift closing receipt'],
                    'barcode_label' => ['document' => 'Barcode labels', 'template' => 'Barcode label'],
                ];
                ?>

                <div class="rounded-2xl border border-cyan-300/20 bg-cyan-400/5 p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <flux:heading size="lg">{{ __('Print assignments') }}</flux:heading>
                            <x-context-help :title="__('Print assignments')" :label="__('Open contextual help')"><p>{{ __('Choose the document, a compatible template, and the printer that will output it.') }}</p></x-context-help>
                        </div>
                        <flux:button size="sm" variant="primary" href="{{ route('admin.settings', ['tab' => 'printers', 'section' => 'printer-profiles']) }}">
                            {{ __('Manage printers') }}
                        </flux:button>
                    </div>

                    <div class="mt-4 grid gap-3 md:grid-cols-3" aria-label="{{ __('Print setup path') }}">
                        <div class="flex items-center gap-3 rounded-xl bg-white/5 p-3">
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-cyan-400/20 font-semibold text-cyan-200">1</span>
                            <div>
                                <p class="font-medium">{{ __('Document') }}</p>
                                <p class="text-xs text-text-muted">{{ __('What will be printed?') }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 rounded-xl bg-white/5 p-3">
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-cyan-400/20 font-semibold text-cyan-200">2</span>
                            <div>
                                <p class="font-medium">{{ __('Template') }}</p>
                                <p class="text-xs text-text-muted">{{ __('Layout and paper size') }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 rounded-xl bg-white/5 p-3">
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-cyan-400/20 font-semibold text-cyan-200">3</span>
                            <div>
                                <p class="font-medium">{{ __('Printer and location') }}</p>
                                <p class="text-xs text-text-muted">{{ __('Where will the document be printed?') }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto rounded-2xl border border-zinc-200 dark:border-zinc-700">
                    <flux:table class="min-w-[52rem]" aria-label="{{ __('Print assignments') }}">
                        <flux:table.columns>
                            <flux:table.column>{{ __('Document') }}</flux:table.column>
                            <flux:table.column>{{ __('Template and paper size') }}</flux:table.column>
                            <flux:table.column>{{ __('Printer and location') }}</flux:table.column>
                            <flux:table.column>{{ __('Status') }}</flux:table.column>
                            <flux:table.column>{{ __('Action') }}</flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            <?php if ($printers->isNotEmpty()) { ?>
                                <?php foreach ($printers as $printer) { ?>
                                    <?php
                                        $mapping = $printMappings[(string) $printer->template_name] ?? ['document' => 'Operational document', 'template' => 'Approved template'];
                                    $paperSize = match (strtolower(trim((string) $printer->paper_size))) {
                                        '58mm', '58 mm' => __('58 mm'),
                                        '80mm', '80 mm' => __('80 mm'),
                                        'a4' => 'A4',
                                        default => (string) $printer->paper_size ?: __('Printer setting'),
                                    };
                                    $printerName = preg_match('/^default[-_]/i', (string) $printer->name)
                                        ? __('Default printer')
                                        : $printer->name;
                                    $location = $printer->store
                                        ? (str_starts_with(app()->getLocale(), 'ar') ? $printer->store->name_ar : $printer->store->name_en)
                                        : ($printer->branch
                                            ? (str_starts_with(app()->getLocale(), 'ar') ? $printer->branch->name_ar : $printer->branch->name_en)
                                            : __('Entire company'));
                                    ?>
                                    <flux:table.row key="printer-{{ $printer->id }}">
                                        <flux:table.cell class="font-medium">{{ __($mapping['document']) }}</flux:table.cell>
                                        <flux:table.cell>
                                            <div class="space-y-1">
                                                <div>{{ __($mapping['template']) }}</div>
                                                <div class="text-xs text-text-muted">{{ $paperSize }}</div>
                                            </div>
                                        </flux:table.cell>
                                        <flux:table.cell>
                                            <div class="space-y-1">
                                                <div class="font-medium">{{ $printerName }}</div>
                                                <div class="text-xs text-text-muted">{{ $location }}</div>
                                            </div>
                                        </flux:table.cell>
                                        <flux:table.cell>
                                            <div class="flex flex-wrap gap-1.5">
                                                <?php if ($printer->status === 'active') { ?>
                                                    <flux:badge size="sm" color="green">{{ __('Active') }}</flux:badge>
                                                <?php } else { ?>
                                                    <flux:badge size="sm" color="red">{{ __('Inactive') }}</flux:badge>
                                                <?php } ?>
                                                <?php if ($printer->is_default) { ?>
                                                    <flux:badge size="sm" color="zinc">{{ __('Default') }}</flux:badge>
                                                <?php } ?>
                                            </div>
                                        </flux:table.cell>
                                        <flux:table.cell>
                                            <flux:button size="sm" variant="subtle" type="button" wire:click="editPrinter({{ $printer->id }})">
                                                {{ __('Edit assignment') }}
                                            </flux:button>
                                        </flux:table.cell>
                                    </flux:table.row>
                                <?php } ?>
                            <?php } else { ?>
                                <flux:table.row>
                                    <flux:table.cell colspan="5" class="py-8 text-center">
                                        <x-state.empty
                                            :title="__('No print assignments yet')"
                                            :description="__('Add a printer, then choose a compatible template for each document.')"
                                            icon="printer"
                                        />
                                    </flux:table.cell>
                                </flux:table.row>
                            <?php } ?>
                        </flux:table.rows>
                    </flux:table>
                </div>
                @endif
            </flux:card>
            <?php } ?>
        </div>
    <?php } ?>

    <!-- TAB 6: Settings Audit Trail -->
    <?php if ($activeTab === 'audit') { ?>
        <div id="panel-audit" role="tabpanel" aria-labelledby="tab-audit" class="space-y-6">
            <div class="flex flex-wrap justify-end gap-2"><flux:button type="button" variant="subtle" wire:click="$set('activeTab', 'printers')">{{ str_starts_with(app()->getLocale(), 'ar') ? 'السابق →' : '← Previous' }}</flux:button><flux:button :href="route('initial-setup')" variant="primary" icon="check-circle" wire:navigate>{{ __('Finish setup review') }}</flux:button></div>
            <flux:card class="space-y-4">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <flux:heading size="lg">{{ __('Configuration Change History') }}</flux:heading>
                        <x-context-help :title="__('Configuration Change History')" :label="__('Open contextual help')"><p>{{ __('Read-only history. Edit settings from the tabs above.') }}</p></x-context-help>
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
                        <?php if ($settingAuditLogs->isNotEmpty()) { ?>
                            <?php foreach ($settingAuditLogs as $log) { ?>
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
                            <?php } ?>
                        <?php } else { ?>
                            <flux:table.row>
                                <flux:table.cell colspan="9" class="text-center py-4">
                                    <x-state.empty
                                        :title="__('No Audit Logs Recorded')"
                                        :description="__('Setting changes will appear here. This history is read-only.')"
                                        icon="shield-check"
                                    />
                                </flux:table.cell>
                            </flux:table.row>
                        <?php } ?>
                    </flux:table.rows>
                </flux:table>
                </div>
            </flux:card>
        </div>
    <?php } ?>
    </div>
</x-app.page>
