# 2026-10-26 — Corrective batch closed by Production release

Approved main application `72ca966510fe7be11fc33afe5d0194fa344a47ac` (source hotfix `f62dc9098046700a4a0289f552a19fa5851f5a39`, merge `2d182ffa63f245045f8b19b4ac9b74a0a3d23a79`) is live at `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.51-barcode-transfer-72ca966`. Previous Production SHA `00f150f7bd7cf80969f143cba171966cbb33eb84` at `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.50-commercial-inventory-00f150f` is retained for rollback. Backup `/home/rajeh/shared/toyjoy/backups/rajeh_toyjoy_20261026T184852Z_pre_72ca966.sql.gz` passed gzip/SQL-readability checks; SHA-256 `e6c5f144a1474ffdcd7a37d574350b2188eadceb6879de70f7bc6e5866442d4d`. Production migration status was zero pending and none ran. Candidate real-vhost checks, atomic activation, PHP 8.5 FPM reload, and public `/`, `/up`, `/login` passed; Product Card create/edit and transfer editor/approval/receiving protected GETs returned expected guest 302 without 5xx. CSS/JS returned 200, FPM remained active, maintenance is off, and no new Laravel errors were found. The first candidate gate had a verification-cookie error and restored the old release before the corrected gate passed. Authenticated Production behavior was not checked; previous focused local tests and browser evidence remain the functional evidence. Barcode validation/localization, compact transfer UX, actual/partial multi-destination receiving, and approval 422 correction are deployed. No Production business transaction, seeding, or database data change was performed. Final deployment rollback: NO. Journal: `/var/lib/toyjoy/barcode-transfer-deployment/72ca966-20261026T185123Z`. This approved I4 release is closed; no corrective-batch deployment remains pending.

# 2026-10-26 — QA/Demo framework merged, deployment separate

| Class | Source | Final SHA | Status |
|---|---|---|---|
| D3 | `feature/qa-demo-data-framework-20261026` | `36c39a4e4651a844d0ee406b939cfec13ddcc0b0` | Merged to main as `58413c1217b0fc3bb5fda8686071dff7b22a32c0`; not deployed |

Supported QA/testing-only modes: `--empty`, `--demo`, `--catalog`, `--inventory`, `--pos`. The command is hidden in Production and new demo seeders fail closed there. No migration or Production auto-seeding was added. The Production business reset and complete branch/warehouse/drawer/location cleanup remain valid; this main merge did not touch Production data or deploy. The prior commercial/inventory Production release remains the active application.

# 2026-10-25 — Commercial and inventory batch closed by Production release

| Class | Source | Final SHA | Status |
|---|---|---|---|
| D3/I4 | User B `feature/purchasing-pos-commercial-ux` | `d9e0e7c6673a89f1891014a2d3d9ad0be674e12a` | Integrated first; merged and deployed |
| D3/I4 | User A `feature/inventory-stocktake-and-resilient-drafts` | `5ac2e631b87b54f2eb32dc5a7dc3d3711bf6cec8` | Integrated second; merged and deployed |
| I4 | `feature/integration-commercial-inventory-batch` | `6b167bdb69e12cecf0225acfb0a3c30c85c81d31` | Main merge `6fe1cbad626be76aa43a8c18cbc3020ee1dcf0c4`, approved main `00f150f7bd7cf80969f143cba171966cbb33eb84`; live at `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.50-commercial-inventory-00f150f` |

This batch is closed: integration acceptance at 1440/390 and post-merge focused 40 tests/325 assertions passed; candidate, activation, PHP 8.5 FPM reload and real-host public verification passed. Production migrations pending/executed: 0/0. DEC-107 nonblocking transfers and DEC-108 fail-closed outlet mapping are live; DEC-109 preserves header branch context. Backup `/home/rajeh/shared/toyjoy/backups/rajeh_toyjoy_20261025T181352Z_pre_00f150f.sql.gz` has SHA-256 `ae02ce69a3718af9232675d9e2a9e1bb7c55238dac80b57b7c6f4d6c4d441b35` and passed isolated restore verification. Previous release `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.49-pos-completion-r9-r16-0cf1214-r2` remains rollback. No User A/User B integration, main merge or deployment action remains pending. Owner operational UAT and live monitoring follow.

## 2026-10-25 — R9–R16 POS completion batch closed by Production release

- Class I4: merged/deployed application `0cf121450502713be385e041cbaf66e2a93ea66e` is live at `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.49-pos-completion-r9-r16-0cf1214-r2`; previous R1–R7 release `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.48-post-pos-r1-r7-99c6f5d-r3` is rollback. R9–R16 is closed and no longer pending.
- Owner-provided journal `/var/lib/toyjoy/r9-r16-deployment/0cf1214-r2-20261025T101832Z` records successful hash-verified handoff, zero pending migrations, no migration run, payment invariant PASS, candidate/activated/public browser and health gates PASS, maintenance LIVE and no database rollback. Failed r1 was a QA-harness failure; the corrected dual-release gate passed 24/24 on each release. Mutable POS behavior remains supported by prior disposable/integration verification. This five-file docs-only main update does not redeploy.

## 2026-10-25 — R1–R7 operational repair batch closed by Production release

- Class I4: merged/deployed application SHA `99c6f5da55efe339fb07057f5703feed9302dcc5` is live at `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.48-post-pos-r1-r7-99c6f5d-r3`; previous R8 release `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.47-pos-theme-r8-b762e57-r4` is rollback. R1–R7 is no longer pending. R9–R16 is a separate POS-only development batch handled by User 2.
- Sole additive transfer-receiving-draft migration is Ran in batch 41; final deployment did not rerun it. Preserved pre-migration backup `/home/rajeh/shared/toyjoy/backups/rajeh_toyjoy_20261025T053617Z_pre_99c6f5d.sql.gz` has SHA-256 `df120219191337f00806520a361149092c7a218fff9b43561ec1573b1fa6983e`. Owner-confirmed journal `/var/lib/toyjoy/r1-r7-deployment/99c6f5d-20261025T054330Z` records candidate/public authenticated browser PASS (60 assertions each), maintenance LIVE, and no final rollback. The five `.ai` continuity files are updated on main after acceptance; this documentation commit does not redeploy the application.

## 2026-10-24 — R8 POS theme batch closed by Production release

- Class I4: deployed application SHA `b762e57002c78e9444475a5409a49f2826369152` is live at `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.47-pos-theme-r8-b762e57-r4`; prior `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.46-pos-strict-parity-a61b758-r2` remains rollback. Owner-confirmed `/var/lib/toyjoy/r8-deployment/b762e570-20261024T181522Z` shows candidate and after-activation authenticated Chromium, Light/Dark, theme persistence and POS state preservation PASS. Final public health and PHP 8.5 FPM reload PASS; no new Laravel errors; maintenance UP; rollback NO.
- Payment configuration unchanged and canonical payment invariant PASS; migrations pending 0, migrations run NO. Failed r1–r3 immutable candidates remain preserved. This release closes R8 only. R1–R7 remains a separate, unmerged batch; R9–R16 has not started. The five canonical Production-continuity files are updated main-only after success; the docs commit does not redeploy the application.

