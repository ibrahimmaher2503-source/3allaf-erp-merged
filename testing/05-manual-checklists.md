# Testing 05 — Manual Checklists

**Parent:** `docs/14-test-plan.md`
**Purpose:** the checks that require a human. Each has signature fields because a signature is the only evidence that a human check actually happened.

## Rule for This Document

**No automated tool and no AI agent may check any box in this document.** The orchestrator emits these as empty boxes and cannot fill them. An empty box means the check has not been performed — that is the correct reading, and it must not be worked around by pre-filling names, writing "verified", or copying a signature from a previous run.

If a box is empty at a release gate, the release is not ready. That is the entire purpose of the box.

Copy the relevant checklist into the run's report or a dated file under `docs/testing/reports/manual/` and complete it there. Never edit a completed checklist to change a result — perform the check again and produce a new record.

---

## Checklist M-1 — Manual Visual Review (per Task)

**When:** after `run-tests.sh task` passes, before the task is handed over.
**Who:** any developer or reviewer other than ideal, but the implementer is acceptable at task level.
**Time:** 5–10 minutes.

### Layout and Language

- [ ] Screen renders correctly in Arabic RTL — text direction, alignment, icon mirroring, input direction
- [ ] Screen renders correctly in English LTR
- [ ] No text overflow, clipping, or overlap in either language
- [ ] Numbers, dates, and currency display in the expected format for the locale
- [ ] Mobile viewport usable — no horizontal scroll, tap targets reachable

### Operational Sense

- [ ] The screen makes sense to someone doing this job, not just to the person who built it
- [ ] Error and validation messages are clear and in the correct language
- [ ] Blocked actions explain why they are blocked, not just that they failed
- [ ] Loading and empty states are present and not alarming
- [ ] Nothing on screen exposes data this role should not see

### Print (where applicable)

- [ ] Print preview matches the required layout
- [ ] Nothing is clipped at page edges
- [ ] Arabic text renders correctly in the print output

**Reviewer:** ________________  **Date:** ____________  **Task ID:** ____________

**Findings:** _________________________________________________

---

## Checklist M-2 — Milestone Review

**When:** after `run-tests.sh milestone` passes.
**Who:** implementer plus one reviewer.
**Time:** 30–60 minutes.

### Test Integrity

- [ ] `SKIPPED` count in the milestone report is zero
- [ ] Every visual regression diff was individually reviewed, not bulk-accepted
- [ ] Every mandatory negative case in `03-test-catalog.md` for this milestone's scope has a passing test
- [ ] Traceability matrix updated — no requirement in scope left at `☐` or `◐`
- [ ] New document types added this milestone are present in every applicable cross-cutting dataset (`04-cross-cutting-test-suite.md`)
- [ ] No test was weakened, skipped, or commented out to make this milestone pass

### Data Integrity

- [ ] `recon:check` ran against a database with realistic volume, not an empty one
- [ ] All reconciliation checks report zero imbalance
- [ ] Migration-from-clean produced a usable system, verified by a smoke path

### Review Findings

- [ ] Any accepted risk is written down here, not carried in someone's memory

**Reviewer:** ________________  **Date:** ____________  **Milestone:** ____________

**Accepted risks:** _________________________________________________

---

## Checklist M-3 — User Acceptance Testing

**When:** pre-production, after all automated levels pass.
**Who:** the people who will actually use each role, in Arabic. **A developer performing UAT is not UAT.**
**Time:** plan a full working session per role.

UAT is driven by the twelve proof obligations in `docs/14-test-plan.md` §2. Each is performed by the relevant role on staging with realistic data.

### Retail Operations — performed by an actual cashier

- [ ] Open a shift, make several sales, take cash and electronic payment, attach evidence
- [ ] Apply a discount and confirm a second discount is refused in an understandable way
- [ ] Issue a gift receipt and confirm no price appears on it
- [ ] Process a return, an exchange, and a gift-card settlement
- [ ] Close the shift blind, confirm expected totals were never visible
- [ ] Confirm the party wallet of a customer is not visible anywhere in the POS

