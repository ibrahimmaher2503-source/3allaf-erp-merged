# Rajeh ERP Full-System Audit and Client-Readiness Report

**Audit date:** 2026-10-03  
**Audit window:** 2026-10-03 UTC  
**Source baseline:** `49f429420b576bb08f05ea757cf44aca9f8e9140`  
**Audit branch:** `audit/full-system-client-readiness`  
**Production baseline at audit start:** `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.22-hotfix51-49f429420b57`  
**Final application target:** `599a5084f7c1c2bb0aee5d6a67fc5df3d9dfd0be` (`0.1.22-audit-final3`)
**Audit class:** I4, owner-authorized release and operational verification

**Final audit status:** Closed after completing the remaining authorized browser/output coverage and repairing confirmed defects. Original completed scans were preserved. Unavailable populated-workflow, concurrency, hardware and human-UAT acceptance is explicitly unexecuted, rather than counted as passed.

## Readiness summary

| Area | Final disposition |
|---|---|
| Confirmed application defects in executed coverage | Repaired; no unresolved confirmed critical/high application defect in these paths |
| Authenticated Admin/User visual coverage | Complete for 386 distinct route/locale/viewport scenarios; two sessions revoked |
| Production release health | Final immutable release activated; compact smoke recorded separately below |
| Normal-user operational configuration | Owner review required: custom sensitive grants, denied shift access and no open assigned shift; no grant changes authorized |
| Financial/inventory acceptance | Populated posting/reversal/concurrency/reconciliation unexecuted: no authorized disposable XAMPP environment available |
| Output/device acceptance | XLSX/CSV downloads and bilingual catalog PDF verified; transaction receipts, physical printers, scanners, terminals and drawers need actual fixtures/devices |
| Backup scheduling | Explicit encrypted backup/monitor and caller-cwd regression passed; next scheduled daily run remains a monitoring follow-up |

**Production readiness:** The deployed application is operational in executed checks. **Unconditional client handover is NOT approved.** Opening configuration, cashier/shift permissions, populated financial/inventory UAT and physical-device acceptance must be resolved before trading/customer sign-off. No milestone, Phase Gate or human UAT approval is inferred from this audit.

## Final client-acceptance pass — 2026-10-03

**Final readiness status: CONDITIONAL HANDOVER — with explicit remaining blockers.** This focused pass did not repeat the 386-state audit, CodeGraph work, or prior test coverage. It used read-only production inspection, the existing backup monitor/restore commands, one isolated restore, and the existing scheduler/runtime checks.

### Cashier and shift

Production company currency is configured as EGP. Branch `DOKKI`, selling store `DO-01`, and active drawer `CDR-001` are assigned to user `cashier1` (user 3), and no active shift currently exists. The user is assigned to the custom `cashir` role. Its live grants include POS sale/payment capabilities but no `shifts_cash_movements.view`, `.create`, `.submit`, or `.print`; the canonical `cashier` role has those shift grants but is not assigned. The actual open/operate/close journey therefore cannot start for the assigned cashier. No role, grant, assignment, drawer, shift, or business record was changed because selecting between the custom grant set and canonical role would invent an owner authorization decision. Required owner action: approve the intended cashier role/grants, then configure and manually run one opening-float → sale → blind close → manager review/close journey.

Additional prerequisites are incomplete: no active document sequence or tax setting is present in the live database. These are configuration blockers for a complete trading journey, not application defects confirmed by this pass.

### Accounting and inventory UAT

The live database has no sales, purchase invoices, stock balances, or stock movements. No approved disposable XAMPP MySQL/MariaDB schema was available in this environment; the only local MariaDB instance contains production and historical verification databases. No production QA records were created. Consequently purchase/receipt, stock movement, sale, return/refund, and accounting/report reconciliation remain **UNEXECUTED**, with no figures claimed as passed. A marked reversible UAT seeder exists in the release, but it was not run against production.

### Printing and device acceptance

The shipped system-side paths remain browser-print based: sales A4 and thermal receipt views, closed-shift A4 and thermal views, purchase/return/order print views, printer/template previews, and label print/PDF views use `window.print()` or the configured PDF response. Barcode camera support is browser capability-gated, and offline/device enrollment is a separate explicit flow. Production currently has zero `printer_configurations` and zero `print_templates`, zero sales/closed shifts to render, and no enrolled device or physical printer available here. System-side output generation was therefore verified only to the existing implementation boundary and prior catalog PDF evidence; transaction receipt content and physical printing were not claimed. On-site confirmation remains required for configured thermal/A4 printers, Arabic/RTL receipt layout, scanner/camera, cash drawer, and any terminal/device bridge.

### Scheduled backup

