# Testing 01 — Test Taxonomy and Triggers

**Parent:** `docs/14-test-plan.md`
**Purpose:** define exactly what each test type is, when it runs, the command that runs it, and what distinguishes a real pass from a hollow one.

## How to Read This Document

Each test type below has four parts:

- **Proves** — the specific claim the test type is allowed to support. A test type may not be cited as evidence for a claim outside this list.
- **Runs at** — Task / Milestone / Pre-production.
- **Command** — the exact invocation.
- **A real pass requires** — the conditions that separate a meaningful pass from a test that passes without proving anything.

The last part matters most. It is easy to write a suite that is green and worthless.

---

## Level 1 — Per Task

Triggered whenever an implementation task is believed complete.

```bash
bash scripts/run-tests.sh task
```

### 1.1 Unit Tests

**Proves:** an isolated calculation or rule is arithmetically and logically correct across normal, boundary, and invalid input.

**Runs at:** Task, Milestone, Pre-production.

**Command:** `php artisan test --testsuite=Unit`

**Applies to:** weighted-average cost recalculation (`PUR-05`), discount resolution and the single-discount rule (`POS-05`), optional tax computation (`POS-04`), shift variance arithmetic (`CSH-03`), loyalty earn/redeem rounding and expiry (`CUS-03`), local barcode format assembly (`MD-03`), stock-count reconciliation arithmetic against the movement window (`INV-08`), depreciation calculation (`AST-04`), open-price min/max validation (`PRC-08`).

**A real pass requires:**
- Boundary values are tested, not only a typical value. Weighted-average cost with zero prior stock, with a return, and with a fractional quantity are three different tests.
- At least one test asserts the rule *refuses*: a discount that would stack, an open price below minimum, a redeem beyond balance.
- No database, no HTTP, no facades that hide the logic under test. If the calculation cannot be tested without booting the framework, the calculation is in the wrong place.

**Not acceptable as a pass:** a unit test that asserts a method returns a non-null value, or that only exercises the value the developer happened to use during implementation.

### 1.2 Feature Tests

**Proves:** a complete action works through the application stack — routing, authorization, validation, business mutation, side effects, and response.

**Runs at:** Task, Milestone, Pre-production.

**Command:** `php artisan test --testsuite=Feature`

**A real pass requires:**
- Both the success path and the validation-failure path, and the failure path asserts **no side effect occurred** (`FLW-SYS-02`): no record written, no stock moved, no audit success event.
- Every state transition the flow permits is exercised, and at least one transition the flow forbids is asserted to be rejected.
- Sensitive mutations assert the audit record in the same test (`FLW-SYS-03`, `NFR-01`), including that it committed in the same transaction — a rolled-back mutation must leave no orphan audit.
- Response assertions cover status *and* the absence of leakage: no internal path, no stack trace, no unauthorised field (`FLW-ERR-01`).

**Not acceptable as a pass:** asserting `200 OK` without asserting the database effect, or asserting the database effect without asserting the audit.

### 1.3 Policy and Scope Tests

**Proves:** authorization is enforced at the server for role, branch, store, document, field, and action — per `NFR-03`.

**Runs at:** Task, Milestone, Pre-production.

**Command:** `php artisan test --filter=Policy`

**Mandatory pattern for every module.** For each protected action, four tests:

1. Authorized user in scope → allowed.
2. Authorized user **out of** branch/store scope → denied.
3. User without the specific permission → denied.
4. Direct access bypassing the UI (forged ID, tampered filter, direct export/download URL) → denied with `403`/`404` and no protected payload (`FLW-SYS-01`).

**Additional mandatory pairs for this product:**
- Cashier attempting to view Party Wallet → denied. Party Manager attempting to view Product Wallet → denied. Both directions, always (`CUS-02`).
- Stock Counter attempting to approve reconciliation → denied (`INV-09`).
- Cashier attempting to view expected close totals before blind submission → denied (`CSH-02`, `CSH-03`).
- Requester attempting to approve their own request → denied (`AC-XCUT-01`).

**A real pass requires:** denial is proven by invoking the action or route directly, not by asserting a button is missing from a rendered page. A hidden button is not authorization.

