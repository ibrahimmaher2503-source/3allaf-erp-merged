# Current Task — TSK-031 Party Bookings and Working Invoices

**Date:** 2026-08-07
**Repository:** `/home/ubuntu/projects/toy-joy-phase-1-documentation`
**Status:** In Progress — Local/Dev party-booking/working-invoice source-safe discovery/readiness boundary

## Source and dependency review

- TSK-030 returns/exchanges readiness is complete for its bounded Local/Dev slice; no party workflow may create customer, child, wallet, payment, return, or financial mutation implicitly.
- Required source review before implementation: `TASKS.md`, `AGENTS.md`, `.ai/` controls, `docs/28-party-operations-policy.md`, customer/child/privacy contracts, store/schedule/timezone contracts, invoice/numbering/print contracts, and existing authorization/routes/views.
- Party bookings must remain separate from retail sales and supplier returns. Do not invent party stores, services/packages, schedules, cancellation windows, responsibilities, prices, or master data.

## Authorized implementation slice

1. Review existing party/customer/child/store/schedule/invoice/authorization/print code and document what is present versus missing.
2. Add only source-safe Local/Dev readiness/configuration surfaces for party store mapping, service/package catalog, schedule/timezone, contact/child/privacy, cancellation, responsibility, price, and working-invoice freeze values.
3. Add a guarded read-only Party Booking/Working Invoice readiness screen if no safe routed surface exists; show empty/pending states and preserve party-only boundaries.
4. Keep booking creation, customer/child mutation, calendar reservation, invoice mutation, payment, and final closure disabled until policy/source approval.
5. Add bilingual Page Guide coverage with stable visible targets if a new screen is introduced.

## Before closing TSK-031

- Review English/LTR and Arabic/RTL UI manually before and after changes.
- Verify authorized/no-access/direct-route behavior, party-only scope, privacy/no-price boundaries, no mutation controls, no overflow, and no console errors.
- Run route/schema safety, lint, Pint, PHPStan, Blade cache, locale parity, registry/routes, build, and diff checks.
- Update `TASKS.md`, `.ai/CURRENT_TASK.md`, `.ai/CURRENT_MILESTONE.md`, `.ai/PROGRESS.md`, `.ai/TEST_RESULTS.md`, `.ai/UI_SCREENS.md`, `.ai/SESSION_SUMMARY.md`, `.ai/HANDOFF.md`, and `.ai/BLOCKERS.md` after the task.
- Create a local commit for TSK-031 only; never push.

## Explicit boundary

This task cannot claim full party booking/calendar, customer/child creation, schedule conflict handling, working-invoice editing/freeze, pricing, cancellation, responsibility, payment, or final closure until required policy/source contracts are configured and verified. Local/Dev readiness is the deliverable until then.

## Next task after this

TSK-032 — Implement Party Payments on Account and Party Balance.
