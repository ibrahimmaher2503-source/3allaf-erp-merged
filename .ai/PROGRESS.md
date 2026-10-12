# 2026-10-12 — Product Card cost visibility hotfix

D3 narrow correction on `hotfix/product-card-cost-visibility`, source `90e60bc`: the live Product Card Blade already had the field, but the missing `products_categories_brands.cost_view` permission row hid it. Canonical active System Administrator role now passes only this cost visibility/edit check, and the authorization seeder registers the permission for future ordinary grants. Focused isolated MariaDB PASS 1/13 with the row inactive; authenticated Arabic/English browser smoke PASS, including save/reload 10, blank edit preservation, and ordinary denial. PHP/JS syntax, Pint and diff hygiene PASS. No Fast Gate by owner instruction, migration, production mutation, merge or deploy. Commit/push follow.

## Preserved previous state

# 2026-10-12 — Product Card cost and purchasing defaults

D3 batch on `feature/product-initial-cost-purchasing-defaults`, source `031815e`: canonical nullable Product Card cost now supplies the shared PO/invoice default; blank costs stay null/blank, drafts leave current cost untouched, and approved invoices retain the sole purchasing current-cost update. Blank supplier/local code preserves existing data, including staged import. Arabic/English cost guidance, quick-product optional cost, and action-level purchasing lookup guards are included. Focused isolated MariaDB PASS 21/179 plus import 1/11; bilingual browser/readback PASS; syntax, JSON, Pint and controlled core Larastan PASS. Staged import retains pre-existing Larastan diagnostics outside changed lines. First Fast Gate could not migrate a second disposable MariaDB due `/tmp` capacity; stale cache cleanup enabled retry PASS 8/8. No migration, main merge, production access/deployment or UAT advancement. Commit and task-branch push follow.

## Preserved previous state

# 2026-10-12 — Final purchase-invoice hotfix scope verification

Read-only CodeGraph/ast-grep caller review found one shared approval-record edge: source type alone allowed a hypothetical non-approve purchase-invoice record to inherit the role exception. The corrected shared policy/action/audit condition requires both invoice source and requested action `approve`; PO, returns, generic workflows and their creator guards remain unchanged. Focused isolated MariaDB PASS 3/38, scoped Larastan and syntax PASS. No Fast Gate rerun, merge or deployment. Corrected branch commit/push follow.

## Preserved previous state

# 2026-10-12 — Purchase-invoice approval locale and administrator hotfix

D3 hotfix completed on `hotfix/purchase-approval-locale-admin` from source `7960c7a`. The invoice approval and receiving screen now has complete `ar-EG`/`ar` translation coverage; English remains LTR. The canonical active System Administrator role can approve its own purchase invoice via the existing permission, scope, transaction, and audit paths. Ordinary creator separation, optional distribution, stock and price posting, shortages, retry idempotency, and other purchasing workflows remain intact. Focused isolated MariaDB PASS 14/121; Arabic/English browser smoke PASS; single Fast QA PASS 8/8 (71/448). No migration, production access, main merge, deployment, or business gate advancement. Commit and branch push follow this status update.

## Preserved previous state

# 2026-10-07 - Owner-authorized integration of all GitHub branches

The owner now explicitly authorizes main integration of all six GitHub module branches and requires every branch to remain. All fetched tips are ancestors of the verified combined history. Integration merges: `cc0fb64` (Module 3), `d713e2e` (Module 9), `a2ec03a` (Module 7); the Module 5 tip already includes Modules 1/2. Focused PASS 52 tests / 500 assertions; 80 changed runtime PHP syntax checks, locale JSON, runtime diff hygiene, conflict-marker checks, Pint and one build passed. Only inherited Markdown hard-break/EOF whitespace was reported by the broad documentation diff check; historical reports were preserved. Existing policy/data/server blockers are retained. Main publication follows this verified closure; no production release or business gate advances.

## Preserved previous state

# 2026-10-07 — Fast Module 1 QA closure

BUG-01–16: 10 fixes approved and pushed separately, 4 already fixed/not reproduced locally, 2 blocked by policy or server configuration. Original clean base `7c3cf6d` preserved; integration code `65c9c42` published on `fix/module1-qa-fast-integration`. Overlap checks: 21 tests/164 assertions pass. Single final suite: 276 tests/6,424 assertions, 243 pass/32 pre-existing failures/1 FriBidi error; all 12 new cases pass (A=0/B=32/C=0/D=1/E=0). Narrow post-suite BUG-04 completion: 1 test/16 assertions pass. One build, changed-PHP syntax, locale, Pint and scoped static-delta checks complete; static delta 0 added with 34 existing diagnostics in six affected source files. No second full suite or review team, browser check, main change, production access/deployment or business gate advancement. Evidence: `.ai/qa/module1-fast-20261007/results.json`.

## Preserved previous state

# 2026-10-07 — Module 1 + Module 2 local QA closure

All ten Module 1 review lanes closed and accepted fixes integrated. Original 31 findings remain individually dispositioned in `.ai/qa/module1-20261007/FINAL_REPORT.md`; hardware/mail/remote and genuine policy limits remain explicit. Module 2: BUG-105/231/232/233/234/235 fixed with focused checks; BUG-106 persisted all 42 active permissions in the real isolated browser and did not reproduce locally. All seven task branches pushed/remote-SHA verified. Combined suite 264 tests/6,257 assertions: 228 pass, 34 fail, 2 errors; all new repair tests passed. Two changed legacy source-string assertions remain failing, not silently rewritten. Static delta zero added, final1,403; build/Blade/syntax/locale checks pass. Integration closure commit3e13a57 is pushed; no main merge or production deployment. Business gates unchanged.

## Preserved previous state

# 2026-10-07 — Module 9 cash/shifts QA fast batch

M9-01/02/03 `bfff4ed`, M9-04/05 display `47fcdef`, M9-06 `849e7d8`, M9-08 `52390ef` complete on `fix/module9-cash-shifts-qa-fast`. Focused 6/69, changed syntax/JSON, Pint and one build PASS; isolated XAMPP schema only. DEC-100 acceptance-reason requirement pending owner decision; M9-07 tooling only. Main unchanged; no production deployment, browser/UAT or Phase Gate advancement.

# 2026-10-07 — Module 7 Part 1 local Returns QA

Local D3 fixes and previously blocked P0-01..P0-10 completed on `fix/module7-returns-p1-fast`; 28 focused tests / 223 assertions PASS. Sellable versus damaged/quarantine posting, cumulative quantities, completion replay, maker-checker, Gift Receipt use/privacy, exchange settlement, search, locale and historical identity verified against dedicated XAMPP MySQL. Minimum fixture dataset retained locally. Runtime branch pushed; final closure commit follows. M7-06 inspection rejection/attachments remains Requires Owner Decision (DEC-103). Main unchanged; no production access, merge, deployment or milestone advancement.

# 2026-10-07 — BUG-121 local purchase cost fix

D3 implementation complete on isolated `hotfix/bug-121`: upgrade missing granular purchase-invoice permissions without reseeding roles or overwriting disabled entries, expose explicit cost-grant labels, and explain the disabled field. Focused MySQL success/denial/replay checks passed; draft 2 × 37.50 = 75.00 preserves sale price/WAC and creates no stock movement. Syntax/JSON/Vite passed. Local commit requested; production validation, migration application, and deployment remain outside scope. Main and the ongoing Module 7 checkout were not edited; no milestone advancement.

# 2026-10-05 — Permanent optional Product Card cost

Source fix and focused A–F verification complete; shared optional rule replaces final-save, action and import requirements. Saved costs and independent accounting snapshots preserved; costless editor save/create and browser DB readback PASS. Three focused tests/24 assertions PASS; static delta zero new (9 existing findings). DEC-091/evidence record the named native MariaDB authorization. Runtime `1d27f7b` committed/pushed and cleanly integrated/pushed to main; production `0.1.22-cost-optional` active, compact smoke PASS. Prior release preserved for rollback; disposable DB, credentials and sessions removed. Report-only closure publication follows without another deployment. No business milestone or full Gate advancement.

# 2026-10-04 — Phase 2 end-to-end runtime closure

Main runtime merge`3821af553daca932e36c8a49ab78f911e1cf2379` pushed; three fixes deployed in0.1.22-qa-phase2. Compact enforced-read-only smoke PASS, no failed jobs/new critical errors; exact final5 rollback preserved. Failed-helper tail alone resumed after automatic recovery. Final MSI 79.34 / 94.14 raw/reviewed,113/27 raw / retained survivors, 86 unchanged exclusions; new precision testsPASS with no new kills. Thirteen races and3,884 matrices preserved;54 new rational cases PASS. Actual GitHub CI unverified solely for external access; latest owner instruction permits integration. No business milestone/UAT advancement. Closure evidence:`.ai/qa/phase2-evidence/final-closure.json`.

# 2026-10-04 — QA Hardening Phase 2 focused progress

- Original 191 survivors classified; exact financial contracts and 3,884 deterministic generated calculation cases added.
- Eleven isolated financial/concurrency cases, thirteen contention races and five new invariants passed; minimal confirmed tax/allocation/deadlock runtime repairs remain undeployed.
- Final scoped mutation 434/547 kills after one affected-only replay: raw MSI 79.34%, 113 visible survivors; reviewed MSI 94.14% after 86 explicit equivalents/unreachable guards, 27 retained survivors.
- Controlled touched-code static baseline passed with no new errors; focused eight-file Semgrep zero findings. Previous broad audit/browser/restore results preserved.
- Fast PASS 104s and Release PASS WITH WARNINGS (0s preserved report assembly); actual CI/publication tracked in `.ai/qa/PHASE2_CHECKPOINT.md`; no business milestone or UAT advancement.

# 2026-10-04 — Permanent QA Gate continuation

