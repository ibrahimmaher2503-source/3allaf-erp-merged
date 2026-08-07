# Active Task: TSK-018 — Location Barcode Label Queues and Printing

## Scope for this continuation — 2026-08-07

- **Status:** Local/Dev Dummy-data queue slice implemented and browser-verified; production queue/print remains open.
- **Allowed:** Use explicitly authorized, clearly labeled Local Demo seeders to exercise stock-derived quantity, approved-price linkage, printer/template metadata, queue status, and append-only print-event display.
- **Forbidden:** Production label quantities, Production printer/device defaults, hardware acceptance, actual print/reprint posting, POS integration, and UAT/release sign-off.
- **Dependencies:** TSK-017 approved/effective price resolver and existing `stock_balances`/`printer_configurations` contracts are available for the Demo slice; final owner label/device/quantity values remain pending.
- **Policy boundary:** `docs/24-pricing-policy.md` and `docs/38-print-export-specification.md` remain derived/local references. Demo rows are explicitly non-Production and unpriced products remain blocked.
- **Verification:** Read-only contract/schema inspection, PHP lint/Pint/PHPStan, Blade cache, route checks, `git diff --check`, then authenticated Local Demo browser verification of the readiness/empty/disabled states. No PHPUnit/Pest or automated browser tests.

---

## Previous Task Record

# Selected Task: Initial Setup Dashboard — First Launch Configuration

## Current state

The Initial Setup Dashboard local/dev slice is **Implemented and browser-verified**. `/dashboard` now derives readiness from authoritative data and shows a setup panel while required owner inputs are missing. `/initial-setup` provides the full permission-gated wizard and links to the existing data-entry screens.

The current local state is intentionally incomplete: the authorized Local Demo company identity is populated with `TOY & JOY - Local Demo`, `EGP`, and `ج.م`; `supplier_return_reasons=0`; four supplier-return financial versions are recorded as Demo-only `Awaiting approval`; and no approved/effective supplier-return financial versions exist. This is expected Demo/Local evidence, not Production/UAT sign-off.

**UI completion:** The wizard now has an Arabic-first visual hierarchy, a clear next-step CTA, progress/legend summary, numbered status cards, explicit no-demo-data guidance, and a session-backed Arabic/English switch in the page header.

**Safety rules:** no defaults, reason rows, approval limits, production users, or approvals are created automatically. Pending or locked-only financial versions do not count; only effective, non-expired versions linked to an approved `ApprovalRecord` count.

**Access boundary:** `/initial-setup` requires `company_settings.edit`; a Local Demo Cashier received the existing Access Denied response. The dashboard remains usable and exposes the setup panel rather than applying a forced redirect loop.

**Related completed scope:** TSK-016 Supplier Returns remains complete for Local/Dev under DEC-052; Production/UAT owner inputs and release gates remain open.

**Forbidden:** claiming setup completion from demo data, treating optional printer review as production acceptance, bypassing financial approval, or publishing Demo Auth on the public HTTPS domain.


The canonical PO implementation is under `app/Modules/Purchasing` and uses the existing `AllocatePurchaseOrderNumberAction` with `DocumentSequence`; no parallel numbering path exists.

## Documentation synchronization — 2026-08-06

- `docs/35 §4` now includes the implemented local `Approved` PO state and explicitly separates the DEC-044 local approval/close slice from downstream receipt-driven `Partially Received` / `Received` transitions.
- `docs/41`–`docs/45` documented proposals are adopted as the DEC-050 local policy baseline; real production branches/stores/users/printers/cutover inputs remain separate prerequisites.
- `docs/38` A4 output requirements remain the production contract. The current PO print is a bilingual local/demo baseline; approver timestamp, reprint history, printer selection, and final print policy remain outside this local closure.
- Created `docs/templates/TSK-015-purchase-invoice-import-template.xlsx` as the approved local import artifact under DEC-050. It contains canonical headers, customization map, fictitious example, input validation, and no formulas/macros/production data.
- Started TSK-015 Slice B locally with `financial_setting_versions`, `FinancialSettingVersion`, and a read-only `/purchasing/invoices/settings` screen. The targeted migration ran successfully with zero rows; settings writes/default seeding/posting remain disabled. Full migration is blocked by pre-existing SQLite drift (`categories` already exists).
## A-01 line reference

`purchase_order_lines.id` is the stable primary key and each PO line also has a unique `(purchase_order_id, line_number)` constraint. The first TSK-015 Slice A migration references `purchase_order_lines.id` from `purchase_invoice_lines.purchase_order_line_id` with a restrictive foreign key, preserving partial supply and one-PO-to-many-invoices. Group A ledger schema is authorized as a reversible local development slice; posting actions remain out of scope until policy and browser evidence are complete.

## Ordered TSK-014 slices

1. Submit + Approve transitions, with approved records immutable and self-approval rejected.
2. Cancel + Close with required reason where applicable and audit.
3. `Partially Received` / `Received` definitions only; no receipt or stock-posting logic. TSK-015 will drive those states later.
4. PO print at `resources/views/purchasing/print.blade.php`.
5. Manual verification: branch/store scope, self-approval denial, approved-document edit denial, RTL/LTR, and mobile.

## Implemented & Verified in this continuation