### 1.4 Livewire Tests

**Proves:** the component enforces rules rather than merely displaying them, and that its state transitions are legal.

**Runs at:** Task, Milestone, Pre-production.

**Command:** `php artisan test --filter=Livewire`

**A real pass requires:**
- Invalid input produces validation errors and no persisted change.
- Actions the user is not permitted to take are rejected when called on the component directly (`$this->call('approve')`), not merely hidden from the rendered view.
- Blocked state transitions from the flow definitions are asserted — for example a component may not move an approved document back to draft.
- Where the component holds a cart, working invoice, or count session, stale data is revalidated on submit rather than trusted from component state (`FLW-POS-02`).

### 1.5 Integration Tests

**Proves:** effects that cross module boundaries happen together, atomically, and consistently.

**Runs at:** Task, Milestone, Pre-production.

**Command:** `php artisan test --testsuite=Integration`

**Required integration chains for this product:**

| Chain | Must prove |
|---|---|
| Purchase invoice approval | stock increased in the selected store, weighted-average cost updated, sale price unchanged, movement created, audit written — all or nothing (`PUR-05`, `PRC-03`). |
| Price approval | old active price closed, exactly one new active price per location, historical sales unchanged, label queue equals remaining stock at that location (`PRC-04`–`PRC-06`). |
| POS sale approval | stock reduced in the assigned selling store only, payment posted, shift/drawer linked, customer history updated, audit written (`POS-02`). |
| Return with gift card | return movement created, gift card issued with correct value, original sale preserved and referenced (`RET-02`, `RET-04`). |
| Stock count reconciliation | counted quantity reconciled against movements during the count window, adjustment referenced, uncounted items untouched (`INV-08`, `INV-09`). |
| Party final settlement | payments on account reconciled, Party Wallet settled, Product Wallet untouched, final invoice immutable, receipt issued (`PTY-06`, `CUS-02`). |
| Transfer receipt | source in-transit cleared, destination increased by actual received, differences isolated into review (`INV-03`). |
| Offline sync acceptance | accepted sale posts stock/payment/number once; rejected sale posts nothing; conflicts queued (`FLW-OFF-02`). |

**A real pass requires:** an induced failure mid-chain rolls back everything. Every integration chain above needs a companion test that forces an exception after the first mutation and asserts the database is unchanged (`FLW-SYS-03`).

### 1.6 Focused Browser Tests

**Proves:** the specific flow that changed works in a real browser, in both text directions, at both viewport sizes.

**Runs at:** Task (focused subset), Milestone, Pre-production (full matrix).

**Command:** `php artisan dusk --group=focused`

**A real pass requires:**
- The flow is exercised as a user would: scanning/typing, not seeding state and asserting a rendered value.
- Arabic RTL and English LTR are both checked for the changed screens — RTL breakage is the single most common visual defect in this stack and is invisible to LTR-only testing.
- Mobile viewport is checked for any operational screen (POS, counting, party operations, asset checkout).
- Console errors and failed network requests are asserted absent, not ignored.

**Scope discipline:** at task level this runs only the changed flow's group. Running the whole browser suite per task makes the loop too slow to be run at all, which is worse than a narrow suite.

### 1.7 Manual Visual Review

**Proves:** the screen is operationally sensible to a human — something no assertion covers.

**Runs at:** Task, Milestone, Pre-production.

**Command:** none. This is a human activity with a checklist in `docs/testing/05-manual-checklists.md`.

**A real pass requires:** a named reviewer and a date. The orchestrator writes this as an unchecked box and can never check it. If the box is empty, the review did not happen — that is the correct and honest reading, and it must not be worked around.

---

## Level 2 — Per Milestone

Triggered when every task in a milestone is Level-1 green.

```bash
bash scripts/run-tests.sh milestone
```

### 2.1 Full Regression

**Proves:** the milestone's work broke nothing previously proven.

**Command:** `php artisan test --parallel`

**A real pass requires:** the full suite, not a subset chosen for speed. If parallel execution causes flakiness, the flakiness is a defect in test isolation and must be fixed rather than worked around by dropping to serial-and-partial.