Owner-authorized I4 QA tooling task on `feature/rajeh-qa-gate`, source `1ab5ab534ea76d60b2da2e732fcb6992b3fd7985`. Resumed the preserved checkpoint; completed Fast/Release tooling and only failed/unfinished validation. Fast PASS (15s final resumed invocation); Release PASS WITH WARNINGS (24s final resumed invocation), mutation MSI 53.41% with 191 survivors. Real disposable MariaDB accounting/inventory chain, populated restore, outputs, Semgrep and Admin/User/Cashier checks passed. See `.ai/qa/RESUME_CHECKPOINT.md` and `docs/qa/RAJEH-QA-GATE.md`.

Current named authorization includes the focused QA PHPUnit/Playwright/Semgrep/Infection scope and preserved native disposable MariaDB workflow; historical/general manual-only and XAMPP direction remain unchanged outside this gate. Completed tooling commit `704d82d` was pushed, fast-forward merged into main and pushed under explicit owner authorization. Retained private QA database/session/credential runtime was removed after preserving report/log evidence. Production final5 `ff75916` remains unchanged; no deployment, runtime application repair, broad audit, repeated passing coverage or milestone/Phase Gate advancement is claimed. Physical device/offline/client UAT and mutation/static debt remain limitations.

## Preserved previous state

## 2026-10-03 — Final Full-System Audit Closure

**Status:** Remaining authorized browser/output audit closed; confirmed defects repaired and deployed. **Readiness:** Operational application verified; unconditional trading/client handover NOT approved pending cashier/shift/opening configuration, populated financial/inventory UAT in disposable XAMPP MySQL/MariaDB, device/transaction-print/offline acceptance and next scheduled backup evidence.

**Application:** `599a5084f7c1c2bb0aee5d6a67fc5df3d9dfd0be`, tree `51129f22539a3ceebc14e6b1d0171896f9131886`, version `0.1.22-audit-final3`; `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.22-audit-final3-24f1c5a`. UI/source fixes merged via `a4d2604`, PDF repair via `66605f1` and cost disclosure repair via `10957d6`, and pushed to main; later report-only closure does not rebuild the artifact.

**Coverage:** 386 unique authenticated Arabic/English desktop/mobile renders; 23 safe-control assertions; shipped UI affected checks; focused independent cost-view export confidentiality checks; 25 output requests and 42 final continuation assertions; XLSX/XML/PDF integrity; isolated repaired bilingual PDF visual rendering. Completed initial source/CodeGraph/database/backup coverage remains preserved. No suite, broad rescan, CodeGraph reinitialization/sync or repeated passing browser matrix. One Vite build; PDF artifact reused it.

**Access:** Exactly two approved sessions for existing administrator ID 1 and normal user ID 3 were created without password-hash inspection or password/identity/grant changes, then immediately revoked after authenticated coverage. Private cookies/tokens deleted; no further session created. No production business fixtures/transactions; output GETs produced their intended operational audit events.

**Release:** New immutable PDF artifact SHA-256 `35eef98261e033575f4662a99a070b5d0b0ff03fce6654d5b17bfac3905f9286`; manifest `a0c96bbd206480b75db8e05c8b8bc5cfeded11af82b8583f3dd3b8d570c3aab7`; 14,816 files verified. Original e6d2d1e and af6874e archives preserved. Journal `/var/lib/toyjoy/audit-final3-599a508`; exact rollback target 24f1c5a. Final-root cache/autoload checks and the single final compact smoke passed, maintenance off, services active, failed jobs zero, cron/schedules present, `/up`/`/login` 200 and unauthenticated `/admin` 302.

**Report:** `docs/audits/RAJEH-FULL-SYSTEM-AUDIT-2026-10-03.md`; evidence `.ai/evidence/full-system-audit-2026-10-03/`. No milestone, phase gate, UAT or device sign-off advanced. Remaining acceptance constraints are documented, not represented as passing checks.

**Late D3 repair:** Confirmed product XLSX/PDF cost disclosure when export is granted without the independent cost-view permission. Server output now omits the cost header/value unless the existing cost-view gate allows it. Focused visible/hidden-cost shapes, XLSX content and PDF generation passed; no roles, permissions, grants, identities or sessions changed. Dedicated final3 artifact activated; prior release receipts are preserved. The final target compact smoke passed once after this code change.

## 2026-10-03 — Preserved Full-System Audit Continuation

Full audit remains incomplete. Source baseline `49f429420b576bb08f05ea757cf44aca9f8e9140`; existing `audit/full-system-client-readiness` worktree preserved. Observed application e6d2d1e is already active; fixed generated runtime caches that retained renamed staging paths, then passed six-path checks and only the previously failing backup regression. Missing deployment smoke passed once. New public login visual pass found a localized username-separator defect; source repair `b04924d4c973580249aa999adab34078935cefb3` is verified and pushed, release pending. Broad source/CodeGraph/database scans and passing historical checks were not repeated. Authenticated Admin/User coverage awaits explicit approval for exactly two temporary sessions after automatic review rejected the broader all-user/password-hash helper. See `.ai/FULL_SYSTEM_AUDIT_CHECKPOINT.md` and the comprehensive report. No phase gate or handover/UAT approval claimed.

## 2026-10-01 — Operational Documentation Pre-Merge Cleanup

- Confirmed original DEC-085/DEC-086 entries in `.ai/DECISIONS.md` at `47da09c15b3d568bb2880a3e8c6d00900fe4c63d` and their removal at `c68f87c20d79085705d3310f1e2c233679dad8e3`. Restored the original text with explicit historical provenance and scope; no historical execution permission is revived.
- Corrected the Translation Editor's erroneous DEC-085 reference to its actual DEC-087 runtime-translation decision, restored from the same source. No screen behavior or requirement changed.
- Confirmed `Ibrahim <ibrahim.maher.2503@gmail.com>` matches the current main and Hotfix51 authors. The owner permits author changes only if clearly stale/wrong; account-name difference alone does not prove that condition. Git identity, remote, and SSH authentication remain unchanged.
- Prior documentation commit `8ccb88c7e90b12883619793d3e1dd934653eadc6` is pushed on `docs/post-hotfix51-operational-baseline`. Cleanup uses the same branch/worktree for one documentation review and a new commit/push. Main remains `4182c08`, and recorded production remains Hotfix51 `b709193815c548bb0100a0b641171c5af7aaf314`. Merge, deployment, and application changes are excluded.

## 2026-10-01 — Post-Hotfix51 Operational Documentation

- V0 operational documentation updated on `docs/post-hotfix51-operational-baseline` in dedicated worktree `/home/rajeh/worktrees/rajeh-erp-post-hotfix51-operational-baseline`, created from main `4182c08e108da7cb3ed1a119470edb65a3681392` (`4182c08`). Commit/push are authorized handoff steps; no merge or deployment is included.
- Recorded production remains Hotfix51 application commit `b709193815c548bb0100a0b641171c5af7aaf314` at `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.22-hotfix51-b709193815c5-r6`; this is carried forward from deployment evidence, without new production access.
- Future work starts from current protected `main` in `IS-Intelligent-Solutions/rajeh-erp`, main worktree `/home/rajeh/worktrees/rajeh-erp-main`, using a new dedicated branch/worktree. The old hotfix50 worktree remains a historical Hotfix51 reference.
- `AGENTS.md` records the `rajeh_ahmed` account, SSH remote, branch publication rules, unchanged deployment boundary, and CodeGraph 1.6.0 / ast-grep 0.45.3 analysis and exclusion guidance. No index or tool configuration is changed by this task.
- Historical Hotfix51 evidence and all earlier progress entries remain preserved. No application or business requirement, milestone, Phase Gate, database, service, or deployment workflow changed.

## Historical Progress Records

The dated records below describe their session-time state. Earlier active/pending/deployment statements are not the current operational baseline.

## 2026-09-30 — Hotfix51 product/supplier finalization deployed

- Started from exact active Hotfix50 application commit `0ff36dd24542d8378f6e9c24784e992b1d56ad74` and preserved unrelated worktrees/history.
- Added the canonical/numeric supplier-code resolver, explicit final-versus-draft Product Card persistence, existing-draft identity-barcode completion, separate international-barcode retention, authorized dependency-safe supplier deletion/deactivation presentation, and contextual product-form help.
- Focused rollback verification passed 50 assertions in isolated restored MariaDB `toyjoy_hf48_restore_368d44f2acea961a9a`. It covered exact `0002-000001` and `0123-000001` results, invalid-final no-draft behavior, same-ID draft completion/no duplicate, both local/international barcodes, authorized unused deletion/audit, linked restriction/deactivation/history, and unauthorized denial. No real supplier was deleted or deactivated.
- Deployed application commit `b709193815c548bb0100a0b641171c5af7aaf314` through a fresh encrypted backup, isolated restore/reconciliation, cache preparation, atomic activation, PHP-FPM reload, runtime restoration, read-only bilingual affected-page checks, and health checks. Active release: `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.22-hotfix51-b709193815c5-r6`; journal: `/var/lib/toyjoy/hotfix51-deployment/b709193815c5-20260930.4qsXOu`; recovery archive SHA-256: `536f3a2998cb0abc376d17e24dd5991ec373ec26050ba343abde1f036a858213`.
- Migrations and dependencies are unchanged, Hotfix50 assets were reused, no production product/supplier test record was created, and no in-scope blocker remains.

## 2026-09-29 — Hotfix50 permission labels and section selection

