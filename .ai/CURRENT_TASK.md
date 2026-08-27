# Current Task — M50B.3 Products, Inventory & Barcodes

**Date:** 2026-08-27
**Branch:** `feature/m50b3-inventory-catalog`
**Baseline:** published `v0.1.7` commit `6dc33299d89d82e5762490d080c6bdfb78dace55`

## Status

- Complete in development: the Inventory landing route now receives its existing overview data and adds permission-aware, visible-store-scoped operational metrics and exceptions.
- Products, barcodes/labels, balances, and movement-ledger tables reuse responsive M50B record-card and pagination patterns.
- Authoritative balance valuation is exposed only to the existing cost-view permission; stock, barcode, and catalog exceptions are never inferred beyond current records.
- The System Settings idle-spinner regression is corrected by separating the Alpine idle-disabled review state from Flux submit loading and targeting each Settings save action explicitly.

## Verification boundary

- PHP syntax, route registration, navigation/permission mapping, `git diff --check`, and one locked Vite build passed.
- No automated suite ran. No isolated MariaDB runtime was configured, so no browser fixture database or screenshots were created.
- No schema, migration, route, permission, workflow, inventory calculation, barcode allocation, production, deployment, tag, or remote change occurred.
