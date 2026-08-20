# Active Milestone — Client Feedback Setup Remediation (Expanded Master Request)

**Date:** 2026-08-20
**Current phase:** Cross-milestone initial-setup remediation
**Current milestone:** Client-feedback task cycle for company, branches, warehouses, POS/drawers, configuration, and master data
**Status:** ACTIVE on the owner-authorized expanded master request. The current CF queue is **12 DONE / 3 PARTIAL / 0 ACTIVE**; CF-08, CF-13, and CF-14 are PARTIAL. The expanded ledger has **8 Local/Dev slices with requirement-level PARTIAL evidence and 7 open groups**; owner approval, physical devices, UAT, Production, release, and final Master closure remain unclaimed.

**CLIENT FIX QUEUE pointer:** CF-08, CF-13, and CF-14 are PARTIAL; the remaining CF items are DONE for their recorded evidence. This pointer does not narrow the expanded implementation-planning authorization.

## Active boundaries

- Historical Batch B evidence exists, but CF-13 and CF-14 are currently PARTIAL. Eight expanded groups have Local/Dev slices with requirement-level PARTIAL evidence; seven groups remain open.
- The master request's requirements 0–72, priority order, acceptance obligations, and cross-cutting closure requirements are in scope. No requirement is marked complete by this authorization.
- The client-feedback Markdown is primary for this cycle; PRD remains authoritative for functional behavior and contradictions are recorded rather than silently resolved.
- Recorded isolated cycle databases are `toyjoy_client_feedback_20260819`, `toyjoy_master_migration_20260820`, and `toyjoy_browser_20260820`; only disposable QA fixtures may be created there.
- Tests and headed-browser checks are authorized only within DEC-085 scope.
- Existing maker/checker, active-status, object-scope, audit, historical immutability, and multi-branch rules remain mandatory.
- No synthetic setup or transaction data enters Production, and readiness is never inferred from page availability.
- Physical printer/device, external destination, Production, human UAT, release approval, commit, and push claims remain outside the authorization.

## Batch B local evidence checkpoint — 2026-08-20 (historical; CF-13/14 currently PARTIAL)

- Serial MariaDB 3307 verification passed **5/5 tests and 25 assertions** on the disposable client-feedback database. The product-import maker/checker scenarios covered staging, independent reviewer approval, and requester self-approval rejection; the §49 recipient resolver passed **2 tests and 5 assertions**.
- Authenticated headed-browser verification passed all six Settings tabs in Arabic RTL at 390px with no horizontal overflow or console errors. The settings 500/parse/root/dirty-state defect chain is fixed for this local scope.
- Supplier, branch, store, and customer-group validation states, the cash-drawer modal, and POS mobile layout at 375px were verified. The expanded waves remain open because owner policy, physical hardware, Production, UAT, release, and final requirement-by-requirement closure are not evidenced.

## Expanded local evidence checkpoint — 2026-08-20

- Customer/master verification now covers structured names, multiple child profiles, duplicate email/phone, hierarchy/group scope, supplier contacts/destinations, recipient resolution, and supplier-default/explicit-override PO terms. Authenticated QA persisted/reloaded customer/child and supplier/PO paths.
- Payment/tax passed **14 tests / 63 assertions**; sequence behavior passed **5/28** plus **3/16**; settings audit/authorization passed **6/38**; multi-branch passed **20/104**; the §66 scenario matrix passed **15/118**.
- Global/Branch/Location printer scope and Location → Branch → Global runtime resolution are locally verified. Migration safety passed 75 forward migrations, corrected rollback, second forward migration, and two stable authorization-seeder runs.
- Arabic RTL and English LTR desktop/mobile browser batches passed the affected pages. Initial Setup exposes unresolved owner decisions as permission-aware CTA cards without persisting approval.
- Local status: **8/15 expanded groups with Local/Dev slices and requirement-level PARTIAL evidence; 7/15 open**. Physical hardware/output, genuine owner values/data, human UAT, Production, release approval, final verdict, commit, and push remain external.

## P0 forged scope-path evidence — 2026-08-20

- Master delete/archive/`openEdit` RED accepted **6 foreign final IDs** and disclosed a foreign drawer; GREEN `BranchStoreDrawerMutationScopeTest` passed **7 tests / 31 assertions** on `toyjoy_scope_delete_p0_20260820`.
- Sequence foreign create/override RED then focused GREEN passed **4 tests / 8 assertions** on `toyjoy_p0_sequence_scope_20260820`. The full class was **10/11** due to an unrelated existing printer-list assertion failure and is not claimed green.
- Maker/checker execution RED was **3 tests / 3 assertions**: an independent approver received `AuthorizationException`, while foreign/mismatched scope targets were accepted. GREEN `PlatformMasterApprovalExecutionTest` passed **3/16** on `toyjoy_approval_execution_20260820` at `127.0.0.1:3307`; canonical target-derived scope and approved internal execution now apply, while direct actions remain gated/scoped.
- This fixes the named P0 forged paths locally only; the broader multi-branch review, owner decisions, UAT, Production, release, commit, and push boundary is unchanged.

## Expanded priority waves

- **Wave 0 / P0:** setup blockers and integrity foundations (settings/company, branches, warehouse/POS/drawer relationships, counts, phone, navigation, safe archive/delete, validation/prerequisites).
- **Wave 1 / P1:** setup architecture and business configuration (timezone, payments/proof/offline wording, tax, sequences, printers/templates, onboarding dashboard, setup/operations separation, imports, account/policy terminology, inheritance/readiness).
- **Wave 2 / P1:** master data (categories; customer registration, grouping, consent, children, loyalty/wallet; supplier groups, contacts, communications, terms, and recipient resolution).
- **Wave 3 / P2 + closure:** terminology/localization, help and empty states, destructive UX, multi-branch scope, audit/authorization/concurrency, regression rechecks, fixtures, evidence, and final remediation deliverables.

This is a planning boundary only. Verification, UAT, Production readiness, and final PASS/PARTIAL/FAIL remain unclaimed until their actual evidence exists.
