# 14 — Test Plan

**Product:** TOY & JOY
**Phase:** Phase 1 — Retail Stores, Inventory, POS, and Party Operations
**Status:** Baseline test plan for the approved Phase 1 functional scope
**Scope authority:** `docs/02-product-requirements.md` is the only business-scope authority. This plan tests what the PRD approved; it does not add capability, limits, or policy.

## Contents

1. Purpose and Testing Principles
2. What Must Be Proven Before Phase 1 Ships
3. Test Levels and Triggers
4. Test Types and Tooling
5. Risk Tiers and Coverage Requirements
6. Entry and Exit Criteria
7. Environments
8. Roles and Responsibility
9. Reporting
10. Detailed Testing Documents

## 1. Purpose and Testing Principles

TOY & JOY is a financial and inventory system of record for a live retail and party business. A defect here does not produce a bad screen — it produces a wrong stock balance, a duplicate invoice number, a customer charged twice, or a cashier seeing a party debt they must never see. The test strategy is therefore built around **proving that prohibited things cannot happen**, not only that permitted things work.

### Principle T-01 — Blocked transitions are first-class tests

The PRD and flows define a large set of *blocked* transitions (Cross-Flow Blocked Transitions in `docs/06-user-flows.md`). Each blocked transition is a mandatory negative test. A feature is not covered when only its happy path passes.

### Principle T-02 — Authorization is tested at the server, not the screen

`NFR-03` requires API/service-level authorization. Every scope test must prove denial through direct request/action invocation, not only through a hidden button. Hiding a menu item is not a passing result.

### Principle T-03 — History is tested, not just state

`NFR-01` and `NFR-02` make audit and immutability part of the functional contract. A mutation test that asserts the new balance but not the audit record and the preserved original is an incomplete test.

### Principle T-04 — Money and stock require concurrency tests

Any flow touching document numbering, stock balance, wallet, loyalty, gift card, or shift totals requires a concurrency/idempotency test. Single-threaded correctness is insufficient for `NFR-06` and `AC-XCUT-09`/`AC-XCUT-14`.

### Principle T-05 — Separation is tested from both directions

Retail↔party separation (`PTY-01`, `CUS-02`) must be tested as: retail actor blocked from party data, *and* party actor blocked from retail data. Testing one direction leaves the other unproven.

### Principle T-06 — Automated results are the only completion signal

A task, milestone, or release is complete when the test orchestrator exits zero and its report is attached — not when the implementer believes the code is correct. See `docs/testing/07-agent-test-protocol.md`.

### Principle T-07 — Human-only checks are never claimed by tooling or by an agent

UAT sign-off, physical device testing, disaster-recovery exercises, and manual visual review require a named human and a date. No script and no AI agent may mark these complete. An unchecked box is an honest state; a checked box without a name is a false one.

## 2. What Must Be Proven Before Phase 1 Ships

These are the non-negotiable proof obligations derived directly from the PRD's acceptance scenarios (§17). Each maps to a suite in `docs/testing/03-test-catalog.md`.

| # | Proof obligation | Primary requirements |
|---|---|---|
| P-01 | A branch, selling store, drawer, price list, roles, and printer config can be created; unauthorized roles cannot alter them. | MD-01, NFR-03 |
| P-02 | A purchase invoice raises store stock and weighted-average cost but never changes a sale price. | PUR-05, PRC-03 |
| P-03 | A price approval creates an immutable version, preserves historical sales, updates remaining-stock price, and generates a location-correct label queue. | PRC-04, PRC-05, PRC-06 |
| P-04 | A cashier sells only assigned-store stock, records payment evidence, applies optional tax, applies exactly one discount type, and prints the required layout. | POS-01–POS-06, INV-02 |
| P-05 | A Gift Receipt carries no price and still validates a later return. | POS-07, RET-01 |
| P-06 | Returns support same-item exchange, different-item exchange, refund, and gift-card settlement with condition inspection. | RET-01–RET-03 |
| P-07 | A stock count runs while sales continue, reconciles correctly, and never zeroes uncounted items. | INV-07–INV-09 |
| P-08 | A party invoice takes multiple payments on account with individual receipts, stays editable until close, and settles independently of Product Wallet. | PTY-03, PTY-04, PTY-06, CUS-02 |
| P-09 | A rental asset cannot be double-booked and retains checkout/return/damage history. | AST-02–AST-04 |
| P-10 | Role permissions block cross-activity wallet exposure and unauthorized approve/export/override/adjust/cancel. | CUS-02, NFR-03 |
| P-11 | Dashboard widgets, alerts, PDF/Excel exports, and audit logs respect authorized branch/store scope. | RPT-01–RPT-03, NFR-05 |
| P-12 | Offline POS accepts only permitted operations, and server truth prevails on sync with every conflict queued for review. | Offline mode, NFR-04, NFR-06 |

