# 2026-10-28 — Rajeh v2.0.0 footer alignment LIVE

2026-10-28 — Display-version metadata alignment deployed. Branch `hotfix/display-version-v2-20261028` source `7c873321d3dcf07ba6e4ac2921b172b37d1ece8e` merged without conflict as `262b298d122bbc0e13c44112fec6d9530a315369` from previous origin/main `94620788f065532288e9ff02965f0f6d5d33f84f`. The sole runtime diff changed `App\Support\ApplicationVersion::RELEASE` from `0.1.22-cost-optional` (footer `v1.22`) to `2.0.0` (footer `v2.0.0`); no Core business behavior, schema, workflow, or permissions changed. The permanent baseline tag `v2.0.0-core-operations` remains on `55d4d5525ced50812f65941bb048dc81c6c7f641`. Production moved from protected `toyjoy-v0.1.56-cash-movement-55d4d55` to `toyjoy-v2.0.0-display-262b298`; the baseline recovery release remains preserved. Deployment backup: `/home/rajeh/shared/toyjoy/backups/rajeh_toyjoy_20261028T185220Z_pre_display_v2_262b298.sql.gz`, SHA-256 `f059fc4869d2ac5087e2dceb90142f1159b51065654abdd5fb5bdb0b85484508`. No pending or executed migrations. Candidate and public `/`, `/up`, `/login` returned 200; version, CSS/JS, guest shift routes, FPM reload, maintenance OFF, and no new Laravel/PHP-FPM error condition were verified. Next phase remains major module expansion; none has started.

# 2026-10-28 — RAJEH v2.0 CORE OPERATIONS BASELINE COMPLETE

Owner declares Core Operations and POS COMPLETE at formal Rajeh v2.0 / semantic `v2.0.0`. Permanent annotated tag `v2.0.0-core-operations` resolves remotely to verified running application `55d4d5525ced50812f65941bb048dc81c6c7f641`, distinct from freeze-precheck origin/main `f7e2a3c6122d26544a613ed63efd3e68aa877c1b` and later documentation commits. The active immutable release `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.56-cash-movement-55d4d55` is marked DO NOT DELETE; no Production deployment or runtime application change occurred in this freeze.

Fresh protected v2 database backup `/home/rajeh/shared/toyjoy/backups/rajeh_toyjoy_20261028T183159Z_v2.0.0_core_baseline.sql.gz`, 90,064 bytes, SHA-256 `04d03d5416280779ea25876f6566908cd7b6e8c4fa0cac222a9acf004f3aa564`, passed gzip, readable SQL, 172-table structure, and a full isolated socket-only restore with Core-table queries. Protected persistent-storage archive `/home/rajeh/shared/toyjoy/backups/rajeh_storage_v2.0.0_core_baseline_20261028T183351Z.tar.gz`, 425 bytes, SHA-256 `663450deab370a4a7438c5ca4ece998e663ffa2849bbfeae3f826725b12394df`, passed gzip/tar readability. It contains current shared attachment/export/public directories; no active business files were present. Historical backup ZIPs, temporary uploads, caches, sessions and logs were excluded. Both assets have a backup-directory protection marker.

`scripts/test-core-baseline.sh` PASS: 21 selected existing PHPUnit tests / 250 assertions plus the existing Smart Split/payment JavaScript check; its disposable MariaDB runtime was removed. Windows-only legacy Module 7 QA harness and physical hardware/browser UAT are not included in this gate. Production precheck `/`, `/up`, `/login` each 200, maintenance OFF, PHP-FPM active, no pending migrations or active error condition. PHP 8.5.12, Laravel 13.24.0, Node 20.20.2. Formal v2.0.0 supersedes the old displayed footer `v1.22`; changing the canonical PHP version constant would change immutable runtime code, so the live footer remains `v1.22` until a separately reviewed release. Core Freeze and code-only/full historical recovery procedures are documented. Next phase: major module expansion; none has started. Historical device/client UAT limitations remain recorded rather than silently cleared.

# 2026-10-28 — Cash Movement / blind-close hotfix LIVE

Owner-approved source `ddce7aec2c21f506a94f11bd530c7a1e479ef13b` merged without squash or conflicts from previous origin/main `ac449a92e7a67e748503bf34fb687183164be782` as application commit `55d4d5525ced50812f65941bb048dc81c6c7f641`; that exact commit was pushed to main and packaged as `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.56-cash-movement-55d4d55`. Previous Production application `d418ccbf2227957c72238ff612fa7062c42f7be0` remains at `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.55-pos-smart-returns-d418ccb` for rollback. The final continuity-only main commit follows the deployed application SHA. No schema, permission, expected-cash formula, audit action, or shift lifecycle changed.

Full compressed Production backup `/home/rajeh/shared/toyjoy/backups/rajeh_toyjoy_20261028T142458Z_pre_cash_movement_55d4d55.sql.gz`, SHA-256 `c1698f430cd57e689c2a2851a6567846e6aa648921f1dfcb8a5b363e4223c6f8`, passed gzip integrity and SQL-header readability. Migrations pending/executed: 0/0; no seeder or destructive command. Merged-main disposable MariaDB movement/accounting PASS 2 tests/53 assertions; ordinary-cashier blind-close secrecy PASS 1 test/18 assertions. Prior hotfix authenticated 1440/390 browser QA passed 30 checks and was not repeated on Production. Node 20 npm ci from committed lock and Vite build passed. Exact final-path release source, Composer/npm lock hashes, autoload, shared `.env`/storage/public-storage links, route registration and caches passed.

Maintenance-gated real Apache/vhost candidate checks after atomic link switch, before public `artisan up`: `/`, `/up`, `/login` 200; `/pos`, `/pos/shift`, `/pos/shift/1/cash-movement` expected guest 302; CSS/JS 200. PHP 8.5 FPM reloaded immediately after switch. Public real-host checks repeated the same codes; maintenance OFF, FPM active, no new Laravel log bytes, no PHP-FPM error journal entries since activation start, and no observed HTTP 5xx. The retained previous release was returned to maintenance OFF. Rollback NO. No safe authenticated Production session was available; credentials were not invented and no Production business movement or shift close was submitted. Operational action separation and blind-close secrecy are proven by merged-main tests and prior authenticated local browser QA, not by authenticated Production QA. The business milestone, Phase Gate, and client UAT status do not advance.

# 2026-10-28 — POS smart split and Returns release live

Approved source `c66f2c7` merged without squash at `d418ccb`; previous origin/main `b7343da`. Immutable Production release `toyjoy-v0.1.55-pos-smart-returns-d418ccb` active; rollback release `toyjoy-v0.1.54-transfer-atomic-9c89e3b` retained. One additive return migration executed after candidate validation and verified Ran. Fresh compressed backup integrity/readability passed. Merged-main disposable MariaDB 6/42, split JS, Vite, Pint, PHP/Blade, and candidate/public real-vhost checks passed. Authenticated 1440/390 browser evidence is the feature-branch 24/24 QA. Maintenance OFF, no post-activation Laravel/PHP errors or observed 5xx, rollback NO. Physical scanner QA remains pending after deployment by owner decision; Windows-only legacy Module 7 harness unavailable on Linux and nonblocking. Official lookup: barcode, invoice, customer name, customer phone, date. Linked return when sale recovered; unverified fallback only when unrecoverable. Exchange UI and damaged inspection remain deferred.

# Historical feature handoff — POS smart split and returns implementation and QA complete

At this feature-branch handoff, work was isolated from `origin/main` with no merge or deployment yet. Smart split, Arabic-first F7 Returns, receipt barcode, bounded invoice/customer/date search, original-economics linked returns, partial quantity, refund allocation, direct restock, permission-controlled manager-amount unverified fallback, audit and idempotency implemented. Focused disposable MariaDB 6/6 (42 assertions); authenticated 1440/390 browser 24/24; split JS test, Vite build, Pint, PHP/Blade syntax PASS. Exchange-ready domain retained; unified POS exchange UI and inspection/quarantine deferred. Physical barcode scan/thermal hardware UAT remains.

# 2026-10-27 — Controlled transfer test cleanup and workflow QA SUCCESS

Owner authorized cleanup of TEST/QA inventory-transfer data; real client business data has not started. Verified backup `/home/rajeh/shared/toyjoy/backups/rajeh_toyjoy_20261027T075306Z_pre_transfer_test_cleanup.sql.gz`, SHA-256 `aa2767ec113a2a991a3af89763a4f1d8c3c94e990ef2c6954ef5e903c1fa9c07`, gzip and SQL readable. Transfers 21–24, seven lines, 21 allocations, four approval records, and 26 exact transfer/approval audit rows were removed transactionally; there were no related stock movements or receiving drafts. Purchase invoice 37 and receipt movements 109/110 remain and reconcile to a clean baseline: MST-01 product 53 and 55 each 12 on hand/available; DST-01, PST-01, SST-01 each zero on hand and in transit for those products. No user, permission, store, purchase, or unrelated record was deleted.

New canonical QA transfer `25 / CENTRAL-000005` was created, submitted, reviewed, and approved once using the final reviewed 9+9 quantities with 3/3/3 allocations. Approval saved review values and automatically dispatched: status `in_transit`, dispatched_at `2026-10-27 07:55:59` (database time), source movements 111/112 each -9, MST-01 balances 3/3, each destination in-transit 3/3 and on-hand zero. Then only DST-01 was received through the canonical receiving draft/finalize actions: receipt movements 113/114 each +3, DST-01 on-hand 3/3 and in-transit zero; PST-01 and SST-01 remain on-hand zero and in-transit 3/3. Parent transfer remains `in_transit`; no other destination received. Movement sums reconcile to on-hand balances for all four stores and both products. Exact Inventory Balance query with actor-visible store IDs returned those balances; no transfer FK orphans or duplicate movements. Public `/`, `/up`, `/login` each returned 200, protected inventory/transfer GETs expected guest 302; no HTTP 5xx or new Laravel/PHP errors observed. Authenticated browser POST was not run; the canonical domain actions and audit trail were exercised. Code/schema changed: NO. Rollback: NO.

# 2026-10-27 — Atomic transfer workflow merge and deployment SUCCESS

Source `f4369401d1fc7a9409667e6f5a50e637f4bfb8bd` merged without squash from prior origin/main `143806eb05b8f81cd7583e370b5cfb85eecc9705` as `9c89e3b61b8d5871b33e5e078cdf01ec33d992fe`; no conflicts. The deployed main application SHA is `9c89e3b61b8d5871b33e5e078cdf01ec33d992fe`. Previous Production application `588fc42687f6fd3895da102abe30727bdf43d1ab` at `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.53-login-polish-588fc42` was replaced by immutable `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.54-transfer-atomic-9c89e3b`. Backup `/home/rajeh/shared/toyjoy/backups/rajeh_toyjoy_20261027T072712Z_pre_9c89e3b.sql.gz` passed gzip/SQL readability; SHA-256 `2fdd8f98747a0f387f685ab1fba4b2a829290b83ac3c4695851c3ca1d1a6c653`. Migration files changed: 0; pending/executed: 0/0.

The lifecycle is Draft → Review → Approve → Automatic Dispatch → In Transit → Destination Receipt(s) → Completed. The reviewer may edit and approve directly. Approval is the final source-side action; reviewed edits, approval, and canonical dispatch are atomic. No separate Dispatch action exists. The candidate used shared `.env`, `storage`, and `public/storage`, lockfile-matched dependencies and assets, and Laravel caches built in the final path as `rajeh`. Merged-main disposable MariaDB focused gate PASS 8 tests/69 assertions. Authenticated local 1440/390 browser QA from the source-equivalent hotfix remains PASS 27 checks; it was not repeated on Production. Real-host maintenance-gated candidate `/`, `/up`, `/login` each 200; transfer register and review protected GETs each expected guest 302; CSS/JS each 200. Atomic switch and PHP 8.5 FPM reload PASS; public `/`, `/up`, `/login` each 200, transfer register expected guest 302. Maintenance OFF, no HTTP 5xx or new Laravel/PHP errors observed. An initial candidate attempt rolled back after a manifest-read permission error in the validation harness; rollback and public health passed. Corrected full candidate gate then passed and final deployment did not roll back. Production transfers 21–24 remain approved with null `dispatched_at`; read-only verification found them untouched. Their repair requires a separate controlled owner-approved action; no Production business record was dispatched or received during deployment. Authenticated Production workflow was not exercised because no safe session was available.

# 2026-10-27 — Atomic final transfer review hotfix

Implemented editable final review and atomic save/approve/canonical dispatch on the isolated hotfix branch. New successful approvals enter `in_transit` and reduce source stock immediately; allocation in-transit and actual destination receipts retain canonical posting. Removed manual dispatch action and direct shared-inbox approval. Legacy Production transfers 21–24 are approved but undispatched and remain unchanged. Focused disposable tests and authenticated local browser QA passed; owner review is next. No migration, Production data repair, merge, or deployment.

# 2026-10-26 — Rajeh login polish merged and LIVE

Owner-approved login UI source `22a030a90dddd4c38cd0283ba5624b0e95771b8f` was merged without squash from unchanged origin/main `3fe5aed191ff157e286d9b116a3fc300cb6be9b6` as application/main merge `588fc42687f6fd3895da102abe30727bdf43d1ab` without conflicts and is LIVE at `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.53-login-polish-588fc42`. Previous Production SHA `a1e08baf1c7498d2586bcf330db87b8aae2d1fd5` at `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.52-client-facing-polish-a1e08ba` remains rollback. Fresh backup `/home/rajeh/shared/toyjoy/backups/rajeh_toyjoy_20261026T201721Z_pre_588fc42.sql.gz` passed gzip and SQL-readability checks; SHA-256 `bcf4bf5d34efeaf47877c39a21e7fdb7e6d0370b9db88d4bbacadbfce788f16e`. Production migrations pending/executed: 0/0. Exact-source candidate real-vhost `/`, `/up`, `/login`, Rajeh wordmark/mark PNG, CSS/JS and rendered login HTML passed. Candidate and public login HTML confirmed the approved logo, username empty by default, Arabic placeholder `أدخل اسم المستخدم`, and Rajeh favicon reference. Atomic active-link switch and mandatory `ea-php85-php-fpm.service` reload passed. Public `/`, `/up`, `/login` and logo assets returned 200; no HTTP 5xx, new Laravel errors or PHP-FPM errors were observed. FPM is active, maintenance is off, and no final rollback was needed. Previous local authenticated 1440/390 acceptance remains functional evidence; authenticated Production credentials were unavailable and not invented. No authentication logic, route, permission, session, database, seeder, or migration behavior changed. Journal: `/var/lib/toyjoy/login-polish-deployment/588fc42-20261026T201910Z`.

# 2026-10-26 — Client-facing branding and decimal polish LIVE

Owner-approved main application `a1e08baf1c7498d2586bcf330db87b8aae2d1fd5` (source hotfix `d4a9ce2fbab47be7afcd75c108527b11a3d0da05`, merge `cec99bf258f06e25bd94b1dd0c079239e25001fb`) is live at `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.52-client-facing-polish-a1e08ba`. Previous Production SHA `72ca966510fe7be11fc33afe5d0194fa344a47ac` at `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.51-barcode-transfer-72ca966` is retained as rollback. Fresh backup `/home/rajeh/shared/toyjoy/backups/rajeh_toyjoy_20261026T194010Z_pre_a1e08ba.sql.gz` passed gzip and SQL-readability checks; SHA-256 `1f441847a82cf42dcf7061ed36bfad1f829d64fd1b63534d2002cc8d3b48bf78`. Production migrations pending/executed: 0/0. Exact-source candidate real-vhost checks passed `/`, `/up`, `/login`, Product Card and transfer editor protected-route boot, both Rajeh PNG assets, CSS/JS, canonical favicon reference and quantity/money/transfer formatter smoke. Atomic active-link switch and mandatory `ea-php85-php-fpm.service` reload passed. Public `/`, `/up`, `/login` and Rajeh assets returned 200; guest protected routes returned expected 302. No HTTP 5xx, new Laravel errors or PHP-FPM errors were observed; FPM is active and maintenance is off. The official login logo, authenticated shell mark, favicon, compact transfer destination UI, two-decimal presentation rule and transfer save/reload correction are deployed. Authenticated Production smoke was unavailable and was not performed; prior focused disposable tests and local 1440/390 browser acceptance remain the functional evidence. No Production migration, seeder or business transaction ran; final rollback was not needed. Journal: `/var/lib/toyjoy/client-polish-deployment/a1e08ba-20261026T194209Z`.

# Historical pre-deployment record — Client-facing polish main merge

