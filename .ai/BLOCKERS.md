# Blocker Register

## Current TSK-027 closure boundary — 2026-08-10

TSK-027 customer master and retail-loyalty behavior is implemented and focused-tested for Local/Dev, including customer/consent/child/privacy records, controlled merge/history, POS linkage, immutable earn/redeem/expiry, approved/rejected adjustments, audit, idempotency, concurrency, and scope/RBAC. BLK-014 remains mitigated rather than closed because legal wording, final loyalty values, production roles, UAT, and release evidence remain owner-controlled. TSK-028, TSK-029, and TSK-030 were not started.

## Database Direction Override — 2026-08-09

DEC-068 supersedes the earlier local SQLite assumption: current local/development/test database operations use only XAMPP MySQL/MariaDB through phpMyAdmin. The older BLK-001 wording remains historical context; it is not active runtime configuration.

## TSK-043/044 gate blockers — 2026-08-08

Local/Dev readiness boundaries are complete and browser-verified. Actual UAT and Go-Live remain blocked by missing named owners, approved scenario/data/device/printer inputs, protected evidence repository, defect/retest register, stock/payment/wallet/party/report reconciliation, release ID, final production data/config/users, backup/restore evidence, rollback rehearsal, monitoring/support/training handover, client approval, and written sign-off.

No UAT result, defect closure, production deployment, migration, cutover, rollback, production restore, monitoring activation, or go-live authorization was executed. These values remain `PENDING/TBD`.

Next action requires owner-provided approvals and evidence, not additional application mutations.

## TSK-042 Production Operations and Handover boundary — 2026-08-08

TSK-042 bounded Local/Dev readiness is complete and browser-verified. Production infrastructure, secret inventory, device enrollment/acceptance, backup destination, restore rehearsal, support owner, training attendance, UAT, and go-live remain blocked pending approved operational inputs and evidence.

Next task: TSK-043 Scenario-Based Manual UAT and Defect Retesting.

## TSK-041 Master Data Import and Cutover boundary — 2026-08-08

TSK-041 bounded Local/Dev readiness is complete and browser-verified. Approved source files, production batch import, destructive replacement, opening-stock posting, cutover timestamp, backup/restore sign-off, and production reconciliation remain blocked pending owner data, operational inputs, and UAT/Production gates.

Next task: TSK-042 Production Readiness, Devices, Backup, and Training.

## TSK-028 wallet boundary — 2026-08-07

The Local/Dev TSK-028 foundation/readiness slice is implemented and browser-verified. Full task scope remains open for customer/source linkage, balances, credit/debt calculation, settlement, correction, reconciliation, payment, transfer, owner policy, Phase 4, UAT, and Production. Ten wallet policy values are exposed through Initial Setup/Settings as blank `PENDING` keys; they do not approve or activate any downstream mutation. Product/Party separation, server authorization, append-only history, idempotency, audit, and no generic transfer remain fixed invariants.

## TSK-040 Export Center and Audit Views boundary — 2026-08-08

TSK-040 bounded Local/Dev readiness is complete and browser-verified. The implementation exposes pending format/limit/retention/storage/redaction/formula-safety/reauthorization/audit-filter policies and a guarded read-only screen only. PDF/Excel/CSV generation, protected downloads, artifact storage, sensitive export auditing, and audit mutation remain blocked pending approved contracts.

Next task: TSK-041 Import and Reconcile Approved Production Master Data readiness.
## TSK-039 Operational Alerts and Exception Queue boundary — 2026-08-08

TSK-039 bounded Local/Dev readiness is complete and browser-verified. The implementation exposes pending trigger/source/severity/owner/scope/lifecycle/source-link/deduplication/notification/navigation policies and a guarded read-only screen only. Alert evaluation, creation, delivery, acknowledgement, resolution, dismissal, escalation, and production exception handling remain blocked pending approved contracts.

Next task: TSK-040 Export Center and Audit Views readiness.
## TSK-038 Dashboards and Reconciled Report Catalog boundary — 2026-08-08

TSK-038 bounded Local/Dev readiness is complete and ready for local commit. The implementation exposes pending report source lineage/scope/filters/KPI/reconciliation/alerts/pagination/export/precision/freshness policies and a guarded read-only screen only. KPI calculation, report rows, financial values, cross-scope access, alerts, drilldown, export, cache, and reconciliation claims remain blocked pending approved source contracts and data.

