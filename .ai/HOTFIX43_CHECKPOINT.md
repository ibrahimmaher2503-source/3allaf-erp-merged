# Hotfix43 implementation checkpoint — 2026-09-18

## Production deployment completed — 2026-09-18

- `https://rajeh.xyz` now serves immutable release `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.22-hotfix43-c71d0ad0651e`, commit `c71d0ad0651e62aa30c537e0f75fb8fdc6b4d8cf`, tree `0a797ddfff9ef8f1d8805f1ea3a4ac50b0ab8cf1`.
- The authenticated archive is `/home/rajeh_ahmed/toyjoy-v0.1.22-hotfix43-c71d0ad0651e.tar.gz` (`7e2078b246ca646581756d4a33d677eeeeebb85c7944660a756f843875729c2d`); its complete 14,779-file manifest is `/home/rajeh_ahmed/.hotfix43-complete-manifest.sha256` (`cad183937f389be1817f2ef045692a024067586e761b1036bfff415a53135bbd`). No Composer or frontend build ran.
- Deployment journal: `/var/lib/toyjoy/hotfix43-deployment/a8474409199802cfc3`. The fresh AES-256 encrypted database/private-storage backup, isolated file/database restore and full reconciliation passed. Candidate-bound receipt: `verified/candidate-bound-receipt.json`; stable copy: `/home/rajeh/shared/toyjoy/deployment/hotfix43-c71d0ad0651e-r8-receipt.json`.
- Production migration `000117` is `Ran` in batch 24 and `000118` is `Ran` in batch 25. Exact required columns/indexes/restricted FK/ownership passed. Five historical invoice lines, five invoice headers and four tax-setting business rows retained their pre-migration hashes.
- Atomic activation, Laravel production caches, required PHP-FPM reload, runtime-control restoration, maintenance removal, and HTTPS `/up` plus `/login` HTTP 200 checks passed. Nine authenticated affected GET pages returned HTTP 200 using array session/cache and a read-only database session; no production test transaction ran. Evidence: `live-smoke/authenticated-pages-r2.json`.
- The exact approved inventory exception remains unchanged and `UNRESOLVED`: balance `1`, product `2`, store `10`, quantity `0`, value `0`, average cost `10`. Quantity/value mismatches remain `0`; missing balances remain `0`; no inventory repair occurred.
- The first R1 deployment attempt stopped before maintenance, backup, migration or activation because its MariaDB metadata helper failed to alias information-schema column names. R2 corrected only that deployment helper and completed. A first live-smoke helper used compatibility URLs and received an expected redirect; the corrected canonical tax/printer URLs passed. Neither event exposed an application defect.
- Requirements 13, 45, 60, `OC-BRANCH-STORE`, and deferred C retain existing behavior pending owner clarification. Microsoft Excel and physical printer/scanner acceptance remain external and do not invalidate this deployment. The 84-row matrix status is not promoted to universal acceptance.

## Pre-deployment candidate snapshot — superseded by completion above

- Owner approval for migrations 000117 and 000118 was received exactly as proposed. Both migrations are implemented with preflight rejection, partially-applied-DDL recovery, and fail-closed rollback protections.
- The restored isolated MariaDB has both migrations in batch 24 exactly once. Historical purchase-invoice monetary aggregates remained unchanged; all four tax rules were unambiguously assigned to company 1; required nullability, uniqueness, lookup index, and restricted FK checks passed. A migration rerun was a no-op and rollback was refused before schema loss.
- The single focused database/browser pass passed after correcting a discovered production-data compatibility gap: legacy products without assignment rows remain company-global only when their tax ownership is compatible, while products assigned elsewhere remain excluded.
- All 12 shared XLSX templates passed ZIP and XML validation. Three localized PO PDFs were generated. Microsoft Excel and physical printer/scanner acceptance remain external.
- The retained 84-row matrix is updated under `.ai/audits/hotfix43-20260918/`. It explicitly separates verified completion, implemented-but-evidence-limited rows, external acceptance, owner decisions, and the inventory exception.
- At this pre-deployment checkpoint, production remained on Hotfix42 pending the release sequence now recorded as complete above.

## Pre-deployment identity and boundaries

