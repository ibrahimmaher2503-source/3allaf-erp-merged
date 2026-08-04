# 14 — Manual Verification and User Acceptance Testing Plan

## TSK-004B Manual Matrix

Verify persistent controls, RTL/LTR, mobile launcher, keyboard/focus, registered and missing guides, full guide/flow access, admin/view-only/scoped/no-access filtering, tour missing-target handling, Livewire re-render behavior, preference persistence/reset, reduced motion, and no sensitive payload leakage. Evidence belongs under `artifacts/platform-dashboard-assistant/`.

## Binding Policy

Automated tests are deferred. No automated test code shall be created. No automated test suite shall be run. This can change only through a new explicit project-owner instruction.

Current status: Automated Tests Not Created; Automated Tests Not Run; Manual Verification Not Started; UAT Not Started.

## Approach

Verification is risk-based and scenario-led. Each future task validates its critical happy path plus authorization, validation, duplicate/concurrency, data/stock/financial effects, failure recovery, printing, responsive behavior, RTL/LTR, and regressions in directly affected flows. Phase Gates execute the end-to-end acceptance scenarios across real role/scopes and approved devices. Results must describe what actually ran; no assumed pass is allowed.

## Environment and Evidence

Before a run, record build/commit, environment, database/data snapshot, browser/device/printer/scanner, locale/direction, user/role/branch/store/drawer/shift, scenario/AC IDs, and prerequisites. Evidence may include timestamped screenshots, printed/PDF/Excel artifacts, approved query/report extracts, audit/source IDs, and defect links. Protect customer/payment/evidence data and restrict the evidence repository.

Each result records: Run ID, date/time/timezone, verifier, environment/build, role/scope, scenario IDs, steps, expected result, actual result, Pass/Fail/Blocked, source document IDs, evidence references, defect, retest, and approver.

## Browser and UI Verification

- Load default, loading/submitting, empty, error/retry, success, disabled, permission-denied, validation, confirmation, and unsaved-change states where relevant.
- Verify keyboard order, focus visibility, labels, error association, touch targets, contrast/readability, no color-only meaning, and reduced-motion behavior.
- Verify approved desktop/tablet/POS widths, zoom/text growth where policy requires, long Arabic/English strings, tables/forms/dialogs/drawers, and no clipped essential action.
- Verify server pagination, filters, sorting, bounded search, clear row actions, deep links/back/refresh, duplicate-submit prevention, and recoverable input/cart preservation.

## Role and Permission Verification

For each role and representative branch/store scope: sign in; open navigation/direct URLs; attempt View/Create/Edit/Logical Delete/Print/Approve/Export/Reverse/Cancel/Override; test own-record and cross-scope access; test sensitive fields and downloads. Specifically verify Cashier cannot access Party Wallet or pre-submit expected close; Party Manager cannot access Product Wallet; Stock Counter cannot approve reconciliation; Reviewer cannot make unauthorized operational edits.

## Data and Integrity Verification

- Unique item code/barcode/customer phone/document/Gift Card and active-price constraints.
- Required bilingual fields, product attributes without variants, actual/preferred supplier history, and approved-document immutability.
- State maps, source/reversal references, idempotent retry, duplicate submission, and concurrency conflict recovery.
- Audit actor/time/session/device/scope/source/reason/before-after and immutability.
- High-volume query scope/pagination and report-source reconciliation.

## Stock Integrity Verification

Reconcile opening/reference values plus approved movements to on-hand/available/in-transit/reserved. Verify purchase receipt/weighted average cost without price change, supplier return, transfer dispatch/partial receipt/difference/damage/refusal, entry/exit/adjustment, negative/fractional rules, count reference plus subsequent sales/movements, repeated counts, Manager reconciliation, and uncounted review without zero.

## Financial and Settlement Verification

Recalculate line original price, one discount, net, tax, total, payments, refund/exchange difference, Gift Card, shift actual/expected/variance, loyalty points, each wallet ledger, party payments/final balance/credit. Test rounding at boundaries, concurrency, duplicate sources, expiration, correction references, and strict Product/Party Wallet separation. This is operational reconciliation, not general-ledger testing.

## Print, PDF, Excel, Barcode, and Attachment Verification

