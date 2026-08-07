# Current Task — TSK-036 Final Party Settlement, Invoice, Receipt, Wallet, and Close Controls

**Date:** 2026-08-07
**Repository:** `/home/ubuntu/projects/toy-joy-phase-1-documentation`
**Status:** In Progress — Local/Dev final settlement/close source-safe discovery/readiness boundary

## Source and dependency review

- TSK-035 Asset Damage/Loss/Maintenance/Depreciation readiness is complete for its bounded Local/Dev slice; no final settlement, invoice, receipt, Party Wallet, credit, or close mutation may be inferred from it.
- Required source review before implementation: `TASKS.md`, `AGENTS.md`, `.ai/` controls, `docs/28-party-operations-policy.md`, `docs/29-rental-asset-policy.md`, final-close/readiness, working/final invoice, payment-on-account, receipt, Party Wallet, credit/overpayment, approval/SoD, audit, idempotency, document numbering, print, and current party/payment/wallet UI.
- Party Wallet must remain separate from Product Wallet; final receipt terminology and final financial values remain owner-configurable/pending.

## Authorized implementation slice

1. Review existing party/payment/wallet/invoice/receipt/numbering/approval/audit contracts and current read-only screens.
2. Add only source-safe Local/Dev readiness/configuration surfaces for final readiness checks, invoice freeze/close, payment reconciliation, credit/overpayment, Party Wallet settlement, receipt, approval, idempotency, audit, and print.
3. Add a guarded read-only final-close readiness screen if no safe routed surface exists.
4. Keep final invoice posting, receipt generation, wallet entry, credit/overpayment calculation, settlement, and close mutations disabled pending approved contracts.
5. Add bilingual Page Guide coverage with stable visible targets if a new screen is introduced.

## Before closing TSK-036

- Review English/LTR and Arabic/RTL UI before and after changes.
- Verify authorized/no-access/direct-route behavior, Party/Product Wallet separation, no financial values or mutation, no overflow, and no console errors.
- Verify the canonical Page Guide route and feature-specific first tour step before attempting the full tour.
- Run route/schema safety, lint, Pint, PHPStan, Blade cache, locale parity, registry/routes, build, and diff checks.
- Update all relevant `TASKS.md` and `.ai/` state/evidence/blocker files after the task.
- Create a local commit for TSK-036 only; never push.

## Explicit boundary

This task cannot claim final settlement, final invoice, receipt, Party Wallet settlement, credit, overpayment, or close behavior until required policy/source contracts are configured and verified. Local/Dev readiness is the deliverable until then.

## Next task after this

TSK-037 — Implement Quotations and Proposals.
