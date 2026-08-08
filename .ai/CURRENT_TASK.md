# Current Task — TSK-043 Scenario-Based Manual UAT and Defect Retesting Readiness

**Date:** 2026-08-08
**Repository:** `/home/ubuntu/projects/toy-joy-phase-1-documentation`
**Status:** In Progress — Local/Dev manual-UAT evidence and scenario-pack readiness

## Required review

- `docs/12-acceptance-criteria.md`, `docs/13-definition-of-done.md`, `docs/14-test-plan.md`, `docs/39-uat-and-release-gates.md`, plus actual scenario, browser-verification, defect, device, print, integrity, offline, and evidence-repository references.

## Authorized implementation slice

1. Reconcile the manual UAT scenario pack and evidence requirements against actual routes/screens and repository capabilities.
2. Add only a guarded Local/Dev scenario/evidence readiness surface if no source-safe UAT workspace exists.
3. Preserve `PENDING/TBD` for named owners, approved data/devices, sign-off, defect disposition, and production acceptance.
4. No UAT sign-off, production approval, automated-test claim, financial/stock posting, or device acceptance is authorized.

## Verification before closure

- Manual browser: authorized/no-access/direct route, English/LTR, Arabic/RTL, no overflow, empty/disabled states, no mutation controls, zero console errors.
- Verify Page Guide title and every interactive selector if a new screen is introduced.
- Run route/view/cache/locale/Pint/PHPStan/lint/build/diff diagnostics.
- Update `TASKS.md` and all active `.ai` evidence/state files, commit one coherent local slice, never push.
