# Current Task — v0.1.21 Purchasing Search/Barcode Hotfix 2

**Date:** 2026-08-31
**Branch:** `hotfix/v0.1.21-purchasing-search-barcodes`
**Baseline:** tagged `v0.1.20` commit `b69fbba638f270cf78dbc01a2b51395eca198f4c`

## Scope

- Complete final Purchase Invoice scanner, manual Add, camera, bounded autocomplete, draft-resume, and state-aware progression acceptance.
- Add explicit company-bound, marked, idempotent and reversible UAT seed/purge commands, Golden Path, and coverage evidence.
- Preserve the earlier Flux correction and release-cache/runtime-user safeguards.

## Verification directive and result

The focused M54/final-acceptance files passed 12 tests / 95 assertions. One isolated MariaDB completed migration, seed, idempotent reseed, Golden Path integrity checks, dry-run purge, confirmed purge, and reseed. One Node 20.20.2 Vite build passed. No broad suite, production access, deployment, tag, remote contact, shared database mutation, or version bump occurred.

## Closure

Complete in development and ready for release preparation. Evidence: `.ai/V0_1_21_HOTFIX2_EVIDENCE.md`.
