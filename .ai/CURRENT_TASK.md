# Current Task — M50B.1 Sales Workspace and Internal Page Foundation

**Date:** 2026-08-27
**Branch:** `feature/m50b1-sales-workspace`
**Baseline:** published `v0.1.5` commit `4ec48f8f6a0f0689e960e239b9ac60502143a963`

## Status

- M50B.1 implements the reusable internal page/filter/table/pagination foundation and complete Sales & POS workspace presentation.
- Sales Overview uses bounded permission-scoped real queries; invoice, customer, and shift pages share responsive list patterns while POS remains dedicated.
- The desktop sidebar regression is corrected through a versioned validated shell preference, expanded safe default, deterministic icon rail, and mobile-only Flux drawer behavior.

- M50A.1 corrects the visual hierarchy, header completeness, dashboard density, navigation polish, responsive behavior, accessibility, and Arabic typography of the accepted M50A engineering foundation.
- The shell retains centralized permission-filtered navigation, real routes, existing permissions, a dedicated POS archetype, and bounded visible-store operational data.
- The global dashboard is denser and role-aware; Inventory is the representative real-data module dashboard; the centralized module-dashboard registry defines the M50B contract without inventing routes or data.
- Arabic uses the vendored local Cairo Variable Arabic and Latin WOFF2 assets across the complete Arabic shell and content. English retains the Latin stack.

## Verification boundary

- One locked Vite production build completed after all source corrections; the manifest and all referenced assets were verified.
- Local-only Playwright evidence covers ten Arabic/English desktop, tablet, mobile, collapsed-sidebar, drawer, dashboard, Inventory, Sales & POS, and header states.
- Evidence records zero external requests, zero horizontal document overflow, zero serious/critical Axe findings, one footer, and no unlabeled icon controls.
- Arabic proof includes `lang=ar`, `dir=rtl`, successful local Cairo WOFF2 responses, `document.fonts.check()` at weights 400/600/700, Cairo-first computed families, and Arabic canvas metrics distinct from Arial fallback.
- No automated application suite, database, migration, production, deployment, tag, remote, or backend mutation change occurred.
