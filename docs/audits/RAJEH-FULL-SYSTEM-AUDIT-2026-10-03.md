# Rajeh ERP Full-System Audit and Client-Readiness Report

**Audit date:** 2026-10-03  
**Audit window:** 2026-10-03 UTC  
**Source baseline:** `49f429420b576bb08f05ea757cf44aca9f8e9140`  
**Audit branch:** `audit/full-system-client-readiness`  
**Production baseline at audit start:** `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.22-hotfix51-49f429420b57`  
**Production target:** `49f429420b576bb08f05ea757cf44aca9f8e9140`  
**Audit class:** I4, owner-authorized release and operational verification

## Readiness summary

| Severity | Remaining | Disposition |
|---|---:|---|
| Critical | 0 confirmed | No confirmed critical application defect |
| High | 1 unresolved | Production role drift: live `cashir` role is assigned to the cashier account while source defines `cashier`; owner must approve the intended production role before reassignment |
| Medium | 1 follow-up | Verify the next scheduled backup run; a fresh encrypted backup and monitor check passed during this audit |
| Low | 1 limitation | Authenticated browser visual matrix could not run because no browser-control executable or package is available in this environment |

**Client handover readiness:** **Conditional.** The active application is healthy at the HTTP, migration, queue, scheduler, cache, database-integrity, and fresh-backup checks completed here. Handover remains conditional until the owner resolves the live role alias and a headed Arabic/English Admin/User browser pass is completed on an environment with browser control.

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

A headed browser pass, authenticated Admin pass, authenticated normal-user pass, screenshots, and interactive control exercise were **not testable** in this execution because the environment has no Chromium/Chrome executable and no installed Playwright/Puppeteer package or browser-control MCP tool. No credentials were printed or created, and no QA records were inserted into production. Historical browser evidence in prior project sessions was treated as historical evidence only and not reused as current verification.

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

One live configuration mismatch remains:

- Source and canonical seed data use role code `cashier`.
- Production also contains a live role code `cashir` with 14 permissions, and the cashier account is assigned to that role.
- The canonical source `cashier` role currently has a different live grant set.
- This is authorization drift, but automatic reassignment or deletion would change owner-approved production access. It therefore remains an owner decision. The exact action is to confirm the intended role alias and permission grant, then reconcile the role assignment in a controlled, audited change.

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

**Verification:** PHP syntax passed. A fresh full encrypted production backup and monitor check passed using the runtime account. A post-release database-only invocation from an unrelated cwd is required to close the focused regression check.

### AUD-002 — Stale production backup monitor

**Severity:** High operational risk, repaired for the current recovery point  
**Root cause:** The scheduled daily archive was absent for the current date, leaving the hourly monitor to reject the stale previous archive. The exact scheduler miss was not proven from the retained logs.

**Repair/action:** Ran the established `platform:backup:run` command as the `rajeh` runtime user from the production release directory. The archive was encrypted, verified, copied to the configured local disk, and a subsequent `backup:monitor --isolated` passed.

**Follow-up:** Confirm the next scheduled daily run remains successful. The audit did not change cron/scheduler design.

### AUD-003 — Live role alias drift

**Severity:** High authorization/configuration risk, unresolved  
**Root cause:** Production contains a manually or historically created `cashir` role and assignment that are not represented by the current source role catalog.

**Repair status:** Deliberately not auto-mutated. Role aliases and production grants require owner approval under the project authorization policy.

### AUD-004 — Browser verification capability unavailable

**Severity:** Low verification limitation, unresolved  
**Root cause:** No browser-control executable, Playwright/Puppeteer installation, or browser MCP tool was available.

**Repair status:** No code change. Exact limitation is recorded so the missing Admin/User and RTL/LTR visual evidence is not misrepresented as a pass.

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

**Audit application commit:** to be filled after commit.  
**Final production commit:** to be filled after atomic activation.  
**Final active release:** to be filled after deployment.  
**Maintenance mode:** expected OFF after deployment smoke.  
**Queue/PHP-FPM/cron:** to be recorded in the final post-deploy smoke.

The release procedure follows the existing Rajeh immutable-release/runbook pattern: authenticated source package, shared environment/storage links, runtime-user cache generation, migration status proof, backup/recovery evidence, atomic active-link switch, PHP-FPM reload, queue/scheduler continuity, health routes, and post-deploy smoke. No migration is included in this audit repair.

## Final decision

There are no confirmed critical application defects from the executed checks. Current readiness is conditional because browser-based Admin/User acceptance and the live role alias require owner-side closure. The fresh backup and monitor state are healthy at the end of the audit window.