- Started from actual active Hotfix49 commit `5c25390599f8d9e431599f0695881123e0f27349`, not the older development checkout.
- Added one shared Arabic/English presentation catalog for all 412 current permissions and 34 business sections. The role-permissions UI no longer shows raw permission keys, and localized names/descriptions/search apply to ar, ar-EG, and en.
- Added per-section `تحديد الكل` / `All` controls with unchecked, indeterminate, and checked states. Each control changes the complete active assignable section set only, keeps protected permissions unchanged, and never persists before the existing Save action.
- Focused isolated browser verification passed in locked MariaDB `toyjoy_hf50_verify_2b798b36b23f`, including section isolation, partial state, Save/reload, full label coverage, RTL/Cairo, and English LTR. Evidence: `/var/lib/toyjoy/hotfix50-validation/2b798b36b23f`.
- Deployed application commit `0ff36dd24542d8378f6e9c24784e992b1d56ad74` through a fresh encrypted backup, isolated full restore/reconciliation, cache preparation, atomic activation, runtime restoration, corrected visible-text live Arabic/English page check, and HTTP health checks. Recovery journal: `/var/lib/toyjoy/hotfix50-deployment/0ff36dd24542-20260929.v4d2J5`; archive SHA-256 `113368a0d3c77c3dab45058a9fe72dd21643b0e711a40a0196b24466858da112`. No production permission/grant mutation occurred and no in-scope item remains.

## 2026-09-27 — v0.1.22-hotfix49 product-code and purchasing row corrections

- In progress from deployed Hotfix48 application commit `d59f5644dde57f14e5351194f3f3454ae1364d0b`, preserving post-deployment documentation commit `cc1dd2f90d06972009a643ed3dac5da5e5fd1c46`.
- Scope is limited to screen-reader-only product labels/aligned selectors in purchase-order and purchase-invoice draft rows plus prefix-shared `<four supplier-code digits>-<six digits>` generated Product Card identities.
- No migration or frontend build is needed. The single rollback-only focused check passed 23 assertions for row alignment/accessibility and code generation/preservation in `toyjoy_hf49_verify_2aa6628094c5`; deployment remains pending. See `.ai/HOTFIX49_CHECKPOINT.md`.

## 2026-09-27 — v0.1.22-hotfix48 purchasing and transfer corrections

- Completed the approved PO, invoice, approval-time product/supplier history, inline-product, supplier-return, transfer presentation/enforcement/numbering, and global System Administrator changes without a migration.
- Focused isolated MariaDB verification passed across non-overlapping rollback-only segments; immediate active System Administrator permission/scope behavior also passed and the disposable account is locked.
- Exactly one Node 20/Vite 8.2.0 production build and focused syntax/Blade/locale/diff checks passed.
- Deployed immutable application commit `d59f5644dde57f14e5351194f3f3454ae1364d0b` after a fresh encrypted backup and reconciled isolated restore. Atomic activation, runtime restoration, live identity/health, both administrator login/landing paths, and four affected authenticated pages passed. No in-scope item remains.

## 2026-09-26 — Hotfix47 authenticated setup redirect repair deployed

- Replaced the conflicting fixed `/dashboard` post-login destinations with one shared authenticated landing decision and intercepted dashboard/initial-setup entry before company readiness resolves a missing operational context.
- A global System Administrator with no visible active store now reaches the existing protected Users, Roles & Scopes setup step; no tenant visibility, administrator bypass, route middleware or verification rule changed.
- A fresh encrypted backup and isolated full restore passed before atomic activation. Focused live authenticated navigation passed for ID 2 (`karim`) and confirmed protected ID 1 (`hassan`) retains normal dashboard/setup access. Production serves application commit `3ed4a9529c45806175424f70e8ce05993e8233cb`.

## 2026-09-26 — Hotfix46 user management repair deployed

- Corrected the split between the active System Administrator role and bootstrap-only `is_super_admin` flag for user management, preserving operational tenant boundaries and approval behavior.
- Admin-created accounts are now immediately verified without email and record both creation and verification actors; the page lists exact validation messages.
- Repaired only audit-proven admin-created unverified user ID 2 (`karim`), preserving credentials, MFA/passkeys, active status, role and scopes. Focused isolated verification and live read-only verification passed; production serves commit `345c3af3f34a9e0ecd75e6694f3183f8f345cb99`.

## 2026-09-14 — v0.1.22-hotfix37 Customer Notes Batch 1

- Implemented the scoped navigation, contextual help, branch presentation, General Settings list-display preference, and user/role card cleanup directly from exact Hotfix36.
- Preserved routes, authorization checks, branch/warehouse/POS relationships, database schema/records, and business/transactional behavior. The focused syntax/render/navigation/accessibility/boundary verification passed, with one successful production Vite build and no database operation.

## 2026-09-08 — v0.1.22-hotfix23 sidebar layout repair completed

- Removed only the active-navigation `scrollIntoView` from `dashboard-assistant.js`; preserved the unrelated guided-tour and product-validation calls.
- Deleted the superseded generated assets and ran exactly one clean replacement Vite 8.2.0 build.
- Actual Laravel layout rendering and the deterministic fallback proved Hotfix20 grid geometry, unique internal scrolling, unchanged document/body scroll, bounded active reveal, collapsed/RTL/LTR/mobile contracts, and final asset identity.
- Hotfix22 keyless navigation, Hotfix21 cash/split payments, Blade/PHP/Livewire roots, locale JSON, asset integrity, and diff hygiene passed.
- Browser screenshots remain unavailable after the single authorized Chromium install attempt failed with ENOSPC; no visual PASS is claimed.

## 2026-09-08 — v0.1.22-hotfix23 resumed deterministic proof (source/build blocker)

- The one authorized workspace-local Playwright Chromium install attempt failed with `ENOSPC`; it was not retried.
- The real Laravel dashboard/navigation render passed with exactly one explicit sidebar scroller and the final Hotfix23 asset identities.
- The deterministic final-bundle proof found a second active-navigation `scrollIntoView` in `resources/js/dashboard-assistant.js`, also present in `public/build/assets/app-641tFKz1.js`.
- Correcting this confirmed source defect requires another production Vite build. The current owner instruction forbids rerunning Vite, so final verification, commit, and packaging stopped fail-closed.

## 2026-09-08 — v0.1.22-hotfix23 sidebar layout repair (visual gate blocked)

- Created the isolated Hotfix23 branch from exact Hotfix22 commit `163548b2f10f217ebf989589701f98a5a60b49ec`.
- Removed the desktop `display:block` shell override, restored the Hotfix20 grid relationship, limited scrolling to `[data-sidebar-scroll]`, removed active-item `scrollIntoView`, and retained Hotfix21 payment plus Hotfix22 normalization behavior.
- One successful Vite 8.2.0 production build completed after an initial npm launcher failure occurred before Vite started or wrote assets.
- Mandatory 1920×1080 screenshot acceptance is blocked: no Chromium, Chrome, Firefox, Playwright browser payload, WebKit/Qt renderer, or cached local browser container exists. Git diff hygiene passed once during the preservation check; the remaining final verification, commit, and packaging were not run.

# UI/UX Release Progress

## 2026-09-21 — Hotfix45 purchasing form repair deployed

- Fixed purchase-order and purchase-invoice reactive line/document totals at their binding and Livewire-morph source, backed by the existing server calculators and decimal snapshots.
- Kept purchase cost distinct from consumer selling price; retained supplier/general/average-cost fallback, existing permissions and required validation, and moved ordinary explanatory notes into contextual help.
- Focused DOM interaction, discount/tax cases, isolated MariaDB save/reload, immutable packaging, fresh encrypted backup/restore, schema preservation, atomic activation, authenticated rendering, and live browser interaction passed. Production serves commit `7a327b93abc48987eab7238e6bcd125635413fbb`; no migration ran.

## 2026-09-18 — Hotfix43 deployed

- Deployed immutable candidate `c71d0ad0651e62aa30c537e0f75fb8fdc6b4d8cf` from exact Hotfix42 baseline after a fresh AES-256 encrypted database/private-storage backup and isolated full restore reconciliation.
- Applied only approved pending migrations 000117 and 000118; exact schema, ownership, monetary-preservation and inventory-preservation checks passed. Atomic activation, caches, PHP-FPM reload, runtime-control restoration, maintenance removal, HTTPS health and nine read-only authenticated affected-page renders passed.
- Deployment evidence is under `/var/lib/toyjoy/hotfix43-deployment/a8474409199802cfc3`; the known inventory exception remains exactly unchanged and unresolved. Owner decisions and external Excel/printer/scanner acceptance remain separate.

## 2026-09-18 — Hotfix43 candidate verification complete

- Owner-approved migrations 000117/000118 and all tax/company consumers are implemented. The restored MariaDB schema/data guards, focused finance/scope/workflow scenarios, affected authenticated browser paths, all 12 XLSX generators, and three localized PO PDFs passed within their recorded boundaries.
- Corrected the focused-pass finding that production has no product assignment rows: unassigned legacy products remain safely company-global only with compatible tax ownership; explicitly assigned products still require an active matching company/branch/store scope.
- Updated the full 84-row recovered matrix without changing original IDs/text. It now distinguishes verified completion, implemented-but-evidence-limited rows, external acceptance, unresolved owner decisions, and the exact approved inventory exception.
- Candidate commit/artifact, fresh production-consistent encrypted backup/restore, deployment, and live verification remain. Production is still exact Hotfix42 at this point.

## 2026-09-18 — Hotfix43 original-requirements correction in progress

- Verified exact deployed Hotfix42 `cb8c1f9637370e531f6ab23f634e70784742cf35` and created an isolated correction worktree from it.
- Implemented the first complete source pass for audited numbering, export, opening-inventory, navigation, printer/template, product/help, customer-group, supplier, PO workflow/PDF outcome and translation defects. Preserved unrelated worktrees, historic references and the known inventory exception.
- Final financial work is waiting only on the exact additive `purchase_invoice_lines.tax_inclusive` and `tax_settings.company_id` schema decision recorded in `.ai/HOTFIX43_CHECKPOINT.md`. No migration, final acceptance, commit, artifact, production write or deployment has occurred.

