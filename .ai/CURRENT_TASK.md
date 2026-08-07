# Current Task — TSK-032 Party Payments on Account and Party Balance

**Date:** 2026-08-07
**Repository:** `/home/ubuntu/projects/toy-joy-phase-1-documentation`
**Status:** In Progress — Local/Dev party-payment/Party Wallet source-safe discovery/readiness boundary

## Source and dependency review

- TSK-031 Party-only booking/working-invoice readiness is complete for its bounded Local/Dev slice; no payment or balance mutation may be inferred from it.
- Required source review before implementation: `TASKS.md`, `AGENTS.md`, `.ai/` controls, `docs/28-party-operations-policy.md`, DEC-019, payment/receipt/evidence/document-sequence contracts, Party Wallet isolation, authorization/SoD/audit, and existing sales/purchasing/payment UI.
- Party payments must remain separate from retail payments and Product Wallet. Exact receipt wording is fixed by policy, but payment/deposit, evidence, overpayment, Party Wallet, legal/financial values remain configurable or pending.

## Authorized implementation slice

1. Review existing payment, receipt, evidence, numbering, wallet, authorization, audit, and relevant UI contracts; distinguish retail payment paths from Party-only requirements.
2. Add only source-safe Local/Dev readiness/configuration surfaces for party payment methods, deposit/payment-on-account, evidence, duplicate/idempotency, overpayment, receipt wording/numbering, balance visibility, and Party Wallet source rules.
3. Add a guarded read-only Party Payments/Balance readiness screen if no safe routed surface exists; show pending/empty state and preserve Product Wallet isolation.
4. Keep payment posting, receipt creation, balance calculation, Party Wallet mutation, overpayment, reversal, and financial settlement disabled pending approved contracts.
5. Add bilingual Page Guide coverage with stable visible targets if a new screen is introduced.

## Before closing TSK-032

- Review English/LTR and Arabic/RTL UI manually before and after changes.
- Verify authorized/no-access/direct-route behavior, party-only scope, Product Wallet isolation, no payment amounts/secrets, no mutation controls, no overflow, and no console errors.
- Run route/schema safety, lint, Pint, PHPStan, Blade cache, locale parity, registry/routes, build, and diff checks.
- Update all relevant `TASKS.md` and `.ai/` state/evidence/blocker files after the task.
- Create a local commit for TSK-032 only; never push.

## Explicit boundary

This task cannot claim party payment posting, receipt numbering, balance calculation, Party Wallet entry, overpayment handling, reversal, or financial settlement until required policy/source contracts are configured and verified. Local/Dev readiness is the deliverable until then.

## Next task after this

TSK-033 — Implement Party Operating Orders and Consumable Movements.
