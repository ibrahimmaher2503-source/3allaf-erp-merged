# Testing 02 — Requirement Traceability Matrix

**Parent:** `docs/14-test-plan.md`
**Purpose:** map every one of the 72 approved Phase 1 requirement IDs to its flows, risk tier, required test types, and test file locations — so that "is this requirement tested?" is answerable by lookup rather than by memory.

## How to Use This Matrix

- **Tier** — A (irreversible financial/stock consequence), B (recoverable operational), C (presentation/reporting). Tier determines the minimum required test types, defined in `docs/14-test-plan.md` §5.
- **Required tests** — the minimum. More is welcome; less is a gap.
  `U`=Unit `F`=Feature `P`=Policy/Scope `L`=Livewire `I`=Integration `C`=Concurrency `B`=Focused Browser `A`=Audit assertion `N`=Negative/blocked-transition
- **Status** — `☐` not started, `◐` partial, `☑` complete. Updated by the implementer, verified against the actual test files, never assumed.

**Rule:** a requirement is not `☑` until every letter under Required tests has at least one passing test that fails when the behaviour regresses.

---

## Master Data — MD-01 to MD-06

| ID | Requirement summary | Flows | Tier | Required tests | Test location | Status |
|---|---|---|---|---|---|---|
| MD-01 | Configurable master data: company, numbering, tax, payments, printers, branches, stores, drawers, users, roles, categories, brands, suppliers, price lists, customer policy. | FLW-ADM-01–05 | B | F P L A N | `tests/Feature/Admin/`, `tests/Feature/Policy/AdminPolicyTest.php` | ☐ |
| MD-02 | Stable internal item code that never changes on supplier change; international barcode when supplied, local otherwise. | FLW-CAT-01 | B | U F N A | `tests/Unit/Catalog/ItemCodeTest.php`, `tests/Feature/Catalog/` | ☐ |
| MD-03 | Local barcode format: 4-digit supplier code + 6-digit serial, independent of item code. | FLW-CAT-01 | B | U F N | `tests/Unit/Catalog/LocalBarcodeFormatTest.php` | ☐ |
| MD-04 | Product card fields: bilingual names/descriptions, codes, attributes, images, keypoints, keywords. | FLW-CAT-01 | B | F L | `tests/Feature/Catalog/ProductCardTest.php` | ☐ |
| MD-05 | Colour/size/character/age are searchable attributes only — **not** variants or separate balances. | FLW-CAT-01, FLW-INV-01 | B | F N | `tests/Feature/Catalog/AttributesNotVariantsTest.php` | ☐ |
| MD-06 | Customer record: unique phone, consent, children data and birthdays, history, two wallets, loyalty, gift cards. | FLW-CUS-01 | B | F P L N A | `tests/Feature/Customer/`, `tests/Feature/Policy/CustomerScopeTest.php` | ☐ |

**MD notes.** MD-02 requires a specific negative test: change a product's supplier and assert the internal item code is unchanged and historical purchase invoices still resolve. MD-05 requires a negative test asserting that two colours of one product do **not** produce two stock balances — this is a common implementation drift.

---

## Product, Barcode, Pricing, Labels — PRC-01 to PRC-08

