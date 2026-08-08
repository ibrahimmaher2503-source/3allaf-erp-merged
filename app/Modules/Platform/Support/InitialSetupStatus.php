<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support;

use App\Models\User;
use App\Modules\Customer\Models\CustomerPolicySettingVersion;
use App\Modules\Platform\Enums\ApprovalState;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\PrinterConfiguration;
use App\Modules\Platform\Models\Store;
use App\Modules\Purchasing\Models\FinancialSettingVersion;
use App\Modules\Purchasing\Models\SupplierReturnReason;
use Illuminate\Database\Eloquent\Builder;

final class InitialSetupStatus
{
    /**
     * @return array{
     *     steps: list<array{key: string, label: string, description: string, route: string, complete: bool, required: bool, status: string}>,
     *     completed_count: int,
     *     required_count: int,
     *     progress_percent: int,
     *     needs_attention: bool,
     *     complete: bool,
     * }
     */
    public function snapshot(): array
    {
        $steps = [
            [
                'key' => 'company',
                'label' => (string) __('Company identity'),
                'description' => (string) __('Add the company name, currency, timezone, and legal identity.'),
                'route' => route('admin.settings'),
                'complete' => $this->companyReady(),
                'required' => true,
            ],
            [
                'key' => 'branches-stores',
                'label' => (string) __('Branches and stores'),
                'description' => (string) __('Create at least one active branch and store before operational data is entered.'),
                'route' => route('admin.branches'),
                'complete' => $this->branchesAndStoresReady(),
                'required' => true,
            ],
            [
                'key' => 'supplier-return-reasons',
                'label' => (string) __('Supplier return reasons'),
                'description' => (string) __('Add the owner-provided bilingual reason catalog before creating supplier returns.'),
                'route' => route('purchasing.returns.settings'),
                'complete' => SupplierReturnReason::query()->where('is_active', true)->exists(),
                'required' => true,
            ],
            [
                'key' => 'financial-settings',
                'label' => (string) __('Approved financial settings'),
                'description' => (string) __('Approved numbering and print settings are required; approval limits remain optional until the owner decides them.'),
                'route' => route('purchasing.returns.settings'),
                'complete' => $this->financialSettingsReady(),
                'required' => true,
            ],
            [
                'key' => 'users-permissions',
                'label' => (string) __('Users and permissions'),
                'description' => (string) __('Review active roles and scope assignments for the opening team.'),
                'route' => route('admin.authorization-baseline'),
                'complete' => User::query()->whereHas('roles', static fn (Builder $query): Builder => $query->where('roles.status', 'active'))->exists(),
                'required' => true,
            ],
            [
                'key' => 'wallet-policies',
                'label' => (string) __('Wallet policy values'),
                'description' => (string) __('Review Product Wallet and Party Wallet limits and policies; blank values remain PENDING and no wallet mutation is enabled.'),
                'route' => route('admin.settings.customer-loyalty'),
                'complete' => $this->walletPolicyValuesConfigured(),
                'required' => false,
            ],
            [
                'key' => 'gift-instruments',
                'label' => (string) __('Gift Card and Gift Receipt policies'),
                'description' => (string) __('Review eligibility, validity, holder, void, reprint, and format values; blanks remain PENDING and no instrument mutation is enabled.'),
                'route' => route('gift.receipts'),
                'complete' => $this->giftPolicyValuesConfigured(),
                'required' => false,
            ],
            [
                'key' => 'return-policies',
                'label' => (string) __('Return and Exchange policies'),
                'description' => (string) __('Review source, window, condition, approval, settlement, and print values; blanks remain PENDING and no return mutation is enabled.'),
                'route' => route('returns.readiness'),
                'complete' => $this->returnPolicyValuesConfigured(),
                'required' => false,
            ],
            [
                'key' => 'party-policies',
                'label' => (string) __('Party booking and working invoice policies'),
                'description' => (string) __('Review party-only stores, services, schedule, privacy, cancellation, pricing, and final-close values; blanks remain PENDING and no party mutation is enabled.'),
                'route' => route('party.readiness'),
                'complete' => $this->partyPolicyValuesConfigured(),
                'required' => false,
            ],
            [
                'key' => 'party-payment-policies',
                'label' => (string) __('Party payment and balance policies'),
                'description' => (string) __('Review Party-only payment, evidence, receipt, balance, idempotency, and Party Wallet values; blanks remain PENDING and no financial mutation is enabled.'),
                'route' => route('party.payments.readiness'),
                'complete' => $this->partyPaymentPolicyValuesConfigured(),
                'required' => false,
            ],
            [
                'key' => 'party-operating-policies',
                'label' => (string) __('Party operating-order and consumable policies'),
                'description' => (string) __('Review Party-only operating order, consumable, issue, return, reconciliation, approval, audit, and print values; blanks remain PENDING and no stock mutation is enabled.'),
                'route' => route('party.operating.readiness'),
                'complete' => $this->partyOperatingPolicyValuesConfigured(),
                'required' => false,
            ],
            [
                'key' => 'rental-asset-policies',
                'label' => (string) __('Rental asset and calendar policies'),
                'description' => (string) __('Review asset identity, separation, availability, reservation, checkout, return, condition, approval, audit, and print values; blanks remain PENDING and no asset mutation is enabled.'),
                'route' => route('party.assets.readiness'),
                'complete' => $this->rentalAssetPolicyValuesConfigured(),
                'required' => false,
            ],
            [
                'key' => 'rental-asset-event-policies',
                'label' => (string) __('Rental asset event and depreciation policies'),
                'description' => (string) __('Review damage, loss, maintenance, assessment, responsibility, evidence, cost privacy, approval, depreciation, and correction values; blanks remain PENDING and no event mutation is enabled.'),
                'route' => route('party.asset-events.readiness'),
                'complete' => $this->rentalAssetEventPolicyValuesConfigured(),
                'required' => false,
            ],
            [
                'key' => 'party-final-close-policies',
                'label' => (string) __('Party final-close and settlement policies'),
                'description' => (string) __('Review final readiness, invoice freeze, payment reconciliation, credit, Party Wallet settlement, receipt, approval, idempotency, numbering, and print values; blanks remain PENDING and no close mutation is enabled.'),
                'route' => route('party.final-close.readiness'),
                'complete' => $this->partyFinalClosePolicyValuesConfigured(),
                'required' => false,
            ],
            [
                'key' => 'quotation-policies',
                'label' => (string) __('Quotation and proposal policies'),
                'description' => (string) __('Review typed activity, customer, validity, status, price, terms, approval, numbering, print/share, and future conversion values; blanks remain PENDING and no quote action is enabled.'),
                'route' => route('quotations.readiness'),
                'complete' => $this->quotationPolicyValuesConfigured(),
                'required' => false,
            ],
            [
                'key' => 'reporting-policies',
                'label' => (string) __('Dashboard and reporting policies'),
                'description' => (string) __('Review report source lineage, scope, filters, KPI formulas, reconciliation, alerts, pagination, export, precision, and freshness; blanks remain PENDING and no metric is certified.'),
                'route' => route('reports.readiness'),
                'complete' => $this->reportPolicyValuesConfigured(),
                'required' => false,
            ],
            [
                'key' => 'alert-policies',
                'label' => (string) __('Operational alert and exception policies'),
                'description' => (string) __('Review alert triggers, severity, ownership, scope, lifecycle, source links, deduplication, notification, and queue navigation; blanks remain PENDING and no alert is created.'),
                'route' => route('alerts.readiness'),
                'complete' => $this->alertPolicyValuesConfigured(),
                'required' => false,
            ],
            [
                'key' => 'export-audit-policies',
                'label' => (string) __('Export and audit policies'),
                'description' => (string) __('Review formats, limits, retention, redaction, formula safety, reauthorization, audit export, and bounded audit filters; blanks remain PENDING and no artifact is generated.'),
                'route' => route('exports.audit.readiness'),
                'complete' => $this->exportAuditPolicyValuesConfigured(),
                'required' => false,
            ],
            [
                'key' => 'master-data-migration',
                'label' => (string) __('Master-data import and cutover'),
                'description' => (string) __('Review approved source, load order, create-only staging, reconciliation, maker/checker, backup, and cutover values; blanks remain PENDING and no import is enabled.'),
                'route' => route('master-data.migration.readiness'),
                'complete' => $this->masterDataMigrationPolicyValuesConfigured(),
                'required' => false,
            ],
            [
                'key' => 'operations-readiness',
                'label' => (string) __('Production operations and handover'),
                'description' => (string) __('Review runtime, secrets, workers, storage, monitoring, backup/restore, devices, support, and training values; blanks remain PENDING and no production claim is enabled.'),
                'required' => false,
                'route' => route('operations.readiness'),
                'complete' => $this->operationsReadinessPolicyValuesConfigured(),
            ],
            [
                'key' => 'printers',
                'label' => (string) __('Printer configuration'),
                'description' => (string) __('Review the local printer profile and leave production device values pending until verified.'),
                'route' => route('admin.settings'),
                'complete' => PrinterConfiguration::query()->where('status', 'active')->exists(),
                'required' => false,
            ],
        ];

        $requiredSteps = array_filter($steps, static fn (array $step): bool => $step['required']);
        $completedCount = count(array_filter($requiredSteps, static fn (array $step): bool => $step['complete']));
        $requiredCount = count($requiredSteps);

        return [
            'steps' => array_values(array_map(static function (array $step): array {
                $step['status'] = $step['complete'] ? 'complete' : ($step['required'] ? 'required' : 'optional');

                return $step;
            }, $steps)),
            'completed_count' => $completedCount,
            'required_count' => $requiredCount,
            'progress_percent' => (int) round(($completedCount / $requiredCount) * 100),
            'needs_attention' => $completedCount < $requiredCount,
            'complete' => $completedCount === $requiredCount,
        ];
    }