## 2026-09-08 — v0.1.22-hotfix22 navigation runtime normalization

- Preserved Hotfix21 payment and sidebar behavior while normalizing every renderer-facing navigation field before Blade access.
- Added deterministic fallback keys for keyless groups/items/subgroups and safe rendering for permission-filtered links, nested groups, headings, separators, and empty optional shapes.
- Authenticated database-free dashboard/navigation rendering, three permission profiles, the real keyless Initial Setup item, Hotfix21 payment/sidebar contracts, full Blade compilation, compiled PHP syntax, Livewire roots, locale JSON, and new Vite asset integrity passed. Exactly one production Vite build ran.
- No migration, database, production, deployment, activation, service, sudo, tag, push, or remote operation occurred.

## 2026-09-04 — v0.1.22-hotfix10 UI deduplication

- Removed only the upper standalone Roles & Permissions navigation item and the lower duplicate Product Catalog readiness summary.
- Retained the nested Basic Data → Essentials → Users, roles, and scopes entry, exact setup query parameters and active-state logic, plus the identical upper setup/readiness summary.
- PHP 8.5 syntax, database-fail-closed Blade cache compilation, locale JSON, source contracts, Vite/font manifest references, and diff/storage boundaries passed. No application frontend source changed, so the verified lock-matched Hotfix9 build was not rebuilt.
- PHPUnit and browser automation were blocked by the execution policy before launch and are not claimed. No database or production access occurred.

## 2026-09-03 — v0.1.22-hotfix9 navigation cleanup

- Removed the duplicated sidebar user/status footer, standalone System Settings link, three upper administration duplicates, the Party navigation group, and the second Pricing Lists readiness summary while preserving their underlying routes and behavior.
- Retained canonical Basic Data → Essentials destinations and Roles & Permissions. The canonical setup registry preserves `party-readiness` as disabled and exposes 20 active steps; readiness, adjacent navigation, numbering, and sidebar leaves exclude it.
- Focused source tests, syntax, Blade, locale JSON, Node 20 Vite build, isolated MariaDB route profiling, and authenticated Arabic/English desktop plus Arabic mobile browser checks passed without production/shared access.

## 2026-09-01 — v0.1.22-hotfix1 post-deployment correction

- Completed the owner-listed Arabic/raw-enum, Setup semantics, Product Filter deletion, balance reset, whole-unit quantity, Barcode Label, Product Card pricing, barcode graphics, printer sizing/template, Purchase Invoice card and version corrections.
- One focused gate passed 4 tests/59 assertions, PHP/JSON/diff checks, and the single Node 20 Vite build; prior eight-case bilingual print visuals were preserved. Only physical-printer output acceptance remains.
- Prepared an immutable local release without production/shared data, active symlink, deployment, tags, pushes or remotes.

## 2026-08-31 — v0.1.21 Hotfix 3 Readiness/Pricing/UAT Consistency

- Unified Product Card and pricing readiness through one current-data evaluator across Setup Center, persistent context, Products/Product Card, and Pricing Lists.
- Added localized bounded remediation, direct edit/view-all actions, and correct List 0 inherited/effective/manual-override explanations.
- Audited and retained every named correction through Hotfix 2 and the marked, positive-priced, guard-protected UAT fixtures without accessing or changing production/UAT data.

## 2026-08-31 — v0.1.21 Final Stabilization

- Started exactly from tagged `v0.1.20`; replaced the unsupported Purchase Invoice `barcode` Flux icon with supported `qr-code` and confirmed all other scoped M54 Flux references are installed.
- Added immutable-release cache exclusion and runtime-user `rajeh` Artisan ownership guidance without changing M54 behavior or frontend assets.
- The focused M54 test file passed 8 tests / 62 assertions; purchasing-invoice routes and the isolated six-contract Flux render probe passed. No database, migration, broad suite, frontend build, PDF, production, deployment, tag, remote contact, shared database mutation, or version bump occurred.

**Updated:** 2026-08-28
**Branch:** `feature/v0.1.13-guided-setup-admin-ux`
**Baseline:** published `v0.1.12`

| Milestone | Status |
|---|---|
| M50A — global shell, navigation, operational dashboard | Engineering foundation committed |
| M50A.1 — visual hierarchy, density, navigation, responsive and Arabic correction | Complete in development |
| M50B.1 — internal-page foundation and Sales & POS workspace | Complete in development |

M50B.1 adds reusable active-filter chips, bounded pagination, responsive resource rows, a scoped Sales Overview, refined invoice/customer/shift pages, consistent POS entry navigation, and the v0.1.5 desktop-sidebar recovery fix.

M50A.1 changed shared presentation, centralized module-dashboard specification, and bounded read-only dashboard composition only. The operational dashboard and representative Inventory overview use existing authorized data; no route, permission, mutation workflow, schema, migration, production runtime, or deployment state changed. The single locked build passed, and genuine local rendered evidence is stored outside Git in the approved `m50a1-evidence-20260827` directory.

| M50B.2 — Purchasing & Suppliers workspace | Complete in development |
| M50B.3 — Products, Inventory & Barcodes workspace | Complete in development |
| M50B.4 — Pricing, Promotions & Discounts workspace | Complete in development |
| M50B.5 — Parties, Bookings & Customers workspace | Complete in development |
| M50C — Reports, Administration & System Settings | Complete in development |
| M50D — Assets, Rental & Transfers workspace | Complete in development |
| M52 — v0.1.14 navigation, tables and action controls | Complete in development |
| M53 — v0.1.15 verified visual UX corrections | Complete in development |
| M53.1 — guided Setup Previous/Next navigation | Complete in development |
| v0.1.13 — Guided Setup & Administration UX | Complete in development |

M50B.2 adds the scoped purchasing overview, seven-day trend, receiving/approval/return indicators, bounded top/recent purchasing lists, and responsive supplier/invoice/order/return tables. Due/overdue balances are omitted because there is no authoritative payable ledger.

M50B.3 wires and expands the existing scoped inventory overview with authoritative product/variant, balance, valuation, movement, exception, transfer/count/adjustment, distribution, and top-movement information. Products, balances, movements, and label queues use the shared responsive list foundation. System Settings save controls now expose exact idle/action loading states.

M50B.4 adds an authorized-store-scoped Pricing dashboard, effective/scheduled price coverage, approval and deterministic exception indicators, bounded recent activity, responsive filters/sorting/pagination, and scoped label readiness. No standalone promotion campaign model exists, so unsupported promotion metrics and margin inference are deliberately omitted. The shell now reports all authorized stores honestly instead of displaying the first visible store as an implied active context.

M50B.5 adds a visible-branch/store-scoped Party dashboard, schedule and collections indicators, seven-day booking trend, upcoming agenda, service/payment summaries, responsive booking/invoice/order queues, and Party history on the existing customer profile. Package and venue master data are not fabricated because the authoritative model currently stores service lines and booking locations directly.

M50D adds an authorized-store-scoped Assets dashboard, asset catalog/detail, bounded availability calendar, reservation and custody actions, return exceptions, and immutable condition/event activity. Rental assets remain separate from inventory products. Standalone transfer requests, maintenance work orders, customer rental ledgers, and rental-revenue reporting are absent from the authoritative model and were not fabricated.

## 2026-08-28 — M50C Reports, Administration & System Settings

- **Task:** Implemented M50C from published `v0.1.10` on `feature/m50c-reports-administration`, without deployment.
- **Work completed:** Added a permission-aware Administration Center for readiness, users/roles, locations, configuration coverage, approvals, and redacted audit activity. Expanded the Reports Center with an authorized catalog, recent requester-owned export state, date presets, and removable filter chips. Added missing Reports/Administration navigation destinations and exact Livewire targets for authorization saving and document-counter correction.
- **Reporting boundary:** Preserved `ReportSnapshot`, visible-store/user scoping, bounded source rows, sales pagination/sorting, the 5,000-row export cap, snapshot hashes, requester ownership, locale restoration, XLSX formula protection, and PDF security.
- **Verification actually run:** Focused PHP/Blade syntax, route/navigation/export mapping, `git diff --check`, and one locked Vite build with Node 20.20.2. Manifest SHA-256: `a9e9896affa4baf7ad19a4febdf6de76bf6cf84475b4e9acc277054c7378d94f`; every referenced asset exists. Automated tests and browser control were not authorized, and no database runtime was used.
- **Boundaries:** No production/shared runtime, database, migration, deployment, tag, remote/network, cPanel, Apache, cron, or Toy & Joy WordPress access occurred.
# 2026-08-28 — v0.1.13 guided setup and administration UX

- Implemented validation-first sequential settings controls over the existing save actions, permission-filtered 21-step setup guidance, branch-to-outlet continuation, compact administration tables/mobile cards, and clarified Sales Outlet/Cashier Till terminology.
- Preserved readiness queries, direct routes, permissions, settings validation/actions, branch/store/till/shift relationships, schema, and business mutations.
- The single locked Vite build completed with Node 20.20.2; manifest/reference, focused syntax, route/permission mapping, and `git diff --check` passed. No automated tests, browser session, database operation, deployment, tag, push, or remote access occurred.


M53 corrects the UI inventory to all 22 authoritative Setup steps, fully localizes and persists Setup context, replaces unreliable Flux idle-loading presentation with exact action-scoped controls, completes the 79-view semantic action adoption matrix, standardizes logical table spacing, and verifies active navigation and responsive mobile cards with genuine Arabic/English rendering. The mobile Sales table surface no longer remains horizontally scrollable after conversion to record cards.

M53.1 completes the primary Setup context with permission-filtered adjacent navigation derived from `InitialSetupStatus`. It preserves Setup query context, resolves nested pages to their owning step, keeps the Settings secondary navigator, and aligns both Setup group maps with the authoritative service order so Opening inventory remains step 22.

