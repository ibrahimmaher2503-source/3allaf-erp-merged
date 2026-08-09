# Testing 03 — Test Case Catalog

**Parent:** `docs/14-test-plan.md`
**Purpose:** the concrete test cases per module. Each case has an ID, the requirement and flow it proves, the type, and the assertion that makes it meaningful.

## Conventions

- **ID format:** `TC-<MODULE>-<NNN>`
- **Type:** `U` Unit, `F` Feature, `P` Policy, `L` Livewire, `I` Integration, `C` Concurrency, `B` Browser
- **⛔ marks a mandatory negative case** derived from the Cross-Flow Blocked Transitions in `docs/06-user-flows.md`. These are not optional and not lower priority — in this system they are the higher-value tests.

**Writing rule:** every test file carries a docblock naming its requirement IDs, flow IDs, and test case IDs. Untraceable tests decay because nobody knows what breaking them means.

---

## Module A — Administration and Master Data

| ID | Case | Type | Proves | Key assertion |
|---|---|---|---|---|
| TC-ADM-001 | Create branch with selling store, drawer, price list, roles, printer config | F I | MD-01, P-01 | Full setup chain persists and is usable for a subsequent sale |
| TC-ADM-002 | ⛔ Non-administrator attempts branch creation | P | NFR-03 | Denied at service level; no record created |
| TC-ADM-003 | ⛔ Deactivate branch with active dependencies | F | FLW-ADM-01 | Blocked with reason; branch remains active |
| TC-ADM-004 | ⛔ Duplicate selling-store mapping | F | FLW-ADM-02, INV-02 | Rejected; existing mapping unchanged |
| TC-ADM-005 | ⛔ Retire drawer with an active shift | F | FLW-ADM-03, CSH-01 | Blocked; shift and drawer unaffected |
| TC-ADM-006 | ⛔ Remove the last administrator | F | FLW-ADM-04 | Blocked; system retains at least one admin |
| TC-ADM-007 | ⛔ User grants themselves a sensitive permission | P | FLW-ADM-04, NFR-03 | Denied; permission set unchanged; denial audited |
| TC-ADM-008 | ⛔ Edit numbering settings to reuse an existing sequence | F | NFR-06 | Rejected; no retroactive rewrite possible |
| TC-ADM-009 | Settings change creates a version; past documents unchanged | F A | NFR-02 | Historical documents render with original settings |

---

## Module B — Catalog, Barcode, Pricing

| ID | Case | Type | Proves | Key assertion |
|---|---|---|---|---|
| TC-CAT-001 | Create product with bilingual data, attributes, images, barcode | F L | MD-02, MD-04 | Persisted with stable item code |
| TC-CAT-002 | Change product supplier after purchase history exists | F | MD-02, PUR-02 | Item code unchanged; historical invoice still shows original supplier |
| TC-CAT-003 | Local barcode assembly: 4-digit supplier + 6-digit serial | U | MD-03 | Format correct; item code independent of barcode |
| TC-CAT-004 | ⛔ Two products with the same barcode | F | MD-02 | Rejected; unique constraint enforced at service level |
| TC-CAT-005 | ⛔ Colour/size variants create separate stock balances | F | MD-05 | One balance per product/store regardless of attributes |
| TC-CAT-006 | Excel import: valid rows create, invalid rows rejected with downloadable error list | F I | PRC-01 | Invalid rows produce **no** database writes |
| TC-CAT-007 | ⛔ Import writes before approval | F | PRC-01, FLW-CAT-02 | No record exists until approval step completes |
| TC-CAT-008 | Import in Update Existing mode does not create new products | F | PRC-01 | Product count unchanged; existing rows updated |
| TC-CAT-009 | Composite and service product types behave per configuration | F | PRC-02 | Type-specific rules applied |
| TC-CAT-010 | ⛔ Rewrite product identity/type after transactions exist | F | FLW-CAT-01 | Blocked; historical documents remain valid |

### Pricing