## 2026-10-24 — POS strict reference parity batch closed by Production release

- Class I4: owner-approved V3 POS feature branch `feature/pos-strict-reference-parity-2026-10-23` final SHA `e1c8bcf`, merged/deployed application SHA `a61b75826f9dc61c268be290e1daf2899aca9f3a`, active immutable `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.46-pos-strict-parity-a61b758-r2`, rollback `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.45-product-card-formatting-41ab1ed`. Category cap removed without changing real category data, IDs, visibility, or product filtering. Approved two-column POS, lookup/customer/modal/quantity/multi-ticket/payment UI, canonical invoice delivery and return entry are live. Canonical payment methods remain intact.
- Disposable >24-category Feature test 1/2 and authenticated Chromium 85 assertions passed. One additive invoice-delivery migration ran in batch 40 after gzip-verified Production backup; existing customers default to PRINT. Real Apache-vhost `r2` candidate and public POS at 1440/1024/390, assets and public health passed; immediate PHP 8.5 FPM reload, maintenance OFF, final rollback NO. The first unreadable-assets candidate was rejected before activation and its temporary Apache link safely restored. No Production QA data or test sale was posted. The five `.ai` continuity files are updated main-only after acceptance; next Owner operational review and monitoring.

## 2026-10-22 — Product Card numeric and Arabic localization hotfix closed by Production release

- Class I4 deployment closure: merged/deployed application `41ab1ed1f537c937becad19ec08e7977d8b8471c` is LIVE at `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.45-product-card-formatting-41ab1ed` with rollback `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.44-sales-invoice-shortcut-626867f`. The approved hotfix changes only Arabic translations, Product Card presentation and Dashboard top-products quantity presentation; canonical `ProductQuantity::format` / `<x-product-quantity>` and `<x-money>` are reused. No schema, migration, backend/domain, route, data or business-rule change; zero pending migrations and none executed.
- Actual Apache-vhost candidate and public `/`, `/up`, `/login`, CSS/JS, authenticated Dashboard and Product Cards passed. Arabic and English 1440/390 browser checks passed quantity `5` versus raw `5.000000`, Product Card quantity trimming and two-decimal money, Arabic UI/English locale, RTL/Cairo, no NaN, no page overflow, no console error and zero business writes. Atomic activation, immediate PHP 8.5 FPM reload, maintenance OFF, new Laravel errors NO, final rollback NO. Evidence: `/tmp/rajeh-product-card-deploy.DhzYQl/`.
- Permanent continuity rule: update the five canonical `.ai` Production-state files on main after deployment; feature/hotfix branches do not modify them. Next Owner UAT: review genuine Product Cards in both locales and Dashboard quantity display during normal use, and monitor this release.

## 2026-10-22 — Sales Invoice / POS shortcut hotfix closed by Production release

- Class I4 deployment closure: merged/deployed application `626867f05cb40dd5bbced82892596fa1b2d09f0c` is live at `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.44-sales-invoice-shortcut-626867f`; rollback target `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.43-operational-polish-8429b0e-r2`. The Dashboard POS and standard Sales Invoice shortcuts now have separate destinations and permissions. The ordinary invoice route saves a draft only, without stock movement, payment, or shift impact. Source and prior focused disposable QA cover POS-only, invoice-only, both and neither visibility cases; no Production business document was submitted for verification.
- Fresh gzip-readable backup `/home/rajeh/shared/toyjoy/backups/rajeh_toyjoy_20261022T175310Z_pre_626867f.sql.gz` SHA-256 `9963f6150e6d6fe4f1fc74dd38b1d30194d6e705fca2a6997a0188c842e0f714` preceded the only new additive data migration `2026_10_22_150000_add_sales_invoice_create_permission`, which ran in batch 39. Exactly one active `sales_invoices.create` row exists; schema changes, seeders and other migration executions: none. Corrected candidate and public Apache checks passed at 1440/390 for Dashboard, POS, invoice creation, CSS/JS, RTL/Cairo, and no console errors or overflow. An initial verifier-only POS-shell assumption triggered a safe candidate rollback; the final release passed, PHP 8.5 FPM reloaded, maintenance is OFF, new Laravel errors are NO, and no final rollback is required.
- Permanent continuity rule: the five canonical `.ai` Production-state files are updated on **main only after deployment result is known**; feature/hotfix branches must not modify them. Next Owner UAT: check separate shortcut visibility with representative accounts and save a genuine invoice draft during normal operations without stock/payment posting.

## 2026-10-22 — operational polish batch closed by Production release

- Class I4 deployment closure: merged/deployed application `8429b0e8a54ae07cddd9ced1aa5409455aa84b7b` is live at `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.43-operational-polish-8429b0e-r2`; rollback target `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.42-stocktake-final-parity-f8a71cb`. Barcode pricing resolver, Supplier Return search reset, sidebar single-open accordion, permission-aware Dashboard Quick Actions/Approval Inbox, and 18 Supplier Return reasons with “أخرى” notes validation are included. Backup: `/home/rajeh/shared/toyjoy/backups/rajeh_toyjoy_20261022T130445Z_pre_8429b0e.sql.gz`; SHA-256 `276046dbace338bbd9ca1895d4588b71f53c1ae2b5d666a585099c6304ab5e57`. The sole additive data migration `2026_10_22_090628_add_default_supplier_return_reasons` ran as batch 38, inserted 18 active missing reasons, changed no schema, and left zero pending. No seeder or Production business test transaction ran.
- The first candidate failed on unreadable asset permissions and rolled back; a second candidate verifier used the wrong Approval Inbox URL and rolled back. Corrected immutable `-r2` candidate and public actual-vhost checks passed at 1440/1024/390 with RTL/Cairo, zero page overflow/console errors and no new Laravel error after the final attempt. PHP 8.5 FPM was reloaded immediately, maintenance is OFF, and the final release needs no rollback. Read-only label pricing matched `PL-0001` to canonical effective price; two actionable approvals appeared; eight privileged shortcuts rendered; 18 reasons had no duplicate codes. Prior focused QA remains evidence for mutating behavior.
- Permanent continuity rule: the five canonical `.ai` Production-state files are updated on **main only after the final deployment result is known**; feature/hotfix branches must not modify them. This documentation-only commit follows the deployed application SHA and requires no second deployment. Exact next action: Owner UAT on a genuine label preview, supplier-return product add/search reset and “أخرى” details, sidebar navigation, and permission-aware Dashboard/approval links with real operational accounts.

## 2026-10-22 — final Stocktake parity and Excel Import batch closed by Production release

