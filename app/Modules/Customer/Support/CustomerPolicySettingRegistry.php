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
            'gift.receipt.eligibility' => [
                'title' => 'Gift Receipt eligibility policy',
                'description' => 'Eligible sale lines/source conditions; no Gift Receipt issue or use is enabled.',
            ],
            'gift.receipt.reprint' => [
                'title' => 'Gift Receipt reprint policy',
                'description' => 'Reprint reason, privacy, and authorization convention; no print artifact is created.',
            ],
            'gift.receipt.format' => [
                'title' => 'Gift Receipt format',
                'description' => 'Owner-configurable format and reference fields; prices remain prohibited.',
            ],
            'gift.card.validity' => [
                'title' => 'Gift Card validity policy',
                'description' => 'Validity/expiry convention; no Gift Card balance or expiry action is enabled.',
            ],
            'gift.card.holder' => [
                'title' => 'Gift Card holder policy',
                'description' => 'Holder/reference and privacy convention; no card reference is created.',
            ],
            'gift.card.redemption' => [
                'title' => 'Gift Card redemption policy',
                'description' => 'Partial/full redemption and concurrency convention; no redemption is enabled.',
            ],
            'gift.card.void' => [
                'title' => 'Gift Card void policy',
                'description' => 'Void reason and approval convention; no void action is enabled.',
            ],
            'gift.card.format' => [
                'title' => 'Gift Card format',
                'description' => 'Identifier/reference and issue/use output convention; no card is issued.',
            ],
            'return.reference.requirement' => [
                'title' => 'Return source reference policy',
                'description' => 'Invoice or Gift Receipt source requirement; no-reference returns remain disabled.',
            ],
            'return.window' => [
                'title' => 'Return eligibility window',
                'description' => 'Owner-configurable timing rule; no return eligibility is calculated in this slice.',
            ],
            'return.exception' => [
                'title' => 'Return exception reasons',
                'description' => 'No-reference, out-of-window, and manager-review reason catalog; no exception is enabled.',
            ],
            'return.condition' => [
                'title' => 'Return condition and disposition',
                'description' => 'Sellable, non-saleable, damaged, and review outcomes; no stock disposition is posted.',
            ],
            'return.approval' => [
                'title' => 'Return approval and SoD',
                'description' => 'Inspection, approval, and separation-of-duties convention; no approval action is enabled.',
            ],
            'return.refund' => [
                'title' => 'Refund and settlement policy',
                'description' => 'Cash, original method, and Gift Card settlement rules; no refund/payment mutation is enabled.',
            ],
            'return.exchange' => [
                'title' => 'Exchange difference policy',
                'description' => 'Same/different item and price-difference convention; no exchange is enabled.',
            ],
            'return.print' => [
                'title' => 'Return and exchange print policy',
                'description' => 'Reference, privacy, and output format; no return/exchange artifact is generated.',
            ],
            'party.store_scope' => [
                'title' => 'Party store scope',
                'description' => 'Party-only store and operational context; no retail/party mixing is enabled.',
            ],
            'party.service_catalog' => [
                'title' => 'Party service and package catalog',
                'description' => 'Owner-configurable service/package master data; no booking line is created.',
            ],
            'party.schedule_timezone' => [
                'title' => 'Party schedule and timezone',
                'description' => 'Schedule, location, timezone, conflict, and reschedule convention; no calendar booking is enabled.',
            ],
            'party.customer_child' => [
                'title' => 'Party customer and child data',
                'description' => 'Required contact/child/privacy fields; no customer or child record is created in this slice.',
            ],
            'party.cancellation' => [
                'title' => 'Party cancellation policy',
                'description' => 'Cancellation and reschedule terms; no cancellation action is enabled.',
            ],
            'party.responsibility' => [
                'title' => 'Party responsibility assignment',
                'description' => 'Assigned staff/responsibility convention; no assignment is persisted.',
            ],
            'party.working_invoice' => [
                'title' => 'Working invoice policy',
                'description' => 'Editable-before-close and immutable-after-close convention; no invoice is created.',
            ],
            'party.price_policy' => [
                'title' => 'Party price policy',
                'description' => 'Owner-configurable service/package pricing; no amount or financial default is enabled.',
            ],
            'party.deposit_policy' => [
                'title' => 'Party deposit policy',
                'description' => 'Deposit/payment-on-account convention; no payment, receipt, or Party Wallet mutation is enabled.',
            ],
            'party.final_close' => [
                'title' => 'Party final-close checklist',
                'description' => 'Readiness checks before immutable final invoice; no final close is enabled.',
            ],
            'party.payment_method' => [
                'title' => 'Party payment methods',
                'description' => 'Allowed payment source and method policy; no payment is posted.',
            ],
            'party.deposit' => [
                'title' => 'Party deposit and payment-on-account',
                'description' => 'Deposit, partial payment, and payment-on-account rules; no receipt is created.',
            ],
            'party.payment_evidence' => [
                'title' => 'Party payment evidence',
                'description' => 'Evidence, source, attachment, and privacy requirements; no file is uploaded.',
            ],
            'party.payment_idempotency' => [
                'title' => 'Party payment idempotency',
                'description' => 'Duplicate, retry, concurrency, and reversal rules; no payment action is enabled.',
            ],
            'party.overpayment' => [
                'title' => 'Party overpayment and residual',
                'description' => 'Underpayment, overpayment, credit, and residual treatment; no balance is calculated.',
            ],
            'party.receipt' => [
                'title' => 'Party receipt wording and numbering',
                'description' => 'Required label, numbering, reprint, and privacy policy; no receipt is generated.',
            ],
            'party.balance' => [
                'title' => 'Party balance visibility',
                'description' => 'Source-linked balance visibility and reconciliation policy; no amount is rendered.',
            ],
            'party.wallet_settlement' => [
                'title' => 'Party Wallet settlement',
                'description' => 'Party Wallet-only settlement source policy; Product Wallet use remains blocked.',
            ],
            'party.payment_approval' => [
                'title' => 'Party payment approval and SoD',
                'description' => 'Create/approve/reverse separation and actor scope; no approval action is enabled.',
            ],
            'party.operating_order' => [
                'title' => 'Party operating-order lifecycle',
                'description' => 'Draft, release, execute, complete, and immutable history rules; no order is created.',
            ],
            'party.operating_store' => [
                'title' => 'Party store and resource scope',
                'description' => 'Party-only store, service, rental, responsibility, and source scope; no stock is reserved.',
            ],
            'party.consumable_uom' => [
                'title' => 'Party consumables and UOM',
                'description' => 'Consumable catalog, units, fractions, availability, and source mapping; no quantity is rendered.',
            ],
            'party.issue_actuals' => [
                'title' => 'Party issue and actual consumption',
                'description' => 'Issue, actual, controlled additions/removals, and operator evidence; no issue is posted.',
            ],
            'party.return_movement' => [
                'title' => 'Party unused return movement',
                'description' => 'Eligible unused return, referenced movement, condition, and approval rules; no return is posted.',
            ],
            'party.stock_reconciliation' => [
                'title' => 'Party stock reconciliation',
                'description' => 'Source, balance, concurrency, and no-direct-edit rules; no stock balance is changed.',
            ],
            'party.operating_approval' => [
                'title' => 'Party operating approval and SoD',
                'description' => 'Release, issue, return, override, and completion separation; no approval action is enabled.',
            ],
            'party.operating_idempotency' => [
                'title' => 'Party operating idempotency and audit',
                'description' => 'Duplicate, retry, lock, audit, and immutable history rules; no movement is created.',
            ],
            'party.operating_print' => [
                'title' => 'Party operating print boundary',
                'description' => 'Order, issue, return, privacy, and print format rules; no document is generated.',
            ],
            'asset.identity' => [
                'title' => 'Rental asset identity',
                'description' => 'Unique code, name, category, location, status, condition, and history; no asset is created.',
            ],
            'asset.separation' => [
                'title' => 'Asset and consumable separation',
                'description' => 'Unique rental assets remain separate from consumables and retail products; no item is mixed.',
            ],
            'asset.availability' => [
                'title' => 'Asset availability and states',
                'description' => 'Available, reserved, checked out, inspection, damaged, maintenance, retired, and lost states remain pending.',
            ],
            'asset.reservation' => [
                'title' => 'Asset reservation interval',
                'description' => 'Party source, interval, timezone, buffer, overlap, cancellation, and reschedule rules; no reservation is created.',
            ],
            'asset.concurrency' => [
                'title' => 'Asset reservation concurrency',
                'description' => 'Overlap lock, retry, idempotency, and conflict behavior; no calendar allocation is enabled.',
            ],
            'asset.checkout' => [
                'title' => 'Asset checkout and pre-condition',
                'description' => 'Party, asset, responsible user, location, pre-condition, and evidence rules; no checkout is recorded.',
            ],
            'asset.return' => [
                'title' => 'Asset return and post-condition',
                'description' => 'Return location, time, post-condition, missing/damaged status, inspector, and evidence rules; no return is recorded.',
            ],
            'asset.condition' => [
                'title' => 'Asset condition checklist',
                'description' => 'Owner-configurable before/after checklist and evidence requirements; no condition is posted.',
            ],
            'asset.approval' => [
                'title' => 'Asset approval and audit',
                'description' => 'Reservation, checkout, return, override, and state-transition authorization; no action is enabled.',
            ],
            'asset.print' => [
                'title' => 'Asset calendar and print boundary',
                'description' => 'Calendar, reservation, checkout, return, privacy, and print formats; no document is generated.',
            ],
            'asset.damage' => ['title' => 'Asset damage assessment', 'description' => 'Source-linked damage event and assessment rules; no event is created.'],
            'asset.loss' => ['title' => 'Asset loss and final state', 'description' => 'Loss reason, responsibility, evidence, and final-state rules; no state is changed.'],
            'asset.maintenance' => ['title' => 'Asset maintenance lifecycle', 'description' => 'Maintenance reason, status, owner, release, and evidence rules; no maintenance event is recorded.'],
            'asset.assessment' => ['title' => 'Asset assessment method', 'description' => 'Owner-configurable assessment/checklist method; no assessment is submitted.'],
            'asset.responsibility' => ['title' => 'Asset responsibility', 'description' => 'Party/source, responsible actor, reviewer, and scope rules; no actor is assigned.'],
            'asset.evidence' => ['title' => 'Asset event evidence', 'description' => 'Attachment purpose, source reference, privacy, retention, and access rules; no file is uploaded.'],
            'asset.cost' => ['title' => 'Asset cost privacy', 'description' => 'Optional cost impact visibility and finance separation; no amount is calculated or posted.'],
            'asset.damage_approval' => ['title' => 'Asset event approval and SoD', 'description' => 'Assessment, cost, final status, and depreciation approval separation; no approval is requested.'],
            'asset.depreciation' => ['title' => 'Operational depreciation history', 'description' => 'Method, amount, immutable record, and no-general-ledger boundary; no depreciation is posted.'],
            'asset.correction' => ['title' => 'Referenced event correction', 'description' => 'Corrections reference original events and preserve history; no correction is created.'],
            'party.final_readiness' => ['title' => 'Party final-close readiness', 'description' => 'Booking, operation, return, payment, wallet, and close checklist; no close is enabled.'],
            'party.invoice_freeze' => ['title' => 'Final party invoice freeze', 'description' => 'Working invoice freeze, immutability, and referenced correction rules; no invoice is frozen.'],
            'party.payment_reconcile' => ['title' => 'Party payment reconciliation', 'description' => 'Payments on account, evidence, duplicate, residual, and reconciliation rules; no amount is calculated.'],
            'party.credit' => ['title' => 'Party remaining amount and credit', 'description' => 'Remaining, credit, overpayment, and owner credit policy; no balance is calculated.'],
            'party.final_receipt' => ['title' => 'Final party receipt', 'description' => 'Exact wording, numbering, privacy, and reprint rules; no receipt is generated.'],
            'party.final_approval' => ['title' => 'Party final-close approval and SoD', 'description' => 'Readiness review, close approval, and actor separation; no approval is requested.'],
            'party.final_idempotency' => ['title' => 'Party final-close idempotency', 'description' => 'Double-close, retry, concurrency, and correction reference rules; no close is posted.'],
            'party.final_numbering' => ['title' => 'Party final document numbering', 'description' => 'Final invoice/receipt sequence and concurrency rules; no number is allocated.'],
            'party.final_print' => ['title' => 'Party final print boundary', 'description' => 'Final invoice/receipt privacy and print format; no document is rendered.'],
            'quotation.type' => ['title' => 'Quotation activity type', 'description' => 'Typed retail or party activity; mixed lines are blocked and no quote is created.'],
            'quotation.customer' => ['title' => 'Quotation customer linkage', 'description' => 'Customer/source linkage and purpose scope; no customer or quote is created.'],
            'quotation.validity' => ['title' => 'Quotation validity and expiry', 'description' => 'Validity dates, expiry, cancellation, and supersession rules; no expiry is applied.'],
            'quotation.status' => ['title' => 'Quotation status machine', 'description' => 'Draft, issued, expired, cancelled, and superseded transitions; no status changes are enabled.'],
            'quotation.prices' => ['title' => 'Quotation price authority', 'description' => 'Price source, snapshot, approval, and visibility rules; no price is rendered or approved.'],
            'quotation.terms' => ['title' => 'Quotation terms and notes', 'description' => 'Terms, notes, conditions, and owner wording; no proposal is issued.'],
            'quotation.approval' => ['title' => 'Quotation approval and audit', 'description' => 'Approval, separation, reason, idempotency, and audit rules; no approval is requested.'],
            'quotation.numbering' => ['title' => 'Quotation numbering', 'description' => 'Unique sequence and document identity rules; no number is allocated.'],
            'quotation.print_share' => ['title' => 'Quotation print and share', 'description' => 'Read-only print/share boundary and privacy; no output is generated.'],
            'quotation.conversion' => ['title' => 'Quotation future conversion', 'description' => 'Future source reference only; Phase 1 conversion to sale or party invoice is blocked.'],
            'report.source_lineage' => ['title' => 'Report source lineage', 'description' => 'Every report figure requires an approved source and lineage; no metric is calculated.'],
            'report.scope' => ['title' => 'Report scope and authorization', 'description' => 'Branch, store, role, user, and activity scope; no cross-scope result is exposed.'],
            'report.filters' => ['title' => 'Report filters and periods', 'description' => 'Date, comparison, status, and domain filters; no filter result is loaded.'],
            'report.kpi' => ['title' => 'Dashboard KPI catalog', 'description' => 'Sales, cash, inventory, purchasing, customer, party, and asset KPI formulas; no value is rendered.'],
            'report.reconciliation' => ['title' => 'Report reconciliation', 'description' => 'Formula, source snapshot, precision, and reconciliation rules; no report is certified.'],
            'report.alerts' => ['title' => 'Operational alert catalog', 'description' => 'Trigger, severity, owner, scope, deduplication, and state rules; no alert is created.'],
            'report.pagination' => ['title' => 'Report pagination and drilldown', 'description' => 'Bounded detail, indexed search, and drilldown rules; no unbounded result is loaded.'],
            'report.export' => ['title' => 'Report export boundary', 'description' => 'Permissioned bounded PDF/Excel/export rules; no artifact is generated.'],
            'report.precision' => ['title' => 'Report precision and currency', 'description' => 'Explicit precision, currency, snapshot, and no-general-ledger boundary; no amount is shown.'],
            'report.freshness' => ['title' => 'Report freshness and cache', 'description' => 'Freshness, cache scope, and historical snapshot rules; no unrestricted cache is enabled.'],
            'alert.trigger' => ['title' => 'Alert trigger catalog', 'description' => 'Trigger conditions and source eligibility remain pending; no alert is evaluated or created.'],
            'alert.severity' => ['title' => 'Alert severity policy', 'description' => 'Severity and escalation meaning remain pending; no priority is assigned.'],
            'alert.owner' => ['title' => 'Alert owner role', 'description' => 'Owner role and operational responsibility remain pending; no alert is assigned.'],
            'alert.scope' => ['title' => 'Alert scope and authorization', 'description' => 'Branch, store, role, and user scope remain pending; no cross-scope alert is exposed.'],
            'alert.lifecycle' => ['title' => 'Alert acknowledgement and resolution', 'description' => 'Acknowledgement, resolution, dismissal, and reopen states remain pending; no state changes are enabled.'],
            'alert.source_link' => ['title' => 'Alert source link', 'description' => 'Source record, safe navigation, and missing-source behavior remain pending; no link is rendered.'],
            'alert.deduplication' => ['title' => 'Alert suppression and deduplication', 'description' => 'Duplicate, stale, suppression, and retry rules remain pending; no duplicate alert is created.'],
            'alert.notification' => ['title' => 'Alert notification delivery', 'description' => 'In-app, email, and other delivery channels remain pending; no notification is sent.'],
            'alert.navigation' => ['title' => 'Exception queue navigation', 'description' => 'Role-safe list, filters, pagination, and detail navigation remain pending; no queue rows are loaded.'],
        ];
    }
}
