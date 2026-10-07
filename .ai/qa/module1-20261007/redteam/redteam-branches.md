# Module 1 branch/POS scope red-team

Reviewed integrated SHA `07a514c94d106efe3516d81780ee9081ecfd86bc` in the isolated `hotfix/module1-redteam-branches-20261007` worktree. Focused on scope changes racing with `OpenShiftAction`; also checked the existing branch → store → drawer lock/revalidation path and the assigned POS shift-open route cases.

## Confirmed finding — critical scope-revocation race

`OpenShiftAction::attempt()` first checked `CashDrawer::visibleTo()` with an ordinary read, then could wait for the branch row lock. Its later ordinary `visibleTo()` query could reuse the transaction's existing repeatable-read snapshot. If the cashier's active store scope was revoked during that wait, the action could still create a shift, active assignment, and `open_shift` audit entry after access had been removed.

The isolated two-process reproducer blocks the opener on the branch row after its initial scope query, deletes that cashier's store-scope row, commits, and lets the opener continue. Before the fix it failed as expected: 1 test failed / 3 assertions; the worker returned `opened` instead of `rejected`.

The minimal correction in `app/Modules/Retail/Actions/OpenShiftAction.php` acquires a shared lock on the active cashier row before branch/store/drawer locks, matching `SaveUserAuthorizationAction`'s user-before-scope update order. After the master rows are locked and their relationships revalidated, the opener performs current shared-lock reads of active branch and store scope rows, preserving the existing global-access and branch-OR-store authorization semantics. The regression now passes with HTTP 403 and no persisted shift. This serializes with the standard authorization action and also proves the final check is a current read rather than a stale snapshot read.

## Checks and limits

- `OpenShiftScopeRevocationConcurrencyTest.php`: 1 test / 5 assertions passed after the fix. It had first produced the recorded RED above.
- `PosShiftOpenRouteTest.php`: 5 tests / 48 assertions passed.
- PHP syntax checks and Pint passed. `git diff --check` passed.
- The exact worktree CodeGraph 1.6.0 index had no path mismatch; after the focused edit, exact-root sync completed and status reported zero pending changes.
- Review found no additional concrete deadlock or shift/drawer relationship failure in the bounded paths. No broader multi-process stress run, browser check, full suite, production access, push, or deployment was performed.

## Declined or unresolved behavior

- No cascade deletion was introduced, and no scope grants, drawer assignment rules, idempotency behavior, or global/branch/store distinctions were changed.
- M1-EX04—whether a selling store may be retyped while a shift is already open—remains an owner policy decision; this change does not add a prohibition.
- The `assigned_user_id` default/preferred/exclusive interpretation remains ambiguous and was not changed.