### 2.2 Security Tests

**Proves:** no known-vulnerable dependency, no unsafe configuration, no type/contract error class that tests cannot reach.

**Commands:**
```bash
./vendor/bin/phpstan analyse --memory-limit=1G
php artisan enlightn
composer audit
```

**Product-specific security assertions that belong in the Feature suite, not the scanners:**
- Attachment routes never return an internal storage path and never serve a file to an out-of-scope user (`AC-XCUT-04`, `FLW-ATT-02`).
- Exports re-apply view, field, branch, store, and export permissions independently of the report view (`AC-XCUT-12`).
- Export cell values are escaped against formula injection (`FLW-RPT-02`).
- Password reset tokens are single-use, expiring, and produce identical responses for known and unknown identities (`FLW-AUTH-02`).
- Errors return a request ID and never a stack trace, secret, or path (`AC-XCUT-15`).

### 2.3 Data Reconciliation

**Proves:** the ledgers agree with themselves — the integrity claim that unit tests cannot make because it is about accumulated state.

**Command:** `php artisan recon:check`

**Required reconciliations:**

| Check | Assertion |
|---|---|
| Stock integrity | For every product/store: sum of approved stock movements equals recorded on-hand. |
| Negative stock | No store balance is negative except where an audited override exists (`INV-05`). |
| Payment integrity | For every approved invoice: sum of payments, discounts, and settlements equals invoice total. |
| Wallet integrity | For every customer: Product Wallet ledger sums to Product Wallet balance; Party Wallet ledger sums to Party Wallet balance; no entry crosses between them (`CUS-02`). |
| Loyalty integrity | Earn minus redeem minus expired equals current balance (`CUS-03`). |
| Gift card integrity | Issued value minus redemptions equals remaining balance; no card is negative (`RET-04`). |
| Numbering integrity | No duplicate approved document number within a sequence scope (`NFR-06`). |
| Price integrity | No product has two active prices at one location (`PRC-05`). |
| Audit integrity | Every approved sensitive document has at least one audit event; no audit event references a missing source (`NFR-01`). |
| Asset integrity | No asset has overlapping reservations; no asset is Checked Out without a checkout record (`AST-02`). |
| Immutability | No approved document has an updated timestamp later than its approval without a referenced correction (`NFR-02`). |

**A real pass requires:** the command runs against a database with meaningful volume and history, not an empty one. Reconciliation on a fresh database proves nothing.

### 2.4 Visual Regression

**Proves:** no unintended layout change, especially in RTL.

**Command:** `npx backstop test`

**A real pass requires:** every diff is either zero or explicitly reviewed and accepted by a human with a note. Accepting diffs in bulk to clear the report defeats the check entirely.

**Minimum scenario set:** login, dashboard, POS sale screen, POS close screen, product card, price approval, transfer, count session, party booking, party invoice, asset checkout, audit list, one report, one print preview — each in Arabic RTL and English LTR, desktop and mobile.

### 2.5 Accessibility

**Proves:** operational screens are usable by keyboard and by assistive technology.

**Command:** `php artisan dusk --group=a11y`

**Priority screens:** POS sale, shift close, stock count entry, party operating order, asset checkout — screens used continuously by staff for long shifts.

**A real pass requires:** zero critical and serious axe violations on priority screens. Labels on inputs, visible focus order, sufficient contrast, and correct `dir` attribute handling for both languages.

### 2.6 Performance Smoke

**Proves:** no obvious performance regression was introduced.

**Command:** `k6 run scripts/smoke.k6.js`

**Additionally required as automated assertions in the test suite** (cheaper and more precise than load tooling for these):
- Query-count assertions on list screens — no N+1 on product list, sales list, movement history, audit list.
- Every high-volume list is bounded and paginated (`NFR-05`, `AC-XCUT-11`).
- Report queries are range-bounded and reject unbounded requests (`FLW-RPT-01`).

### 2.7 Migration-from-Clean

**Proves:** a new deployment can be built from migrations and seeders alone.

**Command:** `php artisan migrate:fresh --seed --env=testing`