| ID | Requirement summary | Flows | Tier | Required tests | Test location | Status |
|---|---|---|---|---|---|---|
| PRC-01 | Excel import: map, validate, error report, Create Only / Update Existing, approval before write. | FLW-CAT-02 | B | F I N A | `tests/Feature/Catalog/ProductImportTest.php` | ☐ |
| PRC-02 | Standard, composite, and service product types. | FLW-CAT-01 | B | U F N | `tests/Feature/Catalog/ProductTypesTest.php` | ☐ |
| PRC-03 | Prices from card, import, or purchase invoice; **cost change never auto-changes sale price**. | FLW-CAT-03, FLW-PUR-02 | A | U F I N A | `tests/Integration/Pricing/CostDoesNotChangePriceTest.php` | ☐ |
| PRC-04 | Price change creates immutable version, requires approval, preserves historical invoices. | FLW-CAT-04 | A | F I C N A | `tests/Integration/Pricing/PriceVersionImmutabilityTest.php` | ☐ |
| PRC-05 | Approved version updates remaining-stock price; **one active shelf price per location**. | FLW-CAT-04 | A | F I C N A | `tests/Integration/Pricing/SingleActivePriceTest.php` | ☐ |
| PRC-06 | Label queue equals remaining stock by location; labels printed against selected store with its approved price. | FLW-CAT-05 | B | F I N A B | `tests/Feature/Pricing/LabelQueueTest.php` | ☐ |
| PRC-07 | Unpriced product shows zero price and pending state; receivable but **not sellable and not label-printable**. | FLW-CAT-05, FLW-POS-01 | B | F N | `tests/Feature/Pricing/UnpricedProductTest.php` | ☐ |
| PRC-08 | Open price only for authorized roles, with reference/min/max, reason where configured, full audit. | FLW-POS-01 | A | U F P N A | `tests/Feature/POS/OpenPriceTest.php` | ☐ |

**PRC notes.** PRC-03 is the single most commonly violated rule in retail systems: a developer wires cost updates to price updates as a convenience. It requires an explicit integration test that approves a purchase invoice at a different cost and asserts the sale price is byte-identical afterwards. PRC-05 requires a concurrency test: two approvals for the same product/location submitted simultaneously must not both activate.

---

## Suppliers and Purchasing — PUR-01 to PUR-06

| ID | Requirement summary | Flows | Tier | Required tests | Test location | Status |
|---|---|---|---|---|---|---|
| PUR-01 | Supplier contact, status, terms, history, returns, last purchase prices. | FLW-PUR-01 | B | F P | `tests/Feature/Purchasing/SupplierTest.php` | ☐ |
| PUR-02 | Preferred supplier per product; invoice retains **actual** supplier historically. | FLW-PUR-02 | B | F N A | `tests/Feature/Purchasing/HistoricalSupplierTest.php` | ☐ |
| PUR-03 | PO states: Draft, Submitted, Partially Received, Received, Cancelled, Closed. | FLW-PUR-01 | B | F L N A | `tests/Feature/Purchasing/PurchaseOrderStatesTest.php` | ☐ |
| PUR-04 | Purchase invoice manual + Excel import with store, items, quantities, costs, discounts, optional tax. | FLW-PUR-02 | B | F I N | `tests/Feature/Purchasing/PurchaseInvoiceTest.php` | ☐ |
| PUR-05 | Approval increases store stock, updates weighted-average cost, creates auditable movement. | FLW-PUR-02 | A | U F I C A N | `tests/Integration/Purchasing/InvoiceApprovalPostingTest.php` | ☐ |
| PUR-06 | Supplier return references original, reduces stock only through approved return, retains cost history. | FLW-PUR-03 | A | F I N A | `tests/Integration/Purchasing/SupplierReturnTest.php` | ☐ |

**PUR notes.** PUR-05 requires a rollback test: force a failure after the stock increase and assert cost, stock, and audit are all unchanged. PUR-02 requires a test that changes the preferred supplier *after* an invoice exists and asserts the historical invoice still shows the original supplier.

---

## Inventory, Transfers, Stock Count — INV-01 to INV-09

