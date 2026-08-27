# Current Task — M50B.2 Purchasing & Suppliers Workspace

**Date:** 2026-08-27
**Branch:** `feature/m50b2-purchasing-workspace`
**Baseline:** published `v0.1.6` commit `624f6341460630e7966141b4e55b24c97c62c755`

## Status

- Complete in development: the purchase-order landing route provides a permission-aware, visible-location-scoped purchasing dashboard and operational queue.
- Suppliers, purchase invoices, purchase orders/receiving, and supplier returns reuse the responsive M50B table and pagination foundations.
- Dashboard data is bounded and covers approved purchasing values, receiving states, approvals, return exceptions, trends, top suppliers/products, and recent activity.
- Due/overdue supplier balances are intentionally omitted because the current model has no authoritative payable ledger.

## Verification boundary

- PHP syntax, route registration, navigation mapping, `git diff --check`, and one effective locked Vite build passed.
- No automated suite ran. No isolated MariaDB runtime was configured, so no browser fixture database or screenshots were created.
- No schema, migration, route, permission, workflow, calculation, production, deployment, tag, or remote change occurred.
