<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support;

use App\Models\User;
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
