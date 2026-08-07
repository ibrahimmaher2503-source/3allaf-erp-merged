# Current Task — TSK-030 Returns and Exchanges

**Date:** 2026-08-07
**Repository:** `/home/ubuntu/projects/toy-joy-phase-1-documentation`
**Status:** In Progress — Local/Dev source-safe returns/exchanges readiness boundary

## Source and dependency review

- TSK-029 Gift Card/Gift Receipt readiness is complete for its bounded Local/Dev slice; no return/exchange mutation may silently create a Gift Card, wallet, payment, or customer mutation.
- Required source review before implementation: `TASKS.md`, `AGENTS.md`, `.ai/` controls, sales/return/stock/audit/authorization contracts, return policy docs, reason catalogs, refund/exchange/receipt/print contracts, and the existing POS routes/views/models.
- Existing return/reversal primitives must be reconciled before adding any schema or action. Do not infer approval, refund, exchange, disposition, eligibility window, or reason defaults from code alone.

## Authorized implementation slice

1. Inspect existing return/reversal/source-line contracts and record the dependency map.
2. Add only source-safe Local/Dev readiness/configuration surfaces for pending return reasons, eligibility windows, approval/SoD, disposition, refund method/evidence, exchange pricing, stock treatment, numbering, and print values.
3. Add a guarded read-only Returns and Exchanges screen if the existing routed surface is insufficient; show empty/pending states and preserve source/tenant/branch authorization boundaries.
4. Keep customer/loyalty/wallet/Gift Card/financial/stock mutations disabled until owner-approved policies and source contracts are complete.
5. Add bilingual Page Guide coverage with stable visible targets if a new screen is introduced.

## Before closing TSK-030

- Review English/LTR and Arabic/RTL UI manually before and after changes.
- Verify authorized/no-access/direct-route behavior, source/tenant/branch scope, no speculative refund/exchange controls, no overflow, and no console errors.
- Run route/schema safety, lint, Pint, PHPStan, Blade cache, locale parity, registry/routes, build, and diff checks.
- Update `TASKS.md`, `.ai/CURRENT_TASK.md`, `.ai/CURRENT_MILESTONE.md`, `.ai/PROGRESS.md`, `.ai/TEST_RESULTS.md`, `.ai/UI_SCREENS.md`, `.ai/SESSION_SUMMARY.md`, `.ai/HANDOFF.md`, and `.ai/BLOCKERS.md` after the task.
- Create a local commit for TSK-030 only; never push.

## Explicit boundary

This task cannot claim full return authorization, source-line validation, reason enforcement, refund, exchange, restocking, stock movement, payment reversal, numbering, printing, approval, or audit completion until the required policy/source contracts are configured and verified. Local/Dev readiness is the deliverable until then.

## Next task after this

TSK-031 — Implement Purchase Orders and Procurement.
