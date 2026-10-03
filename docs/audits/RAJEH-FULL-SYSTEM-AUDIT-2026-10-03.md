# Rajeh ERP Full-System Audit and Client-Readiness Report

**Audit date:** 2026-10-03  
**Audit window:** 2026-10-03 UTC  
**Source baseline:** `49f429420b576bb08f05ea757cf44aca9f8e9140`  
**Audit branch:** `audit/full-system-client-readiness`  
**Production baseline at audit start:** `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.22-hotfix51-49f429420b57`  
**Production target:** `49f429420b576bb08f05ea757cf44aca9f8e9140`  
**Audit class:** I4, owner-authorized release and operational verification

**Continuation status:** Incomplete — authenticated Admin/User browser coverage awaits explicit authorization for two temporary audit sessions. Completed scans and historical evidence below are preserved; they were not restarted.

## Readiness summary

| Severity | Remaining | Disposition |
|---|---:|---|
| Critical | 0 confirmed | No confirmed critical application defect in executed coverage; outstanding coverage is not a pass |
| High | 1 approval review | Confirm the intended existing custom cashier grants; the role spelling alone is not an application defect |
| Medium | 1 follow-up | Verify the next scheduled backup run; a fresh encrypted backup and monitor check passed during this audit |
| Low | 1 pending release | Arabic login separator localization/username copy corrected and verified in source; not yet deployed |
| Verification blocker | 1 | Authenticated Admin/User browser coverage awaits temporary-session approval |

**Client handover readiness:** **Not approved by this audit.** Active release health and the repaired backup command pass the executed checks. Full Admin/User visual, interactive workflow, and acceptance coverage remains incomplete. No milestone, phase gate, or human UAT sign-off is closed.

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

The source and HTTP surface was inspected for all navigation groups, route families, Livewire forms, modal/action references, validation and empty-state paths, and locale direction handling.

Unauthenticated live checks returned the expected login redirect for Admin and user-facing protected paths, including:

`/admin`, `/dashboard`, `/catalog/products`, `/purchasing/orders`, `/pos`, `/inventory`, `/customers`, `/reports`, and `/admin/system/health`.

Public live checks passed:

- `/up` returned HTTP 200.
- `/login` returned HTTP 200 with CSRF markup, secure session attributes, Arabic locale controls, and English LTR document direction.
- `/robots.txt` returned HTTP 200.

At the first checkpoint, browser tooling had not been located. The continuation found cached Chromium 151.0.7922.34 and installed an isolated Playwright Core client in `/tmp/rajeh-audit-continuation`, leaving application dependencies unchanged. Current login screenshots passed Arabic RTL and English LTR desktop rendering at 1440px and Arabic mobile rendering at 390px, with no page errors or page-level horizontal overflow. Screenshots exposed AUD-006 below.

Authenticated Admin/User pages have **not been verified** in this continuation. Automatic approval review rejected a proposed helper that would create sessions for all active users and read password hashes. That helper did not execute. Explicit approval was requested for a safer plan: exactly two temporary sessions, one existing administrator and one existing normal user, without password-hash access, grant changes, or business-record changes, followed by session cleanup. Approval remains pending. No credentials or session cookies were created by that helper, printed, or retained. Historical browser evidence remains historical only.

| Remaining coverage | Actual result |
|---|---|
| Public login desktop Arabic/English and mobile Arabic | Browser screenshots inspected; layout/direction passed; localization defect fixed in source |
| Authenticated administrator navigation/screens/forms | Blocked pending temporary-session authorization |
| Authenticated normal-user POS and direct-route denials | Blocked pending temporary-session authorization |
| Create/edit/save/reload, maker/checker, posting/reversal, concurrency and ledger reconciliation | Current continuation did not execute these workflows; prior static findings remain unchanged |
| Print/PDF, RTL/LTR output, offline/PWA, hardware | Not verified by the public-login browser pass |

## Forms and workflows reviewed

