# Selected Task: TSK-014 Purchase Orders

## Current state

TSK-014 local implementation and authenticated manual browser verification are **Completed for approved local/demo scope**. Definition-only `Partially Received` and `Received` states remain TSK-015. True 390x844 mobile evidence remains pending because CUA Firefox capture is 0x0 and the available Browser Use session has no viewport-resize capability. Production, UAT, and Phase gates remain open.

The canonical PO implementation is under `app/Modules/Purchasing` and uses the existing `AllocatePurchaseOrderNumberAction` with `DocumentSequence`; no parallel numbering path exists.

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

## Current continuation — TSK-015 readiness boundary

**Allowed scope for this slice:** implement only a server-gated, read-only TSK-015 readiness screen. It may show open decision groups, blocker references, lifecycle/reference cards, disabled workflow controls, explicit `TBD`/`Owner Approval Required` badges, and an empty state. It must not persist invoice/line data or expose any financial/inventory mutation.

**Forbidden scope:** invoice posting/import, receipt mutation, stock movements/balances, WAC/cost calculations, approval records, seeded invoice/business data, file upload/parser/storage, tax/discount/currency/numbering defaults, production role grants, or production readiness claims.

**Dependencies and owner inputs:** TSK-014 local/demo scope is completed; TSK-015 receipt/invoice/posting remains gated; `.ai/TSK-015_OWNER_INPUTS.md` remains Awaiting owner answers; BLK-006, BLK-008, BLK-010, and BLK-012 remain open or production-gated. Do not convert engineering defaults into owner approvals.

**Verification plan:** inspect the real route and gate, run PHP lint, route listing, Blade cache, Vite build, locale parity, `git diff --check`, guest redirect smoke with request ID, and manual browser review of the read-only boundary in Arabic RTL and English LTR when a safe local session is available. Authenticated evidence must remain separate from guest redirect evidence.

**Non-claims:** this slice is readiness preparation only; it does not close TSK-015, DM 2.2, UAT, or any production/financial gate.

## Current continuation — observability, identity, and delivery controls

- Local/staging query budget, slow-query channel, dev Debugbar, and local non-production lazy-loading guard are being added under DEC-046.
- `scripts/ai/run-gemini.sh` must prove `pwd` and `git rev-parse --show-toplevel`, inject `PROJECT_NAME`, and pass the verified root to AGY; repository identity is never inferred from a port.
- A single tracked `.githooks/pre-commit` owns Pint, PHPStan, locale-key parity, and staged whitespace checks. Only one writer may modify `.ai/` at a time; the paused watcher stays paused during this interactive slice.
- Realistic volume work uses only the disposable ignored performance database generator; it must never touch shared Demo data.


Do not create invoice posting/import actions, stock mutation workflows, WAC calculations, receipt mutation, tax/payment/discount defaults, or production approval thresholds. The reversible Slice A schema and `inventory:rebuild-balances` diagnostic command are explicitly in scope; do not claim them as financial posting or production readiness. Do not commit or push until final review.