- Pre-deployment production application symlink: `/home/rajeh/laravel` → `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.22-hotfix42-cb8c1f963737`.
- Pre-deployment `RELEASE_COMMIT`: `cb8c1f9637370e531f6ab23f634e70784742cf35`.
- Verified production maintenance mode absent; `https://rajeh.xyz/up` and `/login` returned HTTP 200 before edits.
- Correction worktree: `/home/rajeh/worktrees/rajeh-erp-hotfix43`; branch `hotfix/v0.1.22-hotfix43-original-requirements-corrections`; initial tree `2b43448ebd232734d4b701eedc78720e33e8fb4d`.
- The older `/home/rajeh/worktrees/rajeh-erp-development` audit/tooling worktree and all immutable audit/deployment artifacts remain untouched.
- As of this pre-deployment snapshot, no production database write, test transaction, migration, deployment, service change, commit, push or tag had occurred.
- Known inventory exception remains unchanged and unresolved: balance `1`, product `2`, store `10`, quantity `0`, value `0`, average cost `10`.

## Completed implementation

- Numbering allocation now requires an active branch, serializes on that branch, initializes missing branch/document sequences safely, enforces continuous six-digit `BRANCH-TYPE-000001` formatting, and leaves all historic references and legacy company counters unchanged. PO create labels its sample unallocated; edit shows the saved reference and friendly branch.
- Central exports now apply explicit company/branch/store scopes, filter PO by creating branch, distinguish receiving store, include empty customer/supplier groups and ungrouped suppliers, honor customer creation branch/store, and replace silent 5,000-row truncation with an explicit fail-closed 50,000-row limit. Permission, snapshot, fingerprint and writer paths remain in place.
- Opening paste detects duplicate rows before indexing and reports invalid/unknown/ambiguous/already-present entries. Zero opening locks company locations and refuses initialization after an approved opening document, movement or nonzero stock balance; it creates no stock balance or movement. Setup readiness is company-scoped.
- Customers and unified Printers/Print Templates gained permission-filtered navigation. Templates use an accessible compact table with assigned printer names and exact scopes. Customer groups use a compact create/edit full form instead of per-row forms.
- Products use the requested compact actions/search. Introductory text moved into contextual help, including branch/store/base price fallback. Loyalty help now explains earning, ledger-derived balance, expiry, oldest-first redemption and audited adjustments.
- Supplier English name is enforced in the action and model. Owner/accountant/sales-representative contact sections reuse existing contact roles. Selected-supplier and general purchase-cost queries use approved invoice history within the authorized company.
- PO workflow shows next action and responsible permission/role, records review-return reasons through approval history, and supports an audited row-locked submitted-to-draft return. PDF generation failure now reports that the save/approval succeeded and points to retry from the saved order.
- Changed UI strings were added for `en`, `ar` and `ar-EG`. Product lookup now carries explicit company context and rejects products outside active company assortment assignments.

## Approved exact schema decision

### Migration 000117 — purchase invoice line tax mode snapshot

- Add only `purchase_invoice_lines.tax_inclusive BOOLEAN NOT NULL DEFAULT FALSE` after `tax_code`; no index or foreign key.
- Backfill linked invoice lines from immutable `purchase_order_lines.tax_inclusive`.
- Current production has five invoice lines, all unlinked, all with zero tax rate and amount; `FALSE` is exact for those rows and no monetary value changes.
- In any other environment, unlinked lines with zero tax remain `FALSE`; any unlinked line with a nonzero tax rate or amount makes the migration fail before alteration and report its IDs for an explicit owner mapping. No tax mode is inferred from rounded money.
- Never recalculate or rewrite invoice monetary snapshots or headers.
- Rollback is fail-closed once any inclusive row exists because dropping the column would destroy an immutable financial fact.

### Migration 000118 — company ownership for tax rules

- Add only `tax_settings.company_id BIGINT UNSIGNED NOT NULL`, a foreign key to `companies.id` with restricted deletion, company/code uniqueness, and a company/status/default lookup index.
- Remove only the existing global unique constraint on `code`; replace it with unique `(company_id, code)`.
- Backfill is allowed only when each legacy row has one unambiguous company. Current production has exactly one company and four tax settings, so all four map to that company. Any environment with multiple companies and legacy tax rules must fail before alteration and report the rows for owner mapping.
- No tax rates, treatments, dates, defaults, product assignments or historical snapshots change.
- Rollback is fail-closed because removing company ownership could collapse valid same-code rules and destroy tenant identity.

## Verification and release closure

- The previously recorded focused MariaDB/browser/document/source checks were reused and not rerun.
- Packaging integrity, fresh encrypted backup and isolated restore, production migration invariants, preservation fingerprints, atomic activation, caches, runtime controls, HTTPS health and authenticated affected-page smoke all passed as recorded above.
- No PHPUnit/Pest/broad suite, seed, production test transaction, frontend rebuild, Microsoft Excel acceptance or physical printer/scanner acceptance ran during deployment.
- Hotfix43 packaging and production deployment are complete. Remaining items are only the named owner decisions, external acceptance, and the separately approved unchanged inventory exception.
