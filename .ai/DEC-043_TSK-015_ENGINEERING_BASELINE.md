# DEC-043 — TSK-015 Engineering Baseline and Configuration Boundary

**Date:** 2026-08-06
**Status:** Approved as an **engineering decision by the delivery team**
**Supersedes:** DEC-042 in respect of provenance only
**Related blockers:** BLK-006 (Open), BLK-008 (Open), BLK-010 (Open), BLK-012 (Mitigated)

---

## 1. Provenance Correction

DEC-042 recorded the TSK-015 policy values as **Owner Approved**. That attribution is incorrect and is hereby corrected.

- The business owner has **not** been reached and has approved nothing.
- The values in `docs/41`–`docs/45` are engineering defaults selected by the delivery team so that implementation can proceed.
- No party outside the delivery team reviewed or authorised them.

DEC-042 remains valid as a **record of adopted working defaults**. Its "Owner Approved" status is downgraded to **"Team-adopted default, owner approval outstanding"**.

Nothing in this decision authorises production posting. BLK-006, BLK-008, and BLK-010 remain Open.

---

## 2. The Governing Rule

> Any value that participates in a calculation which has already been posted must not be a runtime setting.

Changing such a value after the first approved document produces historical balances the system can no longer explain, and there is no correction path. This rule decides every entry in sections 3 and 4.

---

## 3. Architectural — Fixed at Build Time

These are **not** configurable and are not deferred to the owner. Where a choice existed, the **wider** option was taken, so that the narrower behaviour remains reachable later without a data migration.

| # | Decision | Rationale |
|---|---|---|
| A-01 | Purchase invoice lines reference **purchase order lines**, not the PO header | Enables partial supply and one-PO-to-many-invoices later with no migration of posted data |
| A-02 | `stock_balances.average_cost` is maintained **per product per store** | Company-wide aggregation is derivable from per-store; the reverse is impossible |
| A-03 | Tax and discount fields exist on every invoice and line, storing zero when disabled | Enabling tax later requires no migration of historical documents |
| A-04 | Money stored as `decimal(19,4)`; display precision is a presentation concern | Reducing stored precision loses data irreversibly |
| A-05 | Every posting carries an `idempotency_key` on `stock_movements` | Retried approval can never double-post stock or cost |
| A-06 | Stock posting, cost posting, PO state change, and audit occur in one transaction | Partial posting is impossible (AC-PUR-05) |
| A-07 | Receiving follows **Model A** — invoice approval posts stock; no separate receipt document | Matches `docs/02-prd.md` §177, the highest authority. A-01 keeps Model B reachable if the owner later requires it |
| A-08 | Approved documents are immutable; correction is a referenced document | `docs/19` |

A-07 is the only architectural entry that could be revisited by the owner. Because of A-01, revisiting it is a feature addition, not a rebuild.

---

## 4. Configurable — Owner Settings Screen

These carry defaults now and are editable by the owner later. Specification in `docs/46`.

| Setting | Default | Locks after first posting? |
|---|---|---|
| Purchase tax enabled | No | **Yes** |
| Tax rate | 0 | No |
| Tax inclusive / exclusive | Exclusive | **Yes** |
| Tax included in inventory cost | No | **Yes** |
| Discount reduces inventory cost | Yes | **Yes** |
| Rounding mode | Half-up | **Yes** |
| Display decimals (unit / line / invoice) | 4 / 2 / 2 | No |
| Maximum discount | 20% | No |
| Discount above which approval is required | 20% | No |
| Cost variance requiring approval | 5% | No |
| Second approval threshold | 100,000 EGP | No |
| Invoice without PO permitted | Yes, with reason and audit | No |
| Over-receipt permitted | No | No |
| Numbering formats and scope | `PINV-{YYYY}-{00000}`, company-wide | **Yes** |
| Import: max size / rows / types | 10 MB / 5,000 / `.xlsx`,`.csv` | No |
| Import column header names | Per `docs/42` §3 | No |
| Print copies and cost visibility on warehouse copy | 2 copies, cost hidden | No |

Currency is **EGP** for every limit above (closes `OI-LIMIT-05`).

---

## 5. Effective Dating

Every financial setting is stored as a **versioned record with an effective timestamp**, following the `price_lists / versions` pattern already specified in `docs/36` §8. A settings change never overwrites the prior value.

A document is evaluated against the setting version effective at its **approval** time, not at read time. A discount limit raised from 20% to 30% must leave last month's invoices explainable under 20%.

---

## 6. Cutover Lock

Settings marked "Locks after first posting" remain editable until the **first approved purchase invoice or opening stock adjustment exists**. From that moment the Settings screen renders them read-only with the lock date shown, and changing them requires an explicit inventory revaluation procedure that is out of Phase 1 scope.

This gives the owner full customisation before go-live and makes post-go-live corruption structurally impossible.

---

## 7. Demo Master Data

Demo branches and stores are permitted for local development only, under the existing `LocalDemoSeeder` guarded by `EnvironmentSafetyTest` and `LocalDemoSeederSafetyTest`. Every demo row carries a `DEMO-` code prefix.

**BLK-006 is not closed by demo data.** Real branch, store, user, and scope lists remain required before production.

No demo opening stock is created. Opening balances must arise from test purchase invoices so that the weighted-average series is exercised from zero.

---

## 8. Still Required From the Owner

Not decidable by engineering under any circumstances:

1. Real branch list.
2. Real store list, types, owning branch, and which stores receive goods.
3. Real users, roles, branch/store scopes.
4. Cutover timestamp.
5. Opening stock quantities and costs.
6. Whether the company is VAT-registered — this determines whether input VAT belongs in inventory cost, and a wrong answer permanently misstates inventory valuation. **This question is for the client's accountant, not the owner.**
7. The official Excel template artifact.