Static form and action coverage included product cards and filter values, supplier/customer masters, purchasing orders/invoices/receiving/returns, inventory balances/transfers/counts/adjustments, pricing lists and approvals, POS context/cart/checkout/shift flows, sales/returns/gift instruments, quotations, reports/exports, assets/party flows, settings, roles/scopes, audit views, uploads, and print routes.

The current environment did not permit safe authenticated form submission/reload/edit/browser-state verification. No production QA records were created, so no cleanup was required.

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
- The continuation checked `SaveRoleAction`, the Roles screen, and `RequestPermissionLookup`: local custom role codes are explicitly supported, and ordinary authorization resolves active permission pivots rather than comparing the code to `cashier`. Therefore the different spelling alone does not prove a defect. Canonical `cashier` has three POS permissions; custom `cashir` has fourteen, including open-price, export and reversal permissions. Its grants and assignment remain unchanged. The owner must confirm intended sensitive capabilities before any reconciliation; no narrower reassignment is assumed safe. Authenticated behavior remains pending coverage.

No obvious secret exposure, debug mode, unauthenticated protected route, or CSRF/session misconfiguration was found in the inspected source/live responses. Raw credentials and cookie values were not retained in this report.

## Accounting and financial logic

Static review covered the retail sale calculator/action, purchase cost and tax snapshots, return/refund calculators, payment capture, shift close, inventory movement posting, price approval, and document immutability boundaries. The inspected actions use transactions and row locks at the financial/inventory boundaries, preserve snapshots, and enforce idempotency/replay checks in the reviewed paths.

There were no live sales, purchase invoices, returns, payments, or stock movements to reconcile in the bounded production data set. Accordingly, this audit does not claim a populated-ledger reconciliation or physical-device acceptance.

## Business-logic findings

The cross-module CodeGraph pass traced product saves into product forms, product lists, purchasing inline creation, imports, seeders, product filters, variants, images, supplier links, store assignments, and pricing. Retail sale and return paths were traced through scope, shift, drawer, customer, tax, payment, stock, audit, and idempotency dependencies.

No confirmed business-rule defect was found in the inspected source paths. Expected validation events in the production log included an invalid international barcode and an inactive-branch store selection; both were rejected by the intended validation rules.

## UI/UX findings

The source preserves bilingual labels, RTL/LTR direction handling, shared shell/navigation patterns, loading/empty/error components, confirmation controls, print templates, and responsive utility classes across the inspected modules. A full visual rendering judgment is unavailable without a browser executable. The report does not claim pixel-level, device-level, or print-device acceptance.

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

**Severity:** Verification limitation; capability gap resolved, authentication approval outstanding
**Initial finding:** No browser-control executable/package was found during the first audit session.

**Continuation disposition:** Cached Chromium was found and an isolated client installed; public-login screenshots were captured and inspected. The wider authenticated pass is blocked by the explicit approval request described above, rather than a missing executable. No unexecuted coverage is counted as passed.

### AUD-005 — Activated release retained staging-directory paths in generated caches

**Severity:** High operational/runtime defect; repaired
**Root cause:** The preserved deployment optimized the staging directory and then renamed it. Cached logging, view, session, cache, storage and backup paths still referenced `.audit-e6d2d1e-stage`, which no longer existed. The focused backup regression failed on the nonexistent log directory. This was a generated-release configuration defect, not a business-data defect.

**Repair:** Cleared configuration and regenerated optimized runtime caches as `rajeh` at the final active release path, retaining the prior production/cache/mail settings and supplying the final compiled-view path. Reloaded the established `ea-php85-php-fpm.service`. No source archive, frozen artifact, database schema, business record, role, shared environment file, or other project's service was changed.

**Focused verification:** Six runtime-path assertions passed for logging, compiled views, sessions, file cache, private storage and backup temporary directory. Only the failed caller-cwd database-backup regression was resumed, and it passed. Runtime cache generation and this repair are recorded separately from the unchanged application commit.

### AUD-006 — Arabic login separator was untranslated and referred to email

