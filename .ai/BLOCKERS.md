# Blocker Register

## TSK-019–TSK-022 correction boundary — 2026-08-07

The Local/Demo inventory correction review is complete in commit `1b66b69`. Visible-store scope, fail-closed action authorization, multi-line transfer receipt/reconciliation, terminal `difference_review`, server-side difference allowlists, generic exception handling, cost permission gating, and resolver contrast are implemented and browser-verified. This mitigates the local implementation gap only; it does not close BLK-006, BLK-010, or BLK-012 for Production. Real branch/store assignments, opening-stock cutover, final reason catalogs, thresholds, dispositions, count tolerances, UAT, hardware, and release approval remain pending.

## TSK-018 boundary update — 2026-08-07

TSK-017 Local/Dev is closed. TSK-018 Local/Dev Dummy-data queue display is implemented using the existing `stock_balances`/`printer_configurations` contracts plus new `label_queues` and append-only `label_print_events`. The owner-authorized fixture is local-only and does not close remaining-stock authority, printer/device, label layout, branch exception, reprint, hardware, Production, or UAT gates.

## TSK-017 boundary update — 2026-08-07

The Local/Dev pricing slice is implemented through proposal/version approval, CSV-as-Draft import, and history comparison. BLK-011 remains `Mitigated`, not Closed: final pricing authority, numeric limits, effective timing/rounding, branch exceptions, label quantities/layouts, printers, and production values still require Owner/Operations input. The implementation must not be treated as Production policy or UAT sign-off.

## TSK-010 Production and Authorization Boundary — 2026-08-04

Local catalog identity implementation is allowed by DEC-043, but production catalog hierarchy/master data, supplier records and codes, final barcode ranges, and final product attributes remain pending under BLK-009. DEC-038-approved catalog `View (A)` grants are now seeded for the verified local roles; catalog `P`/`R` capabilities remain intentionally ungranted until the corresponding owner-approved workflow is implemented. No production readiness or milestone gate is closed by the TSK-010 slice.

The following detailed specifications are local implementation references only. They do not close blockers or decide the production inputs stated in the register.

## Phase 1 Closure Audit Update — 2026-08-03

The TSK-001 through TSK-008 closure audit did not close any production blocker. Local implementation status and production readiness remain separate:

- BLK-001 and BLK-002 remain production blockers for hosting, deployment, queue/scheduler/cache/Redis, storage, backup destination/restore ownership, RPO/RTO, monitoring, and support. TSK-001 also retains a specific local gap for the actual backup/restore capability/status and setup/run/recovery deployment/rollback runbooks.
- BLK-003 and BLK-004 remain production/UAT blockers for actual devices, install/update support, and offline POS policy; they do not invalidate the approved local TSK-003 shell closure.
- BLK-005 remains open for production identity, MFA, lockout, reset, verification, and session-policy values; it does not invalidate the approved local TSK-002 closure.
- BLK-006 remains open for real branch/store/drawer master data and assignments; it does not invalidate the approved local TSK-006/TSK-007 closures.
- BLK-008 remains open for production tax/payment/numbering/template/printer values. TSK-005 also retains local effective-date/overlap and configuration print-preview gaps.

| Blocker | Detailed local mitigation references | Production decision retained |
|---|---|---|
| BLK-001 | docs/30 | Hosting, database, deployment, and runtime choices |
| BLK-002 | docs/30, docs/38, docs/39 | Provider, RPO/RTO, encryption, restore owner, monitoring/support |
| BLK-003 | docs/30, docs/31, docs/32, docs/38, docs/39 | Actual devices, drivers, browsers, paper, and connectivity |
| BLK-004 | docs/30, docs/31, docs/35, docs/36 | Offline enablement, limits, evidence, retry, and conflict disposition |
| BLK-005 | docs/30 | MFA, password, reset, session, lockout, and identity policy |
| BLK-006 | docs/30, docs/32 | Production branch/store/drawer lists and assignments |
| BLK-007 | docs/30, docs/36 | None; canonical matrix remains DEC-038 |
| BLK-008 | docs/30, docs/31, docs/32, docs/38 | Currency, tax, payments, sequence, template, printer values |
| BLK-009 | docs/23, docs/35, docs/36, docs/37 | Production catalog, hierarchy, images, and final attributes |
| BLK-010 | docs/35, docs/36, docs/38, docs/39 | Supplier data, purchasing terms, and opening-stock cutover |
| BLK-011 | docs/24, docs/31, docs/35, docs/36, docs/38 | Pricing authority, limits, timing, labels, and printer values |
| BLK-012 | docs/25, docs/35, docs/36 | Reasons, thresholds, locations, limits, and opening inventory |
| BLK-013 | docs/26, docs/31, docs/35, docs/36, docs/38 | Commercial return/refund terms and financial limits |
| BLK-014 | docs/27, docs/35, docs/36, docs/37 | Legal consent/retention wording and final financial rules |
| BLK-015 | docs/28, docs/33, docs/35, docs/36, docs/37, docs/38 | Party commercial terms, data, and output formats |
| BLK-016 | docs/29, docs/33, docs/35, docs/36, docs/37, docs/38 | Asset data, operations, and finance values |
| BLK-017 | docs/34, docs/38, docs/39 | Formulas, access, UAT owners, evidence, and sign-off |

