<?php

declare(strict_types=1);

namespace App\Modules\Customer\Support;

final class CustomerPolicySettingRegistry
{
    /**
     * @return array<string, array{title: string, description: string}>
     */
    public static function all(): array
    {
        return [
            'customer.phone_normalization' => [
                'title' => 'Phone normalization policy',
                'description' => 'Owner-configurable normalization and duplicate-review convention.',
            ],
            'customer.consent.purpose' => [
                'title' => 'Consent purpose scope',
                'description' => 'Purpose categories that may be captured for customer data.',
            ],
            'customer.consent.wording' => [
                'title' => 'Consent wording/version',
                'description' => 'Legal wording and version reference; not legal approval.',
            ],
            'customer.consent.retention' => [
                'title' => 'Consent retention policy',
                'description' => 'Owner/legal retention rule for consent records.',
            ],
            'customer.children.purpose_scope' => [
                'title' => 'Children data purpose scope',
                'description' => 'Purpose and access boundary for child/birthday fields.',
            ],
            'customer.history.visibility' => [
                'title' => 'Unified history visibility',
                'description' => 'Role and activity scope for customer history tabs.',
            ],
            'loyalty.retail_rule' => [
                'title' => 'Retail loyalty rule',
                'description' => 'Configurable retail earn/redeem rule reference; no calculation is enabled.',
            ],
            'loyalty.party_rule' => [
                'title' => 'Party loyalty rule',
                'description' => 'Configurable party earn/redeem rule reference; no calculation is enabled.',
            ],
            'loyalty.expiry_policy' => [
                'title' => 'Loyalty expiry policy',
                'description' => 'Owner-configurable expiry convention; no expiry action is enabled.',
            ],
            'loyalty.rounding_policy' => [
                'title' => 'Loyalty rounding policy',
                'description' => 'Owner-configurable rounding convention; no points calculation is enabled.',
            ],
            'loyalty.approval_policy' => [
                'title' => 'Loyalty approval policy',
                'description' => 'Approval and adjustment separation reference; no approval action is enabled.',
            ],
            'loyalty.ledger_integrity' => [
                'title' => 'Loyalty ledger integrity policy',
                'description' => 'Source/idempotency/concurrency convention; no ledger exists in this slice.',
            ],
            'wallet.product.credit_limit' => [
                'title' => 'Product Wallet credit limit',
                'description' => 'Owner-configurable local placeholder; no credit entry or balance calculation is enabled.',
            ],
            'wallet.product.debt_limit' => [
                'title' => 'Product Wallet debt limit',
                'description' => 'Owner-configurable local placeholder; no debt entry or balance calculation is enabled.',
            ],
            'wallet.product.settlement_policy' => [
                'title' => 'Product Wallet settlement policy',
                'description' => 'Settlement convention reference; no settlement or payment action is enabled.',
            ],
            'wallet.product.adjustment_policy' => [
                'title' => 'Product Wallet adjustment policy',
                'description' => 'Correction and approval convention; no adjustment action is enabled.',
            ],
            'wallet.product.visibility_scope' => [
                'title' => 'Product Wallet visibility scope',
                'description' => 'Role and source visibility convention; no cross-scope exposure is enabled.',
            ],
            'wallet.party.credit_limit' => [
                'title' => 'Party Wallet credit limit',
                'description' => 'Owner-configurable local placeholder; no credit entry or balance calculation is enabled.',
            ],
            'wallet.party.debt_limit' => [
                'title' => 'Party Wallet debt limit',
                'description' => 'Owner-configurable local placeholder; no debt entry or balance calculation is enabled.',
            ],
            'wallet.party.settlement_policy' => [
                'title' => 'Party Wallet settlement policy',
                'description' => 'Settlement convention reference; no settlement or payment action is enabled.',
            ],
            'wallet.party.adjustment_policy' => [
                'title' => 'Party Wallet adjustment policy',
                'description' => 'Correction and approval convention; no adjustment action is enabled.',
            ],
            'wallet.party.visibility_scope' => [
                'title' => 'Party Wallet visibility scope',
                'description' => 'Role and source visibility convention; no cross-scope exposure is enabled.',
            ],
        ];
    }
}
