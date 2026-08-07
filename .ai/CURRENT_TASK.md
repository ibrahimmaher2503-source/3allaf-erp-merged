# Current Task — TSK-025 Shift/Cash Readiness Boundary

**Date:** 2026-08-07
**Repository:** `/home/ubuntu/projects/toy-joy-phase-1-documentation`
**Status:** TSK-023 Local/Dev online slice is closed for its approved boundary; TSK-024 has a browser-verified read-only financial readiness boundary; TSK-025 is now a discovery/read-only Local/Dev boundary only.

## Required reading completed

- `AGENTS.md`, `TASKS.md`, `AI_INDEX.md`, current `.ai/` control records, and TSK-025 dependencies.
- `docs/32-cash-drawer-shift-specification.md`
- `docs/31-pos-sales-specification.md`
- `docs/35-document-state-machines.md`
- `docs/36-module-data-contracts.md`
- `docs/38-print-export-specification.md`
- `docs/57-ui-interaction-and-data-entry-standard.md`
- `DEC-060`, `BLK-006`, `BLK-008`, and TSK-023/TSK-024 evidence.

## TSK-025 allowed scope

- Reconcile shift/drawer lifecycle, scope, blind-close, variance, audit, and print contracts.
- Add a server-gated read-only readiness page using existing `pos_shifts`/`cash_drawers` records.
- Show only safe counts/status labels and explicit owner/policy `PENDING` states.
- Preserve the blind-close invariant: expected totals must not be exposed before actual submission, including in HTML/JSON/browser-visible data.

## Forbidden until explicit policy/configuration authorization

- No shift open/close transition, cash movement, payment, expected-total calculation, variance calculation/review, approval record, document sequence, or print mutation.
- No display of opening/closing amounts, expected totals, actual totals, variance amounts, or hidden/preloaded monetary fields.
- No new production drawer assignments, opening floats, cash policies, numeric limits, hardware claims, UAT, or Phase 3 exit claim.
- No PHPUnit/Pest or automated browser tests per DEC-012.

## Implementation plan

1. Add `GET /pos/shift-readiness` under `auth`, `verified`, and `pos_sales.view`; query only scoped active drawer count and current-user open-shift count.
2. Render bilingual LTR/RTL readiness cards with disabled/pending semantics; do not pass monetary fields to the view.
3. Verify route/source and inspect the rendered DOM/response for absence of `opening_cash`, `closing_cash`, `expected`, `actual`, and `variance` numeric values.
4. Run PHP lint/Pint/PHPStan/Blade diagnostics, locale parity, `git diff --check`, and real browser authorized English/Arabic/no-access scenarios with console evidence.
5. Synchronize `TASKS.md`, `CURRENT_MILESTONE`, `PROGRESS`, `TEST_RESULTS`, `HANDOFF`, `BLOCKERS`, `UI_SCREENS`, and `SESSION_SUMMARY`; commit locally only.

## Production non-claims

This boundary does not complete TSK-025, DM 3.3, Phase 3, UAT, hardware/print acceptance, or Production readiness. Cashier/manager policies, drawer assignments, numeric limits, payment linkage, variance rules, and close outputs remain owner/configuration pending.

## Next action

Implement only the planned `/pos/shift-readiness` read-only boundary, then verify and synchronize records. Do not advance to TSK-026.
