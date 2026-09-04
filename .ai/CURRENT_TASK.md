# Current Task — v0.1.22-hotfix13 Cash Drawer Context Integrity

**Date:** 2026-09-05
**Change class:** D3
**Source baseline:** exact Hotfix12 commit `287693218aded875749b643e9427f63318088b05` (tree `bb4e615935a246b85aa5437346d6a86bb4f10b14`)
**Branch:** `hotfix/v0.1.22-hotfix13-cash-drawer-integrity`
**Status:** The cash-drawer context defect is corrected in source and focused database-free verification is complete; immutable release preparation follows the release commit.

The exact insertion path was `App\Modules\Platform\Services\UatDataset`: its raw insert created `TEST-REG-0001` with branch/store ownership but omitted `cash_drawers.company_id`, bypassing `SaveCashDrawerAction`. Hotfix13 derives company ownership from the selected store, rejects mismatched company/branch/store contexts before Eloquent saves, corrects all known seeding paths, excludes inconsistent drawers from shift selection, and returns a localized safe validation message rather than a company/branch/store `ModelNotFound` 404.

Migration `2026_09_04_000107_enforce_cash_drawer_context_integrity` audits drawers deterministically by ID. It backfills only when an existing store, its branch, and their company resolve to one consistent owner. Any missing or contradictory relationship raises an exception listing drawer ID, code, and reason before any drawer is changed. After a clean audit it makes `company_id` non-null, retains a restrictive company foreign key, and adds `company_id > 0` enforcement.

Karim/company-owner permissions, permission-search presentation, routes, OpenShiftAction accounting/idempotency/duplicate-shift rules, and the pending V0 UI batch are unchanged. No database, production path, active symlink, service, tag, push, or remote was accessed or mutated.
