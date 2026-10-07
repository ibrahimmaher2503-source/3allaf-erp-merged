# Pending Change Batch

## 2026-10-07 — Local Module 1/2 QA presentation batch (undeployed)

V0/V1 presentation portions: named validation labels and titles, localized approval errors, Settings pagination/search, saved barcode display, image-limit feedback and product metadata fields. Included in the separately authorized sensitive D3/I4 QA integration; exact per-bug commits are in `.ai/qa/module2-20261007/results.json`. One initial Module 1 asset build and one final combined build passed; final Blade compilation passed. No version bump, release artifact or deployment; production and all historical batch records are preserved. Full-suite/static debt remains documented. This entry records local completion, not closure of a production release batch.

## 2026-10-03 — Full-System Audit Final UI Batch

Owner explicitly authorized closure, integration and deployment of the remaining audit fixes. Exactly two existing-user temporary sessions were authorized for authenticated browser coverage; no grant or business-record mutation is included.

| Class | Repair | Commit | Verification/status |
|---|---|---|---|
| V0 | Username sign-in separator in ar/ar-EG/en | `b04924d` | Prior focused three-locale render passed; included in final batch |
| V1 | Safely encoded Translation Editor action arguments | `1d2a363` | Five controls × three quote/markup fixtures passed; no saves/resets |
| V1 | Canonical inventory movement select values | `9842658` | 25 options/selection retention in three locales passed |
| V1 | Idempotent line-total text updates stop observer loop | `5278c0f` | Previously stalled adjustment/Party/quotation pages rendered with exact candidate asset guard; unsaved input returned to event loop |
| V0 | Arabic audit/setup/store copy and header grid sizing | `8f80ab4` | Focused copy render passed; measured Arabic overlap 110.797px became 13.594px separation |
| I4 | One combined version/build/artifact/release | Release commit follows | `0.1.22-audit-final`; one Vite build passed; activated successfully; affected checks passed; both sessions revoked |

The af6874e UI batch activated successfully and affected live checks passed. Both approved sessions were immediately revoked; cookie/token manifest deleted. This UI batch is closed. Frozen e6d2d1e archive and all historical records are preserved unchanged.

## 2026-10-03 — PDF Output Repair After Immutable UI Freeze

L2 display-only PDF repair: shape Arabic cells in English exports, retain ASCII/numeric strings, restore page margins, explicitly size the table font and use proportional header widths; add missing display-label translations. The focused bilingual render from the preserved exported workbook passed after diagnosing the HTML-root margin and table default-font overrides. No production data/schema/grants changed and no session was recreated.

I4: `0.1.22-audit-final2` replaces the frozen UI release with a new immutable artifact. Existing e6d2d1e and af6874e artifacts remain unchanged. Reuse the already-built UI assets; no additional Vite build or broad browser pass. PDF release `24f1c5a` activated successfully; final compact smoke passed. This PDF batch is closed. Prior UI batch remains closed. No further audit application entry is pending.

## 2026-10-03 — D3 Export Confidentiality Repair (Closed)

Product document export admitted the module export permission but included sensitive Unit cost without checking the independent existing `products_categories_brands.cost_view` permission. Route-only repair `5440501` gates both header and mapped value; queries, price calculations, stored records and role/grant definitions remain unchanged. Focused success/hidden-cost/unsupported-format-boundary verification passed with synthetic output rows; 11/10 columns align, hidden cost is not accessed, XLSX omits it and both PDF shapes render. Existing normal-user route-denial evidence retained; no authenticated session recreated.

Dedicated I4 release commit `599a5084f7c1c2bb0aee5d6a67fc5df3d9dfd0be`, version `0.1.22-audit-final3`, merged/pushed via `10957d6`; immutable artifact activated and final-target compact smoke passed. No extra Vite build, schema/data/grant change or backup/test loop. Original/UI/PDF artifacts remain preserved. This batch is closed; no audit application change remains pending.

## 2026-10-01 — Current Operational Documentation Entry

The initial documentation commit is `8ccb88c7e90b12883619793d3e1dd934653eadc6` and is pushed on `docs/post-hotfix51-operational-baseline`. The following cleanup remains part of that same documentation branch; neither entry closes an application release batch.

