# Current Task — TSK-033 Party Operating Orders and Consumable Movements

**Date:** 2026-08-07
**Repository:** `/home/ubuntu/projects/toy-joy-phase-1-documentation`
**Status:** In Progress — Local/Dev party operating-order/consumable source-safe discovery/readiness boundary

## Source and dependency review

- TSK-032 Party payment/balance readiness is complete for its bounded Local/Dev slice; no posting, balance, receipt, or wallet mutation may be inferred from it.
- Required source review before implementation: `TASKS.md`, `AGENTS.md`, `.ai/` controls, `docs/28-party-operations-policy.md`, party-store mapping, consumable/UOM, stock, issue/return, approval/SoD/audit, idempotency, document-sequence, print, and existing inventory UI.
- Party operating orders and consumables must remain separate from retail sales/products and must not create stock movement without approved source contracts.

## Authorized implementation slice

1. Review existing inventory, transfer, receiving, purchase, stock-balance, approval, audit, and relevant UI contracts.
2. Add only source-safe Local/Dev readiness/configuration surfaces for operating order lifecycle, party-store scope, consumables/UOM, issue/actual/return, controlled additions/removals, stock reconciliation, approvals, idempotency, and print.
3. Add a guarded read-only Party Operating Orders/Consumables readiness screen if no safe routed surface exists.
4. Keep order release, consumable issue/return, stock movement, balance edit, and completion mutations disabled pending approved contracts.
5. Add bilingual Page Guide coverage with stable visible targets if a new screen is introduced.

## Before closing TSK-033

- Review English/LTR and Arabic/RTL UI before and after changes.
- Verify authorized/no-access/direct-route behavior, party-only scope, retail/product separation, no stock quantities or mutations, no overflow, and no console errors.
- Run route/schema safety, lint, Pint, PHPStan, Blade cache, locale parity, registry/routes, build, and diff checks.
- Update all relevant `TASKS.md` and `.ai/` state/evidence/blocker files after the task.
- Create a local commit for TSK-033 only; never push.

## Explicit boundary

This task cannot claim operating-order release, consumable issue/return, stock movement, balance reconciliation, print, or final completion until required policy/source contracts are configured and verified. Local/Dev readiness is the deliverable until then.

## Next task after this

TSK-034 — Implement Rental Asset Master, Calendar, Reservation, Checkout, and Return.
