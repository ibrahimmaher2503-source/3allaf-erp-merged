# Current Task — v0.1.22-hotfix9 Navigation Cleanup

**Date:** 2026-09-03
**Baseline:** exact deployed Hotfix8 commit `90180c270f2ba2425877fe095c7a1c82c049989f`
**Branch:** `hotfix/v0.1.22-hotfix9-navigation-cleanup`
**Status:** Hotfix9 and its PHP 8.5 view-cache source correction are implemented and focused verification is complete; corrected immutable release preparation and an unexecuted promotion script follow the correction commit.

Hotfix9 removes duplicated sidebar/profile and pricing presentation, keeps canonical Basic Data navigation, and centrally defers Party navigation/setup readiness while preserving routes, permissions, data, and functionality. The active setup workflow contains 20 steps. Production is not authorized for mutation or deployment.

The view-cache correction decomposes the oversized pricing-label Blade source into bounded partials without changing Livewire compilation, PCRE limits, vendor code, or Hotfix8/Hotfix9 behavior. PHP 8.5 `optimize:clear` and `view:cache` pass with task-local file cache/session overrides because this worktree intentionally has no `.env`.