**Cashier:** ________________  **Date:** ____________

### Warehouse Operations — performed by an actual warehouse user

- [ ] Receive a purchase invoice and confirm stock and cost updated but price did not
- [ ] Create, dispatch, and receive a transfer, including a partial receipt with a difference
- [ ] Run a stock count while sales continue and confirm the reconciled result is correct
- [ ] Confirm uncounted items were not zeroed
- [ ] Attempt an action requiring manager approval and confirm it is refused

**Warehouse user:** ________________  **Date:** ____________

### Party Operations — performed by an actual party manager

- [ ] Create a booking with children data, schedule, and location
- [ ] Build a working invoice, take multiple payments on account, confirm each receipt
- [ ] Reserve, check out, return, and inspect a rental asset
- [ ] Record damage and confirm approval is required
- [ ] Close the party and confirm the settlement is correct
- [ ] Confirm the customer's Product Wallet is not visible or usable

**Party manager:** ________________  **Date:** ____________

### Management and Reporting — performed by an actual manager

- [ ] Review shift variances and confirm the expected-vs-actual detail is available to you
- [ ] Run each required report group and confirm the numbers match reality
- [ ] Export to PDF and Excel and confirm the content is correct and scoped
- [ ] Review the audit log for the day's activity and confirm it tells the true story
- [ ] Confirm you cannot see or alter another branch's data outside your scope

**Manager:** ________________  **Date:** ____________

### UAT Outcome

- [ ] Accepted
- [ ] Accepted with recorded conditions
- [ ] Not accepted

**Conditions or reasons:** _________________________________________________

**Business sign-off:** ________________  **Role:** ____________  **Date:** ____________

---

## Checklist M-4 — Physical Printer, Scanner, and Device Test

**When:** pre-production. **Cannot be simulated.** This checklist is the reason a system that passes every automated test can still fail on day one in the store.

### Thermal Receipt Printer

- [ ] Sales invoice prints completely, correct width, nothing cut off
- [ ] Arabic text renders correctly on thermal output
- [ ] Gift receipt prints with no price visible
- [ ] Shift closing receipt prints and is readable
- [ ] Party payment-on-account receipt prints with the correct invoice number

### A4 Printer

- [ ] Daily closing report prints with correct pagination
- [ ] Final party invoice prints correctly
- [ ] Exported PDF report prints correctly
- [ ] Arabic and English layouts both print without clipping

### Label Printer

- [ ] Barcode label prints at the correct physical dimensions
- [ ] **The printed label is scannable by the actual scanner** — the critical check
- [ ] Price and product name on the label are correct and legible
- [ ] Label queue quantity matches what actually printed

### Barcode Scanner

- [ ] Scans product barcodes at POS reliably
- [ ] Scans during a stock count session reliably
- [ ] Scans a printed local barcode (not only supplier barcodes)
- [ ] Scanning speed is workable for a real queue of customers

### POS Terminal / Tablet

- [ ] The actual device used in branches runs the PWA acceptably
- [ ] Touch targets are usable at the real screen size
- [ ] PWA installs and launches correctly
- [ ] Offline mode engages when connectivity drops and queues sales
- [ ] Queued sales sync correctly when connectivity returns

**Tester:** ________________  **Date:** ____________  **Devices used:** ____________

**Failures found:** _________________________________________________

---

## Checklist M-5 — Backup, Restore, and Disaster Recovery Exercise

**When:** pre-production, and repeated periodically after go-live.

### Backup Verification

- [ ] A backup was taken successfully
- [ ] The backup schedule is configured and confirmed running on the target server
- [ ] An off-site or off-server copy exists and is reachable
- [ ] Backup includes uploaded attachments, not only the database

### Restore Verification

- [ ] Backup restored into a clean database successfully
- [ ] Row counts match on critical tables
- [ ] `recon:check` passes on the restored copy
- [ ] A user can log in and complete a sale on the restored copy