| ID | Requirement summary | Flows | Tier | Required tests | Test location | Status |
|---|---|---|---|---|---|---|
| INV-01 | Display on-hand, available, in-transit, reserved, reorder, movement history by product and store. | FLW-INV-01 | B | F P | `tests/Feature/Inventory/AvailabilityTest.php` | ☐ |
| INV-02 | Branch POS sells **only** from assigned selling store; other branches searchable but not sellable. | FLW-INV-01, FLW-POS-01 | A | F P N B | `tests/Feature/Policy/SellingStoreScopeTest.php` | ☐ |
| INV-03 | Transfer states: Draft, Submitted, Approved, Dispatched/In Transit, Partially Received, Received, Difference Review, Cancelled. | FLW-INV-02 | A | F I C N A B | `tests/Integration/Inventory/TransferLifecycleTest.php` | ☐ |
| INV-04 | Entry/exit/adjustment require reason, store, items, quantities, responsible user, approval. | FLW-INV-03–05 | A | F P N A | `tests/Feature/Inventory/StockDocumentsTest.php` | ☐ |
| INV-05 | Negative stock blocked by default; permitted override highlighted and fully audited. | FLW-INV-04 | A | F P C N A | `tests/Feature/Inventory/NegativeStockBlockTest.php` | ☐ |
| INV-06 | Fractional quantities only for products configured to allow them. | FLW-INV-03–05 | B | U F N | `tests/Unit/Inventory/FractionalQuantityTest.php` | ☐ |
| INV-07 | Count supports full, partial by category/supplier/store, scanning, manual, recounts, discrepancy reports, controlled reconciliation. | FLW-INV-06, FLW-INV-07 | B | F L I N B | `tests/Feature/Inventory/StockCountTest.php` | ☐ |
| INV-08 | Counting does not stop selling; reference balance + movement window reconciled against counted quantity. | FLW-INV-06 | A | U F I C N A | `tests/Integration/Inventory/CountDuringSalesTest.php` | ☐ |
| INV-09 | Uncounted items **never** auto-zeroed; review list requires Counter + Warehouse Manager approval. | FLW-INV-06, FLW-INV-07 | A | F P N A | `tests/Feature/Inventory/UncountedNotZeroedTest.php` | ☐ |

**INV notes.** INV-08 is the hardest correctness problem in this system and needs a dedicated concurrency test: open a count, record a reference balance, execute sales during the count window, submit the counted quantity, and assert the reconciled result accounts for the intervening movements exactly once. INV-09 needs a test asserting that a Stock Counter alone cannot approve reconciliation — the role separation is the control.

---

## POS, Tax, Discounts, Receipts — POS-01 to POS-07

| ID | Requirement summary | Flows | Tier | Required tests | Test location | Status |
|---|---|---|---|---|---|---|
| POS-01 | Search by name/code/barcode, authorized quantity change, customer lookup, suspended sales, thermal/A4 print. | FLW-POS-01, FLW-POS-02 | B | F L N B | `tests/Feature/POS/SaleScreenTest.php` | ☐ |
| POS-02 | Sale links branch, selling store, cashier, drawer, shift, customer, payments, stock movements. | FLW-POS-01 | A | F I C A N | `tests/Integration/POS/SalePostingTest.php` | ☐ |
| POS-03 | Cash and manually recorded electronic payment; POS-terminal receipt image as evidence; no gateway. | FLW-POS-03 | B | F P N A | `tests/Feature/POS/PaymentEvidenceTest.php` | ☐ |
| POS-04 | Tax optional per invoice; sequence remains unified; authorized user selects applicability. | FLW-POS-01 | B | U F P N | `tests/Unit/POS/TaxCalculationTest.php` | ☐ |
| POS-05 | **Only one discount type per amount**; customer/group and invoice/item discounts must not stack. | FLW-POS-01 | A | U F L N A | `tests/Unit/POS/SingleDiscountRuleTest.php` | ☐ |
| POS-06 | Printed invoice shows item, qty, original price, discount line, net item value, and full totals block. | FLW-PRN-01 | B | F B | `tests/Feature/POS/InvoiceLayoutTest.php` | ☐ |
| POS-07 | Gift Receipt without prices, usable to identify original sale for exchange/return. | FLW-POS-04 | B | F N B A | `tests/Feature/POS/GiftReceiptTest.php` | ☐ |

**POS notes.** POS-05 requires unit tests across every discount combination the configuration permits, each asserting either a block or a replacement choice — never a summed result. POS-07 requires a negative test asserting no price value appears anywhere in the gift receipt output, including hidden markup and print data attributes.

---

## Returns, Exchanges, Gift Cards — RET-01 to RET-04