| ID | Case | Type | Proves | Key assertion |
|---|---|---|---|---|
| TC-PRC-001 | **Purchase invoice at new cost does not change sale price** | I | PRC-03 | Sale price byte-identical before and after approval |
| TC-PRC-002 | Price proposal → submit → approve creates immutable version | F I A | PRC-04 | Version record created; approval audited with before/after |
| TC-PRC-003 | ⛔ Edit an approved price version directly | F | PRC-04, NFR-02 | Blocked; correction requires a new version |
| TC-PRC-004 | Historical sales retain original prices after new version | I | PRC-04 | Past invoice totals unchanged |
| TC-PRC-005 | ⛔ Two active prices for one product at one location | C | PRC-05 | Concurrent approvals: exactly one active price survives |
| TC-PRC-006 | Approved version updates remaining-stock price at affected locations | I | PRC-05 | New sales use new price; other locations unaffected |
| TC-PRC-007 | Label queue equals remaining stock by location | I | PRC-06 | Queue count matches on-hand at that store exactly |
| TC-PRC-008 | ⛔ Print label for a product with no approved price | F | PRC-07 | Blocked with pending-pricing state shown |
| TC-PRC-009 | ⛔ Print label against wrong store's price | F | PRC-06 | Blocked; label must bind to selected location |
| TC-PRC-010 | ⛔ Sell a product with no approved price | F | PRC-07 | Blocked at POS; zero price never becomes a sale |
| TC-PRC-011 | Label reprint requires reason and is audited | F A | PRC-06, FLW-PRN-01 | Reprint marked and recorded |
| TC-PRC-012 | ⛔ Open price outside min/max by unauthorized role | P U | PRC-08 | Denied; reason required where configured; audited |

---

## Module C — Purchasing

| ID | Case | Type | Proves | Key assertion |
|---|---|---|---|---|
| TC-PUR-001 | PO lifecycle Draft → Submitted → Partially Received → Received → Closed | F L | PUR-03 | Each transition permitted and recorded |
| TC-PUR-002 | ⛔ Edit a Received or Closed PO | F | PUR-03 | Blocked |
| TC-PUR-003 | Purchase invoice approval increases store stock | I A | PUR-05 | On-hand rises by exact quantity in the selected store only |
| TC-PUR-004 | Weighted-average cost recalculated correctly | U I | PUR-05 | Arithmetic verified across zero-stock, normal, and fractional cases |
| TC-PUR-005 | Rollback on induced failure mid-approval | I | PUR-05, AC-XCUT-08 | Stock, cost, and audit all unchanged |
| TC-PUR-006 | ⛔ Approve the same purchase invoice twice | C | AC-XCUT-09 | Stock rises once; second attempt returns original result |
| TC-PUR-007 | Supplier return reduces stock via approved return document only | I A | PUR-06 | Movement referenced to original purchase |
| TC-PUR-008 | ⛔ Return more than was purchased | F | PUR-06 | Blocked |
| TC-PUR-009 | ⛔ Direct stock edit instead of a return document | F | NFR-02 | No code path permits direct balance mutation |

---

## Module D — Inventory, Transfers, Counting

| ID | Case | Type | Proves | Key assertion |
|---|---|---|---|---|
| TC-INV-001 | Availability view shows on-hand, available, in-transit, reserved, reorder | F P | INV-01 | Scoped to authorized stores |
| TC-INV-002 | ⛔ Cashier sells stock from a non-assigned store | F P | INV-02 | Blocked; other-branch results are informational only |
| TC-INV-003 | Transfer lifecycle through all defined states | F I | INV-03 | Each transition recorded with actor and quantities |
| TC-INV-004 | Dispatch moves source stock to in-transit | I | INV-03 | Source reduced; destination not yet increased |
| TC-INV-005 | Destination receipt of partial quantity isolates the difference | I | INV-03 | Shortage/damage/refusal recorded separately |
| TC-INV-006 | ⛔ Skip a transfer state (Draft directly to Received) | F | INV-03 | Blocked |
| TC-INV-007 | ⛔ Edit a transfer backwards after dispatch | F | INV-03, NFR-02 | Blocked |
| TC-INV-008 | ⛔ Stock exit producing a negative balance | F C | INV-05 | Blocked by default |
| TC-INV-009 | Authorized negative-stock override is highlighted and audited | F P A | INV-05 | Override recorded with actor and reason |
| TC-INV-010 | ⛔ Fractional quantity on a product not configured for it | U F | INV-06 | Rejected |
| TC-INV-011 | Inventory entry/exit/adjustment require reason and approval | F P | INV-04 | Missing reason blocks submission |

