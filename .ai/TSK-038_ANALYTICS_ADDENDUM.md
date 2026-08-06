# TSK-038 Addendum — Inventory Analytics and Profitability Reporting

**Status:** Proposed scope addition to TSK-038 — team-adopted, owner approval outstanding
**Authority:** RPT-01, RPT-03; `docs/50` §4b, §4c
**Blockers:** BLK-017 (KPI formulas, margin access)

---

## 1. Why This Addendum Exists

TSK-038 traces to RPT-01 and the PRD's Required Report Groups. Two things the business will ask for on day one are absent from both:

1. **Inventory ageing, turnover, and dead stock.** The Inventory group lists on-hand, valuation, movement, reorder, unpriced, transfers, count differences, and adjustments. None of these answers "which stock has not moved in six months?"
2. **Gross margin.** RPT-01 names top products, average invoice value, and stock value. No margin figure appears anywhere in the PRD's report requirements, although every input for it exists.

Implemented exactly as written, TSK-038 would ship a reporting module with no margin and no ageing. Both are additive, read from existing tables, and require no schema change — but neither will be built unless it is written down.

---

## 2. Added Scope

### 2.1 Inventory analytics

- Stock age per product/store, from last inbound movement.
- Ageing buckets 0–90 / 91–180 / 181–360 / over 360 days, with quantity and value per bucket.
- Turnover ratio and days of inventory, per store, per period.
- Dead stock list, using a configurable no-outbound-movement window.

Formulas: `docs/50` §4b.

### 2.2 Profitability

- Gross margin and margin % at invoice, product, category, brand, branch, and store level.
- Top products by revenue **and** by margin, presented as two distinct rankings.
- Discount impact on margin.
- Cost trend per product over time.

Formulas: `docs/50` §4c.

### 2.3 Dashboard widgets

Two additions to `UI-ADM-001`, both cost-permissioned:

- Gross margin for the selected period, with comparison to the prior period.
- Ageing summary: value in the over-180-day buckets.

---

## 3. Permissions

Both areas expose cost. They inherit the cost/margin permission boundary in `docs/22` §247 and `docs/04` (cost fields hidden from Cashier unless approved).

A role without margin access receives a permission denial for the whole report. It must **not** receive the report with zeroed or blanked cost columns — a zero is indistinguishable from a real zero and corrupts any figure derived from it.

---

## 4. Data Dependencies

Both depend on `docs/47` CF-01: every `sale` movement stores the unit cost it consumed.

Without that stored cost, margin can only be approximated from today's weighted average, which is wrong for any historical period and cannot be reconciled. This is the single hard dependency — if `stock_movements` ships without a stored consumed cost, this addendum is not implementable and retrofitting it requires a migration over posted financial data.

---

## 5. Verification

Recompute one month of margin by hand from invoice lines and their movement costs; confirm the two top-product rankings differ; confirm ageing uses last inbound rather than creation date; confirm turnover uses cost of sales rather than revenue; confirm a non-margin role is denied rather than shown zeros; confirm per-store figures roll up to the company figure by summation.

No automated tests are created or executed.
