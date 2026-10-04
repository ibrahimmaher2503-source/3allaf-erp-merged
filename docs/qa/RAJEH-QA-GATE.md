# Rajeh ERP QA Gate

```bash
./scripts/qa-gate.sh fast
./scripts/qa-gate.sh release
```

Fast is routine development verification. Release is operator-triggered for major releases or client handover. Neither command deploys code. Use a dedicated development worktree with dependencies and built assets; the gate refuses `.env` and cached runtime configuration, fixes QA to a non-production environment, and uses socket-only disposable MariaDB resources. Production application releases, shared databases and credentials are never targets.

## Fast

Fast checks runtime/tool availability, the current developer CodeGraph root/index presence without initialization or synchronization, changed PHP syntax, Larastan/PHPStan level 7 on five critical arithmetic files, the focused calculator suite, real catalog create/edit/cost/filter/identity/denial/failure actions, schema/database invariants, scheduler/runtime configuration and a compact public browser smoke. It uses one temporary MariaDB schema across its database/browser sections; it runs no historical full suite, Semgrep, Infection, backup or broad browser matrix.

The scoped static baseline records twelve existing exact message/file/count numeric-string inference findings. It does not baseline the full application: the preserved earlier broad attempt found 1,404 diagnostics, which remain debt. New diagnostics in the scoped files fail. Baseline entries must be reviewed, never regenerated automatically to pass a gate.

## Release

Release adds a real application action chain: supplier/product -> approved purchase order -> received invoice -> automatic distribution transfer -> approved price -> cashier shift -> sale/payment -> stock consumption -> return/refund -> closing submission. It checks immutable totals, linked payment/refund records, inventory cost/quantity, idempotent replay, stock-failure rollback, denial, excess-return rejection and audit evidence. The invariant scanner covers declared foreign keys, identities/pivots, allowed statuses, migration coverage, purchase/sale totals, stock ledger, settled payments/refunds, over-returns, effective-price overlaps and filter/variant assignments.

Release also generates actual XLSX and bilingual PDF outputs with independent cost-view permission checks; renders bilingual 58/80mm receipts from a real posted sale; runs four local Semgrep PHP rules; runs scoped Infection arithmetic mutations; dumps only the disposable populated QA database, extracts it through the existing `platform:backup:restore`, imports it into a second explicitly named disposable database and reconciles seven table counts; and runs authenticated Admin/User/Cashier login/logout, form, denial, English/Arabic, desktop/mobile and console/overflow checks.

Browsers use private, random credentials for disposable accounts and a loopback server only. The ordinary User's expected 403 document responses are separated from unexpected JavaScript/resource failures. No production session or user is used. Backup verification is a logical isolated restore/count check, not recovery of an encrypted production backup or an RPO/RTO drill. Shift testing reaches closing submission; reviewer final closure and real cashier observation remain separate acceptance work.

## Mutation interpretation

The initial enforced covered MSI minimum is 50%; the improvement target is 70%. A score between them produces `PASS WITH WARNINGS`. Scope is covered pricing, tax, discount, rounding, cost and quantity arithmetic in five files. Database-backed payment/refund actions are integration-tested, not mutated.

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

`QA_PHP`, `QA_CHROMIUM`, and `QA_PCOV_EXTENSION` can select existing local tools. Playwright's browser is preferred; this host's existing isolated Chromium cache is a fallback. The commands work from the configured QA worktree; a new worktree requires its own setup. GitHub Fast installs only Fast dependencies, prepares its own Chromium/assets, uses native disposable MariaDB and uploads reports. Semantic indexing is excluded from CI. The workflow has no Release/deployment job; execution on GitHub is reported separately from local checks. This preserved-checkpoint publication uses `[skip ci]` to avoid repeating completed passing verification; future ordinary pushes/PRs run Fast.

## Checkpoints and reports

JSON/Markdown reports are under `.ai/qa/`; timestamped logs and raw mutation records are ignored local evidence. Committed dated snapshots preserve the original failed run and final continuation results. `QA_RESUME_REPORT=/absolute/path/report.json` deliberately reuses completed sections, including completed warnings. Its application-source identity must match and runtime source must be clean. Review section-specific QA inputs before resuming; invalidate only the affected row. Default commands run fresh checks.

`QA_PRESERVE_RUNTIME=1` retains only the stopped temporary QA data directory after a run; `QA_REUSE_RUNTIME=1` resumes the recorded socket-only schema without reinstalling or remigrating it. Private credentials/sessions must be removed with that directory at closure. `QA_INTEGRITY_SCAN_ONLY=1`, `QA_RECONCILE_FROM`, and `QA_BROWSER_ROLE`/`QA_BROWSER_FAILURE_ONLY` are continuation controls, not routine Gate settings. Do not set them for an ordinary release.

## October 4 result and limits

Final Fast: **PASS**, final resumed invocation **15s** (22s across its recorded gate continuations). Final Release: **PASS WITH WARNINGS**, final resumed invocation **24s** (166s across recorded Release gate continuations). Successful sections were retained; these are continuation durations, not a fresh complete Release benchmark. Standalone reconciliation/security were not individually timed. Mutation initial run was 77s; survivor replay was 71.64s. Authenticated browser evidence comprises 76 preserved successful assertions and two resumed User console checks, **78 total**. Calculator regressions: **14 tests / 79 assertions**.

Native socket-only disposable MariaDB is the owner-directed preserved QA workflow; no global database-policy change or shared/production database authority follows. The 53.41% mutation score, scoped static debt, narrow security rules, unmutated DB paths, unexecuted GitHub workflow, on-site printers/scanner/drawer/payment devices/offline continuity and human UAT remain explicit limits. No business milestone or handover gate is advanced by this tooling task.
