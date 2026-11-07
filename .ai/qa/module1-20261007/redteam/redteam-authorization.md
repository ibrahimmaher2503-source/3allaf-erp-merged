# Module 1 authorization red-team — second pass

Date: 2026-10-07  
Reviewed integration SHA: `07a514c94d106efe3516d81780ee9081ecfd86bc`  
Worktree: `C:\Users\N\.codex\worktrees\m1-authorization\erp`  
Branch: `hotfix/module1-redteam-authorization-20261007`  
QA database: disposable loopback XAMPP MariaDB schema `qa_rajh_m1_authorization_20261007` only.

## Scope and evidence

Reviewed approval inbox presentation and settings decision paths, and the newer Settings searches for permission and company/store scoping. Paths inspected include `resources/views/platform/admin/settings.blade.php`, `app/Modules/Platform/Actions/PlatformSettingsApprovalAction.php`, the approval presentation/access/policy/transition and request/decision actions, their routes, and focused approval tests. No blanket `Gate::before` grant was relied on; checks used the application's actual permission and branch-scope fixtures.

Confirmed settings-search authorization bypass: a branch-scoped user with the real `company_settings.edit` permission, scoped to Company A, could send a Livewire update setting the public `companyId` to Company B. The component's tax search then rendered Company B's tax row while `AuthorizedCompanyContext::resolve($actor)` continued to resolve Company A. The reproducer failed before the fix because the Company B marker was present in the rendered component. The minimal correction adds Livewire `#[Locked]` to `companyId`; the same direct client update is now rejected with `CannotUpdateLockedPropertyException`, and the authorized in-scope row remains visible. This prevents this forged property update; it does not establish broader company policy or prove every settings surface is safe.

Approval checks against actual gates/actions covered: authorized in-scope reviewer applies an unchanged tax-settings request; reviewer lacking `company_settings.approve` is denied through the direct action with no effect; maker cannot decide own request; out-of-scope reviewer cannot open the payload; presentation contains only allowlisted tax values and branch-delete dependency counts; rejection without a reason stays pending; tampered proposed company context/hash and forged source identifier are rejected with no tax write or approval event. These focused checks passed. Attachment download was inspected statically but not dynamically exercised.

## Verification

On the named QA schema, using the guarded `lead-env.ps1` process environment and committed `Tests\\TestCase` bootstrap:

```
php vendor/bin/phpunit tests/Feature/Platform/SettingsSearchScopeRedTeamTest.php tests/Feature/Platform/ApprovalInboxPresentationTest.php tests/Feature/Platform/PlatformSettingsApprovalRedTeamTest.php --testdox
```

Result: **9 tests, 46 assertions passed**. The settings-search regression had previously produced RED (1 failed test / 2 assertions) because the out-of-scope marker appeared before the lock was added. PHP syntax checks for the changed Blade SFC and two focused tests passed; explicit-file Pint and `git diff --check` passed. No full suite or browser run occurred. The schema was not recreated or dropped; only the dedicated QA schema was used. No production or remote data was accessed.

## PDF/source status and boundaries

This second pass does not reclassify the first-pass source matrix: M1-F04 remains `AMBIGUOUS REQUIREMENT`; M1-F06 remains PDF `NOT TESTED` and its remote claim unverified; M1-F10 remains PDF `PARTIAL`; M1-F14 remains PDF `BLOCKED`. BUG-222's source PDF status remains `FAIL`, although the bounded local approval behaviors listed above passed. The focused local pass is not evidence that all module roles/actions or remote records were verified. Alias/canonical-role grants, Super Admin role/flag interpretation, last-admin simultaneous mutation races, session/auth behavior, broader action/export coverage, and existing-data cleanup were declined as outside this second-pass approval/settings boundary or unresolved owner policy. The parent owns integration of the `editPrintTemplate` gate. No grants or existing records were changed, and no approval policy was broadened.

## Change

Changed `resources/views/platform/admin/settings.blade.php` with `#[Locked]` on `companyId`, and added focused regressions in `tests/Feature/Platform/SettingsSearchScopeRedTeamTest.php` and `tests/Feature/Platform/PlatformSettingsApprovalRedTeamTest.php`. The code/test/session change is committed on the worktree branch; there was no push, merge, deployment, or production action.