Next task: TSK-039 Operational Alerts and Exception Queue.



TSK-037 bounded Local/Dev readiness is complete and ready for local commit. The implementation exposes pending quotation type/customer/validity/status/prices/terms/approval/numbering/print-share/conversion policies and a guarded read-only screen only. Quote creation, approval, pricing, numbering, output, conversion, sale, party invoice, inventory, wallet, payment, and financial mutations remain blocked pending approved source contracts.

Permission note: no canonical quotation permission exists in the current authorization baseline, so the read-only readiness route uses existing `dashboard_reports.view`; no new operational permission was invented. A dedicated owner-approved quotation permission remains a future decision.

Next task: TSK-038 Dashboards and Reconciled Report Catalog.



TSK-036 bounded Local/Dev readiness is complete and ready for local commit. The implementation exposes pending final-readiness, invoice-freeze, payment-reconciliation, credit, Party Wallet, receipt, approval, idempotency, numbering, and print policy values and a guarded read-only screen only. Final invoice, receipt, settlement, wallet entry, credit/overpayment calculation, close, posting, and financial mutations remain blocked pending approved source contracts and operational values.

Next task: TSK-037 Quotations and Proposals.



TSK-035 bounded Local/Dev readiness is complete and ready for local commit. The implementation exposes pending damage/loss/maintenance/assessment/responsibility/evidence/cost/approval/depreciation/correction policy values and a guarded read-only screen only. Event creation, cost posting, approval, state transitions, maintenance completion, depreciation, corrections, stock, and financial mutations remain blocked pending approved source contracts and operational values.

Known follow-up: UI-PTY-012 guide route mismatch was fixed and feature-specific guide title/first target verified; full tour Finish replay reset the browser session and remains unverified.

Next task: TSK-036 Final Party Settlement, Invoice, Receipt, Wallet, and Close Controls.



TSK-034 bounded Local/Dev readiness is complete and ready for local commit. The implementation exposes pending asset identity/separation/availability/reservation/checkout/return/condition/approval/audit/print policy values and a guarded read-only screen only. Asset creation, reservation, checkout, return, condition, maintenance, cost, stock, financial, and print mutations remain blocked pending approved source contracts and operational values.

Next task: TSK-035 Asset Damage, Loss, Maintenance, and Depreciation Review.



TSK-033 bounded Local/Dev readiness is complete and ready for local commit. The implementation exposes pending operating-order/party-store/consumable/issue/return/reconciliation/approval/audit/print policy values and a guarded read-only screen only. Operating-order release, reservation, stock movement, issue/return, balance edit, completion, and print remain blocked pending approved source contracts and operational values.

Next task: TSK-034 Rental Asset Master, Calendar, Reservation, Checkout, and Return.



TSK-032 bounded Local/Dev readiness is complete and committed. The implementation exposes pending payment/evidence/receipt/balance/Party Wallet policy values and a guarded read-only screen only. Payment posting, receipt generation, financial balance, overpayment, reversal, and wallet entries remain blocked pending approved source contracts and operational values.

Next task: TSK-033 Party Operating Orders and Consumable Movements.



The bounded Local/Dev TSK-031 slice is implemented and browser-verified. `/party/readiness` is protected by `party_bookings_invoices.view`, reads no party/customer/child/calendar/invoice/payment/wallet rows, and exposes party-only stores, services/packages, schedule/location, privacy, cancellation/responsibility, pricing, deposit, working-invoice, and final-close values as pending configuration. `UI-PTY-001` is bilingual with stable visible targets and Party-specific guide copy.

Full booking/calendar/customer-child/invoice/payment/operating-order/final-close/print operations remain blocked by missing owner-approved party source and policy contracts. Retail products, supplier returns, Product Wallet, and retail customer mutations remain separate. This slice is Local/Dev evidence only; no Production/UAT approval is implied.

## TSK-030 Returns and Exchanges boundary — 2026-08-07

The bounded Local/Dev TSK-030 slice is implemented and browser-verified. `/pos/returns-readiness` is protected by `returns_exchanges_gift_instruments.view`, reads no return/source/stock/payment/customer/wallet/Gift Card rows, and exposes source, eligibility window, condition/disposition, approval/settlement, and audit/print policies as pending configuration. `UI-POS-008` is bilingual with stable visible targets and Gift Receipt/source wording.

