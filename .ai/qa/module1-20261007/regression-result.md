# Module 1 regression and harness result

**Worktree:** `C:\Users\N\.codex\worktrees\m1-regression\erp`  
**Branch:** `hotfix/m1-regression-20261007`  
**Scope:** shared guarded PHPUnit/Laravel harness; M1-F09, M1-F11, M1-F12, M1-F20; financial arithmetic, transaction boundaries, and bounded Eloquent/performance review. No application behavior fix was authored in this lane.

## Harness

Commits `40f0a38` and `2f13943` add the shared MySQL-only test harness and cache isolation; `e28bbd1`, `f12df2f`, and `21e7217` finish Windows direct-bootstrap and mixed-order calculator isolation. The global bootstrap forces testing mode, empty database URL/socket, and a named `qa_rajh_m1_<domain>_20261007` schema; it verifies the worktree and temp storage share a drive before setting rooted Windows cache paths. Both the project `Tests\TestCase` guard and a direct framework bootstrap smoke verify MySQL / `127.0.0.1:3306` / exact schema, temp storage, and isolated cache paths. Raw calculator classes rebuild a minimal translation/validator container and clear resolved Facade instances in each test setup.

Verification:

- `php vendor/bin/phpunit --configuration phpunit.xml.dist --order-by=random --random-order-seed=4107 tests/Feature/QA/NativeLaravelBootstrapSmokeTest.php tests/Feature/QA/LaravelHarnessSmokeTest.php tests/Feature/QA/CriticalRegressionTest.php tests/Feature/QA/FinancialBoundaryTest.php tests/Feature/QA/FinancialContractTest.php tests/Feature/QA/FinancialMatrixTest.php tests/Feature/QA/FinancialPrecisionClosureTest.php` — **44 passed, 4,190 assertions**.
- `vendor/bin/pint --dirty --format agent` — passed; explicit Pint on the two new QA tests also passed.
- `php -l` on changed bootstrap/test files and `git diff --check` — passed.
- Only the disposable schema `qa_rajh_m1_regression_20261007` was migrated. A test-mode migration was not used to create the initial schema: ordinary migration under explicit safe local-mode env succeeded; test-mode route boot first queried the not-yet-created translation override table. No fake table or provider bypass was added.

## Findings

**M1-F09 — PARTIAL.** The PDF's two inventory drafts 423 ms apart establish successful sequential allocation; page 19 explicitly says same-rule simultaneous numbering was not executed. A new integration test holds the branch row lock and starts two independent PHP/Laravel workers against the same XAMPP MariaDB schema. Both worker connection IDs were observed in `INNODB_TRX` as `LOCK WAIT` before releasing the lock. They then allocated unique consecutive values `...-IA-000001` and `...-IA-000002`, and the persisted counter advanced to 3. The test's branch-scoped audit assertion exposed a separate audit gap: both actual `document_number_allocated` records had `branch_id = NULL`. The audit owner committed `5978dd696a19569475c280177e7d2c1d2403a807` (`fix(audit): preserve event scope and context`); that commit is not integrated into this worktree yet. The focused race therefore remains red at the branch audit assertion pending integration and rerun. No code from that fix was copied here.

**M1-F11 — NOT TESTABLE (screen-scoped).** The PDF describes no upload input in its core Module 1 admin screens. Source inspection of the tested branch, store, drawer, role, authorization-baseline, and settings views found no file-upload input. The repository does contain upload flows in other areas, including catalog product media/imports, inventory opening/import, purchasing invoice import, and POS payment evidence. This finding does not establish that attachments are absent from the application. No upload behavior was changed or tested.

**M1-F12 — NOT TESTABLE (device/offline acceptance).** The app registers a service worker and has an installable manifest. `public/sw.js` caches only static shell assets and deliberately leaves dynamic/navigation requests network-only. The local POS queue uses IndexedDB with an allowlist, but offline POS is opt-in and defaults false; `OfflinePosPolicy` permanently rejects it in Production. No browser, network-disconnect, device, sync, or physical POS acceptance was run in this lane.

**M1-F20 — PASS (local behavioral check).** The test allocates a real branch number, then edits numbering metadata while submitting stale `next_value = 1`. The saved prefix changes while `next_value` remains 2 and `lock_version` remains 2. A direct Eloquent counter mutation throws `LogicException`. The focused test passed **1 test / 8 assertions**. Existing safeguards are in `SaveLocalSettingsAction::persistDocumentSequence` and `DocumentSequence::advanceCounter` / update hook; no application fix was required.

Financial arithmetic review found no new defect in this scope: the five existing POS/purchase calculator QA classes passed in the randomized 4,190-assertion run without altering expected values. The numbering and sequence edit paths use DB transactions and row locks; same-rule allocation was exercised under real MariaDB lock contention as above.

The core administration lists inspected are paginated and generally eager-load or aggregate related rows (branches 10, stores 10, drawers 15, roles 20, authorization users 15). No list-row N+1 was established. One scale risk remains unmeasured: `SaveStoreAction::dependencyReport` selects every dependency ID into memory to count it, with row locking enabled for lifecycle checks. Reference selector collections are also not load-tested at scale. This is a static risk observation, not a reproduced defect; no change was made because altering lock semantics needs its own review. M1-F18's high-volume search/pagination stress remains partial.

## Commands and next action

- Sequence edit: `php vendor/bin/phpunit --configuration phpunit.xml.dist tests/Feature/QA/DocumentSequenceConcurrencyTest.php --filter test_editing_sequence_metadata_does_not_rewind_an_allocated_counter` — **1 passed, 8 assertions**.
- Concurrency test: `php vendor/bin/phpunit --configuration phpunit.xml.dist tests/Feature/QA/DocumentSequenceConcurrencyTest.php --filter test_independent_workers_allocate_unique_numbers_under_real_mariadb_lock_contention` — two-worker uniqueness and counter checks passed; full test currently fails at the missing allocation-audit `branch_id` assertion before the audit fix is integrated.
- After lead integration of `5978dd6`, rerun both `DocumentSequenceConcurrencyTest` methods and record the final pass/fail output. The lead owns the final broad suite. No production inspection, push, deployment, or browser/device acceptance occurred.