| Classification | Short description | Commit | Affected files | Status |
|---|---|---|---|---|
| V0 | Restore historical DEC-085/DEC-086 provenance, correct Translation Editor citation to restored DEC-087, and record conditional Git identity check | Cleanup commit on `docs/post-hotfix51-operational-baseline`, message `docs: clean post-Hotfix51 operational baseline` | `.ai/DECISIONS.md`, `.ai/UI_SCREENS.md`, `AI_INDEX.md`, `.ai/CURRENT_TASK.md`, `.ai/PROGRESS.md`, `.ai/PENDING_CHANGE_BATCH.md`, `.ai/SESSION_SUMMARY.md` | Documentation corrected for branch review; commit/push authorized, merge/deployment excluded |

| Classification | Short description | Commit | Affected files | Status |
|---|---|---|---|---|
| V0 | Post-Hotfix51 main/worktree, GitHub/account/remote, branch publication, and CodeGraph operating guidance; current versus historical status clarification | Documentation commit on `docs/post-hotfix51-operational-baseline`, message `docs: update post-Hotfix51 development baseline` | `AGENTS.md`, `AI_INDEX.md`, `.ai/CURRENT_TASK.md`, `.ai/CURRENT_MILESTONE.md`, `.ai/PROGRESS.md`, `.ai/PENDING_CHANGE_BATCH.md`, `.ai/SESSION_SUMMARY.md` | Documentation updated for branch review; commit/push authorized, merge/deployment excluded |

The current recorded production application is Hotfix51 `b709193815c548bb0100a0b641171c5af7aaf314`; the post-Hotfix51 GitHub/main baseline is `4182c08` at `/home/rajeh/worktrees/rajeh-erp-main`. This documentation entry does not create a release, close an application batch, or authorize deployment. The resulting commit can be resolved from the named branch and message; no self-referential commit hash is asserted here.

## Historical Batch Records

All entries below retain their original session-time wording, including earlier active-release and undeployed labels. They are historical records, not the current production baseline or a current activation prerequisite. Historical ledger reconciliation is outside this task; no prior entry is cleared or rewritten.

## 2026-09-14 — Hotfix39 Customer Notes Batch 3

- **I4 / release candidate / undeployed:** Compact product/customer/supplier masters, incomplete-product Draft/Resume, supplier item identity, dynamic multi-identifier lookup, branch-priced product matrix reuse, opening-inventory header/keyboard grid/paste/XLSX/totals/draft/post/reversal/zero workflow, stable customer code, loyalty help, and structured supplier contacts.
- Exact baseline is Hotfix38 `45d9e78c757cd83a596b11d613475dbdc441d2c3`. Backend export routes/services, stored supplier payment terms, existing pricing contracts, tenant permissions, and historical accounting/inventory records remain intact.
- Activation must prove 000111 and 000112 are already `Ran`, execute only migration 000113, and retain its nullable backward-compatible additions if exact Hotfix38 application rollback occurs.

## 2026-09-14 — Hotfix38 Customer Notes Batch 2

- **I4 / release candidate / undeployed:** Accessible payment/tax modals; UI-hidden tax validity dates; nullable product tax assignment; branch/document continuous numbering; consolidated printer/template management with 15 document types and an accurate relevant-printer count.
- Exact baseline is Hotfix37 `0f0e7aa18d47986f6805c89b3494f4f55042e151`. The only schema change is forward-compatible migration 000112. Historical invoice tax snapshots and accounting calculations are untouched.
- Activation must run exactly migration 000112 after proving 000111 is already `Ran`. Application rollback restores exact Hotfix37 and deliberately retains the nullable schema addition.

## 2026-09-14 — Hotfix37 Customer Notes Batch 1

- **V1 / release candidate / undeployed:** Reusable accessible `!` contextual help; sidebar hover/focus/active/scroll/state/keyboard repair; duplicate-destination suppression and requested supplier/customer/product ordering; branch-screen action/timezone cleanup; company timezone and persisted default list-filter visibility in General Settings; user/role total-card removal with unchanged authorization behavior.
- Exact baseline is Hotfix36 `c5f6d57150a04d95915a72f5146a2aebd369bc83`. No migration or business-data change is included. Optimized unexecuted promotion/activation scripts are restricted to that baseline and exact rollback target.

