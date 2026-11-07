# Merge report — 3allaf-erp + rajeh-erp

**Date:** 2026-11-07
**Merge branch:** `merge/3allaf-plus-rajeh`
**Sources:**
- `ibrahimmaher2503-source/3allaf-erp` @ `codex/r1-sales-reports` (feed-store edition)
- `IS-Intelligent-Solutions/rajeh-erp` @ `main` (2026-11-05)

## 1. What happened (الخلاصة)

- `3allaf-erp` was created from a copy of `rajeh-erp` at commit **`e4ac8a0`** (2026-09-14, *finalize hotfix41*) plus uncommitted local work. It started a new git history (`085097e`, *establish local project baseline*), so the two repositories shared no history.
- History was **grafted**: `085097e` now has `e4ac8a0` as its parent. A real 3‑way merge was possible with `e4ac8a0` as the merge base.
- Since the fork, rajeh received **570 commits** (≈190 fixes/hotfixes, plus features: purchase receiving, multi‑destination transfers, receiving drafts, live stocktake, gift cards in POS, quotations, invoice discounts, tax identity, branch work‑context, UI refresh).
- 3allaf changed 265 files and rajeh changed 674. **121 files** were changed on both sides, and **95 files** had textual conflicts (~250 hunks). Everything else merged automatically.

**Rule used:** keep every 3allaf feed‑store behaviour (units/conversion, kg decimals, feed pricing per unit & customer, credit customers/suppliers, AR/AP, landed cost, expenses/treasury, partial receipts, R1/R2 reports, 3allaf branding). Apply rajeh's fixes and features on top of it. When both sides rewrote the same screen, rajeh's version was taken and the 3allaf‑specific parts were re‑applied.

## 2. Conflict decisions (backend)

| Area | Decision |
|---|---|
| `ApprovePurchaseInvoiceAction` | 3allaf landed cost (`inventory_unit_cost`), supplier credit‑limit and terms snapshot kept. Added rajeh product row locks and transfer numbering. Removed the “temporary warehouse must finish at zero” rule, as rajeh did in `bc27e5a5` (purchase receiving is primary). |
| `SavePurchaseInvoiceAction` | Units kept. Added rajeh company scoping and PO price/discount/tax inheritance, adapted to entered units. The remaining‑qty check stays after unit conversion. |
| `SavePurchaseOrderAction` + `PurchaseOrderCalculator` | rajeh discount/tax calculator, fed with 3allaf base‑unit qty and cost. The calculator accepts 6 decimals. |
| `PurchaseInvoiceCalculator` | 3allaf entered‑unit gross plus rajeh's strict discount types. The converter is optional, so `new PurchaseInvoiceCalculator()` works. |
| `ReversePurchaseInvoiceAction` | Both guards: 3allaf payment/adjustment and rajeh linked transfers. |
| `ProductQuantity` | **3allaf version kept** (up to 6 decimals). rajeh's version allowed whole numbers only and rounded display to 2 dp. |
| Supplier model/action | Union: 3allaf payment policy, credit days/limit, CR. rajeh tax identity/country, lead time, preferred payment method. |
| `SaveProductAction` | rajeh product drafts plus 3allaf feed fields and product units. |
| `RetailSaleAction` | 3allaf unit/customer pricing, minimum‑price guard and price‑source locks. Added rajeh invoice discount and company‑scoped tax. |
| POS cart / snapshot | 3allaf base‑unit stock checks, plus rajeh “only N available” messages and `available_quantity`. |
| `CapturePaymentAction`, `RetailRefundCalculator` | 3allaf kept: partial cash for credit customers, decimal refunds. |
| `RetailReturn` | rajeh unverified returns (`is_verified`, `manager_approved_amount`) plus 3allaf AR split fields. |
| `ReceiveStockTransferAction` | 3allaf partial receipts (`StockTransferReceipt`, idempotency) **and** rajeh multi‑destination allocations and receiving drafts (`finalizeDraft`). |
| `OpenStockCountSessionAction` | rajeh (random scope, movement watermarks). |
| `EffectivePriceResolver` | 3allaf (unit/customer/price‑list resolver). |
| Cash drawers | 3allaf treasury account link plus rajeh eligible cashiers and assigned‑user validation. |
| `InitialSetupStatus` | rajeh company‑scoped tax and opening checks. |
| Navigation | rajeh items/permissions (any‑of) combined with 3allaf `permissions` (all‑of) and report entries. |

### Decimal (kg) quantities
rajeh's new code paths forced whole numbers. They now allow 6 decimals in:
- purchase receiving, distribution and approval
- purchase return drafts
- transfer receiving drafts and finalize
- stocktake quantity, contribution and Excel import
- sales invoice drafts
- PO calculator

UI inputs in inventory, stocktake entry, POS cart and purchase invoice lines also accept decimals.

## 3. Conflict decisions (routes & screens)

