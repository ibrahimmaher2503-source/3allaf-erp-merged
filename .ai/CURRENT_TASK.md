# Current Task — TSK-037 Standalone Retail and Party Quotations

**Date:** 2026-08-07
**Repository:** `/home/ubuntu/projects/toy-joy-phase-1-documentation`
**Status:** In Progress — Local/Dev quotation/proposal source-safe discovery/readiness boundary

## Source and dependency review

- TSK-036 Final Party Settlement/Close readiness is complete for its bounded Local/Dev slice; no quotation conversion or posting may be inferred from it.
- Required source review before implementation: `TASKS.md`, `AGENTS.md`, `.ai/` controls, `docs/35`, `docs/36`, `docs/37`, `docs/38`, quotation/price/customer/party role policies, existing document-numbering/print/share/approval/audit sources, and current retail/party UI.
- Retail and party quotation identity must remain typed and separated; quotation must not create sale, party invoice, inventory, wallet, payment, or financial effects.

## Authorized implementation slice

1. Review quotation/proposal requirements, status/expiry/terms/price authority, customer linkage, party-vs-retail type, numbering, print/share, approval, audit, and future conversion boundaries.
2. Add only source-safe Local/Dev quotation readiness/configuration surfaces for the approved values.
3. Add a guarded read-only quotation/proposal readiness screen if no safe routed surface exists.
4. Keep quote create, line mutation, approval, print/share, conversion, inventory, wallet, payment, invoice, and financial mutations disabled pending approved contracts.
5. Add bilingual Page Guide coverage with stable visible targets if a new screen is introduced.

## Before closing TSK-037

- Review English/LTR and Arabic/RTL UI before and after changes.
- Verify authorized/no-access/direct-route behavior, typed retail/party separation, no financial values or mutation, no overflow, and no console errors.
- Verify canonical Page Guide route and feature-specific first tour step.
- Run route/schema safety, lint, Pint, PHPStan, Blade cache, locale parity, registry/routes, build, and diff checks.
- Update `TASKS.md` and all relevant `.ai/` state/evidence/blocker files.
- Create a local commit for TSK-037 only; never push.

## Explicit boundary

This task cannot claim quotation/proposal issuance, approval, print/share, or conversion until required policy/source contracts are configured and verified. Local/Dev readiness is the deliverable until then.

## Next task after this

TSK-038 — Implement Dashboards and Reconciled Report Catalog.