### Stock Counting — highest-risk area

| ID | Case | Type | Proves | Key assertion |
|---|---|---|---|---|
| TC-CNT-001 | Open count captures reference balance per item | F I | INV-08 | Snapshot recorded at open |
| TC-CNT-002 | **Sales continue during an open count** | I C | INV-08 | Sales succeed; count remains valid |
| TC-CNT-003 | **Reconciliation accounts for intervening movements exactly once** | I C | INV-08 | Final balance = counted quantity adjusted for the movement window; no double-count, no omission |
| TC-CNT-004 | ⛔ Uncounted items set to zero | F | INV-09 | Uncounted items enter review list; balances untouched |
| TC-CNT-005 | ⛔ Stock Counter approves reconciliation alone | P | INV-09 | Denied; Warehouse Manager approval required |
| TC-CNT-006 | Partial count by category/supplier/store limits scope | F I | INV-07 | Out-of-scope items untouched and cannot be entered |
| TC-CNT-007 | ⛔ Enter a count line outside the declared partial scope | F | INV-07 | Rejected |
| TC-CNT-008 | Recount replaces prior entry with full history retained | F A | INV-07 | Both entries auditable |
| TC-CNT-009 | Approved discrepancy creates a referenced adjustment only | I A | INV-09, NFR-02 | No direct balance write |
| TC-CNT-010 | ⛔ Any stock effect before reconciliation approval | I | INV-09 | Balances unchanged until approval |

---

## Module E — POS, Payments, Shifts

| ID | Case | Type | Proves | Key assertion |
|---|---|---|---|---|
| TC-POS-001 | Sale by barcode scan, name search, and item code | F L B | POS-01 | All three lookup paths resolve |
| TC-POS-002 | Approved sale reduces assigned-store stock and posts payment | I A | POS-02 | Movement, payment, shift, drawer, customer all linked |
| TC-POS-003 | ⛔ Duplicate sale approval (double-click / replay) | C B | AC-XCUT-09 | One sale, one stock movement, one number, one audit |
| TC-POS-004 | Optional tax applied per invoice without breaking the sequence | U F | POS-04 | Numbering remains unified and sequential |
| TC-POS-005 | ⛔ Stack a customer discount with an invoice discount | U F L | POS-05 | Blocked or replacement required; never summed |
| TC-POS-006 | ⛔ Stack an item discount with a group discount | U F | POS-05 | Same |
| TC-POS-007 | Invoice print shows item, qty, original price, discount line, net, totals block | F B | POS-06 | Layout matches the defined structure in both languages |
| TC-POS-008 | Gift Receipt contains no price anywhere | F B | POS-07 | No price in rendered output, markup, or print payload |
| TC-POS-009 | Gift Receipt validates a later return without disclosing price | I | POS-07, RET-01 | Return resolves the original sale |
| TC-POS-010 | Suspended sale creates no posting until approval | F L | FLW-POS-02 | No stock, payment, or number allocated |
| TC-POS-011 | ⛔ Retrieve another cashier's suspended sale without authority | P | FLW-POS-02 | Denied |
| TC-POS-012 | Suspended sale revalidates price and stock on retrieval | F | FLW-POS-02 | Stale values refreshed from server |
| TC-POS-013 | Electronic payment requires evidence attachment | F | POS-03 | Missing evidence blocks approval |
| TC-POS-014 | ⛔ Replace payment evidence after approval | F P | POS-03, NFR-02 | Blocked except via controlled correction |

### Shifts and Blind Closing

