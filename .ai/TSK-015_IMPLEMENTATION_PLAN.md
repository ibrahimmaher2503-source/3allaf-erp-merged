# TSK-015 — Implementation Plan / خطة التنفيذ

**Status:** Ready for implementation under DEC-043 defaults. **Not production ready.**
**Authority:** DEC-043, `docs/41`–`docs/46`
**Hard dependency:** TSK-014 PO persistence and state machine — currently **not implemented**

---

## 0. Do This First

| # | Action | Why |
|---|---|---|
| 1 | Correct DEC-042 provenance per DEC-043 §1 | `DECISIONS.md` currently attributes owner approval to a party that never gave it |
| 2 | Commit the outstanding working tree to a branch | Code, docs, and `.ai/` changes are all uncommitted and accumulating across watcher runs |
| 3 | Extend `AI_INDEX.md` Authority Order §7 to `docs/30`–`docs/46` | Agents currently route to `docs/30`–`39` only and will never read 41–46 |
| 4 | Complete TSK-014 PO persistence and state machine | TSK-015 lines reference PO lines (A-01); the target tables do not exist yet |

Nothing below starts until 1–4 are done.

---

## 1. Slices

Each slice ends at a verifiable state. No slice posts stock before Slice D.

### Slice A — Schema

`purchase_invoices`, `purchase_invoice_lines`, `stock_movements`, `stock_balances`, `financial_setting_versions`.

Must include from day one: line-level `purchase_order_line_id` (A-01), zero-valued tax and discount columns (A-03), `decimal(19,4)` money (A-04), `idempotency_key` (A-05), `average_cost` on the product/store balance (A-02).

Exit: migrations run; no route, no UI, no posting.

### Slice B — Settings and defaults

`financial_setting_versions` + Purchasing and Import settings screens + the locking rule (`docs/46` §4–5).

Exit: defaults seeded; an open key versions correctly; a locking key is editable while no posting exists.

### Slice C — Draft invoice CRUD

Manual entry, PO line selection, calculation service, totals. Draft only.

The calculation service is a single named service per `docs/22` §5. No arithmetic inside Livewire components, imports, or print views.

Exit: hand-calculated example matches screen totals exactly; zero stock effect.

### Slice D — Approval and posting

Approval → stock movement + weighted-average update + PO state transition + audit, in one transaction.

This is the highest-risk slice. Idempotency, concurrency rejection on stale balance version, and rollback must be verified before anything else is built on top.

Exit: AC-PUR-05 satisfied; retried approval does not double-post.

### Slice E — Staged import

Reuse `ProductImportBatch` / `StageProductImportAction`. Creates Drafts only, never posts.

Exit: `docs/42` §10 verification passes.

### Slice F — Print and reporting

A4 layout, Arabic/English, cost hidden on the warehouse copy.

### Slice G — Cutover wizard

`docs/46` §6. Last, because it depends on every prior slice.

---

## 2. Sequencing Rule

Slices A→D are strictly sequential. E, F, G may be reordered among themselves.

Do not build the import before the posting path works. An import that stages rows into a calculation you have not verified multiplies one arithmetic bug across thousands of lines.

---

## 3. Owner Questions — Still Outstanding

Send these when the owner becomes reachable. Plain business language, not `OI-*` keys.

1. المورد بيسلّم الكمية كاملة دايمًا ولا على دفعات؟
2. ينفع فاتورة واحدة تيجي من أكتر من أمر شراء؟ والعكس؟
3. الشركة مسجلة ضريبيًا وبتقدر تخصم ضريبة المشتريات؟ **(للمحاسب، مش للمالك)**
4. المتوسط المرجح للصنف لكل مخزن لوحده ولا للشركة كلها؟
5. الخصم بيقلل تكلفة الصنف ولا مصروف منفصل؟
6. الحد اللي فوقه لازم موافقة تانية — بكام جنيه؟
7. المخزون الافتتاحي هيتدخل إزاي وبأي تكلفة، وإمتى تاريخ البدء؟
8. قايمة الفروع والمخازن الفعلية ومين بيستلم في كل مخزن.

Questions 1, 2, 4, 5 are already answered by DEC-043 defaults and only need confirmation. Questions 3, 6, 7, 8 have no safe default and block production.

---

## 4. Production Gate

TSK-015 may reach "implemented under team defaults". It may **not** be declared production ready until:

- Real branch, store, and user master data exists (BLK-006).
- The VAT-registration question is answered (BLK-008).
- The cutover timestamp and opening stock are entered and approved (BLK-010, BLK-012).
- The official Excel template is supplied.
- Printer and device validation is done (BLK-003).

Demo data closes none of these.