Owner-approved `d4a9ce2fbab47be7afcd75c108527b11a3d0da05` from `hotfix/client-facing-ui-branding-decimal-polish-20261026` was merged without squash into unchanged origin/main `4e8cd3e165d32fe89a6bd1d3ae40a77c2eba07e0` as `cec99bf258f06e25bd94b1dd0c079239e25001fb`, with no conflicts. Main focused verification passed 2 tests/20 assertions for quantity formatting, two-decimal money, approved Rajeh logo/favicon references and transfer draft save/reload without six-decimal allocation input values. Changed PHP syntax, Blade compilation, migration delta and diff hygiene passed. The source branch’s authenticated local 1440/390 browser acceptance (26 checks) remains valid for this conflict-free source-equivalent merge and was not rerun. No migration was added. Production remains `72ca966510fe7be11fc33afe5d0194fa344a47ac` at `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.51-barcode-transfer-72ca966`; no Production access, data change or deployment occurred. This batch is merged into main and NOT DEPLOYED.

# 2026-10-26 — Client-facing UI hotfix ready, not merged or deployed

V1 urgent UI batch on `hotfix/client-facing-ui-branding-decimal-polish-20261026` from origin/main `4e8cd3e165d32fe89a6bd1d3ae40a77c2eba07e0`. Application commits `11f34f8ab02675509090c03187a0dd4b9b501f0a` and `ecfacae` reuses approved `public/images/rajeh-logo.png` and `public/images/rajeh-logo-mark.png` for login, application brand, and canonical favicon reference. The transfer allocation leak was raw DECIMAL `quantity` placed into input values after draft reload; ProductQuantity now formats those values, while compact flex items keep warehouse names beside narrow inputs. Receiving draft hydration also uses ProductQuantity. `x-money` enforces two decimal places; visible direct quantities/money in the bounded inventory, purchase history, sales/POS receipts, customer, gift, party, quotation, product and dashboard surfaces were corrected. Stored precision, domain calculations, validation, migrations and dependencies were unchanged; the follow-up edit preserves original party invoice values when the visible rounded fields are untouched. Focused disposable MariaDB: 2 tests/20 assertions PASS, including draft save/reload. Authenticated local Chromium: 26/26 checks PASS at 1440/390 with approved logos/favicon, compact allocations, no six-decimal inputs, RTL/Cairo, no overflow, console error or HTTP 5xx. Modified Blade templates compiled; Pint and diff hygiene PASS. Initial browser attempt was blocked by the disposable QA router omitting `/images`; its temporary router was corrected and the focused browser gate passed. Disposable databases/runtimes were removed. No merge, Production change or deployment occurred.

# 2026-10-26 — Barcode and inventory transfer corrective release LIVE

Approved main application `72ca966510fe7be11fc33afe5d0194fa344a47ac` (source hotfix `f62dc9098046700a4a0289f552a19fa5851f5a39`, merge `2d182ffa63f245045f8b19b4ac9b74a0a3d23a79`) is live at `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.51-barcode-transfer-72ca966`. Previous Production SHA `00f150f7bd7cf80969f143cba171966cbb33eb84` at `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.50-commercial-inventory-00f150f` is retained for rollback. Backup `/home/rajeh/shared/toyjoy/backups/rajeh_toyjoy_20261026T184852Z_pre_72ca966.sql.gz` passed gzip/SQL-readability checks; SHA-256 `e6c5f144a1474ffdcd7a37d574350b2188eadceb6879de70f7bc6e5866442d4d`. Production migration status was zero pending and none ran. Candidate real-vhost checks, atomic activation, PHP 8.5 FPM reload, and public `/`, `/up`, `/login` passed; Product Card create/edit and transfer editor/approval/receiving protected GETs returned expected guest 302 without 5xx. CSS/JS returned 200, FPM remained active, maintenance is off, and no new Laravel errors were found. The first candidate gate had a verification-cookie error and restored the old release before the corrected gate passed. Authenticated Production behavior was not checked; previous focused local tests and browser evidence remain the functional evidence. Barcode validation/localization, compact transfer UX, actual/partial multi-destination receiving, and approval 422 correction are deployed. No Production business transaction, seeding, or database data change was performed. Final deployment rollback: NO. Journal: `/var/lib/toyjoy/barcode-transfer-deployment/72ca966-20261026T185123Z`.

# Historical pre-deployment record — Corrective merge

From prior origin/main `8fb20244e58c9a7fc777f6de8bd13a09741b5a4b`, approved branch `f62dc9098046700a4a0289f552a19fa5851f5a39` entered main in normal merge `2d182ffa63f245045f8b19b4ac9b74a0a3d23a79` with no conflicts. Valid GTIN-8/UPC-12/EAN-13/GTIN-14 and leading-zero string handling remain strict; the observed 15-digit example remains invalid, and Arabic errors are localized. Transfer editor rows are compact and distinct, allocations fit two rows with contained overflow, and duplicate summaries are removed. At destination receiving after dispatch, actual quantities remain editable before final confirmation; only actual quantity posts to stock, allocated/actual/variance are audited, and each destination remains independent. Successful transfer approval now redirects to the register, avoiding the former generic 422; expected review errors stay inline. Focused post-merge checks passed 7 tests/72 assertions, changed PHP syntax, Arabic JSON and diff hygiene. No migration, Production mutation, merge conflict, or repeated browser matrix. Production `00f150f7bd7cf80969f143cba171966cbb33eb84` at `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.50-commercial-inventory-00f150f` is unchanged; deployment requires separate authorization.

# 2026-10-26 — Inventory transfer and barcode corrective branch ready for review

Source `8fb20244e58c9a7fc777f6de8bd13a09741b5a4b` is isolated on `hotfix/inventory-transfer-review-and-barcode-validation-20261026`. The Product Card now trims international barcode input before validation; the existing check-digit and supported 8/12/13/14-digit policy remains strict, including leading zeros. Arabic barcode error translations were added. Transfer editor rows and destination inputs were compacted, separated visually, and duplicate summary outputs removed without changing allocation validation. Approval success redirects to the register instead of reopening the now-ineligible `/approve` GET; stale review feedback is inline. Destination receiving submits edited actual quantities for atomic final posting through the canonical action, with allocated/actual/variance audit metadata. No schema or Production change. Focused QA and authenticated browser checks are complete; merge readiness is YES for owner review, without merge or deployment.

# 2026-10-26 — QA/Demo Data Framework merged, not deployed

Owner-approved `feature/qa-demo-data-framework-20261026` at `36c39a4e4651a844d0ee406b939cfec13ddcc0b0` is preserved in main merge `58413c1217b0fc3bb5fda8686071dff7b22a32c0` over prior main `99bb8b6d12c454f58eadec0520996cb0721eadd1`. `docs/qa-demo-data.md`, factories, guarded seeders, and `rajeh:qa-reset` support `--empty`, `--demo`, `--catalog`, `--inventory`, and `--pos` only in testing/dedicated QA. Post-merge focused verification passed 5 tests/94 assertions in a removed private socket-only MariaDB runtime, 44 changed PHP syntax checks, and Production-hidden command registration. Production auto-seeding and migrations were not introduced. The owner-authorized Production business reset and complete 2026-10-26 operational-location cleanup remain valid. Production data and deployed application SHA `00f150f7bd7cf80969f143cba171966cbb33eb84` are unchanged; deployment has not occurred.

# 2026-10-25 — Commercial and inventory Production deployment SUCCESS

Approved application `00f150f7bd7cf80969f143cba171966cbb33eb84` from User B `d9e0e7c6673a89f1891014a2d3d9ad0be674e12a`, User A `5ac2e631b87b54f2eb32dc5a7dc3d3711bf6cec8`, integration `6b167bdb69e12cecf0225acfb0a3c30c85c81d31`, and main merge `6fe1cbad626be76aa43a8c18cbc3020ee1dcf0c4` is active at `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.50-commercial-inventory-00f150f`. Previous `0cf121450502713be385e041cbaf66e2a93ea66e` remains at `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.49-pos-completion-r9-r16-0cf1214-r2` for rollback. Production migration status: zero pending; none run. Backup and isolated restore passed; exact candidate and real-vhost gates, atomic switch, PHP 8.5 FPM reload, guest browser/public `/`, `/up`, `/login`, and zero new Laravel errors passed. No rollback occurred. DEC-107 and DEC-108 are resolved and live. Authenticated Production smoke was not run by owner choice; prior 1440/390 integration acceptance and 40 post-merge database tests remain recorded. Journal: `/var/lib/toyjoy/commercial-inventory-deployment/00f150f-20261025T181826Z`. If a later application rollback is required, atomically repoint `/home/rajeh/laravel` to `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.49-pos-completion-r9-r16-0cf1214-r2`, immediately reload `ea-php85-php-fpm.service`, ensure maintenance is off, and verify `https://rajeh.xyz/`, `/up`, and `/login` through the real vhost. No migration was run for this release.

# 2026-10-25 — Commercial and inventory batch merged to main; NOT YET DEPLOYED

User B `d9e0e7c6673a89f1891014a2d3d9ad0be674e12a`, User A `5ac2e631b87b54f2eb32dc5a7dc3d3711bf6cec8`, and integration final `6b167bdb69e12cecf0225acfb0a3c30c85c81d31` are preserved in main merge `6fe1cbad626be76aa43a8c18cbc3020ee1dcf0c4` over prior main `8e8cc88ec0a187aea0dbf96fa03d1a4666f0912f`. Main merge conflicts: none. Compact post-merge QA passed 40 focused MariaDB tests/325 assertions, 30 changed PHP syntax checks, one POS calculator test, and diff hygiene. Migration tree unchanged; no migration ran in Production. Integration's authenticated 1440/390 acceptance remains the browser evidence. DEC-107 nonblocking stocktakes, DEC-108 fail-closed explicit/sole outlet resolution, and DEC-109 branch context remain intact. Production remains `0cf121450502713be385e041cbaf66e2a93ea66e`; only owner-authorized deployment is outstanding.

# 2026-10-25 — Commercial and inventory integration, undeployed

Owner-approved User B `d9e0e7c6673a89f1891014a2d3d9ad0be674e12a` merged first, then User A `5ac2e631b87b54f2eb32dc5a7dc3d3711bf6cec8` onto `feature/integration-commercial-inventory-batch` from `origin/main` `8e8cc88ec0a187aea0dbf96fa03d1a4666f0912f`. Both workstreams are integrated on the feature branch; neither is pending development. DEC-107 retains nonblocking open stocktakes; DEC-108 retains explicit/sole eligible outlet resolution; User B's colliding DEC-107 header-context decision is retained as DEC-109. Focused integration database and authenticated 1440/390 browser verification passed after correcting one pre-existing approval-review test assertion; exact results are in `.ai/TEST_RESULTS.md`. Production remains on `0cf1214`; no main merge or deployment occurred.

# 2026-10-25 — Inventory stocktake and resilient drafts branch

Development branch `feature/inventory-stocktake-and-resilient-drafts` refines the already deployed persistent stocktake workflow and canonical transfer draft. Drafts now retain requested quantities through temporary stock shortage; final submit/dispatch continue to enforce availability transactionally. The stocktake count screen gains category filtering, the list shows shortage/surplus counts where line variances are stored, and submitted review shows difference quantities and cost-view-authorized estimated values from current average cost. No schema or migration was added. DEC-107 is resolved: open counts do not block transfers, and canonical settlement accounts for movements after the count snapshot. This is development-only; latest recorded Production application remains `0cf1214` at `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.49-pos-completion-r9-r16-0cf1214-r2`.

# 2026-10-25 — R9–R16 POS completion Production deployment PASS; batch closed

Merged application `0cf121450502713be385e041cbaf66e2a93ea66e` is live at `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.49-pos-completion-r9-r16-0cf1214-r2`; rollback is `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.48-post-pos-r1-r7-99c6f5d-r3`. Owner-provided deployment journal: `/var/lib/toyjoy/r9-r16-deployment/0cf1214-r2-20261025T101832Z`. The corrected dual-release gate passed 24/24 on R1–R7 and 24/24 on R9–R16 after r1 QA-harness defects were fixed. Candidate, activation and public gates passed; zero pending migrations, none run, canonical payment invariant PASS, no new Laravel errors, PHP 8.5 FPM reload PASS, public health PASS, maintenance LIVE and database rollback NO. Prior disposable/integration mutable POS evidence remains 42 PHP tests/319 assertions, one Node test and 127 authenticated Chromium assertions. This docs-only main commit does not change the deployed application SHA or require another deployment.

# 2026-10-25 — R1–R7 operational repairs Production deployment PASS

Merged/deployed application SHA `99c6f5da55efe339fb07057f5703feed9302dcc5` is active at `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.48-post-pos-r1-r7-99c6f5d-r3`; rollback target is `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.47-pos-theme-r8-b762e57-r4`. R1 quantity formatting, R2 purchase draft persistence, R3 transfer approval/receiving drafts/final receipt, R4 transfer/add/deduct navigation, R5 purchase/transfer barcode import and destination sale pricing, R6 authorized POS context inheritance, and R7 stocktake difference review are in the deployed application. R8 Light/Dark, theme persistence, POS state, and canonical payment modes remain available.

The approved `2026_10_24_092522_create_stock_transfer_receiving_drafts` migration is Ran in batch 41 and was not rerun in the final deployment. The preserved pre-migration backup is `/home/rajeh/shared/toyjoy/backups/rajeh_toyjoy_20261025T053617Z_pre_99c6f5d.sql.gz`, SHA-256 `df120219191337f00806520a361149092c7a218fff9b43561ec1573b1fa6983e`. Owner-confirmed journal `/var/lib/toyjoy/r1-r7-deployment/99c6f5d-20261025T054330Z` reports real Apache candidate and public authenticated browser PASS (60 assertions each), RTL/Cairo, no overflow or console/page/HTTP 500 errors, maintenance LIVE, rollback required NO. Earlier r1/r2 stopped safely without activation and remain forensic releases.

The next active development is the separate POS-only R9–R16 branch handled by User 2. This main-only documentation update does not change or redeploy the active application SHA.

# 2026-10-24 — R8 POS theme Production deployment PASS

Application SHA `b762e57002c78e9444475a5409a49f2826369152` is active at `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.47-pos-theme-r8-b762e57-r4`; previous `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.46-pos-strict-parity-a61b758-r2` is preserved for rollback. R8 Light/Dark UI passed authenticated Chromium on the real Apache-vhost candidate and after activation, including theme persistence and POS state preservation. Payment invariant after activation passed; payment configuration was not changed. No migration ran and zero remain pending.

Owner-confirmed `/var/lib/toyjoy/r8-deployment/b762e570-20261024T181522Z` reports immediate PHP 8.5 FPM reload PASS, public health PASS, no new candidate/after-activation/public Laravel errors, maintenance UP and rollback required NO. Failed r1–r3 candidates remain forensic. R1–R7 remains separate and unmerged; next POS batch R9–R16 has not started. This main-only documentation update does not change the deployed application SHA or require redeployment.

# 2026-10-24 — POS strict reference parity Production deployment PASS

Owner-approved V3 POS visuals and final uncapped dynamic category browser are merged/deployed at application SHA `a61b75826f9dc61c268be290e1daf2899aca9f3a`, active `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.46-pos-strict-parity-a61b758-r2`; previous rollback release `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.45-product-card-formatting-41ab1ed`. Products remain left, unified transaction right, exactly two desktop columns, 4/4/2 category columns at 1440/1024/390, real canonical category records and payment methods. Customer/invoice lookup, customer code and governorate rule, PRINT/EMAIL delivery, editable quantity, and multi-ticket state are retained. The post-POS R1–R5 batch has not started.

Disposable MariaDB category test 1/2 and authenticated Chromium 85 assertions passed using 30 real QA-model categories, including 25/30 product filtering. Production backup `/home/rajeh/shared/toyjoy/backups/rajeh_toyjoy_20261024T083342Z_pre_a61b758.sql.gz` SHA-256 `bc722816fcb85ce606239a93b4dfc3a75274e8b6fc71245d97f6017a2ccd1325` preceded the sole additive invoice-delivery migration (batch 40). Final `r2` Apache-vhost candidate and public authenticated POS passed at 1440/1024/390, health/assets passed, PHP 8.5 FPM immediately reloaded, maintenance OFF, no new final-candidate/public Laravel errors, no final rollback. Initial candidate failed asset-read permission and was rejected before activation; separate corrected immutable `r2` passed. Five `.ai` files are updated only on main after success.

# 2026-10-22 — Product Card formatting and Arabic localization Production deployment PASS

