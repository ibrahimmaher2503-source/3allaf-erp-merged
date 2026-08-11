# Active Milestone — TSK-027 Customer Profiles and Shared Retail Loyalty

**Date:** 2026-08-10
**Current phase:** Phase 4
**Current milestone:** DM 4.1 — Customers and Shared Loyalty
**Status:** Local/Dev customer and retail-loyalty contract is actually implemented and focused-tested. Production/UAT/release gates remain open. Do not start TSK-028.

**Evidence:** `TASKS.md` TSK-027, `testing/results/TSK-027-48-TEST-MATRIX.md`, SQLite/MariaDB lifecycle runs, MariaDB concurrency workers, direct route/RBAC checks, the 3-test readiness authorization regression, and Chromium/Firefox/WebKit browser execution.

## Active boundaries

- TSK-027 includes customer master, consent/privacy, child linkage, controlled merge/history, POS retail linkage, retail loyalty ledger, earn/redeem/expiry, approved adjustments, audit, idempotency, and scope enforcement.
- TSK-028 Product Wallet/Party Wallet, TSK-029 Gift Cards/Gift Receipts, and TSK-030 Returns/Exchanges are explicitly out of scope.
- Party-side customer history/loyalty consumers do not exist yet and remain downstream dependencies.
- Production policy values, production-safe role grants, named UAT owners, infrastructure, backup/restore, devices, and go-live approval remain open.

## Historical records

Prior milestone records remain in repository history and are not active task instructions. The active task is TSK-027 only.
