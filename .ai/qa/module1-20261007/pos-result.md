# Module 1 POS QA Result

## Scope and classification

- Change class: D3 (POS/shift authorization and financial-state integrity).
- Worktree: `C:\Users\N\.codex\worktrees\m1-pos\erp`, branch `hotfix/m1-pos-cashier`, source baseline `a01af760a2102dae4993aaa47a47e7c6847a11f3`.
- Production was not accessed. No push, deployment, remote QA write, existing grant change, or shift auto-close occurred.

## Finding results

### BUG-227 — Cashier versus Cashir shift-opening capability — NOT REPRODUCED for the current canonical seeded role

The PDF page 15 reports a remote role-table mismatch and a 403 for Cashier while a misspelled `Cashir` role could open a shift. In current source, `ProductionSeeder` defines the canonical `cashier` role with `shifts_cash_movements.view` and `shifts_cash_movements.create`; this test assigns only that role. Its compatibility GET redirects to `/pos/shift`, the shift screen returns 200, and direct POST opens the shift with the expected branch/store/drawer snapshots, active assignment, and `open_shift` audit. No `Cashir` role or grant was added, no existing records were merged, and permissions were not broadened.

This does not establish what grants currently exist on the remote QA site or explain the PDF's historical role rows. The owner should inspect that site's role configuration separately if the reported 403 persists.

### BUG-226 — Disabled user with an open shift — authentication handoff; financial disposition remains unresolved

The PDF page 14 shows the next request returning 403 while the cashier's shift remains open. Authentication/session revocation belongs to the auth investigator. `docs/32` defines normal blind-close/review states but no emergency disabled-cashier disposition. Preserve the open financial state for authorized manual recovery; do not silently close, cancel, or rewrite the shift. No shift-close path was added here.

### M1-F22 — Remote QA artifacts and cleanup notes — no remote cleanup performed

PDF page 20 records QA branch/store/drawer, user/role, pending branch-delete and tax-approval artifacts, and shift/numbering evidence. These records are remote QA evidence and were not accessed or deleted. The owner must review the pending approval/history and use the normal authorized cleanup path; no cleanup is inferred from the PDF.

### M1-EX01 — Default assigned cashier exclusivity — AMBIGUOUS REQUIREMENT

`docs/04` describes the cashier's assigned branch/store/drawer scope; `docs/32` says the cashier needs assignment. The drawer form and saved model also support a nullable default cashier and show “Unassigned / Any Cashier.” These sources do not establish whether `assigned_user_id` is exclusive or only a preference. No restriction was added. Active shift exclusivity remains enforced for one active shift per cashier and per drawer.

### M1-EX02 — Direct shift POST after outlet becomes inactive/non-selling — FIXED AND VERIFIED

Before the fix, direct POST `/pos/shift/store` opened a shift using an active drawer whose store had been retyped from `selling` to `warehouse`; a second reproduction also opened after the store was deactivated. The shift-page query filtered active selling stores, but `OpenShiftAction` checked only company/branch/store ownership and never revalidated the store's current status/type.

`OpenShiftAction` now locks and validates the current branch, store, and drawer in Branch → Store → Drawer order, rechecks the drawer's current branch/store IDs after locking, and routes an inactive or non-selling store through the existing invalid-drawer validation before any shift, assignment, or audit insert. No permission or default-cashier policy changed.

The verified lock order matches `SaveStoreAction`'s Branch → Store updates. `SaveCashDrawerAction` reads branch/store before locking the drawer; the opener's post-lock relationship revalidation rejects a changed drawer relationship. No two-process contention stress test was run. **M1-EX04 — AMBIGUOUS REQUIREMENT / lifecycle follow-up:** `SaveStoreAction` blocks inactive transition with active POS shifts but permits a selling-to-warehouse type edit while a shift is open. `docs/32` prohibits drawer reassignment/deactivation with an active shift but does not explicitly classify a store-type edit. This change validates the store at shift-open time only; do not infer a new lifecycle restriction without owner approval.

## Changed files

- `app/Modules/Retail/Actions/OpenShiftAction.php` — validate active selling store under stable lock order and reject stale drawer/store configuration.
- `tests/Feature/PosShiftOpenRouteTest.php` — five focused HTTP/action scenarios for canonical cashier success, cross-store/branch denial, active assignment exclusivity, and inactive/non-selling outlet rejection.

## Verification

- Test-first RED before the fix: focused run had 3 passing tests / 37 assertions and 1 failure. The warehouse-store POST redirected to `/pos` (shift opened) rather than the expected `/pos/shift` validation return. After adding the inactive-store case, that case independently reproduced the same behavior (0/1 tests, 3 assertions before the failure).
- Final GREEN: `php vendor/bin/phpunit tests/Feature/PosShiftOpenRouteTest.php` — **5 tests, 48 assertions passed** against only `qa_rajh_m1_pos_20261007` on XAMPP MariaDB `127.0.0.1:3306`.
- The disposable schema was migrated under explicit `APP_ENV=local` safe settings; all 119 migrations completed. Focused tests used explicit `APP_ENV=testing`, the same schema, array cache/session/mail, sync queue, generic test key, and schema-specific temporary storage. Tests use `DatabaseTransactions` after the first lazy-refresh attempt hit Laravel's missing `translation_overrides` table during test-mode migration boot; that bootstrap failure was setup-only and had zero assertions, not an application RED.
- Changed PHP files: `php -l` passed for the action and test. Pint ran on those explicit files with the regression worktree's installed binary/config fallback (exit 0). `git diff --check` passed.
- CodeGraph was synchronized for this exact worktree after status showed five changed files, then `query`, `callers`, and `impact` for `OpenShiftAction` completed. Callers include the retail route, QA concurrency worker, reconciliation script, and two seeders.
- No full suite or browser check was run by this agent; the lead owns final integration and project-level verification.

## Follow-up and remaining risk

- Auth investigator/lead to report session invalidation for BUG-226 and the owner to decide emergency recovery of any disabled cashier's open shift.
- Owner to resolve M1-EX01 (exclusive master assignment versus preferred/default assignment) before narrowing access.
- Owner to resolve M1-EX04, whether a store-type edit should be blocked while a shift is open.
- Owner to review remote QA artifacts in M1-F22 manually. No remote records were touched.
