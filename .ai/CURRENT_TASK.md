# Current Task — M51 v0.1.17 Customer and Supplier Data Management

**Date:** 2026-08-30
**Branch:** `feature/v0.1.17-customer-supplier-data`
**Baseline:** published `v0.1.16` commit `0fc39754d5279286a35c00b824e0b59399c7a567`

## Scope

- Hierarchical customer and supplier groups with stable codes, leaf-only new customer assignments, filtering, and counts.
- Company-wide customer identity with canonical phone normalization and structured Egyptian residence geography.
- Company-scoped city administration over shared canonical governorates.
- Fixed supplier financial-settlement methods and readiness based only on an active configured supplier.
- Staged, auditable XLSX imports with partial valid-row acceptance and downloadable rejection workbooks.
- Filter-preserving XLSX/PDF exports for customer groups, customers, supplier groups, and suppliers.

## Verification directive

The owner authorized the focused M51 PHPUnit file and one genuine authenticated Arabic/English desktop/mobile browser pass against a disposable task-local MariaDB runtime. Production/shared data and runtime remain prohibited.
