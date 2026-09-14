# Current Task — v0.1.22-hotfix41 Reports Runtime Repair

**Date:** 2026-09-14
**Change class:** Production runtime repair
**Source baseline:** exact Hotfix40 code commit `08d733692895f1f3bf3f5be70ed17f5c37b5ddb1`
**Production application baseline:** exact active Hotfix39 commit `76e2f1fa780fb6da758a471902761ca6f40acbd8`
**Branch:** `hotfix/v0.1.22-hotfix41-reports-stringable-runtime`
**Status:** Complete and ready for the authorized root operator; implementation, the single focused final verification, clean commits, immutable package and fail-closed scripts are complete.

Hotfix41 preserves all Hotfix40 purchasing and centralized reporting behavior. It prevents Laravel `Stringable` instances created by report-label transformations from reaching translation helpers by explicitly converting transformed keys to scalar strings. The release workflow also normalizes runtime ownership and modes for `bootstrap/cache` in both exact active Hotfix39 and the Hotfix41 candidate before cache preparation and every authenticated pre-switch `/reports` smoke.

Hotfix41 adds no migration. Migration `2026_09_14_000114_extend_purchase_orders_and_exports_for_hotfix40.php` is already `Ran` in production and must not be rerun or rolled back. Activation must prove migrations 000111–000114 are already `Ran`, execute no migrations, and retain migration 000114 if exact Hotfix39 application files are restored.