The scheduler is registered with `platform:backup:run` daily at 02:30, cleanup at 03:30, and `backup:monitor` hourly; the next run is scheduled for the next 02:30 window. The existing monitor returned healthy. The latest archive was restored into an empty isolated `/tmp` directory and produced `db-dumps/mysql-rajeh_toyjoy.sql` (475,050 bytes). The latest full scheduled archive visible on disk is `2026-10-02-02-30-04.zip`; no `2026-10-03-02-30-*` archive exists. The 2026-10-03 archives at 11:14 and 12:22 were ad hoc verification backups, not the scheduled 02:30 run. Thus restore verification passed for an existing archive and monitoring is healthy, but the current scheduled-run evidence is incomplete until the next daily run succeeds. No extra backup was created in this pass.

### Issues and fixes

No confirmed application defect was found in this focused pass, so no application fix, deployment, role/grant change, data mutation, or release replacement was made. The remaining items are owner/configuration, disposable-UAT, physical-device, and scheduled-run evidence blockers.

**Final production release:** `0.1.22-audit-final3`, application commit `599a5084f7c1c2bb0aee5d6a67fc5df3d9dfd0be`, active release `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.22-audit-final3-599a508`.

**On-site/client confirmation required:** approve cashier role/grants and opening configuration; execute the populated purchase → stock → sale → return/refund reconciliation in a disposable approved MySQL/MariaDB UAT database; configure and test printers, scanner/camera, drawer and any terminal bridge in Arabic/RTL and English/LTR; and confirm the next scheduled backup completes and its restore evidence is retained.

## Coverage map

CodeGraph 1.6.0 was initialized for this worktree and reported 1,093 indexed files, 11,575 nodes, and 27,247 edges. It was used before the shared backup-wrapper edit to trace `RunPlatformBackup`, `SaveProductAction`, `RetailReturnAction`, and product cross-module callers.

Source inventory:

- 11 application modules: Assets, Catalog, Customer, Inventory, Party, Platform, Pricing, Purchasing, Quotation, Reporting, Retail.
- 20 route files; 199 source route declarations; 405 runtime routes, of which 392 are named.
- 9 navigation groups and 40 configured menu entries. The Parties group is deliberately disabled in `config/navigation.php`.
- 206 action classes, 150 models, 113 migrations, 236 Blade views, 9 Livewire files, and 91 source files containing form/action controls.
- 67 permission declarations were found in bounded source inventory. Runtime role and permission tables were inspected.
- Scheduler inventory covers platform backup, backup cleanup, hourly backup monitoring, and the queue worker’s cron entry.

The route-name scan found no unresolved application `route()` reference. The two token-like matches were request/route parameter names rather than missing named routes.

## Admin and normal-user visual coverage

The public-login pass from the prior continuation is preserved: Arabic/English 1440px desktop and Arabic 390px mobile, with correct document direction and no page-level overflow. It was not restarted.

The owner subsequently explicitly authorized exactly two temporary authenticated file sessions: existing administrator ID 1 and existing normal user ID 3. The helper projected identity/status/super-admin fields only. It did not inspect password hashes, change passwords, roles, grants or identities, or alter business records for access. No third user was authenticated. Both sessions were revoked immediately after the last authenticated pass, and the private cookie/token manifest was deleted. Revocation receipt: `.ai/evidence/full-system-audit-2026-10-03/session-revocation.txt`.

| Actor | Arabic desktop | English desktop | Arabic mobile | English mobile | Total |
|---|---:|---:|---:|---:|---:|
| Administrator | 145 | 145 | 7 | 7 | 304 |
| Normal user | 38 | 38 | 3 | 3 | 82 |
| Total | 183 | 183 | 10 | 10 | 386 |

Desktop width was 1440px and mobile width 390px. Final unique renders comprise 304 HTTP 200, 80 HTTP 403 and 2 HTTP 404 results. There were no HTTP 5xx, missing visible images or page-level horizontal overflows in the completed render ledger. The only initial application JavaScript exception was Translation Editor (AUD-007), repaired and confirmed after shipping. Representative desktop/mobile screenshots were inspected and preserved.

Administrator coverage spans administration/setup/settings/authorization/roles/audit/security/backup health, catalog/product/supplier/category/brand/import forms, purchasing, inventory/opening/count/transfer/adjustment forms, pricing, POS/sales/shifts/payments/returns/gift instruments, customers/wallets/loyalty, quotation, reporting/export centers, Party/assets/calendars and existing readiness/approval/offline/conflict screens. Query-tab variants and actual existing product/role IDs were included. Parameterized transaction detail/print routes with no records were not fabricated. Personal account/profile/appearance/security routes and credential-based password/passkey/2FA/recovery flows were excluded from this ERP render ledger; no challenge was bypassed and those paths are not represented as verified.

The normal user received HTTP 200 for POS, sales, sales invoices, payments and payment evidence; administrator/configuration/catalog/purchasing/inventory/report/customer/wallet/Party/shift routes returned server-side 403. This is evidence of denial under the current grants, not proof that those grants match intended cashier responsibilities.

