# Rajeh ERP QA Gate

```bash
./scripts/qa-gate.sh fast
./scripts/qa-gate.sh release
```

Fast is routine development verification. Release is operator-triggered for major releases or client handover. Neither command deploys code. Use a dedicated development worktree with dependencies and built assets; the gate refuses `.env` and cached runtime configuration, fixes QA to a non-production environment, and uses socket-only disposable MariaDB resources. Production application releases, shared databases and credentials are never targets.

## Fast

Fast checks runtime/tool availability, the current developer CodeGraph root/index presence without initialization or synchronization, changed PHP syntax, Larastan/PHPStan level 7 on five critical arithmetic files plus the touched product-card transaction, the focused calculator suite, real catalog create/edit/cost/filter/identity/denial/failure actions, schema/database invariants, scheduler/runtime configuration and a compact public browser smoke. It uses one temporary MariaDB schema across its database/browser sections; it runs no historical full suite, Semgrep, Infection, backup or broad browser matrix.

The arithmetic baseline records twelve existing exact message/file/count numeric-string inference findings. Phase 2 adds three exact pre-existing product-action findings after an identical before/after comparison. It does not baseline the full application: the preserved earlier broad attempt found 1,404 diagnostics, which remain debt. New diagnostics in the scoped files fail. Baseline entries must be reviewed, never regenerated automatically to pass a gate.

## Release

Release adds a real application action chain: supplier/product -> approved purchase order -> received invoice -> automatic distribution transfer -> approved price -> cashier shift -> sale/payment -> stock consumption -> return/refund -> closing submission. It checks immutable totals, linked payment/refund records, inventory cost/quantity, idempotent replay, stock-failure rollback, denial, excess-return rejection and audit evidence. The invariant scanner covers declared foreign keys, identities/pivots, allowed statuses, migration coverage, purchase/sale totals, stock ledger, settled payments/refunds, over-returns, effective-price overlaps and filter/variant assignments.

Release also generates actual XLSX and bilingual PDF outputs with independent cost-view permission checks; renders bilingual 58/80mm receipts from a real posted sale; runs four local Semgrep PHP rules; runs scoped Infection arithmetic mutations; dumps only the disposable populated QA database, extracts it through the existing `platform:backup:restore`, imports it into a second explicitly named disposable database and reconciles seven table counts; and runs authenticated Admin/User/Cashier login/logout, form, denial, English/Arabic, desktop/mobile and console/overflow checks.

Browsers use private, random credentials for disposable accounts and a loopback server only. The ordinary User's expected 403 document responses are separated from unexpected JavaScript/resource failures. No production session or user is used. Backup verification is a logical isolated restore/count check, not recovery of an encrypted production backup or an RPO/RTO drill. Phase 1 reached closing submission; Phase 2 adds concurrent submission and reviewer final closure in the disposable fixture. Real cashier observation remains separate acceptance work.

## Mutation interpretation

Phase 1 enforced covered MSI minimum 50%, improvement target 70%. Phase 2 enforces reviewed MSI minimum 85%, preferred 90%, and retains a visible warning while raw MSI is below 85%. Scope is covered pricing, tax, discount, rounding, cost and quantity arithmetic in five files. Database-backed payment/refund actions are integration-tested, not mutated.

The October 4 continuation rejected an apparent 100% Infection score: its PHPUnit stdout parser did not recognize JSON-only passing results. `phpunit-infection.php` preserves the underlying process exit status and emits the canonical success footer for successful JSON summaries. Preserved process outputs plus a replay of only 235 previously misclassified survivors yielded **219/410 kills, 191 survivors, MSI 53.41%**. See `.ai/qa/mutation-summary.md`. This warning is not full financial/security assurance or client handover approval.

## Setup

Existing lockfiles already contain Larastan/PHPStan, PHPUnit, Infection and Playwright. Install dependencies once per development worktree, prepare assets once when required, and follow `AGENTS.md` for that worktree's own excluded CodeGraph index. Do not rerun these setup steps during a checkpoint continuation.

```bash
composer install --no-interaction --prefer-dist --no-scripts
npm ci --ignore-scripts
npx playwright install chromium
npm run build
```

MariaDB server/client utilities, Python 3, curl and Node 20+ are required. Release additionally requires Semgrep and PCOV or Xdebug coverage. PCOV 1.0.12 was compiled locally into `.qa-runtime/pcov/pcov.so` using the selected PHP's phpize/php-config; it was not installed in global or production PHP. Follow [official PCOV installation instructions](https://github.com/krakjoe/pcov/blob/develop/INSTALL.md) for a new environment. No release dependency installer or deployment action runs automatically.