**Status:** HOTFIX19 CORRECTIVE CANDIDATE — VERIFIED — NOT DEPLOYED
**Scope:** v0.1.22-hotfix19 POS Livewire root and extracted-release cache repair, retaining all Hotfix17/18 work
**Opened after:** operator-verified rollback to exact `v0.1.22-hotfix16`
**Source baseline:** exact Hotfix18 commit `c0fa3ed3623f7c259ba2c29109c08184bfa42eb4`
**Production baseline:** owner-confirmed: exact Hotfix16 commit `31883f5c823458d30738708ec96e1d02337f4c9b`, migration 000111 already Ran, maintenance OFF.
**Baseline disposition:** Hotfix16 remains active and is the exact Hotfix19 activation prerequisite and rollback target. It was not rechecked.

Do not clear or archive this ledger until an authorized operator confirms successful Hotfix19 production activation and smoke verification.

## Pending Changes

| Classification | Short description | Commit | Affected files | Status |
|---|---|---|---|---|
| V1/D3 | Full Hotfix17 compact POS, checkout, customer/product/scanner, order-tab and payment-card redesign | Retained in Hotfix19 | Hotfix17 POS Livewire/components/views | VERIFIED — NOT DEPLOYED |
| D3/I4 | Full Hotfix17 source-linked refund, inventory/payment/accounting/authorization/audit/idempotency and receipt workflow | Retained in Hotfix19 | Migration 000111; return action/calculator/models/components/views/routes | VERIFIED — NOT DEPLOYED |
| V1/D3 | Hotfix18 Blade/PHP compiler repairs without UI or authorization bypass | Retained in Hotfix19 | POS page/cart/checkout and purchasing-return print Blade templates | VERIFIED — NOT DEPLOYED |
| V1 | Permanent closed-state Livewire root for the retained refund wizard | Hotfix19 release commit | `resources/views/livewire/pos/refund-wizard.blade.php` | VERIFIED — NOT DEPLOYED |
| I4 | Post-extraction bootstrap cache creation, recursive `rajeh:rajeh` ownership, mode `0775`, pre/post cache-generation writability, application-user Artisan, already-Ran 000111 preservation and exact Hotfix16 rollback | Hotfix19 operator scripts | Promotion and activation scripts | VERIFIED — ACTIVATION PREFLIGHT DEFERRED |

Production access, deployment, backup execution, migration execution, active-symlink modification, service operation, browser testing, tag, push, and remote contact are outside candidate preparation and remain unexecuted.
## 2026-09-12 — Egyptian Arabic locale completion batch

- **V0 / completed locally / undeployed:** Rewrote all 6,115 `ar-EG` JSON values and 244 PHP locale leaves, added contextual Egyptian render wording and human-readable source labels, and preserved `ar`/`en`. Commit recorded after final verification. No deployment or packaging.

## 2026-09-13 — Hotfix36 Egyptian Arabic quality-repair batch

- **V0 / release candidate / undeployed:** Integrated the direct-child localization repair onto exact Hotfix35, retaining all 6,115 JSON keys, 244 PHP leaves, unchanged `ar`/`en`, and unchanged functional boundaries. Offline package and fail-closed exact-Hotfix35 operator workflow prepared; no production or operator action executed.

## 2026-10-07 — BUG-15 audit response isolation (undeployed)

- **Class:** V1. **Repair:** Isolated audit filters/table/pagination and audit detail modal into native Livewire islands; preserved the existing access and per-record authorization checks.
- **Commit:** a667949c7201a63982ed63ca1e17b3c91e53600b (`fix/bug-15-audit-log-islands`). **Files:** `resources/views/platform/system/audit-log.blade.php`, `tests/Feature/Module1AuditPresentationTest.php`, `.ai/TEST_RESULTS.md`, `.ai/SESSION_SUMMARY.md`, `.ai/PENDING_CHANGE_BATCH.md`.
- **Status:** Focused local regression passed; awaiting lead approval before branch push. No release or deployment.
