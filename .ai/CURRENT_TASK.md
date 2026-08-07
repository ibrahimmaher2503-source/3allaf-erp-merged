# Current Task — TSK-035 Asset Damage, Loss, Maintenance, and Depreciation Review

**Date:** 2026-08-07
**Repository:** `/home/ubuntu/projects/toy-joy-phase-1-documentation`
**Status:** In Progress — Local/Dev damage/loss/maintenance/depreciation source-safe discovery/readiness boundary

## Source and dependency review

- TSK-034 Rental Asset/Calendar readiness is complete for its bounded Local/Dev slice; no damage, loss, maintenance, cost, approval, or depreciation mutation may be inferred from it.
- Required source review before implementation: `TASKS.md`, `AGENTS.md`, `.ai/` controls, `docs/29-rental-asset-policy.md`, asset damage/loss/maintenance/depreciation, condition/evidence, responsibility, cost/privacy, approval/SoD/audit, idempotency, document-sequence, print, and current asset/inventory/party UI.
- Depreciation remains operational history only in Phase 1 and does not imply a general ledger; approved corrections must reference prior events.

## Authorized implementation slice

1. Review existing asset/stock/attachment/approval/audit/financial and print contracts.
2. Add only source-safe Local/Dev readiness/configuration surfaces for damage, loss, maintenance, assessment, responsibility, evidence, cost privacy, approval, final state, depreciation method, and correction references.
3. Add a guarded read-only damage/loss/maintenance/depreciation readiness screen if no safe routed surface exists.
4. Keep event creation/editing, cost posting, approval, state transition, maintenance completion, depreciation, and correction mutations disabled pending approved contracts.
5. Add bilingual Page Guide coverage with stable visible targets if a new screen is introduced.

## Before closing TSK-035

- Review English/LTR and Arabic/RTL UI before and after changes.
- Verify authorized/no-access/direct-route behavior, no mutation, no sensitive cost/evidence payload, no overflow, and no console errors.
- Run route/schema safety, lint, Pint, PHPStan, Blade cache, locale parity, registry/routes, build, and diff checks.
- Update all relevant `TASKS.md` and `.ai/` state/evidence/blocker files after the task.
- Create a local commit for TSK-035 only; never push.

## Explicit boundary

This task cannot claim damage, loss, maintenance, depreciation, cost, approval, or final asset-state behavior until required policy/source contracts are configured and verified. Local/Dev readiness is the deliverable until then.

## Next task after this

TSK-036 — Implement Final Party Settlement, Invoice, Receipt, Wallet, and Close Controls.