| ID | Case | Type | Proves | Key assertion |
|---|---|---|---|---|
| TC-CSH-001 | Shift opens with opening balance on an assigned free drawer | F | CSH-01 | Shift active; float recorded |
| TC-CSH-002 | ⛔ Two active shifts on one drawer | C | CSH-01 | Second attempt blocked |
| TC-CSH-003 | ⛔ Expected totals present in the close-screen response | F B | CSH-02, CSH-03 | **Assert the network payload**, not the rendered view |
| TC-CSH-004 | Variance calculated only after submission | U F | CSH-03 | Calculation runs server-side post-submit |
| TC-CSH-005 | ⛔ Cashier views variance detail | P | CSH-03 | Denied; only authorized reviewer sees expected-vs-actual |
| TC-CSH-006 | ⛔ Reopen or edit an approved shift | F | CSH-02, NFR-02 | Blocked except by referenced correction |
| TC-CSH-007 | Thermal closing receipt and A4 daily report render correctly | F B | CSH-04 | Both formats, both languages |

---

## Module F — Returns, Exchanges, Gift Cards

| ID | Case | Type | Proves | Key assertion |
|---|---|---|---|---|
| TC-RET-001 | Return validated against original invoice | F | RET-01 | Unreferenced return blocked |
| TC-RET-002 | Return validated against Gift Receipt | I | RET-01, POS-07 | Resolves sale without price disclosure |
| TC-RET-003 | ⛔ Return quantity exceeding the original sale | F | RET-01 | Blocked |
| TC-RET-004 | ⛔ Return the same line twice | C | AC-XCUT-09 | Second attempt blocked |
| TC-RET-005 | Same-item exchange settles at zero difference | I | RET-02 | Both movements recorded |
| TC-RET-006 | Different-item exchange settles the difference correctly | I | RET-02 | Collection or refund matches calculated difference |
| TC-RET-007 | Refund posts against the original payment context | I A | RET-02 | Referenced and audited |
| TC-RET-008 | Gift Card issued equals eligible return value | I | RET-02, RET-04 | Balance correct at issue |
| TC-RET-009 | ⛔ Concurrent redemption of one gift card | C | RET-04 | Balance never goes negative; only one redemption succeeds |
| TC-RET-010 | ⛔ Direct gift-card balance edit | F | RET-04, NFR-02 | No path exists |
| TC-RET-011 | Condition inspection required before stock return | F | RET-03 | Missing condition blocks the return |
| TC-RET-012 | Damaged item follows the non-saleable path | I | RET-03 | Does not re-enter sellable stock |
| TC-RET-013 | ⛔ Mix a party line into an exchange | F | PTY-01, RET-02 | Blocked |

---

## Module G — Customers, Wallets, Loyalty

| ID | Case | Type | Proves | Key assertion |
|---|---|---|---|---|
| TC-CUS-001 | Customer created with unique phone and consent | F | MD-06, CUS-01 | Duplicate phone rejected |
| TC-CUS-002 | Profile unifies retail and party history | F | CUS-01 | Both histories visible, balances separate |
| TC-CUS-003 | ⛔ **Cashier views Party Wallet balance** | P | CUS-02 | Denied at service level and absent from payload |
| TC-CUS-004 | ⛔ **Party Manager views Product Wallet balance** | P | CUS-02 | Denied at service level and absent from payload |
| TC-CUS-005 | ⛔ Transfer value between the two wallets | F I | CUS-02 | No code path exists |
| TC-CUS-006 | ⛔ Direct wallet balance edit | F | CUS-02, NFR-02 | Only source-linked ledger entries permitted |
| TC-CUS-007 | Loyalty earn posts per approved rule with expiry | U F A | CUS-03 | Rounding and expiry correct |
| TC-CUS-008 | Party earn rate differs from retail where configured | U | CUS-03 | Activity-specific rule applied |
| TC-CUS-009 | ⛔ Redeem more points than available | F C | CUS-03 | Blocked; concurrent redeem cannot overdraw |
| TC-CUS-010 | ⛔ Redeem expired points | F | CUS-03 | Blocked |
| TC-CUS-011 | ⛔ Redeem loyalty while offline | F | CUS-03, FLW-OFF-01 | Blocked in offline mode |
| TC-CUS-012 | All history entries carry source-document references | I | CUS-04 | No orphan ledger entries |

