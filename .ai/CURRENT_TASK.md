# Current Task — v0.1.22-hotfix8 Performance/UI Correction

**Date:** 2026-09-03
**Baseline:** exact clean Hotfix6 commit `c607a30f82dd30a5a889b526882c141ad874b783`
**Branch:** `hotfix/v0.1.22-hotfix7-inventory-counting-ux`
**Status:** Implementation and focused verification complete; immutable release preparation follows the single Hotfix8 commit.

Hotfix8 preserves Hotfix7 and removes route-inappropriate inventory queries/eager loads, reuses paginator totals, adds complete profiler metrics, and corrects the bilingual balances filter layout. Production access was read-only; isolated verification used `toyjoy_hotfix8_verify` and local Chromium.