- Class I4 deployment closure: merged/deployed application `f8a71cbb225c82867354ad90a95e54c0e624b84a` is live at `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.42-stocktake-final-parity-f8a71cb`; rollback target `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.41-privileged-authority-83d516a`. Final reference UI and real `.xlsx`/`.csv` count-session import are live. `product_identifier` precedes active barcode and `actual_quantity` is count-only; invalid/unknown/duplicate rows are reported, retries do not duplicate lines, company/warehouse/session scope is enforced, and import causes no stock movement or settlement. No migration/schema change; zero pending, none run.
- Actual-vhost candidate/public checks, 1440/1024/390 RTL/Cairo/layout, 390 count-card facts, 1440/390 Arabic import UI, assets, PHP 8.5 FPM reload, maintenance OFF and no new Laravel errors passed. An initial verifier-wrapper exit caused a protective rollback after public checks; the corrected diagnostic attempt completed and the new release is active. Prior focused import QA was 2 tests/26 assertions; no Production business form was submitted. Empty-count visual evidence remains from prior disposable QA because Production had no in-progress empty session. Physical camera remains Owner UAT. Exact next action: Owner tests a genuine stocktake workbook and the camera on a physical mobile device, with normal settlement approval.
- Permanent continuity rule: update the five canonical `.ai` Production-state files on main **after deployment**; feature/hotfix branches do not change them. The docs-only main commit after this release is a source documentation SHA, separate from deployed application SHA, and requires no new application release.

## Historical — 2026-10-21 privileged operational authority batch closed by Production release

- Class I4 release closure: merged/deployed SHA `83d516a9fc547c836b097ce72bbe0f79452ad9c3` at `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.41-privileged-authority-83d516a`; rollback `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.40-stocktake-reference-ui-d4b6a85`. The corrected central mapping recognizes account `is_super_admin`, role `system-administrator`, and role `company-owner` as privileged inside their own company. All three may self-approve/finalize; ordinary-user restrictions, tenant isolation, state integrity, canonical posting, audit, and idempotency remain. Source gate and focused QA (7 tests/105 assertions) passed. Zero pending migrations; none ran. Candidate/public actual-vhost public, transfer, stocktake, and purchasing GET checks passed; PHP 8.5 FPM immediately reloaded, maintenance OFF, no new Laravel errors, rollback NO. No Production business form or stock movement was posted for verification. Next action: Owner operational UAT on genuine documents and monitor this release.

## Historical — 2026-10-21 Stocktake reference UI batch closed by Production release

- Class I4 release closure: merged/deployed SHA `d4b6a854266662706701cb881d69844ecfbd4422` at `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.40-stocktake-reference-ui-d4b6a85`; rollback `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.39-live-stocktake-mobile-b037a59`. Runtime delta contains only stocktake Blade views; no backend/domain/route/controller/permission/schema/business-rule changes or migrations. Production migration status had zero pending; none ran. Candidate and public Apache-vhost list/create/count/review checks passed at 1440/1024/390, including four visible mobile cards with product/SKU, expected, editable actual, difference, and status. RTL/Cairo, assets, no NaN, no page overflow/console errors, PHP 8.5 FPM reload, maintenance OFF, and no new Laravel errors passed; rollback not required. No Production business form was submitted. Physical camera remains Owner UAT. Next action: owner visual/device UAT and release monitoring. Earlier pending entries below are historical pre-release records.

## Historical — 2026-10-21 stocktake reference UI final mobile polish awaiting merge review

- Class V1; final revision on `feature/stocktake-reference-ui`, unmerged and undeployed. Runtime diff adds only mobile card presentation to the existing count table rows and adjusts spacing in the existing create and active-count toolbar views. Mobile rows expose product/SKU, expected, editable actual, difference, and status without changing input, autosave, scanner, route, or authorization contracts. Quick Scan remains after the list. Existing list/review and desktop/tablet table structure remain approved.
- Authenticated local disposable-MariaDB browser checks PASS at 1440/1024/390 across list/create/entry/review, including all five mobile row facts, RTL/Cairo, zero page overflow/console errors, manual-add POST/CSRF, and toolbar focus. PHP/Blade syntax, Node 20 build, Blade cache, Pint, and diff hygiene PASS. No backend/domain/schema/migration change or Production action. Next action: owner review the pushed final revision and authorize a merge separately if accepted.

## 2026-10-21 — Stocktake reference UI redesign awaiting owner review

- Class V1; branch `feature/stocktake-reference-ui` from `96951e3d3aab5810460f1baf0c9de2644b947e7e`; unmerged and undeployed. Runtime changes are confined to the stocktake inventory Blade view and five stocktake-only partials. The four owner reference screenshots drive the list, create, active counting, and review hierarchy. Rajeh shell, RTL, Cairo, colors, and existing components remain.
- Dedicated list filters/results replace the generic dashboard presentation on stocktake routes; create form groups existing fields; count table is the primary workspace with scanner/manual entry after it; review has summary, filters, and reconciliation table. Existing form routes, CSRF, permissions, count/reconciliation values, scanner and autosave hooks are retained. No backend/domain/schema/route/controller/API or migration change. No import/export, unit/image, or category filter was invented where the current screen has no supported data/action contract.
- Authenticated disposable browser checks PASS at 1440/1024/390 for four screens, RTL/Cairo, zero page overflow/console errors, toolbar focus, manual-add POST/CSRF, and scan controls. Node 20 build, Blade/PHP syntax, Pint, Blade cache, and diff hygiene PASS. No Production or QA stocktake business form submission. Physical device camera remains Owner UAT. Next action: Owner review branch and screenshots; merge requires separate authorization.

## 2026-10-20 — Live stocktake mobile review batch closed by Production release

- V1 `min-w-0` review containment, affecting `resources/views/inventory/index.blade.php`, is merged and deployed in approved main `b037a59f749b30cd8b458c090d434fac59131139` at `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.39-live-stocktake-mobile-b037a59`. The prior safe release `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.36-standalone-numeric-7e50a0b` remains the rollback target. Actual-vhost authenticated 390 px reconciliation and 1440 px checks passed before and after public activation. Both additive stocktake migrations remain Ran in batch 37; zero pending, none rerun. Physical mobile camera UAT remains pending. Next action: Owner device and operational UAT; monitor the release.

## 2026-10-20 — Historical: live stocktake 390 px review containment hotfix

- Approved main `7a5358f2ffd2a6c1babda7ae0bdc45931540e459` was packaged at `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.38-live-stocktake-hotfix-7a5358f-r2`. Controlled actual-vhost gates found a real 390 px reconciliation page overflow (`body.scrollWidth` 622). Both controlled switches rolled back; the first stopped on an overbroad verification selector, and the second exposed the overflow. Active Production remains `7e50a0ba3c5b2e12325d6d439f72d4572985c1aa` at `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.36-standalone-numeric-7e50a0b`; PHP 8.5 FPM was reloaded after rollback and maintenance is OFF.
- Both additive stocktake migrations remain applied in batch 37. No Production stocktake, transfer, sale, return, receiving, settlement, migration, or seeder was submitted for verification. The minimal `min-w-0` review section fix is local on `hotfix/live-stocktake-review-mobile-overflow` and passed focused 1440/390 browser checks using disposable MariaDB. Merge and deployment of a new SHA require separate owner authorization. Physical camera UAT remains pending.

## 2026-10-20 — Live stocktake release attempt rolled back

