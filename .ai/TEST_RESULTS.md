## 2026-10-13 — Shift close workflow focused verification

Focused disposable MariaDB run: `Module9CashShiftsQaTest.php` selected shift cases PASS, 8 tests / 74 assertions. Covered existing approval/recount and drawer guards plus cash-only blind submission, immutable system-derived electronic totals, pre-approval thermal/A4 print, final approved print, submitted-shift operational release, two pending closes allowed, and third-open rejection. PHP syntax, locale JSON, diff hygiene and Pint PASS. Fast QA ran once: 7/8 checks passed; CodeGraph failed because the new worktree initially pointed at the main-worktree index. A local CodeGraph index was initialized afterward; Fast QA was not rerun. No migration, production database operation, merge or deployment.

## 2026-10-13 — Audit 500 focused verification

Focused disposable MariaDB run covering audit detail/pagination without compiled islands, localized timestamps, and audit page plus CSV export. PASS: 3 tests, 12 assertions. Both `GET admin.audit` and `GET admin.audit.export` returned successful responses with CSV content validation. Production Laravel logs were read-only inspected; no production cache or runtime mutation was performed. No Fast QA was run because the change is isolated to the audit component and deployment cache lifecycle.

# 2026-10-13 — High-priority issues batch 1

Source `origin/main` `c4560c3`; isolated socket-only MariaDB schemas `qa_rajh_20261013174648_7290`, `qa_rajh_20261013174901_16143`, `qa_rajh_20261013175421_12764`, `qa_rajh_20261013175910_30142`, `qa_rajh_20261013180040_30189`, and `qa_rajh_20261013180328_22968` were used for focused checks. #41 quotation: two initial methods PASS, notes-edit fixture initially lacked an active barcode, corrected fixture and affected method PASS 1/9. #33 stock count PASS 1/5 in English and Arabic. #7 international final save PASS 1/7, draft behavior PASS 1/5. #1 purchase approval PASS 2/25. #2 POS rounding PASS 3/29. #11 shift close/recount/report and state visibility PASS 2/21 using a temporary verification copy of the existing test with its legacy fixed-schema guard adapted to the disposable schema; the copy was removed. #9 normal audit page, selected `ar-EG` locale and CSV export PASS 1/5 on isolated MariaDB; existing audit timestamp/blocked-export checks PASS 2/5. These local results do not establish production export behavior.

Production Laravel log was read-only for #9. IDs `e65992ee-ce17-4015-8d9f-afb4d636eefa`, `4cc82485-4a94-4517-8cf6-cfee4058a132`, and `6233794e-67fb-4375-bcb0-cf5926a10f12` all show Livewire island compilation failing at `CacheManager.php:224` on `touch()` with `Operation not permitted`. `/home/rajeh/shared/toyjoy/storage/framework/views/blaze` and all 172 inspected files are root-owned, group `rajeh`, mode 755; the runtime account cannot update them. The log has no `/admin/audit/export`, `ExportAuditLogs`, or `audit_log_exported` exception entry around the reported times, so the separate production export 500 cannot be attributed from available evidence. No application workaround or production permission change was made.

Changed PHP syntax, both Arabic JSON files, diff hygiene, and Pint PASS. One Fast QA gate PASS 8/8, 75 critical regression tests/485 assertions, isolated MariaDB integrity, runtime check and compact browser smoke; evidence `.ai/qa/runs/20261013T181640Z-12523-fast`, 181 seconds. No full release gate, production DB operation, merge, or deployment.

## Preserved previous results

# 2026-10-13 — POS close-shift header link hotfix

Authenticated local browser on disposable socket-only MariaDB `qa_rajh_pos_close_20261013165437`: PASS 16 checks for hidden link with no shift, own open shift link beside status, exact shift anchor navigation, existing blind-count form, foreign cashier denial on the canonical close POST, and English LTR/Arabic RTL labels. The browser fixture opened a shift through the canonical route; no close was submitted. Existing route inspection confirms a global System Administrator can view a blocking shift and use the same reasoned blind-count/approval path; no admin behavior was changed. The first two browser attempts stopped in temporary QA runner configuration (unset URL and nonstandard disposable path); a third reached the route but posted to the wrong test-driver URL. The corrected final smoke passed. Disposable runtimes were removed.

Fresh isolated MariaDB `qa_rajh_20261013170100_27993`: focused existing POS checkout/cash-rounding regression PASS 2 tests/14 assertions. First attempt was blocked before test execution because the shared Composer autoloader resolved App classes from the main worktree; a worktree-local ignored vendor copy corrected the environment, and the focused rerun passed. No business code was changed. Changed Blade/PHP syntax, Arabic locale JSON and diff hygiene PASS. Fast QA omitted for the narrow navigation-only change; no deployment or production check.

# 2026-10-13 — POS cash-rounding consistency hotfix

Fresh socket-only MariaDB `qa_rajh_20261013133044_17410`: focused `Module5PosQaTest` cash/mixed, disabled exact 550.00, enabled missing-denomination exact 550.00, configured 0.50 on 550.37, missing-denomination nonzero refusal/card acceptance, and role-based administrator/ordinary-user settings access PASS 6 tests / 49 assertions. Payment retry preserved one sale/payment posting in focused cases. The earlier partial run passed the four new transaction cases; the old test initially collided with the migration-seeded setting version 1 and was corrected to append its fixture version. A later attempt to reuse the browser database failed global-count assertions because browser sales existed there; its disposable runtime was removed and the final clean run passed. Both test-runtime directories were removed.

Authenticated browser on disposable `qa_rajh_20261013130445_14472`: standard Admin/User/Cashier desktop/mobile smoke PASS 78 assertions. Targeted English LTR and Arabic RTL smoke PASS 20 checks: cashier 550.00 cash sale completed with rounding enabled and denomination missing; cashier cannot access the financial settings route; role-based System Administrator navigated to and saved an explicit 0.50 version; cashier 550.37 sale showed +0.13 and completed at 550.50; clearing the denomination then showed the concise English and Arabic message and disabled nonzero cash checkout. The initial targeted QA driver accumulated prior draft quantity and then missed a Livewire timing boundary; it was corrected to create a clean order and use the authenticated canonical cart POST before the final passing run. No production browser or database was touched.

Changed PHP syntax, en/ar/ar-EG JSON, Pint, and diff hygiene PASS. Controlled Larastan for calculator, registry and settings action PASS zero findings. A broader changed-core invocation reported three diagnostics on unchanged lines 247/250/278 of `CapturePaymentAction` (nullsafe/coercion types), confirmed present in the source baseline; no new finding in the modified rounding logic. One requested Fast QA gate PASS 8/8 in 217 s, regression 75 tests/485 assertions; evidence `.ai/qa/runs/20261013T133342Z-28773-fast`. No migration, production mutation, merge or deployment.

# 2026-10-13 — POS cashier-shift consistency hotfix

Source `b271e7315c57005bb747efd896aab206db337f3f`; branch `hotfix/pos-shift-consistency`. Focused socket-only MariaDB `qa_rajh_20261013071430_7088`: existing open/scope/collision tests PASS 3/34. Existing scope-revocation race PASS 1/5 on `qa_rajh_20261013073750_1652`. Existing two-worker `concurrency-worker.php` was run directly on `qa_rajh_20261013075034_1504`: one same-drawer open succeeded, one was rejected, with exactly one persisted shift and one active assignment. A related exclusivity method passed 1/12 on that database. All three task disposable runtimes were removed.

Authenticated browser smoke on disposable `qa_rajh_20261013071430_7088`: standard Admin/User/Cashier desktop/mobile smoke PASS 78 assertions. Targeted browser actions and UI readback PASS for free-drawer open, own active shift and enabled POS, foreign active-shift disclosure with no ordinary close control or open form, foreign direct close POST 403, role-only System Administrator blind count with reason and canonical approval, released drawer and next-cashier reopen, enabled POS, English LTR and Arabic RTL. Database readback PASS: original `opened_by`/`cashier_id` retained, administrator `closed_by` and close audit recorded, close document present, and one successor shift active. The targeted browser run was resumed after the first script used the wrong inbox URL (`/admin/approvals`); the application route `/approvals` passed.

Fast QA on the executable worktree PASS 8/8, 75 tests/485 assertions in its regression row, 232 s; evidence `.ai/qa/runs/20261013T073250Z-17661-fast`. The first gate attempt in `/tmp` failed before its checks because `/tmp` is mounted `noexec` and the temporary Composer proxy conflicted with PHPStan; its report is `.ai/qa/runs/20261013T073042Z-26898-fast`. The corrected worktree passed tool, CodeGraph, syntax, PHPStan, MariaDB integrity, runtime, and compact browser checks. Final Pint, JSON parsing, and diff hygiene PASS.

Limitations: the existing `V0122Hotfix13CashDrawerIntegrityTest` failed at its old generic-message assertion after the action correctly returned the specific reused-key error (1 test, 14 assertions at failure). The existing Phase 2 held-lock race harness failed its instrumentation condition before outcome checks because current opening serializes on the store row before the drawer row; the direct two-worker outcome check above passed. No production or client UAT check, migration, merge, or deployment occurred.

## Preserved previous results

# 2026-10-12 — Product Card cost visibility hotfix

Focused `PurchaseApprovalReceivingTest::test_system_administrator_product_card_shows_optional_cost_without_an_active_permission_row` PASS 1 test/13 assertions on isolated socket-only MariaDB `qa_rajh_20261012141713_17086`. The test deactivates the cost permission row, verifies role-based administrator field visibility and cost save/blank/null behavior, and verifies ordinary editor denial. Authenticated browser smoke on fresh isolated `qa_rajh_20261012152430_14815` PASS: Arabic RTL administrator sees cost on create/edit, saves 10 and reloads it, blank unrelated edit preserves 10; English LTR shows Cost price; ordinary editor cannot see the field; no browser errors. PHP/JS syntax, Pint, and diff hygiene PASS. Existing `ProductCardMetadataFormTest` was probed but not counted: it has a pre-existing Livewire isolated-render `with()` null error after its initial assertion, and its flag-only fixture is not the canonical role-based administrator scenario. No Fast Gate was run as instructed for this narrow authorization/UI fix. No production database or deployment action.

## Preserved previous results

# 2026-10-12 — Product Card cost and purchasing default batch

Disposable socket-only MariaDB `qa_rajh_20261012141713_17086` (`/tmp/rajeh-qa-mariadb.iWXHsw`): Product Card/purchase approval/lookup PASS 21 tests, 179 assertions; staged import update-existing PASS 1 test, 11 assertions. Product-cost backend A–F passed in the interrupted run; isolated browser-only rerun passed Admin/Editor saves, Arabic blank edit, cost permission visibility and no-cost finalization; database readback/optional workbook import passed. Purchase-approval browser smoke passed Arabic RTL and English LTR, with no browser errors. Changed PHP/Blade syntax, locale JSON and Pint passed. Controlled Larastan passes for `SaveProductAction` and `SupplierProductPrice` (0 findings); `StageProductImportAction` reports 20 pre-existing findings, none on changed lines. First Fast Gate report `.ai/qa/runs/20261012T142932Z-20524-fast`: FAIL because `/tmp` ran out of space during fresh MariaDB migration; following stages could not use the database. Stale `/tmp` PHPStan caches were removed. Retried Fast Gate `.ai/qa/runs/20261012T143204Z-27598-fast`: PASS 8/8, zero warnings/skips/failures in 220 seconds. The earlier product-cost command ended after `/login` during interruption; its exact browser exception was not retained, and the isolated browser rerun passed. No production DB, deployment, deep audit, or main merge.

## Preserved previous results

# 2026-10-07 — Fast QA harness repair

I4, source `fb683347ccfc3bccc382b684f287d7af0afa075d`, branch `hotfix/fast-qa-harness`. Existing `mariadb-run.sh` provisioned fresh private socket-only MariaDB databases with networking disabled; PHPUnit ran in `testing` with guarded temporary storage/caches and the script checks retained `qa`.

- Initial focused suite: PASS 57 tests / 327 assertions, including unchanged financial/product calculator cases, Laravel/database regressions, 13 unsafe-environment denials, and actual server socket/database/disabled-networking assertions. Database `qa_rajh_20261007130635_32194` removed by cleanup.
- Single fresh Fast Gate: 7/8 PASS in 206 s, report `.ai/qa/runs/20261007T130940Z-18040-fast/report.json`. Only numbering lock-wait observation failed (56/57 tests, 322 assertions); all other gate checks, including real catalog/integrity and compact guest browser smoke, passed. Database `qa_rajh_20261007130957_1099` removed by cleanup.
- Confirmed harness flake: MariaDB 10.11 refreshes lock metadata only after more than 100 ms since its last read; the test's 10 ms poll could retain a one-worker snapshot throughout the timeout. Changed only this poll to 200 ms, retaining the assertion that both independent workers are blocked before releasing the branch lock and all numbering/audit assertions. Syntax and dirty Pint passed. Affected method PASS 1 test / 9 assertions (847 ms) on disposable `qa_rajh_20261007131514_10900`, then removed.
- Direct unprovisioned `phpunit.qa.xml` invocation: correctly rejected by the bootstrap before Laravel/database boot.
- Final Fast Gate: PASS 8/8, zero warnings/failures/skips, using the existing resume mechanism only for the failed regression row. That row passed 57 tests / 327 assertions (9.214 s PHPUnit; 120 s including fresh DB provisioning). Seven passing gate rows preserved from the original report; elapsed resumed invocation 122 s. Final report `.ai/qa/runs/20261007T134339Z-28990-fast/report.json` and local `.ai/qa/latest-fast.json`. Database `qa_rajh_20261007134346_24153`, its runtime and `.qa-runtime/last-db` were removed.

CodeGraph used briefly against the verified current root without init/sync. Shell syntax, affected PHP syntax, Pint and diff hygiene passed. No deep/release suite, business-logic or migration-file edit, production access/deployment, main merge, dependency change or business milestone/UAT advancement. These are local gate results, not a claim about GitHub Actions.

# 2026-10-07 — Module 9 focused QA evidence

`tests/Feature/Module9CashShiftsQaTest.php`: 6 tests, 69 assertions PASS (15.496 s), dedicated schema-only disposable XAMPP MySQL `qa_rajh_m9_cash_shifts_20261007`, transactions rolled back, isolated temporary storage/cache, no runtime database writes. Verified stored Expected/Actual/Variance rendering; pending variance excluded and only final approved recount counted; existing 5 EGP control discoverable/savable; Open/ClosingSubmitted/VarianceReview headers on both POS/shift screens and Closed layout; closing/open explanation; occupied-drawer and missing-confirmation errors without second shift; movement actor/reference/reason/type/amount including absent reference. Initial missing counts and pending -50 reproduced before fixes. Test setup corrections: existing `visuals` key, normalized setting `5.0000`, English JSON locale and session cookie persistence.

Changed PHP/Blade PHP syntax (9 files), Arabic JSON syntax and diff whitespace PASS; `pint --dirty --format agent` PASS. `optimize:clear` ran once against isolated QA paths; `npm run build` once PASS (Vite 8.2.0, 2.08 s, existing optional-fontaine warning). No browser, full suite, PHPStan or production check. No confirmed new regression within this scope. M9-07 report confirms native-dialog tooling limitation; no app change. M9-05 mandatory acceptance reason remains DEC-100 only.

# 2026-10-07 — BUG-121 focused local verification

Owner explicitly authorized only BUG-121 automated verification on a separate local MySQL database. Used XAMPP `127.0.0.1:3306`, disposable `qa_rajh_bug121_20261007`, schema-only copy of the current local schema (no business data). Initial empty-schema Artisan bootstrap hit the existing missing `translation_overrides` table dependency; schema-only initialization avoided changing that unrelated behavior.