Merged/deployed application SHA `41ab1ed1f537c937becad19ec08e7977d8b8471c` is active at `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.45-product-card-formatting-41ab1ed`; rollback target `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.44-sales-invoice-shortcut-626867f`. Production identity, exact main/origin, clean tracked tree, approved three-file application delta, zero pending migrations and healthy free space passed. The delta reuses canonical ProductQuantity and money presentation helpers and adds Arabic UI translations, without DB precision, stored-value, schema, migration or business-rule changes. No DB backup was required for this presentation-only release; no migration or seeder ran.

Built the exact immutable final-path release with shared links, unchanged validated dependencies/assets and final-path Laravel caches as `rajeh`. Candidate and public actual Apache-vhost public routes, assets and authenticated Dashboard/Product Cards returned 200. Arabic and English browser checks at 1440 and 390 showed top-products `5`, meaningful Product Card quantity decimals and two-decimal money; Arabic interface labels/messages and English locale passed. RTL/Cairo passed in Arabic, English LTR remained valid, and there were no NaN values, console errors, page-level overflow, or browser write requests. Atomic activation and immediate PHP 8.5 FPM reload passed, maintenance OFF, new Laravel errors NO, final rollback NO; disposable sessions revoked. Evidence: `/tmp/rajeh-product-card-deploy.DhzYQl/`.

Permanent continuity rule: feature/hotfix branches leave the five canonical `.ai` Production-state files unchanged; update them on main after the final deployment result. Exact next Owner UAT: view genuine Product Cards in both locales and Dashboard quantities, then monitor the release while keeping the rollback target available.

# 2026-10-22 — Sales Invoice / POS shortcut Production deployment PASS

Merged and deployed application SHA `626867f05cb40dd5bbced82892596fa1b2d09f0c` is active at `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.44-sales-invoice-shortcut-626867f`; previous rollback release `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.43-operational-polish-8429b0e-r2`. Production identity matched hostname `s4.instaparty.online`, Rajeh paths, deployed baseline source and `rajeh_toyjoy` database. Fresh compressed backup `/home/rajeh/shared/toyjoy/backups/rajeh_toyjoy_20261022T175310Z_pre_626867f.sql.gz` passed gzip readability and SHA-256 `9963f6150e6d6fe4f1fc74dd38b1d30194d6e705fca2a6997a0188c842e0f714`. Sole additive data migration `2026_10_22_150000_add_sales_invoice_create_permission` ran in batch 39, inserted one active permission, changed no schema, and left zero pending; no seeder ran.

The approved source separates POS `/pos` (`pos_sales.view` plus `pos_sales.create`) and standard invoice `/sales/invoices/create` (`sales_invoices.create`). Invoice save creates an idempotent draft, not a POS sale; it does not post stock, capture payment, or touch shifts. Prior focused disposable tests in the merged batch cover the four permission combinations and draft safety. No Production business form was submitted. The first candidate's temporary verifier incorrectly expected a sidebar on POS and safely rolled back; after correcting that verifier only, candidate and public actual-vhost checks passed. `/`, `/up`, `/login`, assets and authenticated Dashboard/POS/invoice-create returned 200; 1440/390 RTL/Cairo, no NaN, console errors or page overflow passed. Final atomic activation immediately reloaded PHP 8.5 FPM, left maintenance OFF and no new Laravel errors, and requires no final rollback. Temporary verification session revoked.

Permanent continuity rule: feature/hotfix branches do not change the five canonical `.ai` Production-state files; update them on main only after final deployment outcome. Exact next Owner UAT: confirm the two shortcuts and permission visibility with real operational users, then save a legitimate ordinary invoice draft and verify no stock/payment posting while monitoring this release.

# 2026-10-22 — Operational polish Production deployment PASS

Merged/deployed application SHA `8429b0e8a54ae07cddd9ced1aa5409455aa84b7b` is active at `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.43-operational-polish-8429b0e-r2`; rollback release `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.42-stocktake-final-parity-f8a71cb`. Fresh compressed backup `/home/rajeh/shared/toyjoy/backups/rajeh_toyjoy_20261022T130445Z_pre_8429b0e.sql.gz` passed readability and SHA-256 `276046dbace338bbd9ca1895d4588b71f53c1ae2b5d666a585099c6304ab5e57`. The only pending additive data migration `2026_10_22_090628_add_default_supplier_return_reasons` ran in batch 38, inserted 18 active reasons without duplicates or schema changes, and left zero pending; no seeder ran. The approved Barcode Label pricing fix, Supplier Return search reset, sidebar accordion, Dashboard Quick Actions/Approval Inbox/compact KPIs, reason catalog, and “أخرى” notes validation are live. A read-only existing-product label check resolved `PL-0001` and equaled canonical effective price; Dashboard displayed eight authorized shortcuts and two actionable approvals.

The initial candidate's unreadable Vite manifest produced an HTTP 500 and was immediately rolled back. A verifier-only wrong Approval Inbox URL caused a second protective rollback. The corrected same-SHA `-r2` release passed actual Apache-vhost candidate and post-activation checks: `/`, `/up`, `/login`, CSS/JS and authenticated Dashboard/Barcode Labels/Supplier Return/Approval Inbox, 1440/1024/390 RTL/Cairo, no NaN, no console errors or page overflow, single-open sidebar, preserved lower Dashboard widgets, and supplier reason options. Final activation atomically switched `/home/rajeh/laravel`, immediately reloaded PHP 8.5 FPM, and left maintenance OFF with no new Laravel errors after the successful attempt and no outstanding rollback. Production verification remained GET/read-only; prior focused disposable QA supports mutating paths. Evidence: `/tmp/rajeh-operational-polish-deploy.7F43cB/`.

Permanent continuity rule: the five canonical `.ai` Production-state files are updated on **main only after the final deployment result is known**; feature/hotfix branches must not modify them. This documentation-only commit follows the deployed application SHA and requires no second deployment. Exact next action: Owner UAT on real barcode preview, legitimate Supplier Return addition/search reset and “أخرى” notes, sidebar navigation, and Quick Actions/approval links for representative roles; monitor the active release.

# Historical — 2026-10-22 final Stocktake reference parity and Excel Import Production deployment PASS

Merged/deployed application SHA `f8a71cbb225c82867354ad90a95e54c0e624b84a` is active at `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.42-stocktake-final-parity-f8a71cb`, with `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.41-privileged-authority-83d516a` retained for rollback. The owner-approved Stocktake list/create/count/review reference structure and Arabic Excel Import (`.xlsx`/`.csv`) are live. Import uses `product_identifier` and `actual_quantity`, matches product code before active barcode, reports invalid/unknown/duplicate rows, remains retry safe and company/warehouse/session scoped, and does not post stock or settle. Prior focused QA passed 2 tests/26 assertions. Zero pending migrations; no migration or seeder executed. Actual Apache-vhost candidate and public checks passed `/`, `/up`, `/login`, CSS/JS, and authenticated stocktake pages at 1440/1024/390 with RTL/Cairo, no NaN, page overflow, console or Laravel errors; 390 product cards and 1440/390 import panel passed. No live empty count existed, so its layout relies on prior focused QA. The first controlled attempt rolled back after a verifier-wrapper exit; the completed retry activated the new release, immediately reloaded PHP 8.5 FPM, and left maintenance OFF with no outstanding rollback. No Production business form was posted. Physical camera remains Owner UAT.

Permanent continuity rule: update `.ai/CURRENT_TASK.md`, `.ai/PENDING_CHANGE_BATCH.md`, `.ai/PROGRESS.md`, `.ai/SESSION_SUMMARY.md`, and `.ai/TEST_RESULTS.md` on main after a final merged deployment; feature/hotfix branches leave these Production-state files untouched. The following docs-only main SHA is distinct from the deployed application SHA and does not cause another deployment. Next action: Owner imports a genuine workbook into an authorized live stocktake, reviews the session-only result and rejected rows, and completes physical-device camera UAT.

# Historical — 2026-10-21 privileged operational authority Production deployment PASS

Approved merged/deployed main `83d516a9fc547c836b097ce72bbe0f79452ad9c3` is active at `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.41-privileged-authority-83d516a`; rollback is `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.40-stocktake-reference-ui-d4b6a85` (`d4b6a854266662706701cb881d69844ecfbd4422`). Source gate confirmed the canonical `User::canBypassApproval()` recognizes `is_super_admin`, `system-administrator`, and `company-owner`, with normal-user denial and one-company scope. Focused pre-deployment QA passed 7 tests/105 assertions for self-approval, company isolation, audit retention, and idempotent posting. Production had zero pending migrations; none ran. Immutable build, shared links, final-path caches as `rajeh`, actual-vhost candidate/public GET checks for public assets and transfer, stocktake, purchasing views passed. PHP 8.5 FPM reloaded immediately after the atomic switch; maintenance OFF, new Laravel errors NO, rollback required NO. No Production business document was submitted for verification. Locked rule: all three privileged identities may self-approve/finalize within their company; normal-user restrictions, tenant isolation, state integrity, auditability, canonical posting, idempotency, and duplicate-posting protections remain. Next action: Owner UAT on genuine operational documents and release monitoring, with the rollback release retained.

# Historical — system-administrator authority mapping hotfix validated

The approved privileged-authority deployment of merged main `b9b67a79560af0dcae73c259630d8e47da6fe409` stopped before build: `User::canBypassApproval()` lacked the active `system-administrator` role and a transfer test still asserted that role could not self-approve. Production remains unchanged on `d4b6a854266662706701cb881d69844ecfbd4422` at `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.40-stocktake-reference-ui-d4b6a85`. Dedicated branch `hotfix/system-administrator-operational-authority` contains the narrow canonical mapping/test correction. Focused disposable QA passed 4 transfer tests/71 assertions and 3 shared purchase approval tests/34 assertions; changed PHP syntax, Pint, and diff hygiene passed. No migration, Production operation, merge, or deployment has occurred. The earlier merge summary below overstated system-administrator coverage and is superseded by this correction. Next action: owner review of the pushed branch; merge and deployment remain pending separate authorization.

# Historical — Stocktake reference UI deployed

Approved merged/deployed main SHA `d4b6a854266662706701cb881d69844ecfbd4422` is active at `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.40-stocktake-reference-ui-d4b6a85`; rollback is `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.39-live-stocktake-mobile-b037a59` (`b037a59f749b30cd8b458c090d434fac59131139`). Scope is UI-only stocktake list, create, count, review, and mobile count cards; no backend/domain/schema/route/controller/permission/business-rule change. Zero pending migrations; none run. Exact-source immutable packaging, Node 20 assets, shared links, and final-path caches as `rajeh` passed. Candidate and public actual-vhost 1440/1024/390 authenticated checks passed with RTL, Cairo, no NaN, no page overflow, and no console errors; four 390 px product cards displayed expected/actual/difference/status. Public `/`, `/up`, `/login` and assets returned 200. PHP 8.5 FPM immediately reloaded; maintenance OFF, new Laravel errors NO, rollback required NO. No Production business transaction or broad QA. Physical camera is PENDING OWNER UAT. Next action: Owner visual/device UAT and monitoring, preserving the rollback release. Earlier entries below are historical.

# Historical — 2026-10-20 live stocktake and mobile review fix deployed

Approved main `b037a59f749b30cd8b458c090d434fac59131139` is active at `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.39-live-stocktake-mobile-b037a59`, with `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.36-standalone-numeric-7e50a0b` retained for rollback. Both live-stocktake migrations were already Ran in batch 37 and zero migrations were pending. Candidate and public actual-vhost 390/1440 authenticated stocktake checks passed, including exact 390 px reconciliation containment, manual-add POST/CSRF UI, autosave/resume, scanner controls, and transfer state-preservation UI. PHP 8.5 FPM reloaded immediately; maintenance OFF, no new Laravel errors, rollback not required. No Production business transactions or broad QA ran. Physical camera remains PENDING OWNER UAT; next action is Owner device and operational UAT while monitoring the active release.

# 2026-10-20 — Approved live stocktake release blocked by mobile review overflow

Approved main/origin `7a5358f2ffd2a6c1babda7ae0bdc45931540e459` passed backup, migration-status, exact-source, final-path cache, manual-add focused test, and actual-vhost public/asset checks. Authenticated 1440 UI passed. The 390 px reconciliation review produced 622 px body scroll width, so the candidate was rolled back immediately. Active Production remains `7e50a0ba3c5b2e12325d6d439f72d4572985c1aa` with maintenance OFF, PHP 8.5 FPM reloaded, public `/`, `/up`, `/login` 200, and no new Laravel errors. The two stocktake migrations remain applied; no Production business test posting or seeder ran. Local branch `hotfix/live-stocktake-review-mobile-overflow` contains a one-class containment correction; disposable local 1440/390 browser checks pass. Next action: owner review/merge and approval of a new SHA before a fresh immutable deployment. Physical camera UAT remains pending.

# 2026-10-20 — Live stocktake Production attempt rolled back

Approved main `a12f1768301ec7b66b89b000cb098fa0609facb7` was activated as candidate `toyjoy-v0.1.37-live-stocktake-a12f176` after a verified backup and two additive migrations. Authenticated stocktake entry revealed a malformed manual-add form method, so the candidate failed and Production code was rolled back to `toyjoy-v0.1.36-standalone-numeric-7e50a0b` (`7e50a0ba3c5b2e12325d6d439f72d4572985c1aa`). Maintenance is off, PHP 8.5 FPM is active, and `/`, `/up`, `/login` return 200. Batch-37 additive schema remains. Corrective branch `hotfix/live-stocktake-manual-add-form` includes fix commit `9d7eb0bdd79db5e4bb660f41e107a5c023e6dc8e`; focused local MariaDB and authenticated 1440/390 browser manual-add checks pass. The branch is unmerged and undeployed; physical-camera and full Production UI UAT remain pending. Next action: owner review, separately authorized merge, and then separately authorized immutable release.

# Current Task — Shift close receipt and pending allowance (2026-10-13)

Implemented the scoped shift workflow changes from `origin/main` `7972d963`: cash-only blind close, canonical system-derived non-cash totals, pre-approval/final print sheets, operational release on submission, one open shift limit, two-pending cap, and explicit pending-open guidance. No schema change or production action.

## Preserved previous task

# Current Task — Audit 500 fix (2026-10-13)

Issue #9 root cause is stale root-owned Livewire island source files in shared runtime storage. The audit page now avoids request-time island compilation, and the deployment template clears only generated Livewire/Blaze caches before runtime. Focused audit page/export checks passed on the dedicated branch; production was inspected read-only and not modified.

## Preserved previous task

# 2026-10-13 — High-priority issues batch 1

D3 candidate on `issues/high-priority-batch-1` from `origin/main` `c4560c3`: #41 quotation line preservation, #33 actionable incomplete stock-count submission, and #7 international-barcode supplier-code validation are committed. #1 purchase approval, #2 POS rounding, and #11 shift approval/close passed focused checks without reimplementation. #9 production audit page 500 traces to root-owned Livewire compiled cache; export's production 500 has no corresponding Laravel exception and remains unproven. One Fast QA PASS 8/8, including 75 tests/485 assertions and compact browser smoke. Production was inspected read-only for #9; no production write, migration, merge, deployment, issue closure, or milestone/UAT advancement. Final documentation commit and task-branch push follow.

## Preserved previous state

# 2026-10-13 — POS close-shift header link hotfix

V1 on `hotfix/pos-close-shift-link`, source `d29816d`: the active POS status bar now links the signed-in cashier's open assigned shift to its existing `/pos/shift` blind-count form. Existing authorization, administrator override, approval, audit and financial logic are unchanged. Focused authenticated browser PASS 16 checks; isolated MariaDB checkout/rounding PASS 2/14; syntax, Arabic JSON and diff hygiene PASS. No Fast QA, merge or deployment. Commit and task-branch push follow.

## Preserved previous state

# 2026-10-13 — POS cash-rounding consistency hotfix

D3 fix on `hotfix/pos-cash-rounding`, source `548c0ef`: exact 550.00 cash checkout now succeeds with disabled rounding or enabled rounding whose denomination is missing; an actual nonzero adjustment still requires an explicit valid setting. Owner approved configurable positive cent-denominations, including 0.50; the existing 5 EGP version remains until changed. The canonical calculator and sale/payment snapshots are reused, with a separate audited versioned enable setting and bilingual cashier message. Focused clean MariaDB PASS 6/49; authenticated browser generic PASS 78 and targeted PASS 20; syntax/Pint/JSON/core Larastan PASS; Fast QA PASS 8/8 (75/485). Three pre-existing whole-file Larastan findings remain on unchanged payment-action lines. No migration, production access, merge, deployment, milestone gate or UAT advancement. Commit/push follow.