- I4 release candidate `a12f1768301ec7b66b89b000cb098fa0609facb7` at `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.37-live-stocktake-a12f176` failed the authenticated manual-add form gate and was rolled back. Active Production code remains `7e50a0ba3c5b2e12325d6d439f72d4572985c1aa` at `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.36-standalone-numeric-7e50a0b`.
- Production schema retains the two additive stocktake/transfer migrations in batch 37; no seeder, business test posting, or migration reversal occurred. The failed candidate is inactive. Corrective source is local on `hotfix/live-stocktake-manual-add-form`, pending review and a newly authorized merge/release. Physical camera UAT remains pending.
- Manual-add fix commit `9d7eb0bdd79db5e4bb660f41e107a5c023e6dc8e` closes the Blade `class` quote so the existing POST action and CSRF field render correctly. Focused disposable MariaDB PASS 1 test/23 assertions; local authenticated 1440 and 390 browser submissions PASS. The hotfix branch remains separate from main; merge and Production deployment are pending separate authorization.

## 2026-10-20 — Live stocktake and transfer preservation

- I4 feature branch `feature/inventory-live-stocktake-and-draft-preservation` from Production/main `7e50a0ba3c5b2e12325d6d439f72d4572985c1aa`; focused movement validation passed; unmerged and undeployed.
- Canonical movement-ledger reconciliation uses each line’s baseline and count movement watermark, carries later movements into the closing position, and posts only approved variances. Counting never posts stock. Transfer drafts retain input and persist invoice reference; open stocktakes no longer block transfer lifecycle.
- Additive migrations add count-line movement watermarks/counter and transfer invoice reference. Focused QA and browser results are recorded in `.ai/TEST_RESULTS.md`; POS/return/receiving and transfer follow-up checks passed. Physical camera and owner operational UAT remain; merge and deployment are separate approvals.

# 2026-10-19 — Standalone warehouse and transfer numeric UI

- Class D3; branch `hotfix/inventory-standalone-warehouse-and-numeric-ui`; source/previous Production baseline `2b29a067177d6dc615fd95dca0ea3768d358d8a1`; merged and deployed at `7e50a0ba3c5b2e12325d6d439f72d4572985c1aa` to `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.36-standalone-numeric-7e50a0b`. Rollback target: `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.35-invoice-destinations-2b29a06`; rollback required NO.
- An active warehouse/store may exist and operate with branch_id = NULL. Branch membership is not required for inventory transfers or purchase receiving.
- Removed the transfer create/update source-branch requirement while retaining active, same-company and visible-store checks. Branchless transfer numbering is atomic and company scoped; linked branch numbering is unchanged. Purchase receiving's generated transfers use the same allocator.
- Manual lookup now uses the invoice-import quantity and money presentation formatters. Transfer-row numeric fields and values are centered without changing numeric direction or storage.
- Focused disposable MariaDB feature tests and 1440/390 local browser fixture passed; see `.ai/TEST_RESULTS.md`. No migration, broad QA, merge, deployment, or Production data action.

# Pending Change Batch

## Historical — 2026-10-21 system-administrator operational-authority hotfix

- Class D3; branch `hotfix/system-administrator-operational-authority` from merged main `b9b67a79560af0dcae73c259630d8e47da6fe409`. The approved privileged-authority release was blocked before build when the central bypass omitted `system-administrator`. Production remains safely on `d4b6a854266662706701cb881d69844ecfbd4422` at `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.40-stocktake-reference-ui-d4b6a85`.
- The narrow runtime correction adds the active `system-administrator` role to `User::canBypassApproval()` and its existing single-company operational-scope fallback. Focused QA passed 4 transfer tests/71 assertions and 3 shared purchasing tests/34 assertions, covering ordinary-user denial, all three privileged transfer lifecycles, company isolation, retry/idempotency, and audit actor/creator/approver IDs. PHP syntax, Pint, and diff hygiene passed. No migration, schema change, Production seeder, merge, or deployment is authorized by this branch. Exact next action: owner review of the pushed hotfix, then separately authorized merge and deployment.

## 2026-10-18 — Transfer zero-stock quantity guard hotfix

- Class D3; branch `hotfix/transfer-zero-stock-quantity-guard` from approved main `b92656d171d5ee675c8517d0f68ca165f7d37ed6`; verified, awaiting commit/push, unmerged and undeployed. Production remains at safe `708826b49ea70155150bc5059015d1fefac3a07f` after the failed immutable purchase-transfer candidate was rolled back.
- Runtime files: `resources/js/keyboard-product-lines.js`, `resources/views/inventory/index.blade.php`. Requested transfer quantity is a native number input with current source-store maximum for imported, manually selected, and existing draft lines; zero availability starts at zero and disables/clears destination allocation inputs. The existing server create/update actions reject quantities above locked source availability; no second server rule or schema change was added.
- Focused disposable MariaDB test PASS 9/36; authenticated local browser PASS at 1440 and 390 for zero/5/6 and imported/manual parity. PHP/JS syntax, Pint, Blade cache, Node 20 build, diff hygiene PASS. Disposable database/user/server cleaned up. No broad QA, merge, or deployment.

## 2026-10-18 — Purchase invoice transfer lines and clean numeric display

- Class: D3 follow-up (corrected from V1 because the branch changes a route response, queries, shared formatting, and invoice-store authorization); branch `feature/purchase-invoice-transfer-lines-clean-numeric`, based on recorded Production baseline `708826b49ea70155150bc5059015d1fefac3a07f`; validated, undeployed, and unmerged.
- Runtime files: `routes/inventory.php`, `resources/js/keyboard-product-lines.js`, `resources/views/inventory/index.blade.php`, `app/Support/ProductQuantity.php`, plus shared numeric presentation updates in the affected inventory/purchasing views.
- Approved purchase invoice loading now returns every distinct invoice product and its current source-store availability, canonical source cost, and effective selling price. The existing product-line template imports those products into normal transfer rows, skips product IDs already in the draft, includes zero-stock rows with a zero maximum, and keeps the explicit warehouse-mismatch source switch. Compact delete controls remove any line and trigger allocation recalculation.
- `ProductQuantity::format` now displays quantities rounded to at most two decimals with insignificant zeros removed; the existing `x-money` component remains the monetary formatter at two decimals. Database columns, stored values, normalization rules, and posting/lifecycle architecture were not changed.
- D3 follow-up: invoice lookup is scoped to visible stores before loading document data and accepts the displayed invoice number or numeric ID; the UI encodes the entered identifier. Decimal-string quantity formatting retains exact large integer digits. Two existing quantity methods now expect legitimate decimal display while continuing to assert integer normalization.
- Verification: disposable `rajeh_qa_purchase_transfer_d3`, restricted temporary user, migrations, transfer feature 8/33 PASS, authenticated browser visible/hidden invoice and UI checks PASS, quantity methods 1/17 and 1/10 PASS; PHP lint, Pint, Blade cache, Node 20 build, and diff hygiene PASS. The QA database/user/private runtime were removed and verified absent. No broad QA, Production write, merge, or deployment.

## 2026-10-18 — Inventory/invoice UI batch Production deployment

