# Current Task — TSK-026 Offline POS Readiness Boundary

**Date:** 2026-08-07
**Repository:** `/home/ubuntu/projects/toy-joy-phase-1-documentation`
**Status:** TSK-023 Local/Dev online slice is closed for its approved boundary; TSK-024 and TSK-025 have browser-verified read-only boundaries; TSK-026 is now a discovery/readiness Local/Dev boundary only.

## Required reading completed

- `TASKS.md`, `AI_INDEX.md`, current `.ai/` control records, and TSK-026 dependencies.
- `docs/51-offline-pos-operating-policy.md`
- `docs/31-pos-sales-specification.md`
- `docs/30-platform-operations-specification.md`
- `docs/35-document-state-machines.md`
- `docs/36-module-data-contracts.md`
- `docs/57-ui-interaction-and-data-entry-standard.md`
- `DEC-018`, `DEC-060`, `BLK-004`, and TSK-023–TSK-025 evidence.

## TSK-026 allowed scope

- Reconcile the restricted offline contract and owner-pending OFF-01..OFF-05 inputs.
- Add a server-gated read-only readiness/disabled page.
- Show the PRD-required permitted/blocked offline operation classes without enabling them.
- Preserve the platform cache boundary: no sensitive transactional/customer/payment/wallet/audit response is cached.

## Forbidden until explicit policy/configuration authorization

- No IndexedDB offline queue, `offline_transactions`, sync batches, replay, conflict actions, or local transaction persistence.
- No enabled branch/device, duration, amount, queue, price-age, expiry, retry, or conflict-disposition defaults.
- No offline sale/payment/stock/price/numbering/customer/wallet/loyalty mutation or production/UAT/device claim.
- No PHPUnit/Pest or automated browser tests per DEC-012.

## Implementation plan

1. Add `GET /pos/offline-readiness` under `auth`, `verified`, and `pos_sales.view`; pass no transactional or financial dataset.
2. Render bilingual LTR/RTL disabled/readiness cards for OFF-01..OFF-05 and the PRD permitted/blocked policy.
3. Verify route/source and inspect the rendered DOM/response for absence of queue/sync mutation controls, sensitive caches, numeric limits, and device enablement values.
4. Run PHP lint/Pint/PHPStan/Blade diagnostics, locale parity, `git diff --check`, and real browser authorized English/Arabic/no-access scenarios with console evidence.
5. Synchronize `TASKS.md`, `CURRENT_MILESTONE`, `CURRENT_TASK`, `PROGRESS`, `TEST_RESULTS`, `HANDOFF`, `BLOCKERS`, `UI_SCREENS`, `DECISIONS`, and `SESSION_SUMMARY`; commit locally only.

## Production non-claims

This boundary does not complete TSK-026, DM 3.4, Phase 3, UAT, device acceptance, offline security approval, or Production readiness. BLK-004 and DEC-018 remain open.

## Next action

Implement only the planned `/pos/offline-readiness` read-only boundary, then verify and synchronize records. Keep TSK-025 In Progress and do not claim offline enablement.