- Thermal/A4 sales invoices, Gift Receipt without prices, shift thermal/A4 close, labels by location/quantity/price, party payment receipt exact wording, final party receipt, quotation, PDF reports.
- Page/paper/label size, Arabic RTL/English LTR, identifiers/status/totals/page breaks, reprint marking/audit, and no source mutation.
- Excel import mapping/modes/valid/invalid/duplicate/formula/large-bounded file and downloadable error report; export filters/values/formula-injection protection/permissions/expiry.
- Barcode scan/lookup/format/uniqueness and scanner focus/keyboard behavior.
- Attachment safe/unsafe types, size/signature, image preview, protected URL/access, replacement/correction, retention, and cross-scope denial.

## POS and Shift Verification

Use approved cashier hardware/connectivity. Verify scan/name/code, rapid input, quantity permission, assigned-store-only sale, other-branch informational search, unpriced/negative/open-price/discount controls, customer, suspend/retrieve, cash/manual electronic evidence, tax, exact totals, double-submit, receipt print, active shift/drawer linkage, cash movement, blind close without expected values, variance review, and report reconciliation.

## Party and Asset Verification

Verify separate stores/lines/wallets; booking/customer/child/schedule/location/responsibilities; working invoice edits/freeze; multiple payments and exact individual receipt label; operating order changes; consumable issue/return; asset time overlap/concurrency; checkout/return/pre-post condition; all required statuses; damage/loss/depreciation/cost approval; unresolved-operation close block; final invoice/payment/Party Wallet/credit reconciliation and receipt.

## Offline POS and Sync Verification

Only after owner policy/device approval: enroll device, authenticate, lose/recover network, display connectivity and limits, queue only cash/manual electronic eligible sales, block credit/wallet/loyalty redemption/special discount/unpriced/stale/conflict-prone actions, protect/expire/clear local data, retry without duplication, handle app/schema/service-worker update, reauthenticate on sync, accept/reject/conflict based on server truth, review every conflict, and create referenced corrections. Test device loss, logout, session revocation, clock skew, duplicate batch, damaged payload, full local storage, and interrupted sync.

## Backup and Restore Verification

Under approved runbook: create monitored encrypted backup including database and required attachments/config; confirm retention/off-host destination; restore into isolated authorized environment; verify integrity counts/source links/files/secrets separation; record elapsed time against RTO and data point against RPO; document cleanup. Never claim restore verified from backup creation alone.

## UAT Ownership and Scenarios

Named client owners are required for Administration, Purchasing, Warehouse, Pricing, POS/Cash, Customer/Returns, Party/Assets, Finance/Reporting, Security/IT, and final business sign-off. UAT covers every source acceptance scenario, 72 Requirement IDs, DM Delivery Criteria, role/scope matrix, critical end-to-end journeys, and production-like prints/devices/data volumes.

## Defect Severity

| Severity | Definition | Gate rule |
|---|---|---|
| Critical | Security/permission leak, corrupt/duplicate financial or stock effect, broken separation/immutability, unrecoverable loss, unusable critical flow. | Blocks task/DM/phase/release; fix and full affected retest required. |
| High | Major requirement or role workflow unavailable/incorrect with no safe practical workaround. | Blocks Phase Gate unless owner explicitly defers with risk and target. |
| Medium | Incorrect secondary behavior or usable workaround with controlled risk. | Owner/target required; retest before closure. |
| Low | Cosmetic/copy/minor usability issue with no integrity/security impact. | May be accepted into known limitations with owner/target. |

## Retest and Regression

Reproduce the defect, record source/evidence, implement only under approved task/change, retest the original scenario, then manually verify directly affected neighboring permissions/states/calculations/prints/RTL/LTR. Preserve prior failure and new pass evidence. Reopening requires reason and owner.

## Sign-off

A task is accepted by its responsible reviewer under `docs/13-definition-of-done.md`. A DM/Phase Gate requires completed evidence, reconciliations, critical-defect closure, open-item ownership, approved inputs, role/audit verification, updated handoff/status/traceability, and written client approval. DM 6.4 additionally requires production data/config/printer/user/backup approval, training attendance, operational/support handover, and final sign-off.