- Class I4; deployed from approved main `708826b49ea70155150bc5059015d1fefac3a07f` as immutable release `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.32-ui-708826b`. Previous rollback release: `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.30-inventory-mobile-f9bfdb2` (`f9bfdb2d164582e12cd8541ad20493463928b44b`).
- Preserved shared `.env`, shared `storage`, and `public/storage`; generated Laravel caches only in the final release path; no migrations or seeders ran. Atomic activation and immediate `ea-php85-php-fpm` reload passed. The first candidate browser invocation rolled back automatically because its stage variable was applied to the transformer rather than the browser; the corrected retry passed.
- Focused origin/public HTTP checks for `/`, `/up`, `/login`, CSS and JS assets returned 200. Authenticated GET-only browser checks at 1440 and 390 passed Inventory header, invoice copy/feedback, RTL/Cairo, zero mutating requests, and zero console errors. At 390, viewport/client/document/body widths were exactly `390/390/390/390`; no new Laravel errors; final rollback required: NO.

## 2026-10-18 — Inventory header and invoice-number copy UI

- Class V1; verified, undeployed. Branch `hotfix/inventory-header-and-invoice-copy` from `f9bfdb2d164582e12cd8541ad20493463928b44b`; commit is this branch's focused hotfix commit.
- Runtime files: `resources/views/inventory/index.blade.php`, `resources/views/purchasing/invoices.blade.php`.
- Default Inventory header keeps New transfer only; Purchasing invoice rows with an actual number have an inline copy control that copies only the displayed number and shows success feedback. No fallback ID/reference copy button, business rule, schema, route, permission, or API change.
- Focused compiled-Blade browser fixture PASS at desktop 1440 and mobile 390 for visible header, exact clipboard value, inline layout, no horizontal overflow or console errors. Affected PHP/Blade syntax, Blade cache, Pint and diff hygiene PASS. No merge or deployment.

## 2026-10-18 — Shared mobile topbar overflow containment

- Class V0; undeployed. Branch `hotfix/f160-mobile-overflow-regression`, source main `f1603bcc8f2fde04368036eedc8b5c69f910bbd9`; commit is this branch's focused hotfix commit.
- Runtime file `resources/views/layouts/app/sidebar.blade.php`; mobile `.app-topbar__actions` now wraps below the identity/search row at widths up to 767 px, preserving the action controls and the latest inventory/invoice UI.
- Exact 390 px cause: `.app-topbar__actions { flex: none; }` kept a 306 px action group beside the 120 px identity and 44 px search controls, placing it at x=-96..210 and extending `body.scrollWidth` to 486. The prior wrap existed only in the transfer-specific inventory view; current main did not change the shared selector, so default inventory and invoice pages remained exposed to the overflow.
- Authenticated GET-only browser checks PASS at 1440 and 390 for body/document width containment, inventory header, real-invoice copy value and `تم النسخ`, with zero console errors and no writes. Affected syntax, Pint, Blade cache and diff hygiene PASS. No merge or deployment.

## 2026-10-18 — Inventory transfer mobile overflow containment

- Class V1; deployed in Production `f9bfdb2d164582e12cd8541ad20493463928b44b`. Branch `hotfix/inventory-transfer-mobile-overflow`; source main `6d0a0a10c15c78e374f923f03d5069438fc1e544`.
- Runtime file `resources/views/inventory/index.blade.php`; the only change wraps transfer-screen topbar actions below the identity/search row at mobile widths up to 767 px.
- Rendered 390 px root cause: `.app-topbar__actions` x=-96 to 210, width 306 px, made `body.scrollWidth` 486 despite `documentElement.scrollWidth` being masked at 390. After correction, viewport/client/document/body/full-page-image widths are all 390.
- Authenticated GET-only Node 20 browser checks at 1440/1024/768/430/390/375 passed; PHP syntax, Pint, Blade cache and diff hygiene passed. Subsequently merged and deployed in the approved `f9bfdb2` release.

## 2026-10-18 — Selected destination transfer UX

- Class V1; deployed in Production `f9bfdb2d164582e12cd8541ad20493463928b44b`. Branch `hotfix/inventory-transfer-selected-destinations-ux`; commit is this branch's focused hotfix commit.
- Runtime file `resources/views/inventory/index.blade.php`; supporting CURRENT_TASK, SESSION_SUMMARY, TEST_RESULTS, and pending-batch entries.
- Allocation controls follow checked destinations, unchecking clears values, remaining uses requested quantity, and numeric values use LTR isolation.
- Focused Node 20 browser checks at 1440/1024/390 plus PHP syntax, Pint, Blade cache and diff hygiene passed. Subsequently merged and deployed in the approved `f9bfdb2` release.


## 2026-10-18 — Inventory transfer allocation UI/state

- Class: V1; status: focused verification PASS; deployed in Production `5206127df0fa92ad944faf10304dad0fc566a13b` and retained in `f9bfdb2d164582e12cd8541ad20493463928b44b`.
- Commit: the commit containing this entry on `hotfix/inventory-transfer-allocation-ui-state`.
- Runtime file: `resources/views/inventory/index.blade.php`; supporting records: CURRENT_TASK, TEST_RESULTS, SESSION_SUMMARY, and this ledger.
- Safe finite allocation totals/remaining and delayed over-allocation warning; responsive readable allocation cells; matching added-row state. Existing server validation and inventory lifecycle preserved.
- Focused Node 20 form browser checks at 1440/1024/390, PHP syntax, Pint, Blade cache passed. Final diff check recorded at commit. The focused implementation session created no build or release; subsequent Production releases are recorded above.


## 2026-10-15 — Audit Logs permission data cleanup (#55)

| Class | Repair | Branch | Affected files | Verification/status |
|---|---|---|---|---|
| V1 | Remove only obsolete `audit_logs` permission rows (`approve`, `cancel`, `create`, `edit`, `logical_delete`, `override`, `reject`, `reverse`, `submit`) and their role pivots; preserve `view`, `export`, `print` and every other module | `issues/permission-taxonomy-55-data-cleanup` | `database/migrations/2026_10_15_000123_clean_audit_log_permissions.php`, `tests/Feature/PermissionTaxonomyDataCleanupTest.php` | Focused isolated MariaDB PASS 6 tests / 24 assertions; PHP syntax, Pint and diff checks PASS; migration not run in production, no merge or deployment |

## 2026-10-15 — Permission taxonomy #55

| Class | Repair | Branch | Affected files | Verification/status |
|---|---|---|---|---|
| V1 | Map legacy `edit`/`logical_delete` presentation to canonical Update/Delete labels, restrict fresh Audit Logs permissions to View/Export/Print, and retain legacy permission rows and role pivots for compatibility | `issues/permission-taxonomy-55` | `app/Modules/Platform/Support/PermissionPresentation.php`, `database/seeders/ProductionSeeder.php`, `tests/Feature/PermissionTaxonomyTest.php` | Focused isolated MariaDB PASS 5 tests / 20 assertions; PHP syntax, Pint and diff checks PASS; Fast QA ran once with focused regression/integrity/runtime PASS and pre-existing PHPStan plus unavailable-browser failures; no migration, merge or deployment |

## 2026-10-14 — Supplier settlement and preferred payment separation (#31)

