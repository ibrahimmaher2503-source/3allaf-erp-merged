# Current Task — Client Feedback Remediation: Expanded Master Request

**Date:** 2026-08-20
**Status:** ACTIVE. Batch A and Batch B (CF-01–CF-15) are closed for the evidenced disposable Local/Dev scope. The expanded master ledger is **8/15 locally implemented + verified and 7/15 open**; the Master request is not complete because owner approvals, physical hardware, UAT, Production, release, and final closure evidence remain outstanding.

**CLIENT FIX QUEUE pointer:** CF-14 DONE (CF-01–CF-15 complete for the evidenced local scope). The queue remains the factual progress ledger, not a limit on the expanded implementation-planning scope.

## Scope

- Primary source: `docs/Master Change Request — Client Feedback Remediation & Setup UX Overhaul.md`.
- Persistent ledger: `docs/client-feedback-remediation-checklist.md`.
- The owner-directed expanded scope is the complete master request (requirements 0–72, its acceptance/test obligations, and the P0/P1/P2 priority order). The simplified `CLIENT FIX QUEUE` remains the status ledger; it no longer restricts implementation planning.
- Completed evidence checkpoint: Batch B (CF-13/14). Other master-request waves may be planned and implemented in parallel when independent, with shared-file work serialized and every change mapped back to the master request.
- The older CR/RG extraction remains historical traceability. Findings must still be classified and mapped to a root cause before implementation; this expansion does not authorize unrelated product work.
- Use only named disposable XAMPP MariaDB databases for focused verification (`toyjoy_client_feedback_20260819`, `toyjoy_master_migration_20260820`, and `toyjoy_browser_20260820` are the recorded cycle databases); never use SQLite or Production data.
- Preserve the pre-existing dirty worktree and do not overwrite unrelated prior remediation changes.

## Verification directive

- DEC-085 authorizes focused PHPUnit/Feature/Integration/concurrency coverage and visible headed Chromium/Playwright for this client-feedback cycle.
- Every production behavior change follows RED → GREEN → refactor; no production change is required when the exact client scenario already passes.
- The active CF task must identify the exact affected screen(s), source, selector scope, and displayed readiness/count rule before a production change is authorized.
- Exact commands, tests, assertions, browser evidence, and limitations must be recorded; page render or HTTP 200 alone is insufficient.
- Production connections, fabricated owner data, physical-device claims, external service actions, UAT acceptance, commits, pushes, and release approval are not authorized.

## Expanded implementation waves

1. **Wave 0 / P0 — Setup blockers:** canonical settings and company persistence; branch source-of-truth and six-branch creation; branch/warehouse/POS/drawer relationships; warehouse counts and safe archive/delete; phone validation; sidebar/navigation; actionable validation and prerequisite states.
2. **Wave 1 / P1 — Setup architecture:** timezone inheritance; payment and proof/offline wording; tax defaults/zero-tax/overrides; document sequences and safe overrides; printer/template separation and runtime selection; setup dashboard; setup-versus-operations navigation; manual/import staging; account and policy terminology; inheritance and truthful readiness.
3. **Wave 2 / P1 — Master data:** category hierarchy and optional English; customer registration/name/duplicate/consent/group/child/loyalty/wallet flows; supplier groups, contacts, communication preferences, payment terms, and order-recipient resolution.
4. **Wave 3 / P2 and cross-cutting closure:** bilingual UX, warehouse taxonomy, help/empty/destructive states, consistency and dirty-state behavior, audit/authorization/concurrency, multi-branch scope review, regression rechecks, deterministic fixtures, scenario evidence, remediation matrix, remaining blockers, and final verdict.

These waves authorize planning and implementation only. They do not claim that any wave, requirement, test, browser check, Phase Gate, UAT, Production readiness, or final verdict is complete.

## Current closure sequence