Administrator stock-card requests for all four products returned 403 because the route requires an existing balance in a visible store; no balance records were available. Label preview returned 404 because no label-preview selection was staged. `/forbidden` intentionally returned 403. These are documented request prerequisites, not unresolved application faults.

Twenty-three safe-control/validation assertions passed: scoped navigation-search open/empty/close, contextual help open/Escape/focus return, native required-field rejection without submission, interactive quantity preview, disabled no-shift POS controls and uncovered Arabic mobile states. Initial timeouts on adjustment/Party/quotation forms were traced to AUD-009; only their failed renders resumed with the exact candidate asset guard. Other cascading closed-browser/logging/timing/selector failures were driver incidents, preserved separately from final unique outcomes. No full passing matrix was repeated.

Post-activation checks confirmed shipped login copy, Translation Editor edit modal and safely cancelled reset confirmation, canonical inventory filter round-trip, responsive adjustment form, localized dashboard/store copy, header separation and version. The interceptor initially rejected Livewire's read-only `$set` representation (empty call parameters with `updates.editorOpen=false`); its narrow allowance was corrected and only the failed guard assertion resumed. This was a verifier failure, not an application write failure.

## Forms and workflows reviewed

The preserved source review covers product/filter/supplier/customer forms, purchasing orders/invoices/receiving/returns, inventory/opening/transfers/counts/adjustments, pricing and approvals, POS/cart/checkout/shifts, sales/returns/gift instruments, quotations, reporting, Party/assets and platform authorization/settings/audit/upload flows.

Authenticated coverage exercised read-only forms, real existing product edit screens, safe modal/search/help controls, native validation and empty/disabled/denied states. Browser guards prevented saves, payments, approvals, resets and uploads. No production QA transactions or fabricated customers/suppliers/orders/stock movements were created. Normal export GETs produced their intended operational audit events; existing business records and grants were preserved.

Save/reload, maker/checker, financial posting/reversal, concurrent writers, persisted inventory/wallet/loyalty/gift-card reconciliation and transaction output acceptance remain unexecuted in this continuation. The required disposable XAMPP MySQL/MariaDB environment was unavailable; SQLite and live business QA writes were not substituted. Earlier named verification remains historical evidence only.

## Database and schema integrity

- Production migration status: every migration through `2026_10_02_000119_create_product_filter_values` reported `Ran`; no pending migration was found.
- Production database: MariaDB 10.11.19, MySQL connection, approximately 32 MB reported by Laravel.
- Read-only schema inspection covered products, roles, users, role pivots, foreign keys, indexes, and migration state.
- The bounded live integrity script found no foreign-key orphans in the checked relations and no duplicate product item codes, barcodes, supplier codes, or customer phones.
- Current counts from the bounded check were: companies 1, users 3, products 4, suppliers 2, customers 0, sales 0, purchase invoices 0, stock movements 0.
- Product schema contains the expected identity, pricing, tax, supplier, category, brand, status, and SEO fields with unique/indexed identity columns and foreign keys.
- The audit did not mutate business data and did not run SQLite.

## Permissions and security

Server-side authorization patterns were reviewed in route closures, action classes, scoped queries, POS context resolution, returns/gift actions, pricing flows, and user/role screens. The source uses explicit gates/permission checks and visible-to-scope queries in the high-risk retail and inventory actions.

One live configuration difference was recorded at the initial checkpoint:

- Source and canonical seed data use role code `cashier`.
- Production also contains a live role code `cashir` with 14 permissions, and the cashier account is assigned to that role.
- The canonical source `cashier` role currently has a different live grant set.
- The continuation checked `SaveRoleAction`, the Roles screen, and `RequestPermissionLookup`: local custom role codes are explicitly supported, and ordinary authorization resolves active permission pivots rather than comparing the code to `cashier`. Therefore the different spelling alone does not prove a defect. Canonical `cashier` has three POS permissions; custom `cashir` has fourteen, including open-price, export and reversal permissions. Its grants and assignment remain unchanged. The owner must confirm intended sensitive capabilities before any reconciliation; no narrower reassignment is assumed safe. Authenticated POS access and direct-route denials now match the existing grants; the user cannot open/view a shift, so operational cashier configuration still requires owner review.

No obvious secret exposure, debug mode, unauthenticated protected route, or CSRF/session misconfiguration was found in the inspected source/live responses. Raw credentials and cookie values were not retained in this report.

## Accounting and financial logic

Static review covered the retail sale calculator/action, purchase cost and tax snapshots, return/refund calculators, payment capture, shift close, inventory movement posting, price approval, and document immutability boundaries. The inspected actions use transactions and row locks at the financial/inventory boundaries, preserve snapshots, and enforce idempotency/replay checks in the reviewed paths.

There were no live sales, purchase invoices, returns, payments, or stock movements to reconcile in the bounded production data set. Accordingly, this audit does not claim a populated-ledger reconciliation or physical-device acceptance.

## Business-logic findings

