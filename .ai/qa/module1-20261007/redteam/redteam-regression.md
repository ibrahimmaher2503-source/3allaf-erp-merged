# Module 1 second-pass review — regression lane

Reviewed integration: `07a514c94d106efe3516d81780ee9081ecfd86bc` on `hotfix/module1-redteam-regression-20261007`, in the isolated `m1-regression` worktree. This report covers test-harness safety/order, financial calculator plus HTTP mixing, numbering/drawer workers, permission-catalog cleanup, source-text debt, and false CSRF claims. Production and remote QA were not inspected.

## Checks

- A randomized mixed invocation over `CriticalRegressionTest`, `FinancialBoundaryTest`, `AuthenticationRegressionTest`, `PosShiftOpenRouteTest`, `CashDrawerConcurrencyTest`, and `DocumentSequenceConcurrencyTest` passed **43 tests / 1,378 assertions**. It inherited `APP_ENV=local`, root-checkout cache paths, a URL naming `rajh_erp_local_20261007`, and a socket value. The XML/bootstrap forced and synchronized the effective testing environment and disposable MySQL connection; the project `Tests\TestCase` environment, running-unit-tests, schema, and resolved-cache guards held. Both real two-process races passed.
- Seeded `company_settings.edit` in only `qa_rajh_m1_regression_20261007`. Before correction, `DocumentSequenceConcurrencyTest::test_editing_sequence_metadata_does_not_rewind_an_allocated_counter()` failed before assertions at `Permission::create()` with duplicate key `permissions_code_unique` (pre-fix line 147). After correction, randomized `DocumentSequenceConcurrencyTest.php` passed **2 tests / 17 assertions** with the same pre-existing catalog row. Read-back showed its ID/code/module/action/sensitivity/status unchanged. Sequence, branch, company, and audit fixtures were cleaned; only the exact synthetic permission row was then removed.
- Unsafe inherited `DB_DATABASE=rajh_erp_local_20261007` made PHPUnit bootstrap exit 2 with the QA-schema rejection before app boot. An unsafe `QA_STORAGE_PATH` outside system temp also exited 2; the rejected path did not exist before or after.
- `vendor/bin/pint --dirty --format agent`, PHP lint for the edited test, and `git diff --check` passed.

## Findings

1. **Fixed on this review branch — duplicate permission fixture.** The sequence metadata test formerly created the canonical `company_settings.edit` permission unconditionally and always deleted it. It failed when a normal catalog row already existed. It now uses `firstOrCreate` and deletes the row only when `wasRecentlyCreated` is true. No application code changed.
2. **Confirmed, not changed here — cash-drawer test leaves audit rows.** After the mixed run, `CashDrawerConcurrencyTest::test_cash_drawer_delete_request_rejects_stale_version_and_approved_delete_advances_it()` left two `approval_requested` / `approval_approved` audit rows (`branch_id=42`, `source_type=ApprovalRecord`, `source_id=6`) after its helper deleted the matching approval and branch. I removed only the two exact rows created by my run. The owner should clean approval-source audit rows by their captured IDs in `deleteDrawerFixture()`; no edit was made to that other lane's file.

## Coverage classification and limits

- The current focused calculator tests assert fixed expected financial values, and the mixed random run showed their pure-container setup remained independent of subsequent HTTP boots.
- `V0122Hotfix13CashDrawerIntegrityTest` is source-text-only; its string assertions are structural tripwires, not proof that the transaction, locks, or scope checks execute. The new drawer/sequence feature tests provide separate behavioral coverage. `V0122Hotfix12PosShiftOpenRouteTest` combines route metadata/source text with HTTP GET assertions; those text checks are not runtime-flow proof.
- No relevant test asserted a 419 response or disabled middleware. Laravel's test environment does not establish browser CSRF enforcement; the POST tests are business/authorization behavior evidence only. CSRF, browser/device acceptance, offline/PWA behavior, attachment surface, broad N+1 profiling, unrelated historical failures, and the full project suite were not tested in this bounded review. The parent owns the final suite.
- QA mutations stayed within `qa_rajh_m1_regression_20261007`; no application DB, production, `.env`, dependency, grant, push, or deployment changes occurred.

Review correction/session commit: `fed47ec71a96f34de776febff7594a493f663b0b`. The review branch is clean; the report remains outside Git by design.