## Preserved previous state

# 2026-10-12 — Product Card cost visibility hotfix

D3 narrow correction on `hotfix/product-card-cost-visibility`, source `90e60bc`: the live Product Card Blade already had the field, but the missing `products_categories_brands.cost_view` permission row hid it. Canonical active System Administrator role now passes only this cost visibility/edit check, and the authorization seeder registers the permission for future ordinary grants. Focused isolated MariaDB PASS 1/13 with the row inactive; authenticated Arabic/English browser smoke PASS, including save/reload 10, blank edit preservation, and ordinary denial. PHP/JS syntax, Pint and diff hygiene PASS. No Fast Gate by owner instruction, migration, production mutation, merge or deploy. Commit/push follow.

## Preserved previous state

# 2026-10-12 — Product Card cost and purchasing defaults

D3 batch on `feature/product-initial-cost-purchasing-defaults`, source `031815e`: canonical nullable Product Card cost now supplies the shared PO/invoice default; blank costs stay null/blank, drafts leave current cost untouched, and approved invoices retain the sole purchasing current-cost update. Blank supplier/local code preserves existing data, including staged import. Arabic/English cost guidance, quick-product optional cost, and action-level purchasing lookup guards are included. Focused isolated MariaDB PASS 21/179 plus import 1/11; bilingual browser/readback PASS; syntax, JSON, Pint and controlled core Larastan PASS. Staged import retains pre-existing Larastan diagnostics outside changed lines. First Fast Gate could not migrate a second disposable MariaDB due `/tmp` capacity; stale cache cleanup enabled retry PASS 8/8. No migration, main merge, production access/deployment or UAT advancement. Commit and task-branch push follow.

## Preserved previous state

# 2026-10-12 — Final purchase-invoice hotfix scope verification

Read-only CodeGraph/ast-grep caller review found one shared approval-record edge: source type alone allowed a hypothetical non-approve purchase-invoice record to inherit the role exception. The corrected shared policy/action/audit condition requires both invoice source and requested action `approve`; PO, returns, generic workflows and their creator guards remain unchanged. Focused isolated MariaDB PASS 3/38, scoped Larastan and syntax PASS. No Fast Gate rerun, merge or deployment. Corrected branch commit/push follow.

## Preserved previous state

# 2026-10-12 — Purchase-invoice approval locale and administrator hotfix

D3 hotfix completed on `hotfix/purchase-approval-locale-admin` from source `7960c7a`. The invoice approval and receiving screen now has complete `ar-EG`/`ar` translation coverage; English remains LTR. The canonical active System Administrator role can approve its own purchase invoice via the existing permission, scope, transaction, and audit paths. Ordinary creator separation, optional distribution, stock and price posting, shortages, retry idempotency, and other purchasing workflows remain intact. Focused isolated MariaDB PASS 14/121; Arabic/English browser smoke PASS; single Fast QA PASS 8/8 (71/448). No migration, production access, main merge, deployment, or business gate advancement. Commit and branch push follow this status update.

## Preserved previous state

# 2026-10-07 - Owner-authorized integration of all GitHub branches

The owner now explicitly authorizes main integration of all six GitHub module branches and requires every branch to remain. All fetched tips are ancestors of the verified combined history. Integration merges: `cc0fb64` (Module 3), `d713e2e` (Module 9), `a2ec03a` (Module 7); the Module 5 tip already includes Modules 1/2. Focused PASS 52 tests / 500 assertions; 80 changed runtime PHP syntax checks, locale JSON, runtime diff hygiene, conflict-marker checks, Pint and one build passed. Only inherited Markdown hard-break/EOF whitespace was reported by the broad documentation diff check; historical reports were preserved. Existing policy/data/server blockers are retained. Main publication follows this verified closure; no production release or business gate advances.

## Preserved previous state

# 2026-10-07 — Fast Module 1 QA closure

BUG-01–16: 10 fixes approved and pushed separately, 4 already fixed/not reproduced locally, 2 blocked by policy or server configuration. Original clean base `7c3cf6d` preserved; integration code `65c9c42` published on `fix/module1-qa-fast-integration`. Overlap checks: 21 tests/164 assertions pass. Single final suite: 276 tests/6,424 assertions, 243 pass/32 pre-existing failures/1 FriBidi error; all 12 new cases pass (A=0/B=32/C=0/D=1/E=0). Narrow post-suite BUG-04 completion: 1 test/16 assertions pass. One build, changed-PHP syntax, locale, Pint and scoped static-delta checks complete; static delta 0 added with 34 existing diagnostics in six affected source files. No second full suite or review team, browser check, main change, production access/deployment or business gate advancement. Evidence: `.ai/qa/module1-fast-20261007/results.json`.

## Preserved previous state

# 2026-10-07 — Module 1 + Module 2 local QA closure

All ten Module 1 review lanes closed and accepted fixes integrated. Original 31 findings remain individually dispositioned in `.ai/qa/module1-20261007/FINAL_REPORT.md`; hardware/mail/remote and genuine policy limits remain explicit. Module 2: BUG-105/231/232/233/234/235 fixed with focused checks; BUG-106 persisted all 42 active permissions in the real isolated browser and did not reproduce locally. All seven task branches pushed/remote-SHA verified. Combined suite 264 tests/6,257 assertions: 228 pass, 34 fail, 2 errors; all new repair tests passed. Two changed legacy source-string assertions remain failing, not silently rewritten. Static delta zero added, final1,403; build/Blade/syntax/locale checks pass. Integration closure commit3e13a57 is pushed; no main merge or production deployment. Business gates unchanged.

## Preserved previous state

# 2026-10-07 — Module 9 cash/shifts QA fast batch

M9-01/02/03 `bfff4ed`, M9-04/05 display `47fcdef`, M9-06 `849e7d8`, M9-08 `52390ef` complete on `fix/module9-cash-shifts-qa-fast`. Focused 6/69, changed syntax/JSON, Pint and one build PASS; isolated XAMPP schema only. DEC-100 acceptance-reason requirement pending owner decision; M9-07 tooling only. Main unchanged; no production deployment, browser/UAT or Phase Gate advancement.

# 2026-10-07 — Module 7 Part 1 local Returns QA

Local D3 fixes and previously blocked P0-01..P0-10 completed on `fix/module7-returns-p1-fast`; 28 focused tests / 223 assertions PASS. Sellable versus damaged/quarantine posting, cumulative quantities, completion replay, maker-checker, Gift Receipt use/privacy, exchange settlement, search, locale and historical identity verified against dedicated XAMPP MySQL. Minimum fixture dataset retained locally. Runtime branch pushed; final closure commit follows. M7-06 inspection rejection/attachments remains Requires Owner Decision (DEC-103). Main unchanged; no production access, merge, deployment or milestone advancement.

# 2026-10-07 — BUG-121 local purchase cost fix

D3 implementation complete on isolated `hotfix/bug-121`: upgrade missing granular purchase-invoice permissions without reseeding roles or overwriting disabled entries, expose explicit cost-grant labels, and explain the disabled field. Focused MySQL success/denial/replay checks passed; draft 2 × 37.50 = 75.00 preserves sale price/WAC and creates no stock movement. Syntax/JSON/Vite passed. Local commit requested; production validation, migration application, and deployment remain outside scope. Main and the ongoing Module 7 checkout were not edited; no milestone advancement.

# 2026-10-05 — Permanent optional Product Card cost

Source fix and focused A–F verification complete; shared optional rule replaces final-save, action and import requirements. Saved costs and independent accounting snapshots preserved; costless editor save/create and browser DB readback PASS. Three focused tests/24 assertions PASS; static delta zero new (9 existing findings). DEC-091/evidence record the named native MariaDB authorization. Runtime `1d27f7b` committed/pushed and cleanly integrated/pushed to main; production `0.1.22-cost-optional` active, compact smoke PASS. Prior release preserved for rollback; disposable DB, credentials and sessions removed. Report-only closure publication follows without another deployment. No business milestone or full Gate advancement.

# 2026-10-04 — Phase 2 end-to-end runtime closure

Main runtime merge`3821af553daca932e36c8a49ab78f911e1cf2379` pushed; three fixes deployed in0.1.22-qa-phase2. Compact enforced-read-only smoke PASS, no failed jobs/new critical errors; exact final5 rollback preserved. Failed-helper tail alone resumed after automatic recovery. Final MSI 79.34 / 94.14 raw/reviewed,113/27 raw / retained survivors, 86 unchanged exclusions; new precision testsPASS with no new kills. Thirteen races and3,884 matrices preserved;54 new rational cases PASS. Actual GitHub CI unverified solely for external access; latest owner instruction permits integration. No business milestone/UAT advancement. Closure evidence:`.ai/qa/phase2-evidence/final-closure.json`.

# 2026-10-04 — QA Hardening Phase 2 focused progress

- Original 191 survivors classified; exact financial contracts and 3,884 deterministic generated calculation cases added.
- Eleven isolated financial/concurrency cases, thirteen contention races and five new invariants passed; minimal confirmed tax/allocation/deadlock runtime repairs remain undeployed.
- Final scoped mutation 434/547 kills after one affected-only replay: raw MSI 79.34%, 113 visible survivors; reviewed MSI 94.14% after 86 explicit equivalents/unreachable guards, 27 retained survivors.
- Controlled touched-code static baseline passed with no new errors; focused eight-file Semgrep zero findings. Previous broad audit/browser/restore results preserved.
- Fast PASS 104s and Release PASS WITH WARNINGS (0s preserved report assembly); actual CI/publication tracked in `.ai/qa/PHASE2_CHECKPOINT.md`; no business milestone or UAT advancement.

# 2026-10-04 — Permanent QA Gate continuation

Owner-authorized I4 QA tooling task on `feature/rajeh-qa-gate`, source `1ab5ab534ea76d60b2da2e732fcb6992b3fd7985`. Resumed the preserved checkpoint; completed Fast/Release tooling and only failed/unfinished validation. Fast PASS (15s final resumed invocation); Release PASS WITH WARNINGS (24s final resumed invocation), mutation MSI 53.41% with 191 survivors. Real disposable MariaDB accounting/inventory chain, populated restore, outputs, Semgrep and Admin/User/Cashier checks passed. See `.ai/qa/RESUME_CHECKPOINT.md` and `docs/qa/RAJEH-QA-GATE.md`.

Current named authorization includes the focused QA PHPUnit/Playwright/Semgrep/Infection scope and preserved native disposable MariaDB workflow; historical/general manual-only and XAMPP direction remain unchanged outside this gate. Completed tooling commit `704d82d` was pushed, fast-forward merged into main and pushed under explicit owner authorization. Retained private QA database/session/credential runtime was removed after preserving report/log evidence. Production final5 `ff75916` remains unchanged; no deployment, runtime application repair, broad audit, repeated passing coverage or milestone/Phase Gate advancement is claimed. Physical device/offline/client UAT and mutation/static debt remain limitations.

## Preserved previous state

## 2026-10-03 — Final Full-System Audit Closure

**Status:** Remaining authorized browser/output audit closed; confirmed defects repaired and deployed. **Readiness:** Operational application verified; unconditional trading/client handover NOT approved pending cashier/shift/opening configuration, populated financial/inventory UAT in disposable XAMPP MySQL/MariaDB, device/transaction-print/offline acceptance and next scheduled backup evidence.

**Application:** `599a5084f7c1c2bb0aee5d6a67fc5df3d9dfd0be`, tree `51129f22539a3ceebc14e6b1d0171896f9131886`, version `0.1.22-audit-final3`; `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.22-audit-final3-24f1c5a`. UI/source fixes merged via `a4d2604`, PDF repair via `66605f1` and cost disclosure repair via `10957d6`, and pushed to main; later report-only closure does not rebuild the artifact.

**Coverage:** 386 unique authenticated Arabic/English desktop/mobile renders; 23 safe-control assertions; shipped UI affected checks; focused independent cost-view export confidentiality checks; 25 output requests and 42 final continuation assertions; XLSX/XML/PDF integrity; isolated repaired bilingual PDF visual rendering. Completed initial source/CodeGraph/database/backup coverage remains preserved. No suite, broad rescan, CodeGraph reinitialization/sync or repeated passing browser matrix. One Vite build; PDF artifact reused it.

**Access:** Exactly two approved sessions for existing administrator ID 1 and normal user ID 3 were created without password-hash inspection or password/identity/grant changes, then immediately revoked after authenticated coverage. Private cookies/tokens deleted; no further session created. No production business fixtures/transactions; output GETs produced their intended operational audit events.

**Release:** New immutable PDF artifact SHA-256 `35eef98261e033575f4662a99a070b5d0b0ff03fce6654d5b17bfac3905f9286`; manifest `a0c96bbd206480b75db8e05c8b8bc5cfeded11af82b8583f3dd3b8d570c3aab7`; 14,816 files verified. Original e6d2d1e and af6874e archives preserved. Journal `/var/lib/toyjoy/audit-final3-599a508`; exact rollback target 24f1c5a. Final-root cache/autoload checks and the single final compact smoke passed, maintenance off, services active, failed jobs zero, cron/schedules present, `/up`/`/login` 200 and unauthenticated `/admin` 302.

**Report:** `docs/audits/RAJEH-FULL-SYSTEM-AUDIT-2026-10-03.md`; evidence `.ai/evidence/full-system-audit-2026-10-03/`. No milestone, phase gate, UAT or device sign-off advanced. Remaining acceptance constraints are documented, not represented as passing checks.

**Late D3 repair:** Confirmed product XLSX/PDF cost disclosure when export is granted without the independent cost-view permission. Server output now omits the cost header/value unless the existing cost-view gate allows it. Focused visible/hidden-cost shapes, XLSX content and PDF generation passed; no roles, permissions, grants, identities or sessions changed. Dedicated final3 artifact activated; prior release receipts are preserved. The final target compact smoke passed once after this code change.

## 2026-10-03 — Preserved Full-System Audit Continuation

Full audit remains incomplete. Source baseline `49f429420b576bb08f05ea757cf44aca9f8e9140`; existing `audit/full-system-client-readiness` worktree preserved. Observed application e6d2d1e is already active; fixed generated runtime caches that retained renamed staging paths, then passed six-path checks and only the previously failing backup regression. Missing deployment smoke passed once. New public login visual pass found a localized username-separator defect; source repair `b04924d4c973580249aa999adab34078935cefb3` is verified and pushed, release pending. Broad source/CodeGraph/database scans and passing historical checks were not repeated. Authenticated Admin/User coverage awaits explicit approval for exactly two temporary sessions after automatic review rejected the broader all-user/password-hash helper. See `.ai/FULL_SYSTEM_AUDIT_CHECKPOINT.md` and the comprehensive report. No phase gate or handover/UAT approval claimed.

## 2026-10-01 — Operational Documentation Pre-Merge Cleanup

- Confirmed original DEC-085/DEC-086 entries in `.ai/DECISIONS.md` at `47da09c15b3d568bb2880a3e8c6d00900fe4c63d` and their removal at `c68f87c20d79085705d3310f1e2c233679dad8e3`. Restored the original text with explicit historical provenance and scope; no historical execution permission is revived.
- Corrected the Translation Editor's erroneous DEC-085 reference to its actual DEC-087 runtime-translation decision, restored from the same source. No screen behavior or requirement changed.
- Confirmed `Ibrahim <ibrahim.maher.2503@gmail.com>` matches the current main and Hotfix51 authors. The owner permits author changes only if clearly stale/wrong; account-name difference alone does not prove that condition. Git identity, remote, and SSH authentication remain unchanged.
- Prior documentation commit `8ccb88c7e90b12883619793d3e1dd934653eadc6` is pushed on `docs/post-hotfix51-operational-baseline`. Cleanup uses the same branch/worktree for one documentation review and a new commit/push. Main remains `4182c08`, and recorded production remains Hotfix51 `b709193815c548bb0100a0b641171c5af7aaf314`. Merge, deployment, and application changes are excluded.

## 2026-10-01 — Post-Hotfix51 Operational Documentation