The cross-module CodeGraph pass traced product saves into product forms, product lists, purchasing inline creation, imports, seeders, product filters, variants, images, supplier links, store assignments, and pricing. Retail sale and return paths were traced through scope, shift, drawer, customer, tax, payment, stock, audit, and idempotency dependencies.

No confirmed business-rule defect was found in the inspected source paths. Expected validation events in the production log included an invalid international barcode and an inactive-branch store selection; both were rejected by the intended validation rules.

## UI/UX findings

Completed Arabic RTL/English LTR desktop and representative mobile screens have no page-level overflow or missing visible images in the normalized ledger. Existing responsive product forms, purchase/inventory editors, empty/permission-denied panels and POS disabled context were inspected. Confirmed login, Translation Editor, inventory filter, observer-loop, administration copy, header-overlap and PDF defects are recorded below with focused evidence.

The measured Arabic header overlap was 110.797px. Giving the action column its required width and letting search use remaining space produced 13.594px separation, confirmed on the shipped UI release. Table-local scrolling is retained where wide data tables need it.

Twenty-five additional output requests completed: 13 administrator downloads (eight import-template workbooks, product XLSX, Arabic/English product PDFs, empty purchasing/inventory CSV) and 12 normal-user 403 denials. Nine downloaded XLSX archives/worksheet XML and two PDF containers passed integrity checks. PDF raster inspection then found AUD-012; its repaired bilingual A4 landscape rendering from the already-exported workbook was inspected with all columns inside margins, correctly shaped Arabic names in both locales, localized headings/status/type and unchanged numeric prices.

Normal-user empty-cart POS connectivity toggled offline and online successfully without enqueuing a sale/payment. Production offline continuity remains disabled by the existing implementation/configuration; this checks the connectivity indicator only. Populated offline replay/conflicts, transaction receipt/PDF formats, actual printer/scanner/drawer/terminal operation and human UAT were not passed by inference.

## Error and log findings

The active production log was reviewed for recurring exceptions and recent 4xx/5xx patterns.

- The recurring backup monitor failure was real: the newest archive was stale. A fresh encrypted full platform backup completed successfully, and `backup:monitor --isolated` immediately returned healthy.
- Historical bootstrap/cache permission errors reference superseded releases or probes run as a user without the runtime ACL. The active release works as the `rajeh` runtime user; these were not treated as current application defects.
- The `lastResponse` Livewire exception came from an audit probe reading a non-existent testing property, not from an application request.
- Missing `user_roles` table and wrong `roles.name` probe errors came from verifier scripts using an obsolete schema, not from application code.
- Barcode and inactive-branch exceptions were expected validation rejections.
- No failed queue jobs were present.

## Defects and repairs

### AUD-001 — Backup wrapper depended on the caller’s working directory

**Severity:** Medium operational robustness  
**Root cause:** `app/Console/Commands/RunPlatformBackup.php` delegated to the backup package without first normalizing the process working directory. Invoking the command from a directory inaccessible to the runtime user caused the database dump child process to inherit an invalid cwd and fail.

**Repair:** Added a guarded `chdir(base_path())` before the production encryption check and backup delegation. The command now fails with a localized operational error if the application base directory cannot be entered.

**Files changed:** `app/Console/Commands/RunPlatformBackup.php`.

**Verification:** Initial PHP syntax/full encrypted backup/monitor evidence is preserved. The outstanding runtime-user database-only invocation from the private audit worktree initially exposed AUD-005. After that generated-cache repair, the affected invocation passed: database dump, encrypted archive creation, archive integrity verification, and local-disk copy. No passing backup scan was repeated. The focused caller-cwd regression is closed.

### AUD-002 — Stale production backup monitor

**Severity:** High operational risk, repaired for the current recovery point  
**Root cause:** The scheduled daily archive was absent for the current date, leaving the hourly monitor to reject the stale previous archive. The exact scheduler miss was not proven from the retained logs.

**Repair/action:** Ran the established `platform:backup:run` command as the `rajeh` runtime user from the production release directory. The archive was encrypted, verified, copied to the configured local disk, and a subsequent `backup:monitor --isolated` passed.

**Follow-up:** Confirm the next scheduled daily run remains successful. The audit did not change cron/scheduler design.

### AUD-003 — Live role alias drift

**Severity:** Configuration approval review; no confirmed application defect from spelling
**Initial finding:** Production contains a custom `cashir` role and assignment differing from the canonical source `cashier` template.

**Continuation disposition:** Custom roles are supported and active grant pivots drive authorization. No automatic role reassignment, rename, deletion or grant change occurred. Intended open-price/export/reversal capabilities remain an owner approval review under `docs/04-roles-permissions.md`; the original observation is preserved without overstating it as a proven bypass.

### AUD-004 — Browser verification capability unavailable

**Severity:** Initial verification limitation; resolved
**Initial finding:** No browser-control executable/package was found during the first audit session.