## 2026-08-29 — v0.1.16 Setup and Administration Foundation

- Replaced duplicated 22-step UI maps with one 21-step route/group authority (5 Basics, 5 Settings, 11 Operational Readiness); Product Import remains available from Product Cards but is no longer a Setup step.
- Added persistent route-owned Setup context, passed/failed readiness reasons, company-scoped append-only skip/defer decisions, and continuation that bypasses decisions until unresolved required deferred work must be surfaced.
- Supplier readiness now requires an active supplier with an active financial-settlement method only. Product/SKU linkage and payment terms remain optional capabilities.
- Settings persistence is separate from Previous/Next navigation, core forms use exact-target Save controls, and the Cashier template remains location-scoped through existing user/store/till mechanisms.
- Genuine guarded rendering passed 11 Arabic/English desktop/mobile cases with 21 authorized Setup cards, one sticky primary context, one Settings secondary navigator, localized reasons and navigation, visible Cashier least-privilege scope, Save labels, Cairo, zero document overflow, zero idle spinners, zero console/external requests, and zero serious/critical Axe findings. Existing built assets were reused; manifest SHA-256 remains `dabbde0ffadfb86f62400eb67668010949dd1b7eeb931174c7c72a0958e988b0`.

## 2026-08-30 — M51 v0.1.17 Customer and Supplier Data Management

- Added reusable hierarchical customer/supplier-group presentation, stable codes, ancestry-preserving search, leaf-only new customer assignments, and filtered XLSX/PDF exchange.
- Centralized customer phone identity, added Egyptian governorate/city residence data and tenant city administration, and preserved legacy customers without destructive backfill.
- Added fixed supplier settlement methods and aligned Setup readiness to an active configured supplier while retaining optional terms and supplier-product references.
- Added staged company-scoped XLSX imports, partial valid-row processing, localized rejection workbooks, formula-safe exports, and bounded filtered customer/supplier exports.
- Focused tests, syntax/routes/diff checks, isolated MariaDB migration, and 13 genuine Arabic/English desktop/mobile captures passed. Existing frontend assets were reused; manifest SHA-256 remains `dabbde0ffadfb86f62400eb67668010949dd1b7eeb931174c7c72a0958e988b0`.

## 2026-08-30 — M52 v0.1.18 Product Cards and Opening Inventory

- Added modal Product Filters management, two-stage Product Cards, exact international/supplier-local barcode policy, and an explicit reusable pricing-update contract.
- Added protected partial XLSX import and filtered XLSX/Cairo-PDF exchange for products, categories, and brands.
- Added dedicated immutable Opening Inventory draft, preview, transactional approval, reversal, and audited zero-opening decision; Setup step 21 now resolves to this workflow.
- Focused tests passed 11 tests/33 assertions; migration 000097 passed on isolated MariaDB; 10 genuine Arabic/English desktop/mobile captures passed with Cairo, zero serious/critical Axe findings, console errors, external requests, and document overflow. Existing frontend build retained: dabbde0ffadfb86f62400eb67668010949dd1b7eeb931174c7c72a0958e988b0.

## 2026-08-31 — M53 v0.1.19 Pricing Lists

- Added company-scoped protected List 0, reusable percentage lists, decimal-safe ceiling-to-5 pricing, dated manual product overrides, and outlet/branch price-list assignment.
- Added an authoritative effective-price resolver and snapshot DTO while preserving Product Card base prices, approved price versions/lines, costs, invoices, and completed sales.
- Added the responsive Pricing Lists workspace, Product Card pricing matrix, assignment controls, least-privilege permissions, audits, navigation, and factual Setup readiness reasons.
- Focused pricing tests passed 6 tests/28 assertions; migration 000098 passed on isolated MariaDB. Five genuine Arabic/English desktop/mobile cases passed with Cairo/RTL/LTR, zero serious/critical Axe findings, overflow, console errors, or external requests. Frontend sources were unchanged and the existing manifest was retained.
# M54 — v0.1.20 Purchase Receiving Distribution (2026-08-31)

- Implemented the full-page two-stage workflow, lookup/scanning, in-context Product Card creation, exact distribution matrix, outlet price snapshots, destination labels, transactional idempotent posting, Product Card cost/List 0 audit updates, reversal, and eight least-privilege permissions.
- Added one additive migration (`000099`). Existing Setup sequence remains the single context and Product Import remains inside Product Cards.
- Focused M54 PHPUnit passed 5 tests/29 assertions. Migration `000099` passed on disposable MariaDB `rajeh_m54_20260831`; the database/server were removed. Focused syntax, routes, Arabic JSON, and diff checks passed.

# v0.1.21 Final Acceptance (2026-08-31)

- Added distinct scanner/manual/camera entry, bounded three-character autocomplete, duplicate quantity focus, unknown-barcode Product Card continuation, and explicit draft stage/next-action/remaining-quantity presentation.
- Added company-bound UAT batches and allowlisted marked records with explicit dry-run seed/purge commands, generated-on-execution credentials, Golden Path, and coverage report.
- Corrected pending approval source-version synchronization after Stage 2 distribution, found during the isolated UAT journey.
- Focused tests passed 12/95; isolated MariaDB seed/purge/reseed lifecycle and Node 20.20.2 build passed. Manifest SHA-256: `4d41c4fce936e8ba68f79ec69c89c36178f4481a8fa660774cce61de4894fe3f`.

# v0.1.21 Purchasing Localization/Layout Hotfix (2026-08-31)

- Completed Purchase Invoice Arabic/English keys, locale-safe names/status/currency/quantities, responsive list records, aligned entry/search controls, and empty-line suppression.
- Persisted the explicit production UAT environment confirmation and real purchase-sequence reuse safeguards.
- Focused tests and eight-case desktop/mobile bilingual visual evidence passed; no database or production access occurred.

# v0.1.21 Purchasing Search/Barcode Hotfix 2 (2026-08-31)

- Separated exact scanner lookup from a cancellable ranked 175ms smart search with immediate Enter, company context, fragment matching, loading discipline, and a 20-result bound.
- Replaced the mixed-mode UAT product with separate GTIN and allocator-backed local products; new UAT prefix state is rejected if it would touch an existing real sequence and all created identities are marked for purge.
- Corrected the malformed Product Card `$isEditing` directive and completed the focused bilingual create/edit audit and evidence.
## 2026-09-02 — v0.1.22-hotfix4 inventory labels/sidebar/pricing

- Centralized strict whole-number product quantity presentation and validation across affected inventory, purchasing, retail, Party-consumable, reporting, and print flows; genuine fractions now fail rather than round, while monetary precision remains unchanged.
- Centralized inventory movement labels in Arabic and English, rebuilt barcode-label selection around bounded server search and preserved multi-selection/remediation state, corrected document-scrolling desktop sidebar behavior, and made positive Product Card base consumer price the immediate selling fallback.
- Focused PHPUnit, PHP/Blade/JSON/routes/diff checks, authenticated Arabic desktop/mobile browser checks, barcode PDFs, and isolated MariaDB migration status passed. Immutable release preparation remains the final packaging action; production remains untouched.

## 2026-09-02 — v0.1.22-hotfix5 performance

- Profiled 20 authenticated workflows against the exact Hotfix4 source and candidate using the same isolated 10,000-product/100,000-movement MariaDB fixture.
- Removed shared-render permission/translation/readiness duplication, overview-only inventory aggregates from child pages, the suppliers' 10,000-row response payload, and unbounded pricing-readiness materialization; added bounded index-friendly exact/prefix search.
- Added four measured reversible indexes. Warm candidate medians remained below 800 ms for standard routes and below 1.5 seconds for Reports; the largest gains were Suppliers (71%) and inventory child pages (40–51%).

## 2026-09-03 — v0.1.22-hotfix5 corrective no-regression follow-up

- Proved that the original product status/parent/code index caused a full active-product index scan and filesort on the shared sellable-product query; removed it while retaining the three beneficial Hotfix5 indexes.
- Made pricing proposal options, dashboard work and version pagination conditional, reused the unpriced paginator count, and added version-invalidated readiness metadata reuse without caching effective prices.
- Focused exact-baseline benchmarks now improve Products, Pricing and Unpriced response and SQL medians. Affected tests pass 9/9 with 88 assertions and zero errors.

## 2026-09-03 — v0.1.22-hotfix6 admin navigation/localization

- Centralized the 21 real routable setup definitions (5 Essentials, 5 Settings, 11 Operational Readiness) and rendered every authorized leaf in the desktop/mobile Basic Data sidebar while preserving the existing overview and setup workflow.
- Standardized user-facing Arabic warehouse terminology to مخزن/مخازن, clarified the combined Warehouse / Point of Sale create workflow, and corrected bilingual Audit Log event/source presentation, dates, warning copy and responsive layout.
- The sidebar uses static canonical metadata and the existing request permission lookup, never readiness/catalog evaluation. Shared-layout profiling retained 5 queries with no duplicates and no meaningful latency regression.
## 2026-09-03 — v0.1.22-hotfix7 inventory counting UX

- Removed active company-wide sales-outlet fallback from POS/readiness; shifts require an explicit authorized outlet/cash-drawer context and confirmation.
- Added authorized store navigation, centralized localized human-name-first rendering, bounded balance/product lookup, improved store filters, and name-first exports.
- Extended stock counts additively with immutable location snapshots, teams/assignments, append-only idempotent contributions and corrections, explicit zero/completion, transfer locks, cutoff-aware reconciliation, per-location adjustments, permissions, audit events and bilingual reporting.
- Focused verification used disposable MariaDB and local Chromium only; evidence is under `.ai/evidence/v0.1.22-hotfix7`.

## 2026-09-03 — v0.1.22-hotfix8 performance/UI

