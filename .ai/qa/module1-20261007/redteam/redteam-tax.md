# Tax / financial / numbering second-pass review

Reviewed integrated SHA: 07a514c94d106efe3516d81780ee9081ecfd86bc
Original source baseline: e693ab8c2e86b6e4e8ef628aacef2aa3d8ae987e
Review branch: hotfix/module1-redteam-tax-20261007
Worktree: C:\Users\N\.codex\worktrees\m1-tax\erp

## Result

No confirmed regression or new defect found in the assigned scope. The review worktree remained at the supplied integrated SHA; no application source or test file was changed.

## Tax guard and alternate writers

The integrated validator is confined to tax-setting input. SaveLocalSettingsAction::validatedTaxRate() at app/Modules/Platform/Actions/SaveLocalSettingsAction.php:174 applies required, numeric, 0–100 rules and returns the validated value as a string. PlatformSettingsApprovalAction::request() calls it at line 45 before resolving company scope, building approval source data, or creating an approval. Direct save and approved-effect persistence both pass through persistTaxSetting() at lines 157–188; the latter validates before its write transaction. The approved-effect path is already wrapped in an outer transaction, so validation failure cannot retain the approval transition or audit side effects.

CodeGraph call tracing found two callers of persistTaxSetting(): direct saveTaxSetting() and applyApprovedTaxSetting(). No user-controlled rate write path outside the guarded settings action was found. The UAT seed command is a separate internal fixture writer: UatDataset::seed() inserts a literal rate of 0 and its command accepts only a company ID and dry-run flag, not tax-rate input (app/Modules/Platform/Services/UatDataset.php:62; app/Console/Commands/SeedUatDataset.php:11). Company attribution remains bounded by AuthorizedCompanyContext in request and persistence. The POS cart/sale paths read the effective company default; no calculator or purchase tax code was modified. Explicit zero and malformed-rate side-effect checks passed again on the integrated branch.

## Arithmetic, decimal boundaries, and snapshots

The integration diff contains no changes to PosCalculationService, RetailSaleAction, Sale, PurchaseInvoiceCalculator, PurchaseOrderCalculator, SavePurchaseInvoiceAction, PurchaseInvoiceLine, or PurchaseOrderLine. Existing calculator checks passed:

- FinancialContractTest: 11 tests, 47 assertions. Includes 14.1256 purchase tax/cost snapshots, 0% and 100% boundaries, POS inclusive/exclusive tax, and exact purchase document totals.
- FinancialBoundaryTest: 10 tests, 1,127 assertions. Includes four-place purchase tax precision, zero/large amounts, and POS allocation/cash boundaries.
- FinancialMatrixTest: 3 tests, 2,845 assertions.
- FinancialPrecisionClosureTest: 3 tests, 57 assertions, including half-cent POS residues and four-place purchase snapshots.

RetailSaleAction still writes tax_setting_id, tax_rate_snapshot, tax_treatment_snapshot, and tax_inclusive_snapshot when creating a sale and when confirming a suspended sale (lines 190–193 and 333–336). resolveTax() continues to select the effective default within the sale company (line 771). The approved policy in docs/48-pos-financial-calculation-policy.md §5 requires invoice snapshots and unchanged numbering. The sale detail and print views render tax_rate_snapshot and the stored tax_total from the Sale (resources/views/pages/sales/show.blade.php:60 and print.blade.php:19); they do not look up current TaxSetting values to display the old total. Those source paths are unchanged in the reviewed diff. No existing test asserts the tax_*_snapshot fields or reprints a historical sale after a settings edit, so historical reprint behavior was source-reviewed, not dynamically exercised in this pass.

## Numbering invariants

DocumentSequenceConcurrencyTest passed 2 tests / 17 assertions against the named MariaDB schema. Its real two-worker lock-contention test observed unique sequential numbers, counter value 3 after two allocations, and two allocation audit rows. Its metadata-edit test confirmed the allocated counter remains at 2 after editing the prefix and rejects a direct counter rewind. SaveLocalSettingsAction::persistDocumentSequence() locks the row and removes next_value from updates; DocumentSequence only permits next_value/lock_version changes through advanceCounter().

The targeted branch/global sequence override audit test passed 1 test / 2 assertions. OverrideDocumentSequenceCounter::persist() locks the row, checks lock_version and no-op changes, advances the counter, and records the audit in its transaction. The existing test covers branch/global audit scope; no existing focused test exercises a stale or approved override rollback end-to-end, so those paths were reviewed statically and not claimed as dynamically verified.

## Exact verification

All PHPUnit runs used the shared lead-env.ps1 helper with QaDatabase qa_rajh_m1_tax_20261007 and LOG_LEVEL=emergency:

- php vendor/bin/phpunit tests/Feature/QA/FinancialContractTest.php — PASS, 11 / 47.
- php vendor/bin/phpunit tests/Feature/QA/FinancialBoundaryTest.php — PASS, 10 / 1,127.
- php vendor/bin/phpunit tests/Feature/QA/FinancialMatrixTest.php — PASS, 3 / 2,845.
- php vendor/bin/phpunit tests/Feature/QA/FinancialPrecisionClosureTest.php — PASS, 3 / 57.
- php vendor/bin/phpunit tests/Feature/QA/DocumentSequenceConcurrencyTest.php — PASS, 2 / 17.
- php vendor/bin/phpunit tests/Feature/Module1TaxRateValidationTest.php — PASS, 9 / 108.
- php vendor/bin/phpunit --filter test_branch_sequence_override_event_keeps_the_sequence_branch_scope tests/Feature/Module1AuditPresentationTest.php — PASS, 1 / 2.

A first manual migrate:status attempt failed before DB access because Laravel prefixed the drive-qualified cache path; no migration ran. Retrying with the shared helper, Artisan migrate:status on the exact tax QA schema showed only the integrated cash-drawer lock_version migration pending. After inspecting that migration’s additive up() operation, php artisan migrate --force --no-interaction applied only 2026_10_07_023742_add_lock_version_to_cash_drawers_table. A read-only post-test query returned zero rows in companies, tax_settings, approval_records, audit_logs, branches, document_sequences, and cash_drawers. CodeGraph status for the exact worktree was up to date.

## Declined / remaining limits

No full suite or unrelated regression classes were run. No database reset/drop or migration rollback was run. No physical printer, browser, production environment, or release workflow was used. No new business rule or speculative test was added. Existing dynamic coverage does not prove a historical receipt reprint after changing a tax setting, nor stale/approved sequence-override rollback; these are recorded as coverage limits, not confirmed defects.