- V0 operational documentation updated on `docs/post-hotfix51-operational-baseline` in dedicated worktree `/home/rajeh/worktrees/rajeh-erp-post-hotfix51-operational-baseline`, created from main `4182c08e108da7cb3ed1a119470edb65a3681392` (`4182c08`). Commit/push are authorized handoff steps; no merge or deployment is included.
- Recorded production remains Hotfix51 application commit `b709193815c548bb0100a0b641171c5af7aaf314` at `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.22-hotfix51-b709193815c5-r6`; this is carried forward from deployment evidence, without new production access.
- Future work starts from current protected `main` in `IS-Intelligent-Solutions/rajeh-erp`, main worktree `/home/rajeh/worktrees/rajeh-erp-main`, using a new dedicated branch/worktree. The old hotfix50 worktree remains a historical Hotfix51 reference.
- `AGENTS.md` records the `rajeh_ahmed` account, SSH remote, branch publication rules, unchanged deployment boundary, and CodeGraph 1.6.0 / ast-grep 0.45.3 analysis and exclusion guidance. No index or tool configuration is changed by this task.
- Historical Hotfix51 evidence and all earlier progress entries remain preserved. No application or business requirement, milestone, Phase Gate, database, service, or deployment workflow changed.

## Historical Progress Records

The dated records below describe their session-time state. Earlier active/pending/deployment statements are not the current operational baseline.

## 2026-09-30 — Hotfix51 product/supplier finalization deployed

- Started from exact active Hotfix50 application commit `0ff36dd24542d8378f6e9c24784e992b1d56ad74` and preserved unrelated worktrees/history.
- Added the canonical/numeric supplier-code resolver, explicit final-versus-draft Product Card persistence, existing-draft identity-barcode completion, separate international-barcode retention, authorized dependency-safe supplier deletion/deactivation presentation, and contextual product-form help.
- Focused rollback verification passed 50 assertions in isolated restored MariaDB `toyjoy_hf48_restore_368d44f2acea961a9a`. It covered exact `0002-000001` and `0123-000001` results, invalid-final no-draft behavior, same-ID draft completion/no duplicate, both local/international barcodes, authorized unused deletion/audit, linked restriction/deactivation/history, and unauthorized denial. No real supplier was deleted or deactivated.
- Deployed application commit `b709193815c548bb0100a0b641171c5af7aaf314` through a fresh encrypted backup, isolated restore/reconciliation, cache preparation, atomic activation, PHP-FPM reload, runtime restoration, read-only bilingual affected-page checks, and health checks. Active release: `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.22-hotfix51-b709193815c5-r6`; journal: `/var/lib/toyjoy/hotfix51-deployment/b709193815c5-20260930.4qsXOu`; recovery archive SHA-256: `536f3a2998cb0abc376d17e24dd5991ec373ec26050ba343abde1f036a858213`.
- Migrations and dependencies are unchanged, Hotfix50 assets were reused, no production product/supplier test record was created, and no in-scope blocker remains.

## 2026-09-29 — Hotfix50 permission labels and section selection

- Started from actual active Hotfix49 commit `5c25390599f8d9e431599f0695881123e0f27349`, not the older development checkout.
- Added one shared Arabic/English presentation catalog for all 412 current permissions and 34 business sections. The role-permissions UI no longer shows raw permission keys, and localized names/descriptions/search apply to ar, ar-EG, and en.
- Added per-section `تحديد الكل` / `All` controls with unchecked, indeterminate, and checked states. Each control changes the complete active assignable section set only, keeps protected permissions unchanged, and never persists before the existing Save action.
- Focused isolated browser verification passed in locked MariaDB `toyjoy_hf50_verify_2b798b36b23f`, including section isolation, partial state, Save/reload, full label coverage, RTL/Cairo, and English LTR. Evidence: `/var/lib/toyjoy/hotfix50-validation/2b798b36b23f`.
- Deployed application commit `0ff36dd24542d8378f6e9c24784e992b1d56ad74` through a fresh encrypted backup, isolated full restore/reconciliation, cache preparation, atomic activation, runtime restoration, corrected visible-text live Arabic/English page check, and HTTP health checks. Recovery journal: `/var/lib/toyjoy/hotfix50-deployment/0ff36dd24542-20260929.v4d2J5`; archive SHA-256 `113368a0d3c77c3dab45058a9fe72dd21643b0e711a40a0196b24466858da112`. No production permission/grant mutation occurred and no in-scope item remains.

## 2026-09-27 — v0.1.22-hotfix49 product-code and purchasing row corrections

- In progress from deployed Hotfix48 application commit `d59f5644dde57f14e5351194f3f3454ae1364d0b`, preserving post-deployment documentation commit `cc1dd2f90d06972009a643ed3dac5da5e5fd1c46`.
- Scope is limited to screen-reader-only product labels/aligned selectors in purchase-order and purchase-invoice draft rows plus prefix-shared `<four supplier-code digits>-<six digits>` generated Product Card identities.
- No migration or frontend build is needed. The single rollback-only focused check passed 23 assertions for row alignment/accessibility and code generation/preservation in `toyjoy_hf49_verify_2aa6628094c5`; deployment remains pending. See `.ai/HOTFIX49_CHECKPOINT.md`.

## 2026-09-27 — v0.1.22-hotfix48 purchasing and transfer corrections

- Completed the approved PO, invoice, approval-time product/supplier history, inline-product, supplier-return, transfer presentation/enforcement/numbering, and global System Administrator changes without a migration.
- Focused isolated MariaDB verification passed across non-overlapping rollback-only segments; immediate active System Administrator permission/scope behavior also passed and the disposable account is locked.
- Exactly one Node 20/Vite 8.2.0 production build and focused syntax/Blade/locale/diff checks passed.
- Deployed immutable application commit `d59f5644dde57f14e5351194f3f3454ae1364d0b` after a fresh encrypted backup and reconciled isolated restore. Atomic activation, runtime restoration, live identity/health, both administrator login/landing paths, and four affected authenticated pages passed. No in-scope item remains.

## 2026-09-26 — Hotfix47 authenticated setup redirect repair deployed

- Replaced the conflicting fixed `/dashboard` post-login destinations with one shared authenticated landing decision and intercepted dashboard/initial-setup entry before company readiness resolves a missing operational context.
- A global System Administrator with no visible active store now reaches the existing protected Users, Roles & Scopes setup step; no tenant visibility, administrator bypass, route middleware or verification rule changed.
- A fresh encrypted backup and isolated full restore passed before atomic activation. Focused live authenticated navigation passed for ID 2 (`karim`) and confirmed protected ID 1 (`hassan`) retains normal dashboard/setup access. Production serves application commit `3ed4a9529c45806175424f70e8ce05993e8233cb`.

## 2026-09-26 — Hotfix46 user management repair deployed

- Corrected the split between the active System Administrator role and bootstrap-only `is_super_admin` flag for user management, preserving operational tenant boundaries and approval behavior.
- Admin-created accounts are now immediately verified without email and record both creation and verification actors; the page lists exact validation messages.
- Repaired only audit-proven admin-created unverified user ID 2 (`karim`), preserving credentials, MFA/passkeys, active status, role and scopes. Focused isolated verification and live read-only verification passed; production serves commit `345c3af3f34a9e0ecd75e6694f3183f8f345cb99`.

## 2026-09-14 — v0.1.22-hotfix37 Customer Notes Batch 1

- Implemented the scoped navigation, contextual help, branch presentation, General Settings list-display preference, and user/role card cleanup directly from exact Hotfix36.
- Preserved routes, authorization checks, branch/warehouse/POS relationships, database schema/records, and business/transactional behavior. The focused syntax/render/navigation/accessibility/boundary verification passed, with one successful production Vite build and no database operation.

## 2026-09-08 — v0.1.22-hotfix23 sidebar layout repair completed

- Removed only the active-navigation `scrollIntoView` from `dashboard-assistant.js`; preserved the unrelated guided-tour and product-validation calls.
- Deleted the superseded generated assets and ran exactly one clean replacement Vite 8.2.0 build.
- Actual Laravel layout rendering and the deterministic fallback proved Hotfix20 grid geometry, unique internal scrolling, unchanged document/body scroll, bounded active reveal, collapsed/RTL/LTR/mobile contracts, and final asset identity.
- Hotfix22 keyless navigation, Hotfix21 cash/split payments, Blade/PHP/Livewire roots, locale JSON, asset integrity, and diff hygiene passed.
- Browser screenshots remain unavailable after the single authorized Chromium install attempt failed with ENOSPC; no visual PASS is claimed.

## 2026-09-08 — v0.1.22-hotfix23 resumed deterministic proof (source/build blocker)

- The one authorized workspace-local Playwright Chromium install attempt failed with `ENOSPC`; it was not retried.
- The real Laravel dashboard/navigation render passed with exactly one explicit sidebar scroller and the final Hotfix23 asset identities.
- The deterministic final-bundle proof found a second active-navigation `scrollIntoView` in `resources/js/dashboard-assistant.js`, also present in `public/build/assets/app-641tFKz1.js`.
- Correcting this confirmed source defect requires another production Vite build. The current owner instruction forbids rerunning Vite, so final verification, commit, and packaging stopped fail-closed.

## 2026-09-08 — v0.1.22-hotfix23 sidebar layout repair (visual gate blocked)

- Created the isolated Hotfix23 branch from exact Hotfix22 commit `163548b2f10f217ebf989589701f98a5a60b49ec`.
- Removed the desktop `display:block` shell override, restored the Hotfix20 grid relationship, limited scrolling to `[data-sidebar-scroll]`, removed active-item `scrollIntoView`, and retained Hotfix21 payment plus Hotfix22 normalization behavior.
- One successful Vite 8.2.0 production build completed after an initial npm launcher failure occurred before Vite started or wrote assets.
- Mandatory 1920×1080 screenshot acceptance is blocked: no Chromium, Chrome, Firefox, Playwright browser payload, WebKit/Qt renderer, or cached local browser container exists. Git diff hygiene passed once during the preservation check; the remaining final verification, commit, and packaging were not run.

# UI/UX Release Progress

## 2026-09-21 — Hotfix45 purchasing form repair deployed

- Fixed purchase-order and purchase-invoice reactive line/document totals at their binding and Livewire-morph source, backed by the existing server calculators and decimal snapshots.
- Kept purchase cost distinct from consumer selling price; retained supplier/general/average-cost fallback, existing permissions and required validation, and moved ordinary explanatory notes into contextual help.
- Focused DOM interaction, discount/tax cases, isolated MariaDB save/reload, immutable packaging, fresh encrypted backup/restore, schema preservation, atomic activation, authenticated rendering, and live browser interaction passed. Production serves commit `7a327b93abc48987eab7238e6bcd125635413fbb`; no migration ran.

## 2026-09-18 — Hotfix43 deployed

- Deployed immutable candidate `c71d0ad0651e62aa30c537e0f75fb8fdc6b4d8cf` from exact Hotfix42 baseline after a fresh AES-256 encrypted database/private-storage backup and isolated full restore reconciliation.
- Applied only approved pending migrations 000117 and 000118; exact schema, ownership, monetary-preservation and inventory-preservation checks passed. Atomic activation, caches, PHP-FPM reload, runtime-control restoration, maintenance removal, HTTPS health and nine read-only authenticated affected-page renders passed.
- Deployment evidence is under `/var/lib/toyjoy/hotfix43-deployment/a8474409199802cfc3`; the known inventory exception remains exactly unchanged and unresolved. Owner decisions and external Excel/printer/scanner acceptance remain separate.

## 2026-09-18 — Hotfix43 candidate verification complete

- Owner-approved migrations 000117/000118 and all tax/company consumers are implemented. The restored MariaDB schema/data guards, focused finance/scope/workflow scenarios, affected authenticated browser paths, all 12 XLSX generators, and three localized PO PDFs passed within their recorded boundaries.
- Corrected the focused-pass finding that production has no product assignment rows: unassigned legacy products remain safely company-global only with compatible tax ownership; explicitly assigned products still require an active matching company/branch/store scope.
- Updated the full 84-row recovered matrix without changing original IDs/text. It now distinguishes verified completion, implemented-but-evidence-limited rows, external acceptance, unresolved owner decisions, and the exact approved inventory exception.
- Candidate commit/artifact, fresh production-consistent encrypted backup/restore, deployment, and live verification remain. Production is still exact Hotfix42 at this point.

## 2026-09-18 — Hotfix43 original-requirements correction in progress

- Verified exact deployed Hotfix42 `cb8c1f9637370e531f6ab23f634e70784742cf35` and created an isolated correction worktree from it.
- Implemented the first complete source pass for audited numbering, export, opening-inventory, navigation, printer/template, product/help, customer-group, supplier, PO workflow/PDF outcome and translation defects. Preserved unrelated worktrees, historic references and the known inventory exception.
- Final financial work is waiting only on the exact additive `purchase_invoice_lines.tax_inclusive` and `tax_settings.company_id` schema decision recorded in `.ai/HOTFIX43_CHECKPOINT.md`. No migration, final acceptance, commit, artifact, production write or deployment has occurred.

## 2026-09-08 — v0.1.22-hotfix22 navigation runtime normalization

- Preserved Hotfix21 payment and sidebar behavior while normalizing every renderer-facing navigation field before Blade access.
- Added deterministic fallback keys for keyless groups/items/subgroups and safe rendering for permission-filtered links, nested groups, headings, separators, and empty optional shapes.
- Authenticated database-free dashboard/navigation rendering, three permission profiles, the real keyless Initial Setup item, Hotfix21 payment/sidebar contracts, full Blade compilation, compiled PHP syntax, Livewire roots, locale JSON, and new Vite asset integrity passed. Exactly one production Vite build ran.
- No migration, database, production, deployment, activation, service, sudo, tag, push, or remote operation occurred.

## 2026-09-04 — v0.1.22-hotfix10 UI deduplication

- Removed only the upper standalone Roles & Permissions navigation item and the lower duplicate Product Catalog readiness summary.
- Retained the nested Basic Data → Essentials → Users, roles, and scopes entry, exact setup query parameters and active-state logic, plus the identical upper setup/readiness summary.
- PHP 8.5 syntax, database-fail-closed Blade cache compilation, locale JSON, source contracts, Vite/font manifest references, and diff/storage boundaries passed. No application frontend source changed, so the verified lock-matched Hotfix9 build was not rebuilt.
- PHPUnit and browser automation were blocked by the execution policy before launch and are not claimed. No database or production access occurred.

## 2026-09-03 — v0.1.22-hotfix9 navigation cleanup

- Removed the duplicated sidebar user/status footer, standalone System Settings link, three upper administration duplicates, the Party navigation group, and the second Pricing Lists readiness summary while preserving their underlying routes and behavior.
- Retained canonical Basic Data → Essentials destinations and Roles & Permissions. The canonical setup registry preserves `party-readiness` as disabled and exposes 20 active steps; readiness, adjacent navigation, numbering, and sidebar leaves exclude it.
- Focused source tests, syntax, Blade, locale JSON, Node 20 Vite build, isolated MariaDB route profiling, and authenticated Arabic/English desktop plus Arabic mobile browser checks passed without production/shared access.

## 2026-09-01 — v0.1.22-hotfix1 post-deployment correction

- Completed the owner-listed Arabic/raw-enum, Setup semantics, Product Filter deletion, balance reset, whole-unit quantity, Barcode Label, Product Card pricing, barcode graphics, printer sizing/template, Purchase Invoice card and version corrections.
- One focused gate passed 4 tests/59 assertions, PHP/JSON/diff checks, and the single Node 20 Vite build; prior eight-case bilingual print visuals were preserved. Only physical-printer output acceptance remains.
- Prepared an immutable local release without production/shared data, active symlink, deployment, tags, pushes or remotes.

## 2026-08-31 — v0.1.21 Hotfix 3 Readiness/Pricing/UAT Consistency

- Unified Product Card and pricing readiness through one current-data evaluator across Setup Center, persistent context, Products/Product Card, and Pricing Lists.
- Added localized bounded remediation, direct edit/view-all actions, and correct List 0 inherited/effective/manual-override explanations.
- Audited and retained every named correction through Hotfix 2 and the marked, positive-priced, guard-protected UAT fixtures without accessing or changing production/UAT data.

## 2026-08-31 — v0.1.21 Final Stabilization

- Started exactly from tagged `v0.1.20`; replaced the unsupported Purchase Invoice `barcode` Flux icon with supported `qr-code` and confirmed all other scoped M54 Flux references are installed.
- Added immutable-release cache exclusion and runtime-user `rajeh` Artisan ownership guidance without changing M54 behavior or frontend assets.
- The focused M54 test file passed 8 tests / 62 assertions; purchasing-invoice routes and the isolated six-contract Flux render probe passed. No database, migration, broad suite, frontend build, PDF, production, deployment, tag, remote contact, shared database mutation, or version bump occurred.

**Updated:** 2026-08-28
**Branch:** `feature/v0.1.13-guided-setup-admin-ux`
**Baseline:** published `v0.1.12`