| Class | Repair | Branch | Affected files | Verification/status |
|---|---|---|---|---|
| V1 | Expose the existing nullable preferred payment method separately from supplier settlement method; persist both independently, validate active payment-method references, include both in supplier audit snapshots, and show distinct bilingual form/detail labels | `issues/supplier-settlement-preferred-payment-31` | `app/Modules/Catalog/Actions/SaveSupplierAction.php`, `resources/views/catalog/suppliers.blade.php`, `tests/Feature/SupplierBusinessDecisionTest.php` | Focused isolated MariaDB PASS 2 tests / 12 assertions; PHP syntax, Pint and diff checks PASS; no migration, merge or deployment |

## 2026-10-13 — POS close-shift link (undeployed)

| Class | Repair | Commit | Affected files | Verification/status |
|---|---|---|---|---|
| V1 | Show a cashier-owned open-shift link beside the POS shift indicator, targeting the existing blind-count form | `hotfix/pos-close-shift-link` task commit | POS layout/index/shift Blade and Arabic locale JSON | Focused authenticated browser 16 checks and isolated MariaDB checkout/rounding 2 tests/14 assertions passed; no Fast QA, merge or deployment |

## 2026-10-07 — Local Module 1/2 QA presentation batch (undeployed)

V0/V1 presentation portions: named validation labels and titles, localized approval errors, Settings pagination/search, saved barcode display, image-limit feedback and product metadata fields. Included in the separately authorized sensitive D3/I4 QA integration; exact per-bug commits are in `.ai/qa/module2-20261007/results.json`. One initial Module 1 asset build and one final combined build passed; final Blade compilation passed. No version bump, release artifact or deployment; production and all historical batch records are preserved. Full-suite/static debt remains documented. This entry records local completion, not closure of a production release batch.

| Class | Repair | Commit | Affected files | Verification/status |
|---|---|---|---|---|
| V0 | BUG-14: keep the cash-drawer table readable with a minimum width and prevent drawer names/status labels from breaking into narrow columns | `f96dd09722455783b4baf615ca59fde0112c9a73` | `resources/views/platform/admin/drawers.blade.php` | Lead approved, exact branch pushed and integrated locally; existing focused render 1 test / 10 assertions, Pint and diff checks passed; browser sizing unverified; undeployed |
| L2/V1 | BUG-17: show field-specific duplicate, malformed and invalid-check-digit reasons in barcode modal and product creation | `fix/bug-17-barcode-validation` | `resources/views/catalog/products.blade.php`, `resources/views/catalog/product-form.blade.php`, `tests/Feature/ProductPreferredSupplierFormTest.php` | Focused check 1 test / 28 assertions, PHP syntax and Pint passed; no browser check or push; undeployed pending lead review |

## 2026-10-07 — BUG-26 POS cashier logout (local, undeployed)

| Class | Repair | Commit | Affected files | Verification/status |
|---|---|---|---|---|
| V1 | Add a visible CSRF-protected POST logout action to the dedicated POS header, reusing Fortify's existing logout/session invalidation | `3f1e1f5501982a7c6fffef51aa7fe946bf3ea87f` | `resources/views/layouts/pos.blade.php`, `tests/Feature/PosShiftOpenRouteTest.php` | Focused cashier POS render/logout check passed 1 test / 9 assertions; Pint, PHP lint, and diff hygiene passed; no browser or deployment |

## 2026-10-03 — Full-System Audit Final UI Batch

Owner explicitly authorized closure, integration and deployment of the remaining audit fixes. Exactly two existing-user temporary sessions were authorized for authenticated browser coverage; no grant or business-record mutation is included.

| Class | Repair | Commit | Verification/status |
|---|---|---|---|
| V0 | Username sign-in separator in ar/ar-EG/en | `b04924d` | Prior focused three-locale render passed; included in final batch |
| V1 | Safely encoded Translation Editor action arguments | `1d2a363` | Five controls × three quote/markup fixtures passed; no saves/resets |
| V1 | Canonical inventory movement select values | `9842658` | 25 options/selection retention in three locales passed |
| V1 | Idempotent line-total text updates stop observer loop | `5278c0f` | Previously stalled adjustment/Party/quotation pages rendered with exact candidate asset guard; unsaved input returned to event loop |
| V0 | Arabic audit/setup/store copy and header grid sizing | `8f80ab4` | Focused copy render passed; measured Arabic overlap 110.797px became 13.594px separation |
| I4 | One combined version/build/artifact/release | Release commit follows | `0.1.22-audit-final`; one Vite build passed; activated successfully; affected checks passed; both sessions revoked |

The af6874e UI batch activated successfully and affected live checks passed. Both approved sessions were immediately revoked; cookie/token manifest deleted. This UI batch is closed. Frozen e6d2d1e archive and all historical records are preserved unchanged.

## 2026-10-03 — PDF Output Repair After Immutable UI Freeze

L2 display-only PDF repair: shape Arabic cells in English exports, retain ASCII/numeric strings, restore page margins, explicitly size the table font and use proportional header widths; add missing display-label translations. The focused bilingual render from the preserved exported workbook passed after diagnosing the HTML-root margin and table default-font overrides. No production data/schema/grants changed and no session was recreated.

I4: `0.1.22-audit-final2` replaces the frozen UI release with a new immutable artifact. Existing e6d2d1e and af6874e artifacts remain unchanged. Reuse the already-built UI assets; no additional Vite build or broad browser pass. PDF release `24f1c5a` activated successfully; final compact smoke passed. This PDF batch is closed. Prior UI batch remains closed. No further audit application entry is pending.

## 2026-10-03 — D3 Export Confidentiality Repair (Closed)

Product document export admitted the module export permission but included sensitive Unit cost without checking the independent existing `products_categories_brands.cost_view` permission. Route-only repair `5440501` gates both header and mapped value; queries, price calculations, stored records and role/grant definitions remain unchanged. Focused success/hidden-cost/unsupported-format-boundary verification passed with synthetic output rows; 11/10 columns align, hidden cost is not accessed, XLSX omits it and both PDF shapes render. Existing normal-user route-denial evidence retained; no authenticated session recreated.

Dedicated I4 release commit `599a5084f7c1c2bb0aee5d6a67fc5df3d9dfd0be`, version `0.1.22-audit-final3`, merged/pushed via `10957d6`; immutable artifact activated and final-target compact smoke passed. No extra Vite build, schema/data/grant change or backup/test loop. Original/UI/PDF artifacts remain preserved. This batch is closed; no audit application change remains pending.

## 2026-10-01 — Current Operational Documentation Entry

The initial documentation commit is `8ccb88c7e90b12883619793d3e1dd934653eadc6` and is pushed on `docs/post-hotfix51-operational-baseline`. The following cleanup remains part of that same documentation branch; neither entry closes an application release batch.

| Classification | Short description | Commit | Affected files | Status |
|---|---|---|---|---|
| V0 | Restore historical DEC-085/DEC-086 provenance, correct Translation Editor citation to restored DEC-087, and record conditional Git identity check | Cleanup commit on `docs/post-hotfix51-operational-baseline`, message `docs: clean post-Hotfix51 operational baseline` | `.ai/DECISIONS.md`, `.ai/UI_SCREENS.md`, `AI_INDEX.md`, `.ai/CURRENT_TASK.md`, `.ai/PROGRESS.md`, `.ai/PENDING_CHANGE_BATCH.md`, `.ai/SESSION_SUMMARY.md` | Documentation corrected for branch review; commit/push authorized, merge/deployment excluded |

