# Selected Task: TSK-014 Purchase Orders

## Current state

TSK-014 is **In Progress under DEC-044**. TSK-015 Slice A and Performance Group A (TSK-P01–P03) are authorized and implemented as reversible local schema/diagnostic work; invoice posting/import, receipt mutation, WAC/cost logic, and production acceptance remain gated.

The canonical PO implementation is under `app/Modules/Purchasing` and uses the existing `AllocatePurchaseOrderNumberAction` with `DocumentSequence`; no parallel numbering path exists.

## A-01 line reference

`purchase_order_lines.id` is the stable primary key and each PO line also has a unique `(purchase_order_id, line_number)` constraint. The first TSK-015 Slice A migration now references `purchase_order_lines.id` from `purchase_invoice_lines.purchase_order_line_id` with a restrictive foreign key, preserving partial supply and one-PO-to-many-invoices. Group A ledger schema is now authorized as a reversible local development slice; posting actions remain out of scope until policy and browser evidence are complete.

## Ordered TSK-014 slices

1. Submit + Approve transitions, with approved records immutable and self-approval rejected.
2. Cancel + Close with required reason where applicable and audit.
3. `Partially Received` / `Received` definitions only; no receipt or stock-posting logic. TSK-015 will drive those states later.
4. PO print at `resources/views/purchasing/print.blade.php`.
5. Manual verification: branch/store scope, self-approval denial, approved-document edit denial, RTL/LTR, and mobile.

## Implemented in this continuation

- Added `ApprovePurchaseOrderAction` with `purchase_orders.approve` authorization, submitted-only transition, optimistic version check, self-approval denial, immutable post-approval editing boundary, and atomic audit.
- Added approval fields/index to `purchase_orders` through `2026_08_06_000023_add_purchase_order_approval_fields.php`.
- Added `approved` presentation/state and allowed close only from `approved`, `partially_received`, or `received`.
- Added approval permission definition and local accountant-reviewer/system-administrator grants in the canonical local authorization seeder.
- Added UI approve action and status filter; existing print route/view and allocator are reused.
- Added branch/store visibility enforcement to PO list/detail/store selector and hid requester self-approval in the UI; backend self-approval guard remains authoritative.
- Added explicit local-only `database/seeders/DemoSeeder.php` entrypoint for deterministic Demo identities, authorization, master data, and PO walkthrough records when `DEMO_AUTH=true`.
- Slice-5 manual verification now covers Demo Auth, scope denial/visibility, self-approval UI, approved immutability, print, and RTL/LTR. Mobile viewport verification remains pending; TSK-014 stays In Progress.
- Updated `.ai/CURRENT_MILESTONE.md`, `.ai/DECISIONS.md` with DEC-044/DEC-045, and `TASKS.md`.

## Verification

- PHP lint passed for changed PO actions/models/migration/view.
- Clean temporary SQLite migration through `2026_08_06_000023` passed.
- PO route and print route registered.
- Blade cache and Vite build passed; only existing optional `fontaine` warning appeared.
- `git diff --check` passed.
- Guest HTTP smoke for `/purchasing/orders` and print returned `302 /login` with request IDs.
- Authenticated manual browser verification is still pending because no authenticated browser session is available and no password will be entered.
- Existing local `database/database.sqlite` was not reset; its migration attempt is blocked by an older pre-existing `categories` table collision at migration `2026_08_04_000017`.

## Do not do

Do not create invoice posting/import actions, stock mutation workflows, WAC calculations, receipt mutation, tax/payment/discount defaults, or production approval thresholds. The reversible Slice A schema and `inventory:rebuild-balances` diagnostic command are explicitly in scope; do not claim them as financial posting or production readiness. Do not commit or push until final review.