| Milestone | Status |
|---|---|
| M50A — global shell, navigation, operational dashboard | Engineering foundation committed |
| M50A.1 — visual hierarchy, density, navigation, responsive and Arabic correction | Complete in development |
| M50B.1 — internal-page foundation and Sales & POS workspace | Complete in development |

M50B.1 adds reusable active-filter chips, bounded pagination, responsive resource rows, a scoped Sales Overview, refined invoice/customer/shift pages, consistent POS entry navigation, and the v0.1.5 desktop-sidebar recovery fix.

M50A.1 changed shared presentation, centralized module-dashboard specification, and bounded read-only dashboard composition only. The operational dashboard and representative Inventory overview use existing authorized data; no route, permission, mutation workflow, schema, migration, production runtime, or deployment state changed. The single locked build passed, and genuine local rendered evidence is stored outside Git in the approved `m50a1-evidence-20260827` directory.

| M50B.2 — Purchasing & Suppliers workspace | Complete in development |
| M50B.3 — Products, Inventory & Barcodes workspace | Complete in development |
| M50B.4 — Pricing, Promotions & Discounts workspace | Complete in development |
| M50B.5 — Parties, Bookings & Customers workspace | Complete in development |
| M50C — Reports, Administration & System Settings | Complete in development |
| M50D — Assets, Rental & Transfers workspace | Complete in development |
| M52 — v0.1.14 navigation, tables and action controls | Complete in development |
| M53 — v0.1.15 verified visual UX corrections | Complete in development |
| M53.1 — guided Setup Previous/Next navigation | Complete in development |
| v0.1.13 — Guided Setup & Administration UX | Complete in development |

M50B.2 adds the scoped purchasing overview, seven-day trend, receiving/approval/return indicators, bounded top/recent purchasing lists, and responsive supplier/invoice/order/return tables. Due/overdue balances are omitted because there is no authoritative payable ledger.

M50B.3 wires and expands the existing scoped inventory overview with authoritative product/variant, balance, valuation, movement, exception, transfer/count/adjustment, distribution, and top-movement information. Products, balances, movements, and label queues use the shared responsive list foundation. System Settings save controls now expose exact idle/action loading states.

M50B.4 adds an authorized-store-scoped Pricing dashboard, effective/scheduled price coverage, approval and deterministic exception indicators, bounded recent activity, responsive filters/sorting/pagination, and scoped label readiness. No standalone promotion campaign model exists, so unsupported promotion metrics and margin inference are deliberately omitted. The shell now reports all authorized stores honestly instead of displaying the first visible store as an implied active context.

M50B.5 adds a visible-branch/store-scoped Party dashboard, schedule and collections indicators, seven-day booking trend, upcoming agenda, service/payment summaries, responsive booking/invoice/order queues, and Party history on the existing customer profile. Package and venue master data are not fabricated because the authoritative model currently stores service lines and booking locations directly.

M50D adds an authorized-store-scoped Assets dashboard, asset catalog/detail, bounded availability calendar, reservation and custody actions, return exceptions, and immutable condition/event activity. Rental assets remain separate from inventory products. Standalone transfer requests, maintenance work orders, customer rental ledgers, and rental-revenue reporting are absent from the authoritative model and were not fabricated.

## 2026-08-28 — M50C Reports, Administration & System Settings

- **Task:** Implemented M50C from published `v0.1.10` on `feature/m50c-reports-administration`, without deployment.
- **Work completed:** Added a permission-aware Administration Center for readiness, users/roles, locations, configuration coverage, approvals, and redacted audit activity. Expanded the Reports Center with an authorized catalog, recent requester-owned export state, date presets, and removable filter chips. Added missing Reports/Administration navigation destinations and exact Livewire targets for authorization saving and document-counter correction.
- **Reporting boundary:** Preserved `ReportSnapshot`, visible-store/user scoping, bounded source rows, sales pagination/sorting, the 5,000-row export cap, snapshot hashes, requester ownership, locale restoration, XLSX formula protection, and PDF security.
- **Verification actually run:** Focused PHP/Blade syntax, route/navigation/export mapping, `git diff --check`, and one locked Vite build with Node 20.20.2. Manifest SHA-256: `a9e9896affa4baf7ad19a4febdf6de76bf6cf84475b4e9acc277054c7378d94f`; every referenced asset exists. Automated tests and browser control were not authorized, and no database runtime was used.
- **Boundaries:** No production/shared runtime, database, migration, deployment, tag, remote/network, cPanel, Apache, cron, or Toy & Joy WordPress access occurred.
# 2026-08-28 — v0.1.13 guided setup and administration UX

- Implemented validation-first sequential settings controls over the existing save actions, permission-filtered 21-step setup guidance, branch-to-outlet continuation, compact administration tables/mobile cards, and clarified Sales Outlet/Cashier Till terminology.
- Preserved readiness queries, direct routes, permissions, settings validation/actions, branch/store/till/shift relationships, schema, and business mutations.
- The single locked Vite build completed with Node 20.20.2; manifest/reference, focused syntax, route/permission mapping, and `git diff --check` passed. No automated tests, browser session, database operation, deployment, tag, push, or remote access occurred.


M53 corrects the UI inventory to all 22 authoritative Setup steps, fully localizes and persists Setup context, replaces unreliable Flux idle-loading presentation with exact action-scoped controls, completes the 79-view semantic action adoption matrix, standardizes logical table spacing, and verifies active navigation and responsive mobile cards with genuine Arabic/English rendering. The mobile Sales table surface no longer remains horizontally scrollable after conversion to record cards.

M53.1 completes the primary Setup context with permission-filtered adjacent navigation derived from `InitialSetupStatus`. It preserves Setup query context, resolves nested pages to their owning step, keeps the Settings secondary navigator, and aligns both Setup group maps with the authoritative service order so Opening inventory remains step 22.

## 2026-08-29 — v0.1.16 Setup and Administration Foundation

- Replaced duplicated 22-step UI maps with one 21-step route/group authority (5 Basics, 5 Settings, 11 Operational Readiness); Product Import remains available from Product Cards but is no longer a Setup step.
- Added persistent route-owned Setup context, passed/failed readiness reasons, company-scoped append-only skip/defer decisions, and continuation that bypasses decisions until unresolved required deferred work must be surfaced.
- Supplier readiness now requires an active supplier with an active financial-settlement method only. Product/SKU linkage and payment terms remain optional capabilities.
- Settings persistence is separate from Previous/Next navigation, core forms use exact-target Save controls, and the Cashier template remains location-scoped through existing user/store/till mechanisms.
- Genuine guarded rendering passed 11 Arabic/English desktop/mobile cases with 21 authorized Setup cards, one sticky primary context, one Settings secondary navigator, localized reasons and navigation, visible Cashier least-privilege scope, Save labels, Cairo, zero document overflow, zero idle spinners, zero console/external requests, and zero serious/critical Axe findings. Existing built assets were reused; manifest SHA-256 remains `dabbde0ffadfb86f62400eb67668010949dd1b7eeb931174c7c72a0958e988b0`.

## 2026-08-30 — M51 v0.1.17 Customer and Supplier Data Management

- Added reusable hierarchical customer/supplier-group presentation, stable codes, ancestry-preserving search, leaf-only new customer assignments, and filtered XLSX/PDF exchange.
- Centralized customer phone identity, added Egyptian governorate/city residence data and tenant city administration, and preserved legacy customers without destructive backfill.
- Added fixed supplier settlement methods and aligned Setup readiness to an active configured supplier while retaining optional terms and supplier-product references.
- Added staged company-scoped XLSX imports, partial valid-row processing, localized rejection workbooks, formula-safe exports, and bounded filtered customer/supplier exports.
- Focused tests, syntax/routes/diff checks, isolated MariaDB migration, and 13 genuine Arabic/English desktop/mobile captures passed. Existing frontend assets were reused; manifest SHA-256 remains `dabbde0ffadfb86f62400eb67668010949dd1b7eeb931174c7c72a0958e988b0`.

## 2026-08-30 — M52 v0.1.18 Product Cards and Opening Inventory

- Added modal Product Filters management, two-stage Product Cards, exact international/supplier-local barcode policy, and an explicit reusable pricing-update contract.
- Added protected partial XLSX import and filtered XLSX/Cairo-PDF exchange for products, categories, and brands.
- Added dedicated immutable Opening Inventory draft, preview, transactional approval, reversal, and audited zero-opening decision; Setup step 21 now resolves to this workflow.
- Focused tests passed 11 tests/33 assertions; migration 000097 passed on isolated MariaDB; 10 genuine Arabic/English desktop/mobile captures passed with Cairo, zero serious/critical Axe findings, console errors, external requests, and document overflow. Existing frontend build retained: dabbde0ffadfb86f62400eb67668010949dd1b7eeb931174c7c72a0958e988b0.

## 2026-08-31 — M53 v0.1.19 Pricing Lists

- Added company-scoped protected List 0, reusable percentage lists, decimal-safe ceiling-to-5 pricing, dated manual product overrides, and outlet/branch price-list assignment.
- Added an authoritative effective-price resolver and snapshot DTO while preserving Product Card base prices, approved price versions/lines, costs, invoices, and completed sales.
- Added the responsive Pricing Lists workspace, Product Card pricing matrix, assignment controls, least-privilege permissions, audits, navigation, and factual Setup readiness reasons.
- Focused pricing tests passed 6 tests/28 assertions; migration 000098 passed on isolated MariaDB. Five genuine Arabic/English desktop/mobile cases passed with Cairo/RTL/LTR, zero serious/critical Axe findings, overflow, console errors, or external requests. Frontend sources were unchanged and the existing manifest was retained.
# M54 — v0.1.20 Purchase Receiving Distribution (2026-08-31)

- Implemented the full-page two-stage workflow, lookup/scanning, in-context Product Card creation, exact distribution matrix, outlet price snapshots, destination labels, transactional idempotent posting, Product Card cost/List 0 audit updates, reversal, and eight least-privilege permissions.
- Added one additive migration (`000099`). Existing Setup sequence remains the single context and Product Import remains inside Product Cards.
- Focused M54 PHPUnit passed 5 tests/29 assertions. Migration `000099` passed on disposable MariaDB `rajeh_m54_20260831`; the database/server were removed. Focused syntax, routes, Arabic JSON, and diff checks passed.

# v0.1.21 Final Acceptance (2026-08-31)

- Added distinct scanner/manual/camera entry, bounded three-character autocomplete, duplicate quantity focus, unknown-barcode Product Card continuation, and explicit draft stage/next-action/remaining-quantity presentation.
- Added company-bound UAT batches and allowlisted marked records with explicit dry-run seed/purge commands, generated-on-execution credentials, Golden Path, and coverage report.
- Corrected pending approval source-version synchronization after Stage 2 distribution, found during the isolated UAT journey.
- Focused tests passed 12/95; isolated MariaDB seed/purge/reseed lifecycle and Node 20.20.2 build passed. Manifest SHA-256: `4d41c4fce936e8ba68f79ec69c89c36178f4481a8fa660774cce61de4894fe3f`.

# v0.1.21 Purchasing Localization/Layout Hotfix (2026-08-31)

- Completed Purchase Invoice Arabic/English keys, locale-safe names/status/currency/quantities, responsive list records, aligned entry/search controls, and empty-line suppression.
- Persisted the explicit production UAT environment confirmation and real purchase-sequence reuse safeguards.
- Focused tests and eight-case desktop/mobile bilingual visual evidence passed; no database or production access occurred.

# v0.1.21 Purchasing Search/Barcode Hotfix 2 (2026-08-31)

- Separated exact scanner lookup from a cancellable ranked 175ms smart search with immediate Enter, company context, fragment matching, loading discipline, and a 20-result bound.
- Replaced the mixed-mode UAT product with separate GTIN and allocator-backed local products; new UAT prefix state is rejected if it would touch an existing real sequence and all created identities are marked for purge.
- Corrected the malformed Product Card `$isEditing` directive and completed the focused bilingual create/edit audit and evidence.
## 2026-09-02 — v0.1.22-hotfix4 inventory labels/sidebar/pricing

- Centralized strict whole-number product quantity presentation and validation across affected inventory, purchasing, retail, Party-consumable, reporting, and print flows; genuine fractions now fail rather than round, while monetary precision remains unchanged.
- Centralized inventory movement labels in Arabic and English, rebuilt barcode-label selection around bounded server search and preserved multi-selection/remediation state, corrected document-scrolling desktop sidebar behavior, and made positive Product Card base consumer price the immediate selling fallback.
- Focused PHPUnit, PHP/Blade/JSON/routes/diff checks, authenticated Arabic desktop/mobile browser checks, barcode PDFs, and isolated MariaDB migration status passed. Immutable release preparation remains the final packaging action; production remains untouched.

## 2026-09-02 — v0.1.22-hotfix5 performance

- Profiled 20 authenticated workflows against the exact Hotfix4 source and candidate using the same isolated 10,000-product/100,000-movement MariaDB fixture.
- Removed shared-render permission/translation/readiness duplication, overview-only inventory aggregates from child pages, the suppliers' 10,000-row response payload, and unbounded pricing-readiness materialization; added bounded index-friendly exact/prefix search.
- Added four measured reversible indexes. Warm candidate medians remained below 800 ms for standard routes and below 1.5 seconds for Reports; the largest gains were Suppliers (71%) and inventory child pages (40–51%).

## 2026-09-03 — v0.1.22-hotfix5 corrective no-regression follow-up

- Proved that the original product status/parent/code index caused a full active-product index scan and filesort on the shared sellable-product query; removed it while retaining the three beneficial Hotfix5 indexes.
- Made pricing proposal options, dashboard work and version pagination conditional, reused the unpriced paginator count, and added version-invalidated readiness metadata reuse without caching effective prices.
- Focused exact-baseline benchmarks now improve Products, Pricing and Unpriced response and SQL medians. Affected tests pass 9/9 with 88 assertions and zero errors.

## 2026-09-03 — v0.1.22-hotfix6 admin navigation/localization

- Centralized the 21 real routable setup definitions (5 Essentials, 5 Settings, 11 Operational Readiness) and rendered every authorized leaf in the desktop/mobile Basic Data sidebar while preserving the existing overview and setup workflow.
- Standardized user-facing Arabic warehouse terminology to مخزن/مخازن, clarified the combined Warehouse / Point of Sale create workflow, and corrected bilingual Audit Log event/source presentation, dates, warning copy and responsive layout.
- The sidebar uses static canonical metadata and the existing request permission lookup, never readiness/catalog evaluation. Shared-layout profiling retained 5 queries with no duplicates and no meaningful latency regression.
## 2026-09-03 — v0.1.22-hotfix7 inventory counting UX

- Removed active company-wide sales-outlet fallback from POS/readiness; shifts require an explicit authorized outlet/cash-drawer context and confirmation.
- Added authorized store navigation, centralized localized human-name-first rendering, bounded balance/product lookup, improved store filters, and name-first exports.
- Extended stock counts additively with immutable location snapshots, teams/assignments, append-only idempotent contributions and corrections, explicit zero/completion, transfer locks, cutoff-aware reconciliation, per-location adjustments, permissions, audit events and bilingual reporting.
- Focused verification used disposable MariaDB and local Chromium only; evidence is under `.ai/evidence/v0.1.22-hotfix7`.

## 2026-09-03 — v0.1.22-hotfix8 performance/UI

- Removed confirmed Hotfix7 inventory child-route over-fetching while preserving counting and scoping behavior; balances production-read median improved from 263.9 ms/30 queries to 182.4 ms/18 queries.
- Corrected the balances filters for one balanced desktop row and stacked no-overflow mobile layout. Evidence is under `.ai/evidence/v0.1.22-hotfix8`.

## 2026-09-03 — v0.1.22-hotfix9 PHP 8.5 view-cache correction

- Replaced the oversized `resources/views/pricing/labels.blade.php` body with seven bounded Blade partials while preserving its form, scanner search, barcode remediation, advanced settings, Alpine state, authorization branches, and Hotfix9 navigation.
- PHP 8.5.9 `optimize:clear` and `view:cache` passed with task-local file cache/session overrides. Authenticated Arabic and English browser verification passed from isolated `toyjoy_hotfix9_viewcache_verify` with zero console/page errors or overflow.
- Preserved Hotfix8 performance source, vendor, Livewire compilation, PCRE configuration, `.env`, storage and public-storage boundaries; `storage/storage` was not created. Production was untouched and promotion was not executed.

## 2026-09-04 — v0.1.22-hotfix11 company-owner RBAC release

