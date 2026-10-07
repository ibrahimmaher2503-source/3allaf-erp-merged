# POS red-team review — 2026-10-07

## Reviewed revision and scope

- Integrated baseline reviewed: `07a514c94d106efe3516d81780ee9081ecfd86bc`.
- Review branch: `hotfix/module1-redteam-pos-20261007` in the existing POS worktree.
- Focus: event/source/scope correctness for numbering, inventory draft/approval, branch and drawer lifecycle; audit viewer/export scope, fingerprints/redaction, append-only behavior, and audit events on rejected writes.
- Test database: only the previously named disposable XAMPP MariaDB schema `qa_rajh_m1_pos_20261007`; process helper supplied explicit loopback and schema-specific temporary paths. It contains 114 applied migration rows, matching the 114 migration files in this checkout. No reset or recreation occurred in this pass.

## Confirmed finding and repair

**Inventory approval audit scope was incomplete.** A real inventory adjustment was drafted, submitted, and approved through `SaveInventoryAdjustmentAction` and `SubmitInventoryAdjustmentAction`. The persisted `approve_inventory_adjustment` event had the correct `store_id` but a null `branch_id`. `AuditLog::visibleTo()` filters on the event’s explicit branch/store fields; a branch-scoped viewer therefore could not see this approval event, although a store-scoped viewer could.

The focused behavior test first failed on the branch viewer query (`false`), confirming the access gap through real records and actions. Minimal repair in `app/Modules/Inventory/Actions/ApproveInventoryAdjustmentAction.php` eager-loads the adjustment’s `store` and records its owning `branch_id` on the approval event. The same event continues to carry `store_id`. After repair, the focused test passed and asserted persisted branch/store IDs plus visibility to both branch- and store-scoped viewers.

## Checks and evidence

The combined focused command used the guarded `lead-env.ps1 -QaDatabase qa_rajh_m1_pos_20261007` environment and `LOG_LEVEL=emergency`:

```text
php vendor/bin/phpunit --configuration phpunit.xml.dist \
  tests/Feature/QA/ApproveInventoryAdjustmentActionTest.php \
  tests/Feature/Module1AuditPresentationTest.php \
  tests/Feature/Module1AuthorizationBoundaryTest.php \
  tests/Feature/BranchLifecycleDependencyTest.php \
  tests/Feature/CashDrawerConcurrencyTest.php \
  tests/Feature/Platform/ApprovalInboxPresentationTest.php \
  tests/Feature/QA/DocumentSequenceConcurrencyTest.php

PASS — 34 tests, 213 assertions
```

Observed coverage and inspection:

- Real numbering allocation and branch-sequence override retain branch scope; company-wide override remains Global. Number-allocation concurrency behavior was included in the focused numbering test.
- Inventory draft creation records both branch and store; branch/store scoped readers see it, while another branch’s event is excluded.
- Inventory approval initially omitted branch scope; fixed and verified as described above.
- Branch lifecycle events supply branch scope; drawer create/update/status/delete paths supply their branch and store. Focused branch/drawer tests passed, including stale drawer writes leaving business state and audit counts unchanged. Existing approval presentation tests passed.
- The emitted device and session values are SHA-256 hashes. The authorized detail view presents only 12-character hash prefixes; the rendered test excludes raw device/session identifiers, full hashes, and raw user-agent text.
- Audit export applies `AuditLog::visibleTo()` before filters, enforces the configured row limit, and redacts before/after values for the viewer. The focused scoped-export test used an actual CSV response and confirmed an assigned-store event was included while an out-of-scope store event was omitted. The unconfigured-limit path remains blocked and does not write `audit_log_exported`.
- `AuditLog` model instance update/delete hooks throw to preserve append-only history. No application-level bulk update/delete of audit rows was found in the bounded source search. Drawer stale-write and blocked-export checks cover no-event failure paths; no separate failed inventory-draft event test was added.
- PHP syntax checks passed for the changed action and reproducer. Explicit-file Pint, `git diff --check`, and exact-root CodeGraph sync/status passed.

## Considered and set aside

- Audit export limit: M1-F07 remains ambiguous because no approved positive cap was found. Safe blocked behavior is retained; no limit was invented.
- Default cashier exclusive assignment and `Cashir` legacy-role repair: these are separate authorization/policy decisions. No grants or historical roles were changed.
- M1-EX04 (whether store type changes must be blocked during an active POS shift) and orphaned financial-shift recovery: lifecycle/owner policy, outside this audit repair; no shift was closed or recovered.
- Raw database mutation/tamper resistance: Eloquent instance guards were inspected, but DB-level triggers/permissions and privileged direct SQL are outside this application-code review. Test fixture cleanup uses query-builder deletes and is not evidence of a user-facing mutation path.
- User-agent retention: the event writer still stores a truncated raw user-agent string in metadata. The reviewed UI does not render metadata and CSV export omits it; this pass did not establish an at-rest retention/privacy requirement.
- Scoped HTTP export route behavior: the existing authorization report separately records the route/action policy discrepancy; this review verified the scoped action’s CSV data and did not change route policy.

No full suite, browser, production access, remote QA access/cleanup, push, or deployment was performed. No application changes beyond the single audit-scope repair and its focused regression test were made.