`QA_PHP`, `QA_CHROMIUM`, and `QA_PCOV_EXTENSION` can select existing local tools. Playwright's browser is preferred; this host's existing isolated Chromium cache is a fallback. The commands work from the configured QA worktree; a new worktree requires its own setup. GitHub Fast installs only Fast dependencies, prepares its own Chromium/assets, uses native disposable MariaDB and uploads reports. Semantic indexing is excluded from CI. The workflow has no Release/deployment job; execution on GitHub is reported separately from local checks. Phase 1 publication used `[skip ci]` to preserve passing evidence. Phase 2 publication triggers the existing Fast workflow; actual GitHub results are recorded separately.

## Checkpoints and reports

JSON/Markdown reports are under `.ai/qa/`; timestamped logs and raw mutation records are ignored local evidence. Committed dated snapshots preserve the original failed run and final continuation results. `QA_RESUME_REPORT=/absolute/path/report.json` deliberately reuses completed sections, including completed warnings. Its application-source identity must match and runtime source must be clean. Review section-specific QA inputs before resuming; invalidate only the affected row. Default commands run fresh checks.

`QA_PRESERVE_RUNTIME=1` retains only the stopped temporary QA data directory after a run; `QA_REUSE_RUNTIME=1` resumes the recorded socket-only schema without reinstalling or remigrating it. Private credentials/sessions must be removed with that directory at closure. `QA_INTEGRITY_SCAN_ONLY=1`, `QA_RECONCILE_FROM`, and `QA_BROWSER_ROLE`/`QA_BROWSER_FAILURE_ONLY` are continuation controls, not routine Gate settings. Do not set them for an ordinary release.

## October 4 result and limits

Final Fast: **PASS**, final resumed invocation **15s** (22s across its recorded gate continuations). Final Release: **PASS WITH WARNINGS**, final resumed invocation **24s** (166s across recorded Release gate continuations). Successful sections were retained; these are continuation durations, not a fresh complete Release benchmark. Standalone reconciliation/security were not individually timed. Mutation initial run was 77s; survivor replay was 71.64s. Authenticated browser evidence comprises 76 preserved successful assertions and two resumed User console checks, **78 total**. Calculator regressions: **14 tests / 79 assertions**.

Native socket-only disposable MariaDB is the owner-directed preserved QA workflow; no global database-policy change or shared/production database authority follows. The 53.41% mutation score, scoped static debt, narrow security rules, unmutated DB paths, unexecuted GitHub workflow, on-site printers/scanner/drawer/payment devices/offline continuity and human UAT remain explicit limits. No business milestone or handover gate is advanced by this tooling task.

## Phase 2 — critical financial QA hardening

Method: retain Phase 1 successful evidence, classify every original survivor, strengthen observable financial assertions, then replay only survivors after focused additions. Mutation scope remains the five arithmetic/contract files; database actions are exercised through real transactions, not added to Infection's scope. A final scoped run accounts for newly covered branches; it is not another system audit. `phase2-mutation-review.json` pins each equivalence/unreachable decision to its exact file, location, patch and source SHA. No whole mutator is disabled. Source changes invalidate reviewed exclusions. Raw covered MSI and survivor counts always remain visible; an adjusted denominator removes only individually justified equivalents/unreachable guards. Unproven precision/coercion cases remain counted. The minimum reviewed target is 85%, preferred 90%; raw below 85 remains a warning.

Financial matrix: fixed seed **20261004**, an independent Python Fraction oracle, exact whole-product quantities, half-away money rounding, four-place purchase snapshots and eight-place purchasing intermediate precision. Cases include 0/14/15/100/0.0001/99.9999% tax, inclusive/exclusive modes, fixed/percentage/line/document discounts, stable largest remainder/ties, zero/full discounts, signed rounding at scales 0/1/2/4/6, extra input precision, large valid money/cost/quantity boundaries, cash denominations and exact four-place inclusive-tax boundaries. Decimal product quantities are rejected; no new fractional-quantity business policy is invented. Product-card persistence retains its existing two-place schema while purchase/inventory history preserves four places.

