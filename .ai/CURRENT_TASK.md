# Current Task — TSK-039 Operational Alerts and Exception Queue

**Date:** 2026-08-08
**Repository:** `/home/ubuntu/projects/toy-joy-phase-1-documentation`
**Status:** In Progress — Local/Dev alert/exception readiness boundary

## Source and dependency review

- TSK-038 dashboard/report readiness is complete; it provides no metric rows, report source data, or alert records.
- Required review: `docs/34-reporting-dashboard-alerts-specification.md`, `docs/36-module-data-contracts.md`, `docs/37-ui-screen-specifications.md`, `docs/38-print-export-specification.md`, `docs/39-uat-and-release-gates.md`, roles/permissions, source models, current notification/navigation UI, and alert/exception policies.
- Alerts require trigger, severity, owner role, scope, created/due time, acknowledged/resolved state, source link, suppression/deduplication, and safe navigation.

## Authorized implementation slice

1. Review existing alert, notification, exception, source, permission, and navigation contracts.
2. Add only source-safe Local/Dev alert/exception readiness or empty-state UI.
3. Keep thresholds, creation, acknowledgement, resolution, escalation, notification delivery, and source links pending when undocumented or absent.
4. Add bilingual Page Guide coverage with stable targets if a new screen is introduced.

## Before closing TSK-039

- Verify English/LTR and Arabic/RTL, authorized/no-access/direct-route, no overflow, no unsupported alert rows, no mutation controls, and zero console errors.
- Verify canonical Page Guide title and all interactive tour selectors.
- Run cache/routes/locale/Pint/PHPStan/lint/build/diff gates.
- Update all `.ai` evidence/state files and `TASKS.md`.
- Commit one reviewed local slice; never push.

## Explicit boundary

No alert is created, delivered, acknowledged, resolved, dismissed, escalated, or treated as a production exception until trigger/source/scope/owner policy is approved.

## Next task after this

TSK-040 — Implement Acceptance/UAT and Release Readiness.
