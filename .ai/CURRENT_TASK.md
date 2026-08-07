# Current Task — TSK-029 Gift Cards and Gift Receipts

**Date:** 2026-08-07
**Repository:** `/home/ubuntu/projects/toy-joy-phase-1-documentation`
**Status:** In Progress — Local/Dev foundation/readiness boundary

## Source and dependency review

- TSK-028 separate wallet foundation is complete as a bounded Local/Dev slice; no Gift Card operation may use Product Wallet.
- Required source: `docs/27-customer-loyalty-wallet-gift-policy.md`, `TASKS.md`, `AI_INDEX.md`, PRD/acceptance/security/UI/print contracts mapped by TSK-029, plus `.ai/` controls and repository rules.
- Existing `sales`, `sale_lines`, payments, document numbering, audit, protected media/print, and authorization contracts must be inspected before any write.

## Authorized implementation slice

1. Add Gift Receipt and Gift Card readiness/configuration surfaces to Initial Setup/Settings for missing eligibility, validity, holder, void, reprint, format, and privacy values; unknown values remain `PENDING/TBD`.
2. Add separate named Gift Card/Gift Receipt read-only screens with empty states, source/privacy boundaries, and bilingual Page Guides for stable visible targets.
3. Add schema only where it is source-safe and append-only; do not create balances, card references, receipt references, issue/redeem/void rows, payments, sales, or print artifacts without a documented source and approved policy.
4. Preserve price-free Gift Receipt language and explicit Product Wallet isolation.
5. Keep all issue/use/redeem/void/expiry/print buttons disabled or absent until prerequisites are configured and approved; no fake demo cards or monetary defaults.

## Before closing TSK-029

- Review English/LTR and Arabic/RTL UI manually before and after changes.
- Verify authorized/no-access/direct-route behavior, no price leakage, no card/reference leakage, no overflow, and no console errors.
- Run migration/schema safety, lint, Pint, PHPStan, Blade cache, locale parity, registry/routes, build, and diff checks.
- Update `TASKS.md`, `.ai/CURRENT_TASK.md`, `.ai/CURRENT_MILESTONE.md`, `.ai/PROGRESS.md`, `.ai/TEST_RESULTS.md`, `.ai/UI_SCREENS.md`, `.ai/SESSION_SUMMARY.md`, `.ai/HANDOFF.md`, and `.ai/BLOCKERS.md` after the task.
- Create a local commit for TSK-029 only; never push.

## Explicit boundary

This task cannot claim full Gift Card/Gift Receipt issue, balance, partial/full redeem, void, expiry, concurrency, privacy, source reconciliation, or print completion until the required source, approval, numbering, eligibility, validity, holder, and format policies are configured and verified. Local/Dev readiness is the deliverable until then.

## Next task after this

TSK-030 — Implement Returns and Exchanges.
