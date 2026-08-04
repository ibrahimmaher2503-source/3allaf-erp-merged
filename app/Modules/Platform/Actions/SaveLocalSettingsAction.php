<?php

namespace App\Modules\Platform\Actions;

use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\DocumentSequence;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Platform\Models\PrinterConfiguration;
use App\Modules\Platform\Models\TaxSetting;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class SaveLocalSettingsAction
{
    /**
     * Execute local settings save/update with correlation ID and append-only audit log.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function execute(array $data): array
    {
        Gate::authorize('company_settings.edit');
        $correlationId = Context::get('request_id') ?? (string) Str::uuid();

        return DB::transaction(function () use ($data, $correlationId) {
            // 1. Company identity update or create
            $companyData = [
                'code' => $data['company']['code'] ?? 'TBD',
                'name_ar' => $data['company']['name_ar'] ?? null,
                'name_en' => $data['company']['name_en'] ?? null,
                'legal_name' => $data['company']['legal_name'] ?? null,
                'tax_number' => $data['company']['tax_number'] ?? null,
                'commercial_registration' => $data['company']['commercial_registration'] ?? null,
                'currency_code' => $data['company']['currency_code'] ?? 'TBD',
                'currency_symbol' => $data['company']['currency_symbol'] ?? 'TBD',
                'timezone' => $data['company']['timezone'] ?? 'UTC',
                'locale_default' => $data['company']['locale_default'] ?? 'ar',
                'phone' => $data['company']['phone'] ?? null,
                'email' => $data['company']['email'] ?? null,
                'address' => $data['company']['address'] ?? null,
                'status' => $data['company']['status'] ?? 'active',
                'policy_notes' => $data['company']['policy_notes'] ?? 'TBD: Production company legal and currency policy pending owner decision.',
            ];

            $company = Company::first();
            $before = $company?->getAttributes();

            if ($company) {
                $company->update($companyData);
            } else {
                $company = Company::create($companyData);
            }

            app(RecordAuditEvent::class)->execute(
                category: 'master_data',
                event: 'update_local_settings',
                source: $company,
                before: $before,
                after: $company->getAttributes(),
            );

            return [
                'success' => true,
                'correlation_id' => $correlationId,
                'company' => $company,
            ];
        });
    }

    /**
     * Create or update a payment method setting.
     *
     * @param  array<string, mixed>  $data
     */
    public function savePaymentMethod(array $data, ?int $id = null): PaymentMethod
    {
        Gate::authorize('company_settings.edit');
        $correlationId = Context::get('request_id') ?? (string) Str::uuid();

        return DB::transaction(function () use ($data, $id, $correlationId) {
            $attributes = [
                'code' => strtoupper($data['code']),
                'name_ar' => $data['name_ar'],
                'name_en' => $data['name_en'],
                'type' => $data['type'] ?? 'manual',
                'requires_evidence' => (bool) ($data['requires_evidence'] ?? false),
                'offline_eligible' => (bool) ($data['offline_eligible'] ?? false),
                'status' => $data['status'] ?? 'active',
                'policy_notes' => $data['policy_notes'] ?? 'TBD',
            ];

            if ($id) {
                $method = PaymentMethod::findOrFail($id);
                $before = $method->getAttributes();
                $method->update($attributes);
                $action = 'update_payment_method';
            } else {
                $method = PaymentMethod::create($attributes);
                $before = null;
                $action = 'create_payment_method';
            }

            app(RecordAuditEvent::class)->execute('master_data', $action, $method, $before, $method->getAttributes());

            return $method;
        });
    }

    /**
     * Create or update a tax setting.
     *
     * @param  array<string, mixed>  $data
     */
    public function saveTaxSetting(array $data, ?int $id = null): TaxSetting
    {
        Gate::authorize('company_settings.edit');
        $correlationId = Context::get('request_id') ?? (string) Str::uuid();

        return DB::transaction(function () use ($data, $id, $correlationId) {
            $attributes = [
                'code' => strtoupper($data['code']),
                'name_ar' => $data['name_ar'],
                'name_en' => $data['name_en'],
                'rate' => $data['rate'] !== null && $data['rate'] !== '' ? $data['rate'] : null,
                'is_tax_inclusive' => (bool) ($data['is_tax_inclusive'] ?? false),
                'tax_number' => $data['tax_number'] ?? null,
                'status' => $data['status'] ?? 'active',
                'policy_notes' => $data['policy_notes'] ?? 'TBD: Production tax policy pending owner approval.',
            ];

            if ($id) {
                $tax = TaxSetting::findOrFail($id);
                $before = $tax->getAttributes();
                $tax->update($attributes);
                $action = 'update_tax_setting';
            } else {
                $tax = TaxSetting::create($attributes);
                $before = null;
                $action = 'create_tax_setting';
            }

            app(RecordAuditEvent::class)->execute('master_data', $action, $tax, $before, $tax->getAttributes());

            return $tax;
        });
    }

    /**
     * Create or update a document sequence.
     *
     * @param  array<string, mixed>  $data
     */
    public function saveDocumentSequence(array $data, ?int $id = null): DocumentSequence
    {
        Gate::authorize('company_settings.edit');
        $correlationId = Context::get('request_id') ?? (string) Str::uuid();

        return DB::transaction(function () use ($data, $id, $correlationId) {
            $attributes = [
                'document_type' => $data['document_type'],
                'prefix' => $data['prefix'] ?? null,
                'suffix' => $data['suffix'] ?? null,
                'padding_length' => (int) ($data['padding_length'] ?? 6),
                'next_value' => (int) ($data['next_value'] ?? 1),
                'reset_rule' => $data['reset_rule'] ?? 'never',
                'status' => $data['status'] ?? 'active',
                'policy_notes' => $data['policy_notes'] ?? 'TBD',
            ];

            if ($id) {
                $seq = DocumentSequence::findOrFail($id);
                $before = $seq->getAttributes();
                $seq->update($attributes);
                $action = 'update_document_sequence';
            } else {
                $seq = DocumentSequence::create($attributes);
                $before = null;
                $action = 'create_document_sequence';
            }

            app(RecordAuditEvent::class)->execute('master_data', $action, $seq, $before, $seq->getAttributes());

            return $seq;
        });
    }

    /**
     * Create or update a printer configuration.
     *
     * @param  array<string, mixed>  $data
     */
    public function savePrinterConfiguration(array $data, ?int $id = null): PrinterConfiguration
    {
        Gate::authorize('company_settings.edit');
        $correlationId = Context::get('request_id') ?? (string) Str::uuid();

        return DB::transaction(function () use ($data, $id, $correlationId) {
            $attributes = [
                'name' => $data['name'],
                'printer_type' => $data['printer_type'] ?? 'thermal',
                'paper_size' => $data['paper_size'] ?? '80mm',
                'template_name' => $data['template_name'] ?? 'default_thermal',
                'connection_type' => $data['connection_type'] ?? 'network',
                'ip_address' => $data['ip_address'] ?? null,
                'port' => $data['port'] !== null && $data['port'] !== '' ? (int) $data['port'] : null,
                'is_default' => (bool) ($data['is_default'] ?? false),
                'status' => $data['status'] ?? 'active',
                'notes' => $data['notes'] ?? 'TBD',
            ];

            if ($id) {
                $printer = PrinterConfiguration::findOrFail($id);
                $before = $printer->getAttributes();
                $printer->update($attributes);
                $action = 'update_printer_configuration';
            } else {
                $printer = PrinterConfiguration::create($attributes);
                $before = null;
                $action = 'create_printer_configuration';
            }

            app(RecordAuditEvent::class)->execute('master_data', $action, $printer, $before, $printer->getAttributes());

            return $printer;
        });
    }
}