`Bug121PurchaseCostPermissionTest`: baseline reproduction failed at cost authorization before the fix. Three distinct cases subsequently passed: upgrade/System Administrator/Livewire draft save, preserving inactive permissions and existing role grants on migration replay, and restricted-role UI/server denial with no invoice write. After the initial focused run, only the failing success case was rerun while correcting fixture completeness and existing decimal-cast expectations. Its final result was 1 test / 12 assertions; the other two cases were already passing (9 assertions). Saved draft total `75.0000`, unit cost `37.5000`, independent sale price `50.00`, unchanged WAC `10.00`, and zero stock movements.

Changed PHP syntax (4 files), Arabic JSON parsing (2 files), rendered Livewire/Blade views, and one Vite build passed. No full suite or browser control. No production verification or writes, and no migration was applied to the normal local database.

# 2026-10-04 — QA Hardening Phase 2 actual focused evidence

`phase2-evidence/` and `phase2-mutation-summary.json` record eleven populated domain/concurrency scenarios, thirteen two-connection races, five new invariant counts (all zero), exact static before/after comparison and eight-file security zero findings. Seed 20261004: 2,097 POS, 1,327 purchase, 400 signed rounding and 60 cash cases (3,884 unique generated cases); additional exact financial/denial/boundary tests passed. Final five-file Infection plus affected-only newly covered default-rule replay: covered 547, killed 434, raw survivors 113, raw corrected MSI 79.34%, reviewed equivalents/unreachable 86, retained 27, adjusted MSI 94.14%, errors/syntax errors/timeouts/ignored zero. No broad old audit, full browser/Semgrep scan or restore repeated. Fast PASS 104s and Release PASS WITH WARNINGS (0s preserved report assembly); actual CI/publication pending this entry.

# Test and Verification Results

## 2026-10-07 - Module 5 POS focused QA

PASS: 10 focused POS methods / 119 assertions (tests/Feature/Module5PosQaTest.php) on disposable XAMPP qa_rajh_m5_pos_20261007; 7 payment-calculator assertions; existing missing-audit-cap method 1 test / 2 assertions. Covers cash/mixed nearest-5-EGP settlement and invalid config feedback, exact/under/over/repeated card submission, decimal whole quantity and stock refusal, customer/items preserved through hold/resume and repeat rejection, required protected evidence/type/size, over-gross/negative/zero/equal discount boundaries, safe thumbnail fallback and authorization, bilingual product fallback, active/unavailable held context, customer policy feedback/code/company scope. Syntax for 7 PHP + 6 Blade files PASS; changed views rendered in focused checks; locale JSON PASS; Pint executed; build PASS once; isolated optimize-clear completed once. No browser/full suite/static/production check. M5-10 remains decision-required; no confirmed new regression in the focused scope.


## 2026-10-03 — Final Full-System Audit Closure

**Status:** Remaining authorized browser/output audit closed; confirmed defects repaired and deployed. **Readiness:** Operational application verified; unconditional trading/client handover NOT approved pending cashier/shift/opening configuration, populated financial/inventory UAT in disposable XAMPP MySQL/MariaDB, device/transaction-print/offline acceptance and next scheduled backup evidence.

**Application:** `599a5084f7c1c2bb0aee5d6a67fc5df3d9dfd0be`, tree `51129f22539a3ceebc14e6b1d0171896f9131886`, version `0.1.22-audit-final3`; `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.22-audit-final3-24f1c5a`. UI/source fixes merged via `a4d2604`, PDF repair via `66605f1` and cost disclosure repair via `10957d6`, and pushed to main; later report-only closure does not rebuild the artifact.

**Coverage:** 386 unique authenticated Arabic/English desktop/mobile renders; 23 safe-control assertions; shipped UI affected checks; focused independent cost-view export confidentiality checks; 25 output requests and 42 final continuation assertions; XLSX/XML/PDF integrity; isolated repaired bilingual PDF visual rendering. Completed initial source/CodeGraph/database/backup coverage remains preserved. No suite, broad rescan, CodeGraph reinitialization/sync or repeated passing browser matrix. One Vite build; PDF artifact reused it.

**Access:** Exactly two approved sessions for existing administrator ID 1 and normal user ID 3 were created without password-hash inspection or password/identity/grant changes, then immediately revoked after authenticated coverage. Private cookies/tokens deleted; no further session created. No production business fixtures/transactions; output GETs produced their intended operational audit events.

**Release:** New immutable PDF artifact SHA-256 `35eef98261e033575f4662a99a070b5d0b0ff03fce6654d5b17bfac3905f9286`; manifest `a0c96bbd206480b75db8e05c8b8bc5cfeded11af82b8583f3dd3b8d570c3aab7`; 14,816 files verified. Original e6d2d1e and af6874e archives preserved. Journal `/var/lib/toyjoy/audit-final3-599a508`; exact rollback target 24f1c5a. Final-root cache/autoload checks and the single final compact smoke passed, maintenance off, services active, failed jobs zero, cron/schedules present, `/up`/`/login` 200 and unauthenticated `/admin` 302.

**Report:** `docs/audits/RAJEH-FULL-SYSTEM-AUDIT-2026-10-03.md`; evidence `.ai/evidence/full-system-audit-2026-10-03/`. No milestone, phase gate, UAT or device sign-off advanced. Remaining acceptance constraints are documented, not represented as passing checks.

**Late D3 repair:** Confirmed product XLSX/PDF cost disclosure when export is granted without the independent cost-view permission. Server output now omits the cost header/value unless the existing cost-view gate allows it. Focused visible/hidden-cost shapes, XLSX content and PDF generation passed; no roles, permissions, grants, identities or sessions changed. Dedicated final3 artifact activated; prior release receipts are preserved. The final target compact smoke passed once after this code change.

## Earlier Verification Records (Historical)

# Test and Verification Results

## 2026-10-03 — Full-System Audit Continuation

- Preserved original e6d2d1e code/report, source scans, CodeGraph analysis, lint/Blade sweep, database/integrity and initial backup evidence. Those passing checks were not repeated.
- Outstanding caller-cwd database-backup regression exposed stale renamed-stage paths in production config cache. Repaired only generated final-release caches and reloaded established PHP-FPM. Six runtime-path assertions passed. Only the affected backup regression resumed and passed encrypted dump/archive integrity/local-copy checks.
- Resumed the already-active deployment's missing compact smoke once: exact e6d2d1e release, maintenance off, PHP-FPM/cron active, no failed queue jobs, three backup schedules, `/up` and `/login` 200. This is not full-audit acceptance.
- Newly available Chromium visual pass: ar/en desktop 1440px and ar mobile 390px login layouts/direction passed, no page errors/page overflow. Screenshots inspected and retained in `.ai/evidence/full-system-audit-2026-10-03`. Found untranslated/misleading email separator; source repair changes only login copy and three locale entries.
- Source copy fix: JSON/translation resolution, changed Blade/compiled PHP syntax and isolated ar/ar-EG/en rendering passed. Initial renderer failed on absent local Vite manifest; corrected to reuse existing public assets with explicit nonzero failures and resumed only affected renderer. No asset rebuild.
- Authenticated Admin/User browser pass, persisted transaction/workflow/concurrency/ledger/print/offline/device verification remain NOT RUN here. Automatic approval review rejected the broad session/password-hash helper before execution; narrower two-session approval is pending. No cookies from that helper or business test data were created.
- No automated test suite created/run; no new CodeGraph sync, broad test loop, migration, role/grant change or new frozen release artifact. Source repair `b04924d4c973580249aa999adab34078935cefb3` is committed/pushed only on the preserved audit branch; integration/release pending.

## 2026-09-30 — Hotfix51 focused product/supplier verification and deployment

- **Focused code/data result:** PASS, 50 assertions in isolated restored MariaDB `toyjoy_hf48_restore_368d44f2acea961a9a`, wrapped in rollback. Exact canonical and numeric results were `SUP-0002 → 0002-000001` and `0123456 → 0123-000001`. Explicit draft save, same-product final completion, local identity barcode creation, no duplicate product, specific invalid-final errors with no draft, and separate international-barcode retention passed.
- **Supplier result:** PASS. An authorized disposable unlinked supplier was deleted and audited; linked deletion was rejected with its dependency/history preserved; a linked disposable supplier could be deactivated with history preserved; unauthorized deletion was denied. All records were isolated and rolled back. No real supplier was deleted or deactivated.
- **Reused evidence:** Hotfix49's focused evidence remains the proof for shared-prefix row locking/atomic sequencing, collision advancement, existing-code preservation, and inactive-draft immutability. Those scenarios were not broadly repeated.
- **Static/render checks:** Changed PHP and focused verifier syntax, all three locale JSON files, affected Blade compilation, full Blade cache compilation and compiled-view PHP syntax, package/manifests, and Git diff hygiene passed. `RemoveSupplierAction` formatting passed. A file-scoped Pint command also reported pre-existing style findings across `SaveProductAction`; that file was not mechanically reformatted because it would create unrelated churn. No full automated suite ran.
- **Deployment result:** PASS. Immutable application commit `b709193815c548bb0100a0b641171c5af7aaf314` / tree `9be1c3dba9f8ee2be992496487d5d300916e252a` was packaged as authenticated R6, with no migration or frontend build. A fresh encrypted backup restored and reconciled database/schema/private-file state before the verifier passed. Atomic activation, caches, PHP-FPM reload, runtime restoration, maintenance-off state, release identity, and HTTPS `/up` plus `/login` HTTP 200 passed.
- **Live read-only result:** PASS after correcting the probe for an empty catalog. User ID 1 rendered the Product Card create screen and Suppliers page in Arabic and English with HTTP 200, correct RTL/LTR, Hotfix51 contextual help, delete/deactivate controls, and version. Temporary sessions were removed; no business record was changed.
- **Recovery evidence:** Successful journal `/var/lib/toyjoy/hotfix51-deployment/b709193815c5-20260930.4qsXOu`; encrypted archive 39,022,816 bytes, SHA-256 `536f3a2998cb0abc376d17e24dd5991ec373ec26050ba343abde1f036a858213`; isolated account locked and credential files removed. Earlier attempts `53472dfbe636-20260930.Zwwn2q`, `3ba703e05529-20260930.mkxehE`, `b709193815c5-20260930.PvBJcO`, and `b709193815c5-20260930.5Vy7T5` stopped before activation and recovered exact Hotfix50; `b709193815c5-20260930.2JOL1w` stopped before maintenance. See `.ai/HOTFIX51_CHECKPOINT.md` for causes.
- **Boundary:** No PHPUnit/Pest/broad suite, production test transaction, production supplier mutation, migration, dependency update, Vite build, push, or tag occurred.

## 2026-09-29 — Hotfix50 focused isolated permission-UI check

- **Environment:** Dedicated MariaDB clone `toyjoy_hf50_verify_2b798b36b23f`; temporary database account locked after verification; production remained read-only and no production role or permission grant changed.
- **Result:** PASS. Authenticated Chromium rendered all 412 permissions in 34 sections with mapped labels/descriptions. Arabic and ar-EG showed Arabic labels without raw keys or English fallback; English showed business labels. Arabic direction/Cairo and English LTR passed.
- **Interaction:** Purchase Orders began partially selected; `All` selected all 12 permissions in that section and preserved the Supplier section. Clearing one permission immediately restored the indeterminate state. Existing Save persisted 11 Purchase Order permissions, and reload reproduced 11 plus the unchanged Supplier permission.
- **Evidence:** `/var/lib/toyjoy/hotfix50-validation/2b798b36b23f/focused-result.json`, `permission-ui.png`, and `SHA256SUMS`.
- **Boundary:** This was one focused scenario with setup/harness retries and implementation corrections before the final pass, not a test suite. No PHPUnit/Pest suite, migration, asset build, production browser mutation, or production permission test was run.

### Hotfix50 deployment and live verification

- **PASS — package/restore:** Immutable application commit `0ff36dd24542d8378f6e9c24784e992b1d56ad74` / tree `90d224119ad75269627fce4b2c4cb96a8d02dfb2` was prepared without migrations or rebuilt assets. Fresh encrypted archive `consistent-database-and-private-storage.zip` (39,051,369 bytes; SHA-256 `113368a0d3c77c3dab45058a9fe72dd21643b0e711a40a0196b24466858da112`) restored and reconciled database rows/schema, selected private files, and stock state in isolated MariaDB `toyjoy_hf48_restore_a1fe5362f65997cef4`.
- **PASS — activation/health:** Production atomically activated `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.22-hotfix50-0ff36dd24542`; release identity, PHP-FPM, restored runtime controls, maintenance-off state, and HTTPS `/up` plus `/login` HTTP 200 passed.
- **PASS — affected live page:** A read-only authenticated check returned HTTP 200 for the role-permissions page in Arabic and English, rendered all 34 localized sections, the expected purchase labels and localized bulk control, Hotfix50 version/direction, and no raw permission key in visible text. Temporary file sessions were deleted; the check did not save or alter any production grant.
- **Verifier correction:** The first post-activation assertion searched the complete Livewire HTML payload and treated serialized internal permission state as displayed text; Laravel's console exception renderer also returned zero, allowing the wrapper's success line. The page itself already returned correct localized content. The verifier was corrected to inspect visible text and explicitly exit nonzero on any throwable, then its read-only live check passed. Application rollback was not required.
- **Evidence/recovery:** `/var/lib/toyjoy/hotfix50-deployment/0ff36dd24542-20260929.v4d2J5`. No broad/automated suite, production permission mutation, migration, dependency update, asset build, push, or tag occurred.

## 2026-09-27 — v0.1.22-hotfix49 focused verification

- **PASS:** The one owner-authorized focused verifier completed 23 assertions in dedicated MariaDB `toyjoy_hf49_verify_2aa6628094c5`, with every data mutation inside a rolled-back transaction. The account is locked and no generated credential was retained.
- **PASS — rows:** Purchase-order create/edit share the corrected full-page line partial; purchase-invoice create/edit share the corrected modal line editor. Both retain their desktop Product column heading, use a screen-reader-only Product label plus direct `aria-label`, align the selector with sibling `mt-1`/`h-10` controls, and retain responsive RTL/LTR classes. Affected Blade sources compiled during the verifier.
- **PASS — codes:** Supplier code `0123456…` previewed/allocated `0123-000001`; a second supplier beginning `0123` allocated shared next code `0123-000002`; an existing `0456-000001` collision was preserved and skipped to `0456-000002`. Invalid nonnumeric starts and missing suppliers were refused clearly. Existing saved codes, inactive NULL-code drafts, final-code immutability, generated local barcode behavior, and an international barcode remained preserved.
- **PASS — structure:** Allocation uses prefix-scoped rows in `global_product_code_sequences`, `lockForUpdate`, advancement before collision checks, and no `MAX` query. Changed PHP/verifier syntax, three locale JSON files, existing compiled CSS utility presence, and Git diff hygiene passed.
- **INFO:** The first verifier invocation stopped before its transaction because a verifier-only source assertion interpolated `$prefix`. The assertion literal was corrected and only the same focused verifier/database was resumed; no reset, application failure, or persisted verification record resulted.
- **Boundary:** No PHPUnit/Pest/broad suite, browser control, production test record, migration, data reset, dependency change, or Vite build ran. Deployment verification is pending.

## 2026-09-27 — v0.1.22-hotfix48 focused verification

