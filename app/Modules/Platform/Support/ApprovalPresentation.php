<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support;

use App\Models\User;
use App\Modules\Platform\Actions\AuditLogValueRedactor;
use App\Modules\Platform\Enums\TaxTreatment;
use App\Modules\Platform\Models\ApprovalRecord;
use Illuminate\Support\Str;

final class ApprovalPresentation
{
    /** @var array<string, string> */
    private const TAX_SETTING_FIELDS = [
        'code' => 'Code',
        'name_ar' => 'Name (Arabic)',
        'name_en' => 'Name (English)',
        'rate' => 'Rate %',
        'treatment' => 'Treatment',
        'is_default' => 'Default',
        'is_tax_inclusive' => 'Inclusive',
        'effective_from' => 'Effective from',
        'effective_to' => 'Effective to',
        'status' => 'Status',
    ];

    /**
     * Return only the reviewed platform-setting payload fields needed by the inbox.
     *
     * @return array{type: 'tax_setting', rows: list<array{label: string, before: string, after: string}>}|array{type: 'branch_delete', counts: list<array{label: string, value: int}>}|null
     */
    public static function reviewDetails(ApprovalRecord $record, User $viewer): ?array
    {
        if ($record->source_type !== 'platform_settings') {
            return null;
        }

        $context = $record->limit_context ?? [];

        return match ($context['resource'] ?? null) {
            'tax_setting' => [
                'type' => 'tax_setting',
                'rows' => self::taxSettingChanges($context, $viewer),
            ],
            'branch_delete' => [
                'type' => 'branch_delete',
                'counts' => self::branchDeleteCounts($context),
            ],
            default => null,
        };
    }

    public static function source(string $type, int|string|null $id = null): string
    {
        if ($type === 'pos_shifts') {
            return $id === null ? __('POS shifts') : __('POS shift #:id', ['id' => $id]);
        }

        $label = __(match ($type) {
            'platform_settings' => 'Platform settings',
            'pricing_labels' => 'Pricing labels',
            'purchase_invoices' => 'Purchase invoices',
            'purchase_returns' => 'Purchase returns',
            'loyalty_adjustments' => 'Loyalty adjustments',
            'product_wallet_adjustments' => 'Product Wallet adjustments',
            'party_wallet_adjustments' => 'Party Wallet adjustments',
            'pos_open_price' => 'POS open-price request',
            'pos_discount' => 'POS discount request',
            'asset_events' => 'Asset event',
            default => Str::headline(str_replace('_', ' ', $type)),
        });

        return $id === null ? $label : $label.' #'.$id;
    }

    public static function action(string $action): string
    {
        return __(match ($action) {
            'approve_close' => 'Approve shift close',
            'store_archive', 'store_delete' => 'Request archive',
            default => Str::headline(str_replace('_', ' ', $action)),
        });
    }

    /**
     * @param  array<string, mixed>  $context
     * @return list<array{label: string, before: string, after: string}>
     */
    private static function taxSettingChanges(array $context, User $viewer): array
    {
        $before = is_array($context['before'] ?? null) ? $context['before'] : [];
        $proposed = is_array($context['proposed'] ?? null) ? $context['proposed'] : [];
        $allowedFields = array_fill_keys(array_keys(self::TAX_SETTING_FIELDS), true);
        $redactor = app(AuditLogValueRedactor::class);
        $before = $redactor->redactForViewer(array_intersect_key($before, $allowedFields), $viewer) ?? [];
        $proposed = $redactor->redactForViewer(array_intersect_key($proposed, $allowedFields), $viewer) ?? [];
        $rows = [];

        foreach (self::TAX_SETTING_FIELDS as $field => $label) {
            if (! array_key_exists($field, $before) && ! array_key_exists($field, $proposed)) {
                continue;
            }

            $rows[] = [
                'label' => __($label),
                'before' => self::taxSettingValue($field, $before[$field] ?? null),
                'after' => self::taxSettingValue($field, $proposed[$field] ?? null),
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return list<array{label: string, value: int}>
     */
    private static function branchDeleteCounts(array $context): array
    {
        $proposed = is_array($context['proposed'] ?? null) ? $context['proposed'] : [];
        $fields = [
            'store_count' => 'Linked stores',
            'active_store_count' => 'Active stores',
            'active_selling_mapping_count' => 'Active selling-store mappings',
            'active_cash_drawer_count' => 'Active cash drawers',
            'active_user_scope_count' => 'Active user scope assignments',
        ];
        $counts = [];

        foreach ($fields as $field => $label) {
            $count = filter_var($proposed[$field] ?? null, FILTER_VALIDATE_INT);
            if ($count === false || $count < 0) {
                continue;
            }

            $counts[] = ['label' => __($label), 'value' => $count];
        }

        return $counts;
    }

    private static function taxSettingValue(string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return __('Not set');
        }

        if (in_array($field, ['is_default', 'is_tax_inclusive'], true)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? __('Yes') : __('No');
        }

        if (! is_scalar($value)) {
            return __('Not set');
        }

        return match ($field) {
            'rate' => is_numeric($value) ? number_format((float) $value, 2, '.', '') : (string) $value,
            'treatment' => TaxTreatment::tryFrom((string) $value)?->label() ?? (string) $value,
            'status' => UiLabel::status((string) $value),
            'effective_from', 'effective_to' => substr((string) $value, 0, 10),
            default => (string) $value,
        };
    }
}
