# Current Task — TSK-027 Real Functional Closure

**Date:** 2026-08-10
**Repository:** `C:\projects\toy-joy-phase-1-documentation`
**Status:** Local/Dev functional implementation and focused verification complete; production/UAT/release remain open. TSK-028, TSK-029, and TSK-030 were not started.

## Scope completed

- Customer master with bilingual identity/contact data, normalized unique phone, controlled duplicate/merge handling, branch/store scope, idempotency, and audited named mutations.
- Consent snapshots and purpose-scoped child profiles with privacy filtering and immutable/append-only history boundaries.
- Retail customer history and POS customer selection/registration; approved POS sale linkage now posts loyalty earn in the same transaction.
- Immutable source-linked retail loyalty ledger with FIFO earn/redeem/expiry, reconciliation/allocation records, approval-backed adjustment approval/rejection, SoD, audit, idempotency, locking, and rollback behavior.
- Real customer and loyalty screens, bounded customer/loyalty exports, direct HTTP route guards, and Local/Dev browser coverage.

## Verification evidence

- SQLite: `CustomerLoyaltyLifecycleTest`, 10 tests / 81 assertions.
- MariaDB: `CustomerLoyaltyLifecycleTest`, 10 tests / 81 assertions.
- MariaDB concurrency: 3 tests / 27 assertions, including duplicate customer creation and competing redemption.
- Browser: 7 executed tests passed across Chromium, Firefox, and WebKit; 2 non-Chromium mobile/visual checks intentionally skipped because Chromium owns the stable visual/accessibility baseline.
- Readiness regression: `MilestoneReadinessAuthorizationTest` passed 3 tests / 71 assertions; the legacy customer readiness route is authorization-protected and redirects to Customer Master instead of rendering a readiness-only screen.
- Full classification is in `testing/results/TSK-027-48-TEST-MATRIX.md`.

## Boundaries

- Exact legal consent wording/retention and final production loyalty values remain owner-controlled configuration.
- Party history/rules, Product Wallet/Party Wallet, Gift Cards/Gift Receipts, and Returns/Exchanges remain downstream and were not implemented.
- Human UAT, production role grants, infrastructure, backup/restore, physical devices, and release approval were not executed.
