# Current Task — Rajeh ERP v0.1.21 Final Stabilization

**Date:** 2026-08-31
**Branch:** `feature/v0.1.21-final-stabilization`
**Baseline:** tagged `v0.1.20` commit `b69fbba638f270cf78dbc01a2b51395eca198f4c`

## Scope

- Permanently replace the unsupported M54 Purchase Invoice `barcode` Flux icon with supported `qr-code`.
- Audit only the M54 invoice, distribution, quick Product Card, state, and destination-label surfaces for unsupported Flux references.
- Add release-cache and runtime-user ownership safeguards to the deployment runbook.

## Verification directive and result

Only the existing focused M54 test file, the purchasing-invoice route subset, and one isolated Flux render probe were authorized. They passed without a database, migration, frontend build, PDF generation, broad suite, production access, deployment, tag, remote contact, shared database mutation, or version bump.

## Closure

Complete in development and ready for owner review. Evidence: `.ai/V0_1_21_EVIDENCE.md`.
