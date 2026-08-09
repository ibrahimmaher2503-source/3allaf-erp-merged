# Testing 04 — Cross-Cutting Test Suite

**Parent:** `docs/14-test-plan.md`
**Purpose:** define the tests for AC-XCUT-01 through AC-XCUT-16. These behaviours apply to every module, so they are written once as reusable suites and applied across all applicable documents rather than re-implemented per feature.

## Why This Suite Exists Separately

Approval, attachments, immutability, numbering, idempotency, scope, export, print, audit, error handling, and concurrency are not features — they are properties every feature must have. Testing them per-module produces either enormous duplication or, more commonly, silent gaps where one module quietly lacks what the others have.

The approach here: write a **shared test trait or dataset** per property, then apply it to every applicable document type. When a new document type is added, it joins the dataset and inherits the whole property suite automatically. A document type missing from a dataset is the gap the reviewer looks for.

### Applicable Document Types

Maintain this list in one place; every dataset below iterates it.

| Group | Document types |
|---|---|
| Purchasing | Purchase Order, Purchase Invoice, Supplier Return |
| Inventory | Stock Transfer, Inventory Entry, Inventory Exit, Inventory Adjustment, Stock Count Session |
| Retail | POS Sale, Retail Return, Exchange, Gift Card |
| Pricing | Price Version, Label Print Batch |
| Cash | Shift, Shift Close |
| Party | Party Booking, Working Party Invoice, Party Payment on Account, Operating Order, Consumable Issue, Final Party Invoice |
| Assets | Asset Reservation, Asset Checkout, Asset Return, Damage Assessment, Depreciation Event |
| Other | Quotation, Export Artifact |

---

## AC-XCUT-01 — Approval Separation

**Claim:** a requester cannot approve or reject their own request unless an explicit approved exception exists.

**Suite:** `tests/Feature/Approval/SeparationTest.php`

| Case | Assertion |
|---|---|
| Requester attempts to approve own request | Denied at service level; request stays pending; denial audited |
| Requester attempts to reject own request | Denied; same |
| Different authorized approver approves | Succeeds; decision audited with approver identity |
| Approver acting outside branch/store scope | Denied |
| Approver without approve permission for that document type | Denied |

**Dataset:** every document type requiring approval. A document type absent from the dataset is treated as an untested gap, not as "approval not applicable" — if approval genuinely does not apply, record that explicitly in the dataset with a reason.

---

## AC-XCUT-02 — Approval Staleness

**Claim:** a decision is denied when the source version or hash no longer matches the pending request.

**Suite:** `tests/Feature/Approval/StalenessTest.php`

| Case | Assertion |
|---|---|
| Source edited after request created, then approved | Denied with a staleness response; no source effect |
| Source unchanged, then approved | Succeeds |
| Two approvers act on the same pending request concurrently | Exactly one decision recorded; the other refused |
| Approval of a request whose source was deleted or cancelled | Denied |

**Why this matters here:** a price version or a transfer approved against a stale snapshot posts the wrong quantity or the wrong amount. Staleness failures are silent and only appear later in reconciliation.

---

## AC-XCUT-03 — Approval Terminal Integrity

**Claim:** terminal approval records cannot be reused or edited into another state.

**Suite:** `tests/Feature/Approval/TerminalIntegrityTest.php`

| Case | Assertion |
|---|---|
| Approve an already-approved request | Refused; no second effect |
| Reject an already-rejected request | Refused |
| Approve a withdrawn, cancelled, or expired request | Refused |
| Edit a terminal request's state directly | No path exists |
| New request after a terminal decision | Creates a new record, does not mutate the old one |

---

## AC-XCUT-04 — Protected File Storage

**Claim:** attachments are private and delivered only through source-authorized server routes.

**Suite:** `tests/Feature/Attachment/ProtectedStorageTest.php`

| Case | Assertion |
|---|---|
| Authorized viewer downloads evidence | Succeeds with safe headers |
| Out-of-scope user requests the same attachment ID | Denied `403`/`404` with no payload |
| Attachment requested by guessed sequential ID | Denied |
| Response contains an internal storage path | **Must not** — assert absence in body and headers |
| Direct public URL access to the storage location | Not reachable |
| Revoked, quarantined, or expired attachment | Denied |
| Unsafe format requested inline | Forced to download rather than rendered |

**Applies to:** POS payment evidence (`POS-03`), damage assessment evidence (`AST-04`), import files (`PRC-01`), export artifacts (`RPT-03`).

---

## AC-XCUT-05 — File Validation

