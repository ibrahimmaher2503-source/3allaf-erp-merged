# Current Task — TSK-038 Dashboards and Reconciled Report Catalog

**Date:** 2026-08-08
**Repository:** `/home/ubuntu/projects/toy-joy-phase-1-documentation`
**Status:** In Progress — Local/Dev dashboard/report source-safe discovery/readiness boundary

## Source and dependency review

- TSK-037 quotation/proposal Local/Dev readiness is complete; no quotation source data or report effect may be inferred from it.
- Required source review before implementation: `TASKS.md`, `AGENTS.md`, `.ai/` controls, `docs/34-reporting-dashboard-alerts-specification.md`, `docs/36-module-data-contracts.md`, `docs/37-ui-screen-specifications.md`, `docs/38-print-export-specification.md`, `docs/39-uat-and-release-gates.md`, formula/source lineage, scope permissions, pagination/export, and current dashboard/report UI.
- Reports must be source-linked, scope-filtered, reconciled, and must not expose unapproved financial values or cross-scope data.

## Authorized implementation slice

1. Review existing dashboard/report routes, source models, formula/lineage contracts, scope gates, alerts, exports, and current UI.
2. Add only source-safe Local/Dev dashboard/report readiness/configuration surfaces for approved values.
3. Keep KPI calculation, report truth, cross-scope access, PDF/Excel export, alert mutation, and financial claims disabled or explicitly pending when source contracts/data are incomplete.
4. Add bilingual Page Guide coverage with stable visible targets if a new screen is introduced.

## Before closing TSK-038

- Review English/LTR and Arabic/RTL UI before and after changes.
- Verify authorized/no-access/direct-route behavior, scope/lineage boundary, no unsupported financial values, no overflow, and no console errors.
- Verify canonical Page Guide route and feature-specific first tour step.
- Run route/schema safety, lint, Pint, PHPStan, Blade cache, locale parity, registry/routes, build, and diff checks.
- Update `TASKS.md` and all relevant `.ai/` state/evidence/blocker files.
- Create a local commit for TSK-038 only; never push.

## Explicit boundary

This task cannot claim production dashboard KPIs, reconciled reports, exports, or alert correctness until source contracts, formulas, scopes, and data are configured and verified. Local/Dev readiness is the deliverable until then.

## Next task after this

TSK-039 — Implement Operational Alerts and Exception Queue.
