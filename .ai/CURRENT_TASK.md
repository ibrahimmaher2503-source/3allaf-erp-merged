# Current Task — TSK-042 Production Readiness, Devices, Backup, and Training Readiness

**Date:** 2026-08-08
**Repository:** `/home/ubuntu/projects/toy-joy-phase-1-documentation`
**Status:** In Progress — Local/Dev production-readiness/device/backup/training discovery boundary

## Required review

- `docs/30-platform-operations-specification.md`, `docs/34-reporting-dashboard-alerts-specification.md`, `docs/38-print-export-specification.md`, `docs/39-uat-and-release-gates.md`, `docs/53-deployment-backup-and-rollback-runbook.md`, `docs/54-production-data-migration-and-reconciliation.md`, plus actual deployment, device, backup, monitoring, worker, scheduler, storage, and training references discovered by repository mapping.
- Inspect existing health/readiness routes, configuration screens, backup/restore commands, queue/scheduler configuration, printer/device records, and training/runbook surfaces.

## Authorized implementation slice

1. Reconcile production-readiness, devices, backup/restore, monitoring, support, and training contracts against the actual repository.
2. Add only a guarded Local/Dev readiness or empty-state UI if no source-safe operational surface exists.
3. Keep domains, secrets, workers, storage, monitoring, printers/scanners, backup targets, restore evidence, support owners, and training attendees `PENDING/TBD` when undocumented.
4. No production deployment, secret creation, device enrollment, backup deletion, restore claim, UAT sign-off, or release approval.

## Verification before closure

- Manual browser: authorized/no-access/direct route, English/LTR, Arabic/RTL, no overflow, empty/error/disabled states, no secret or mutation controls, zero console errors.
- Verify Page Guide title and every interactive selector if a new screen is introduced.
- Run allowed route/view/cache/locale/Pint/PHPStan/lint/build/diff diagnostics.
- Update `TASKS.md` and all active `.ai` evidence/state files, commit one coherent local slice, never push.