| ID | Requirement summary | Flows | Tier | Required tests | Test location | Status |
|---|---|---|---|---|---|---|
| RET-01 | Validate original invoice or Gift Receipt before return/exchange, subject to authorization and policy. | FLW-RET-01 | B | F P N A | `tests/Feature/Returns/ReferenceValidationTest.php` | ☐ |
| RET-02 | Same-item exchange, different-item exchange with settlement, cash refund, or Gift Card issuance. | FLW-RET-01, FLW-RET-02 | A | F I C N A | `tests/Integration/Returns/ExchangeSettlementTest.php` | ☐ |
| RET-03 | Inspection condition recorded before stock return; manager approval where configured; damaged items follow separate process. | FLW-RET-01 | B | F P N A | `tests/Feature/Returns/ConditionInspectionTest.php` | ☐ |
| RET-04 | Gift Cards: unique ID, value, balance, issue/use/void history, validity, permission-controlled use. | FLW-RET-03 | A | F P C N A | `tests/Feature/Returns/GiftCardLedgerTest.php` | ☐ |

**RET notes.** RET-04 requires a concurrency test for concurrent redemption of one card — the classic double-spend. RET-02 requires an excess-return negative test: returning more than was sold, and returning the same line twice.

---

## Customers, Loyalty, Wallets — CUS-01 to CUS-04

| ID | Requirement summary | Flows | Tier | Required tests | Test location | Status |
|---|---|---|---|---|---|---|
| CUS-01 | Unified profile and history across retail and party **without** combining financial balances. | FLW-CUS-01 | B | F P N | `tests/Feature/Customer/UnifiedProfileTest.php` | ☐ |
| CUS-02 | Product Wallet and Party Wallet separate in storage, display, authorization, settlement, reporting — **both directions**. | FLW-CUS-04, FLW-CUS-05 | A | F P I N A | `tests/Feature/Policy/WalletSeparationTest.php` | ☐ |
| CUS-03 | Loyalty is one shared balance with configurable earn/redeem, approval, expiry, audit; rules may differ by activity. | FLW-CUS-02, FLW-CUS-03 | A | U F C N A | `tests/Feature/Customer/LoyaltyLedgerTest.php` | ☐ |
| CUS-04 | Preserve transaction, party, payment, return, gift card, point, and wallet history with source references. | FLW-CUS-04, FLW-CUS-05 | B | F I A | `tests/Feature/Customer/HistoryReferencesTest.php` | ☐ |

**CUS notes.** CUS-02 is Tier A and needs the strongest negative coverage in the system: cashier cannot read, settle, adjust, or report on Party Wallet; Party Manager cannot do the same for Product Wallet; no transfer between wallets exists at any layer; and `recon:check` proves no ledger entry crosses. CUS-03 requires a concurrency test on redemption and a test that offline redemption is refused.

---

## Cash Drawers, Shifts, Daily Closing — CSH-01 to CSH-04

| ID | Requirement summary | Flows | Tier | Required tests | Test location | Status |
|---|---|---|---|---|---|---|
| CSH-01 | Shift opens on a specified drawer with opening balance; transactions link to active shift. | FLW-CSH-01 | B | F P C N A | `tests/Feature/Shift/ShiftOpeningTest.php` | ☐ |
| CSH-02 | At closing, cashier enters actual cash and electronic **without seeing expected values**. | FLW-CSH-02, FLW-CSH-03 | A | F P L N A B | `tests/Feature/Shift/BlindCloseTest.php` | ☐ |
| CSH-03 | System calculates variance after submission; expected-vs-actual exposed only to authorized reviewers. | FLW-CSH-02 | A | U F P N A | `tests/Unit/Shift/VarianceCalculationTest.php` | ☐ |
| CSH-04 | Thermal closing receipt and detailed A4 daily report covering methods, movement, shifts, drawers, variances. | FLW-PRN-01 | B | F B | `tests/Feature/Shift/ClosingReportsTest.php` | ☐ |

