# 54 — Production Data Migration and Reconciliation

**Product:** TOY & JOY
**Phase:** Phase 1
**Status:** Derived implementation specification — team-adopted defaults, owner data outstanding
**Authority:** MD-01–MD-06, PRC-01, INV-01, NFR-01, NFR-02
**Blockers:** BLK-006 (branch/store/drawer structure), BLK-009 (catalog and barcode), BLK-010 (supplier and opening stock), BLK-012 (opening inventory)
**Task:** TSK-041 — Import and Reconcile Approved Production Master Data

---

## 1. Purpose

TSK-041 has no supporting specification. This document defines the order in which real master data enters the system, how each stage is verified, and what makes the migration reversible before cutover and irreversible after it.

The distinguishing risk: master data errors are cheap to fix before the first posting and expensive afterwards. Sequencing exists to keep every correctable error in the correctable window.

---

## 2. Load Order

Dependencies dictate the order. Nothing may load before its parents exist.

```
1. Company identity, currency, timezone
2. Branches
3. Stores, types, owning branch, receives-goods flag
4. Selling-store mappings
5. Cash drawers and assignments
6. Users, roles, branch/store scopes
7. Categories and brands
8. Products, UOM, types, fractional flags
9. Barcodes
10. Suppliers and terms
11. Product–supplier links and last prices
12. Price lists and approved sale prices
13. Financial settings review and lock (docs/46)
14. Opening stock (docs/44)
15. Cutover
```

Steps 1–12 are correctable. Step 14 is the last correctable step, and only until step 15. After cutover, every correction is a referenced adjustment visible permanently in the audit trail.

---

## 3. Per-Stage Gate

No stage begins until the previous stage passes all four checks:

| Check | Question |
|---|---|
| Completeness | Does the loaded row count match the source count? |
| Referential | Does every foreign reference resolve? |
| Uniqueness | Are codes, barcodes, and phone numbers unique as required? |
| Sample | Do ten randomly selected rows match the source document field by field? |

A failed check stops the migration. It does not proceed with a noted exception.

---

## 4. Source Requirements

Each stage needs an approved source artifact from the owner — a signed list or file, not a verbal confirmation. `.ai/TSK-015_OWNER_INPUTS.md` §7 carries the branch and store templates.

Where a source is unavailable, the stage is blocked. Inventing plausible master data is prohibited; BLK-006 states directly that no production master data is inferred.

---

## 5. Import Mechanics

Reuse the staged-import pattern (`docs/42`, `ProductImportBatch`): upload, validate, stage, preview, approve, commit. Applies to products, barcodes, suppliers, prices, and opening stock.

Rules:

1. Every import runs in **Create Only** mode during migration. Update mode is enabled only for a deliberate correction pass, under permission.
2. Every batch retains its source file through `docs/18` private storage, so a figure can be traced back to the document it came from.
3. Duplicate detection is on by default; a duplicate is an error to investigate, never an auto-merge.
4. Error rows are isolated and downloadable, and the stage does not pass until the error file is empty.

---

## 6. Reconciliation

Before cutover:

| Reconciliation | Passing condition |
|---|---|
| Branch and store count | Matches the owner's approved list exactly |
| User scope | Every user has at least one branch scope; no user has unintended global scope |
| Product count | Matches the source catalog |
| Barcode uniqueness | Zero duplicates across the catalog |
| Unpriced products | List reviewed and accepted — unpriced items cannot be sold (`docs/24` §7) |
| Opening quantity per store | Matches the count sheets |
| Opening value per store | Matches the valuation the owner approved |
| Ledger consistency | Balances rebuilt from movements match stored balances (`docs/47` §5) |

The last two are signed off by the owner or a delegate before step 15. That signature is what converts a data load into a book of record.

---

## 7. Post-Cutover

1. Backdated movements before the cutover timestamp are rejected (`docs/46` §7).
2. Master data corrections continue normally — a product name is not financial data.
3. Opening stock corrections are referenced adjustments only, never edits (`docs/44` §5).
4. Demo or test rows, if any exist in the target database, are removed and verified absent **before** step 14, never after.

---

## 8. Manual Verification

Verify: each stage gate blocks the next stage on failure; a referential failure is caught rather than silently skipped; duplicate barcodes are rejected; source files are retrievable from private storage; opening quantities and values match count sheets per store; balances reconcile to movements; backdating is rejected after cutover; no test or demo row survives into production; every migration action carries an audit record.

No automated tests are created or executed.