    private function companyReady(): bool
    {
        return Company::query()
            ->where('status', 'active')
            ->whereNotNull('name_en')
            ->where('name_en', '!=', '')
            ->whereNotIn('currency_code', ['', 'TBD'])
            ->whereNotIn('currency_symbol', ['', 'TBD'])
            ->exists();
    }

    private function branchesAndStoresReady(): bool
    {
        return Branch::query()->where('status', 'active')->exists()
            && Store::query()->where('status', 'active')->exists();
    }

    private function walletPolicyValuesConfigured(): bool
    {
        return CustomerPolicySettingVersion::query()
            ->where('key', 'like', 'wallet.%')
            ->whereNotNull('value')
            ->where('value', '!=', '')
            ->exists();
    }

    private function giftPolicyValuesConfigured(): bool
    {
        return CustomerPolicySettingVersion::query()
            ->where('key', 'like', 'gift.%')
            ->whereNotNull('value')
            ->where('value', '!=', '')
            ->exists();
    }

    private function returnPolicyValuesConfigured(): bool
    {
        return CustomerPolicySettingVersion::query()
            ->where('key', 'like', 'return.%')
            ->whereNotNull('value')
            ->where('value', '!=', '')
            ->exists();
    }

    private function partyPolicyValuesConfigured(): bool
    {
        return CustomerPolicySettingVersion::query()
            ->where('key', 'like', 'party.%')
            ->whereNotNull('value')
            ->where('value', '!=', '')
            ->exists();
    }