**CSH notes.** CSH-02/CSH-03 need a test asserting the expected total is absent from the **response payload**, not merely hidden in the view. A value rendered invisibly in HTML or returned in JSON and hidden by CSS is a failure of blind closing. Check the network response, not the screen.

---

## Party Operations — PTY-01 to PTY-06

| ID | Requirement summary | Flows | Tier | Required tests | Test location | Status |
|---|---|---|---|---|---|---|
| PTY-01 | Party workflow and stores separate from retail; no mixing of party and retail lines in one invoice. | FLW-PTY-01, FLW-PTY-02 | A | F P N A | `tests/Feature/Party/ActivitySeparationTest.php` | ☐ |
| PTY-02 | Booking captures customer, child info, date/time/location, contacts, notes, services, consumables, assets, responsibilities. | FLW-PTY-01 | B | F L N | `tests/Feature/Party/BookingTest.php` | ☐ |
| PTY-03 | Party invoice starts as working draft and stays editable before final closing. | FLW-PTY-02 | B | F L N A | `tests/Feature/Party/WorkingInvoiceTest.php` | ☐ |
| PTY-04 | Multiple payments on account; **each issues an individual receipt** labelled with the party invoice number. | FLW-PTY-03 | A | F I C N A B | `tests/Integration/Party/PaymentsOnAccountTest.php` | ☐ |
| PTY-05 | Operating order reserves services and assets, issues consumables from party inventory, allows controlled changes until completion. | FLW-PTY-04, FLW-PTY-05 | B | F I N A | `tests/Integration/Party/OperatingOrderTest.php` | ☐ |
| PTY-06 | Final close creates final invoice, reconciles payments, settles Party Wallet, captures remainder/credit, issues final receipt. | FLW-PTY-11 | A | F I C N A B | `tests/Integration/Party/FinalSettlementTest.php` | ☐ |

**PTY notes.** PTY-01 needs the blocking test in both directions: adding a retail product line to a party invoice, and adding a party service line to a retail sale — each must be blocked with a clear message, not silently dropped. PTY-06 needs an assertion that Product Wallet is untouched by party settlement.

---

## Rental Assets — AST-01 to AST-05

| ID | Requirement summary | Flows | Tier | Required tests | Test location | Status |
|---|---|---|---|---|---|---|
| AST-01 | Assets maintained separately from consumables with code, category, availability, location, condition, status, cost, history. | FLW-PTY-06 | B | F P | `tests/Feature/Assets/AssetRegistryTest.php` | ☐ |
| AST-02 | Reserve per date/time, **block conflicting allocation**, record checkout and return documents. | FLW-PTY-06, FLW-PTY-07 | A | F I C N A | `tests/Integration/Assets/DoubleBookingBlockedTest.php` | ☐ |
| AST-03 | Condition recorded before and after; states Available, Reserved, Checked Out, Under Inspection, Damaged, Under Maintenance, Retired, Lost. | FLW-PTY-07, FLW-PTY-08 | B | F L N A | `tests/Feature/Assets/AssetStateMachineTest.php` | ☐ |
| AST-04 | Damage and depreciation capture asset, event, party reference, assessment, responsible user, cost, approval, final status. | FLW-PTY-09, FLW-PTY-10 | B | F P N A | `tests/Feature/Assets/DamageAndDepreciationTest.php` | ☐ |
| AST-05 | Consumables issued from party stores against operating order; permitted unused returns need a reference movement. | FLW-PTY-05 | B | F I N A | `tests/Feature/Assets/ConsumableIssueTest.php` | ☐ |

**AST notes.** AST-02 requires a genuine concurrency test — two managers reserving the same asset for overlapping intervals at the same moment. A sequential test passes trivially and proves nothing about the actual failure mode.

---

## Quotations — QTN-01 to QTN-03

