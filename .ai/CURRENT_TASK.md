# Current Task — v0.1.22-hotfix42 Customer Corrections

**Date:** 2026-09-16
**Change class:** Customer-correction hotfix
**Source and production application baseline:** exact Hotfix41 commit `e4ac8a0a98d788eabcb750c797307fee0aa2efef`
**Branch:** `hotfix/v0.1.22-hotfix42-customer-corrections`
**Status:** Root reports schema-after PASS with migrations 000111–000116 Ran on the existing isolated database. Scenarios then failed at the application PO quantity validation: the normalized integer was passed directly to strict `bccomp`. Replacement application candidate `be76a45db4e6c4d7eb48c5ecd447a811733ebf81` casts it directly to decimal text without float conversion. Authenticated scenarios-only tooling inspects/reuses existing fixtures and resumes conversions transactionally, followed by pending locales and Hotfix41 compatibility; no migrations, guards, seed, restore or full runner repeat. Runtime verification remains pending root execution. No production action occurred. The original numbered 1–73 coverage source remains unavailable and mapping unproven.

Hotfix42 preserves the Hotfix37–Hotfix41 implementation of the original customer scope and closes the approved corrections:

- Migration 000115 adds exactly the approved purchase-order line discount/tax/inclusive/total snapshot fields, initializes legacy values without touching purchase-order headers, and fails closed on rollback.
- Server calculations persist authoritative PO snapshots, preserve an undistributed legacy header-tax delta during authorized draft edits, keep approved documents immutable, and carry snapshots into partial PO conversion.
- Creating Branch and Receiving Store/Warehouse are separate, branch numbering remains locked/continuous, and sales outlets may coexist with warehouses in a branch.
- Migration 000116 changes only `products.item_code varchar(50)` to nullable, preserving all codes and the existing unique index, with no backfill and a fail-closed rollback.
- Incomplete product saves normalize an absent code to `NULL`, remain inactive/searchable/resumable, and are excluded from transaction-facing complete/sellable scopes. Completion allocates once through the locked global sequence after supplier selection using the first three English supplier-name letters plus the established seven-digit sequence; persisted codes never regenerate.
- Purchase camera controls are absent, PO lines calculate discounts/taxes/totals immediately, and new user-facing behavior is localized for `ar`, `en`, and `ar-EG`.

No migration, database mutation, production access, promotion, or activation is authorized locally. Runtime checks that require the migrated database are blocked locally and must be enforced by the prepared activation guards instead. This package is not ready for activation until those checks are available and pass.
