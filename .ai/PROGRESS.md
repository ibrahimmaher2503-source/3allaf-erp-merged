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
| M53 — v0.1.15 verified visual UX corrections | Complete in development |
| M53.1 — guided Setup Previous/Next navigation | Complete in development |
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


M53 corrects the UI inventory to all 22 authoritative Setup steps, fully localizes and persists Setup context, replaces unreliable Flux idle-loading presentation with exact action-scoped controls, completes the 79-view semantic action adoption matrix, standardizes logical table spacing, and verifies active navigation and responsive mobile cards with genuine Arabic/English rendering. The mobile Sales table surface no longer remains horizontally scrollable after conversion to record cards.

M53.1 completes the primary Setup context with permission-filtered adjacent navigation derived from `InitialSetupStatus`. It preserves Setup query context, resolves nested pages to their owning step, keeps the Settings secondary navigator, and aligns both Setup group maps with the authoritative service order so Opening inventory remains step 22.

## 2026-08-29 — v0.1.16 Setup and Administration Foundation

- Replaced duplicated 22-step UI maps with one 21-step route/group authority (5 Basics, 5 Settings, 11 Operational Readiness); Product Import remains available from Product Cards but is no longer a Setup step.
- Added persistent route-owned Setup context, passed/failed readiness reasons, company-scoped append-only skip/defer decisions, and continuation that bypasses decisions until unresolved required deferred work must be surfaced.
- Supplier readiness now requires an active supplier with an active financial-settlement method only. Product/SKU linkage and payment terms remain optional capabilities.
- Settings persistence is separate from Previous/Next navigation, core forms use exact-target Save controls, and the Cashier template remains location-scoped through existing user/store/till mechanisms.
- Genuine guarded rendering passed 11 Arabic/English desktop/mobile cases with 21 authorized Setup cards, one sticky primary context, one Settings secondary navigator, localized reasons and navigation, visible Cashier least-privilege scope, Save labels, Cairo, zero document overflow, zero idle spinners, zero console/external requests, and zero serious/critical Axe findings. Existing built assets were reused; manifest SHA-256 remains `dabbde0ffadfb86f62400eb67668010949dd1b7eeb931174c7c72a0958e988b0`.

## 2026-08-30 — M51 v0.1.17 Customer and Supplier Data Management

- Added reusable hierarchical customer/supplier-group presentation, stable codes, ancestry-preserving search, leaf-only new customer assignments, and filtered XLSX/PDF exchange.
- Centralized customer phone identity, added Egyptian governorate/city residence data and tenant city administration, and preserved legacy customers without destructive backfill.
- Added fixed supplier settlement methods and aligned Setup readiness to an active configured supplier while retaining optional terms and supplier-product references.
- Added staged company-scoped XLSX imports, partial valid-row processing, localized rejection workbooks, formula-safe exports, and bounded filtered customer/supplier exports.
- Focused tests, syntax/routes/diff checks, isolated MariaDB migration, and 13 genuine Arabic/English desktop/mobile captures passed. Existing frontend assets were reused; manifest SHA-256 remains `dabbde0ffadfb86f62400eb67668010949dd1b7eeb931174c7c72a0958e988b0`.
