# Current Task — TSK-041 Approved Master Data Import and Reconciliation Readiness

**Date:** 2026-08-08
**Repository:** `/home/ubuntu/projects/toy-joy-phase-1-documentation`
**Status:** In Progress — Local/Dev import/cutover and reconciliation discovery boundary

## Required review

- `docs/23-master-data-and-opening-stock.md`, `docs/25-data-import-and-cutover.md`, `docs/36-module-data-contracts.md`, `docs/37-ui-screen-specifications.md`, `docs/38-print-export-specification.md`, `docs/39-uat-and-release-gates.md`, plus any canonical import/template, backup, audit, permissions, and opening-stock documents discovered by repository mapping.
- Inspect existing import routes/actions/models, validation/error surfaces, dry-run/reconciliation tooling, seeders, master-data permissions, and migrations.

## Authorized implementation slice

1. Reconcile approved-master-data and opening-stock contracts against the actual repository; do not treat templates or proposed values as approval.
2. Add only a guarded Local/Dev readiness or empty-state UI for import/cutover/reconciliation if no source-safe operational surface exists.
3. Keep file acceptance, parsing, persistence, destructive replacement, opening valuation, cutover timestamps, maker/checker ownership, and production data `PENDING/TBD` when undocumented.
4. No upload, import, batch persistence, destructive update, stock posting, production cutover, or UAT sign-off.

## Verification before closure

- Manual browser: authorized/no-access/direct route, English/LTR, Arabic/RTL, no overflow, empty/error/disabled states, no sensitive values or mutation controls, zero console errors.
- Verify Page Guide title and every interactive selector if a new screen is introduced.
- Run allowed route/view/cache/locale/Pint/PHPStan/lint/build/diff diagnostics.
- Update `TASKS.md` and all active `.ai` evidence/state files, commit one coherent local slice, never push.