| Classification | Short description | Commit | Affected files | Status |
|---|---|---|---|---|
| V0 | Post-Hotfix51 main/worktree, GitHub/account/remote, branch publication, and CodeGraph operating guidance; current versus historical status clarification | Documentation commit on `docs/post-hotfix51-operational-baseline`, message `docs: update post-Hotfix51 development baseline` | `AGENTS.md`, `AI_INDEX.md`, `.ai/CURRENT_TASK.md`, `.ai/CURRENT_MILESTONE.md`, `.ai/PROGRESS.md`, `.ai/PENDING_CHANGE_BATCH.md`, `.ai/SESSION_SUMMARY.md` | Documentation updated for branch review; commit/push authorized, merge/deployment excluded |

The current recorded production application is Hotfix51 `b709193815c548bb0100a0b641171c5af7aaf314`; the post-Hotfix51 GitHub/main baseline is `4182c08` at `/home/rajeh/worktrees/rajeh-erp-main`. This documentation entry does not create a release, close an application batch, or authorize deployment. The resulting commit can be resolved from the named branch and message; no self-referential commit hash is asserted here.

## Historical Batch Records

All entries below retain their original session-time wording, including earlier active-release and undeployed labels. They are historical records, not the current production baseline or a current activation prerequisite. Historical ledger reconciliation is outside this task; no prior entry is cleared or rewritten.

## 2026-09-14 — Hotfix39 Customer Notes Batch 3

- **I4 / release candidate / undeployed:** Compact product/customer/supplier masters, incomplete-product Draft/Resume, supplier item identity, dynamic multi-identifier lookup, branch-priced product matrix reuse, opening-inventory header/keyboard grid/paste/XLSX/totals/draft/post/reversal/zero workflow, stable customer code, loyalty help, and structured supplier contacts.
- Exact baseline is Hotfix38 `45d9e78c757cd83a596b11d613475dbdc441d2c3`. Backend export routes/services, stored supplier payment terms, existing pricing contracts, tenant permissions, and historical accounting/inventory records remain intact.
- Activation must prove 000111 and 000112 are already `Ran`, execute only migration 000113, and retain its nullable backward-compatible additions if exact Hotfix38 application rollback occurs.

## 2026-09-14 — Hotfix38 Customer Notes Batch 2

- **I4 / release candidate / undeployed:** Accessible payment/tax modals; UI-hidden tax validity dates; nullable product tax assignment; branch/document continuous numbering; consolidated printer/template management with 15 document types and an accurate relevant-printer count.
- Exact baseline is Hotfix37 `0f0e7aa18d47986f6805c89b3494f4f55042e151`. The only schema change is forward-compatible migration 000112. Historical invoice tax snapshots and accounting calculations are untouched.
- Activation must run exactly migration 000112 after proving 000111 is already `Ran`. Application rollback restores exact Hotfix37 and deliberately retains the nullable schema addition.

## 2026-09-14 — Hotfix37 Customer Notes Batch 1

- **V1 / release candidate / undeployed:** Reusable accessible `!` contextual help; sidebar hover/focus/active/scroll/state/keyboard repair; duplicate-destination suppression and requested supplier/customer/product ordering; branch-screen action/timezone cleanup; company timezone and persisted default list-filter visibility in General Settings; user/role total-card removal with unchanged authorization behavior.
- Exact baseline is Hotfix36 `c5f6d57150a04d95915a72f5146a2aebd369bc83`. No migration or business-data change is included. Optimized unexecuted promotion/activation scripts are restricted to that baseline and exact rollback target.

**Status:** HOTFIX19 CORRECTIVE CANDIDATE — VERIFIED — NOT DEPLOYED
**Scope:** v0.1.22-hotfix19 POS Livewire root and extracted-release cache repair, retaining all Hotfix17/18 work
**Opened after:** operator-verified rollback to exact `v0.1.22-hotfix16`
**Source baseline:** exact Hotfix18 commit `c0fa3ed3623f7c259ba2c29109c08184bfa42eb4`
**Production baseline:** owner-confirmed: exact Hotfix16 commit `31883f5c823458d30738708ec96e1d02337f4c9b`, migration 000111 already Ran, maintenance OFF.
**Baseline disposition:** Hotfix16 remains active and is the exact Hotfix19 activation prerequisite and rollback target. It was not rechecked.

Do not clear or archive this ledger until an authorized operator confirms successful Hotfix19 production activation and smoke verification.

## Pending Changes

| Classification | Short description | Commit | Affected files | Status |
|---|---|---|---|---|
| V1/D3 | Full Hotfix17 compact POS, checkout, customer/product/scanner, order-tab and payment-card redesign | Retained in Hotfix19 | Hotfix17 POS Livewire/components/views | VERIFIED — NOT DEPLOYED |
| D3/I4 | Full Hotfix17 source-linked refund, inventory/payment/accounting/authorization/audit/idempotency and receipt workflow | Retained in Hotfix19 | Migration 000111; return action/calculator/models/components/views/routes | VERIFIED — NOT DEPLOYED |
| V1/D3 | Hotfix18 Blade/PHP compiler repairs without UI or authorization bypass | Retained in Hotfix19 | POS page/cart/checkout and purchasing-return print Blade templates | VERIFIED — NOT DEPLOYED |
| V1 | Permanent closed-state Livewire root for the retained refund wizard | Hotfix19 release commit | `resources/views/livewire/pos/refund-wizard.blade.php` | VERIFIED — NOT DEPLOYED |
| I4 | Post-extraction bootstrap cache creation, recursive `rajeh:rajeh` ownership, mode `0775`, pre/post cache-generation writability, application-user Artisan, already-Ran 000111 preservation and exact Hotfix16 rollback | Hotfix19 operator scripts | Promotion and activation scripts | VERIFIED — ACTIVATION PREFLIGHT DEFERRED |

Production access, deployment, backup execution, migration execution, active-symlink modification, service operation, browser testing, tag, push, and remote contact are outside candidate preparation and remain unexecuted.
## 2026-09-12 — Egyptian Arabic locale completion batch

- **V0 / completed locally / undeployed:** Rewrote all 6,115 `ar-EG` JSON values and 244 PHP locale leaves, added contextual Egyptian render wording and human-readable source labels, and preserved `ar`/`en`. Commit recorded after final verification. No deployment or packaging.

## 2026-09-13 — Hotfix36 Egyptian Arabic quality-repair batch

- **V0 / release candidate / undeployed:** Integrated the direct-child localization repair onto exact Hotfix35, retaining all 6,115 JSON keys, 244 PHP leaves, unchanged `ar`/`en`, and unchanged functional boundaries. Offline package and fail-closed exact-Hotfix35 operator workflow prepared; no production or operator action executed.

## 2026-10-07 — BUG-15 audit response isolation (undeployed)

