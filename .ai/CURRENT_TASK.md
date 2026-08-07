# Current Task — TSK-034 Rental Asset Master, Calendar, Reservation, Checkout, and Return

**Date:** 2026-08-07
**Repository:** `/home/ubuntu/projects/toy-joy-phase-1-documentation`
**Status:** In Progress — Local/Dev rental asset/calendar source-safe discovery/readiness boundary

## Source and dependency review

- TSK-033 Party operating-order/consumable readiness is complete for its bounded Local/Dev slice; no asset, reservation, stock, or operating mutation may be inferred from it.
- Required source review before implementation: `TASKS.md`, `AGENTS.md`, `.ai/` controls, `docs/29-rental-asset-policy.md`, asset master/register, category/location, calendar/interval/buffer, reservation, checkout/return, condition/evidence, approval/SoD/audit, idempotency, document-sequence, print, and current inventory/party UI.
- Rental assets must remain unique and separate from consumables and retail products; no double booking or direct stock/balance edit may be enabled without approved contracts.

## Authorized implementation slice

1. Review existing inventory, store, product, party, calendar/date-time, attachment, approval, audit, and print contracts.
2. Add only source-safe Local/Dev readiness/configuration surfaces for asset identity/category/location, availability, interval/buffer, reservation, checkout/return, conditions/evidence, maintenance state, approval, idempotency, and print.
3. Add a guarded read-only Rental Asset/Calendar readiness screen if no safe routed surface exists.
4. Keep asset creation/editing, reservation, checkout, return, condition posting, stock/balance mutation, and completion disabled pending approved contracts.
5. Add bilingual Page Guide coverage with stable visible targets if a new screen is introduced.

## Before closing TSK-034

- Review English/LTR and Arabic/RTL UI before and after changes.
- Verify authorized/no-access/direct-route behavior, asset/consumable separation, no reservation/checkout/return mutation, no sensitive evidence, no overflow, and no console errors.
- Run route/schema safety, lint, Pint, PHPStan, Blade cache, locale parity, registry/routes, build, and diff checks.
- Update all relevant `TASKS.md` and `.ai/` state/evidence/blocker files after the task.
- Create a local commit for TSK-034 only; never push.

## Explicit boundary

This task cannot claim rental asset master, calendar reservation, checkout, return, condition, maintenance, or financial behavior until required policy/source contracts are configured and verified. Local/Dev readiness is the deliverable until then.

## Next task after this

TSK-035 — Implement Asset Damage, Loss, Maintenance, and Depreciation Review.