- **PASS:** All changed/new PHP syntax, changed JavaScript syntax, `ar`, `en`, and `ar-EG` JSON parsing, six changed Blade compilations with compiled PHP syntax, and Git diff hygiene.
- **PASS:** Exactly one Node 20.20.2/Vite 8.2.0 production build; no package installation occurred.
- **PASS:** Dedicated MariaDB `toyjoy_hf48_verify_54d17584239f736c` verified PO supplier-specific approved cost/zero fallback, zero PO discounts/taxes, invoice totals/save/reload, draft-safe product/supplier state, approval-only last cost/Base selling price/supplier history, custom-price and inventory-WAC preservation, inline local/international identities and entered prices, supplier-return opening, transfer availability/WAC/effective price/field order, branch-shared immutable numbering, and source-branch immutability. Every scenario ran inside a rolled-back transaction; production records were untouched.
- **PASS:** A newly created active non-Super-Admin System Administrator immediately received every active application permission and global active company/branch/store visibility; an undefined dotted ability remained denied and maker/checker bypass remained reserved to Super Admin.
- **INFO:** Two segment runs initially stopped on verifier-only literal decimal-scale assumptions (`47.0000` versus `decimal:3`, then `11.00` versus `decimal:3`). The assertions were corrected to numeric comparisons and only the outstanding segments resumed.
- **Database account status:** Locked after verification; no generated credential retained. No migration, broad automated suite, or browser-control run occurred.
- **PASS (package/restore):** The immutable 14,790-file candidate manifest passed. Fresh AES-256 encrypted database/private-storage backup SHA-256 `3be76e31773735291033ba385e19cb2ab29786a5fef01469d3e26cabc0f8fdbb` was fully decrypted and reconciled in isolated MariaDB `toyjoy_hf48_restore_d06b3d85690e33b35d`; database rows/schema, selected files, and inventory state matched. The generated account is locked and credentials were removed.
- **PASS (activation/live):** Exact release commit/tree/version, atomic link activation, optimized Laravel caches, PHP-FPM reload, restored cron writers, absent maintenance, and HTTPS `/up` plus `/login` HTTP 200 passed. Authenticated user 2 (`karim`) and user 1 (`hassan`) both reached dashboard and initial setup without a loop; user 2 also rendered purchase-order create, purchase invoices, supplier returns, and transfer create with Hotfix48 markers.
- **INFO (safe retry):** The first operator attempt stopped before maintenance, backup, or activation on an unreadable Hotfix47 cache `.gitignore`. The cache metadata ownership was normalized and candidate cache preparation hardened; the subsequent run passed. Successful journal: `/var/lib/toyjoy/hotfix48-deployment/d59f5644dde5-20260927.oU5iJP`.
- **Boundary:** No migration, production business test record, broad automated suite, browser-control run, dependency upgrade, push, or tag occurred.

## 2026-09-26 — Hotfix47 focused authenticated-navigation verification

- **CONFIRMED (pre-fix live trace):** For ID 2 / `karim`, the login response sent the verified authenticated user to `/dashboard`; dashboard company-context resolution redirected to `/login`; authenticated guest handling sent `/login` back to `/dashboard`; the verification notice also redirected to `/dashboard`; `/initial-setup` failed while resolving the same missing context. The existing `/admin/authorization-baseline?setup=true&setup_step=users-scopes` route rendered HTTP 200 for this user.
- **PASS (package/restore):** Immutable Hotfix47 application package SHA-256 `255edc32ba4f727d458d2c4ac1faaf70a491da37c93fe710859188fb614d7aff` passed. A fresh encrypted backup was restored and fully reconciled in isolated MariaDB database `toyjoy_hf47_restore_d3f54ec2406121368e`; the account is locked and no generated credentials are retained. Migrations and compiled assets were unchanged from Hotfix46.
- **PASS (live authenticated ID 2 / `karim`):** Login response, direct dashboard, authenticated `/login`, and `/initial-setup` each terminated at the protected Users, Roles & Scopes setup URL. The verification notice retained its normal dashboard redirect, whose next decision terminated at that same setup destination. The final setup page rendered HTTP 200 with the expected authenticated marker.
- **PASS (live authenticated ID 1 / `hassan`):** Login and authenticated `/login` retained the normal dashboard destination; dashboard and `/initial-setup` rendered HTTP 200 with their expected markers. The protected setup route also remained accessible.
- **PASS (operations):** Production serves exact application commit `3ed4a9529c45806175424f70e8ce05993e8233cb`; maintenance is off, PHP-FPM is active, and runtime controls are restored. Two earlier operator attempts stopped before activation and recovered exact Hotfix46; the successful journal is `/var/lib/toyjoy/hotfix47-deployment/3ed4a9529c45-20260926.ek14It`.
- **Boundary:** One focused live authenticated navigation pass was run after deployment and was not repeated. No broad suite, migration, reset, account creation, credential request, business-data mutation, dependency update, asset build, tenant-scope change, push or tag occurred.

## 2026-09-26 — Hotfix46 focused user-management verification

- **PASS:** PHP syntax, Arabic/Arabic-Egyptian/English JSON parsing, Git diff hygiene, immutable candidate manifest and Laravel cache preparation.
- **PASS (one isolated MariaDB scenario):** From a current production copy, an authorized administrator created an account with the System Administrator role and empty administrator location scope. The account was immediately verified, no notification was recorded, creator/verifier audits pointed to the authenticated administrator, credential login passed, verification middleware returned 204, user IDs 1 and 2 were visible, and same-role assignment to the affected account succeeded. The transaction rolled back.
- **PASS (live read-only):** Commit `345c3af3f34a9e0ecd75e6694f3183f8f345cb99` is active; ID 1 / `hassan` remains the protected super-admin; ID 2 / `karim` is active, verified, non-super-admin, has only the active System Administrator role, retains zero branch/store scopes, sees user IDs 1 and 2, and has view/create/edit user permissions. Verification middleware returned 204 and remediation audit records system Hotfix46 with creator actor ID 1 and no notification.
- **PASS (operations):** Fresh verified platform backup completed, activation was atomic, Rajeh runtime controls were restored, maintenance is off, PHP-FPM is active, and live `/up` plus `/login` return 200.
- **Boundary:** No migration, reset, production test user/transaction, automated suite, browser-control run, dependency update, asset build, push or tag occurred.

## 2026-09-18 — Hotfix43 production deployment verification

- **PASS — package/restore:** The immutable 14,779-file candidate manifest passed. A fresh AES-256 encrypted database/private-storage backup was fully decrypted, restored to isolated MariaDB `toyjoy_hf43_restore_78b1e7d1d7887b46a9`, and reconciled by files, complete database schema/rows and stock state.
- **PASS — production migrations:** Only pending 000117 then 000118 ran. Production records batches 24 and 25 respectively. Required tax snapshot/ownership schema and constraints passed; captured invoice-line, invoice-header and tax-setting business hashes were unchanged.
- **PASS — activation/live:** Exact release identity, atomic link switch, Laravel caches, required PHP-FPM reload, runtime controls, absent maintenance and HTTPS `/up` and `/login` HTTP 200 passed. Nine affected authenticated GET pages returned 200 in English/LTR under a read-only database session with array cache/session state and no test transaction.
- **PASS — preservation:** Quantity/value mismatch count `0`, missing balance count `0`, and the single approved unresolved average-cost exception remained exactly balance 1/product 2/store 10/quantity 0/value 0/average cost 10.
- Existing focused application/browser/document checks were not repeated. No automated suite, seed, frontend build, production test transaction, Excel application, or physical printer/scanner acceptance ran.

## 2026-09-18 — Hotfix43 focused isolated acceptance

- **PASS — MariaDB:** A production-derived isolated MariaDB restore applied migrations 000117 and 000118 exactly once. Exact column definitions, unchanged historical invoice money, tax ownership backfill, company/code uniqueness, lookup index, restricted FK, safe no-op rerun, and fail-closed rollback were checked. No SQLite operation occurred.
- **PASS — focused transactions:** Inclusive/exclusive invoice math and snapshots; company tax ownership, duplicate rejection, restricted deletion and cross-company rejection; branch PO numbering continuity; supplier English-name enforcement; submitted-PO return/reason history; and all seven central export datasets passed. Scenario writes occurred only in the isolated database and rollback-only transactions where applicable.
- **PASS — browser:** Authenticated affected routes rendered HTTP 200 in English; Arabic and Egyptian Arabic rendered RTL; explicit-company product lookup selected through Arrow/Enter; zero-opening refused existing stock history; 390 px Product Cards rendered; zero console/page errors. Browser control was explicitly authorized by the active task.
- **PASS — documents:** All 12 shared XLSX generators passed ZIP integrity and XML parsing for every workbook XML part. Three localized PO PDFs were generated and identified as PDF 1.7. Microsoft Excel and physical printer/scanner acceptance were not performed and remain external.
- **PASS — source/build artifacts:** 49 changed/new PHP and Blade sources passed PHP 8.4 syntax; Blade cache generation passed and 716 compiled views passed syntax; 405 routes listed; all 99 changed locale keys exist and are nonempty in `en`, `ar`, and `ar-EG`; `git diff --check` passed. The existing Node 20/Vite build is newer than the unchanged frontend source and every manifest asset is nonempty; no redundant build ran.
- **NOT RUN:** PHPUnit, Pest, Laravel automated suites, Cypress, or an automated broad regression suite.

## 2026-09-18 — Hotfix43 pre-schema implementation checkpoint

- Read-only production checks confirmed the active Hotfix42 commit/release, maintenance off, and HTTP 200 for `/up` and `/login` before correction work.
- Direct PHP 8.4 syntax checks passed for changed PHP/Blade files checked during the implementation pass. All three locale JSON files parse, and `git diff --check` passes.
- These are interim structural checks only. No automated application suite, final Blade compilation, browser control, MariaDB mutation, migration, XLSX generation/open validation, PDF rendering, frontend build, deployment, commit, push or tag has occurred.

## 2026-09-05 — v0.1.22-hotfix14 focused verification

- Direct database-free arithmetic/source verification passed 20 assertions covering all required upward-derived-price examples, nearest-5 cash examples, unchanged base/manual price boundaries, exact non-cash settlement, append-only setting migration, and safe missing/invalid configuration handling.
- PHP 8.5 syntax passed for all 14 changed/new PHP files. The POS route subset, Arabic/English JSON parsing, and `git diff --check` passed.
- Per the standing current-task directive, no new automated test file was created and no PHPUnit suite was run. No database, migration, seeder, browser, Composer, Vite, production, backup, tag, push, or remote operation ran.

## 2026-09-05 — v0.1.22-hotfix13 focused verification

- Pure database-free PHPUnit regression: `tests/Feature/V0122Hotfix13CashDrawerIntegrityTest.php` passed 4 tests / 43 assertions covering retained valid shift opening, store-derived ownership and mismatch rejection, deterministic legacy backfill/fail-closed reporting/constraints, invalid-selector exclusion, and company/branch/store scope enforcement.
- The preserved Hotfix12 shift-route regression passed database-free with 1 test / 24 assertions using locale `zz` and a disposable process-only application key. The first command named the nonexistent `phpunit.xml`; the corrected configuration then reached 19 assertions before identifying the missing isolated application key. Neither setup failure accessed a database.
- Changed PHP syntax passed for the 10 final changed/new PHP files. After the test harness was converted to pure PHPUnit, that test file was linted again and passed.
- The exact `pos.shift` GET, `pos.shift.open` GET, and `pos.shift.store` POST route registrations passed a database-free router probe. Arabic and English JSON parsing and `git diff --check` passed.
- One initial combined attempt reported 5 errors / 0 assertions because the standard Laravel test/route harness tried to query `translation_overrides` through the deliberately unreachable MySQL endpoint. It did not connect to or mutate any database. The Hotfix13 suite was then made framework-boot-free; no application behavior changed for this harness correction.
- No migration, seeder, database, Composer, Vite, browser, broad test suite, production path, service, active symlink, tag, push, or remote operation ran.

## 2026-09-04 — v0.1.22-hotfix10 focused verification

- PHP 8.5.9 syntax passed for all five changed PHP/config/test files. Blade `view:cache` passed using the documented translation database-outage fallback, query observability disabled, an intentionally invalid database driver, task-local compiled-view output, and no `.env`.
- Two earlier fail-closed Blade attempts stopped before compilation: first at the query-observability listener and then at the translation override loader under `APP_ENV=testing`. Neither connected to a database or changed data.
- Arabic and English JSON parsing, focused source contracts, diff hygiene, absence of frontend-source changes, and the `storage/storage` guard passed.
- `public/build/manifest.json` SHA-256 is `b0d6e69cb067407c8ecc07e72eed30f06fb88a4050a3bd55f4f526c5c20606df`; all 12 Vite references are regular, non-symlink, non-empty files.
- `public/build/fonts-manifest.json` SHA-256 is `66edf17c93351fe01158be7414a441a762f106417c74351881876b405b8b10ca`; all 7 unique font-manifest and embedded CSS references are regular, non-symlink, non-empty files.
- No Vite build ran because application frontend sources and both dependency lockfiles are unchanged from the verified Hotfix9 source/build identity.
- Focused PHPUnit and Arabic desktop, English desktop, and Arabic mobile browser automation were requested but blocked by the execution policy before launch. No test/browser pass, console-error result, or overflow result is claimed.

## 2026-09-03 — v0.1.22-hotfix8 focused pass

- Hotfix7 regression contract passed 5 tests / 26 assertions after updating only its release-version assertion. A combined historical Hotfix4/Hotfix7 run passed 9/10; the sole failure is the documented obsolete Hotfix4 sidebar CSS source-string expectation.
- PHP syntax, Blade compilation, Arabic/English JSON, Vite 8.2.0 production build, diff hygiene, and authenticated Arabic/English desktop plus Arabic 390px balances checks passed. Browser console errors and horizontal overflow were zero.
- Disposable MariaDB `toyjoy_hotfix8_verify` applied migrations through 000106. No Hotfix8 migration or index was added.

## 2026-09-03 — v0.1.22-hotfix7 focused pass

- Hotfix7 PHPUnit passed 5 tests / 26 assertions. PHP syntax, Blade compilation, both locale JSON files, focused routes, diff hygiene and the Node 20 Vite production build passed.
- Fresh disposable MariaDB applied all migrations; migration `2026_09_03_000106_add_inventory_count_sessions` passed down/up, creating four session tables and nine permissions.
- Mandatory reconciliation passed: opening 10, counted 10, sale-after-cutoff -2, expected 8, normalized 8, variance 0; transfer lock rejected the counted location.
- Two parallel counters produced exactly two contributions totaling 2; request replay remained idempotent. Product-code and model searches used `products_item_code_unique` and `prod_model_status_idx` under EXPLAIN.
- Authenticated Arabic/English desktop and Arabic 390px checks covered balances, stores, count creation and active entry with zero console errors and zero horizontal overflow. Route query samples were balances 41, stores 93 on cold readiness-cache creation, count creation 41 and active counting 49; duplicates observed were shared session/translation/permission/layout loads, not unbounded product preload.
- A broader historical source-contract selection reported 26/33 passing: failures were obsolete Hotfix4/Hotfix6 version/CSS expectations and pre-existing missing M54 fixture/view contracts, not runtime failures. They are not represented as passing.

## 2026-09-03 — v0.1.22-hotfix6 focused pass

- Focused PHPUnit passed 4 tests / 34 assertions with zero errors or failures. Changed PHP syntax, Blade compilation, Arabic/English JSON parsing and diff hygiene passed.
- Authenticated isolated-browser verification passed Arabic desktop with all Basic Data groups expanded and the mobile drawer; 21 unique leaves appeared in category counts 5/5/11, exact setup links and active expansion passed, and no horizontal overflow or browser errors occurred.
- Arabic and English Audit Logs exposed no tested raw event keys, model class names or configuration key; the combined store create wording passed. Isolated MariaDB `rajeh_hotfix6_verify` reports migrations through 000105 Ran; Hotfix6 adds no migration.
- Shared-layout `/settings/profile` retained 5 queries and zero duplicates. Five-sample median was 140.345 ms versus Hotfix5 evidence 136.458 ms (+2.8%); app-only median improved from 130.258 to 124.455 ms. Node 20.20.0 Vite build passed after the desktop sidebar stylesheet correction; manifest SHA-256 is `baed65ad827593d44cbc8352a62c8807ea1a95b924945fbade1b68852302bb3c`.

