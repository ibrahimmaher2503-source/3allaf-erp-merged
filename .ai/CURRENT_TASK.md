# Current Task — v0.1.22-hotfix42 Customer Corrections

**Date:** 2026-09-16
**Change class:** Customer-correction hotfix
**Source and production application baseline:** exact Hotfix41 commit `e4ac8a0a98d788eabcb750c797307fee0aa2efef`
**Branch:** `hotfix/v0.1.22-hotfix42-customer-corrections`
**Status:** Replacement candidate `f9b488c9d2bde96718c2e23f9a4cd6ef79ee3b22` and separately authenticated resumable isolated-validation tools are prepared after the prior guard failed on MariaDB information-schema result-name casing. The replacement has not been promoted or activated; isolated runtime checks remain operator-executed and runtime-gated.

Hotfix42 preserves the Hotfix37–Hotfix41 implementation of the original customer scope and closes the approved corrections:

- Migration 000115 adds exactly the approved purchase-order line discount/tax/inclusive/total snapshot fields, initializes legacy values without touching purchase-order headers, and fails closed on rollback.
- Server calculations persist authoritative PO snapshots, preserve an undistributed legacy header-tax delta during authorized draft edits, keep approved documents immutable, and carry snapshots into partial PO conversion.
- Creating Branch and Receiving Store/Warehouse are separate, branch numbering remains locked/continuous, and sales outlets may coexist with warehouses in a branch.
- Migration 000116 changes only `products.item_code varchar(50)` to nullable, preserving all codes and the existing unique index, with no backfill and a fail-closed rollback.
- Incomplete product saves normalize an absent code to `NULL`, remain inactive/searchable/resumable, and are excluded from transaction-facing complete/sellable scopes. Completion allocates once through the locked global sequence after supplier selection using the first three English supplier-name letters plus the established seven-digit sequence; persisted codes never regenerate.
- Purchase camera controls are absent, PO lines calculate discounts/taxes/totals immediately, and new user-facing behavior is localized for `ar`, `en`, and `ar-EG`.

No migration, database mutation, production access, promotion, or activation is authorized locally. Runtime checks that require the migrated database are blocked locally and must be enforced by the prepared activation guards instead. This package is not ready for activation until those checks are available and pass.