- Finalized the approved D3 company-owner RBAC and Approval Inbox navigation source as `0.1.22-hotfix11`; no D3 functional file changed after its focused 4-test/49-assertion pass.
- Preserved super-admin-only Administration Center and Activity Log access, company-bound owner permissions, one correctly positioned Approval Inbox item, and the unchanged secure interactive Karim provisioner.
- Reused the focused authorization, PHP syntax, and Blade compilation results and ran the single direct PHP 8.5 version assertion. No Composer, Vite, database, production, deployment, provisioning, migration, service, HTTP, tag, push, or remote action occurred.

## 2026-09-04 — v0.1.22-hotfix12 POS shift-opening route

- Corrected the `/pos/shift/open` GET 404 with a permission-gated compatibility redirect to the existing shift page, separated the canonical POST route name, and redirected successful creation to POS.
- Preserved the unchanged shift action and its outlet/drawer authorization, transactions, idempotency, opening-balance rules, and duplicate-active-shift prevention.
- One focused database-free feature test passed with 1 test / 24 assertions. No Composer, Vite, database, browser, production, deployment, migration, seeder, service, HTTP, tag, push, or remote action occurred.

## 2026-09-05 — v0.1.22-hotfix13 cash drawer context integrity

- Traced production drawer code `TEST-REG-0001` to the raw UAT dataset insert, which omitted `cash_drawers.company_id` while inserting its valid store and branch and bypassed the guarded drawer action.
- Made selected-store ownership authoritative, added model-level company/branch/store validation for Eloquent creates and updates, corrected the UAT/production/remediation seeding paths, and found no cash-drawer import path.
- Added migration `000107`: it audits all drawers in deterministic ID order, backfills company only from an unambiguous valid store/branch/company chain, reports all ambiguous IDs/codes/reasons before changing anything, then enforces non-null positive company IDs and the restrictive company foreign key.
- Invalid drawers are excluded from shift selection; shift opening retains scope checks, accounting, locking, idempotency, and duplicate guards while returning a localized configuration-validation message instead of context `findOrFail` 404s.
- Focused Hotfix13 regression passed 4 tests / 43 assertions. Changed PHP syntax, database-free route registration, Arabic/English JSON, and diff hygiene passed. The earlier combined harness attempt reached zero assertions because the translation override loader attempted the deliberately unreachable database; no database connection or mutation occurred.
- Karim/company-owner permissions, the permission-search display defect, and the pending V0 UI batch were untouched. Production, database, active symlink, services, tags, pushes, and remotes were untouched.
## 2026-09-05 — v0.1.22-hotfix14 pricing and POS cash rounding

- Confirmed the canonical M53 percentage-derived price resolver already used BC Math ceiling-to-5 while base prices and effective manual overrides bypassed it; named the 5 EGP increment explicitly and left all materialized/historical prices untouched.
- Added append-only migration `000108` to establish `pos.cash_rounding_denomination=5.0000` only when needed, retaining setting history and exact Hotfix13 rollback compatibility.
- Preserved nearest-denomination rounding for cash settlement only, kept non-cash totals exact, and converted missing/invalid cash configuration into a localized cash-only disabled state instead of an unhandled checkout/render exception.
- One database-free focused pass completed 20 arithmetic/source assertions; changed PHP syntax, POS routes, Arabic/English JSON, and diff hygiene passed. No database, production, browser, Composer, Vite, tag, push, or remote operation occurred.



## 2026-09-06 — v0.1.22-hotfix16 POS checkout and multi-customer workflow

- Replaced the session-only POS cart with durable open orders scoped to company, branch, selling outlet, cash drawer, shift, cashier, and idempotency token; each order independently retains its customer, cart/approval payload, tax choice, notes, payment draft, totals, and lifecycle.
- Added bounded indexed customer lookup and compact in-POS customer creation/attachment, responsive Arabic-first order tabs, cash/electronic/multi-card/mixed payment flows, safe references, success modal, and scoped configured receipt preview with explicit browser-print fallback.
- Sale completion locks the open order and active shift, rehydrates its durable lines, and delegates inside one transaction to the existing sale/payment/inventory/tax/accounting/audit/receipt contracts; Hotfix14 cash rounding remains cash-only.
- Focused database-free verification passed after correcting only failed checks: all four authorized source-contract methods have passing results, 28 assertions passed in the corrected method, all changed/new PHP syntax passed, four changed Blade templates compiled and linted, 45 POS routes registered without duplicate names, 133 Hotfix16 locale keys have Arabic parity, diff hygiene passed, and Vite inputs were unchanged.
- No database, migration execution, browser, live HTTP, production access or mutation, backup execution, deployment, activation, service action, Composer install, Vite build, tag, push, or remote contact occurred.

## 2026-09-06 — v0.1.22-hotfix17 POS compact redesign and refund workflow

- Reworked only the POS presentation and Livewire coordination needed for compact desktop proportions: persistent order tabs are inside the cart, customer and product search are unobstructed and automatic/server-bounded, payment choices are compact selectable cards, and checkout is a wide responsive cash/card/split dialog with distinct insufficient-cash and customer-change states.
- Added a seven-step in-POS refund wizard over the existing returns foundation. It supports verified source lookup, full/partial remaining quantities, condition and authorized return location, original split allocation, cash and store-value alternatives, independent approval/rejection, success/view/print actions, and localized safe errors.
- Extended return persistence with deterministic reversible migration 000111 and strengthened completion with source-sale serialization, row locking, final quantity/location/value/tax/rounding revalidation, completion payload idempotency, exact-outlet cash shift/drawer checks, append-only payment snapshots, reversing inventory movements, shift reconciliation, audit events, and configured sales-return printing.
- Implementation is complete. The one authorized consolidated database-free verification pass and release packaging remain; no test, browser, database, production, migration, deployment, backup, service, tag, push, or remote operation has run during implementation.
- The single Hotfix17 consolidated database-free verification passed: 17 changed/new PHP sources linted, all changed/new Blade sources compiled and linted, focused POS/refund route and source contracts passed, Arabic/English locale parity and diff hygiene passed, and Vite was skipped because no frontend input changed.
- Hotfix17 is ready for the clean release commit and offline immutable operator package. Migration 000111 remains unexecuted and production remains untouched.

## 2026-09-07 — v0.1.22-hotfix18 POS Blade repair

- Retained exact Hotfix17 UI and refund behavior while repairing the compiler-confirmed nested POS/checkout return-permission directives, cart inline PHP assignments, and PHP 8.5 purchasing-return print fallback.
- Hardened the derived operator scripts so `bootstrap/cache` is created with `rajeh:rajeh` ownership and mode `0770` before Artisan, all Artisan commands execute through `runuser` as `rajeh`, exact active Hotfix16 is required, and migration 000111 may already be Ran.
- The single focused database-free verification passed all real Blade compilation and compiled-PHP syntax checks, including the POS view; changed PHP syntax, locale JSON, diff hygiene, route registration, and operator-script Bash syntax passed. The existing harness does not support authenticated POS rendering without a database, so no render PASS is claimed.
- No frontend input changed, so Vite was not run. No Composer, broad suite, browser, database, production access, migration, backup, deployment, service action, tag, push, or remote operation occurred.


## 2026-09-07 — v0.1.22-hotfix19 POS Livewire root and release-cache repair

- Retained all Hotfix17/18 POS and refund behavior while placing the conditionally displayed refund wizard inside one permanent Livewire root. The other changed POS Livewire templates already had permanent single roots and were left structurally intact.
- Corrected derived promotion/activation preparation so the extracted release gets bootstrap cache at mode `0775` with recursive `rajeh:rajeh` ownership, all Artisan commands run as `rajeh`, and writability is proved before and after cache generation.
- The remaining database-free pass compiled all 231 Blade templates through the registered Laravel/Flux/Livewire compiler, confirmed the four POS Livewire roots and closed refund render contract, and linted the changed compiled refund view. Host-user `rajeh` ownership/cache proof is deferred to the fail-closed activation preflight.
- Hotfix19 adds no migration; 000111 is required to remain already Ran. No broad test, browser, Composer, Vite, database, production access, migration, deployment, tag, push, sudo, or remote operation occurred.


## 2026-09-07 - v0.1.22-hotfix20 POS production assets

- Created Hotfix20 from exact Hotfix19 commit `1fd252c6f0e612f8b3a0d4cea3413a53295205a8` without changing the POS/refund implementation.
- Confirmed Tailwind v4 recursively scans all `resources/views`, including POS and Livewire Blade templates; no content-path, invalid-utility, or CSS-conflict edit was required.
- Ran exactly one clean production Vite build. The new manifest SHA-256 is `8e5c124882f8b0af416f5a35e28e90adfc2952a38abf70296780b80075c73901`; the new app CSS SHA-256 is `56e206d8fc38fc2e01c82d5f2b2b8e8ef20947b67fc5feac0124db18c850ec87`. Both differ from Hotfix16.
- Authenticated safe non-production POS rendering was unavailable because no isolated environment/database exists, so no visual PASS or screenshots are claimed. Production remained untouched.
- Hotfix20 adds no migration. Migration 000111 must remain already Ran; activation performs no migration and fails closed before switching unless exact assets, Livewire rendering, and `rajeh` runtime-cache preparation pass.

## 2026-09-08 — v0.1.22-hotfix21 cash checkout and persistent sidebar

- Corrected cash/split arithmetic with exact cents in the browser and BC Math on the server; underpayment remains due and blocked, exact cash settles at zero, excess cash becomes customer change, and invalid/duplicate/overallocated portions fail closed.
- Made the desktop sidebar fixed independently of document scrolling with a dedicated vertical/no-horizontal navigation scroller; persisted scroll position, expanded groups, and active ancestry across full and Livewire navigation while retaining RTL, collapsed desktop, and mobile drawer behavior.
- Ran one Vite production build and completed focused database-free payment, navigation-state, Blade/compiled-PHP, Livewire-root, locale, asset-integrity, and diff checks. No migration, database, production, deployment, tag, push, sudo, or remote action occurred.
# 2026-09-09 — v0.1.22-hotfix24 product quick-create and compact UI

- Started from exact Hotfix23 commit `ac812449e446f69301a0202cb540c625f71eb9d8` in the isolated `rajeh_ahmed` worktree and required Hotfix24 branch.
- Completed the source implementation for atomic purchase-invoice quick Product Card creation, the label-only Arabic cost rename, product-description Stage B placement, compact authenticated shared spacing, compact Reports Center, and affected bilingual localization.
- Preserved the invoice draft component state and deferred inventory/accounting/supplier posting to the existing invoice-finalization path. No migration was added or run.
- Exactly one clean Node 20/Vite 8.2.0 production build passed. The bounded source, Blade/PHP, locale, asset, Hotfix23 sidebar, cash/split-payment, POS/refund/inventory unchanged-source, diff, and operator-script checks passed in one focused final verification.
- The clean local commit and immutable archive, complete manifest, promotion script, and activation script were prepared under `/home/rajeh_ahmed`; promotion and activation remain unexecuted.
# 2026-09-09 — v0.1.22-hotfix25 keyboard product lines

- Added one reusable accessible product combobox and keyboard row controller for exact barcode/code selection, bounded multi-match navigation, quantity/price progression, line totals, validation, and unlimited one-at-a-time row entry.
- Applied the workflow to purchase orders/invoices/returns, transfers, stock counts, adjustments, exchanges/returns, quotations, Party booking/invoice product lines, and POS search/cart presentation while preserving downstream authorization and document rules.
- Added a permission-gated transaction lookup endpoint covering barcode, item/SKU code, model, Arabic name, and English name; cost is omitted from responses unless the operator already holds a cost-bearing permission.
- PHP/Blade/runtime contracts, compiled asset identity, and representative browser keyboard scenarios passed. One successful Node 20/Vite 8.2.0 production build emitted the final assets after the default Node 16 launcher failed before Vite could compile.
- No schema, migration, database, production, deployment, tag, push, sudo, or root action occurred.

# 2026-09-10 — v0.1.22-hotfix26 supplier product lookup

- Replaced purchasing selectors in purchase orders, purchase invoices, and supplier returns with the shared incremental product combobox, supplier-first gating, associated/all-product filtering, keyboard/scanner behavior, and explicit native camera scanning.
- Reused the existing many-to-many `product_suppliers` schema and approved purchase-invoice history. Supplier-specific latest approved price/fallback source is shown and remains editable; valid purchasing saves establish missing associations and approval updates purchase history.
- Added localized supplier-change confirmation that clears affected lines/prices, preserved supplier-return source-invoice cost constraints, and retained Hotfix25 behavior outside purchasing.
- Focused syntax, Blade compilation, dependency-free interaction tests, runtime rendering/asset checks, locale JSON, and diff hygiene passed. Exactly one Node 20/Vite 8.2.0 production build ran.
- No migration was added or run. No production access, deployment, promotion, activation, backup, service action, sudo, root, push, tag, browser/package installation, or remote operation occurred.

# 2026-09-10 — v0.1.22-hotfix27 purchase-order full-page search

- Reserved a fixed logical camera-button area in the shared purchasing lookup and applied direction-safe logical padding/alignment so Arabic, English, numeric, and mixed queries remain visible responsively.
- Changed contained server matching and browser selection state so `001`, narrowed identifiers, and Arabic names update incrementally; multiple matches remain unselected until an arrow/mouse choice, while one exact unique barcode, SKU, supplier code, or model may select immediately.
- Added the permission-gated full-page purchase-order create route and adjacent bilingual sidebar item. The page reuses the existing Livewire component, validation, supplier/store/line state, supplier pricing, totals, and draft-save action.
- Focused syntax, 721-view Blade compilation, route/runtime rendering, dependency-free interaction tests, localization, compiled-asset integrity, and diff hygiene passed. Exactly one clean Node 20/Vite 8.2.0 production build ran.
- No migration was added or run. No production access, deployment, promotion, activation, database change, service action, sudo, root, push, tag, browser/package installation, or remote operation occurred.

# 2026-09-10 — v0.1.22-hotfix28 purchase-order runtime repair

- Correlated both supplied production request IDs to the same `Undefined variable $createPage` exception during Livewire `purchasing::orders` rendering; both custom render branches omitted the new view datum.
- Added `createPage` explicitly to the overview and full-page create render arrays without changing the Hotfix27 routes, navigation, form, product lookup, or create-modal removal.
- Added a fail-fast activation runtime verifier that uses an existing authorized user and visible store, array-only session/cache drivers, internal HTTP rendering in Arabic and English, warning escalation, and an always-rolled-back database transaction.
- Focused PHP syntax, 715-view Blade compilation, route/runtime fixture, navigation/product lookup, 3/3 JavaScript behavior checks, locale JSON, unchanged asset hashes, and diff hygiene passed. No Vite build was run because frontend source was unchanged.
- No migration or database mutation occurred. Production logs were read-only; production files, caches, data, releases, and active links were not modified.

# 2026-09-11 — v0.1.22-hotfix29 purchase-order search and compact layout

- Rebound the shared product lookup whenever Livewire preserves its root but replaces child controls, and stopped empty product IDs from emitting competing Livewire input requests while users type.
- Moved the camera control fully outside the input, made Arabic/English direction and alignment explicit, and preserved supplier filtering/pricing, unique exact selection, guarded multi-match Enter, stale-response protection, and keyboard row progression.
- Compacted the full-page purchase-order editor into a five-field desktop metadata row and dense aligned item rows, with a bounded scrolling item area, sticky header, compact price source, end-positioned notes, totals, and actions.
- Focused local checks passed: touched PHP syntax, 887-view Blade compilation, locale JSON, static bilingual route/layout/asset contract, 4/4 JavaScript runtime behavior tests, compiled asset identity, and diff hygiene. Real-environment authenticated bilingual route/search checks remain deliberately activation-gated because the isolated worktree has no environment or authorized local database credentials.
- One clean Node 20/Vite 8.2.0 production build completed successfully. No migration, production access/change, deployment, activation, sudo/root action, push, tag, package/browser installation, or unrelated work occurred.

# 2026-09-11 — v0.1.22-hotfix30 compiled browser product search

- Preserved the Hotfix29 purchase-order overview and compact create-form layout byte-for-byte.
- Repaired the hidden lookup feedback by rendering its dropdown as a fixed body overlay outside the bounded `overflow-y-auto` line container; added compact localized loading, results, empty, and request-error states.
- Made lookup binding idempotent across initial DOM load, Livewire initialization/navigation, Livewire morph hooks, mutation fallback, and dynamically added rows, while retaining authenticated debounce, supplier filtering, exact-unique selection, multi-match keyboard safeguards, row pricing/totals, camera behavior, and stale-request protection.
- One clean Node 20.20.2/Vite 8.2.0 build completed. The compiled bundle passed the rendered-markup DOM lifecycle contract for Arabic and identifier input, feedback visibility, keyboard/row state, Livewire replacement/addition/navigation, and unclipped overlay placement.
- No migration, production access/change, deployment, activation, sudo/root action, push, tag, package/browser installation, or unrelated work occurred.
# v0.1.22-hotfix31 purchase-invoice keyboard compact UI — 2026-09-11