## 2026-09-03 — v0.1.22-hotfix5 corrective no-regression pass

- Only Products, Pricing and Unpriced were re-benchmarked against exact Hotfix4 under identical isolated conditions. All three corrected medians, p95 values, query counts and SQL medians improved; see `.ai/evidence/v0.1.22-hotfix5/CORRECTIVE.md`.
- Affected PHPUnit passed 9 tests / 88 assertions with zero errors, failures or skips after explicitly supplying the translator dependency in the readiness test harness.
- Four affected PHP/migration files passed syntax. Migrations 000104/000105 report Ran, the harmful product index is absent, and diff checks passed. No broad, visual or PDF checks were repeated.

## 2026-09-02 — v0.1.22-hotfix5 performance

- Exact-baseline/candidate benchmark covered 20 authenticated routes with one warm-up and five samples each on the same isolated MariaDB fixture; full results and method are in `.ai/evidence/v0.1.22-hotfix5/PERFORMANCE.md`.
- Focused regression passed 5 tests / 55 assertions. The separate legacy readiness unit file has one pre-existing translator-bootstrap error and 3 passing tests / 25 assertions; no broad suite ran.
- Changed PHP syntax, Blade/config/route/event cache compilation, Arabic/English JSON, reversible migration up/down/up, MariaDB query plans, 20-route Arabic desktop journey, POS search, mobile navigation, and diff checks passed.
- Browser result: 20/20 HTTP 200, 20/20 RTL, zero console/page errors, zero overflow; mobile drawer opened without overflow.

## 2026-08-27 — M50A.1 focused local visual verification

- Owner-authorized local Playwright rendering only; no automated application test suite was run.
- Ten evidence cases: Arabic/English 1440px dashboard, 390px/360px mobile dashboard, Arabic mobile drawer, English collapsed sidebar tooltip, Arabic Inventory module, English Sales & POS navigation, 768px tablet dashboard, and 1024px header quick-create state.
- Result: zero serious/critical Axe violations, zero horizontal document overflow, zero external requests, one footer per case, and no unlabeled icon-only controls.
- Cairo proof for all Arabic cases: `lang=ar`, `dir=rtl`, local Arabic and Latin WOFF2 responses HTTP 200, `document.fonts.check()` true for 400/600/700, Cairo-first computed styles on body/sidebar/header/KPI/footer, and Cairo Arabic canvas metrics distinct from Arial fallback.
- Evidence: `/home/rajeh/worktrees/toyjoy-release-artifacts/m50a1-evidence-20260827/verification.json` and the ten PNG captures in the same directory.

## 2026-08-30 — M52 focused verification

- Owner-authorized focused PHPUnit passed 11 tests and 33 assertions. No broad suite ran.
- Isolated task-local MariaDB migration 000097 completed in batch 2 and created all three Opening Inventory tables.
- Genuine authenticated evidence: 10 Arabic/English desktop/mobile cases passed with zero serious/critical Axe violations, zero document overflow, no console errors or external requests, and local Cairo loaded.
- Arabic product, category, and brand PDFs embedded subsetted Cairo-Regular only; no DejaVu reference was present, and all three rendered pages were captured as PNG.

## 2026-08-31 — M53 focused verification

- Owner-authorized focused PHPUnit passed 6 tests and 28 assertions for formula/rounding, protected List 0, dated overrides/removal, outlet resolution, snapshot integrity, authorization, and Setup reasons. No unrelated suite ran.
- Isolated task-local MariaDB migration 000098 completed successfully and created the override table, list metadata, assignment foreign keys, and permissions.
- Genuine authenticated evidence covered Pricing Lists in Arabic desktop and English mobile, Arabic Product Card pricing matrix, English outlet assignment, and Arabic mobile Setup readiness. All returned HTTP 200 with correct direction, local Cairo on Arabic pages, zero serious/critical Axe violations, overflow, console errors, or external requests.
# 2026-08-31 — M54 focused verification

- Focused PHPUnit: 5 tests / 29 assertions passed.
- Covered posting transaction/idempotency, exact-distribution enforcement, price resolution, permissions, and two-stage/label surfaces.
- Migration `000099` passed on disposable MariaDB `rajeh_m54_20260831`; no shared database was used.
- No broad automated suite was run.

# 2026-08-31 — v0.1.21 Final Acceptance

- Focused PHPUnit passed 12 tests / 95 assertions after correcting the obsolete icon expectation exposed by the first focused run.
- Isolated MariaDB `rajeh_v021_uat_20260831`: initial seed created 44 marked records; repeat seed reused the same batch; dry-run purge reported those 44 records; confirmed purge removed them; reseed created a new 44-record batch.
- Golden Path integrity: exactly one Draft, Awaiting Distribution, Approved, and Reversed invoice; receiving remainder `0.000000`; two destination price snapshots.
- The journey exposed and verified a fix for stale pending approval source versions after distribution.
- Node 20.20.2 Vite build passed; manifest SHA-256 `4d41c4fce936e8ba68f79ec69c89c36178f4481a8fa660774cce61de4894fe3f`. The host-default Node 16 attempt failed before compilation and changed no application source.

# 2026-08-31 — v0.1.21 Purchasing Localization/Layout Hotfix

- Directly related tests: initial pass 15/16; corrected the sole over-broad test assertion, then the hotfix file passed 4 tests / 249 assertions.
- Eight task-local visual cases covered list/create, Arabic/English, and 1440px/390px. All passed mixed-language, raw-enum, clipping, overlap, and document-overflow checks; representative captures were visually inspected.
- One Node 20.20.2 build passed. No database, broad suite, PDF, Artisan, production, or remote operation ran.

# 2026-08-31 — v0.1.21 Purchasing Search/Barcode Hotfix 2

- Directly related test pass completed 32/33; corrected its sole outdated search-contract assertion, then the affected file passed 4 tests / 33 assertions.
- Six task-local bilingual visual cases covered Purchase Invoice create and Product Card create/edit. All passed mixed-language, exposed-variable, and horizontal-overflow checks; representative Arabic Product Card edit and English Purchase Invoice create captures were visually inspected.
- No database cycle, broad regression, PDF, Artisan, migration, UAT execution, production, or remote operation ran.

# 2026-08-31 — v0.1.21 Hotfix 3 Readiness/Pricing/UAT Consistency

- Focused readiness and retained-Hotfix source tests passed after correcting the new test harness path; no application defect was exposed by the first pass.
- Sixteen task-local bilingual desktop/mobile visual cases passed mixed-language, exposed-variable/raw-state, and horizontal-overflow checks; two representative captures were inspected.
- One Node 20.20.2 Vite build passed; manifest SHA-256 `cc095c1ecdfd4690ed5e500b18c78c63d74043ae0933e548c72f5111a92fdf78`.
- No database, Artisan, migration, seed, purge, broad suite, PDF, production, tag, push, or remote operation ran.

# 2026-09-01 — v0.1.22 cumulative stabilization final verification

- Focused grouped PHPUnit: 70 tests, 692 assertions, all passed across the 11 named V016/M51–M54/V021/V022 files; no broad suite ran.
- Node runtime: `/opt/cpanel/ea-nodejs20/bin/node` v20.20.2 and `/opt/cpanel/ea-nodejs20/bin/npm` 10.8.2. One production Vite build passed using the mandated Node 20 PATH.
- Printing visuals: eight local Playwright fixture cases passed for Arabic/English, desktop/mobile, and template-library/printer-scope families; correct RTL/LTR, no mixed-language leakage, no document overflow, and minimum 44 px controls. Four representative captures were visually inspected.
- Isolated MariaDB: complete 96-migration chain passed in a temporary user-owned socket-only MariaDB. Migration `000102` column, FK, composite uniqueness, rollback and remigrate passed. A first rollback exposed MariaDB error 1553 and the down migration was corrected; the fresh repeat passed. The earlier `000101` run verified schema, FKs, uniqueness and preservation of legacy `template_name` through rollback/remigrate.
- Cleanup: both exact temporary databases, MariaDB processes and data directories were removed. No production/shared database or symlink target was accessed.
# 2026-08-31 — v0.1.21 Final Stabilization

- Focused M54 PHPUnit: passed 8 tests / 62 assertions.
- Focused route pass: purchasing-invoice route subset registered successfully.
- Isolated Blade/Flux render probe: passed for the six requested UI contracts and all scoped M54 Flux components/icons.
- No database, migration, broad suite, frontend build, PDF, browser-control, or unrelated verification ran.
# 2026-09-01 — v0.1.22-hotfix1 post-deployment focused gate

- `tests/Unit/V022Hotfix1PostDeploymentTest.php`: 4 passed, 59 assertions.
- `tests/Unit/V0122Hotfix2CorrectiveTest.php`: 4 passed, 26 assertions. Isolated authenticated browser evidence: Product Card save/reload `25.00`, readiness ready, EAN-13 `4006381333931` decoded twice, Code 128 `TESTLOCAL0001` decoded once, no-barcode state preservation, invoice canonical redirect, 50×30 mm thermal and A4 visuals; no console errors/overflow. Disposable MariaDB only (97 migrations); no shared or production data.
- Changed/new PHP syntax, Arabic/English JSON parsing (`5541`/`2360` keys), and `git diff --check`: passed.
- Barcode assertions: valid EAN-13 checksum/pattern output and Code 128-B start/data/checksum/stop SVG structure passed for `4006381333931` and `TJ-ABC-001`.
- Preserved visual evidence: 8 previously completed Arabic/English desktop/mobile printing cases; not repeated under the one-pass rule.
- Node 20.20.2/npm 10.8.2 Vite 8.2.0 build: passed. Manifest SHA-256 `cc095c1ecdfd4690ed5e500b18c78c63d74043ae0933e548c72f5111a92fdf78`.
- Database: no migration executed in this correction pass. Production/shared databases were not accessed. Physical-printer acceptance remains outstanding.
# 2026-09-02 — v0.1.22-hotfix3 post-review closure

- Focused correction test passed 9 tests / 143 assertions with zero failures.
- Authenticated isolated-browser verification passed 3 real-route PDFs and 11 Arabic desktop/mobile route cases. Raster decoding passed 3/3 Code 128 copies and 1/1 thermal plus 1/1 A4 EAN-13 copies; all generated pages were rasterized. Zero console errors and zero horizontal document overflow were recorded.
- Disposable MariaDB `rajeh_hotfix3_verify` reports all 97 migrations ran. PHP syntax, Blade compilation, Arabic/English JSON, and diff checks passed once. No broad suite or repeat Vite build ran.
# 2026-09-03 — v0.1.22-hotfix9 focused verification

- PHPUnit: 12 tests, 78 assertions passed across Hotfix9 navigation cleanup plus Hotfix6/Hotfix7 preservation contracts.
- PHP syntax passed for every changed PHP/config/Blade/test file; Blade cache compilation passed; Arabic and English locale JSON parsed successfully; `git diff --check` passed.
- Vite 8.2.0 production build passed with Node 20; `public/build/manifest.json` SHA-256: `b0d6e69cb067407c8ecc07e72eed30f06fb88a4050a3bd55f4f526c5c20606df`. The host-default Node 16 attempt failed before asset emission because Vite 8 requires a newer Node runtime.
- Disposable MariaDB database `toyjoy_hotfix9_verify` migrated through `000106`. No Hotfix9 migration exists or was required. Demo seeding stopped on its pre-existing supplier-settlement fixture incompatibility after the canonical production seed had completed; this did not affect the focused authenticated checks.
- Authenticated Chromium passed Arabic and English desktop sidebars, Arabic mobile drawer, 20-card Setup Center, and Pricing Lists setup context with one retained top pricing summary. Results: zero console/page errors, zero horizontal overflow, no Party/System Settings/duplicate administration/footer-profile navigation, and all five Essentials leaves plus Roles & Permissions present.
- Isolated route profiling recorded dashboard 89 queries, Pricing Lists setup 76, Initial Setup 72, and inventory balances 13. Hotfix9 adds no query-producing sidebar code; it removes the Party policy/booking readiness reads from source and removes the page-level duplicate pricing-readiness consumer. Inventory route source is unchanged from Hotfix8.

# 2026-09-03 — v0.1.22-hotfix9 view-cache correction

- PHP 8.5.9 `optimize:clear` passed with task-local `CACHE_STORE=file`, `SESSION_DRIVER=file`, and `QUEUE_CONNECTION=sync` overrides; the worktree has no `.env`, and the initial unoverridden attempt failed by defaulting the cache store to unavailable `root@localhost` database credentials.
- PHP 8.5.9 `view:cache` passed with the same isolated overrides after the pricing-label view was decomposed into seven bounded partials. No vendor, Livewire compiler, PCRE-limit, or environment configuration change was made.
- Preserved authenticated Chromium artifact covers Arabic RTL and English LTR pricing labels, Pricing Lists and navigation: 20 unique setup leaves, Party deferred, one readiness summary, zero console/page errors and zero horizontal overflow.
- No automated suite or repeated frontend build was run for this correction. No production/shared runtime or database was accessed, and `storage/storage` remained absent.


## 2026-09-06 — v0.1.22-hotfix16 focused verification

- The single authorized database-free source-contract file completed with passing results for all four methods. The initial consolidated run passed three methods and exposed one over-specific status-scope assertion; only that method was corrected and rerun, ultimately passing 1 test / 28 assertions.
- All changed/new PHP files passed PHP 8.4 syntax. The four changed Blade templates compiled through Laravel's registered Blade/Flux component environment with a verification-only file translation loader and each compiled file passed PHP syntax.
- Database-free route registration found 45 POS routes, all required Hotfix16 canonical names, and zero duplicate POS route names. The failed expectation used stale names and inspection also removed one unused customer-search closure capture before the route-only rerun passed.
- Arabic and English JSON parsed; all 133 newly added English Hotfix16 keys have present, nonblank Arabic counterparts and no unintended identity values. Git diff hygiene passed.
- No Vite input changed, so no build ran. No broad suite, database, migration, browser, HTTP, production access, deployment, backup, service, tag, push, or remote operation ran.

# 2026-09-06 — v0.1.22-hotfix17 focused verification

- One consolidated database-free pass completed successfully from exact Hotfix16 source commit `31883f5c823458d30738708ec96e1d02337f4c9b` on the required Hotfix17 branch.
- Syntax passed for all 17 changed/new non-Blade PHP files under available PHP 8.5.9. All changed/new Blade templates compiled and their compiled PHP passed syntax.
- Required POS/refund route contracts, duplicate-name guard, compact desktop/order/customer/product/payment/checkout contracts, refund authorization/quantity/payment/locking/idempotency/inventory/shift/receipt contracts, Arabic/English JSON parity, and diff hygiene passed.
- No frontend input changed, so Vite was not run. No broad suite, browser, database, migration, production/live HTTP, service, backup, deployment, activation, tag, push, or remote operation ran.

# 2026-09-07 — v0.1.22-hotfix18 focused verification

- The real Laravel Blade compiler with registered Flux and Livewire extensions compiled every application Blade template, and all compiled PHP passed syntax; the repaired POS view passed explicitly with no literal Blade directive remaining.
- Changed PHP syntax, Arabic/English JSON parsing, POS/refund route registration, duplicate-name detection, Git diff hygiene, and both operator scripts’ Bash syntax passed. Hotfix18 changes no locale key or value.
- The existing database-free route harness supports route registration and middleware contracts but not authenticated POS rendering without persisted POS context. No route-render PASS is claimed and no database was accessed.
- No frontend input changed, so Vite was not run. No broad suite, browser, Composer, database, migration, production access, backup, deployment, activation, service, tag, push, sudo, or remote operation ran.