- **Class:** V1. **Repair:** Isolated audit filters/table/pagination and audit detail modal into native Livewire islands; preserved the existing access and per-record authorization checks.
- **Commit:** a667949c7201a63982ed63ca1e17b3c91e53600b (`fix/bug-15-audit-log-islands`). **Files:** `resources/views/platform/system/audit-log.blade.php`, `tests/Feature/Module1AuditPresentationTest.php`, `.ai/TEST_RESULTS.md`, `.ai/SESSION_SUMMARY.md`, `.ai/PENDING_CHANGE_BATCH.md`.
- **Status:** Lead approved, exact branch pushed and integrated locally. Focused regression passed (1 test/13 assertions, including modal, page-2 table island, and denied update after access removal); integration overlap 21 tests/164 assertions and the single build passed. Browser/network timing remains unverified. No release or deployment.

- BUG-24 / V0 / undeployed: readable product validation attributes, resources/views/catalog/{product-form,products}.blade.php; focused locale proof 1/78 PASS; separate commit on fix/module2-qa.

- BUG-18 / V1 / undeployed: visible required outlet and default branch context in barcode label form/render; focused proof 1/10 PASS. Stock-linked queue remains blocked; single fix/module2-qa commit.

- BUG-20 / V0 / undeployed: visible next-5-EGP rounding explanation in pricing matrix, en/ar/ar-EG; existing calculator focused proof 1/7 PASS. Separate commit on fix/module2-qa.

## 2026-10-13 — Repository consolidation candidate

- **V0 / undeployed:** Shared application footer displays `v1.22` for the existing `0.1.22-cost-optional` release value. It strips the legacy leading `0.` and any descriptive suffix; later numeric releases display as `v1.22.1` or `v1.23`. **Commit:** `integration/full-repository-consolidation` candidate commit. **File:** `resources/views/components/app-footer.blade.php`. **Status:** Prepared for review; no release artifact, main merge, or deployment.

## 2026-10-15 — Operational context batch #15/#34/#35/#36/#42/#52/#55 (undeployed)

| Class | Repair | Branch | Affected files | Verification/status |
|---|---|---|---|---|
| V1 | Restore permission-gated navigation for existing Party, quotation, gift-card, suspended-sale, supplier-history, invoice-readiness, price-history, and POS financial-control screens; add the POS readiness setup link | `issues/operational-context-15-35-36-42-52-55` | `config/navigation.php`, `resources/views/platform/initial-setup.blade.php` | Focused batch PASS; no migration; undeployed |
| V1 | Keep count-entry errors on the count page, accept explicit zero through the canonical zero-completion path, and show count recovery for transfer locks | `issues/operational-context-15-35-36-42-52-55` | `app/Modules/Inventory/Actions/RecordStockCountZeroAction.php`, `routes/inventory.php`, `resources/views/inventory/index.blade.php` | Focused batch PASS; Fast QA focused regression PASS; undeployed |
| V1 | Show specific maker-checker feedback for inventory-adjustment self approval | `issues/operational-context-15-35-36-42-52-55` | `routes/inventory.php`, `resources/views/inventory/index.blade.php` | Focused batch PASS; undeployed |
| V1 | Resolve bounded document/product references for movement, approval, alert, report, and audit presentations | `issues/operational-context-15-35-36-42-52-55` | `app/Modules/Inventory/Support/StockMovementSourceReferences.php`, `app/Modules/Platform/Support/ApprovalInboxReferences.php`, `app/Modules/Platform/Support/AuditLogPresentation.php`, `app/Modules/Reporting/Queries/ReportSnapshot.php`, `routes/reporting.php`, related views | Focused batch PASS; undeployed |
| L2 | Give permission checkboxes specific accessible names | `issues/operational-context-15-35-36-42-52-55` | `resources/views/platform/admin/role-permissions.blade.php` | Focused batch PASS; #55 broader permission taxonomy remains blocked on owner decision; undeployed |

## 2026-10-15 — Customer/catalog/reporting consolidated batch (#13/#17/#19/#20/#44/#50/#58/#60)

| Class | Repair | Branch | Affected files | Verification/status |
|---|---|---|---|---|
| V1 | Normalize customer phone search across Arabic/Persian digits and Egyptian international forms; add sales invoice customer, phone, and cashier filters; use bilingual product-name fallback, consistent draft/status presentation, pending-code guidance, and base selling-price display | `issues/customer-catalog-reporting-13-17-19-20-44-50-58-60` | `app/Modules/Customer/Support/PhoneNormalizer.php`, `routes/customers.php`, `routes/retail.php`, `resources/views/pages/sales/invoices.blade.php`, `resources/views/catalog/products.blade.php`, `resources/views/catalog/product-detail.blade.php` | Focused customer normalization PASS; PHP syntax, Pint, and diff checks PASS; Fast QA 7/8 with the only failure a pre-existing PHPStan finding in unchanged `app/Modules/Catalog/Actions/SaveProductAction.php:267`; no migration |
| V0 | #13 current bounded audit-export and label-search implementation verified from current main | same | existing routes/services | ALREADY_FIXED_VERIFIED |
| Blocked | #50 existing duplicate customer records need owner-approved merge/retention and non-Egyptian uniqueness policy before data correction | same | none | BLOCKED pending Ahmed decision |
| Blocked | #58 quotation detail and shared-link expiry need approved retention/expiry policy before implementing irreversible link behavior | same | none | BLOCKED pending Ahmed decision |

## 2026-10-15 — Customer and quotation policy batch (#13/#17/#19/#20/#43/#44/#50/#58/#60)

| Class | Repair | Branch | Affected files | Verification/status |
|---|---|---|---|---|
| V1 | Normalize Egyptian mobile variants before customer uniqueness checks while preserving existing duplicate records; add persistent quotation share links with 30-day/valid-until expiry, signed-token validation, audit metadata, revoke and regenerate controls; expose existing quotation status transitions in the UI | `issues/customer-quotation-policy-43-50-58` | Customer phone normalizer, quotation share action/model/routes/view, quotation model, migration, focused customer test | Focused normalization test PASS; PHP syntax/Pint/diff checks PASS; Fast QA 7/8 with the only failure a pre-existing PHPStan finding in unchanged `app/Modules/Catalog/Actions/SaveProductAction.php:267`; migration not run |
| V0 | #13, #17, #19, #20, #44 and #60 behavior from current main verified as already present | same | existing current-main implementation | ALREADY_FIXED_VERIFIED |

## Pending Production deployment — Final stocktake reference parity + Excel import

The validated `feature/stocktake-strict-reference-match` batch is merged/pending completion into main from feature tip `00ef552b1c2eef8927cfa372f71e4d3365844d73`. Production remains on `83d516a9fc547c836b097ce72bbe0f79452ad9c3` until explicit deployment approval.

Scope:
- reference-matched stocktake list/create/count/review UI
- compact warehouse selector on create
- Add Item / Excel Import / Barcode / Camera / Add All toolbar
- populated desktop/tablet count table
- mobile product cards exposing product/SKU, expected, actual, difference, and status
- stocktake-specific `.xlsx` / `.csv` quantity import
- no migrations
- no stock posting during import