---

## Module H — Party Operations and Assets

| ID | Case | Type | Proves | Key assertion |
|---|---|---|---|---|
| TC-PTY-001 | ⛔ **Add a retail product line to a party invoice** | F L | PTY-01 | Blocked with a clear warning; line not silently dropped |
| TC-PTY-002 | ⛔ **Add a party service line to a retail sale** | F L | PTY-01 | Blocked with a clear warning |
| TC-PTY-003 | Booking captures schedule, location, contacts, children data, resources | F L | PTY-02 | Missing schedule/location/contact blocks confirmation |
| TC-PTY-004 | Working invoice remains editable before final close | F L | PTY-03 | Edits recorded with versions |
| TC-PTY-005 | ⛔ Edit a final party invoice | F | PTY-03, NFR-02 | Blocked |
| TC-PTY-006 | Each payment on account issues an individual receipt with the invoice number | F I B | PTY-04 | Receipt text and number correct per payment |
| TC-PTY-007 | ⛔ Settle a party invoice from Product Wallet | F P | PTY-06, CUS-02 | Blocked |
| TC-PTY-008 | Final settlement reconciles payments and Party Wallet | I | PTY-06 | Remaining balance or credit correct; Product Wallet untouched |
| TC-PTY-009 | ⛔ Close a party with unresolved assets | F | PTY-06, AST-03 | Blocked until assets resolved |
| TC-PTY-010 | Operating order issues consumables from party stores | I | PTY-05, AST-05 | Party-store stock reduced; retail stores untouched |
| TC-PTY-011 | ⛔ Issue consumables from a retail store | F | PTY-05 | Blocked |
| TC-PTY-012 | ⛔ Edit an operating order after completion | F | PTY-05 | Blocked |

### Rental Assets

| ID | Case | Type | Proves | Key assertion |
|---|---|---|---|---|
| TC-AST-001 | Reserve asset for a date/time interval | F | AST-02 | Reservation recorded; no stock effect |
| TC-AST-002 | ⛔ **Concurrent overlapping reservation of one asset** | C | AST-02 | Exactly one reservation succeeds; the other is refused atomically |
| TC-AST-003 | ⛔ Checkout without recorded pre-condition | F | AST-03 | Blocked |
| TC-AST-004 | ⛔ Duplicate checkout of one asset | C | AST-02 | Blocked |
| TC-AST-005 | ⛔ Return without recorded post-condition | F | AST-03 | Blocked |
| TC-AST-006 | ⛔ Invalid asset state jump (Checked Out → Available directly) | F | AST-03 | Blocked; must pass Under Inspection |
| TC-AST-007 | Damage assessment requires approval before status change | F P A | AST-04 | Unapproved cost or status change blocked |
| TC-AST-008 | Damage history remains tied to the party reference | I | AST-04 | Reference preserved |
| TC-AST-009 | Unused consumable return requires a reference movement | F | AST-05 | Direct reversal blocked |

---

## Module I — Quotations, Reports, Exports, Printing

| ID | Case | Type | Proves | Key assertion |
|---|---|---|---|---|
| TC-QTN-001 | Quotation created, printed, shared | F L | QTN-01 | Status lifecycle works |
| TC-QTN-002 | ⛔ **Quotation creates any posting effect** | F I | QTN-02 | Stock, reservation, payment, wallet, loyalty, gift-card tables all unchanged |
| TC-QTN-003 | ⛔ Convert quotation to invoice in Phase 1 | F | QTN-03 | Blocked — out of Phase 1 scope |
| TC-RPT-001 | Dashboard shows the defined widget set within scope | F P | RPT-01 | Out-of-scope data absent |
| TC-RPT-002 | ⛔ Filter parameter requesting another branch's data | P | AC-XCUT-10 | Scope applied before filters; own scope returned |
| TC-RPT-003 | Alerts fire for each defined operational condition | F | RPT-02 | Each alert type verified |
| TC-RPT-004 | Export re-applies view, field, branch, store, export permissions | F P | AC-XCUT-12 | Hidden fields absent from the file |
| TC-RPT-005 | ⛔ Excel export cell beginning with a formula character | F | FLW-RPT-02 | Escaped; no formula injection |
| TC-RPT-006 | ⛔ Unbounded report request | F | AC-XCUT-11, NFR-05 | Rejected or bounded |
| TC-RPT-007 | ⛔ Download an export artifact by guessed or expired ID | P | AC-XCUT-04 | Denied |
| TC-PRN-001 | Print renders from persisted source, not client parameters | F | AC-XCUT-13 | Tampered parameters ignored |
| TC-PRN-002 | Reprint requires reason and is visibly marked | F A | FLW-PRN-01 | Marker present |
| TC-PRN-003 | ⛔ Gift Receipt price leak through print parameters | F | POS-07 | No price under any parameter combination |