| ID | Requirement summary | Flows | Tier | Required tests | Test location | Status |
|---|---|---|---|---|---|---|
| QTN-01 | Standalone retail or party quotations with customer, lines, prices, terms, notes, validity, status. | FLW-QTN-01 | C | F P L | `tests/Feature/Quotation/QuotationTest.php` | ☐ |
| QTN-02 | Printable/shareable with **no** inventory, reservation, payment, wallet, or accounting effect. | FLW-QTN-01 | C | F N | `tests/Feature/Quotation/NoSideEffectsTest.php` | ☐ |
| QTN-03 | Retain identity and source references for future conversion; **conversion not in Phase 1**. | FLW-QTN-01 | C | F N | `tests/Feature/Quotation/ConversionBlockedTest.php` | ☐ |

**QTN notes.** QTN-02's test is entirely negative: create a quotation and assert the stock, reservation, payment, wallet, loyalty, and gift-card tables are unchanged. QTN-03 needs an explicit test that any conversion attempt is refused in Phase 1 — this prevents a well-meaning implementation from delivering out-of-scope behaviour.

---

## Dashboard, Reports, Alerts — RPT-01 to RPT-03

| ID | Requirement summary | Flows | Tier | Required tests | Test location | Status |
|---|---|---|---|---|---|---|
| RPT-01 | Dashboard filtered by date, branch, authorized scope, showing the full defined widget set. | FLW-RPT-01 | C | F P | `tests/Feature/Reports/DashboardScopeTest.php` | ☐ |
| RPT-02 | Operational alerts across stock, pricing, transfers, counts, shifts, parties, and assets. | FLW-RPT-01 | C | F P | `tests/Feature/Reports/AlertsTest.php` | ☐ |
| RPT-03 | Reports exportable to PDF and Excel per permission with the defined filter set. | FLW-RPT-02, FLW-EXP-01 | C | F P N A | `tests/Feature/Reports/SecureExportTest.php` | ☐ |

**RPT notes.** RPT-01 and RPT-03 both need scope-before-filter tests (`AC-XCUT-10`): a user requesting another branch's data through a filter parameter must receive their own scope, not the requested one. RPT-03 needs a formula-injection escaping test on Excel export.

---

## Audit, Integrity, Non-functional — NFR-01 to NFR-07

| ID | Requirement summary | Flows | Tier | Required tests | Test location | Status |
|---|---|---|---|---|---|---|
| NFR-01 | Sensitive events capture user, time, device/session, scope, source, reason, before/after — across the mandated event list. | FLW-SYS-03, FLW-AUD-01 | A | F I A N | `tests/Feature/Audit/SensitiveEventCoverageTest.php` | ☐ |
| NFR-02 | Approved documents immutable; reversal/cancellation/return/adjustment reference the original and preserve records. | FLW-DOC-01 | A | F I C N A | `tests/Integration/Audit/ImmutableCorrectionTest.php` | ☐ |
| NFR-03 | Authorization enforced at API/service level, not by hidden menus. | FLW-SYS-01 | A | F P N | `tests/Feature/Policy/ServerSideAuthorizationTest.php` | ☐ |
| NFR-04 | Secure auth, role-scoped sessions, server validation, controlled attachment storage, safe offline sync. | FLW-AUTH-01–02, FLW-ATT-01–02, FLW-OFF-02 | A | F P I N A | `tests/Feature/Security/` | ☐ |
| NFR-05 | Searchable/filterable views within role scope; high-volume views paginate safely. | FLW-RPT-01 | B | F P N | `tests/Feature/Reports/BoundedListsTest.php` | ☐ |
| NFR-06 | Unique sequential document numbers; concurrency must not duplicate approved numbers. | FLW-NUM-01 | A | F I C N A | `tests/Integration/Numbering/ConcurrentAllocationTest.php` | ☐ |
| NFR-07 | Modular design permits future extension without breaking Phase 1 separation and history. | — | C | — | architectural review, not automated | ☐ |

**NFR notes.** NFR-01 requires a coverage test that iterates the mandated audit event list — price change, price override, label printing, preferred-supplier change, transfer and receipt, count edit/reconciliation, shift-variance settlement, wallet/loyalty use, party operating-order edit, cancellation, logical deletion — and asserts each produces an audit record. NFR-06 requires true parallel execution, not simulated sequence.

---

