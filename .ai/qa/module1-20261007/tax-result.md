# Module 1 tax/settings validation result

Date: 2026-10-07

Scope: BUG-223 tax-rate validation, plus assigned findings M1-F08, M1-F15, M1-F16, and M1-F17.
Worktree: `C:\Users\N\.codex\worktrees\m1-tax\erp` (`hotfix/module1-tax-validation`), source `a01af760a2102dae4993aaa47a47e7c6847a11f3`.

## BUG-223

**Result: fixed in this worktree.** The settings form converted an empty string to `null` and used `nullable|numeric|min:0|max:100`. The direct approval request and tax persistence action had no required numeric-range validation, so malformed rates could get as far as approval or be coerced while applying an approved rule.

`SaveLocalSettingsAction::validatedTaxRate()` now validates a present numeric rate from 0 through 100 and returns its string form. The Livewire form uses `required|numeric|min:0|max:100`; `PlatformSettingsApprovalAction::request()` validates before creating an approval; and `persistTaxSetting()` validates before transaction writes or default changes. Invalid approved payloads raise a validation exception inside the surrounding approval transaction, rolling back the approval transition and its audit. Explicit `0` remains valid for standard, zero-rated, and exempt treatments. Decimal persistence/casting, effective-date logic, history, and tax calculations were not changed.

The focused behavioral test was first run before the production changes and failed with `An invalid tax rate was accepted into approval.` It then passed after the repair. It covers the blank Livewire form, missing/null/blank/non-numeric/-5/150 direct approval requests, the same direct persistence inputs with assertions that tax/default/approval/audit counts do not change, standard/exempt zero persistence, zero-rated approval, and rollback of a corrupt pending approval.

Additional bounded settings coverage exercises the existing company and payment workflows: authorized company edits through Livewire succeed; a user without edit permission is denied without changing company/audit state; a blank required currency is rejected without a write; a valid cash method saves and appears in the rendered payment list; a user without edit permission is denied; and an invalid offline-card policy creates no payment row or audit.

Verification on the disposable XAMPP MariaDB schema `qa_rajh_m1_tax_20261007`: `9 tests, 108 assertions` passed. PHP syntax checks and Pint passed for changed PHP files; `git diff --check` passed. The project CodeGraph index was incrementally synchronized after the affected source edits. No full suite, browser interaction, production write, physical print, push, or deployment was performed.

## Assigned coverage findings

- **M1-F08 — AMBIGUOUS REQUIREMENT.** `SaveLocalSettingsAction::savePrinterConfiguration()` permits a null template, but the requirements do not establish whether every printer requires a template. No behavior change made; keep the decision open.
- **M1-F15 — NOT REPRODUCED in authorized local coverage.** Authorized company edit succeeded; blank currency validation and missing-edit-permission denial caused no write/audit. The finding has no expected-behavior definition, so a role-specific read-only policy remains undecided.
- **M1-F16 — VERIFIED in focused local coverage.** Valid cash method was saved and displayed; missing permission and an invalid offline-card configuration were rejected without writes/audits.
- **M1-F17 — NOT TESTED / BLOCKED.** No physical printer was used. Physical printing remains outside this safe QA run.

## Changed files

- `resources/views/platform/admin/settings.blade.php`
- `app/Modules/Platform/Actions/PlatformSettingsApprovalAction.php`
- `app/Modules/Platform/Actions/SaveLocalSettingsAction.php`
- `tests/Feature/Module1TaxRateValidationTest.php`

The guarded QA harness was cherry-picked as a prerequisite on this branch. The shared findings matrix and `.ai/CURRENT_TASK.md` were not modified.
