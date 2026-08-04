# Audit Goal Report — TSK-012 to TSK-015

## Goal

The audit goal is complete when **TSK-012, TSK-013, TSK-014, and TSK-015** are each completed for the approved local scope, their critical happy-path and failure-path scenarios are browser-verified, their focused automated tests pass where explicitly authorized, stock and weighted-average-cost results reconcile, and no unresolved **Critical** or **High** defect remains.

This report defines the completion gate. It does not claim that the gate has already been satisfied.

## Audit scope

| Task | Scope |
|---|---|
| TSK-012 | Staged product Excel import: upload, mapping, Create Only/Update Existing, validation, review/approval, valid-row writes, rejected-row error download. |
| TSK-013 | Supplier master and product-supplier history: supplier lifecycle, contacts/terms, product preference, actual/history views, returns and last-price links without rewriting historical supplier data. |
| TSK-014 | Purchase orders: line entry and Draft/Submitted/Partially Received/Received/Cancelled/Closed state machine with reliable receipt links and no stock/payment effect. |
| TSK-015 | Purchase invoices, import, receipt, and weighted-average cost: manual/staged import, selected-store receipt, tax/discount handling, atomic approval, stock movement, weighted-average cost, and pricing-review signal without changing sale price. |

## Authority and required reading completed

The complete `.ai/*.md` set was read before creating this report. The task-specific documentation was also read according to `AI_INDEX.md` and the task routing in `TASKS.md`.

### Shared sources

- `AGENTS.md`
- `AI_INDEX.md`
- `TASKS.md`
- `.ai/CURRENT_TASK.md`
- `.ai/CURRENT_MILESTONE.md`
- `.ai/BLOCKERS.md`
- `.ai/DECISIONS.md`
- `.ai/HANDOFF.md`
- `.ai/PROGRESS.md`
- `.ai/SESSION_SUMMARY.md`
- `.ai/TEST_RESULTS.md`
- `.ai/UI_SCREENS.md`
- `docs/09-coding-standards.md`
- `docs/10-milestones.md`
- `docs/12-acceptance-criteria.md`
- `docs/13-definition-of-done.md`
- `docs/14-test-plan.md`
- `docs/15-security-checklist.md`
- `docs/39-uat-and-release-gates.md`

### TSK-012 and TSK-013

- `docs/23-product-barcode-policy.md`
- `docs/35-document-state-machines.md`
- `docs/36-module-data-contracts.md`
- `docs/37-ui-screen-specifications.md`
- `docs/38-print-export-specification.md`
- Relevant catalog/purchasing sections of `docs/02-prd.md`, `docs/04-roles-permissions.md`, `docs/08-architecture.md`, and `docs/16-authorization-traceability.md`

### TSK-014 and TSK-015

- `docs/17-approval-policy.md`
- `docs/18-attachment-media-policy.md`
- `docs/19-audit-immutability-policy.md`
- `docs/35-document-state-machines.md`
- `docs/36-module-data-contracts.md`
- `docs/37-ui-screen-specifications.md`
- `docs/38-print-export-specification.md`
- Relevant purchasing/inventory sections of `docs/02-prd.md`, `docs/04-roles-permissions.md`, `docs/08-architecture.md`, and `docs/16-authorization-traceability.md`

## Current status at report creation

- `TSK-012`: **Not Started** in `TASKS.md`.
- `TSK-013`: **Not Started** in `TASKS.md`.
- `TSK-014`: **Not Started** in `TASKS.md`.
- `TSK-015`: **Not Started** in `TASKS.md`.
- The repository control files identify TSK-011 as the current closure-reviewed task and explicitly prohibit starting TSK-012, TSK-013, purchasing, or inventory until the current project gate/owner direction changes.
- Therefore, this report is an audit-goal and evidence-definition artifact only. It is not a completion report for any of the four tasks.

## Completion criteria

### 1. Implementation completeness

Each task must have an approved local vertical slice, including:

- Server-enforced authorization and branch/store scope checks.
- Validated actions, state transitions, idempotency, and duplicate-submission protection.
- Transaction boundaries for approval, receipt, stock, cost, audit, and other financial/inventory effects.
- Append-only audit context and immutable approved records where required.
- A complete user-facing Livewire/Blade screen, not a backend-only or placeholder implementation.
- Required loading, empty, error, validation, denied, disabled, success, and unsaved-change states.
- Arabic RTL and English LTR behavior at required responsive widths.
- Protected attachments and safe error/export artifacts where the task requires files.
- Print/export behavior required by the applicable specification, without mutating source records.

### 2. Browser evidence

The audit evidence must cover, at minimum:

- The critical happy path for every task.
- Unauthorized navigation, direct-route access, forged action, and cross-branch/store access.
- Validation and failure paths, including duplicate, stale, invalid-state, invalid-reference, unsafe-file/formula, and retry/idempotency cases applicable to the task.
- State-machine transitions and terminal-state edit/reversal restrictions.
- Responsive Arabic RTL and English LTR review.
- Console/network review for unexpected errors or sensitive leakage.
- Evidence paths or screenshots recorded in `.ai/TEST_RESULTS.md` and the task/session handoff.

### 3. Focused automated tests

Automated tests are **not automatically authorized** by the current repository directive. They may be created or run only when a current owner decision or task-specific directive explicitly authorizes a named focused scope. If authorized, the report must record the exact command and actual pass result. Otherwise, the result must be recorded as `Not Created/Not Run`, not inferred as passing.

### 4. Stock reconciliation

For TSK-015 and every dependent inventory effect:

- Approved invoice receipt quantities equal the posted stock movements.
- Stock movement totals reconcile to store/product balances.
- Duplicate approval/receipt retries do not create duplicate movements.
- Partial and full receipt paths reconcile independently.
- Invalid or rejected rows/documents create no stock movement.
- Source references remain traceable from invoice to receipt, movement, balance, and audit records.

### 5. Weighted-average-cost reconciliation

The audit must provide reproducible calculation evidence for each approved receipt scenario, including:

- Existing stock quantity and value before receipt.
- Received quantity and accepted unit cost after tax/discount policy.
- Exact rounding/precision inputs and outputs.
- Resulting quantity, total value, and weighted-average unit cost.
- Zero-stock behavior.
- Partial receipt behavior.
- Duplicate/concurrent receipt protection.
- Return/reversal impact when applicable.
- Confirmation that sale price is unchanged and only the pricing-review signal is produced.

The balance/value equation must reconcile for every recorded scenario; any unexplained difference is a blocking defect until resolved.

### 6. Defect gate

The goal cannot be marked complete while any unresolved Critical or High defect exists in the approved scope or in a directly affected regression path. Medium/Low findings must be recorded with ownership and disposition; they must not conceal a Critical or High defect.

## Required final evidence package

Before declaring the goal complete, update the project evidence with:

1. Task-by-task implementation status and scope boundary in `TASKS.md` or the approved status artifact.
2. Browser scenarios, roles, URLs, expected/actual results, and evidence paths in `.ai/TEST_RESULTS.md`.
3. Focused automated-test authorization, exact commands, and actual output, or an explicit `Not Created/Not Run` record.
4. Stock movement/balance reconciliation results.
5. Weighted-average-cost calculation cases and reconciliation results.
6. Defect register showing no unresolved Critical or High defect.
7. Relevant session/handoff updates with the actual next action and remaining blockers.

## Non-claims

This report does not close DM 2.1 or DM 2.2, does not close a Phase 2 gate, does not approve production master data or financial values, does not grant deferred permissions, and does not constitute UAT acceptance or production readiness.
