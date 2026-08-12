# Active Milestone — TSK-045 Product Variations and POS Selection

**Date:** 2026-08-12
**Current phase:** Phase 2/3 follow-on
**Current milestone:** DM 2.1 catalog extension with DM 3.1 POS integration
**Status:** Local/Dev implementation and isolated MariaDB migration/seed integrity verification completed. Required headed browser scenarios, screenshots, UAT, Production configuration, and release gates remain open.

## Active boundaries

- Ordinary colour, size, character, and age fields remain descriptive and never generate implicit variants.
- Explicit variation families are standard products only and are non-sellable; child Products are the transaction identities.
- Existing simple products remain simple. Used/barcoded/priced/stocked products cannot be converted.
- Excel variation import is deferred; simple-product import rejects family and child updates.
- Automated test creation/execution is prohibited for TSK-045. Verification uses XAMPP MariaDB plus authorized non-test diagnostics and manual browser work.
- No Production readiness, UAT acceptance, Phase Gate completion, commit, or push is inferred.

## Remaining milestone evidence

Run and record the headed Chromium scenario matrix and same-viewport Product Master/POS screenshots, including responsive RTL/LTR, keyboard/focus, media authorization/fallback, all transaction consumers, cart persistence, suspend/resume, checkout, receipt, and return behavior.