# 2026-09-07 — v0.1.22-hotfix19 focused verification

- Result: PASS for all checks available as `rajeh_codex2`; host-user `rajeh` cache proof is deferred to activation.
- The real registered Laravel/Flux/Livewire compiler compiled all 231 application Blade templates. Static checks passed for all four Hotfix17/18-changed POS Livewire roots, and the closed refund wizard passed Livewire root insertion.
- The changed refund template compiled PHP passed PHP 8.5 syntax. The initial database-free harness produced zero compiled files because testing mode rethrew the expected unavailable translation database; only that affected check was corrected with Laravel file translations and rerun. No database connection succeeded or mutation occurred.
- Final archive/manifest integrity, operator-script Bash syntax and fail-closed safety assertions, and Git diff hygiene passed. Host-user `rajeh` ownership and cache generation were not impersonated; activation performs and requires that proof before symlink switching. No broad suite, browser, Composer, Vite, database, production access, migration, deployment, activation, tag, push, sudo, or remote operation ran.

# 2026-09-08 — v0.1.22-hotfix21 focused verification

- Server and browser payment contracts passed the required `25/20`, `25/25`, and `25/1000` cases plus split, zero, negative, malformed, duplicate, and overallocated validation.
- The deterministic multi-page sidebar contract preserved lower-menu scroll position and expanded/active ancestry without resetting an already-visible active item; RTL/collapsed/mobile boundaries passed source and compiled-asset checks. No headed-browser visual claim is made because no executable was available.
- Laravel Blade compilation produced 886 compiled PHP files and compiled syntax passed. The affected Livewire checkout retained one permanent root and a keyed total-refresh contract.
- Exactly one Vite build passed. Manifest `1618bfbf5e4ebe3830db4f18bc435648d5f067cb03a24a1c4fe6b7ccab67d71c`; app JS `7173aa54896453aa9eb94569c2e60d07d93701fc447d8f9dbb60affa1aa523e1`; app CSS `bd7cfb054cd76cec89861bce602db64d6beaa670122149ea31337ab6520ba24a`.
- Locale JSON, affected PHP syntax, referenced-asset integrity, and Git diff hygiene passed. Harness-only selector and Vite-entry assumptions were corrected without changing application code or rebuilding.
## 2026-09-08 — v0.1.22-hotfix22 focused verification

- The runtime navigation contract passed for real configured navigation under cashier, manager, and administrator permission profiles; the real keyless Initial Setup item; deterministic unique fallback keys; nested groups; headings; separators; empty optional fields; and an authenticated database-free render of the actual dashboard layout.
- The initial contract run exposed only fixture-construction and harness API/database-isolation issues. Each was corrected in the evidence harness, and only the affected navigation runtime contract was resumed until it passed. No application defect was exposed after the normalization change.
- Preserved Hotfix21 server/browser cash and split-payment contracts and deterministic multi-page sidebar restoration passed. All application Blade templates compiled, all compiled PHP passed syntax, affected Livewire root contracts passed, both locale JSON files parsed, and the single new Vite build's manifest/referenced assets passed integrity checks.
- No broad automated suite, headed browser, database, migration, production access, deployment, tag, push, sudo, or remote service was used.

## 2026-09-08 — v0.1.22-hotfix23 focused verification

- **Result:** PASS using the owner-authorized deterministic layout fallback; browser visual acceptance unavailable.
- **Layout/navigation:** Actual authenticated database-free Laravel dashboard rendered with real normalized navigation and replacement assets. Exactly one internal sidebar scroller exists; Hotfix20 grid geometry, bounded internal active reveal, zero document/body scroll change, RTL/LTR, collapsed desktop, and mobile contracts passed.
- **Preserved behavior:** Hotfix22 keyless navigation profiles and Hotfix21 cash/split-payment contracts passed.
- **Compilation/integrity:** 886 compiled Blade PHP files, affected PHP, Livewire roots, locale JSON, replacement Vite manifest/references, and Git diff hygiene passed.
- **Assets:** manifest `5d171d1aae53e952aa727a1093dcf427d0366a881481e713caaa371e67243687`; JS `app-DGJ2yKaj.js` `988e8ee9c13d186c4d3692ed1b7a3cf9680ba8897e2b4ec5272f2646b58392e5`; CSS `app-DWxi_J5Q.css` `9d49b148f0775f0d0b8b104f77c6fa39ea6910c14dfac8c93cbcdd067d371228`.
- **Boundary:** The one authorized Chromium download failed with ENOSPC and was not retried. No screenshot or visual PASS is claimed.
# 2026-09-09 — v0.1.22-hotfix24 focused final verification

- PASS: Phase 1 purchase-invoice quick Product Card source contract, required/duplicate validation, supplier prefill, transaction boundary, created-product insertion, and draft-state preservation.
- PASS: description placement, Arabic cost label, compact authenticated layout/Reports Center, report selector/filter/export preservation, and affected Arabic/English translations.
- PASS: Hotfix23 sidebar layout/restoration runtime and asset contracts; cash/split-payment contract; unchanged POS/refund/inventory source boundary.
- PASS: PHP syntax, Blade compilation/compiled syntax, Vite asset integrity, locale JSON, Git diff hygiene, and operator Bash syntax.
- Exactly one clean Vite production build ran. Automated tests and browser checks were not created or run. No database or migration command ran.
# 2026-09-09 — v0.1.22-hotfix25 focused verification

- PASS: PHP syntax for the changed controller, POS Livewire class, version, and route; full Blade cache compilation; both locale JSON files; and Git diff hygiene.
- PASS: bilingual runtime rendering of the reusable combobox, exact named-route/controller binding, and final compiled keyboard asset (`app-CYFvxL4g.js`, SHA-256 `461db02ecf0d732da400cf6230deb73d18171c26e14854553685e7445deb4b33`).
- PASS: representative local browser contracts for purchase-order exact barcode selection, selection-to-quantity focus, quantity-to-cost focus, live total, single automatic next row, repeated-Enter de-duplication, multiple-result arrow/Enter selection, inventory automatic next row, purchase-invoice price-to-final-field progression, Escape, and prevention of parent-form submission. A separate Arabic RTL run passed direction and Arabic no-results text. No screenshot was taken.
- PASS: one successful clean Node 20.20.0 / Vite 8.2.0 production build. An initial invocation selected host Node 16.20.2 and failed on missing `node:util.styleText` before Vite compilation or asset emission; it was corrected using the already-installed Node 20 binary, with no package installation.
- Existing focused source tests: 23 executed, 15 passed, 7 failed, 1 errored, 197 assertions. The non-passing contracts are stale Hotfix24/baseline expectations: removed legacy invoice-search markup, old version markers, unrelated old POS markers, and a pre-existing missing purchasing fixture. Tests were not edited or broadened to manufacture a pass.
- No database or migration command ran. Migration 000111 remains an activation precondition and must already be `Ran`; this local session did not query production or claim live migration status.

# 2026-09-10 — v0.1.22-hotfix26 focused verification

- PASS: changed PHP and Blade syntax; full registered Blade cache compilation (927 views); Arabic and English locale JSON; Git diff hygiene.
- PASS: dependency-free JavaScript behavior contract, 3 tests / 3 passed, covering incremental narrowing, duplicate-request suppression, stale-response rejection, exact barcode/external-scanner Enter, Arrow Up/Down, Enter, Escape, parent-submit prevention, row progression, camera unsupported fallback, and camera-track cleanup.
- PASS: focused existing PHPUnit unit contract, 3 tests / 17 assertions; bilingual purchasing lookup runtime render, named authorized search route, and compiled Vite asset contract. Final app JavaScript SHA-256: `c592a88f1ccd285323f3cea676d45dce14055e03292ac886fbb4e2e2af8acb32`.
- PASS: exactly one clean Node 20/Vite 8.2.0 production build after frontend edits.
- NOT RUN: the new database-backed purchase order/invoice/return lookup feature test. Both available local MariaDB identities rejected authentication and no authorized isolated database credentials were present; no database was created, queried, migrated, or mutated.
- INFO: an optional focused PHPStan attempt did not complete cleanly: `/tmp` cache capacity and the default 128 MB worker limit were corrected, then analysis reported existing Eloquent relation-inference findings plus joined-column inference in the new query. No PHPStan PASS is claimed.
- Migration status: no Hotfix26 migration exists or ran. Migration 000111 was not queried locally and remains a mandatory already-`Ran` activation precondition.

# 2026-09-10 — v0.1.22-hotfix27 focused verification

- PASS: changed PHP/Blade syntax, 721 registered Blade cache files, Arabic/English locale JSON, named route registration, and Git diff hygiene.
- PASS: dependency-free JavaScript behavior contract, 3 tests / 3 passed, covering continuous narrowing, duplicate/stale response protection, no implicit first-result Enter selection, Arrow Up/Down plus Enter, Escape, exact unique external-scanner selection, parent-submit prevention, row progression, and camera fallback/cleanup.
- PASS: bilingual rendered input geometry (`dir=auto`, logical start/end padding, fixed camera area), full-page create route reuse and `purchase_orders.create` middleware, adjacent localized navigation item, and compiled Vite asset contract. Final app JavaScript SHA-256: `b06582b6a9bf032f7d37ad6e94dffc3936a07300e7239c75c960f899efeeb5f7`.
- PASS: exactly one clean Node 20/Vite 8.2.0 production build after the functional frontend edits.
- BLOCKED BEFORE DATABASE ACCESS: the updated focused MySQL feature contract (4 tests) could not authenticate to dedicated database `toyjoy_testing` because no authorized local credentials exist in the isolated worktree. It made zero assertions, fixtures, migrations, or database changes; no rerun was attempted.
- Migration status: no Hotfix27 migration exists or ran. Migration 000111 was not queried locally and remains a mandatory already-`Ran` activation precondition.

# 2026-09-10 — v0.1.22-hotfix28 focused verification

- PASS: affected PHP/runtime-verifier syntax and full Blade cache compilation (715 compiled views).
- PASS: purchase-order overview/create named routes, shared Livewire action, permission middleware, toolbar destination, adjacent bilingual sidebar item, no legacy create-modal trigger, and fixture runtime contract.
- PASS: unchanged product-search behavior contract, 3 tests / 3 passed, covering incremental/stale/duplicate behavior, multi-match Enter prevention, arrow selection, exact scanner selection, row progression, parent-submit prevention, and camera cleanup.
- PASS: Arabic/English locale JSON, Git diff hygiene, and Hotfix27 Vite artifact identity (`manifest.json` SHA-256 `73073894ef9dfca13d6038ccfed8ee7905aa7fc91a38c795b86ac9872277c1b7`; app JavaScript SHA-256 `b06582b6a9bf032f7d37ad6e94dffc3936a07300e7239c75c960f899efeeb5f7`). No Vite build ran because no frontend source changed.
- ACTIVATION-GATED: authenticated production-shaped Arabic/English HTTP 200 checks for `/purchasing/orders` and `/purchasing/orders/create` require the real environment and runtime user `rajeh`. Activation runs them before and after switching and rolls back to exact Hotfix26 on any failure.
- Migration status: no Hotfix28 migration exists or ran. Migration 000111 was not queried locally and remains a mandatory already-`Ran` activation precondition.

# 2026-09-11 — v0.1.22-hotfix29 focused verification

- PASS: PHP 8.4 syntax for `ApplicationVersion` and the runtime verifier; one Blade cache compilation produced 887 compiled PHP views.
- PASS: Arabic/English locale JSON; bilingual lookup rendering uses explicit `rtl`/right and `ltr`/left alignment, with the camera control as an adjacent button outside the input container.
- PASS: 4/4 dependency-free JavaScript behavior tests. The real component lifecycle fixture proves a Livewire child morph rebinds the full-page lookup, typing renders multiple visible results without an empty hidden-model request, narrowing rejects stale/duplicate responses, multi-match Enter does not select, Arrow/Enter selects explicitly, exact unique identifiers select, parent submission is stopped, and camera tracks close.
- PASS: named overview/create route reuse and permission contracts, sidebar placement, full-width/compact/sticky layout contract, compiled Vite asset identity, and Git diff hygiene. Built app asset `assets/app-Cjfoc2bG.js` SHA-256 `280a711a347c1a3983cb5b83b9c57858a889c4cdf992d2b77d822cef7bde5a57`; Vite manifest SHA-256 `0de9cdb3f77b3f4f7ef71472c29ce37806cafc59d024cd05364c93b7b25e9fea`.
- PASS: one successful clean Node 20.20.2/Vite 8.2.0 production build. Three preceding environment-resolution attempts emitted no usable build (missing isolated `node_modules`, then config dependencies, then isolated `vendor`); no package was installed, and the final build started from an empty build directory using unchanged shared dependencies.
- ACTIVATION-GATED: authenticated Arabic/English HTTP 200 checks for `/purchasing/orders` and `/purchasing/orders/create`, plus authorized supplier-product partial-search JSON, require the real release environment. The verifier uses array cache/session state and an always-rolled-back transaction; activation runs it before and after switching.
- Migration status: no Hotfix29 migration exists or ran. Migration 000111 was not queried locally and is a mandatory already-`Ran` activation precondition.

# 2026-09-11 — v0.1.22-hotfix30 focused compiled-browser verification

- PASS: PHP 8.4 syntax for the touched version/runtime PHP files; one Blade compilation produced 887 compiled views; Arabic/English locale JSON passed.
- PASS: exactly one clean Node 20.20.2/Vite 8.2.0 production build. Compiled app asset `assets/app-DFNxkatv.js` SHA-256 `b9a4bf0238e0ea37899858e7c448c4e1f9b7c7a689724618301180132da750f5`; Vite manifest SHA-256 `daecb4bae24237df2f581d13eecc6434950d8b9c0148a1d97c807e6620509809`.
- PASS: production compiled JavaScript executed against rendered purchase-order lookup markup. Real Arabic `منتج` and identifier `001` input events produced loading and visible multi-result states; narrowing, empty and localized request-error states passed; supplier-only/all-product request parameters passed.
- PASS: multi-result Enter remained non-selecting; Arrow/Enter populated product ID, supplier cost provenance, quantity-derived total, and quantity focus; exact unique scanner identifier selection passed.
- PASS: initial load, Livewire initialization/navigation, morph replacement, and dynamically added-row initialization remained single-bound; outside click/Escape closure and fixed body-overlay containment passed.
- PASS: Hotfix29 compact layout files are unchanged; static route/component/asset contract and `git diff --check` passed.
- ACTIVATION-GATED: real-environment authenticated Arabic/English HTTP 200 checks and the same compiled-browser contract against the complete authenticated `/purchasing/orders/create` response run before and after switching inside the rollback-safe verifier.
- Migration status: no Hotfix30 migration exists or ran. Migration 000111 remains a mandatory already-`Ran` activation precondition.
# 2026-09-11 — v0.1.22-hotfix31 focused verification

