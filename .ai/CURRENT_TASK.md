# Current Task — TSK-040 Export Center and Audit Views Readiness

**Date:** 2026-08-08
**Repository:** `/home/ubuntu/projects/toy-joy-phase-1-documentation`
**Status:** In Progress — Local/Dev export/audit and acceptance-readiness discovery boundary

## Required review

- `docs/19-audit-logging-and-approval-records.md`, `docs/34-reporting-dashboard-alerts-specification.md`, `docs/36-module-data-contracts.md`, `docs/37-ui-screen-specifications.md`, `docs/38-print-export-specification.md`, `docs/39-uat-and-release-gates.md`, `docs/50-reporting-formula-catalog.md`, `docs/53-deployment-backup-and-rollback-runbook.md`, and existing audit/export/attachment/permission routes and screens.
- Reconcile TSK-040 against existing audit logs, source reports, permissioned downloads, PDF/Excel packages, artifact retention, redaction, and current UAT/release evidence.

## Authorized implementation slice

1. Inspect existing source-safe audit and export contracts; do not assume packages or artifact persistence exist.
2. Add only a guarded Local/Dev readiness or empty-state UI for export center/audit views.
3. Keep format, row/size limits, retention, storage, redaction, formula safety, download expiry, and audit access `PENDING/TBD` when undocumented.
4. No export artifact, PDF/Excel generation, download, audit mutation, production/UAT sign-off, or release approval.

## Verification before closure

- Manual browser: authorized/no-access/direct route, English/LTR, Arabic/RTL, no overflow, empty/error/disabled states, no sensitive values or mutation controls, zero console errors.
- Verify Page Guide title and every interactive selector if a new screen is introduced.
- Run allowed route/view/cache/locale/Pint/PHPStan/lint/build/diff diagnostics.
- Update `TASKS.md` and all active `.ai` evidence/state files, commit one coherent local slice, never push.
