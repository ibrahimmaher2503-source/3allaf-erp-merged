# Current Task — TSK-024 Discovery/Read-only Boundary after TSK-023

**Date:** 2026-08-07
**Repository:** `/home/ubuntu/projects/toy-joy-phase-1-documentation`
**Status:** TSK-023 is complete for the authorized Local/Dev online slice; TSK-024 is now discovery/read-only only.

## Required reading completed

- `AGENTS.md`, `AI_INDEX.md`, current `.ai/` control records, and the TSK-024 section in `TASKS.md`.
- `docs/31-pos-sales-specification.md`
- `docs/26-discount-return-policy.md`
- `docs/48-pos-financial-calculation-policy.md`
- `docs/49-validation-and-error-contracts.md`
- `docs/35-document-state-machines.md`
- `docs/36-module-data-contracts.md`
- `docs/37-ui-screen-specifications.md`
- `docs/38-print-export-specification.md`
- `DEC-060`, `BLK-008`, and POSF-01..04 references.

## TSK-023 closure evidence

The Local/Dev online POS slice is implemented and browser-verified: server-resolved assigned store/branch/drawer/shift, approved price and stock revalidation, idempotent sale approval, append-only stock movement linkage, suspend/retrieve, bilingual POS/sales/detail/thermal-A4 baseline, and `demo-no-access` denial. Correct `DEMO-SELL` scope is `store_id=1`: product 1 has `on_hand=1`, ledger sum `1`, and two sale movements totaling `-2`; the prior `store_id=2` probe was an incorrect scope query.

## TSK-024 allowed scope now

- Reconcile requirements, policies, data/print contracts, pending owner inputs, and existing TSK-023 seams.
- Design a read-only readiness/empty boundary for discount/tax/payment/evidence/open-price configuration if useful.
- Preserve POSF-01 rounding level, POSF-02 cash rounding, POSF-03 split-payment residual, and POSF-04 discount replacement as pending.

## Forbidden until explicit policy/configuration authorization

- No sales recalculation mutation, discount/tax/payment persistence, payment evidence upload, open-price approval, tax/discount defaults, gateway package, production payment/tax/numbering values, or seeded financial records.
- No claim of Phase 3 gate, UAT, hardware, or Production readiness.
- No PHPUnit/Pest or automated browser tests per DEC-012.

## Verification plan

Read-only inspection, route/source reconciliation, PHP lint/Pint/PHPStan/Blade diagnostics only if code changes, locale parity, `git diff --check`, and real browser verification for any readiness UI. Update `.ai/TEST_RESULTS.md`, `.ai/PROGRESS.md`, `.ai/HANDOFF.md`, `.ai/BLOCKERS.md`, `.ai/UI_SCREENS.md`, `TASKS.md`, and `.ai/SESSION_SUMMARY.md` after the boundary decision.

## Next action

Finalize the TSK-024 read-only boundary records and commit the coherent Local/Dev slice. Keep TSK-024 In Progress because financial mutation, POSF-01..04, BLK-008, UAT, hardware, and Production policies remain pending; do not advance TSK-025.
