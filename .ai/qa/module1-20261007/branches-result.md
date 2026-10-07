# Branch/store lifecycle QA result

## Execution boundary

- Source baseline: `a01af760a2102dae4993aaa47a47e7c6847a11f3`.
- Disposable MySQL schema for this investigator: `qa_rajh_m1_branches_20261007`.
- The schema is isolated to XAMPP MariaDB on loopback. This name is recorded before any migration/reset command; no application database or `.env` file is used.
- Production baseline is recorded Hotfix51 only. No production access, merge, push, or deployment is authorized.

## Findings

- **BUG-224 — reproduced and fixed in this worktree.** Before repair, the focused action regression failed because the active-store/mapping guard threw `InvalidArgumentException` instead of a user-visible `ValidationException`; the branch component sent the exception through `UserSafeError::message()`, which hid the dependency reason. Branch-delete requests previously carried only `deleted=true`. The eventual logical-delete path already rechecked active stores and mappings and set the branch inactive without cascading.
- The branch edit and toggle paths now lock the branch first, then use locking reads for active stores and active selling-store mappings. Those existing dependencies remain the only status/deletion blockers. The action raises the existing translated dependency message as a validation error, and branch error toasts display that message.
- Branch-delete approval requests now record request-time integer counts for linked stores, active stores, active selling-store mappings, active cash drawers, and active scope assignments. Active scope assignments are active `user_branch_scopes` rows for the branch plus active `user_store_scopes` rows for its stores; this is a row/assignment count, not distinct users. Approval locks the branch and rechecks only active stores/mappings. A failed recheck leaves approval pending and branch/store/drawer/scope records intact; no cascade or new drawer/scope blocker was added.
- **M1-F02 — NOT REPRODUCED; existing safety behavior.** `docs/25-inventory-exception-policy.md` classifies “Negative stock blocked by default” as a PRD requirement and states that rule in §3. The store UI pairs “Blocked (Safe)” with “Allowed (Warning)” when the setting is enabled. The page-18 report asks what the label means but identifies no conflicting expected behavior; no product-policy ambiguity or label change remains.
- **M1-F03 — ambiguous requirement remains.** The branch Warehouses count uses active stores with `type=warehouse`; sales outlets are excluded by the current relationship. Store/Warehouse taxonomy remains unresolved, so the count was not broadened.

## Request-time branch dependency context

The branch-delete approval request carries integer counts for all linked stores, active stores, active selling-store mappings, active cash drawers, and active scope assignments. Active scope assignments count active `user_branch_scopes` rows for the branch plus active `user_store_scopes` rows for stores in the branch; this counts assignments, not distinct users. Only active stores and active selling-store mappings remain lifecycle blockers. Drawer and scope counts are explanatory request-time context, not new blockers or cleanup rules.

## RED/GREEN evidence

- RED (before the production fix): `php vendor/bin/phpunit tests/Feature/BranchLifecycleDependencyTest.php --filter='test_active_store_and_selling_mapping_block_branch_deactivation_with_a_specific_message'` failed with **1 failed test, 5 assertions** because `InvalidArgumentException` was not the expected `ValidationException`.
- GREEN (after the fix): `php vendor/bin/phpunit tests/Feature/BranchLifecycleDependencyTest.php` passed **5 tests, 25 assertions** on `qa_rajh_m1_branches_20261007`. Coverage isolates an active store and an active selling mapping, checks status-edit and toggle paths, checks the branch toast text, confirms user scopes alone do not add a blocker, records dependency context, and proves approval rejection preserves pending/active source records.
- The local Pint launcher contains an embedded path to the regression worktree and fails when started from this worktree. Pint was therefore run successfully from the regression worktree launcher against only the four changed PHP/Blade/test paths: `C:\Users\N\.codex\worktrees\m1-regression\erp\vendor\bin\pint --format agent app/Modules/Platform/Actions/PlatformSettingsApprovalAction.php app/Modules/Platform/Actions/SaveBranchAction.php resources/views/platform/admin/branches.blade.php tests/Feature/BranchLifecycleDependencyTest.php`.
- `git diff --check` passed. The guarded harness verified Composer resolves `App\Models\User` from this worktree. The isolated schema was created/migrated with explicit local-mode loopback MySQL and schema-specific temp storage; PHPUnit used the guarded testing-mode environment. No `.env` file, application database, production, browser session, or full suite was used.

## Changed files

- `app/Modules/Platform/Actions/SaveBranchAction.php`
- `app/Modules/Platform/Actions/PlatformSettingsApprovalAction.php`
- `resources/views/platform/admin/branches.blade.php`
- `tests/Feature/BranchLifecycleDependencyTest.php`

M1-F03 remains an ambiguity finding; no product policy was changed. No production deployment, push, merge, or milestone advancement occurred. The worktree commit is recorded in Git history; lead review and integration remain follow-up.

## Follow-up fixture and classification check

- Made the regression permission fixture idempotent by using `Permission::firstOrCreate()` keyed by `code`, with defaults only for absent catalog rows. No existing permission row is deleted or updated.
- Inserted the active `company_settings.approve` catalog row into the named disposable QA schema before the rerun. Guarded `BranchLifecycleDependencyTest.php` passed **5 tests, 25 assertions** with that pre-existing row; the row remained `company_settings.approve / company_settings / approve / active` afterward. Pint on the changed test file and `git diff --check` passed.
- `docs/03-scope.md` and the unresolved-decision text in `.ai/DECISIONS.md` retain Store/Warehouse terminology as open, so M1-F03 remains ambiguous; the existing count is active stores of type `warehouse` and was not broadened.