**A real pass requires:** the resulting database supports an immediate smoke path — create branch, create product, approve price, open shift, make a sale. A migration that completes but produces an unusable system has not passed.

**Why this matters here:** the business will be deployed to a real environment once, and any drift between migrations and the developer's incrementally-evolved local database will surface at exactly the worst moment.

---

## Level 3 — Pre-Production

Triggered when a release candidate is proposed.

```bash
bash scripts/run-tests.sh preprod
```

### 3.1 Load Test

**Proves:** the system holds at expected peak concurrent usage.

**Command:** `k6 run scripts/load.k6.js` against **staging**.

**Scenario must reflect reality:** concurrent cashiers across branches making sales, a warehouse user running a transfer, a count session in progress, a party manager taking payments, and a manager running a report — simultaneously. A load test that only requests the dashboard proves nothing about this system.

**A real pass requires:** thresholds met *and* data integrity intact afterwards — run `recon:check` after the load test. Load that produces duplicate numbers or imbalanced ledgers is a failure even if response times were acceptable.

### 3.2 Backup and Restore Test

**Proves:** a backup is restorable and complete.

**Command:** `bash scripts/backup-restore-drill.sh`

**A real pass requires:** restore into a clean database, row counts matching on critical tables, and a functional smoke path on the restored copy. A restore that completes but cannot serve a login has not passed.

### 3.3 Disaster Recovery Exercise

**Proves:** the business can resume operating after a real loss.

**This is a human exercise, not a command.** It covers: time to restore, who performs it, where the off-site copy lives, what the branches do while the system is down, and what happens to offline-queued POS sales during the outage. Record the actual elapsed time — an untimed drill provides no planning value.

### 3.4 Security Review

**Proves:** a human with security scope has examined authorization, attachment handling, session policy, export controls, and offline sync trust boundaries.

Scanners do not perform this. They inform it.

### 3.5 Physical Printer, Scanner, and Device Test

**Proves:** the hardware in the branches actually works with the system.

**Required device checks:** thermal receipt printer (invoice, gift receipt, shift close), A4 printer (daily closing report, party final invoice, exports), label printer (barcode labels at correct dimensions and scannable output), barcode scanner (product lookup at POS and in count sessions), and the actual tablet or terminal used at the POS station.

**Critical assertion that only physical testing can make:** a printed barcode label must be scannable by the actual scanner. A visually correct label that the scanner cannot read passes every automated test and fails in the store.

### 3.6 Production-like Migration Rehearsal

**Proves:** the deployment procedure works on a realistic copy before it is performed live.

Run the actual upgrade path against a restored production-like database at realistic volume, timed. Record how long migrations take — a migration that is instant on 100 rows and takes 40 minutes on real volume is an outage nobody planned.

### 3.7 UAT

**Proves:** the business accepts the behaviour as correct for their operation.

Driven from the twelve proof obligations in `docs/14-test-plan.md` §2, performed by the people who will actually use each role, in Arabic. A developer performing UAT is not UAT.

### 3.8 Full Browser and Device Matrix

**Proves:** the PWA works across what the business actually uses.

**Minimum matrix:** Chrome desktop, Edge desktop, Safari desktop if used, Chrome Android, Safari iOS — each in Arabic RTL and English LTR, plus PWA install and offline behaviour on the POS device (`FLW-PWA-02`, `FLW-OFF-01`).

---

## Trigger Summary

| Trigger | Command | Report |
|---|---|---|
| Task believed complete | `bash scripts/run-tests.sh task` | `docs/testing/reports/<ts>-task.md` |
| Milestone complete | `bash scripts/run-tests.sh milestone` | `docs/testing/reports/<ts>-milestone.md` |
| Release candidate | `bash scripts/run-tests.sh preprod` | `docs/testing/reports/<ts>-preprod.md` |
| Touching numbering, stock posting, wallets, loyalty, gift cards, price approval, shift close, permissions, or offline sync | escalate to milestone regardless of task size | milestone report |

---

**Disclaimer:** This document defines test execution for already-approved PRD requirements. It introduces no new Phase 1 business capability, production value, permission, limit, state, or commercial policy.