Concurrency strategy: separate socket-only MariaDB hardening fixture, private disposable accounts, two independent PHP/Laravel connections and a parent-held resource lock. Both workers enter together and must be demonstrably blocked before release. MariaDB prepared-statement process-list observations supplement unavailable InnoDB transaction rows; refund advisory-lock contention is recorded explicitly. Validate final balances/ledgers/snapshots and one winner/safe loser or idempotent replay outcomes, not only worker completion. Journal successful cases and races to resume only unfinished assertions. Release's hardening runner uses a separate disposable database so race fixtures cannot contaminate the proven Phase 1 accounting/restore chain; it removes its runtime automatically.

Scenarios: shift open/close and reviewer approval double-submit; supplier item-code allocation/retry; last-unit stock decrement and same-key replay; partial/over-limit payment capture/replay and immutable payment records; split tender/partial returns/refunds and historical cost/tax/price snapshots; purchase approval/automatic distribution/destination receiving; concurrent immediate price activation; refundable quantity reservation and same-key refund completion. Five new reusable invariants detect over-capture, over-refund and duplicate/terminal active-shift assignments. Existing passing scanner rules are preserved.

Confirmed runtime repairs are limited to inclusive tax at 0.04/14%, exact largest-remainder allocation at close large weights, and Laravel's three-attempt whole-product deadlock retry. Actual sale snapshots, codes, barcodes, sequence increments and audit counts verify the corrections. No migration, historical data rewrite, new permission, production data mutation or deployment occurred. A separate established production release task must deliver these fixes.

FAST retains small representative contract regressions and controlled static analysis; it excludes matrix/concurrency/mutation work. RELEASE adds the stronger matrix and isolated financial concurrency checks, plus the full approved mutation scope. Existing Semgrep/browser/restore results remain valid for unchanged scope; Phase 2 ran security only on eight changed files. Actual Fast/Release durations, final scores and GitHub CI status are recorded in the dated Phase 2 evidence below at closure.

Known limits: mutation scope does not include database-backed actions; concurrency proves specified two-writer scenarios rather than arbitrary stress, scheduled future-price activation, external gateway settlement or every caller's nested transaction; three old product static findings and historical broad static debt remain. Raw mutation survivors, uncertain precision cases and error-message/coercion paths remain documented. Physical devices, offline behavior, client UAT and a real observed cashier shift remain external acceptance work.

Phase 2 mutation result: **434/547 covered kills**, **113 raw survivors**, **79.34% raw corrected MSI**; **86 individually reviewed equivalent/unreachable exclusions**, **27 retained survivors**, **94.14% reviewed MSI**. Errors, syntax errors, timeouts and Infection-configured ignores: zero. Minimum 85% and preferred 90% are reached only on the documented reviewed denominator; raw MSI remains a visible warning. With 86 proven equivalents/unreachable guards, even killing every remaining survivor would leave raw MSI below 85%; artificial tests or blanket ignores are prohibited. The final scoped run took 124s; one newly covered zero-default survivor was killed in a 0.58s affected-only replay. Twelve unproven precision/native-comparison cases and fifteen low-value message/coercion cases remain visible in the exact review manifest.

Cold local Fast took **104s**, with 78s in disposable schema preparation; the 25 focused tests/126 assertions took **88ms**. A focused non-production I/O experiment took 73s for the DB section and did not materially improve it; established temporary-server options were restored. This host's cold schema preparation remains a known Fast cost. No check was weakened or removed to improve timing. GitHub's actual cold duration remains to be measured.

Final Phase 2 Release: **PASS WITH WARNINGS**. Final checkpoint report assembly took 0s; this is not a fresh whole-Release benchmark. Newly necessary mutation sections took 124.58s; successful financial case journals record 15.55s, excluding cold schema setup and earlier partial-case attempts. All sixteen Release rows are backed by preserved Phase 1 or actual Phase 2 receipts. See `.ai/qa/2026-10-04-phase2-{fast,release}.json` and `.ai/qa/phase2-evidence/`. No completed broad browser/security/restore check was repeated.

GitHub Fast remains enabled for ordinary task-branch pushes/PRs. A bounded shallow-checkout reproduction confirmed that `origin/main` can be absent and the old syntax helper could silently fall back. The QA workflow now fetches only the main comparison commit; the helper fails when that baseline is missing. Both missing-reference rejection and known-baseline syntax paths were validated; read-only workflow permissions and production deployment are unchanged. Local Fast passed. Owner confirmed GitHub installation/connection; this conversation still exposes no repository/Actions tool or authenticated CLI/API, so actual GitHub execution is **pending verification**. Publishing the Phase 2 branch triggers the configured workflow; main integration is held until the required CI result can be checked. These limitations do not convert local checks into GitHub success.
