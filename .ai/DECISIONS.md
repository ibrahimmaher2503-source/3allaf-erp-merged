# Owner Decisions

- 2026-08-23 — Owner-requested privileged role-permission override: a super-admin or user holding the active `system-administrator` role may manage all active permissions, including sensitive permissions, for every role except the `system-administrator` role itself. Canonical role metadata remains protected, and maker/checker rules remain unchanged.

- 2026-08-26 — Source-handoff repository hygiene: generated/runtime artifacts, environment overrides, secrets, caches, logs, local databases/dumps, temporary exports/backups, and private attachments are excluded from version control while intentional examples, migrations, public source assets, and Laravel placeholder `.gitignore` files remain tracked. Item 36 removed the two tracked browser verification logs and generated PHPUnit compiled-view artifacts without rewriting history; the placeholder files were preserved. The stable source-handoff reference is `v0.1.3-source-handoff-complete`, which will be corrected to the item-35 documentation commit after that commit is created.

- 2026-08-26 — Isolated sanitized release lineage: Release Candidate publication uses `release/v0.1.4-rc1-sanitized`, created by removing only the approved QA/runtime/database path roots from isolated history. The original handoff branch remains unchanged as local recovery history and is not approved for publication. The sanitized lineage has no remotes and passed prohibited-path and high-confidence secret scans. Production deployment, migrations, backup verification, QA/UAT, release approval, and owner production approval remain unclaimed.

- 2026-08-27 — M50A shell architecture: one centralized permission-filtered navigation definition powers the desktop sidebar, mobile drawer, and command search. The global `/dashboard` is an operational cross-module summary; existing route-owned module landing pages remain module workspaces. POS stays a dedicated full-screen archetype under Sales & POS. Arabic uses locally hosted Cairo variable fonts, while locale-derived document direction and logical layout utilities preserve RTL/LTR behavior. M50A adds no route, schema, mutation, or commercial-template dependency.

- 2026-08-27 — M50A.1 visual correction: retain the M50A route/permission architecture, make Sales Overview the Sales & POS landing concept while keeping POS directly reachable, and use Inventory as the first real-data module-dashboard implementation. A centralized module-dashboard specification records ten module contracts for staged M50B delivery. Arabic rendering is accepted only with local WOFF2 response, FontFace readiness, Cairo-first computed styles, and glyph-metric evidence; a CSS family declaration alone is insufficient. Mobile KPI regions are keyboard-focusable horizontal strips, while the global document remains overflow-free.

- 2026-08-27 — M50B.1 internal-page and shell-state boundary: resource lists use shared page headers, GET filter summaries, responsive labeled rows, bounded 20/50/100 pagination, and server-validated sort allowlists. Desktop collapse is Rajeh-owned; only a valid version-2 local preference may select collapsed mode, while missing, legacy, unknown, empty, or corrupt state self-recovers to expanded. Flux remains responsible only for the mobile overlay drawer.

## 2026-08-27 — M50B.2 purchasing landing and payable boundary

- Use the existing authorized `purchasing.orders` route as the module landing/dashboard and order/receiving queue; do not add a route.
- Do not infer supplier due/overdue amounts without an authoritative payable ledger.
- Preserve existing routes, Gates, policies, visible-store/branch scopes, actions, calculations, approvals, inventory mutations, and schema.

## 2026-08-27 — M50B.3 inventory scope and Settings loading boundary

- Use `inventory.index` as the existing module landing route and keep catalog, balances, transfers, counts, adjustments, and barcode labels in one permission-filtered navigation group.
- Display inventory value only from authoritative `stock_balances.total_value` and only when `inventory_stock_card.cost_view` is allowed.
- Treat Flux submit loading and an Alpine idle-disabled state as separate concerns: the Company review button invokes only `previewCompany`, while every Settings save action has an explicit loading target.
- Preserve inventory mutations, costing, barcode allocation, routes, permissions, policies, and schema.


## 2026-08-27 — M50B.4 pricing capability and work-context boundary

- Treat immutable `PriceList`/`PriceVersion`/`PriceLine` records, effective dates, open-price bounds, approval, and POS cart-discount permissions as the authoritative pricing capabilities. Do not present standalone promotions or campaign discounts because no such source-owned model/workflow exists.
- Do not infer margin from unrelated or mixed store costs. Pricing coverage and exception indicators remain authorized-store scoped and read-only.
- The prior header selected the first visible store while most dashboards aggregated all visible stores. The shell now defaults to “All authorized stores,” persists an optional permission-checked store in the existing session context, and applies it to supported Sales, Purchasing, Inventory, and Pricing views. POS continues to use its existing shift/store ownership and is never silently reassigned by the shell.

## 2026-08-27 — M50B.5 Party capability and customer-history boundary

- Use the existing authorized `parties.bookings.index` route as the Party module landing page and keep calendar, invoice/payment, operating-order, and booking-detail workflows on their current routes and action boundaries.
- Treat `PartyBooking.location` and Party invoice service lines as the authoritative current venue/service description. Do not invent package, venue, resource-capacity, deposit, or approval master records that do not exist.
- Reuse the existing Customer identity and expose separately scoped Party history in its profile. Retail sales, Product Wallet, Party Wallet, Party invoices, and Party payments remain distinct ledgers and histories.

## 2026-08-28 — v0.1.13 guided-setup and administration UX boundary

- Keep `InitialSetupStatus` as the readiness authority and derive continuation from the first authorized required incomplete item; visiting a page never marks it complete and no persisted wizard-position field is introduced.
- The canonical hierarchy is `Branch → Store(type=selling) → CashDrawer → PosShift`. Present it as Branch → Sales Outlet → Cashier Till → Shift while preserving every internal class, field, route, permission, action, and relationship.
- Settings navigation wraps existing save methods: navigation happens only after successful validation/persistence, each control targets its exact action, and read-only history has no save action.
- Branch success guidance may prefill only the existing authorized store form. Primary-outlet selection appears only through the existing mapping action and is never fabricated or silently reassigned.


## 2026-08-28 — v0.1.14 navigation, table and action-system boundary

- Server-resolved route patterns are the authority for active module and destination state; local storage controls only the valid expanded/collapsed desktop preference and is migrated to version 3 with an expanded fallback.
- `InitialSetupStatus` remains the sole readiness authority. The primary 21-step context persists on linked pages and the settings-only numbered navigator remains secondary; neither writes progress.
- Management lists share table density, focus, mobile-record, and semantic action presentation. Print/PDF/export documents and the operational POS cart remain specialized and are intentionally excluded.
- Exact `wire:target` values scope confirmed loading states to their actual action. Routes, methods, permissions, policies, schema, calculations, and workflows are unchanged.


## 2026-08-28 — v0.1.15 genuine visual acceptance boundary

- `InitialSetupStatus` remains the sole readiness authority. The UI maps all 22 unique steps, including the existing `opening-configuration` step after approved selling prices; totals remain permission-filtered and derived rather than hard-coded.
- Settings actions retain their original methods and validation. The shared loading button exposes a spinner only for its exact `wire:target`; read-only sections never receive a save action.
- Responsive management tables stop horizontal scrolling when they have transformed into mobile record cards. Desktop/tablet overflow behavior remains available where content genuinely needs it.
- Genuine evidence may read authoritative data only through GET/HEAD requests guarded by a database read-only session and request rollback, with all writable runtime state task-local. No production record mutation is permitted.