Full return authorization, source-line/quantity/window/reason validation, condition approval, cash/original-method refund, exchange difference, Gift Card settlement, stock disposition, payment reversal, numbering, and print remain blocked by missing owner-approved policy/source contracts. Existing sales and supplier-return workflows remain separate. This slice is Local/Dev evidence only; no Production/UAT approval is implied.

## TSK-029 Gift Cards and Gift Receipts boundary — 2026-08-07

The bounded Local/Dev TSK-029 slice is implemented and browser-verified. `/gift-receipts` and `/gift-cards` are protected by `returns_exchanges_gift_instruments.view`, show empty/readiness states with no prices, references, balances, holder data, payment, or print artifacts, and link to configurable pending policies. `UI-POS-010` and `UI-POS-011` have bilingual Gift-specific guides and stable visible targets.

Full issue, reference generation, balance, redemption, partial/full use, void, expiry, privacy enforcement, numbering, source reconciliation, and print workflows remain blocked by missing owner-approved eligibility/validity/holder/void/reprint/format/privacy/source values. This slice is Local/Dev evidence only; no Production/UAT approval is implied.

## Historical TSK-027 dynamic settings boundary — 2026-08-07

The owner authorized dynamic Local/Dev customer-policy values. The reversible settings/readiness slice is implemented, but BLK-014 is not closed: configured values are not owner/legal approval and are not consumed by domain workflows. Consent/retention wording, children privacy, loyalty rates/tiers/earn rules, expiry/reversal, rounding, approval/SoD, ledger/idempotency, wallet, Gift Card, Phase 4, UAT, and Production inputs remain required.

## Next five task dependency audit — 2026-08-07

The requested next sequence is `TSK-023` through `TSK-027`. Each task was read against its routed PRD, milestone, architecture, policy, data-contract, UI, print, and interaction sources before any implementation attempt.

- **TSK-023 — POS Checkout:** the approved Local/Dev online slice is implemented and browser-verified. Formal completion remains blocked by the Phase 2/Phase 3 gates, approved POS/receipt/hardware workflow, real production drawer/shift assignments, and production/UAT evidence. The implementation deliberately excludes tax, discounts, payments/evidence, open price, offline, customer, and final hardware print policy.
- **TSK-024 — Discounts/Payments:** the read-only `/pos/financial-readiness` boundary is implemented and browser-verified under `pos_sales.view`. It reads only active configuration counts and explicitly displays pending discount/tax/payment/evidence/rounding/open-price/print states. Financial mutation remains blocked by BLK-008 and POSF-01..04.
- **TSK-025 — Shifts/Cash Control:** the read-only `/pos/shift-readiness` boundary is implemented and browser-verified under `pos_sales.view`. It reads only scoped active-drawer/current-user open-shift counts, passes no monetary fields, and preserves blind close; shift/cash/payment/variance/close mutation remains blocked by BLK-006/BLK-008 and owner policy.
- **TSK-026 — Offline POS:** the read-only `/pos/offline-readiness` boundary is implemented and browser-verified under `pos_sales.view`. It records OFF-01..OFF-05/NFR-04 as pending and exposes no queue/sync/replay/conflict/transaction surface. BLK-004 and DEC-018 remain open; no device, limit, expiry, retry, price-age, or conflict disposition is enabled.
- **Historical TSK-027 — Customers/Loyalty:** the old read-only `/customers/loyalty-readiness` boundary was implemented and browser-verified under the temporary `pos_sales.view` gate solely to protect an empty page. It has since been replaced by the real Local/Dev customer and retail-loyalty contract; current evidence is recorded in `.ai/CURRENT_TASK.md` and `testing/results/TSK-027-48-TEST-MATRIX.md`.

**Prepared plans:** each task must start with the documented dependencies above, use Local/Dev-only configurable values if explicitly authorized, preserve all pending owner inputs, and close only after its own server/UI/manual-browser/integrity evidence plus the applicable phase gate. Next action requires owner/phase-gate inputs or an explicit authorization for bounded Local/Dev slices; production/UAT completion is not claimed.

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