- PASS: touched PHP/Blade syntax, one Blade compilation, Arabic/English locale JSON, invoice rendered lookup directions, exact Arabic supplier-only label, compact invoice/price-list geometry, compiled asset identity, Git diff hygiene, and Bash syntax.
- PASS: compiled application JavaScript processed real `منتج` and `001` input events and exposed loading, multiple visible results, narrowing, empty and request-error states with supplier-only/all-product parameters.
- PASS: multiple-match Enter did not auto-select; Arrow/Enter selected the highlighted product, applied supplier/fallback cost, focused quantity, progressed through invoice line fields, created one next row, focused its product search, suppressed repeated Enter, and prevented parent invoice submission. Exact unique scanner selection and Escape closure passed.
- PASS: exactly one clean Node 20.20.2 / Vite 8.2.0 build; manifest SHA-256 `43ee8a205f5c4467d00ced437109f1478e86a67c4edf9aa3b24be79dba5cf11a`; app JavaScript SHA-256 `d3943fbaff090cbc93123b0597b173271fe4399a22d6a6cab40681e34c64e6b4`.
- ACTIVATION-GATED: authenticated Arabic/English HTTP 200 and production-shaped runtime verification execute before and after activation. No database or migration was accessed locally; migration 000111 must already be `Ran` and no migration is executed.

# 2026-09-11 — v0.1.22-hotfix32 focused verification

- PASS: corrected fixture runtime verification rendered one exact Arabic supplier-only editor label and no exact label text inside two lookup controls, then exercised the unchanged compiled search/keyboard runtime and emitted the required terminal PASS marker.
- PASS: real `منتج` and `001` input events, visible dropdown lifecycle, supplier-only/all-product requests, multi-match keyboard selection, exact scanner selection, line creation/next focus, repeated-Enter suppression, and parent-submit prevention remain intact in the reused Hotfix31 compiled asset.
- PASS: isolated forced verifier failures proved pre-switch no-switch, post-switch exact Hotfix31 restoration, and absence of `ACTIVATION=SUCCESS` after either failure. Temporary paths only were used.
- PASS: touched PHP syntax, Bash syntax, exact reused manifest/asset identity, immutable archive/manifest integrity, executable script modes, and Git diff hygiene. Blade and locale files were unchanged; Vite build count is 0.
- INFO: two initial fixture invocations under an inappropriate testing environment failed closed before runtime checks because the unavailable translation database was deliberately rethrown; the affected fixture was rerun with the production-shaped environment and passed. No database connection succeeded or mutation occurred.
- Migration status: no Hotfix32 migration exists or ran. Migration 000111 remains a mandatory already-`Ran` activation precondition; activation executes no migration.

# 2026-09-12 — v0.1.22-hotfix35 concise verification

- PASS: PHP syntax, `ar-EG` JSON/PHP key and protected-token parity, unchanged `ar`/`en`, Hotfix34 cached controller locale route, exact `lang`/RTL/Cairo and three-way switching contracts, Egyptian greeting and localized alert/source presentation contracts, exact reused Hotfix34 Vite assets, no migration diff, immutable package/manifest integrity, Bash syntax, isolated exact-Hotfix34 fail-closed rollback, and Git diff hygiene.
- ACTIVATION-GATED: the unexecuted activation script requires authenticated HTTP 200 rendering of dashboard, alerts, approvals, sales, purchasing, and inventory routes in `ar`, `en`, and `ar-EG` before and after switching. Its verifier uses an always-rolled-back transaction and reports no database mutation.
- Migration status: Hotfix35 adds and executes no migration. Migration 000111 was not queried locally and must already be `Ran`; activation executes no migration.
- Vite build count: zero; no Vite input changed and exact Hotfix34 compiled assets are reused.

# 2026-09-13 — v0.1.22-hotfix36 concise release verification

- PASS: PHP/locale syntax, 6,115 JSON-key and 244 PHP-leaf parity, protected tokens, nonempty values, zero documented corruption markers, and representative Egyptian dashboard/products/inventory/sales/purchasing/alerts/approvals wording.
- PASS: exact unchanged Hotfix35 `ar`/`en`, routes, business/query/permission/search/keyboard/accounting/database boundaries, RTL/Cairo/switching contracts, and compiled Vite assets; build count zero.
- PASS: archive/complete-manifest integrity, root-script Bash syntax and static fail-closed safety contract, and Git diff hygiene. The operator scripts were not executed.
- ACTIVATION-GATED: authenticated `ar`, `en`, and `ar-EG` representative route rendering runs transactionally before and after switching. It was not locally executed without an authorized runtime database.
- Migration status: no migration was added, queried, or executed. Migration 000111 must already be `Ran` when activation starts.

# 2026-09-14 — v0.1.22-hotfix37 concise release verification

- PASS: changed PHP/Blade syntax, complete Blade cache compilation and compiled PHP syntax, exact `ar`/`en`/natural `ar-EG` locale JSON, and targeted database-free rendering for the contextual-help dialog and shared closed-by-default Filter/Search control.
- PASS: sidebar hover/focus/active contracts, single internal bottom scroller, saved scroll/expanded state across Livewire navigation, no-jump toggle handling, arrow-key behavior, unique canonical authorized destinations, Supplier-before-Customer ordering, and the Product Cards/Categories/Brands/Product Filters sequence.
- PASS: contextual-help trigger/dialog accessibility contracts (button, keyboard activation, close, backdrop, Escape, focus trap, initial focus and focus return); branch timezone and warehouse/POS shortcut removal; General Settings timezone and persisted list-display preference; user/role count-card removal with authorization code unchanged.
- PASS: exactly one successful Node 20/Vite 8.2.0 production build; final manifest SHA-256 `14ab2392f1214f12750ba4d841be4828d9da315013ed9c732fdb46cd1439ead0`, JavaScript SHA-256 `a9ca86aeb7e0ad1983173cc94f9dc6f9195f17a3d0a15333dccf38bd3ba891ea`, and CSS SHA-256 `06c494bb235b3aaaf790f4f9ca8730ce826faf5ca4d04a4eaa9af61190d0a0d6`. One earlier launcher attempt selected system Node 16 and stopped before Vite compilation or asset output.
- PASS: `git diff --check` and no-migration boundary. No automated suite or browser-control run was created or executed under the current directive.
- ACTIVATION-GATED: the optimized script checks migration 000111 is already `Ran` and performs one focused authenticated smoke render per `ar`, `en`, and `ar-EG` before switching. Activation executes no migration.
- Database status: no database command, connection, query, fixture, migration or mutation occurred during local implementation or verification.

# 2026-09-14 — v0.1.22-hotfix39 focused verification

- PASS: changed PHP syntax, complete Blade compilation, `ar`/`en`/`ar-EG` JSON parsing and required-key coverage, database-free Hotfix39 contract verification, Vite reference integrity, and Git diff hygiene.
- PASS: product Draft/Resume and five-identifier search contracts; separate supplier code; hidden product/customer/supplier distributed exports; hidden supplier payment terms; structured contact fields; opening header/line/import/totals/numbering/immutability source contracts.
- PASS: exactly one Node 20 / Vite 8.2.0 production build. Manifest SHA-256 `f5e7c91b350a37fa8b89fc368708e1398fe442662e1a22dfa224794efd93c7ec`; CSS SHA-256 `bff10abe3cea4c46bd6e9789adc4acb22025c28d08a6d0143afa6a596682642d`; JavaScript SHA-256 `a9ca86aeb7e0ad1983173cc94f9dc6f9195f17a3d0a15333dccf38bd3ba891ea`.
- ACTIVATION-GATED: authenticated `ar`, `en`, and `ar-EG` focused renders execute transactionally before the atomic switch. No automated suite, browser control, database connection, migration, or mutation ran locally.

# 2026-09-14 — v0.1.22-hotfix40 focused verification

- PASS: changed/new PHP syntax with PHP 8.4.24.
- PASS: `ar`, `en`, and `ar-EG` locale JSON and required new labels.
- PASS: task-local Blade compilation and compiled-view PHP syntax.
- PASS: database-free Hotfix40 workflow, receiving, numbering, PDF, report dataset, deduplication, snapshot and formula-safety contracts.
- PASS: focused purchase-order/export route discovery and Git diff hygiene.
- PASS: exactly one Node 20/Vite 8.2.0 production build; manifest SHA-256 `94c478524c18097f15deeaef3573794ab4e7b25d051e3a4c77f69dd8cfe5eff9`.
- NOT RUN by directive: migrations, database operations, automated suites, browser control, production/operator actions.

# 2026-09-14 — v0.1.22-hotfix41 focused final verification

- PASS: changed PHP syntax and one complete task-local Blade cache compilation with compiled PHP syntax.
- PASS: authenticated `/reports` returned HTTP 200 with the report marker and exact `lang` for `ar`, `en` and `ar-EG`; execution used array cache/session state inside an always-rolled-back database transaction.
- PASS: directly related report/export views contain no uncast `Stringable`, `str()` or `Str::of()` expression reaching `__()`, `trans()` or `@lang`.
- PASS: Hotfix41 changes no migration and `git diff --check` passed.
- Vite build count: zero; no Vite-managed frontend input changed and exact Hotfix40 compiled assets are retained.
- NOT RUN: broad legacy suites, browser control, database mutation, migration commands, promotion, activation or post-switch health checks.

## 2026-09-16 — v0.1.22-hotfix42 focused final verification

- **PASS:** The Hotfix42 focused verifier passed with no database mutation or migration execution. It confirmed the exact release version, both migration contracts and fail-closed downs, line discount/tax/inclusive calculations, legacy-header-tax preservation hooks, draft-code isolation, supplier-first locked allocation, camera-control removal, branch/outlet coexistence, exact migration scope, and populated `ar`, `en`, and `ar-EG` keys.
- **PASS:** PHP 8.4.24 syntax passed for every changed/new PHP source, migration, and evidence file; `git diff --check` passed. The single Node 20.20.0 / Vite 8.2.0 production build passed, and every manifest-referenced asset exists and is nonempty. Final Vite manifest SHA-256: `c43b317d384adee99b6e2c02024f1ac50f5f317dacc7b7f83af222fdd2a8b8f5`.
- **BLOCKED (not substituted):** No migrated disposable MySQL/MariaDB database credentials are available in this worktree. Therefore migration execution/status, schema/data backfill guards, and authenticated `ar`/`en`/`ar-EG` rendering were not run locally. The unexecuted activation procedure must enforce them before switching and will roll back Hotfix42 application files while retaining migrations/data on failure.
- **Build note:** The shell-default Node 16.20.2 invocation stopped before Vite compilation because it lacks `node:util.styleText`; it emitted no usable output. The only successful Hotfix42 build used the established Node 20.20.0 binary; no package was installed.
- **PASS:** The immutable Hotfix42 payload, complete manifest, and Vite payload were checked once before handoff; both root operator scripts passed Bash syntax. Promotion and activation were deliberately not executed. The superseded pre-closure candidate is not an activation artifact; the final package is regenerated from the final documentation closure commit.

## 2026-09-21 — Hotfix45 focused purchasing repair

- **PASS (recorded implementation check):** One focused browser DOM pass showed purchase-order and invoice line/document totals of 120.00 for quantity 12 × purchase cost 10; 10% discount plus 14% exclusive tax yielded 123.12, and inclusive tax yielded 108.00. The retained Livewire editor, editable invoice purchase cost, blank missing cost, and separation from selling price were checked.
- **PASS (recorded isolated MariaDB check):** Fresh isolated restore saved and reloaded order and invoice totals at `120.0000`, invoice purchase cost at `10.0000`, and selling price independently at `600.000`; missing purchase cost remained `null`, average-cost fallback was `25.0000`, and the transaction rolled back. Deployment receipt `/var/lib/toyjoy/hotfix45-deployment/54f67f3a27b35e6e04/verified/candidate-bound-receipt.json` records focused verification, database restore, and private-storage restore as passed.
- **PASS (recorded deployment/live check):** Fresh encrypted backup/restore reconciliation, stock/schema preservation, atomic activation, authenticated read-only renders, and live browser interaction on both forms passed without a production business test document. The live interaction confirmed 120.00 totals, an enabled visible invoice cost input, contextual help, and removal of ordinary inline notes.
- **PASS (read-only continuation check):** Active release symlink points to Hotfix45 commit `7a327b93abc48987eab7238e6bcd125635413fbb`; all eight changed application files match the worktree bytes. Deployment state says success/finished/switched with maintenance off; `https://rajeh.xyz/up` and `/login` returned HTTP 200, and the compiled asset manifest exists. The calculation and browser scenarios above were reused, not rerun.
- **Boundary:** No automated suite, migration, new production transaction, extra browser scenario, redeployment, or new asset build ran in this continuation.


# 2026-10-02 — Product-card Focused Hotfix Checks

PASS: changed PHP syntax; three changed Blade compilations/compiled syntax; locale JSON; 22 saved-cost runtime/filter/SEO source-contract checks; 98,709-byte real component temporary upload, hydration and protected validation; owner-authorized loopback browser upload HTTP 200 at all four stages, absent/pending-file gating, main/additional roles and readiness reset. Browser fixture writes no database records.

NOT RUN: XAMPP MariaDB migration/backfill/down, product save/reload, filter SQL persistence, SEO database retention and gallery records; environment unavailable. Exact original production request failure was not reproduced. No full suite, production browser/mutation, migration, build, merge or deployment. Evidence: .ai/evidence/product-card-filters-media-cost-seo/RESULTS.md. Integration/release requires those focused database checks.

## 2026-10-04 — Named permanent QA Gate continuation

Owner explicitly authorized this focused automated scope. Preserved all original successful evidence and the initial failed Fast run. Fixed QA harness runtime/fixture/adapter assumptions, not application source. Final Fast PASS, resumed 15s; final Release PASS WITH WARNINGS, resumed 24s. Calculator regressions 14 tests/79 assertions; real catalog actions and denial/stale/immutable paths passed; populated purchasing/distribution/price/sale/payment/stock/return/refund/closing chain and scanner passed; actual XLSX/bilingual PDF cost-boundary and 58/80mm receipt checks passed; populated logical backup extraction/import/seven-table counts passed. Browser: 78 authenticated Admin/User/Cashier desktop/mobile assertions, 76 retained plus only two User console checks resumed; expected 403 documents distinguished from unexpected failures. Four Semgrep rules/605 files/zero findings. Corrected Infection evidence: 219 kills/410 generated, 191 escaped, MSI 53.41%; 175 original valid kills retained, only 235 survivors replayed. Initial misleading 100% score rejected.

Native disposable DB `qa_rajh_20261004110243_20992`, socket-only; restore identifiers recorded before creation, under 60 characters. No production DB/business-data/session/credential/grant change, broad audit, historical full suite, new CodeGraph operation, frontend rebuild or deployment. GitHub workflow configured but not executed in this evidence. Static baseline/scope, mutation survivors/unmutated DB paths, final shift-review/device/offline/client acceptance remain limits. Dated JSON/Markdown snapshots record final continuation durations; standalone steps without timing are explicitly identified.


## 2026-10-04 — Phase 2 final scoped precision and production smoke

New Release-only precision classPASS 3 tests / 57 assertions:54 new Fraction cases plus17-line allocation scenario. Only six affected survivor replays performed,1.493 s,zero new kills; scores79.34% raw / 94.14% reviewed unchanged, no new exclusions. Prior13 races,3,884 matrices,Fast104 s,Release/static/security/browser/restore checks preserved, not rerun. Compact production enforced-read-only smokePASS; original passing rows preserved after automatic rollback for root helper PATH, only unfinished cron/backup-monitor/log checks resumed. HTTPSup/login 200,guest admin 302,existing admin form/inbox/inventory/POS read200,normal-user403,four persisted costs read,queue/failed jobs 0,services/cron/schedules present,backup healthy,newerror/critical logcount0. No product/approval production write claim; isolated MariaDB financial effects retained. Actual GitHub CI unverified due unavailable authenticated access. Evidence:`.ai/qa/phase2-evidence/final-production-smoke.json`.


## 2026-10-05 — Product Card optional-cost focused hotfix

