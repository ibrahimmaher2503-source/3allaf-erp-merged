# UI/UX Release Progress

## 2026-09-01 — v0.1.22-hotfix1 post-deployment correction

- Completed the owner-listed Arabic/raw-enum, Setup semantics, Product Filter deletion, balance reset, whole-unit quantity, Barcode Label, Product Card pricing, barcode graphics, printer sizing/template, Purchase Invoice card and version corrections.
- One focused gate passed 4 tests/59 assertions, PHP/JSON/diff checks, and the single Node 20 Vite build; prior eight-case bilingual print visuals were preserved. Only physical-printer output acceptance remains.
- Prepared an immutable local release without production/shared data, active symlink, deployment, tags, pushes or remotes.

## 2026-08-31 — v0.1.21 Hotfix 3 Readiness/Pricing/UAT Consistency

- Unified Product Card and pricing readiness through one current-data evaluator across Setup Center, persistent context, Products/Product Card, and Pricing Lists.
- Added localized bounded remediation, direct edit/view-all actions, and correct List 0 inherited/effective/manual-override explanations.
- Audited and retained every named correction through Hotfix 2 and the marked, positive-priced, guard-protected UAT fixtures without accessing or changing production/UAT data.

## 2026-08-31 — v0.1.21 Final Stabilization

- Started exactly from tagged `v0.1.20`; replaced the unsupported Purchase Invoice `barcode` Flux icon with supported `qr-code` and confirmed all other scoped M54 Flux references are installed.
- Added immutable-release cache exclusion and runtime-user `rajeh` Artisan ownership guidance without changing M54 behavior or frontend assets.
- The focused M54 test file passed 8 tests / 62 assertions; purchasing-invoice routes and the isolated six-contract Flux render probe passed. No database, migration, broad suite, frontend build, PDF, production, deployment, tag, remote contact, shared database mutation, or version bump occurred.

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

## 2026-08-30 — M52 v0.1.18 Product Cards and Opening Inventory

- Added modal Product Filters management, two-stage Product Cards, exact international/supplier-local barcode policy, and an explicit reusable pricing-update contract.
- Added protected partial XLSX import and filtered XLSX/Cairo-PDF exchange for products, categories, and brands.
- Added dedicated immutable Opening Inventory draft, preview, transactional approval, reversal, and audited zero-opening decision; Setup step 21 now resolves to this workflow.
- Focused tests passed 11 tests/33 assertions; migration 000097 passed on isolated MariaDB; 10 genuine Arabic/English desktop/mobile captures passed with Cairo, zero serious/critical Axe findings, console errors, external requests, and document overflow. Existing frontend build retained: dabbde0ffadfb86f62400eb67668010949dd1b7eeb931174c7c72a0958e988b0.

## 2026-08-31 — M53 v0.1.19 Pricing Lists

- Added company-scoped protected List 0, reusable percentage lists, decimal-safe ceiling-to-5 pricing, dated manual product overrides, and outlet/branch price-list assignment.
- Added an authoritative effective-price resolver and snapshot DTO while preserving Product Card base prices, approved price versions/lines, costs, invoices, and completed sales.
- Added the responsive Pricing Lists workspace, Product Card pricing matrix, assignment controls, least-privilege permissions, audits, navigation, and factual Setup readiness reasons.
- Focused pricing tests passed 6 tests/28 assertions; migration 000098 passed on isolated MariaDB. Five genuine Arabic/English desktop/mobile cases passed with Cairo/RTL/LTR, zero serious/critical Axe findings, overflow, console errors, or external requests. Frontend sources were unchanged and the existing manifest was retained.
# M54 — v0.1.20 Purchase Receiving Distribution (2026-08-31)

- Implemented the full-page two-stage workflow, lookup/scanning, in-context Product Card creation, exact distribution matrix, outlet price snapshots, destination labels, transactional idempotent posting, Product Card cost/List 0 audit updates, reversal, and eight least-privilege permissions.
- Added one additive migration (`000099`). Existing Setup sequence remains the single context and Product Import remains inside Product Cards.
- Focused M54 PHPUnit passed 5 tests/29 assertions. Migration `000099` passed on disposable MariaDB `rajeh_m54_20260831`; the database/server were removed. Focused syntax, routes, Arabic JSON, and diff checks passed.

# v0.1.21 Final Acceptance (2026-08-31)

- Added distinct scanner/manual/camera entry, bounded three-character autocomplete, duplicate quantity focus, unknown-barcode Product Card continuation, and explicit draft stage/next-action/remaining-quantity presentation.
- Added company-bound UAT batches and allowlisted marked records with explicit dry-run seed/purge commands, generated-on-execution credentials, Golden Path, and coverage report.
- Corrected pending approval source-version synchronization after Stage 2 distribution, found during the isolated UAT journey.
- Focused tests passed 12/95; isolated MariaDB seed/purge/reseed lifecycle and Node 20.20.2 build passed. Manifest SHA-256: `4d41c4fce936e8ba68f79ec69c89c36178f4481a8fa660774cce61de4894fe3f`.

# v0.1.21 Purchasing Localization/Layout Hotfix (2026-08-31)

- Completed Purchase Invoice Arabic/English keys, locale-safe names/status/currency/quantities, responsive list records, aligned entry/search controls, and empty-line suppression.
- Persisted the explicit production UAT environment confirmation and real purchase-sequence reuse safeguards.
- Focused tests and eight-case desktop/mobile bilingual visual evidence passed; no database or production access occurred.

# v0.1.21 Purchasing Search/Barcode Hotfix 2 (2026-08-31)

- Separated exact scanner lookup from a cancellable ranked 175ms smart search with immediate Enter, company context, fragment matching, loading discipline, and a 20-result bound.
- Replaced the mixed-mode UAT product with separate GTIN and allocator-backed local products; new UAT prefix state is rejected if it would touch an existing real sequence and all created identities are marked for purge.
- Corrected the malformed Product Card `$isEditing` directive and completed the focused bilingual create/edit audit and evidence.
## 2026-09-02 — v0.1.22-hotfix4 inventory labels/sidebar/pricing

- Centralized strict whole-number product quantity presentation and validation across affected inventory, purchasing, retail, Party-consumable, reporting, and print flows; genuine fractions now fail rather than round, while monetary precision remains unchanged.
- Centralized inventory movement labels in Arabic and English, rebuilt barcode-label selection around bounded server search and preserved multi-selection/remediation state, corrected document-scrolling desktop sidebar behavior, and made positive Product Card base consumer price the immediate selling fallback.
- Focused PHPUnit, PHP/Blade/JSON/routes/diff checks, authenticated Arabic desktop/mobile browser checks, barcode PDFs, and isolated MariaDB migration status passed. Immutable release preparation remains the final packaging action; production remains untouched.
