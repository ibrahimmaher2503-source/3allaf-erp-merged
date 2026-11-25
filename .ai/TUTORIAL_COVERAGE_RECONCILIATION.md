# Tutorial Coverage Reconciliation — 2026-09-20

Scope: Local/Dev contextual guidance for real named application screens. Authentication, error, print, download, attachment, JSON/helper, and redirect-only routes remain outside the persistent assistant unless a normal application screen owns the workflow.

Implementation:

- `ApplicationTutorialFactory` supplies bilingual guides for previously uncovered administration, catalog, customer, pricing, purchasing, inventory, POS/offline, reporting, party, quotation, return, and gift-instrument screens.
- `TutorialRegistry` now resolves 101 guide definitions across 147 route references with no duplicate route ownership.
- Roles and permissions use their actual `admin.roles` and `admin.role-permissions` routes; the legacy POS reference guide no longer shadows the normal `pos` route.
- Inventory, purchasing orders, suppliers, sales invoices, customer wallets, and suspended-sales guides include their real route variants where one rendered screen owns them.
- `capability-boundary.blade.php` renders the documented readiness boundary, summary, first-card, cards, and empty-state hooks.
- Readiness permissions follow the protecting route (`dashboard_reports.view`, `company_settings.view`, `audit_logs.view`, `party_bookings_invoices.view`, `returns_exchanges_gift_instruments.view`, or `pos_sales.view`).
- The client-facing guide DTO no longer exposes permission identifiers, alternate route lists, or requirement/acceptance IDs. The full guide page retains internal traceability server-side for its documentation view.

Static reconciliation:

- 142 page-like application route candidates were inventoried.
- 142 are assigned to a tutorial route after the update; `sales.invoices` is covered by the sales-register guide after the final correction.
- Route references were checked against the live Laravel route list; no nonexistent tutorial route reference remains.
- PHP syntax checks passed for the changed factories, DTO, and controller.
- Blade cache compilation passed.
- `git diff --check` passed.
- Automated tests, browser verification, database access, Vite build, commit, push, deployment, and release were not run or authorized.

Remaining boundary: selector-level runtime proof for every screen requires the authorized manual Arabic RTL/English LTR browser pass. The assistant's existing runtime safely skips a missing target; this is not claimed as visual acceptance.