    private function partyPaymentPolicyValuesConfigured(): bool
    {
        return CustomerPolicySettingVersion::query()
            ->where('key', 'like', 'party.%')
            ->whereIn('key', [
                'party.payment_method',
                'party.deposit',
                'party.payment_evidence',
                'party.payment_idempotency',
                'party.overpayment',
                'party.receipt',
                'party.balance',
                'party.wallet_settlement',
                'party.payment_approval',
            ])
            ->whereNotNull('value')
            ->where('value', '!=', '')
            ->exists();
    }

    private function partyOperatingPolicyValuesConfigured(): bool
    {
        return CustomerPolicySettingVersion::query()
            ->whereIn('key', [
                'party.operating_order',
                'party.operating_store',
                'party.consumable_uom',
                'party.issue_actuals',
                'party.return_movement',
                'party.stock_reconciliation',
                'party.operating_approval',
                'party.operating_idempotency',
                'party.operating_print',
            ])
            ->whereNotNull('value')
            ->where('value', '!=', '')
            ->exists();
    }

    private function rentalAssetPolicyValuesConfigured(): bool
    {
        return CustomerPolicySettingVersion::query()
            ->whereIn('key', [
                'asset.identity',
                'asset.separation',
                'asset.availability',
                'asset.reservation',
                'asset.concurrency',
                'asset.checkout',
                'asset.return',
                'asset.condition',
                'asset.approval',
                'asset.print',
            ])
            ->whereNotNull('value')
            ->where('value', '!=', '')
            ->exists();
    }