**Claim:** unsafe, mismatched, oversized, empty, executable, traversal, or prohibited files are rejected without orphan files or metadata.

**Suite:** `tests/Feature/Attachment/FileValidationTest.php`

| Case | Assertion |
|---|---|
| Valid image within size limit | Accepted; metadata and source link created |
| Extension/MIME mismatch (e.g. `.jpg` with executable signature) | Rejected |
| Oversized file | Rejected |
| Empty file | Rejected |
| Filename containing traversal sequences | Normalised; no path escape |
| Rejected upload | **No orphan file on disk and no metadata row** |
| Storage failure mid-upload | No metadata row, no success audit |

**The orphan assertion is the one most often missing.** Assert both the storage fake and the database after every rejection case.

---

## AC-XCUT-06 — Immutable Approved Source

**Claim:** approved documents cannot be directly edited or physically deleted.

**Suite:** `tests/Feature/Document/ImmutabilityTest.php`

| Case | Assertion |
|---|---|
| Update an approved document through its normal action | Blocked |
| Update through a direct model/service call | Blocked — the guard lives below the controller |
| Physical delete of an approved document | No path exists |
| Logical delete without permission | Denied |
| Logical delete with permission | Record preserved, marked, audited, still referenced by history |

**Dataset:** every document type in the applicable list. This is the single most important cross-cutting suite for `NFR-02`, and the one where a per-module implementation most reliably produces one forgotten module.

---

## AC-XCUT-07 — Referenced Correction

**Claim:** every correction, reversal, return, or cancellation references and preserves the original source.

**Suite:** `tests/Integration/Document/ReferencedCorrectionTest.php`

| Case | Assertion |
|---|---|
| Correction created | New document references original; original unchanged |
| Compensating effects posted | Stock/payment/wallet effects reverse exactly, atomically |
| Unreferenced reversal attempt | Blocked |
| Duplicate reversal of one source | Blocked |
| Original document after correction | Still readable, still shows original values, linked to the correction |
| Timeline view | Shows source and correction linked in both directions |

---

## AC-XCUT-08 — Atomic Audit

**Claim:** sensitive mutation and audit commit together; rollback leaves neither partial mutation nor orphan audit.

**Suite:** `tests/Integration/Audit/AtomicAuditTest.php`

| Case | Assertion |
|---|---|
| Successful mutation | Exactly one audit event, same transaction, correct before/after |
| Failure after mutation, before audit | Both rolled back |
| Failure after audit, before commit | Both rolled back — **no orphan audit** |
| Audit write itself fails | Whole mutation rolled back |
| Repeated identical mutation | One business effect, one audit event |

**Mandated audit event coverage** (`NFR-01`) — assert each produces a record:

price/list change, price override, barcode-label printing, preferred-supplier change, transfer and receipt, count edit/reconciliation, shift-variance settlement, wallet use, loyalty use, party operating-order edit, cancellation, logical deletion.

Write this as a single data-driven test over the event list so that adding a sensitive action without its audit fails immediately.

---

## AC-XCUT-09 — Idempotent Replay

**Claim:** replaying the same logical request creates no duplicate business or audit effect.

**Suite:** `tests/Integration/Idempotency/ReplayTest.php`

| Case | Assertion |
|---|---|
| Same idempotency key sent twice | One effect; original result returned on replay |
| Same key with a conflicting payload | Refused |
| New key for a genuinely new action | New effect created |
| Rapid double-click at the UI | One document, one number, one movement, one audit |
| Replay after the original failed | Behaves per policy; no partial duplicate |

**Applies to:** sale approval, purchase invoice approval, payment recording, party payment on account, gift-card redemption, loyalty redemption, transfer dispatch and receipt, offline sync submission.

---

## AC-XCUT-10 — Scope Before Filters

**Claim:** scope is applied before search, filter, sort, pagination, export, and detail parameters.

**Suite:** `tests/Feature/Policy/ScopeBeforeFiltersTest.php`

| Case | Assertion |
|---|---|
| Filter requesting another branch's data | Own scope returned; no cross-scope rows |
| Sort parameter referencing an unauthorized field | Rejected or ignored safely |
| Detail request for an out-of-scope record ID | `403`/`404` with no payload |
| Pagination cursor crafted to reach out-of-scope rows | Blocked |
| Export with a cross-scope filter | Scope re-applied at export independently |
| Total/count values in a scoped list | Reflect scoped data only — counts leak information |

**The count assertion matters.** A list that correctly hides rows but reports "1,248 results" tells an unauthorized user how much data exists elsewhere.