- Removed confirmed Hotfix7 inventory child-route over-fetching while preserving counting and scoping behavior; balances production-read median improved from 263.9 ms/30 queries to 182.4 ms/18 queries.
- Corrected the balances filters for one balanced desktop row and stacked no-overflow mobile layout. Evidence is under `.ai/evidence/v0.1.22-hotfix8`.

## 2026-09-03 — v0.1.22-hotfix9 PHP 8.5 view-cache correction

- Replaced the oversized `resources/views/pricing/labels.blade.php` body with seven bounded Blade partials while preserving its form, scanner search, barcode remediation, advanced settings, Alpine state, authorization branches, and Hotfix9 navigation.
- PHP 8.5.9 `optimize:clear` and `view:cache` passed with task-local file cache/session overrides. Authenticated Arabic and English browser verification passed from isolated `toyjoy_hotfix9_viewcache_verify` with zero console/page errors or overflow.
- Preserved Hotfix8 performance source, vendor, Livewire compilation, PCRE configuration, `.env`, storage and public-storage boundaries; `storage/storage` was not created. Production was untouched and promotion was not executed.

## 2026-09-04 — v0.1.22-hotfix11 company-owner RBAC release

- Finalized the approved D3 company-owner RBAC and Approval Inbox navigation source as `0.1.22-hotfix11`; no D3 functional file changed after its focused 4-test/49-assertion pass.
- Preserved super-admin-only Administration Center and Activity Log access, company-bound owner permissions, one correctly positioned Approval Inbox item, and the unchanged secure interactive Karim provisioner.
- Reused the focused authorization, PHP syntax, and Blade compilation results and ran the single direct PHP 8.5 version assertion. No Composer, Vite, database, production, deployment, provisioning, migration, service, HTTP, tag, push, or remote action occurred.

## 2026-09-04 — v0.1.22-hotfix12 POS shift-opening route

- Corrected the `/pos/shift/open` GET 404 with a permission-gated compatibility redirect to the existing shift page, separated the canonical POST route name, and redirected successful creation to POS.
- Preserved the unchanged shift action and its outlet/drawer authorization, transactions, idempotency, opening-balance rules, and duplicate-active-shift prevention.
- One focused database-free feature test passed with 1 test / 24 assertions. No Composer, Vite, database, browser, production, deployment, migration, seeder, service, HTTP, tag, push, or remote action occurred.

## 2026-09-05 — v0.1.22-hotfix13 cash drawer context integrity

- Traced production drawer code `TEST-REG-0001` to the raw UAT dataset insert, which omitted `cash_drawers.company_id` while inserting its valid store and branch and bypassed the guarded drawer action.
- Made selected-store ownership authoritative, added model-level company/branch/store validation for Eloquent creates and updates, corrected the UAT/production/remediation seeding paths, and found no cash-drawer import path.
- Added migration `000107`: it audits all drawers in deterministic ID order, backfills company only from an unambiguous valid store/branch/company chain, reports all ambiguous IDs/codes/reasons before changing anything, then enforces non-null positive company IDs and the restrictive company foreign key.
- Invalid drawers are excluded from shift selection; shift opening retains scope checks, accounting, locking, idempotency, and duplicate guards while returning a localized configuration-validation message instead of context `findOrFail` 404s.
- Focused Hotfix13 regression passed 4 tests / 43 assertions. Changed PHP syntax, database-free route registration, Arabic/English JSON, and diff hygiene passed. The earlier combined harness attempt reached zero assertions because the translation override loader attempted the deliberately unreachable database; no database connection or mutation occurred.
- Karim/company-owner permissions, the permission-search display defect, and the pending V0 UI batch were untouched. Production, database, active symlink, services, tags, pushes, and remotes were untouched.
## 2026-09-05 — v0.1.22-hotfix14 pricing and POS cash rounding

- Confirmed the canonical M53 percentage-derived price resolver already used BC Math ceiling-to-5 while base prices and effective manual overrides bypassed it; named the 5 EGP increment explicitly and left all materialized/historical prices untouched.
- Added append-only migration `000108` to establish `pos.cash_rounding_denomination=5.0000` only when needed, retaining setting history and exact Hotfix13 rollback compatibility.
- Preserved nearest-denomination rounding for cash settlement only, kept non-cash totals exact, and converted missing/invalid cash configuration into a localized cash-only disabled state instead of an unhandled checkout/render exception.
- One database-free focused pass completed 20 arithmetic/source assertions; changed PHP syntax, POS routes, Arabic/English JSON, and diff hygiene passed. No database, production, browser, Composer, Vite, tag, push, or remote operation occurred.



## 2026-09-06 — v0.1.22-hotfix16 POS checkout and multi-customer workflow

- Replaced the session-only POS cart with durable open orders scoped to company, branch, selling outlet, cash drawer, shift, cashier, and idempotency token; each order independently retains its customer, cart/approval payload, tax choice, notes, payment draft, totals, and lifecycle.
- Added bounded indexed customer lookup and compact in-POS customer creation/attachment, responsive Arabic-first order tabs, cash/electronic/multi-card/mixed payment flows, safe references, success modal, and scoped configured receipt preview with explicit browser-print fallback.
- Sale completion locks the open order and active shift, rehydrates its durable lines, and delegates inside one transaction to the existing sale/payment/inventory/tax/accounting/audit/receipt contracts; Hotfix14 cash rounding remains cash-only.
- Focused database-free verification passed after correcting only failed checks: all four authorized source-contract methods have passing results, 28 assertions passed in the corrected method, all changed/new PHP syntax passed, four changed Blade templates compiled and linted, 45 POS routes registered without duplicate names, 133 Hotfix16 locale keys have Arabic parity, diff hygiene passed, and Vite inputs were unchanged.
- No database, migration execution, browser, live HTTP, production access or mutation, backup execution, deployment, activation, service action, Composer install, Vite build, tag, push, or remote contact occurred.

## 2026-09-06 — v0.1.22-hotfix17 POS compact redesign and refund workflow

- Reworked only the POS presentation and Livewire coordination needed for compact desktop proportions: persistent order tabs are inside the cart, customer and product search are unobstructed and automatic/server-bounded, payment choices are compact selectable cards, and checkout is a wide responsive cash/card/split dialog with distinct insufficient-cash and customer-change states.
- Added a seven-step in-POS refund wizard over the existing returns foundation. It supports verified source lookup, full/partial remaining quantities, condition and authorized return location, original split allocation, cash and store-value alternatives, independent approval/rejection, success/view/print actions, and localized safe errors.
- Extended return persistence with deterministic reversible migration 000111 and strengthened completion with source-sale serialization, row locking, final quantity/location/value/tax/rounding revalidation, completion payload idempotency, exact-outlet cash shift/drawer checks, append-only payment snapshots, reversing inventory movements, shift reconciliation, audit events, and configured sales-return printing.
- Implementation is complete. The one authorized consolidated database-free verification pass and release packaging remain; no test, browser, database, production, migration, deployment, backup, service, tag, push, or remote operation has run during implementation.
- The single Hotfix17 consolidated database-free verification passed: 17 changed/new PHP sources linted, all changed/new Blade sources compiled and linted, focused POS/refund route and source contracts passed, Arabic/English locale parity and diff hygiene passed, and Vite was skipped because no frontend input changed.
- Hotfix17 is ready for the clean release commit and offline immutable operator package. Migration 000111 remains unexecuted and production remains untouched.

## 2026-09-07 — v0.1.22-hotfix18 POS Blade repair

- Retained exact Hotfix17 UI and refund behavior while repairing the compiler-confirmed nested POS/checkout return-permission directives, cart inline PHP assignments, and PHP 8.5 purchasing-return print fallback.
- Hardened the derived operator scripts so `bootstrap/cache` is created with `rajeh:rajeh` ownership and mode `0770` before Artisan, all Artisan commands execute through `runuser` as `rajeh`, exact active Hotfix16 is required, and migration 000111 may already be Ran.
- The single focused database-free verification passed all real Blade compilation and compiled-PHP syntax checks, including the POS view; changed PHP syntax, locale JSON, diff hygiene, route registration, and operator-script Bash syntax passed. The existing harness does not support authenticated POS rendering without a database, so no render PASS is claimed.
- No frontend input changed, so Vite was not run. No Composer, broad suite, browser, database, production access, migration, backup, deployment, service action, tag, push, or remote operation occurred.


## 2026-09-07 — v0.1.22-hotfix19 POS Livewire root and release-cache repair

- Retained all Hotfix17/18 POS and refund behavior while placing the conditionally displayed refund wizard inside one permanent Livewire root. The other changed POS Livewire templates already had permanent single roots and were left structurally intact.
- Corrected derived promotion/activation preparation so the extracted release gets bootstrap cache at mode `0775` with recursive `rajeh:rajeh` ownership, all Artisan commands run as `rajeh`, and writability is proved before and after cache generation.
- The remaining database-free pass compiled all 231 Blade templates through the registered Laravel/Flux/Livewire compiler, confirmed the four POS Livewire roots and closed refund render contract, and linted the changed compiled refund view. Host-user `rajeh` ownership/cache proof is deferred to the fail-closed activation preflight.
- Hotfix19 adds no migration; 000111 is required to remain already Ran. No broad test, browser, Composer, Vite, database, production access, migration, deployment, tag, push, sudo, or remote operation occurred.


## 2026-09-07 - v0.1.22-hotfix20 POS production assets