**Final disposition:** Cached Chromium and the isolated browser client supported the remaining public/authenticated/mobile/output pass. The owner authorized exactly two sessions, both subsequently revoked. No authentication blocker remains; unavailable workflow/device acceptance is separately documented.

### AUD-005 — Activated release retained staging-directory paths in generated caches

**Severity:** High operational/runtime defect; repaired
**Root cause:** The preserved deployment optimized the staging directory and then renamed it. Cached logging, view, session, cache, storage and backup paths still referenced `.audit-e6d2d1e-stage`, which no longer existed. The focused backup regression failed on the nonexistent log directory. This was a generated-release configuration defect, not a business-data defect.

**Repair:** Cleared configuration and regenerated optimized runtime caches as `rajeh` at the final active release path, retaining the prior production/cache/mail settings and supplying the final compiled-view path. Reloaded the established `ea-php85-php-fpm.service`. No source archive, frozen artifact, database schema, business record, role, shared environment file, or other project's service was changed.

**Focused verification:** Six runtime-path assertions passed for logging, compiled views, sessions, file cache, private storage and backup temporary directory. Only the failed caller-cwd database-backup regression was resumed, and it passed. Runtime cache generation and this repair are recorded separately from the unchanged application commit.

### AUD-006 — Arabic login separator was untranslated and referred to email

**Severity:** Low UI defect; repaired and deployed
**Root cause:** The passkey component defaults to sentence-case `Or continue with email`, missing in Arabic; the visible uppercase key in Arabic did not match. Login actually requires a username.

**Repair:** Passed the login-specific `Or continue with username` separator and added its Arabic, Egyptian Arabic and English translations. The shared passkey component and authentication behavior are unchanged.

**Files:** `resources/views/pages/auth/login.blade.php`, `lang/ar.json`, `lang/ar-EG.json`, `lang/en.json`. Source repair commit `b04924d4c973580249aa999adab34078935cefb3` was included in the merged/deployed final UI batch.

**Focused verification:** JSON/translation resolution, changed Blade compilation/compiled PHP syntax and isolated unauthenticated rendering passed for `ar`, `ar-EG` and `en`. The first render verifier lacked the existing Vite manifest; it was corrected to use the existing release's public assets and explicitly exit nonzero on failure. Only that failed verifier was resumed. No asset rebuild or authentication attempt ran. The shipped Arabic username separator was confirmed after activation.

### AUD-007 — Translation Editor emitted literal Blade directives in action parameters

**Severity:** Medium UI failure; closed. Five desktop/mobile/modal action attributes contained literal `@js(...)`, triggering `Invalid or unexpected token` during Livewire initialization. Bound attributes now evaluate safely encoded `Js::from` arguments. Commit `1d2a363`. Focused verification rendered all five controls across three quote/markup fixtures (15 parsed expressions); live edit opened the modal and reset confirmation was cancelled without saving/resetting.

### AUD-008 — Inventory movement filter submitted localized labels

**Severity:** Medium UI/request-contract defect; closed. `InventoryMovementLabel::options()` preserves canonical keys, but the select iterated translated values and submitted them to a canonical-key allowlist, causing HTTP 422 and unknown-label warnings. The view now submits keys and displays localized values. Commit `9842658`. All 25 options and selection retention passed in three locales; shipped Arabic `movement_type=retail_sale` round-trip returned 200.

### AUD-009 — Product-line observer recursively rewrote unchanged totals

**Severity:** High workflow responsiveness defect; closed. The shared observer calls `boot`/`initializeEditor`/`updateTotal`, which unconditionally replaced total text and retriggered its own child-list observer. Adjustment, Party-create and quotation forms stalled. The updater now writes only changed formatted text, preserving numeric rules and raw totals. Commit `5278c0f`. Only the stalled renders resumed; unsaved quantity input and the shipped adjustment screen remained interactive. Direct observer/editor callers were reviewed; original CodeGraph analysis was not repeated.

### AUD-010 — Arabic administration/setup/purchasing copy remained raw English

**Severity:** Low presentation defect; closed. Dashboard recent activity now reuses the existing audit presentation helper, with labels for the observed user/email/product-card/filter events and authorization category. Added the exact warehouse setup description and reused the existing translated store placeholder. Commit `8f80ab4`; focused three-locale rendering and shipped copy checks passed. Stored audit records/events are unchanged.

### AUD-011 — Arabic desktop header controls overlapped

**Severity:** Low presentation defect; closed. The search column retained 448px while the action content extended into it. Changed the grid to give actions their required width and let search consume remaining space. Commit `8f80ab4`. Exact bounds changed from 110.797px overlap to 13.594px separation, confirmed after activation. Existing mobile layout overrides remain applicable.

### AUD-012 — Master-data PDF page bounds and bilingual Arabic rendering failed

