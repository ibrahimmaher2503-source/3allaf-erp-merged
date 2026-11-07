# Module 1 audit red-team result — 2026-10-07

**Reviewed integration:** `07a514c94d106efe3516d81780ee9081ecfd86bc`  
**Branch:** `hotfix/module1-redteam-audit-20261007`  
**Boundary:** Settings master-list searches, pagination, scoping, relationships/query behavior, inactive-tab work, printer/template pagination, rendered-data exposure, and actual permission checks.

## Confirmed finding and repair

**REDTEAM-AUDIT-01 — confirmed authorization gap, fixed here.** `editPrintTemplate()` loaded a `PrintTemplate` through its company visibility scope and copied the full model into the public `templateForm`, including header, footer, and layout fields, without reauthorizing `manage-settings`. The component only checked that permission in `mount()`; `rendering()` did not recheck it. A still-authenticated user could retain the signed component state from an authorized page, lose the role permission, and call the Livewire action. The template company remained visible through the user's branch/store scope, so model visibility alone did not enforce the revoked edit permission.

The focused test first failed as expected: after mounting with a real `company_settings.edit` role and revoking the permission, the component action returned **200** where the test required **403** (1 failed test / 1 assertion). The minimal repair adds `Gate::authorize('manage-settings')` at the start of `editPrintTemplate()`, matching the sibling `editPrinter()` and other protected edit actions. The same test then passed with the focused suite.

## Attacks and results

- **Large master lists / pagination:** The four lists changed by M1-EX03 (payments, taxes, document sequences, printers) return 25 rows per page and support search. The existing scale test reaches row 101 on page five and by search. It also checks page/search reset behavior. Search predicates are nested under a closure after the base scope, so each set of OR terms remains grouped with the company/location restriction.
- **Cross-scope search:** Inspected `TaxSetting::forCompany`, `DocumentSequence::visibleTo`, `PrinterConfiguration::visibleTo`, `PrintTemplate::visibleTo`, and the settings search closures. The printer scope's company/branch/store OR predicates are parenthesized; the free-text OR branches are nested in a second `where`. No source-level scope bypass was found. Existing scope tests exercise allowed/hidden rows without search; no separate runtime fixture combining a hidden-scope match with each search OR branch was added.
- **Inactive tabs:** The settings master-list query test records SQL while displaying Payments and asserts the other master tables are not read. This establishes presentation/query routing only: `Module1MasterScaleTest` uses `Gate::before(... => true)` and is not evidence of authorization. Actual gates were inspected separately: the settings route requires `company_settings.view`, component mount requires `manage-settings` (`company_settings.edit`), the audit tab checks `audit_logs.view`, and the protected direct actions check their relevant permission. `Module1AuthorizationBoundaryTest` exercises actual permission-denial paths, and the new stale-snapshot test exercises the missing per-action check.
- **Printer/template pagination:** Printer profiles use the default `page` paginator; templates use the distinct `templates_page` name. The code paths are separate. No behavioral defect was established, but a dedicated two-paginator interaction test is absent.
- **Relationships / N+1:** Sequence rows eager-load `scopeBranch`; printer rows eager-load `branch`, `store`, and `printTemplate`; template rows eager-load active visible printers and their branch/store. No per-row relationship query is visible in these render loops. Tests verify inactive master-table exclusion but do not assert a numeric query-count ceiling.
- **Rendered settings data:** Printer rows render name, assigned template name, scope label, type, and status; they do not render IP address, port, or notes. Template list rows render identifiers, document/paper/language, active printer names/scopes, and status, not header/footer/layout data. The action-level authorization finding above was the confirmed path for loading full template fields after permission revocation and is fixed.
- **Audit handling:** The settings-history table is bounded to the latest 20 master-data events and its OR source filter is grouped. Audit export remains policy-gated and blocked when no approved positive row cap is configured; the existing focused test verifies this. No export policy or limit was changed.

## Residual limits and declined findings

- The active template page eagerly loads every active visible printer linked to its 25 templates, then renders all compatible assignments. The printer modal also fetches all visible active branches, stores, and compatible templates on each printer-tab render, including while its modal is closed. This has no demonstrated N+1, but related-row volume is not bounded or stress-tested; it is a static scale risk, not a reproduced defect, and no query redesign was made in this review.
- Search uses substring `LIKE` patterns. No production-like volume, query plan, or latency benchmark was authorized or run; high-volume search performance remains unverified.
- Existing Settings history renders stored `before_values` and `after_values` JSON directly. The tested sources are configuration records, with branch/store visibility applied; no actual unauthorized sensitive-value leak was reproduced in this scope. A broader value-redaction policy review was declined rather than inferred from field names alone.
- No full suite, browser session, physical-printer/device check, production access, push, merge, or deployment was performed. M1-F18 remains source-status `PARTIAL`; 101-row functional pagination is not a high-volume stress result.

## Verification

- Disposable database: `qa_rajh_m1_audit_20261007` on the local XAMPP MySQL/MariaDB loopback. Only pending additive migration `2026_10_07_023742_add_lock_version_to_cash_drawers_table` was applied before testing; no reset/drop or SQLite was used.
- RED: `Module1SettingsAuthorizationRedteamTest.php` — 1 failed / 1 assertion, expected 403 but received 200.
- GREEN: `Module1SettingsAuthorizationRedteamTest.php`, `Module1MasterScaleTest.php`, `Module1AuthorizationBoundaryTest.php`, and `Module1AuditPresentationTest.php` — **18 tests / 114 assertions passed**.
- Pint passed using the intact regression-worktree Pint binary with this worktree as the current directory; this worktree's copied `vendor/bin/pint` launcher embeds an obsolete regression-worktree path and could not start.
- `php -l tests/Feature/Module1SettingsAuthorizationRedteamTest.php`, `git diff --check`, CodeGraph query/callers/impact and exact-root sync/status passed. No code outside the reported boundary was changed.
