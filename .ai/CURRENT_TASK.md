# Current Task — v0.1.22-hotfix7 Inventory Counting UX

**Date:** 2026-09-03
**Baseline:** exact clean Hotfix6 commit `c607a30f82dd30a5a889b526882c141ad874b783`
**Branch:** `hotfix/v0.1.22-hotfix7-inventory-counting-ux`
**Status:** Implementation and focused verification complete; immutable release preparation follows the single Hotfix7 commit.

Hotfix7 removes the active default-outlet fallback, restores authorized store navigation, applies human-name-first inventory presentation, adds a bounded stock locator, improves store discovery, and extends the existing stock-count domain with scoped multi-location sessions, append-only concurrent contributions, idempotency, transfer locks, cutoff-aware reconciliation, approval and reporting. Verification used only disposable socket-local MariaDB `toyjoy_hotfix7_verify` and local Chromium.