Statuses are `Open`, `Mitigated`, or `Closed`. A Mitigated blocker has a documented temporary implementation assumption but still requires owner approval before production.

### TSK-011 production boundary — 2026-08-04

Local product-card fields, approved types, reportable attributes, and protected media are implemented for the approved local scope. BLK-009 and BLK-010 remain open for production catalog hierarchy/data, supplier codes and supplier master/history, final UOM/type/image-retention values, and final attribute/fractional-quantity policy. These production inputs do not authorize TSK-012 or TSK-013 and do not constitute a Phase 2 gate or production-readiness claim.

| ID | Title | Description / Impact | Required Owner and Information | Affected Requirements | Affected Milestones / Tasks | Temporary Assumption | Status |
|---|---|---|---|---|---|---|---|
| BLK-001 | Runtime and hosting baseline | The local runtime is verified, but production hosting, database, domain, deployment, SSL, queue, scheduler, cache, and Redis remain unknown; production package and deployment design cannot be finalized. | Technical owner: hosting environment, production domain, database engine/version, runtime limits, Redis availability, queue/scheduler process. | NFR-04–NFR-07 | DM 1.1; TSK-001–TSK-004 | Use PHP 8.4.21, Laravel 13.23.0, and SQLite locally. This does not approve the production stack. | Mitigated |
| BLK-002 | Backup, storage, and monitoring | Local attachment, retention, and audit controls are defined; production backup, recovery, and monitoring ownership remain unknown. | Technical/business owners: production RPO/RTO, encryption, destination, restore owner, storage quotas/retention, monitoring service and alert contacts. | POS-03, NFR-01, NFR-04 | DM 1.1, 1.4, 6.4; TSK-001, TSK-004, TSK-009, TSK-042 | DEC-039 adopts docs/18 and docs/19 for local implementation. Production provider, backup/restore, encryption, log retention, monitoring, and support values remain pending. | Mitigated |
| BLK-003 | Supported devices and browsers | POS scanners, cameras, thermal/A4/label printers, tablets, browser versions, and local-device integration are unconfirmed. | Operations/technical owners: device inventory, drivers, paper/label sizes, browsers, connectivity profile. | PRC-06, POS-01, POS-03, POS-06, CSH-04, NFR-04 | DM 1.1, 2.3, 3.1–3.4; TSK-003, TSK-018, TSK-023–TSK-026 | Design responsive, browser-standard interfaces; verify hardware later. | Open |
| BLK-004 | Offline POS policy | Enabled branches/devices, duration/amount/transaction limits, local evidence rules, permitted price age, expiry, retry, review ownership, and conflict disposition are missing. | Project owner + operations/security: full offline operating policy. | POS-01–POS-05, NFR-01, NFR-04 | DM 1.1, 3.4, 6.3; TSK-003, TSK-026, TSK-043 | Treat offline as restricted and block wallets, loyalty redemption, credit, special discounts, and conflict-prone operations. | Open |
| BLK-005 | Authentication and session policy | Identity source, password/MFA requirements, reset/verification, session duration, device/session tracking, and lockout rules are unknown. | Security/project owner: authentication and session policy. | NFR-01, NFR-03, NFR-04 | DM 1.1, 1.3; TSK-002, TSK-008 | Laravel session authentication; no MFA assumption. | Open |
| BLK-006 | Branch/store/drawer structure | Approved branch list, store list/types, selling-store mappings, cash drawers, overrides, and opening assignments are missing. | Operations owner: canonical lists, relationships, statuses, ownership, opening balances. | MD-01, INV-02, CSH-01 | DM 1.2, 2.4, 3.3; TSK-005–TSK-007, TSK-019, TSK-025 | No production master data is inferred. | Open |
| BLK-007 | Roles and permissions | Closed by DEC-038: `docs/04-roles-permissions.md` is the approved canonical matrix for implementation. | None. | All; especially NFR-03 | DM 1.3 onward; TSK-008 and every functional task | Implement only the approved matrix. | Closed |
| BLK-008 | Tax, payments, numbering, and prints | Currency, tax policy, methods, evidence requirements, document sequences, invoice/receipt/label layouts, printers, and copies are unknown. | Finance/operations: currency/precision, tax applicability, methods, numbering formats, approved templates and paper sizes. | MD-01, PUR-04, POS-03–POS-07, CSH-04, PTY-04, PTY-06, RPT-03, NFR-06 | DM 1.2, 2.2–2.3, 3.x, 5.x, 6.x; TSK-005, TSK-007, TSK-014–TSK-018, TSK-023–TSK-025, TSK-032, TSK-036, TSK-040 | Keep layouts and formulas configurable; PRD wording governs receipts. | Open |
| BLK-009 | Product and barcode setup | Local product and barcode conventions are defined; production catalog and allocation data remain unknown. | Merchandising/warehouse: production hierarchy, templates, data, supplier codes, image sources, and final fractional settings. | MD-02–MD-05, PRC-01–PRC-02, INV-06 | DM 2.1; TSK-010–TSK-013 | DEC-039 adopts docs/23 for local implementation. Real master data, final configuration values, and production images remain pending. | Mitigated |
| BLK-010 | Supplier, purchasing, and opening stock data | Supplier records/terms, purchase templates, receipt semantics, returns, discounts/tax, and opening-stock method are missing. | Purchasing/warehouse/finance: approved data, templates, authorization, opening inventory cutover. | PUR-01–PUR-06 | DM 2.1–2.2, 6.4; TSK-013–TSK-016, TSK-041 | Do not create production records from examples. | Open |
| BLK-011 | Pricing policies | Local pricing, approval, unpriced, open-price, and label conventions are defined. | Pricing/owner: final authority assignments, numeric limits, effective timing, label quantities/layouts, and production printer values. | PRC-03–PRC-08 | DM 2.3; TSK-017–TSK-018 | DEC-039 adopts docs/24 for local implementation; final limits and production print/device values remain configurable or pending. | Mitigated |
| BLK-012 | Inventory exception policies | Local negative-stock, adjustment, transfer-difference, fractional, and count conventions are defined. | Warehouse/owner: final reason catalog, approval limits, disposition locations, count tolerances, and opening inventory method. | INV-03–INV-09 | DM 2.4; TSK-019–TSK-022 | DEC-039 adopts docs/25 for local implementation; production reasons, thresholds, locations, and opening inventory remain pending. | Mitigated |
| BLK-013 | Discount and return policies | Local discount, return, exchange, inspection, and refund conventions are defined. | Operations/finance: final return terms, numeric limits, exceptions, condition catalog, and refund approval values. | POS-05, RET-01–RET-03 | DM 3.2, 4.4; TSK-024, TSK-030 | DEC-039 adopts docs/26 for local implementation; production commercial terms and financial limits remain configurable or pending. | Mitigated |
| BLK-014 | Customer consent, loyalty, wallet, and gift-card policy | Local customer, loyalty, separated-wallet, and Gift Card conventions are defined. | Marketing/finance/legal/project owner: final legal consent/retention wording, loyalty rates, expiry/rounding, wallet limits, and Gift Card financial values. | MD-06, RET-04, CUS-01–CUS-04 | DM 4.1–4.3; TSK-027–TSK-029 | DEC-039 adopts docs/27 for local implementation; legal wording and final financial rules remain configurable or pending. | Mitigated |
| BLK-015 | Party commercial and operating policy | Local party booking, working invoice, payment-on-account, operating-order, consumable, and settlement conventions are defined. | Party/finance/operations owners: final services/packages, cancellation/deposit terms, responsibilities, real master data, final-close checks, and print formats. | PTY-01–PTY-06 | DM 5.1–5.5; TSK-031–TSK-036 | DEC-039 adopts docs/28 for local implementation; production commercial terms, data, and print formats remain configurable or pending. | Mitigated |
| BLK-016 | Rental asset and damage policy | Local asset identity, reservation, inspection, damage, loss, maintenance, and depreciation conventions are defined. | Party/asset/finance owners: final asset register, categories, locations, buffers, checklists, assessment methods, approval limits, and finance rules. | AST-01–AST-05 | DM 5.3–5.4; TSK-033–TSK-035 | DEC-039 adopts docs/29 for local implementation; production asset data and final operations/finance values remain configurable or pending. | Mitigated |
| BLK-017 | Reporting and UAT ownership | KPI formulas, margin access, report layouts, export limits, client acceptance owners, evidence repository, severity owners, and sign-off process are missing. | Management/finance/project owner: formula catalog, access, UAT owners/scenarios, sign-off. | RPT-01–RPT-03 and all acceptance scenarios | DM 6.1–6.4; TSK-038–TSK-044 | Trace every figure to source transactions and permissions. | Open |
