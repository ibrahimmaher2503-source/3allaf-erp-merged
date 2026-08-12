# Current Task — TSK-045 Product Variations, Media, and POS Selection

**Date:** 2026-08-12
**Status:** Local/Dev implementation and isolated MariaDB migration/seed verification completed; required manual browser evidence remains open.

## Scope

- Explicit bilingual option groups and values for standard-product variation families.
- Non-sellable parent families with independent sellable child SKUs.
- Protected family/child media fallback and POS-authorized thumbnails.
- Isolated Livewire POS discovery, variation selection, and cart updates with guarded POST checkout.
- Historical bilingual variation snapshots on sale lines and downstream documents.

## Verification directive

- Do not create or run PHPUnit, Pest, Playwright, Cypress, or any automated suite.
- Use XAMPP MySQL/MariaDB only. The disposable verification database is `toyjoy_tsk045_verify_20260812`.
- Permitted checks are PHP syntax, Pint check-only, PHPStan, Blade cache, Vite build, migration status, database integrity queries, and `git diff --check`.
- Required visible headed Chromium scenarios and same-viewport Product Master/POS screenshots remain pending because no authorized interactive browser-control capability was available in this session.

## Remaining closure

Complete and record the manual browser scenario matrix, responsive Arabic RTL/English LTR review, keyboard/focus review, protected-media cases, transaction-consumer checks, and Product Master/POS screenshots. Do not claim UAT, Production readiness, Phase Gate completion, commit, or push without actual evidence.