**Rule:** No proof obligation may be marked satisfied by a manual demo alone. Each requires at least one automated test that fails when the behaviour regresses.

## 3. Test Levels and Triggers

Three levels. Each has a fixed trigger, a fixed command, and a fixed report.

### Level 1 — Per Task

**Trigger:** any implementation task is believed complete, before it is handed over or marked done.
**Command:** `bash scripts/run-tests.sh task`
**Purpose:** prove the changed unit of behaviour, cheaply and fast enough to run repeatedly.

Runs: Unit, Feature, Policy/Scope, Livewire, Integration, Focused Browser. Plus a manual visual review entry that stays unchecked until a human signs it.

### Level 2 — Per Milestone

**Trigger:** a milestone (e.g. DM 1.1 — Platform Foundation) has all its tasks at Level-1 green.
**Command:** `bash scripts/run-tests.sh milestone`
**Purpose:** prove that the milestone's tasks did not break each other or anything before them.

Runs everything from Level 1, plus: full regression, static/security analysis, dependency audit, data reconciliation, visual regression, accessibility, performance smoke, and a migration-from-clean rebuild.

### Level 3 — Pre-Production

**Trigger:** a release candidate is proposed for the live business.
**Command:** `bash scripts/run-tests.sh preprod`
**Purpose:** prove the system survives real load, real data volume, real hardware, and real recovery — and that a human with business authority has accepted it.

Runs everything from Level 2, plus load test and backup/restore drill. Then blocks on the human checklist: UAT sign-off, disaster-recovery exercise, security review, physical printer/scanner/device test, production-like migration rehearsal, and full browser/device matrix.

### Level Escalation Rule

A change that touches any of the following is treated as milestone-level even if it is a single task: document numbering, stock movement posting, wallet or loyalty ledgers, gift-card balance, price approval, shift close calculation, permission resolution, or offline sync. These are the areas where a local change has non-local consequences.

## 4. Test Types and Tooling

| Test type | Proves | Tool | Location |
|---|---|---|---|
| Unit | Isolated calculation and rule logic: weighted-average cost, discount resolution, tax, variance, loyalty rounding, barcode format, reconciliation arithmetic. | Pest | `tests/Unit/` |
| Feature | HTTP/action-level behaviour end to end through the application container, including validation and side effects. | Pest | `tests/Feature/` |
| Policy/Scope | Server-side authorization: role, branch, store, document, field, action, and cross-activity denial. | Pest | `tests/Feature/Policy/` |
| Livewire | Component state, validation feedback, blocked UI transitions, and that the component enforces rather than decorates rules. | `Livewire::test()` | `tests/Feature/Livewire/` |
| Integration | Multi-module effects in one transaction: invoice → stock movement → cost → audit; party close → payments → wallet. | Pest | `tests/Integration/` |
| Focused Browser | The specific user-visible flow that changed, in a real browser, including RTL and mobile viewport. | Laravel Dusk, `--group=focused` | `tests/Browser/` |
| Concurrency | Duplicate numbers, double-posting, stale overwrite, double-booking, race on balances. | Pest + parallel processes / DB locks | `tests/Integration/Concurrency/` |
| Full regression | Nothing previously proven has broken. | `php artisan test --parallel` | all suites |
| Static analysis | Type and contract errors not reachable by tests. | Larastan/PHPStan | project root |
| Security | Known-vulnerable dependencies, unsafe config, exposed debug, weak session/storage handling. | `composer audit`, Enlightn | project root |
| Data reconciliation | Ledger and balance integrity: stock movements sum to on-hand, wallet ledger sums to balance, payments sum to invoice totals, no orphan audit. | Custom `php artisan recon:check` | `app/Console/Commands/` |
| Visual regression | Unintended layout change, especially RTL breakage. | BackstopJS | `backstop.json` |
| Accessibility | Keyboard operation, labels, contrast, focus order on operational screens. | axe-core via Dusk, `--group=a11y` | `tests/Browser/` |
| Performance smoke | Obvious regressions: N+1 queries, missing indexes, unbounded list queries. | k6 + query-count assertions | `scripts/smoke.k6.js` |
| Migration-from-clean | A brand-new deployment builds correctly from migrations and seeders alone. | `migrate:fresh --seed` on isolated DB | CI/orchestrator |
| Load | Sustained and peak concurrent branch usage. | k6 | `scripts/load.k6.js` |
| Backup/restore | A backup is genuinely restorable and complete. | `scripts/backup-restore-drill.sh` | `scripts/` |
| Manual visual review | Bilingual layout, print output realism, operational sensibility. | Human + checklist | `docs/testing/05-manual-checklists.md` |
| UAT | The business accepts the behaviour as correct for their operation. | Human stakeholder | same |
| Physical device | Thermal printer, A4 printer, label printer, barcode scanner, tablet POS hardware. | Human + real devices | same |
| Disaster recovery | The business can actually be restored to operation after loss. | Human ops exercise | same |

