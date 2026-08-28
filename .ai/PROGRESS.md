# UI/UX Release Progress

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