**Severity:** Medium output defect; closed by the final PDF release. Rasterized live downloads exposed clipping and unshaped Arabic names in English exports despite valid PDF containers. The shared PDF formatter now shapes only Arabic-containing strings in either locale, preserving ASCII/numeric values and locale-specific column order. The template no longer overrides page margins on the HTML root, explicitly sizes its table font, uses supported wrapping and proportional header widths, and receives missing display translations. Commit `c48bbfc`.

Direct PDF callers in Catalog, Customers and master-data exports were reviewed. The focused renderer reused the already-exported product workbook without database access or renewed authentication. Initial candidate checks diagnosed the remaining root-margin, default-table-font and long-header issues; only those unresolved PDF renders resumed. Final Arabic/English A4 landscape raster inspection passed. The already-frozen UI artifact was preserved; a new version/artifact was created, with the same built UI assets and no extra Vite build. Supplier/customer transaction-specific content and physical printers still need their own populated UAT; this does not claim all document formats.

### AUD-013 — Product exports bypassed independent cost visibility

**Severity:** High sensitive-data disclosure boundary; closed. Catalog document export required `products_categories_brands.export` but included Unit cost unconditionally, whereas Product Card UI/actions independently enforce `products_categories_brands.cost_view`. An export-enabled/cost-disabled actor could therefore receive the hidden cost. This is a confirmed source-contract defect; no current-user populated-cost leak was reproduced, and no role/grant was changed to obtain one.

**D3 repair:** Commit `5440501` evaluates the existing cost-view gate per request and includes both header and value only when permitted. Base selling price, filters, row limits, product queries, other master types, format validation and export authorization remain unchanged. Both XLSX and PDF consume the same protected row/header shape. No business data or financial calculation changes.

**Focused verification:** Synthetic row passed through the actual changed header/mapper expressions with cost visibility enabled and disabled. Authorized output retains 11 columns and cost; hidden-cost output has 10 aligned columns, never accesses the cost property, omits the sentinel from XLSX XML and renders the PDF. Selling price remains unchanged. Existing export middleware and unsupported-format 404 guard were checked; the earlier actual normal-user 403 results remain evidence for full-route denial. No new user/session/grant or database fixture was created. Final3 release used a new immutable artifact; the PDF/UI snapshots and earlier release receipts were preserved.

## Focused verification completed

- Original CodeGraph initialization/impact results preserved. The continuation CLI later reported this root uninitialized; no reinitialization/index/sync was performed. New bounded JS/PDF impact was reviewed through direct source callers.
- PHP syntax: all application, route, config, migration, and bootstrap PHP files passed lint in the audit worktree.
- Blade cache compilation: passed.
- Runtime route list: 405 routes, 392 named routes, no unresolved route helper names.
- Public HTTP checks: `/up`, `/login`, and `/robots.txt` passed; protected routes redirect to login.
- Production migration status: all current migrations ran.
- Queue failed-job check: no failed jobs.
- Scheduler list: backup, cleanup, monitor jobs registered.
- Full encrypted backup: passed, archive verified.
- Backup monitor: passed.
- Remaining authenticated coverage: 386 unique renders, 23 safe-control assertions, shipped affected checks, 25 output requests and 42 final continuation assertions. Driver failures and their limited resumes are explicitly recorded.
- One Vite build for the UI batch; PDF and confidentiality releases reused those assets. Changed source/helper/template syntax, bilingual PDF rendering and cost visibility output contracts passed.
- No automated PHPUnit/Pest/Playwright/Cypress suite was created or run. Browser-control scripts were used within the explicit authorization.

## Release and live status

| Release | Application commit | Disposition |
|---|---|---|
| Initial baseline | `49f429420b576bb08f05ea757cf44aca9f8e9140` | Preserved |
| Original audit | `e6d2d1e76294a4b77d851d2cc249eaf2ed5512ff` | Already active at resume; generated caches repaired; frozen artifact preserved |
| Final UI batch | `af6874e53aff6fbaba523719925658638f7af676` | Merged via `a4d2604`, activated, affected checks passed; immutable archive preserved |
| PDF repair | `24f1c5a93e6085c1d9a680776bffae6e530288a0` | Merged via `66605f1`; activated and preserved |
| Final export confidentiality repair | `599a5084f7c1c2bb0aee5d6a67fc5df3d9dfd0be` | Merged via `10957d6`; final application release |

Final version: `0.1.22-audit-final3`. Final release: `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.22-audit-final3-24f1c5a`. Source tree: `51129f22539a3ceebc14e6b1d0171896f9131886`.

Final archive: `/tmp/rajeh-audit-cost-599a508.tar.gz`; SHA-256 `35eef98261e033575f4662a99a070b5d0b0ff03fce6654d5b17bfac3905f9286`. Manifest SHA-256 `a0c96bbd206480b75db8e05c8b8bc5cfeded11af82b8583f3dd3b8d570c3aab7`; 14,816 files authenticated before cache generation. Journal: `/var/lib/toyjoy/audit-final3-599a508`. Exact application rollback target is the preserved 24f1c5a PDF release.