## Cross-Cutting Acceptance Criteria — AC-XCUT-01 to AC-XCUT-16

Full test definitions in `docs/testing/04-cross-cutting-test-suite.md`. Summary mapping:

| AC | Subject | Tier | Test location | Status |
|---|---|---|---|---|
| AC-XCUT-01 | Approval separation (no self-approval) | A | `tests/Feature/Approval/SeparationTest.php` | ☐ |
| AC-XCUT-02 | Approval staleness (version/hash mismatch denied) | A | `tests/Feature/Approval/StalenessTest.php` | ☐ |
| AC-XCUT-03 | Approval terminal integrity | A | `tests/Feature/Approval/TerminalIntegrityTest.php` | ☐ |
| AC-XCUT-04 | Protected file storage | A | `tests/Feature/Attachment/ProtectedStorageTest.php` | ☐ |
| AC-XCUT-05 | File validation and rejection without orphans | A | `tests/Feature/Attachment/FileValidationTest.php` | ☐ |
| AC-XCUT-06 | Immutable approved source | A | `tests/Feature/Document/ImmutabilityTest.php` | ☐ |
| AC-XCUT-07 | Referenced correction | A | `tests/Integration/Document/ReferencedCorrectionTest.php` | ☐ |
| AC-XCUT-08 | Atomic audit | A | `tests/Integration/Audit/AtomicAuditTest.php` | ☐ |
| AC-XCUT-09 | Idempotent replay | A | `tests/Integration/Idempotency/ReplayTest.php` | ☐ |
| AC-XCUT-10 | Scope before filters | A | `tests/Feature/Policy/ScopeBeforeFiltersTest.php` | ☐ |
| AC-XCUT-11 | Bounded lists | B | `tests/Feature/Reports/BoundedListsTest.php` | ☐ |
| AC-XCUT-12 | Secure export | A | `tests/Feature/Reports/SecureExportTest.php` | ☐ |
| AC-XCUT-13 | Safe print | B | `tests/Feature/Print/SafePrintTest.php` | ☐ |
| AC-XCUT-14 | Unique concurrent numbering | A | `tests/Integration/Numbering/ConcurrentAllocationTest.php` | ☐ |
| AC-XCUT-15 | Safe unexpected error | B | `tests/Feature/Errors/SafeErrorResponseTest.php` | ☐ |
| AC-XCUT-16 | Optimistic concurrency | A | `tests/Integration/Concurrency/StaleUpdateTest.php` | ☐ |

---

## Coverage Summary

| Group | IDs | Tier A | Tier B | Tier C |
|---|---|---|---|---|
| Master Data | 6 | 0 | 6 | 0 |
| Pricing/Catalog | 8 | 4 | 4 | 0 |
| Purchasing | 6 | 2 | 4 | 0 |
| Inventory | 9 | 6 | 3 | 0 |
| POS | 7 | 3 | 4 | 0 |
| Returns | 4 | 2 | 2 | 0 |
| Customers | 4 | 2 | 2 | 0 |
| Cash/Shifts | 4 | 2 | 2 | 0 |
| Party | 6 | 4 | 2 | 0 |
| Assets | 5 | 1 | 4 | 0 |
| Quotations | 3 | 0 | 0 | 3 |
| Reports | 3 | 0 | 0 | 3 |
| Non-functional | 7 | 5 | 1 | 1 |
| **Total** | **72** | **31** | **34** | **7** |

Thirty-one Tier-A requirements each need Unit/Feature/Policy/Integration/Concurrency/negative/audit coverage as applicable. That is where the majority of testing effort belongs, and where a shortfall is not recoverable after go-live.

---

## Gap Register

Any requirement that cannot be tested as written belongs here rather than being silently guessed at. Raise it with the product owner; do not invent the missing rule.

| ID | Ambiguity | Raised on | Resolution |
|---|---|---|---|
| | | | |

---

**Disclaimer:** This matrix maps already-approved PRD requirements to test obligations. It introduces no new Phase 1 business capability, production value, permission, limit, state, or commercial policy.