- Reused the Hotfix30 browser-side product lookup per purchase-invoice line with supplier filtering, dynamic bilingual/identifier search, scanner separation, stale-response handling, and explicit multi-match selection.
- Added invoice keyboard progression through quantity, editable cost, list price, discount and tax fields; final Enter creates at most one next row and focuses its product search while parent submission remains blocked.
- Compacted purchase-invoice metadata, bounded/sticky line geometry, totals, notes/actions, and the price-list search/status row without changing invoice or pricing business rules.
- Updated version/tracking/evidence; exactly one clean Node 20 production Vite build completed. No migration or production operation occurred.

# v0.1.22-hotfix32 invoice verification fail-closed repair — 2026-09-11

- Confirmed the approved Hotfix31 invoice Blade declares the supplier-only checkbox once; the stale verifier was matching the same words inside a longer lookup empty-state data message.
- Replaced the substring assertion with exact rendered text-node checks around one editor checkbox and multiple lookup controls, and made all verifier exceptions exit explicitly with status 1.
- Hardened activation around checked `tee` pipeline statuses and an exact terminal PASS marker; post-switch failure restores exact Hotfix31, rebuilds caches, reloads PHP-FPM, runs `artisan up`, and confirms maintenance mode is off.
- The isolated negative contract passed pre-switch/no-switch, post-switch/rollback, and no-false-success scenarios. No frontend source changed; exact Hotfix31 compiled assets are reused and Vite build count is zero.
- No migration, database, production access/change, promotion, activation, sudo/root action, service action, push, tag, package/browser installation, or unrelated application change occurred.

# 2026-09-12 — Egyptian Arabic locale

- Added the separate `ar-EG` locale named `العربية المصرية` from exact source baseline `3ca22dbe6671f94aaec4132b84fb6a2c126cec82`; retained `ar` and `en` unchanged.
- Completed the 6,115-key union of current Arabic and English JSON catalogs plus all six Arabic PHP locale files, with no empty or duplicate `ar-EG` entries and protected placeholders/tokens preserved.
- Added three-way locale switching with existing session/cookie persistence, Laravel fallback `ar-EG → ar → en`, exact `lang="ar-EG"`, RTL/Cairo behavior, and Arabic field/label selection across existing application surfaces.
- Recorded 1,515 remaining hardcoded user-facing candidate locations in `docs/audits/egyptian-arabic-hardcoded-strings.md`; no functional Blade/Livewire copy was mass-extracted during the audit.
- Focused locale integrity, PHP/JavaScript syntax, full Blade compilation, fallback, switch/persistence, RTL/lang, audit-count, build-manifest, unchanged-source-locale, and diff checks passed. One Vite build completed successfully after one earlier invocation stopped before asset generation because the isolated worktree lacked a local `vendor` path.
- No automated test suite, browser, database, migration, deployment, packaging, activation, production access, push, tag, sudo, package installation, or business-rule change occurred.

# v0.1.22-hotfix33 Egyptian Arabic integration — 2026-09-12

- Integrated localization commit `be300bfb039316a3f89b29f2cd2c29bd73bd0add` onto exact Hotfix32 commit `2636d8dc1d345fa050e4e20c729a4cab9ac4a35a` in a separate `rajeh_ahmed` worktree and preserved both tracking histories.
- Accepted only locale registration, switching/persistence/fallback, Arabic-family data presentation, RTL/Cairo, and view-copy changes. Rejected the locale-dependent POS customer-search `ORDER BY` hunk and retained exact Hotfix32 query behavior.
- Corrected 532 non-wildcard-aware `isLocale('ar*')` calls across 107 files to a real Arabic-family prefix check after the focused runtime probe demonstrated the Laravel method uses exact equality. Existing Arabic and Egyptian Arabic now share the intended presentation without business-rule changes.
- Focused verification passed 6,115 JSON keys, 244 PHP locale leaves, exact key/token parity, unchanged `ar`/`en`, switch/session/cookie/fallback, RTL/lang/Cairo/sidebar/command palette, Hotfix32 invoice-search/keyboard compiled behavior, assets, audit count, syntax, diff hygiene, and isolated activation failure/rollback behavior. Authenticated production-shaped route rendering remains activation-gated before and after the switch.
- No migration, database access/mutation, production access/change, deployment, promotion, activation, sudo/root action, service action, automated suite, browser, push, tag, package/browser installation, or remote operation occurred.

# v0.1.22-hotfix34 Egyptian locale route-cache repair — 2026-09-12

- Rebuilt from exact Hotfix32 in a separate clean worktree, reapplied the accepted Hotfix33 localization history, and left the failed Hotfix33 release and artifacts immutable.
- Replaced the locale-switch closure with the dedicated invokable `LocaleController`; preserved POST, route name, CSRF/web middleware, redirect-back, session persistence, one-year cookie, and exact `ar`, `en`, `ar-EG` validation.
- Replaced direct cached-action invocation in the runtime proof with genuine HTTP-kernel requests through the cached router, including safe rejection of unsupported and malformed locale values.
- Preserved exact Hotfix32 business queries, all Hotfix24–Hotfix32 purchasing/search/keyboard repairs, 6,115 JSON keys, 244 PHP leaves, and the 1,515-location deferred hardcoded-string audit.
- Frontend inputs are unchanged; exact Hotfix33 compiled assets are reused and Hotfix34 Vite build count is zero. Authenticated database-backed rendering remains strictly pre/post-switch activation-gated and transactional.

# v0.1.22-hotfix35 Egyptian localization completion — 2026-09-12

- Created the Hotfix35 branch from exact active Hotfix34 commit `397fd3529a087f08f402fd11c88692481bb0654b` in a separate clean worktree and cherry-picked localization commit `57cb2a85666cc7d9cfad02edce33eb5bfa766df6` without conflicts or discarded localization content.
- Completed the `ar-EG` presentation while preserving `ar`, `en`, the Hotfix34 controller-based locale route and cached-route repair, internal PHP class names, queries, permissions, routes, search, keyboard workflows, accounting, posting, and migrations.
- Version, focused evidence, immutable offline package, and fail-closed operator scripts target `0.1.22-hotfix35`. Promotion and activation accept only exact Hotfix34; activation requires migration 000111 already `Ran`, runs no migration, verifies authenticated `ar`, `en`, and `ar-EG` representative routes before and after switching, and restores exact Hotfix34 on any post-switch failure.
- Vite inputs are unchanged, exact Hotfix34 compiled assets are reused, and Hotfix35 Vite build count is zero. No production access, database mutation, migration, deployment, promotion, activation, root execution, push, tag, or package installation occurred.
- 2026-09-12: Completed the owner-directed V0 Egyptian Arabic locale pass from exact Hotfix34. All 6,115 JSON values and 244 PHP leaves were rewritten locally; focused rendered-source wording and the 1,515-candidate audit were resolved/categorized. Final verification and commit are recorded in the session summary.


# 2026-09-13 — Egyptian Arabic locale quality repair

- Repaired the Hotfix35 `ar-EG` corruption from exact commit `2f6b07285ca082cb3c61f1f860e13b0d3d2bccb8` on `ui/egyptian-arabic-locale-quality-repair`.
- Removed all 3,093 literal `بالمصري:` occurrences. Of 6,115 JSON values, 6,111 changed: 2,974 needed a prefix-only cleanup and 3,137 received contextual or grammatical rewriting beyond prefix removal. All 244 PHP locale leaves changed and were reviewed.
- Corrected residual Hotfix35 word-map ordering, malformed empty states, approval and wallet wording, product-card labels, limit text, and Egyptian-only rendered copy across 14 Blade templates. Preserved professional accounting, inventory, tax, and legal terminology plus required identifiers, placeholders, brands, and file formats.
- The focused final gate passed JSON/PHP syntax, 6,115-key and 244-leaf integrity, placeholders, exact unchanged `ar`/`en`, zero forbidden prefixes and known corruption signatures, representative product/dashboard/alerts/approvals/sales/purchasing/inventory wording, unchanged RTL/Cairo/locale switching and compiled assets, allowed-file scope, and `git diff --check`.
- No browser-control or automated suite was run under the current repository directive. No Vite build, database operation, migration, production access, deployment, packaging, push, tag, or business-logic change occurred.

# v0.1.22-hotfix36 Egyptian localization quality repair — 2026-09-13

- Integrated direct-child repair commit `80156c9ab50d9b76c19a797dc30db487b694ecb2` onto exact active Hotfix35 `2f6b07285ca082cb3c61f1f860e13b0d3d2bccb8`, preserving all 6,115 `ar-EG` JSON keys and 244 PHP locale leaves.
- Retained byte-identical `ar` and `en`, exact Hotfix35 compiled assets, locale routes, queries, permissions, search, keyboard, accounting and database behavior; Vite build count is zero and no migration was added or run.
- Finalized version/evidence and prepared immutable offline packaging plus unexecuted fail-closed scripts restricted to exact Hotfix35. Authenticated three-locale representative rendering is enforced transactionally before and after activation, with exact Hotfix35 rollback after any post-switch failure.

# 2026-09-14 — v0.1.22-hotfix40 purchase workflow and reports center

- Implemented the exact draft/review/procurement-approved/ready-to-convert purchase-order lifecycle with server permissions, audit reuse, explicit next actions, full-page editing, branch numbering/filtering/counts, duplicate-submit safeguards, and separate receiving status.
- Enabled locked/idempotent partial receiving and multiple invoices per order with ordered/received/remaining quantities, immutable approved invoice and ledger snapshots, linked-document visibility, and independent not/partially/fully received states.
- Added compact supplier-cost-aware product entry, private versioned localized purchase-order PDFs, and a unified permission-scoped Reports and Export Center with centralized CSV/XLSX/PDF generation, request deduplication, snapshot hashes and formula-safe CSV output.
- Added only migration 000114 with nullable/indexed/backward-compatible purchase document and export additions; it was not executed and rollback intentionally retains the additions.
- One focused database-free verification pass and exactly one Node 20/Vite 8.2.0 production build passed. No broad legacy suite or browser control ran.

# 2026-09-14 — v0.1.22-hotfix41 reports runtime repair

- Converted every `Stringable`-derived translation argument in the Hotfix40 Reports and Export Center views to an explicit scalar string, preserving all labels, locales, datasets, permissions, filters and export behavior.
- Prepared an authenticated `/reports` smoke for `ar`, `en` and `ar-EG` and a release workflow that requires 000111–000114 already `Ran`, executes no migrations, normalizes active/candidate cache permissions, switches atomically and restores exact Hotfix39 application files on failure while retaining 000114.
- The single focused final source pass completed changed PHP syntax, one full Blade compilation with compiled syntax, scalar translation-boundary assertions, authenticated transactional `/reports` rendering in all three locales and diff hygiene. No Vite-managed input changed, so the Hotfix40 build is reused without rebuilding.

D3 batch on `issues/business-decisions-6-8-14-22` from `origin/main` `a7855462`: approved VAT, supplier Tax ID, duplicate supplier, and rejected return inspection decisions implemented or verified. Focused tax/supplier checks and one Fast QA gate passed; #22 retained the canonical existing rejection/audit workflow. No production migration, merge, deployment, or issue closure.
Supplier batch `issues/supplier-26-29` (source `3d24cf5`) implements #26 stale-edit message handling and #29 preferred-supplier audit history/detail visibility. #31 remains outside this batch and requires Ahmed to decide whether settlement method and preferred payment method are the same business field. No migration or deployment. Focused tests were added but blocked by unavailable local DB credentials and missing Playwright in the disposable QA harness; syntax, diff hygiene, and Pint passed. Commit and branch push follow.
Validation completed for `issues/supplier-26-29`: isolated MariaDB focused tests pass after a test-only missing `Gate` import fix; PHP syntax and Pint pass. No Fast QA, merge, deployment, or issue closure.

# 2026-10-17 — Purchasing distribution and supplier returns v2

- Isolated task branch from exact main `0e09f86e6d8b3b8eeb937e9e3ec6d75b4a99c84d`; added independent distribution input keys and partial/over-allocation totals and validation without automatic allocation.
- Preserved existing selling-price snapshots on quantity-only distribution saves and resolved new destinations through the existing canonical pricing resolver/current selling price fallback; no list or selling-price data was changed.
- Replaced supplier-return creation modal with a responsive Arabic RTL full page. Invoice reference is optional; linked lines snapshot historical invoice unit cost, while invoice-free lines snapshot current product `average_cost`. Supplier-product relationships, active source store/reason, stock, audit, and approval checks remain enforced.
- Added the owner-approved supplier payable entry ledger with source-document uniqueness and signed invoice/return postings, including negative balances. No historical invoice backfill or opening balance was invented. Additive migration only.
- Focused disposable MariaDB tests, authenticated 1440/1024/390 browser review, RTL/overflow/console checks, PHP syntax, Pint, Blade cache, and diff hygiene passed. Screenshots are in `storage/app/frontend-review/supplier-return-v2/`. No broad QA, production data operation, merge, or deployment occurred.

## 2026-10-20 — Inventory live stocktake feature branch

Class I4 implementation on `feature/inventory-live-stocktake-and-draft-preservation` from `7e50a0b`. Existing count sessions, lines, contributions, stock balances, canonical movements, document numbering, and permission gates were reused. Open-count transfer blocking was removed. Session load, scanning, autosave, review, movement visibility, approved settlement, pagination and branchless warehouse support were added. The branch remains unmerged and undeployed; Production remains `7e50a0b`.

## 2026-10-20 — Live stocktake movement validation follow-up

D3 focused validation on the existing feature branch. Real POS sale, sellable customer return, and purchase-invoice receiving each posted a single canonical movement while the stocktake remained active. A 100-unit baseline reconciled to 97 after -10/+2/+5 and retained a -2 variance after a later sale. Existing transfer non-blocking case passed. No runtime defect or design change; only focused regression tests and canonical verification/UAT records were added. Production remains at `7e50a0b`; merge/deployment did not occur.

## Privileged operational authority — merged, deployment pending

Merged validated authority changes for `system-administrator` and `company-owner`. Both roles may self-approve and bypass normal operational approval gates within their own company. Normal users retain existing restrictions. Cross-company isolation, idempotency, state integrity, and duplicate-posting guards remain enforced. Focused validation passed; Production deployment is still pending.

## Final stocktake reference parity + Excel import — validated, deployment pending

Feature tip `00ef552b1c2eef8927cfa372f71e4d3365844d73` completed the final owner-approved stocktake reference-parity pass. List, Create, empty count, populated count, mobile count cards, Review, and Excel Import UI passed focused 1440/1024/390 checks with RTL/Cairo, no console errors, and no page overflow. Excel import focused QA passed without stock movements or settlement. No migrations. Production deployment remains pending.

# 2026-10-27 — PRE-LIVE business-data reset complete

Owner-authorized full test-data reset completed against the existing Production release without code deployment or schema change. Verified backup: `/home/rajeh/shared/toyjoy/backups/rajeh_toyjoy_20261027T122918Z_pre_pre_live_business_reset.sql.gz` (SHA-256 `a2b94516c77be720303da33416d48f77b6074a834066e6abe020b24e89eb1298`). A one-off FK-safe transaction removed test catalog, inventory, transfers, purchasing, supplier, pricing, branch/store/warehouse, cash drawer, scope mapping and numbering records. Sales/POS and customers were already zero. Users, roles, permissions and assignments stayed intact; system configuration stayed intact. All business and operational-location counts are zero, FK check passed, public health passed, and maintenance is off. The QA reset command was not run because its Production guard and migration/seeder behavior are unsuitable. Previous test transfer 25 and all related records are gone. Next action: configure clean master data and locations from scratch; prior transfer QA results below are historical.
# 2026-10-29 — Rajeh v2 Core polish branch verified, unmerged

The nine owner-authorized areas are implemented on `feature/v2-products-transfers-inventory-pos-polish-20261029` from current main `66fe6cd`. Existing document-sequence tables support separate Addition (`IAD`) and Deduction (`IDD`) counters without migration. Focused database tests, the Core regression gate, and authenticated 1440/390 QA passed. The branch is not merged or deployed.