The established workflow packaged source/vendor/public build, verified immutable hashes, used shared `.env`/storage/public-storage links, rejected nested storage, generated caches at the final candidate path, verified runtime paths/version/autoload, used maintenance and the established writer wait, switched the active link atomically, reloaded existing PHP-FPM and restored service. No Composer/Vite operation ran in production, migration/schema/business-data change was introduced, or other project was modified. Separate immutable PDF and export-confidentiality releases were necessary because each defect was confirmed after the preceding freeze; no prior artifact was patched. The single UI Vite build was reused throughout.

**Final-target compact smoke: PASS.** Active release/commit/tree/version match; maintenance is OFF; shared runtime links are correct and nested storage absent; PHP-FPM and cron are active; zero failed queue jobs; backup/cleanup/monitor schedules and scheduler/queue-worker cron entries are registered; HTTPS `/up` and `/login` return 200, unauthenticated `/admin` returns 302. Both audit sessions were already revoked. An earlier unreadable private helper attempt performed no actual smoke checks; the readable helper completed this final smoke once. Receipts are preserved in the evidence directory.

The exact 24f1c5a rollback target exists and recovery traps are retained; rollback was not deliberately exercised. No new backup/restore loop was required for this data/schema-neutral release; passing earlier encrypted backup/recovery evidence remains preserved. After session revocation, repaired PDF verification used isolated snapshot rendering with artifact/source parity rather than creating another authenticated session.

The final2 compact release-health receipt remains historical evidence in `final2-smoke.json`/`final2-http.json`; the later code change required a new target-specific final smoke. No passing browser matrix, financial workflow, graph/index, backup regression or source sweep was repeated. The cost fix was verified with isolated output contracts and preserved denial evidence, without renewed authentication.

Historical e6d2d1e archive SHA-256 remains `206fe0b11ea4e98b672ab03f649bdb861f0f5b018c74de1c732b9255253ef213`. UI af6874e archive SHA-256 is `f6c7e259f0f561abfd8be84e8d4abeba502afca77e99dceccfc0b8bd0231cbe9`. No passing original scan, full source lint, database integrity inventory, CodeGraph analysis, public-login baseline or successful backup regression was restarted. Documentation closure is a later governance commit and does not rebuild the authenticated application artifact.

## Final decision

The remaining authorized/executable audit is closed, and confirmed repairs are integrated and deployed. Production is operational in the executed release/browser/output checks. **Unconditional production trading/client handover readiness is NOT established.**

Before operational sign-off, the owner must confirm the custom cashier sensitive grants and intended shift access/opening setup; complete populated financial/inventory posting, reversal, concurrency and reconciliation in the mandated disposable XAMPP environment; complete required transaction print formats and actual printer/scanner/payment/drawer/offline continuity acceptance; and confirm the next scheduled backup run. These are recorded unexecuted acceptance/configuration obligations, not hidden passing checks or new authorization requests.

Detailed evidence is in `.ai/evidence/full-system-audit-2026-10-03/`. All previous findings and history remain preserved. Exactly two audit sessions were created and revoked; no passwords or password hashes were inspected, and no passwords, identities or grants were changed by the access helper, and no business fixture records were created.

## Final client acceptance closure — 2026-10-04

This is the focused continuation of the conditional-handover pass; the deep audit, CodeGraph work, browser coverage, and earlier checks were not repeated.

**Cashier/shift:** Production `cashier1` remains scoped to branch 2/store 2 and drawer `CDR-001`. The custom role `cashir` now has exactly the five permissions required by the approved shift workflow: `shifts_cash_movements.view`, `create`, `edit`, `submit`, and `print`. No `approve` grant was added. The isolated UAT cashier opened a shift, completed the approved sale flow, submitted a blind close, and a separate manager closed it; the stored shift ended `closed` with opening/closing cash `0.00`.

**Tax/document configuration:** The explicitly documented company configuration is now present in production: company 1 `ZERO` / `Zero Rated`, rate `0.00`, `zero_rated`, default, inclusive, active. All 16 documented company document sequences are active with their existing prefixes and six-digit padding (`SAL-`, `PO-`, `PIN-`, `SR-`, `ADJ-`, `TRF-`, `CNT-`, `SHC-`, `GR-`, `PB-`, `PI-`, `POO-`, `PFI-`, `PFR-`, `PPR-`, `QT-`). No value was invented; no undefined fiscal or legal setting remains in this scope.

**Accounting/inventory UAT:** A disposable native MariaDB database `toyjoy_client_acceptance_20261003` was seeded and removed after evidence capture. The flow produced an approved purchase order and invoice for 5 units at cost `10.0000` (`50.0000` subtotal/total, zero tax), a received transfer of 5 units, an approved sale of 1 unit at `30.00` with an approved `3.00` line discount, tax `0.00`, total/payable/paid `27.00`, CARD payment, and a completed original-tender return of `27.00`. Stock movements reconciled as purchase `+5`, transfer dispatch `-5` from warehouse, transfer receipt `+5` at outlet, sale `-1`, return `+1`; warehouse ended `0`, outlet ended `5`, and the stored sale/return totals matched the movement ledger. The return quantity defect found during this UAT was fixed and deployed (commit `0e253ab`). No genuine client record was changed.