---

## Module J — Offline POS and Synchronization

| ID | Case | Type | Proves | Key assertion |
|---|---|---|---|---|
| TC-OFF-001 | Offline sale accepts cash within configured limits | F B | FLW-OFF-01 | Queued locally with local ID |
| TC-OFF-002 | ⛔ Offline credit sale | F | FLW-OFF-01 | Blocked |
| TC-OFF-003 | ⛔ Offline wallet use | F | FLW-OFF-01, CUS-02 | Blocked |
| TC-OFF-004 | ⛔ Offline loyalty redemption | F | FLW-OFF-01, CUS-03 | Blocked |
| TC-OFF-005 | ⛔ Offline special discount | F | FLW-OFF-01, POS-05 | Blocked |
| TC-OFF-006 | ⛔ Offline sale of an unpriced product | F | FLW-OFF-01, PRC-07 | Blocked |
| TC-OFF-007 | Sync posts accepted sale exactly once | I C | FLW-OFF-02, AC-XCUT-09 | Stock, payment, number allocated once |
| TC-OFF-008 | ⛔ Client value overwrites server truth on conflict | I | FLW-OFF-02 | Server price/stock/wallet/loyalty prevails |
| TC-OFF-009 | Rejected sync posts nothing | I | FLW-OFF-02 | No partial effect |
| TC-OFF-010 | Every conflict is queued for review | I | FLW-OFF-03 | No silent auto-resolution |
| TC-OFF-011 | ⛔ Resolve a conflict by deleting history | F | FLW-OFF-03, NFR-02 | Blocked; resolution requires referenced documents |
| TC-OFF-012 | Offline queue survives a PWA update | B | FLW-PWA-02 | Queued data compatible or routed to review |

---

## Mandatory Negative Case Index

Every entry in the Cross-Flow Blocked Transitions list must appear here with at least one test. This index is the checklist used at milestone review.

| Blocked transition | Test cases |
|---|---|
| Approved/final/closed document back to editable draft | TC-PRC-003, TC-PUR-002, TC-CSH-006, TC-PTY-005, TC-INV-007 |
| Direct ledger or stock-balance edit | TC-PUR-009, TC-CUS-006, TC-RET-010, TC-CNT-009 |
| Retail document accepting a party line, or the reverse | TC-PTY-001, TC-PTY-002, TC-RET-013 |
| Cross-wallet view, settlement, transfer, or correction | TC-CUS-003, TC-CUS-004, TC-CUS-005, TC-PTY-007 |
| Stock Counter approving reconciliation | TC-CNT-005 |
| Cashier seeing expected close totals before submission | TC-CSH-003, TC-CSH-005 |
| Sale of non-assigned store stock or unpriced product | TC-INV-002, TC-PRC-010 |
| Offline credit, wallet, loyalty, special discount, expired policy | TC-OFF-002 through TC-OFF-006 |
| Quotation posting effects or converting in Phase 1 | TC-QTN-002, TC-QTN-003 |

**Milestone gate:** if any row above has no passing test, the milestone is not complete regardless of how many positive tests pass.

---

**Disclaimer:** This catalog decomposes already-approved PRD requirements and flows into test cases. It introduces no new Phase 1 business capability, production value, permission, limit, state, or commercial policy.