---

## AC-XCUT-11 — Bounded Lists

**Claim:** high-volume lists use validated bounded queries and stable pagination.

**Suite:** `tests/Feature/Reports/BoundedListsTest.php`

| Case | Assertion |
|---|---|
| Request without a page size | Default bound applied |
| Request with an excessive page size | Clamped or rejected |
| Unbounded date range on a large report | Rejected or bounded |
| Pagination across pages with concurrent inserts | Stable; no duplicate or skipped rows |
| Query count on each high-volume list | No N+1 — assert a query-count ceiling |

**Priority lists:** product list, sales list, stock movement history, audit list, customer list, party bookings, asset history.

---

## AC-XCUT-12 — Secure Export

**Claim:** exports re-apply view, field, branch, store, and export permissions.

**Suite:** `tests/Feature/Reports/SecureExportTest.php`

| Case | Assertion |
|---|---|
| User with view but not export permission | Export denied |
| Export contains a field the user cannot view | Field absent from the file |
| Export with cross-scope filter | Own scope only |
| Excel cell beginning with a formula character | Escaped |
| Artifact download by another user | Denied |
| Expired artifact | Denied |
| Export request | Audited with filters, row count, actor, artifact reference |

---

## AC-XCUT-13 — Safe Print

**Claim:** print output comes from persisted source data and cannot be manipulated through client parameters.

**Suite:** `tests/Feature/Print/SafePrintTest.php`

| Case | Assertion |
|---|---|
| Print with tampered amount/price parameters | Output reflects persisted data, ignores parameters |
| Print a document the user cannot view | Denied |
| Gift Receipt with any parameter combination | No price appears |
| Label print for an unpriced product | Blocked |
| Label print bound to the wrong store | Blocked |
| Reprint | Requires reason where configured, visibly marked, audited |
| Template selection | Wrong template for the document type rejected |

---

## AC-XCUT-14 — Unique Concurrent Numbering

**Claim:** concurrent approved document creation cannot allocate duplicate document numbers.

**Suite:** `tests/Integration/Numbering/ConcurrentAllocationTest.php`

| Case | Assertion |
|---|---|
| Two genuinely parallel approvals on one sequence | Numbers N and N+1; no duplicate |
| Many parallel approvals (10+) | All numbers unique and contiguous per policy |
| Rollback after number allocation | Behaves per configured policy consistently |
| Different sequence scopes in parallel | Independent, no interference |
| Retry after failure | Returns existing result, does not consume a new number |

**Implementation note for the test:** this must use real parallel processes or database-level concurrency. A test that calls the allocator twice in a loop proves nothing — the failure mode being tested is a race, and a sequential test cannot produce one.

---

## AC-XCUT-15 — Safe Unexpected Error

**Claim:** unexpected failures return a safe localized response with a request ID and no stack trace, secret, or path.

**Suite:** `tests/Feature/Errors/SafeErrorResponseTest.php`

| Case | Assertion |
|---|---|
| Forced server exception during a mutation | Transaction rolled back; database unchanged |
| Error response body | Contains request ID; contains no stack trace, secret, file path, or SQL |
| Error response language | Correct for Arabic and English sessions |
| JSON vs HTML request | Each returns its appropriate safe format |
| Maintenance mode | Safe `503` |
| Audit after a failed request | No false success event |

---

## AC-XCUT-16 — Optimistic Concurrency

**Claim:** a stale update cannot silently overwrite a newer source version.

**Suite:** `tests/Integration/Concurrency/StaleUpdateTest.php`

| Case | Assertion |
|---|---|
| Two users open V1; A saves; B submits V1 | B receives a conflict response |
| B's data after conflict | Preserved, not discarded |
| Database after conflict | Only A's change persisted |
| Audit after conflict | Records A's change only; no misleading success for B |
| B reloads and resubmits | Succeeds against the current version |

**Priority sources:** working party invoice, price proposal, stock count session, operating order, product card, transfer document.

---

## Applying the Suite

1. Define the applicable document-type dataset in one shared location.
2. Write each suite above as a data-driven test over that dataset.
3. When adding a document type, add it to the dataset — the suites apply automatically.
4. At milestone review, check the dataset against the document-type list in this file. A missing entry is a gap.

**Review question for every milestone:** which document types were added this milestone, and are they in every applicable dataset? This one question catches most cross-cutting gaps before they reach production.

---

**Disclaimer:** This suite tests already-approved cross-cutting acceptance criteria. It introduces no new Phase 1 business capability, production value, permission, limit, state, or commercial policy.