### Disaster Recovery Exercise

- [ ] The restore was performed by the person who would actually do it, using written steps
- [ ] Elapsed time recorded: ____________
- [ ] It is documented what branches do while the system is unavailable
- [ ] It is documented what happens to offline-queued POS sales during an outage
- [ ] Contact and escalation path is written down and current

**Operations owner:** ________________  **Date:** ____________

**Actual restore time:** ____________  **Acceptable to the business:** ☐ Yes ☐ No

---

## Checklist M-6 — Security Review

**When:** pre-production.
**Who:** a reviewer with security scope. Scanners inform this review; they do not perform it.

- [ ] Authorization reviewed at the service layer for every module, not just tested paths
- [ ] Attachment storage, delivery, and access control reviewed
- [ ] Session policy, password reset, and rate limiting reviewed
- [ ] Export controls and field-level redaction reviewed
- [ ] Offline sync trust boundary reviewed — what the client can and cannot assert
- [ ] Audit log integrity reviewed — append-only, no update or delete path
- [ ] Dependency audit clean, or exceptions documented with reasons
- [ ] No debug mode, verbose errors, or exposed diagnostics in the production configuration
- [ ] Customer children's data (`MD-06`) handling reviewed for access scope and retention

**Reviewer:** ________________  **Date:** ____________

**Findings and dispositions:** _________________________________________________

---

## Checklist M-7 — Production Migration Rehearsal

**When:** pre-production, on a production-like copy at realistic volume.

- [ ] Migration run against a restored production-like database
- [ ] Elapsed time recorded: ____________
- [ ] No data loss or corruption — verified by `recon:check` before and after
- [ ] Rollback procedure written down and tested
- [ ] Downtime window calculated and communicated to the business
- [ ] Post-migration smoke path completed successfully

**Engineer:** ________________  **Date:** ____________

---

## Checklist M-8 — Browser and Device Matrix

**When:** pre-production.

| Platform | Arabic RTL | English LTR | PWA install | Offline mode |
|---|---|---|---|---|
| Chrome desktop | ☐ | ☐ | ☐ | ☐ |
| Edge desktop | ☐ | ☐ | ☐ | ☐ |
| Safari desktop (if used) | ☐ | ☐ | ☐ | ☐ |
| Chrome Android | ☐ | ☐ | ☐ | ☐ |
| Safari iOS | ☐ | ☐ | ☐ | ☐ |
| Actual POS terminal | ☐ | ☐ | ☐ | ☐ |

**Tester:** ________________  **Date:** ____________

---

## Checklist M-9 — Production Go / No-Go

**When:** the final gate. Every prior checklist must be complete with real names and dates.

### Prerequisites

- [ ] `run-tests.sh preprod` exited zero with `SKIPPED` = 0
- [ ] All Tier-A requirements in the traceability matrix are `☑`
- [ ] M-2 Milestone Review complete for every milestone
- [ ] M-3 UAT accepted by the business
- [ ] M-4 Physical device test complete with no blocking failures
- [ ] M-5 Backup, restore, and DR exercise complete with acceptable restore time
- [ ] M-6 Security review complete with findings dispositioned
- [ ] M-7 Migration rehearsal complete with a known downtime window
- [ ] M-8 Browser and device matrix complete

### Readiness

- [ ] Support and escalation path is in place for go-live day
- [ ] Branch staff have been trained on the flows they will use
- [ ] A rollback decision point and owner are agreed
- [ ] Known open issues are written down and accepted by the business, not merely known to the developer

### Decision

- [ ] **GO** — proceed to production
- [ ] **NO-GO** — reason: _________________________________________________

**Product owner:** ________________  **Date:** ____________

**Technical owner:** ________________  **Date:** ____________

---

**Disclaimer:** These checklists verify already-approved PRD requirements. They introduce no new Phase 1 business capability, production value, permission, limit, state, or commercial policy.