### Tooling Prerequisites

The orchestrator reports `SKIPPED` when a tool is absent rather than silently passing. Before Level-2 runs are meaningful, install: Pest, Laravel Dusk, Larastan, Enlightn, BackstopJS, axe-core, k6. A `SKIPPED` count above zero at milestone level is a blocking finding, not a warning.

## 5. Risk Tiers and Coverage Requirements

Not all 72 requirements carry equal consequence. Coverage effort follows risk.

### Tier A — Irreversible financial or stock consequence

`PUR-05`, `PRC-04`, `PRC-05`, `INV-03`, `INV-05`, `INV-08`, `INV-09`, `POS-02`, `POS-05`, `RET-02`, `RET-04`, `CUS-02`, `CUS-03`, `CSH-02`, `CSH-03`, `PTY-04`, `PTY-06`, `AST-02`, `NFR-01`, `NFR-02`, `NFR-06`, offline sync.

**Required:** Unit + Feature + Policy + Integration + Concurrency + negative/blocked-transition tests + audit assertion + Focused Browser. No Tier-A requirement may ship with only Feature tests.

### Tier B — Operational correctness with recoverable consequence

`MD-01`–`MD-06`, `PRC-01`–`PRC-03`, `PRC-06`–`PRC-08`, `PUR-01`–`PUR-04`, `PUR-06`, `INV-01`, `INV-02`, `INV-04`, `INV-06`, `INV-07`, `POS-01`, `POS-03`, `POS-04`, `POS-06`, `POS-07`, `RET-01`, `RET-03`, `CUS-01`, `CUS-04`, `CSH-01`, `CSH-04`, `PTY-01`–`PTY-03`, `PTY-05`, `AST-01`, `AST-03`–`AST-05`, `NFR-03`–`NFR-05`.

**Required:** Feature + Policy + at least one of Livewire/Integration + blocked-transition tests where the flow defines them.

### Tier C — Presentation, reporting, and non-posting

`QTN-01`–`QTN-03`, `RPT-01`–`RPT-03`, `NFR-07`.

**Required:** Feature + Policy/Scope + export/print safety tests. Quotations additionally require an explicit test that **no** stock, reservation, payment, wallet, or accounting effect is created (`QTN-02`).

## 6. Entry and Exit Criteria

### Entry criteria — before testing a task

- The task's PRD requirement IDs and flow IDs are identified and written into the test file docblock.
- Test data can be built from factories/seeders without manual database editing.
- The relevant acceptance criteria in `docs/07-acceptance-criteria.md` are readable and unambiguous. Ambiguity is raised, not guessed.

### Exit criteria — Level 1 (task)

