# UI/UX Release Progress

**Updated:** 2026-08-27
**Branch:** `feature/m50a-shell-dashboard`
**Baseline:** published `v0.1.4`

| Milestone | Status |
|---|---|
| M50A — global shell, navigation, operational dashboard | Engineering foundation committed |
| M50A.1 — visual hierarchy, density, navigation, responsive and Arabic correction | Complete in development |
| M50B.1 — internal-page foundation and Sales & POS workspace | Complete in development |

M50B.1 adds reusable active-filter chips, bounded pagination, responsive resource rows, a scoped Sales Overview, refined invoice/customer/shift pages, consistent POS entry navigation, and the v0.1.5 desktop-sidebar recovery fix.

M50A.1 changed shared presentation, centralized module-dashboard specification, and bounded read-only dashboard composition only. The operational dashboard and representative Inventory overview use existing authorized data; no route, permission, mutation workflow, schema, migration, production runtime, or deployment state changed. The single locked build passed, and genuine local rendered evidence is stored outside Git in the approved `m50a1-evidence-20260827` directory.