**Printing/device:** The documented system-side default `DEFAULT-THERMAL` configuration is now present in production (`thermal`, `80mm`, `default_thermal`, browser connection, active/default). Focused source inspection confirms sales A4 and thermal receipt routes, shift A4/thermal routes, return/gift/label/purchasing print paths, `window.print()` handling, Arabic/LTR direction, LTR numeric amounts, identifiers, product lines, tax and totals. No physical printer, scanner, drawer, payment terminal, or offline continuity device was available; physical output and on-site device acceptance remain explicitly unverified.

**Scheduled backup:** The daily schedule remains `02:30`; the 2026-10-04 scheduled archive `2026-10-04-02-30-04.zip` exists, the existing `backup:monitor` reports the local backups healthy, and the archive’s database dump entry is present. The next scheduled execution is registered for the following daily run. No unnecessary backup was created for this check; the separate pre-deployment backup was required for the confirmed application fix.

**Fixes and production state:** Production permissions, tax/document settings, and the documented browser thermal printer configuration were corrected through audited, minimal changes. The return whole-unit quantity normalization fix was branched, committed, pushed, merged, deployed, and smoke-checked once. Current production release path is `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.22-audit-final4-0e253ab`, source fix commit `0e253ab` (the application’s displayed version remains `0.1.22-audit-final3`). Maintenance is off; `/up` and `/login` returned 200, unauthenticated `/admin` returned 302, and failed jobs were zero.

## Final readiness status

**CONDITIONAL HANDOVER — with explicit remaining blockers**

Remaining blocker: physical/on-site acceptance of the actual printer and connected scanner, cash drawer, payment terminal, receipt output, and offline/continuity behavior. The system-side print boundary is verified; physical success is not claimed. The client should also observe the assigned cashier’s first real shift against the corrected permissions and configured opening process on site.

## Final client-acceptance closure — 2026-10-04

This is the focused continuation for the three named pending UI/business issues. The deep audit, CodeGraph analysis, authenticated browser coverage, full test suites and completed UAT evidence were not repeated.

**Cost price persistence:** Already fixed in the current source and verified by bounded source inspection. On final/non-draft edit, the form reads the existing `average_cost` and makes the field nullable when that stored value is a valid nonnegative number. `SaveProductAction` reuses the locked stored cost when the submitted value is blank/omitted or the actor lacks cost access. A missing cost remains required. No code change was needed for this item.

**Approvals Inbox layout:** Fixed in `resources/views/platform/system/approval-inbox.blade.php`. Source/action cells now explicitly contain long identifiers with `min-w-0`, normal wrapping and `break-all` for technical action values; requester/scope cells use the same bounded wrapping and action controls remain separated. The existing RTL shell is preserved. The changed Blade view compiled successfully and the active release contains the exact reviewed file hash.

**Approval action UX:** The existing backend path already authorized decisions through `Gate::authorize('decide', ...)`, executed approval immediately, and validated only `decisionReason` for rejection. The normal inbox modal no longer exposes evidence upload, evidence revocation, file state, or extra evidence metadata. The component no longer mounts `WithFileUploads` or validates an evidence field. Audit recording remains in `DecideApprovalSource`; attachment backend routes and authorization were not changed. The active route map still exposes the approval inbox and attachment boundary.

**Focused verification and deployment:** `git diff --check`, bounded source assertions for all three items, and the changed Blade view cache passed. The fix was committed as `e642c18`, merged to `main` as `ff75916`, and deployed as the immutable release `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.22-audit-final5-ff75916`. The active symlink points to final5; the deployed approval view hash matches the reviewed source. Maintenance is off, `ea-php85-php-fpm` is active, and the existing backup monitor reports healthy local backups. Public DNS was unavailable from this execution environment, so no public HTTP success is claimed; the route/cache/source boundary checks passed. The pre-deployment backup was created by the established production process and is distinct from the previously verified scheduled archive.

**Other acceptance results carried forward:** Cashier/shift, documented tax and sequence configuration, isolated MariaDB accounting/inventory UAT, system-side print boundary, and scheduled-backup evidence remain as recorded in the preceding acceptance section. The scheduled `2026-10-04-02-30-04.zip` archive and healthy `backup:monitor` evidence remain verified; next daily execution remains configured at `02:30`. No genuine client record was changed.

## Final readiness status

**CONDITIONAL HANDOVER — with explicit remaining blockers**

Remaining blockers are physical/on-site confirmation of the actual printer and connected scanner, cash drawer, payment terminal, receipt output and offline/continuity behavior, plus client observation of the assigned cashier’s first real shift/opening process. No physical-device success is claimed.
