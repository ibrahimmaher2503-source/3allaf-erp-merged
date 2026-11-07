# Module 1 red-team audit — 2026-10-07

**Reviewed SHA:** `07a514c94d106efe3516d81780ee9081ecfd86bc`  
**Worktree/branch:** `C:\Users\N\.codex\worktrees\m1-audit\erp`, `hotfix/module1-redteam-audit-20261007`

## Confirmed finding

`settings.blade.php::editPrintTemplate()` loaded the full template into public Livewire state without an action-level settings permission check. The page had authorized the initial mount, but after `company_settings.edit` was revoked, a signed existing component could still call the method and receive status 200. The behavior-first regression failed before repair (expected 403, got 200). Added `Gate::authorize('manage-settings')` at the method boundary; the focused test now denies access. This matches sibling edit actions.

## Attacks and findings

- Four changed Settings master lists use 25-row pagination; the existing 101-row test reaches/searches row 101. Company/location scopes and search OR terms are grouped in the Eloquent queries. Existing scope tests verify hidden rows stay hidden, but no separate hostile search-term fixture combines hidden scope with every OR branch.
- Inactive master-list tables are not queried in the active Payment tab test. That test has a blanket `Gate::before(... => true)`: it validates query/presentation behavior, not authorization. Actual settings route/component gates, direct action checks, audit-tab gate, and audit export policy were inspected; actual denial tests also ran. The new stale-snapshot test covered the missing action gate without a blanket grant.
- Printer and template lists use separate paginator keys (`page` and `templates_page`). Relationship reads are eager-loaded, so no per-row N+1 was apparent; no numeric query-count ceiling is asserted. Template-to-printer child rows and printer-form branch/store/template options remain unbounded and un-stress-tested, a static scale risk only.
- Printer/template list rows omit IP, port, notes, and template header/footer/layout. The confirmed template-field exposure path was the ungated edit action above, now fixed. Audit export remains denied without an approved configured limit.

## Exact checks and limits

- Only disposable XAMPP schema `qa_rajh_m1_audit_20261007`; applied only the pending additive drawer `lock_version` migration before tests.
- RED 1 test / 1 assertion (expected 403, received 200); GREEN 18 tests / 114 assertions across the focused settings, authorization, and audit files.
- Pint passed using the regression worktree binary because the copied local launcher embeds a stale absolute path. PHP lint, `git diff --check`, and exact-root CodeGraph sync/status passed.
- No full suite, browser/device/physical-printer check, production access, push, merge, or deployment. No query latency/high-volume stress was run. M1-F18 remains `PARTIAL`. Raw Settings-history JSON was not classified as a leak absent a reproduced unauthorized field disclosure; broader value-redaction policy review is deferred.
