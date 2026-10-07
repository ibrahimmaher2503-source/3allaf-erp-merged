# Module 1 auth-domain red-team review

Reviewed integrated SHA `07a514c94d106efe3516d81780ee9081ecfd86bc` in isolated worktree `C:\Users\N\.codex\worktrees\m1-auth\erp`, branch `hotfix/module1-redteam-auth-20261007`. Review scope was cash-drawer version/scope/mass-assignment and status/delete paths, locked edit baseline, dirty form preservation, adjacent callers, and the additive lock-version migration. Local-only review; production was not inspected.

## Confirmed finding and correction

`SaveCashDrawerAction::toggleStatus()` accepted `active` without applying the active-selling-store invariant already enforced by `execute()`. A supported sequence (deactivate a drawer, deactivate its linked selling store through `SaveStoreAction::toggleStatus()`, then reactivate the drawer using its current lock version) reproduced the defect: the drawer became active while its store was inactive. This was a pre-existing invariant gap in the integrated SHA, not caused by the reviewed lock-version changes. POS shift opening independently revalidates the linked store and remained blocked, so this reproduction did not open a financial shift.

The focused test was observed RED before the fix (1 failed / 2 assertions; expected inactive drawer, got active). The minimal correction now applies the same invariant during reactivation, with branch → store → drawer locks, scope and relationship rechecks, and the existing localized validation messages. The test then passed and confirmed the drawer status/version remain unchanged on rejection and activation succeeds after the store is restored.

Also corrected the focused fixture cleanup: it now removes only `ApprovalRecord`-source audit rows belonging to that fixture before deleting its approval records. A post-test QA query returned zero such orphan audit rows; unrelated audit records are untouched.

## Checks

- Guarded focused check on disposable loopback MariaDB schema `qa_rajh_m1_auth_20261007`: `php vendor\bin\phpunit tests\Feature\CashDrawerConcurrencyTest.php` — **9 tests, 63 assertions passed**.
- `php -l` passed for `SaveCashDrawerAction.php` and `CashDrawerConcurrencyTest.php`.
- Pint passed on both changed PHP files; `git diff --check` passed.
- Exact-worktree CodeGraph sync/status and focused callers/impact checks passed.

The focused set also exercises stale-version rejection, scope-constrained save/delete behavior, locked edit baseline, and preservation of dirty values. Static review found explicit action attributes, server-derived company scope, and scoped/locked delete reloads; no additional confirmed mass-assignment, forged-scope, stale-version, or caller-compatibility defect was established. The additive migration was present in the named QA schema; no destructive migration was run.

## Limits and declined behaviors

No full suite, browser interaction, production access, push, deployment, or merge was performed. User assignment policy (`assigned_user_id`) was not expanded into this bounded drawer version/status review. Store-type changes while a shift is active and default-cashier exclusivity remain outside this review; neither was changed. These are scope limits, not confirmed defects.