    private function rentalAssetEventPolicyValuesConfigured(): bool
    {
        return CustomerPolicySettingVersion::query()
            ->whereIn('key', [
                'asset.damage', 'asset.loss', 'asset.maintenance', 'asset.assessment', 'asset.responsibility',
                'asset.evidence', 'asset.cost', 'asset.damage_approval', 'asset.depreciation', 'asset.correction',
            ])
            ->whereNotNull('value')
            ->where('value', '!=', '')
            ->exists();
    }

    private function reportPolicyValuesConfigured(): bool
    {
        return CustomerPolicySettingVersion::query()
            ->whereIn('key', [
                'report.source_lineage', 'report.scope', 'report.filters', 'report.kpi', 'report.reconciliation',
                'report.alerts', 'report.pagination', 'report.export', 'report.precision', 'report.freshness',
            ])
            ->whereNotNull('value')
            ->where('value', '!=', '')
            ->exists();
    }

    private function quotationPolicyValuesConfigured(): bool
    {
        return CustomerPolicySettingVersion::query()
            ->whereIn('key', [
                'quotation.type', 'quotation.customer', 'quotation.validity', 'quotation.status', 'quotation.prices',
                'quotation.terms', 'quotation.approval', 'quotation.numbering', 'quotation.print_share', 'quotation.conversion',
            ])
            ->whereNotNull('value')
            ->where('value', '!=', '')
            ->exists();
    }

    private function partyFinalClosePolicyValuesConfigured(): bool
    {
        return CustomerPolicySettingVersion::query()
            ->whereIn('key', [
                'party.final_readiness', 'party.invoice_freeze', 'party.payment_reconcile', 'party.credit',
                'party.wallet_settlement', 'party.final_receipt', 'party.final_approval', 'party.final_idempotency',
                'party.final_numbering', 'party.final_print',
            ])
            ->whereNotNull('value')
            ->where('value', '!=', '')
            ->exists();
    }

    private function alertPolicyValuesConfigured(): bool
    {
        return CustomerPolicySettingVersion::query()
            ->whereIn('key', [
                'alert.trigger', 'alert.severity', 'alert.owner', 'alert.scope', 'alert.lifecycle',
                'alert.source_link', 'alert.deduplication', 'alert.notification', 'alert.navigation',
            ])
            ->whereNotNull('value')
            ->where('value', '!=', '')
            ->exists();
    }

    private function exportAuditPolicyValuesConfigured(): bool
    {
        return CustomerPolicySettingVersion::query()
            ->whereIn('key', [
                'export.format', 'export.limits', 'export.retention', 'export.redaction',
                'export.formula_safety', 'export.permission', 'export.audit', 'audit.filters',
            ])
            ->whereNotNull('value')
            ->where('value', '!=', '')
            ->count() === 8;
    }

    private function masterDataMigrationPolicyValuesConfigured(): bool
    {
        return CustomerPolicySettingVersion::query()
            ->whereIn('key', [
                'migration.source', 'migration.load_order', 'migration.create_only', 'migration.file_safety',
                'migration.duplicate', 'migration.stage_validation', 'migration.reconciliation',
                'migration.approval', 'migration.cutover',
            ])
            ->whereNotNull('value')
            ->where('value', '!=', '')
            ->count() === 9;
    }

    private function operationsReadinessPolicyValuesConfigured(): bool
    {
        return CustomerPolicySettingVersion::query()
            ->whereIn('key', [
                'operations.runtime', 'operations.secrets', 'operations.workers', 'operations.storage',
                'operations.monitoring', 'operations.backup', 'operations.devices', 'operations.training',
            ])
            ->whereNotNull('value')
            ->where('value', '!=', '')
            ->count() === 8;
    }

    private function financialSettingsReady(): bool
    {
        $requiredKeys = [
            'purchasing.supplier_return.number_prefix',
            'purchasing.supplier_return.print_title',
            'purchasing.supplier_return.print_footer',
        ];

        return FinancialSettingVersion::query()
            ->whereIn('key', $requiredKeys)
            ->where('effective_from', '<=', now())
            ->where(static fn (Builder $query): Builder => $query->whereNull('effective_to')->orWhere('effective_to', '>', now()))
            ->whereHas('approvalRecord', static fn (Builder $query): Builder => $query->where('approval_state', ApprovalState::Approved))
            ->distinct('key')
            ->count('key') === count($requiredKeys);
    }
}