1. Completed: CF-04 RED→GREEN inheritance fix, focused MariaDB tests, and repeated headed Chromium verification.
2. Completed: CF-05 branch/warehouse relationship, terminology, dropdown, and count correction with focused MariaDB and headed Chromium verification.
3. Completed: CF-07 cash drawer association after focused tests, headed Chromium, and Sol PASS.
4. Completed: CF-08 archive safety closure: backend 1/1 with 9 assertions; core headed archive UI verified; later locator-only stop recorded honestly.
5. Completed: Batch A — CF-09, CF-10, CF-11, CF-12, and CF-15.
6. Completed: Batch B — CF-13 and CF-14. Serial focused MariaDB verification on disposable port 3307 passed 5/5 tests with 25 assertions. Authenticated headed-browser verification passed the six Settings tabs in Arabic RTL at 390px with no horizontal overflow and no console errors, plus supplier/branch/store/customer-group validation and drawer modal states, product-import staged/reviewer/self-approval evidence, and POS mobile width checks. The settings 500/parse/root/dirty defects are fixed for this local scope.
7. Completed local evidence consolidation: structured customer names/children/duplicate email, supplier/PO terms, scoped printers, payment/tax/sequences, migration rollback/forward and authorization seeder, owner-decision CTA matrix, bilingual browser batches, §66 **15/118 PASS**, and multi-branch **20/104 PASS**.

## Resume point

1. Keep CF-01 through CF-15 closed for the evidenced disposable Local/Dev scope; do not convert that queue closure into a master-request, UAT, Production, or Go-Live claim.
2. Continue the seven open expanded groups; the queue pointer and eight local checkmarks must not be interpreted as owner approval or final Master completion.
3. Keep Batch A closed and do not revive the historical 74-ID matrix as delivery scope.
4. Requirements 36/37 category optional-English/hierarchy behavior is locally implemented and verified through focused MariaDB tests and browser checks.
5. Future destructive verification must use a named disposable MariaDB database. The recovered local 3306 instance is not a disposable test target; Production remains prohibited.

## Batch B closure evidence — 2026-08-20

- Serial MariaDB 3307 verification completed with **5/5 tests and 25 assertions** on the disposable client-feedback database. Product import evidence separately covered staged mapping, reviewer approval, and requester self-approval rejection; §49 supplier order-recipient resolution covered **2 tests and 5 assertions**.
- Authenticated headed-browser checks passed the six `/admin/settings` tabs in Arabic RTL at 390px with no horizontal overflow or console errors. The settings 500/Blade parse/Livewire root/Alpine dirty-state chain is fixed for this local scope.
- Browser checks also passed supplier, branch, store, and customer-group empty-submit validation, cash-drawer modal opening, and POS mobile layout at 375px with no overflow. No owner business data, physical device, Production, UAT, release, commit, or push claim is made.

## Expanded local closure evidence — 2026-08-20

- **Backend/database:** payment/tax **14 tests / 63 assertions**; sequences **5/28** plus **3/16**; settings audit/authorization **6/38**; multi-branch **20/104**; §66 minimum scenarios **15/118**. Structured names, children, duplicate email, customer/supplier scope, PO terms, and recipient resolution passed their focused MariaDB checks.
- **Migration/seeder:** 75 migrations passed forward; the corrected printer-scope rollback and second forward migration passed; `CanonicalAuthorizationSeeder` passed twice with stable 9-role/400-permission invariants and no company/owner data.
- **UI:** authenticated Arabic RTL and English LTR desktop/mobile batches passed the affected setup/master/settings/forms pages with no error page, console warnings/errors, or horizontal overflow. Customer/child and supplier/PO QA covered persistence/reload; Global/Branch/Location printer scope and runtime resolution are locally verified, while physical printing is not.
- **Owner decisions:** unresolved business policies are exposed through permission-aware Initial Setup cards/CTAs and remain pending. No fictitious approval was persisted.
- **Ledger:** 8/15 expanded groups are locally implemented + verified; 7/15 remain open. Master completion, human UAT, Production readiness, release, physical-device acceptance, commit, and push are not claimed.

## Translation override slice — 2026-08-20

- Owner-directed Local/Dev feature: `/admin/translations` (`UI-ADM-014`) permits `company_settings.edit` users to change known Arabic/English JSON or PHP translation keys through audited database overrides. It is not a language-file editor and does not imply UAT, Production, or release closure.