- **routes/inventory.php:** rajeh workspace (stocktake import/add‑all/quantity, multi‑destination transfers, receiving drafts, `LowStockRule`) plus 3allaf count managers, aggregated overview queries and decimal transfer lines. `POST transfers/{id}/receive` accepts **both** flows: with `received_quantities` it runs a 3allaf partial receipt, otherwise it runs rajeh draft finalize.
- **routes/reporting.php:** 3allaf R1/R2 reports **and** rajeh phase‑one reports.
- **routes/retail.php:** rajeh plus 3allaf unit/customer open‑price check. The sales overview is not forced to the POS context store.
- **Inventory screen:** rajeh version (rewritten upstream), with decimal inputs.
- **Purchase invoices / orders / order form:** rajeh logic (branch, receiving, discount/tax preview, PDF fail‑safe) with 3allaf unit select, entered qty/price, and the single‑store shortcut. Orders keep 3allaf auto‑submit/approval toasts.
- **POS checkout:** rajeh redesigned dialog plus 3allaf **credit sale** mode (button for credit/both customers, partial payment, remaining‑on‑account).
- **POS cart:** rajeh line UI plus 3allaf unit selector, price‑list/minimum label and decimal quantity.
- **Pricing:** 3allaf per‑unit pricing matrix kept. Added rajeh rule that a proposer cannot approve or reject their own price version.
- **Product form:** rajeh (local code preview, filters) plus 3allaf units editor and feed fields.
- **Suppliers / drawers / returns / customers / quotations / sales invoices:** rajeh with 3allaf additions re‑applied (credit fields, treasury account, AR invoices on customer page, optional phone, no children section, single‑store shortcuts).
- **Kept as 3allaf:** welcome page (rajeh replaced it with the Rajeh marketing site), print branding, hidden party/rental permission modules.
- **Translations (`lang/*.json`):** key‑level 3‑way merge. When both sides changed the same key, the 3allaf wording won (9 keys).
- **Agent state docs (`.ai/*`, `AI_INDEX.md`):** 3allaf versions kept.

## 4. Database migrations

- Both sides used the same sequence numbers (`000115`–`000129`) but different dates, so all filenames are unique and run in date order.
- **Collision fixed:** rajeh `2026_10_16_000126_add_supplier_commercial_terms` added `suppliers.credit_limit`, which 3allaf `2026_09_15_000117` already adds. The rajeh migration is now guarded with `Schema::hasColumn`.
- ✅ `php artisan migrate` from an empty MySQL (MariaDB 10.11) schema: **all migrations ran successfully**.

## 5. Verification actually run

Environment: PHP 8.4, MariaDB 10.11, Laravel deps installed from `composer.lock` (without phpstan/larastan/infection; those could not be downloaded here).

- `php artisan migrate` (fresh schema): passed.
- `php artisan route:list`: 449 routes load.
- `php artisan view:cache`: **all Blade views compile**.
- `php -l` on every resolved PHP file: clean.
- Unit and Feature suites: run on the merged tree and on both original repos (see section 6).

Not run: phpstan/larastan (packages unavailable offline), browser/E2E checks.

## 6. Test comparison

All suites were run in the same environment (PHP 8.4, MariaDB 10.11) on the merged tree and on both original repos.

**Unit suite**
- merged: 99/122 pass. **Every remaining failure also fails in rajeh or 3allaf itself.** These are source-string checks tied to each repo's own files.

**Feature suite (full)**
- rajeh/main alone: 347/461 pass.
- merged: 325/526 pass. The merged tree has more tests because it includes both repos' tests.
- 14 tests that fail on rajeh/main pass on the merged tree.

**3allaf feed-store tests (seeded with `DemoFeedStoreSeeder`)**, 3allaf baseline → merged:
- Unchanged and passing: R1 sales reports 9/9, feed pricing 8/8, POS credit checkout 6/6, AR return split, last customer price, batch tracking, financial scope, factories, legacy settlement migration.
- Failing the same way on the 3allaf baseline too: shift→treasury transfer 0/3, feed-store operations UI 0/3.
- Now failing: P0 inventory receiving, P0.8 credit settlement, supplier credit payables, R2 inventory reports, expenses/cash accounts, customer receipts, fractional adjustment. The cause is rajeh's security hardening: a super admin is now limited to their **operational company**. When more than one company exists, a super admin without a branch/store scope is denied ("You are not authorized for this inventory store" / "No active company is available in your authorized scope"). These 3allaf tests create a second company with an unscoped super admin, so they hit this rule. In a single-company feed store with scoped users the rule does not apply. The fix is in the test fixtures (give the actor a branch scope), not in the business code. **Decide whether to keep rajeh's rule.**

**Rajeh tests that fail because of 3allaf business rules (expected):**
- products need a sellable base unit and a positive price per store/unit
- shift closing requires a treasury cash account
- inventory adjustments require a reason
- quantities display as exact decimals (`2.000000` / `1.235`), not rajeh's rounded integers

Rajeh fixtures don't create these records. Either update those fixtures or relax the 3allaf rule.

## 7. Needs your decision / follow‑ups

1. **Two supplier‑payables models now coexist.** 3allaf `SupplierBalance` (invoices − payments − adjustments) and rajeh `supplier_payable_entries` ledger (from supplier returns). Choose one source for supplier balance reports.
2. **Quantity display rounding.** rajeh's `ProductQuantity::format` rounds to 2 dp (`1.235` → `1.24`). 3allaf keeps exact decimals. 3allaf behaviour was kept, so rajeh's `CriticalRegressionTest` boundary test expecting `1.24` will fail by design.
3. **Transfer receiving UI** is now rajeh's draft/finalize per destination. The 3allaf direct partial‑receipt endpoint still works but has no dedicated form in the new screen.
4. **phpunit DB name** now follows rajeh's QA bootstrap guard (`qa_rajh_m1_*_20261007`), because the 3allaf name is rejected by that guard.
5. Rajeh‑only modules (gift cards, quotations, party/rental) are present. Hide them from navigation if the feed store does not use them.
6. Run phpstan and a manual pass on: POS credit sale, purchase invoice with kg units + landed cost, transfer receive, stocktake with decimals.