- Automated failures: zero.
- Every blocked transition named in the task's flows has a passing negative test.
- Every sensitive mutation asserts its audit record in the same test.
- Manual visual review box present and unchecked, awaiting a human.

### Exit criteria — Level 2 (milestone)

- All Level-1 criteria across the milestone's tasks.
- Full regression green.
- `SKIPPED` count is zero.
- `recon:check` reports no imbalance.
- Migration-from-clean succeeds on an empty database.
- Visual regression differences are all reviewed and explicitly accepted.
- Accessibility failures on operational screens (POS, counting, party ops) are zero.

### Exit criteria — Level 3 (pre-production)

- All Level-2 criteria.
- Load test thresholds met at expected peak concurrency.
- Backup restored into a clean database with matching row counts on critical tables.
- Every human checklist item carries a real name and date.
- All Tier-A requirements traced to passing tests in the traceability matrix with no gaps.

**No exception process is defined for Tier-A gaps.** If a Tier-A requirement is untested, the release is not ready, regardless of schedule.

## 7. Environments

| Environment | Purpose | Data | Notes |
|---|---|---|---|
| Local | Development and Level-1 runs. | Factories + seeders only. | Never contains real customer data. |
| Testing (`--env=testing`) | Automated suite execution and migration-from-clean. | Rebuilt per run. | Isolated database; destructive operations are expected here and only here. |
| Staging | Level-2 and Level-3 runs, load tests, browser matrix, UAT. | Anonymised or synthetic data at realistic volume. | Mirrors production configuration including printers where possible. |
| Production | Live business. | Real data. | **No test suite, load test, or drill runs here.** Backup drills read from a copy. |

### Data Protection Rule

Load tests, backup drills, and browser automation never run against production. Any staging copy of production data must have customer phone numbers, names, children's data, and consent records anonymised before use — customer children's birthday data (`MD-06`) is sensitive and must not exist in a test environment in identifiable form.

## 8. Roles and Responsibility

| Activity | Responsible | Cannot be delegated to tooling or an AI agent |
|---|---|---|
| Writing automated tests | Implementer | — |
| Running Level 1/2/3 suites | Implementer or CI | — |
| Reviewing visual regression diffs | Implementer | — |
| Manual visual review sign-off | Human reviewer | ✅ human only |
| UAT acceptance | Business stakeholder | ✅ human only |
| Physical device testing | Human tester with hardware | ✅ human only |
| Disaster recovery exercise | Operations owner | ✅ human only |
| Security review | Reviewer with security scope | ✅ human only |
| Production go/no-go | Product owner | ✅ human only |

## 9. Reporting

Every orchestrator run writes a timestamped Markdown report to `docs/testing/reports/`. The report contains: pass/fail/skip counts, per-check duration, full failure logs, and the unchecked human checklist for that level.

Reports are evidence. They are committed with the work they certify and are never edited by hand to change a result. Correcting a failure means fixing the code and re-running, which produces a new report.

## 10. Detailed Testing Documents

| Document | Contains |
|---|---|
| `docs/testing/01-test-taxonomy-and-triggers.md` | Every test type, its exact command, when it runs, and what a real pass looks like. |
| `docs/testing/02-traceability-matrix.md` | All 72 PRD requirement IDs mapped to flows, risk tier, required test types, and test file paths. |
| `docs/testing/03-test-catalog.md` | Concrete test cases per module, including mandatory negative cases from the blocked-transition list. |
| `docs/testing/04-cross-cutting-test-suite.md` | Tests for AC-XCUT-01 to AC-XCUT-16: approval, attachments, immutability, numbering, idempotency, scope, export, print, audit, error, concurrency. |
| `docs/testing/05-manual-checklists.md` | Manual visual review, milestone review, UAT, device matrix, DR exercise, and production go/no-go — all with signature fields. |
| `docs/testing/06-test-data-and-environments.md` | Factories, seeders, scenario builders, anonymisation, and environment reset procedure. |
| `docs/testing/07-agent-test-protocol.md` | Rules governing how an AI agent runs and reports tests, and what it may never claim. |

---

**Disclaimer:** This test plan decomposes already-approved PRD requirements into testable obligations. It introduces no new Phase 1 business capability, production value, permission, limit, state, or commercial policy.