Authorized isolated database `qa_rajh_20261005173119_24094`. A–F PASS through backend/browser/readback and real purchase/receiving/valuation snapshots; costless-role hidden field/create/edit PASS. PHPUnit 3 tests/24 assertions PASS; affected syntax/Blade PASS; PHPStan delta PASS, zero new and nine pre-existing findings. Real import blank/valid/invalid cost and pending-cost opening rejection PASS. Evidence: `.ai/evidence/product-cost-optional-permanent/RESULTS.md`. No full audit/Gate or production business QA write.


Production closure: `0.1.22-cost-optional` / `1d27f7b`, compact smoke PASS once after recovery from a pre-smoke helper mode failure. Identity, optional runtime/compiled rule, existing-cost reads, six authenticated read-only routes, HTTP/services/jobs/schedules/backup and zero new critical/error log entries passed. No production business QA write. Disposable DB/credentials/sessions removed; receipts remain under the focused evidence path.

## 2026-10-07 — Module 1 tax-rate validation

- RED before repair: focused behavioral test confirmed a malformed/missing tax rate was accepted by `PlatformSettingsApprovalAction::request()`.
- PASS after repair: isolated XAMPP MariaDB schema `qa_rajh_m1_tax_20261007`; `tests/Feature/Module1TaxRateValidationTest.php` passed 9 tests / 108 assertions. Covered blank Livewire input; missing, null, blank, non-numeric, -5, and 150 at request and persistence boundaries with no approval/tax/audit/default side effects; explicit zero for standard, exempt, and zero-rated approval; corrupt pending approval rollback; authorized company edit, missing permission denial, blank currency validation, payment-method save and display, missing permission denial, and invalid offline-card policy rejection.
- PASS: PHP syntax for changed PHP files, Pint, CodeGraph incremental sync for the exact worktree root, and `git diff --check`.
- NOT RUN: full suite, browser session, production writes, and physical printing.

## 2026-10-07 — Direct-action tax validation label follow-up

- RED then PASS: `tests/Feature/Module1ValidationLocalizationTest.php --filter test_direct_tax_rate_validation_uses_the_same_localized_field_label`, 2 tests / 2 assertions, disposable XAMPP MariaDB schema `qa_rajh_m1_tax_20261007`. Before the fix, English and Arabic direct-action validation messages exposed `taxSettingForm.rate`; after the fix they use the translated Tax Rate (%) label.
- PASS: PHP syntax for both changed PHP files, explicit-file Pint, and `git diff --check`.
- Scope: localized direct-action validation label only. No tax rate rules, rounding, persistence, approval, or audit behavior changed. No full suite, browser, production access/write, push, merge, or deployment.

## 2026-10-07 — Lead combined Module 1/2 verification

Named DEC-093/094 exception, XAMPP MySQL only: `qa_rajh_m1_integrated_20261007` (114 migrations); ten primary/review lanes used their recorded `qa_rajh_m1_<domain>_20261007` schemas. Full combined suite ran once after integration: 264 tests, 228 passed,34 failures,2 errors,6,257 assertions, exit2. All new reported-fix tests passed. Two new legacy source-string assertion failures in V0122Hotfix13CashDrawerIntegrityTest reference replaced lock/scope expressions; supported route and real two-worker checks passed. They were preserved. FriBidi renderer unavailable and a legacy render-target file missing account for the two errors. Complete failure names and baseline comparison: `.ai/qa/module2-20261007/results.json`.

Static1,403 versus baseline1,404: zero added diagnostics/one removed; verbose recapture completed initially truncated output. No baseline/suppression or dependencies changed. Required copied root Pint launcher failed255; installed same-version regression-worktree launcher passed, five formatting-only file deltas reviewed. Syntax69PHP files passed; all3locale JSON files parse and no new duplicate keys after one repeated audit label was removed. optimize:clear, integrated Vite build and temporary Blade view compilation passed. Actual local BUG106 checkbox/group save/reload retained42activeIDs, QAfixtures/server removed. No second full-suite run, production access/deployment, main merge or business/UAT advancement. Exact seven bug-branch remote SHA reads passed; integration closure commit3e13a57 was pushed.

## 2026-10-07 — Focused closure review only

Owner-authorized D3 verification-only continuation on `fix/module2-qa-integration` from `aa0bf8bdee075b46bec19b605e1a495779d8bc27`. Reproduced exactly the two named Hotfix13 failures and the one M54 `source(): false` error (2 failures / 1 error). Both Hotfix13 failures were obsolete source-string expectations, not observed behavioral regressions. Replaced only those two methods with native cashier HTTP/database assertions; their necessary base/transaction/fixture support is in the same file. Cover valid branch/store-scoped opening, permission/cross-scope denial, invalid company/branch/store relationships, inactive and warehouse drawers, immutable opening context, exact audit/assignment, duplicate cashier/drawer, replay and altered-payload protection. A controlled exception after the audit insert proves shift, assignment and audit all roll back inside the action transaction; the event dispatcher is restored in `finally`.

The M54 error was a deterministic stale template reference: `f3d3ba2` removed `purchasing/destination-labels.blade.php` and redirected to canonical pricing labels. Only its affected stabilization method now checks the existing renderer/destination route and current page-guide include/context. Other historical assertions remain unchanged and were not run.

Focused PASS on disposable XAMPP `qa_rajh_m1_integrated_20261007`: two drawer methods **2 tests / 61 assertions** (`focused-closure-final-drawers.xml`); the affected M54 method **1 test / 10 assertions** (`focused-closure-candidate.xml`). Total **3 affected tests / 71 assertions, zero failures/errors**. Passing M54 was not rerun; later runs selected only changed/failed drawer methods. Existing formatter fallback passed, both changed PHP files linted and `git diff --check` passed. Unrelated formatting was restored to honor the named-method scope. A single read-only reviewer found no weakened guards; the unchanged historical M54 template assertion remains excluded debt. No full suite/static scan, Vite build, browser, ten-agent pass or unrelated historical test was run.

FriBidi remains **BLOCKED**: the application-required `/usr/bin/fribidi` is not executable on this host; its renderer/test are untouched and were not run. The prior full-suite totals remain historical, not recomputed or represented as green. Application code, dependencies, normal local data, main, production and business/UAT gates are unchanged. Compact record: `focused_closure_review` in `.ai/qa/module2-20261007/results.json`.
## 2026-10-07 — BUG-08 active-template readiness regression extension

PASS: `InitialSetupStatusTest::test_print_template_assignments_are_incomplete_without_a_linked_template`, run alone on disposable XAMPP schema `qa_rajh_m1_pos_20261007` through the private worktree-safe bootstrap: 1 test / 3 assertions. The method checks unlinked active printer = incomplete, linked active template = complete, and the same linked template after inactivation = incomplete. Explicit-file Pint and `git diff --check` passed. No other tests or broader readiness checks ran.

## 2026-10-07 — BUG-15 Livewire response isolation

`Module1AuditPresentationTest::test_audit_detail_update_returns_only_its_livewire_island` passed: 1 test, 13 assertions, using the existing disposable `qa_rajh_m1_regression_20261007` MySQL schema. It confirms the modal action updates `audit-details`; native pagination updates `audit-table` to page 2 with the intended record; each island response omits the outer page header and is smaller than the full initial HTML; and a table-island update returns 403 after the actor is made inactive. No browser check, full suite, build, migration, or production check was run.


## 2026-10-07 — Fast Module 1 integration final verification

ONE final optimize-clear and ONE full suite ran on disposable XAMPP MySQL schema `qa_rajh_m1_integrated_20261007`: 276 tests / 6,424 assertions, 243 passed, 32 failures, 1 error. Classification A=0 / B=32 / C=0 / D=1 / E=0: all 32 non-environment failing names were already failing in the previous recorded full run; the error requires unavailable FriBidi. All 12 new test cases passed. No unrelated historical test or renderer was repaired. Integration overlap passed 21 tests / 164 assertions. Full suite is FAILED, not green; no new failing name or batch regression was identified.

ONE Vite build passed; all 18 changed PHP files passed syntax checks. Pint passed; the SFC's fully qualified WithPagination trait spelling was restored because the formatter's import rewrite breaks native Livewire compilation. ar/en/ar-EG parsing, new cashier-message parity and no added duplicate keys passed. Scoped PHPStan across six changed application PHP files has 34 existing diagnostics and zero added against the preserved 1,403-finding historical report. Git diff hygiene passed before publication.

After the full run, the narrow BUG-04 generic-type/validated-branch-ID and ar-EG message completion passed only the affected eligibility method: 1 test / 16 assertions, plus syntax, Pint, locale and scoped static-delta checks. No second full suite or build ran. No browser, production or business UAT check ran; BUG-14 browser sizing and BUG-16 remote compression remain unverified. Business data and main were untouched.

## 2026-10-07 — Module 5 verify-and-finish follow-up

Reused `fix/module5-pos-qa-fast` at f625a938d6f0ad718843be6cb7ba2456f1853f23; three Luna High agents inspected existing fixes only. Existing Module5PosQaTest PASS 10/119 and missing audit-cap method PASS 1/2 on preserved disposable XAMPP qa_rajh_m5_pos_20261007. M5-02/03/04/05/06/07/08/09/11 already fixed; M5-10 still DEC-098 Requires Owner Decision. One confirmed M5-01 remainder: settings/settlement accepted unapproved positive denominations despite the exact-5-EGP decision. Added only save-time/read-time 5 comparison, reused localized message, extended existing rounding method: failure reproduced before fix, PASS 1/21 afterward. Three changed PHP syntax and dirty Pint PASS. No frontend edits/build, browser, optimize-clear, full suite, static or real-data/config change; no main change/merge/deployment. New fix commit and exact Module 5 branch push close this authorized prior task before beginning the newly requested Module 3 batch.

## 2026-10-07 — Module 3 named focused purchasing QA

Disposable XAMPP MySQL `qa_rajh_m3_purchasing_20261007`, isolated temporary storage; production data untouched. Final `Module3PurchasingQaTest`: PASS 7 tests / 72 assertions (14.476 s). Covers authorized ordinary/admin direct cost draft save and audit without stock/WAC/supplier-price mutation; unauthorized create/save denial; canonical PO receipt save, locked store, forged mismatched store rejection and direct-store selection; eligible supplier search by name/code/barcode/model with inactive exclusion; exclusive/inclusive tax and incomplete-input preview; supplier history empty/populated/permission/store scope; alternate-language and code fallback. Seven changed PHP/Blade syntax checks, Pint, diff hygiene and one frontend build PASS; existing optional fontaine warning only. optimize:clear once. M3-01, exact production M3-02 denial and M3-04 products require unavailable production grant/eligibility evidence. No full suite, PHPStan, browser, approval/posting/WAC reconciliation/return execution, migration, merge or deployment was run.

## 2026-10-07 — Module 3 v2 verify-and-finish

Existing `Module3PurchasingQaTest` rerun once: PASS 7 tests / 72 assertions (4.301 s) on disposable XAMPP MySQL `qa_rajh_m3_purchasing_20261007`. New M3-06 PO-create dropdown check reproduced the cross-branch options while no branch was chosen, then PASS 1 test / 14 assertions (2.788 s): empty initial store options and only the selected branch's visible stores after branch changes. Test fixture method assumption corrected before reproduction. Two changed PHP/Blade syntax checks, Pint, diff hygiene and one frontend build PASS; existing optional fontaine warning only. No duplicate old tests, full suite, PHPStan, browser, production write or new tax/timezone policy test. M3-10/12 need owner decisions, M3-11 needs verified data cleanup, M3-09 remains UX/tooling; production retest is distinct from these local results.

## 2026-10-07 — Named Module 7 Part 1 local Returns QA

Owner-authorized focused scope, XAMPP MySQL 127.0.0.1:3306 / qa_rajh_m7_returns_20261007, APP_ENV=testing, isolated temporary QA storage. Final PASS: Module7SaleFixtureTest 2/32, Module7ReturnInventoryTest 7/42, Module7ReturnFormTest 5/38, Module7SourceLookupTest 10/73, Module7ReturnHistoryApprovalTest 4/38 (28 tests / 223 assertions). P0-01..P0-10 passed through real application actions/HTTP/Livewire. Inventory: sellable +2 to selling stock; damaged/non-sellable +2 only to damaged stock; manager-review quarantined, approval required; 1+2+2 reaches sold 5, overflow/repeat denied; 2+2 leaves 1, overflow denied; completion replay leaves stock/movements/settlements unchanged. Maker-checker approve/reject, audit, reason/condition/notes, snapshot names, Gift Receipt single-use/privacy and exchange refund 66.67 with correct stock deltas verified.

Changed-PHP syntax (18 files), locale JSON, Pint and diff check passed; optimize:clear once and Vite build once passed. Build retained the existing optional-fontaine warning. No browser-control check, full suite, PHPStan, production QA, merge or deployment. Fixture retention mode M7_KEEP_FIXTURES=1 used only for the final fixture class: sales 135/136, active receipt 72, cashier 310/reviewer 311 remain in the dedicated QA schema; test returns rolled back. M7-06 decision remains open (DEC-103).

## 2026-10-07 - Combined GitHub branch integration verification

Focused existing checks on merged source PASS: Module3PurchasingQaTest 8/86; Module5PosQaTest 10/122; Module9CashShiftsQaTest 6/69; Module7SaleFixtureTest 2/32, Module7ReturnInventoryTest 7/42, Module7ReturnFormTest 5/38, Module7SourceLookupTest 10/73, Module7ReturnHistoryApprovalTest 4/38. Total 52 tests / 500 assertions. Existing isolated disposable XAMPP MySQL schemas for Modules 3/5/7/9 used; transactions retained existing data and M7_KEEP_FIXTURES was disabled. No new tests or full suite.

PHP syntax: 80 changed runtime PHP/Blade files PASS. Changed ar/ar-EG JSON parsing, runtime diff hygiene, no conflict markers, dirty Pint (CLI OPcache disabled) and one Vite build PASS. Existing optional-fontaine build warning and historical Markdown whitespace preserved. All six fetched module branch tips are ancestors of the combined commit history. No browser verification, deployment, production data/schema mutation or business gate advancement.

## 2026-10-12 — Purchase approval and quantity-difference batch

Fresh `origin/main` `365c277` on dedicated `feature/purchase-approval-quantity-differences`. Focused guarded socket-only MariaDB `qa_rajh_20261012110921_4000`: 11 tests / 81 assertions PASS. Covers default receipt, optional partial transfer and blank cells, over-allocation validation/rollback, 1000→900 snapshot and 100 report/CSV, fully received exclusion, zero receipt, draft versus approved price timing, later approval with historical snapshots unchanged, retry idempotency, denied approval/report, stale review, company scope, model-card create/edit/draft/final and two blank-model workbook imports. Initial three failures were stale Livewire error state and test decimal-scale expectations; rerun affected cases passed. The original blank-cell normalization error was reproduced and is now surfaced as field validation. Authenticated isolated Arabic RTL Dokki approval/report and product-card blank-model save/reload, mobile 390×844, English LTR and no browser errors PASS. One Vite build PASS. Changed core Larastan zero findings; one generic annotation corrected and rechecked. One Fast Gate PASS 8/8, 68 tests / 408 assertions, MariaDB integrity, runtime and compact browser sections; `.ai/qa/runs/20261012T121618Z-13548-fast`. Changed-file PHP syntax and diff hygiene PASS. One fresh disposable MariaDB setup initially failed solely because `/tmp` filled; only this task's stopped disposable databases were removed, then focused tests and Fast passed. No production access/write, broad deep audit, main merge or deployment.

## 2026-10-12 — Purchase-invoice locale/System Administrator hotfix