- Authenticated manual browser verification passed for approved local/demo scope on local/demo server (`DEMO_AUTH=true`): list/detail rendering, draft creation with 3 x 12.50 = 37.50, submit transition, approve transition by a separate reviewer, self-approval backend denial, approved edit denial, cancellation reason validation, cancellation audit logging, print A4 rendering, reviewer branch scope and print permission, and no-access 403 direct denial.
- Arabic RTL and English LTR visual checks passed at the available 1280px viewport with no observed console errors or element overlap.
- Confirmed zero stock, invoice, or cost posting side effects on PO approval.
- Added `ApprovePurchaseOrderAction` with `purchase_orders.approve` authorization, submitted-only transition, optimistic version check, self-approval denial, immutable post-approval editing boundary, and atomic audit.
- Added approval fields/index to `purchase_orders` through `2026_08_06_000023_add_purchase_order_approval_fields.php`.
- Added `approved` presentation/state and allowed close only from `approved`, `partially_received`, or `received`.
- Added approval permission definition and local accountant-reviewer/system-administrator grants in the canonical local authorization seeder.
- Added UI approve action and status filter; existing print route/view and allocator are reused.
- Added branch/store visibility enforcement to PO list/detail/store selector and hid requester self-approval in the UI; backend self-approval guard remains authoritative.
- Added explicit local-only `database/seeders/DemoSeeder.php` entrypoint for deterministic Demo identities, authorization, master data, and PO walkthrough records when `DEMO_AUTH=true`.
- Definition-only `Partially Received` / `Received` remain TSK-015. True 390x844 mobile evidence remains pending because CUA Firefox capture is 0x0 and the available Browser Use session has no viewport-resize capability. Production, UAT, and Phase gates remain open.
- Updated `.ai/CURRENT_MILESTONE.md`, `.ai/DECISIONS.md` with DEC-044/DEC-045, `.ai/HANDOFF.md`, `.ai/PROGRESS.md`, `.ai/TEST_RESULTS.md`, and `TASKS.md`.

## Verification

- Locale parity: 1035/1035 keys matched between `lang/ar.json` and `lang/en.json`.
- PHPStan: 0 errors detected.
- Pint & PHP lint: passed with 0 errors across actions, models, migrations, and views.
- Blade cache: `php artisan view:cache` passed cleanly.
- Vite build: passed (`npm run build`), emitting only the optional `fontaine` font optimization warning.
- Git whitespace check: `git diff --check` passed cleanly.
- Automated tests: No PHPUnit/Pest or automated browser tests claimed per owner directive.
- Guest HTTP smoke for `/purchasing/orders` and print returned `302 /login` with request IDs.
- Authenticated manual browser verification for TSK-014 completed for approved local/demo scope: list/detail, draft creation (3 x 12.50 = 37.50), submit, separate reviewer approve, self-approval backend denial, approved edit denial, cancellation reason validation, cancellation audit logging, print A4, reviewer branch scope and print permission, no-access 403, and Arabic RTL / English LTR visual checks at 1280px viewport with no console errors or overlap.
- Definition-only `Partially Received` / `Received` states remain TSK-015; true 390x844 mobile evidence remains pending because CUA Firefox capture is 0x0 and Browser Use session has no viewport resize.
- Existing local `database/database.sqlite` was not reset; its migration attempt is blocked by an older pre-existing `categories` table collision at migration `2026_08_04_000017`.

## Current continuation — TSK-015 full local/dev implementation

**Implemented local/dev scope:** purchase invoice schema/lifecycle, manual draft CRUD, BCMath calculation (tax/discount/rounding inputs), staged private `.xlsx`/`.csv` import with row-level validation and formula-like cell rejection, duplicate/idempotency checks, submit/reject/cancel/approve/reverse actions, strict PO matching/no over-receipt, automatic receipt on approval under DEC-043 local default, stock movement/balance/WAC update, reversal idempotency, audit events, print, CSV export, settings foundation, and permission boundaries.

**Local/dev defaults explicitly adopted for this engineering slice:** approval receives stock automatically; over-receipt is blocked; invoice numbering uses `PINV-` only when approved; reversal requires sufficient on-hand; import creates Draft invoices only and never approves them; no sale-price mutation.

**Production/UAT boundary:** DEC-050 answers the 83 documented policy keys for the local baseline. Real master data, named approvers, printer/device assignments, cutover timestamp, UAT, and release gates remain open. Full migration remains affected by pre-existing SQLite drift (`categories` already exists); targeted migrations were applied without changing historical migrations.

**Verification plan:** PHP lint, Pint, PHPStan, Blade cache, route discovery, `git diff --check`, invalid-import smoke, number-sequence smoke, guest browser redirects, and authenticated browser verification when a safe local session is available. No PHPUnit/Pest or automated browser tests are claimed.

**Non-claims:** this local implementation does not close production/UAT gates or authorize real financial/stock data; DemoSeeder remains local-only.
## Current continuation — observability, identity, and delivery controls

- Local/staging query budget, slow-query channel, dev Debugbar, and local non-production lazy-loading guard are being added under DEC-046.
- `scripts/ai/run-gemini.sh` must prove `pwd` and `git rev-parse --show-toplevel`, inject `PROJECT_NAME`, and pass the verified root to AGY; repository identity is never inferred from a port.
- A single tracked `.githooks/pre-commit` owns Pint, PHPStan, locale-key parity, and staged whitespace checks. Only one writer may modify `.ai/` at a time; the paused watcher stays paused during this interactive slice.
- Realistic volume work uses only the disposable ignored performance database generator; it must never touch shared Demo data.


The TSK-015 local/dev actions, import, ledger/WAC, receiving/matching, and lifecycle boundaries are implemented in reversible local slices. Do not claim production financial posting, production master-data readiness, UAT, or owner approval until `.ai/TSK-015_OWNER_INPUTS.md` is resolved and release gates are explicitly recorded.