- Created Hotfix20 from exact Hotfix19 commit `1fd252c6f0e612f8b3a0d4cea3413a53295205a8` without changing the POS/refund implementation.
- Confirmed Tailwind v4 recursively scans all `resources/views`, including POS and Livewire Blade templates; no content-path, invalid-utility, or CSS-conflict edit was required.
- Ran exactly one clean production Vite build. The new manifest SHA-256 is `8e5c124882f8b0af416f5a35e28e90adfc2952a38abf70296780b80075c73901`; the new app CSS SHA-256 is `56e206d8fc38fc2e01c82d5f2b2b8e8ef20947b67fc5feac0124db18c850ec87`. Both differ from Hotfix16.
- Authenticated safe non-production POS rendering was unavailable because no isolated environment/database exists, so no visual PASS or screenshots are claimed. Production remained untouched.
- Hotfix20 adds no migration. Migration 000111 must remain already Ran; activation performs no migration and fails closed before switching unless exact assets, Livewire rendering, and `rajeh` runtime-cache preparation pass.

## 2026-09-08 — v0.1.22-hotfix21 cash checkout and persistent sidebar

- Corrected cash/split arithmetic with exact cents in the browser and BC Math on the server; underpayment remains due and blocked, exact cash settles at zero, excess cash becomes customer change, and invalid/duplicate/overallocated portions fail closed.
- Made the desktop sidebar fixed independently of document scrolling with a dedicated vertical/no-horizontal navigation scroller; persisted scroll position, expanded groups, and active ancestry across full and Livewire navigation while retaining RTL, collapsed desktop, and mobile drawer behavior.
- Ran one Vite production build and completed focused database-free payment, navigation-state, Blade/compiled-PHP, Livewire-root, locale, asset-integrity, and diff checks. No migration, database, production, deployment, tag, push, sudo, or remote action occurred.
# 2026-09-09 — v0.1.22-hotfix24 product quick-create and compact UI

- Started from exact Hotfix23 commit `ac812449e446f69301a0202cb540c625f71eb9d8` in the isolated `rajeh_ahmed` worktree and required Hotfix24 branch.
- Completed the source implementation for atomic purchase-invoice quick Product Card creation, the label-only Arabic cost rename, product-description Stage B placement, compact authenticated shared spacing, compact Reports Center, and affected bilingual localization.
- Preserved the invoice draft component state and deferred inventory/accounting/supplier posting to the existing invoice-finalization path. No migration was added or run.
- Exactly one clean Node 20/Vite 8.2.0 production build passed. The bounded source, Blade/PHP, locale, asset, Hotfix23 sidebar, cash/split-payment, POS/refund/inventory unchanged-source, diff, and operator-script checks passed in one focused final verification.
- The clean local commit and immutable archive, complete manifest, promotion script, and activation script were prepared under `/home/rajeh_ahmed`; promotion and activation remain unexecuted.
# 2026-09-09 — v0.1.22-hotfix25 keyboard product lines

- Added one reusable accessible product combobox and keyboard row controller for exact barcode/code selection, bounded multi-match navigation, quantity/price progression, line totals, validation, and unlimited one-at-a-time row entry.
- Applied the workflow to purchase orders/invoices/returns, transfers, stock counts, adjustments, exchanges/returns, quotations, Party booking/invoice product lines, and POS search/cart presentation while preserving downstream authorization and document rules.
- Added a permission-gated transaction lookup endpoint covering barcode, item/SKU code, model, Arabic name, and English name; cost is omitted from responses unless the operator already holds a cost-bearing permission.
- PHP/Blade/runtime contracts, compiled asset identity, and representative browser keyboard scenarios passed. One successful Node 20/Vite 8.2.0 production build emitted the final assets after the default Node 16 launcher failed before Vite could compile.
- No schema, migration, database, production, deployment, tag, push, sudo, or root action occurred.

# 2026-09-10 — v0.1.22-hotfix26 supplier product lookup

- Replaced purchasing selectors in purchase orders, purchase invoices, and supplier returns with the shared incremental product combobox, supplier-first gating, associated/all-product filtering, keyboard/scanner behavior, and explicit native camera scanning.
- Reused the existing many-to-many `product_suppliers` schema and approved purchase-invoice history. Supplier-specific latest approved price/fallback source is shown and remains editable; valid purchasing saves establish missing associations and approval updates purchase history.
- Added localized supplier-change confirmation that clears affected lines/prices, preserved supplier-return source-invoice cost constraints, and retained Hotfix25 behavior outside purchasing.
- Focused syntax, Blade compilation, dependency-free interaction tests, runtime rendering/asset checks, locale JSON, and diff hygiene passed. Exactly one Node 20/Vite 8.2.0 production build ran.
- No migration was added or run. No production access, deployment, promotion, activation, backup, service action, sudo, root, push, tag, browser/package installation, or remote operation occurred.

# 2026-09-10 — v0.1.22-hotfix27 purchase-order full-page search

- Reserved a fixed logical camera-button area in the shared purchasing lookup and applied direction-safe logical padding/alignment so Arabic, English, numeric, and mixed queries remain visible responsively.
- Changed contained server matching and browser selection state so `001`, narrowed identifiers, and Arabic names update incrementally; multiple matches remain unselected until an arrow/mouse choice, while one exact unique barcode, SKU, supplier code, or model may select immediately.
- Added the permission-gated full-page purchase-order create route and adjacent bilingual sidebar item. The page reuses the existing Livewire component, validation, supplier/store/line state, supplier pricing, totals, and draft-save action.
- Focused syntax, 721-view Blade compilation, route/runtime rendering, dependency-free interaction tests, localization, compiled-asset integrity, and diff hygiene passed. Exactly one clean Node 20/Vite 8.2.0 production build ran.
- No migration was added or run. No production access, deployment, promotion, activation, database change, service action, sudo, root, push, tag, browser/package installation, or remote operation occurred.

# 2026-09-10 — v0.1.22-hotfix28 purchase-order runtime repair

- Correlated both supplied production request IDs to the same `Undefined variable $createPage` exception during Livewire `purchasing::orders` rendering; both custom render branches omitted the new view datum.
- Added `createPage` explicitly to the overview and full-page create render arrays without changing the Hotfix27 routes, navigation, form, product lookup, or create-modal removal.
- Added a fail-fast activation runtime verifier that uses an existing authorized user and visible store, array-only session/cache drivers, internal HTTP rendering in Arabic and English, warning escalation, and an always-rolled-back database transaction.
- Focused PHP syntax, 715-view Blade compilation, route/runtime fixture, navigation/product lookup, 3/3 JavaScript behavior checks, locale JSON, unchanged asset hashes, and diff hygiene passed. No Vite build was run because frontend source was unchanged.
- No migration or database mutation occurred. Production logs were read-only; production files, caches, data, releases, and active links were not modified.

# 2026-09-11 — v0.1.22-hotfix29 purchase-order search and compact layout

- Rebound the shared product lookup whenever Livewire preserves its root but replaces child controls, and stopped empty product IDs from emitting competing Livewire input requests while users type.
- Moved the camera control fully outside the input, made Arabic/English direction and alignment explicit, and preserved supplier filtering/pricing, unique exact selection, guarded multi-match Enter, stale-response protection, and keyboard row progression.
- Compacted the full-page purchase-order editor into a five-field desktop metadata row and dense aligned item rows, with a bounded scrolling item area, sticky header, compact price source, end-positioned notes, totals, and actions.
- Focused local checks passed: touched PHP syntax, 887-view Blade compilation, locale JSON, static bilingual route/layout/asset contract, 4/4 JavaScript runtime behavior tests, compiled asset identity, and diff hygiene. Real-environment authenticated bilingual route/search checks remain deliberately activation-gated because the isolated worktree has no environment or authorized local database credentials.
- One clean Node 20/Vite 8.2.0 production build completed successfully. No migration, production access/change, deployment, activation, sudo/root action, push, tag, package/browser installation, or unrelated work occurred.

# 2026-09-11 — v0.1.22-hotfix30 compiled browser product search

- Preserved the Hotfix29 purchase-order overview and compact create-form layout byte-for-byte.
- Repaired the hidden lookup feedback by rendering its dropdown as a fixed body overlay outside the bounded `overflow-y-auto` line container; added compact localized loading, results, empty, and request-error states.
- Made lookup binding idempotent across initial DOM load, Livewire initialization/navigation, Livewire morph hooks, mutation fallback, and dynamically added rows, while retaining authenticated debounce, supplier filtering, exact-unique selection, multi-match keyboard safeguards, row pricing/totals, camera behavior, and stale-request protection.
- One clean Node 20.20.2/Vite 8.2.0 build completed. The compiled bundle passed the rendered-markup DOM lifecycle contract for Arabic and identifier input, feedback visibility, keyboard/row state, Livewire replacement/addition/navigation, and unclipped overlay placement.
- No migration, production access/change, deployment, activation, sudo/root action, push, tag, package/browser installation, or unrelated work occurred.
# v0.1.22-hotfix31 purchase-invoice keyboard compact UI — 2026-09-11

- Reused the Hotfix30 browser-side product lookup per purchase-invoice line with supplier filtering, dynamic bilingual/identifier search, scanner separation, stale-response handling, and explicit multi-match selection.
- Added invoice keyboard progression through quantity, editable cost, list price, discount and tax fields; final Enter creates at most one next row and focuses its product search while parent submission remains blocked.
- Compacted purchase-invoice metadata, bounded/sticky line geometry, totals, notes/actions, and the price-list search/status row without changing invoice or pricing business rules.
- Updated version/tracking/evidence; exactly one clean Node 20 production Vite build completed. No migration or production operation occurred.

# v0.1.22-hotfix32 invoice verification fail-closed repair — 2026-09-11