Source `7960c7a`; branch `hotfix/purchase-approval-locale-admin`. Isolated socket-only MariaDB focused suite PASS 14 tests / 121 assertions. It covers canonical active role-only System Administrator self-approval in Arabic, ordinary creator denial in receiving and approval, independent authorized approver success, unauthorized denial, English/`ar-EG` translation, correct creator/approver and bypass audit, accepted stock, current cost/List 0, original/shortage snapshots, optional distribution, and retry without duplicate effects. Browser smoke PASS: authenticated `ar-EG` RTL and English LTR approval modals, no visible English approval labels in Arabic, role-only administrator approval, shortage report, and mobile layout; no browser errors. PHP/JS syntax, JSON parsing, Pint, diff hygiene PASS. Controlled Larastan on four changed approval/purchasing action/policy files PASS zero findings; broader ad hoc changed-file analysis exposed existing generic/PHPDoc/test-fixture findings outside the controlled baseline, with its sole newly introduced test assertion type corrected. One Fast QA run PASS 8/8, zero warnings/skips, 71 tests / 448 assertions; report `.ai/qa/runs/20261012T130306Z-8884-fast`, 172 seconds. All named disposable runtimes removed. No production DB, migration, main merge, or deployment.

## 2026-10-12 — Final approval-scope verification after `5d8508c`

CodeGraph current-root caller/impact and bounded ast-grep found the new helper only in purchase-invoice receiving/action and source-conditional shared approval policy/action. The original shared condition used invoice source type alone; the correction also requires requested action `approve` in policy, action and audit metadata. Purchase-order/return guards and generic `RejectRequest` remain unchanged. Fresh socket-only disposable MariaDB focused checks PASS 3 tests / 38 assertions: role-only invoice self-approval, ordinary creator denial, and denial of purchase-invoice/non-approve, purchase-order, purchase-return and unrelated approval records. Scoped shared-action/policy Larastan PASS zero findings; syntax/Pint/diff hygiene PASS. Translation catalogs unchanged from prior authenticated `ar-EG`/English browser PASS, and static lookup shows 168/168 current invoice/action keys translated in both `ar` and `ar-EG`. Fast QA was not rerun. No migration, production data, main merge, or deployment.

## 2026-10-14 — GitHub issues #24/#25/#28 actionable-error batch

Focused `UserSafeErrorTest`: PASS 3 tests / 3 assertions on an isolated socket-only MariaDB runtime. Validation and domain argument messages are surfaced while unexpected exceptions retain the generic fallback. Changed PHP syntax and Pint PASS. One Fast QA gate PASS 8/8 at `.ai/qa/runs/20261014T045151Z-27196-fast`; its focused regression, integrity scanner, runtime checks and compact browser smoke all passed. No migration, production access, deployment, merge or issue closure.
## 2026-10-14 — Supplier issue batch #26/#29

- PHP syntax checks: PASS for modified PHP and test files.
- `git diff --check`: PASS.
- Pint: ran successfully and fixed import ordering.
- Focused tests requested: `ProductCardMetadataFormTest::test_stale_product_edit_shows_a_clear_reason_and_preserves_the_winning_record` and `ProductPreferredSupplierFormTest::test_preferred_supplier_switch_records_before_and_after_audit_events` could not execute. Direct PHPUnit stopped on unavailable local MySQL credentials; the established `scripts/qa/mariadb-run.sh focused` harness stopped before MariaDB provisioning because Playwright is unavailable in this worktree.
- Fast QA: not run; no shared business calculation logic changed.
- No migration, production database operation, merge, deployment, or issue closure.
## 2026-10-14 — Supplier batch validation completion

- Isolated MariaDB focused run: stale-edit regression PASS; preferred-supplier audit test initially exposed a missing test import.
- Test-only correction: imported `Illuminate\Support\Facades\Gate`.
- Affected rerun: preferred-supplier audit test PASS, 1 test / 4 assertions.
- PHP syntax: PASS. Pint: PASS. Browser dependency was skipped with `QA_CHROMIUM=/bin/true`; no Fast QA, production operation, merge, deployment, or issue closure.

# 2026-10-15 — Operational context batch #15/#34/#35/#36/#42/#52/#55

Focused isolated MariaDB run: `IssueOperationalContextBatchTest.php` PASS 7 tests / 27 assertions. Coverage includes permission-gated navigation, non-team count feedback, explicit zero count persistence without a contribution row, open-count transfer recovery, inventory maker-checker feedback, document/product approval and movement references, alert product context, audit-event fallback labels, and accessible permission checkbox names. PHP 8.4 syntax, Pint and `git diff --check` passed. One Fast QA gate was run at `.ai/qa/runs/20261015T052438Z-19059-fast`: 7/8 checks passed; the only failure was the pre-existing PHPStan finding in unchanged `app/Modules/Catalog/Actions/SaveProductAction.php:267` (`Model::$supplier_item_code`). Fast QA's focused regression, MariaDB integrity, runtime and compact browser checks passed. No migration, merge, deployment, or issue closure occurred.
## 2026-10-15 — Permission taxonomy #55

- `PermissionTaxonomyTest`: PASS, 3 tests / 9 assertions, isolated disposable MariaDB. Covered canonical Audit Logs catalog, `edit` → `update` and `logical_delete` → `delete` mappings, legacy role-pivot preservation, bilingual labels, and checkbox accessible name.
- `RolePermissionPersistenceTest`: PASS, 2 tests / 11 assertions, isolated disposable MariaDB. Existing permission-group persistence and invalid-ID protection remain green.
- PHP syntax, Pint, and `git diff --check`: PASS.
- One Fast QA run: focused critical regression, MariaDB integrity, runtime configuration, tool self-check, and CodeGraph passed. PHPStan failed only on the existing unchanged `app/Modules/Catalog/Actions/SaveProductAction.php:267` property finding. Compact browser smoke was not applicable to this backend-focused batch and failed because it was intentionally run with `QA_CHROMIUM=/bin/true`; no browser dependency was installed. No second Fast QA run was made.

## 2026-10-15 — Audit Logs permission data cleanup (#55)

- Focused isolated MariaDB run: `PermissionTaxonomyDataCleanupTest`, `PermissionTaxonomyTest`, and `RolePermissionPersistenceTest` PASS, 6 tests / 24 assertions. The new test verifies obsolete Audit Logs rows and pivots are removed while unrelated supplier permissions and assignments remain.
- PHP syntax, Pint, and `git diff --check` passed. No Fast QA was run because authorization logic did not change. The targeted migration is prepared but was not run in production; no production data, merge, or deployment operation occurred.

## 2026-10-15 — Customer/catalog/reporting consolidated batch

- Focused `M51CustomerSupplierDataTest`: PASS, 10 tests / 33 assertions.
- Additional read-only normalization probe confirmed local `01012345678`, Egyptian `+20 1012345678`, Arabic digits, and Persian digits resolve to the same canonical search digits; malformed partial terms retain digit search behavior.
- PHP syntax, Pint, and `git diff --check`: PASS. No migration added or run. Fast QA run once at `.ai/qa/runs/20261015T135231Z-26770-fast`: 7/8 checks passed. The only failure is the pre-existing unchanged `SaveProductAction.php:267` PHPStan property finding; focused regression, MariaDB integrity, runtime, and browser smoke passed.

## 2026-10-15 — Customer and quotation policy batch

- Focused `M51CustomerSupplierDataTest`: PASS, 10 tests / 33 assertions, including a bare 10-digit Egyptian mobile normalizing to the canonical national value.
- PHP syntax and Pint: PASS. `git diff --check`: PASS.
- Migration `000124_create_quotation_share_links` was inspected but not run. Fast QA ran once at `.ai/qa/runs/20261015T163106Z-32695-fast`: 7/8 checks passed. The only failure was the pre-existing unchanged `SaveProductAction.php:267` PHPStan property finding; focused regression, MariaDB integrity, runtime and browser smoke passed.

## 2026-10-17 — Purchase invoice approval/UI hotfix validation

- Disposable socket-only MariaDB schema `qa_rajh_20261017072753_291` under `/tmp/rajeh-qa-mariadb.ADIuNw`; isolated from production. Focused `PurchaseApprovalReceivingTest.php`: PASS, 20 tests / 180 assertions. Coverage includes full and partial receiving, invalid quantity rollback and Arabic error, approved state, one stock posting, current cost/price, approval audit, stale/unauthorized denial, and duplicate posting prevention.
- Authenticated local Playwright: PASS on the disposable purchase fixtures. Exact Arabic approval table headings and current `products.sale_price` display passed; price was read-only and formatted to two decimals. One confirmed click path passed. Invalid quantity returned HTTP 200 with a visible Arabic validation error and an enabled button afterward; partial and full approval requests returned HTTP 200 and closed the review. One confirmation occurred per action.
- List layout: desktop 1440px and tablet 1024px displayed nine aligned columns in one row, with a compact secondary-action menu; mobile 390px displayed the compact grid. RTL, page overflow checks, print/reversal menu availability, and browser console checks passed. Screenshots: `storage/app/frontend-review/purchase-invoice-hotfix/desktop-1440x900.png`, `tablet-1024x768.png`, `mobile-390x844.png`. The mobile full-page image was cropped to the visible 390px viewport to exclude the off-canvas sidebar from the browser's wider full-page capture.
- Final Blade view cache, PHP syntax, Pint, browser-script syntax, shell syntax, and diff hygiene: PASS. Local Vite build passed for screenshot assets. No broad QA, production data operation, merge, or deployment occurred.

## 2026-10-17 — Purchase approval central warehouse hotfix

Disposable socket-only MariaDB schema `qa_rajh_20261017082747_13859` with networking disabled: focused `PurchaseApprovalReceivingTest.php` PASS, 24 tests / 200 assertions. New cases passed for active branch-unassigned central warehouse full and partial receipt, active branch-linked warehouse, inactive and nonexistent warehouse rejection, selected-warehouse stock posting, current cost, approval audit, and repeated approval without duplicate movement or audit. PHP syntax on changed files, Pint, Blade view cache, and diff whitespace check passed. The disposable schema was migrated for tests; no production data operation, migration, seeder, merge, deployment, or broad QA was run.

## 2026-10-17 — Purchasing distribution and supplier returns v2

Disposable socket-only MariaDB schema `qa_rajh_20261017085443_13129` (no production business records): focused distribution independent-state/price-preservation test passed 1 test / 12 assertions; invoice-free and invoice-linked supplier-return cases passed; validation cases passed 2 tests / 10 assertions; selected source store case passed 1 test / 4 assertions. Existing partial distribution and zero-received tests passed. A combined 7-case run initially had 6 pass and one fixture-sensitive legacy assertion fail because it read the first stock balance across products; the assertion was scoped to its intended product and the case reran successfully (1 test / 6 assertions). No broad ERP suite was run.

Authenticated local browser workflow passed invoice-free draft save and cost-source detail. Screenshots at 1440, 1024, and 390 pixels were saved under `storage/app/frontend-review/supplier-return-v2/`. Arabic RTL, no document horizontal overflow, and no console errors passed at all three widths. Final PHP syntax, Pint, Blade view cache, JavaScript syntax, and `git diff --check` passed. The isolated schema was migrated; production was untouched.

## 2026-10-17 — Purchasing production rollout and rollback

Backup `/home/rajeh/shared/toyjoy/backups/rajeh_toyjoy_20261017_pre_df1e308.sql.gz` passed gzip integrity and isolated restore: 168/168 tables, 123 migration rows. Candidate `df1e308` compiled config/routes/views, served loopback `/up`, `/`, `/login` and assets with HTTP 200, and exposed `purchasing.returns.create` in route:list. Only migration `2026_10_17_000128_supplier_returns_and_payables` ran, batch 35. Real-host `/up`, `/`, `/login`, CSS, and JS returned 200 immediately after activation; protected `/purchasing/returns/create` returned 404, so required supplier-return production verification failed. The code symlink was immediately rolled back to `0e09f86`; subsequent `/up`, `/`, `/login` returned 200, maintenance was off, and the new ledger had zero entries. No live purchase, distribution, return, or payable posting was submitted. Production scenario behavior remains unverified; source and disposable-database tests are the only evidence for those paths.

## 2026-10-17 — Supplier-return v2 real-vhost retry

Read-only topology inspection found `rajeh.xyz` on Apache `13.140.139.9:80/443`, `DocumentRoot /home/rajeh/public_html/erp`, and PHP handler `ea-php85-php-fpm`. The stable public link remained `/home/rajeh/public_html/erp -> /home/rajeh/laravel/public`. Under maintenance with a private bypass cookie, candidate `df1e308` returned 404 for `/purchasing/returns/create` on the actual origin vhost before PHP 8.5 FPM reload (six requests), then 302 after reload (twelve requests). Reloading PHP 8.4 FPM had no effect. A controlled diagnostic restored the old release before the one retry.

The one retry used the unchanged hash-verified `df1e308` artifact. Before public opening, the origin vhost returned 200 for `/`, `/up`, `/login` and 302 for the protected create route (six requests). After opening, the public hostname returned 200 for those public paths and CSS/JS, and 302 for the protected route (six requests). The active and public/index symlinks resolve to the candidate, maintenance is off, and the post-retry Laravel error-log segment has zero new bytes. Migration `000128` remains Ran in batch 35 without rerun; `supplier_payable_entries` remains at zero. No production business transaction was submitted.

## 2026-10-18 — Inventory transfer allocation UI/state hotfix

Owner-authorized V1 fix on `hotfix/inventory-transfer-allocation-ui-state`, source `e7b55567a931e2b66c0949594532b23f2c7d2c8a`. Production baseline remains `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.27-inventory-e7b5556-r2`, same application SHA. No merge or deployment is authorized.

Root causes: `Number('—')` propagated NaN into remaining; the error's hidden condition was inverted and had no valid-product/known-stock guard; `lg:col-span-full` was absent from the existing CSS build, leaving allocations in the parent's 2.5rem action track. The product-selection event also precedes stock-output updates. The existing added-row template lacked allocation cells and summaries.

Changed only `resources/views/inventory/index.blade.php` at runtime: explicit finite-number guards and zero totals while incomplete, placeholder for unknown stock, correct availability-based warning after numeric allocation, deferred refresh after lookup/source change, delegated added-row handling, and full-width allocation grids with readable cell minimum widths and mobile stacking. Draft/create share this form. Allocation field names and server validation remain unchanged; inventory actions, dispatch/receipt, routes, permissions, database schema, and business logic were not edited. No frontend build is needed for the view-local CSS/JS.

Focused browser validation PASS under Node v20.20.0 / Playwright 1.62.1: actual compiled Blade form, existing application CSS, Alpine and ProductLookup, eight synthetic warehouses and mocked product-search responses, isolated loopback server. No database, login session, or production business transaction was used; this is focused form verification, not authenticated full-page or server/domain verification. A empty row: placeholders, zero totals, no error; B stock 10 with 3+2: allocated 5/remaining 5; C changing A preserves B; D 6+5: Arabic error. Desktop 1440, tablet 1024, mobile 390 PASS for RTL, Cairo, readable labels, no overlapping labels, no page horizontal overflow, no NaN, and no console errors. Destination widths were 204px, approximately 209px, and 266px respectively; mobile used one column. Source reset, added rows, and unknown quantities passed. Final targeted follow-up verified malformed NaN/Infinity availability becomes a placeholder and totals zero, and requested quantity is not mistaken for available stock in this warning. Existing server requested-quantity validation is preserved.

Evidence retained locally at `storage/app/frontend-review/inventory-allocation-ui/`: screenshots and results.json. Initial fixture rendering needed the usual validation error bag; the first browser launch needed the installed Playwright entry path. These harness issues were corrected before the passing checks. Affected PHP syntax, Pint and Blade cache PASS; final diff hygiene follows. The initial PHP wrapper emitted host safelock warnings; final affected checks used direct PHP 8.4. No broad QA, PHPUnit suite, migration, asset build, merge, push to main, or deployment occurred.