**Severity:** Low UI defect; source repair verified, release pending
**Root cause:** The passkey component defaults to sentence-case `Or continue with email`, missing in Arabic; the visible uppercase key in Arabic did not match. Login actually requires a username.

**Repair:** Passed the login-specific `Or continue with username` separator and added its Arabic, Egyptian Arabic and English translations. The shared passkey component and authentication behavior are unchanged.

**Files:** `resources/views/pages/auth/login.blade.php`, `lang/ar.json`, `lang/ar-EG.json`, `lang/en.json`. Source repair commit `b04924d4c973580249aa999adab34078935cefb3` is pushed only on the preserved audit branch; not merged/deployed.

**Focused verification:** JSON/translation resolution, changed Blade compilation/compiled PHP syntax and isolated unauthenticated rendering passed for `ar`, `ar-EG` and `en`. The first render verifier lacked the existing Vite manifest; it was corrected to use the existing release's public assets and explicitly exit nonzero on failure. Only that failed verifier was resumed. No asset rebuild or authentication attempt ran. The repair is not yet in production.

## Focused verification completed

- CodeGraph worktree index: current and up to date.
- PHP syntax: all application, route, config, migration, and bootstrap PHP files passed lint in the audit worktree.
- Blade cache compilation: passed.
- Runtime route list: 405 routes, 392 named routes, no unresolved route helper names.
- Public HTTP checks: `/up`, `/login`, and `/robots.txt` passed; protected routes redirect to login.
- Production migration status: all current migrations ran.
- Queue failed-job check: no failed jobs.
- Scheduler list: backup, cleanup, monitor jobs registered.
- Full encrypted backup: passed, archive verified.
- Backup monitor: passed.
- No automated PHPUnit/Pest/Playwright/Cypress suite was run, per the active project testing directive and the explicit one-pass audit budget.

## Release and live status

**Preserved audit application commit:** `e6d2d1e76294a4b77d851d2cc249eaf2ed5512ff`.

**Observed active application commit:** `e6d2d1e76294a4b77d851d2cc249eaf2ed5512ff`.

**Observed active release:** `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.22-audit-e6d2d1e`.

**Generated-cache repair:** completed after activation; source commit/artifact unchanged.

**Maintenance:** OFF in the continuation's compact deployment smoke.
**Queue/PHP-FPM/cron:** no failed queue jobs; backup/cleanup/monitor schedules registered; PHP-FPM and cron services active. The managed per-minute Rajeh scheduler and bounded queue-worker cron entries remain in place. No daemonized Rajeh queue worker is assumed necessary for this cron-driven model.

The continuation resumed the saved deployment after observing the already-active target; it did not redeploy the same artifact. Deployment journal: `/var/lib/toyjoy/audit-deployment-e6d2d1e`. Existing archive SHA-256: `206fe0b11ea4e98b672ab03f649bdb861f0f5b018c74de1c732b9255253ef213`. This is a preserved release identity, not a repeated complete manifest verification. The missing compact smoke was completed once after the cache repair; it is not final acceptance of the unfinished full audit. AUD-006 remains in source pending final batch integration/release.

The release procedure follows the existing Rajeh immutable-release/runbook pattern: authenticated source package, shared environment/storage links, runtime-user cache generation, migration status proof, backup/recovery evidence, atomic active-link switch, PHP-FPM reload, queue/scheduler continuity, health routes, and post-deploy smoke. No migration is included in this audit repair.

## Final decision

The preserved release is operational after AUD-005 and the focused backup regression passes. The full audit is **incomplete**, and unconditional production readiness is **not established**. Outstanding authenticated Admin/User screens, interactive workflows, grants review, output/device/offline acceptance, and the source-only login repair must remain explicit. Complete the approved remaining coverage and release batch before one final full-audit smoke and final handover decision. Do not rerun the completed inventory, lint/Blade sweep, CodeGraph analysis, initial integrity checks, public-login baseline screenshots, runtime-path assertions, or successful backup regression.