- Confirmed the approved Hotfix31 invoice Blade declares the supplier-only checkbox once; the stale verifier was matching the same words inside a longer lookup empty-state data message.
- Replaced the substring assertion with exact rendered text-node checks around one editor checkbox and multiple lookup controls, and made all verifier exceptions exit explicitly with status 1.
- Hardened activation around checked `tee` pipeline statuses and an exact terminal PASS marker; post-switch failure restores exact Hotfix31, rebuilds caches, reloads PHP-FPM, runs `artisan up`, and confirms maintenance mode is off.
- The isolated negative contract passed pre-switch/no-switch, post-switch/rollback, and no-false-success scenarios. No frontend source changed; exact Hotfix31 compiled assets are reused and Vite build count is zero.
- No migration, database, production access/change, promotion, activation, sudo/root action, service action, push, tag, package/browser installation, or unrelated application change occurred.

# 2026-09-12 — Egyptian Arabic locale

- Added the separate `ar-EG` locale named `العربية المصرية` from exact source baseline `3ca22dbe6671f94aaec4132b84fb6a2c126cec82`; retained `ar` and `en` unchanged.
- Completed the 6,115-key union of current Arabic and English JSON catalogs plus all six Arabic PHP locale files, with no empty or duplicate `ar-EG` entries and protected placeholders/tokens preserved.
- Added three-way locale switching with existing session/cookie persistence, Laravel fallback `ar-EG → ar → en`, exact `lang="ar-EG"`, RTL/Cairo behavior, and Arabic field/label selection across existing application surfaces.
- Recorded 1,515 remaining hardcoded user-facing candidate locations in `docs/audits/egyptian-arabic-hardcoded-strings.md`; no functional Blade/Livewire copy was mass-extracted during the audit.
- Focused locale integrity, PHP/JavaScript syntax, full Blade compilation, fallback, switch/persistence, RTL/lang, audit-count, build-manifest, unchanged-source-locale, and diff checks passed. One Vite build completed successfully after one earlier invocation stopped before asset generation because the isolated worktree lacked a local `vendor` path.
- No automated test suite, browser, database, migration, deployment, packaging, activation, production access, push, tag, sudo, package installation, or business-rule change occurred.

# v0.1.22-hotfix33 Egyptian Arabic integration — 2026-09-12

- Integrated localization commit `be300bfb039316a3f89b29f2cd2c29bd73bd0add` onto exact Hotfix32 commit `2636d8dc1d345fa050e4e20c729a4cab9ac4a35a` in a separate `rajeh_ahmed` worktree and preserved both tracking histories.
- Accepted only locale registration, switching/persistence/fallback, Arabic-family data presentation, RTL/Cairo, and view-copy changes. Rejected the locale-dependent POS customer-search `ORDER BY` hunk and retained exact Hotfix32 query behavior.
- Corrected 532 non-wildcard-aware `isLocale('ar*')` calls across 107 files to a real Arabic-family prefix check after the focused runtime probe demonstrated the Laravel method uses exact equality. Existing Arabic and Egyptian Arabic now share the intended presentation without business-rule changes.
- Focused verification passed 6,115 JSON keys, 244 PHP locale leaves, exact key/token parity, unchanged `ar`/`en`, switch/session/cookie/fallback, RTL/lang/Cairo/sidebar/command palette, Hotfix32 invoice-search/keyboard compiled behavior, assets, audit count, syntax, diff hygiene, and isolated activation failure/rollback behavior. Authenticated production-shaped route rendering remains activation-gated before and after the switch.
- No migration, database access/mutation, production access/change, deployment, promotion, activation, sudo/root action, service action, automated suite, browser, push, tag, package/browser installation, or remote operation occurred.

# v0.1.22-hotfix34 Egyptian locale route-cache repair — 2026-09-12

- Rebuilt from exact Hotfix32 in a separate clean worktree, reapplied the accepted Hotfix33 localization history, and left the failed Hotfix33 release and artifacts immutable.
- Replaced the locale-switch closure with the dedicated invokable `LocaleController`; preserved POST, route name, CSRF/web middleware, redirect-back, session persistence, one-year cookie, and exact `ar`, `en`, `ar-EG` validation.
- Replaced direct cached-action invocation in the runtime proof with genuine HTTP-kernel requests through the cached router, including safe rejection of unsupported and malformed locale values.
- Preserved exact Hotfix32 business queries, all Hotfix24–Hotfix32 purchasing/search/keyboard repairs, 6,115 JSON keys, 244 PHP leaves, and the 1,515-location deferred hardcoded-string audit.
- Frontend inputs are unchanged; exact Hotfix33 compiled assets are reused and Hotfix34 Vite build count is zero. Authenticated database-backed rendering remains strictly pre/post-switch activation-gated and transactional.

# v0.1.22-hotfix35 Egyptian localization completion — 2026-09-12

- Created the Hotfix35 branch from exact active Hotfix34 commit `397fd3529a087f08f402fd11c88692481bb0654b` in a separate clean worktree and cherry-picked localization commit `57cb2a85666cc7d9cfad02edce33eb5bfa766df6` without conflicts or discarded localization content.
- Completed the `ar-EG` presentation while preserving `ar`, `en`, the Hotfix34 controller-based locale route and cached-route repair, internal PHP class names, queries, permissions, routes, search, keyboard workflows, accounting, posting, and migrations.
- Version, focused evidence, immutable offline package, and fail-closed operator scripts target `0.1.22-hotfix35`. Promotion and activation accept only exact Hotfix34; activation requires migration 000111 already `Ran`, runs no migration, verifies authenticated `ar`, `en`, and `ar-EG` representative routes before and after switching, and restores exact Hotfix34 on any post-switch failure.
- Vite inputs are unchanged, exact Hotfix34 compiled assets are reused, and Hotfix35 Vite build count is zero. No production access, database mutation, migration, deployment, promotion, activation, root execution, push, tag, or package installation occurred.
- 2026-09-12: Completed the owner-directed V0 Egyptian Arabic locale pass from exact Hotfix34. All 6,115 JSON values and 244 PHP leaves were rewritten locally; focused rendered-source wording and the 1,515-candidate audit were resolved/categorized. Final verification and commit are recorded in the session summary.


# 2026-09-13 — Egyptian Arabic locale quality repair

- Repaired the Hotfix35 `ar-EG` corruption from exact commit `2f6b07285ca082cb3c61f1f860e13b0d3d2bccb8` on `ui/egyptian-arabic-locale-quality-repair`.
- Removed all 3,093 literal `بالمصري:` occurrences. Of 6,115 JSON values, 6,111 changed: 2,974 needed a prefix-only cleanup and 3,137 received contextual or grammatical rewriting beyond prefix removal. All 244 PHP locale leaves changed and were reviewed.
- Corrected residual Hotfix35 word-map ordering, malformed empty states, approval and wallet wording, product-card labels, limit text, and Egyptian-only rendered copy across 14 Blade templates. Preserved professional accounting, inventory, tax, and legal terminology plus required identifiers, placeholders, brands, and file formats.
- The focused final gate passed JSON/PHP syntax, 6,115-key and 244-leaf integrity, placeholders, exact unchanged `ar`/`en`, zero forbidden prefixes and known corruption signatures, representative product/dashboard/alerts/approvals/sales/purchasing/inventory wording, unchanged RTL/Cairo/locale switching and compiled assets, allowed-file scope, and `git diff --check`.
- No browser-control or automated suite was run under the current repository directive. No Vite build, database operation, migration, production access, deployment, packaging, push, tag, or business-logic change occurred.

# v0.1.22-hotfix36 Egyptian localization quality repair — 2026-09-13

- Integrated direct-child repair commit `80156c9ab50d9b76c19a797dc30db487b694ecb2` onto exact active Hotfix35 `2f6b07285ca082cb3c61f1f860e13b0d3d2bccb8`, preserving all 6,115 `ar-EG` JSON keys and 244 PHP locale leaves.
- Retained byte-identical `ar` and `en`, exact Hotfix35 compiled assets, locale routes, queries, permissions, search, keyboard, accounting and database behavior; Vite build count is zero and no migration was added or run.
- Finalized version/evidence and prepared immutable offline packaging plus unexecuted fail-closed scripts restricted to exact Hotfix35. Authenticated three-locale representative rendering is enforced transactionally before and after activation, with exact Hotfix35 rollback after any post-switch failure.

# 2026-09-14 — v0.1.22-hotfix40 purchase workflow and reports center

- Implemented the exact draft/review/procurement-approved/ready-to-convert purchase-order lifecycle with server permissions, audit reuse, explicit next actions, full-page editing, branch numbering/filtering/counts, duplicate-submit safeguards, and separate receiving status.
- Enabled locked/idempotent partial receiving and multiple invoices per order with ordered/received/remaining quantities, immutable approved invoice and ledger snapshots, linked-document visibility, and independent not/partially/fully received states.
- Added compact supplier-cost-aware product entry, private versioned localized purchase-order PDFs, and a unified permission-scoped Reports and Export Center with centralized CSV/XLSX/PDF generation, request deduplication, snapshot hashes and formula-safe CSV output.
- Added only migration 000114 with nullable/indexed/backward-compatible purchase document and export additions; it was not executed and rollback intentionally retains the additions.
- One focused database-free verification pass and exactly one Node 20/Vite 8.2.0 production build passed. No broad legacy suite or browser control ran.

# 2026-09-14 — v0.1.22-hotfix41 reports runtime repair

- Converted every `Stringable`-derived translation argument in the Hotfix40 Reports and Export Center views to an explicit scalar string, preserving all labels, locales, datasets, permissions, filters and export behavior.
- Prepared an authenticated `/reports` smoke for `ar`, `en` and `ar-EG` and a release workflow that requires 000111–000114 already `Ran`, executes no migrations, normalizes active/candidate cache permissions, switches atomically and restores exact Hotfix39 application files on failure while retaining 000114.
- The single focused final source pass completed changed PHP syntax, one full Blade compilation with compiled syntax, scalar translation-boundary assertions, authenticated transactional `/reports` rendering in all three locales and diff hygiene. No Vite-managed input changed, so the Hotfix40 build is reused without rebuilding.
