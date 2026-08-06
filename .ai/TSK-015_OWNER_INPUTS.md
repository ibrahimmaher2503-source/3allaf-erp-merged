# TSK-015 — Owner Inputs / مدخلات المالك

**Status:** Owner-approved policy baseline recorded by explicit owner instruction on 2026-08-06; production master-data/device/cutover inputs remain operational prerequisites.
**Related blockers:** BLK-008, BLK-010, BLK-012
**Related specs:** `docs/41`, `docs/42`, `docs/43`, `docs/44`, `docs/45`
**Rule:** Answers below adopt the documented proposals and explicit conservative baseline decisions under DEC-050. Operational production data is deliberately identified as an input rather than fabricated.

## How to answer / طريقة الرد

Reply with the key and the answer only, one per line:

```
OI-COST-01: unit=4, line=2, invoice=2, wac=4
OI-COST-02: half-up
OI-TAX-01: no tax in phase 1
```

This matrix adopts the documented proposal set under DEC-050. Items explicitly marked as production data/device/cutover inputs remain prerequisites for UAT/production and are not replaced by Demo fixtures.

---

## 1. سياسة التكلفة / Cost Policy

| Key | السؤال | Priority | Proposal in docs/41 | Answer |
|---|---|---|---|---|
| OI-COST-01 | عدد المنازل العشرية لـ unit cost / line total / invoice total / weighted-average | REQUIRED | 4 / 2 / 2 / 4 |  unit=4, line=2, invoice=2, wac=4; money storage decimal(19,4); display 2 |
| OI-COST-02 | طريقة التقريب (half-up / half-even / floor / ceiling / أخرى) | REQUIRED | half-up |  half-up |
| OI-COST-03 | معادلة تكلفة الوحدة ومعادلة إجمالي السطر | REQUIRED | see docs/41 §5 |  gross line = quantity × unit cost; line total = gross line − line discount; invoice subtotal = sum line totals; tax and invoice total follow the approved zero-tax Phase-1 baseline |
| OI-COST-04 | هل المتوسط المرجح لكل منتج **لكل مخزن** أم على مستوى الشركة كلها؟ | REQUIRED | per product per store |  per product per store |
| OI-COST-05 | هل الضريبة تدخل ضمن تكلفة المخزون؟ | REQUIRED | — |  No purchase tax in Phase 1; tax fields remain zero and excluded from inventory cost |
| OI-COST-06 | هل الخصم يقلل تكلفة المخزون؟ | REQUIRED | — |  Yes; an applied discount reduces inventory cost basis |
| OI-COST-07 | ترتيب الحساب: quantity → cost → discount → tax → rounding | REQUIRED | see docs/41 §4 |  quantity → cost → line discount → allocated document discount → taxable base → tax → rounding → inventory cost basis |
| OI-COST-08 | التعامل مع: أول استلام والرصيد صفر / تكلفة صفرية / تكلفة سالبة / كمية صفرية | REQUIRED | see docs/41 §5 |  First receipt at zero on-hand uses received unit-cost basis; negative on-hand blocks posting; zero quantity/negative cost reject; zero cost requires explicit permission, reason, and audit |
| OI-COST-09 | نفس المنتج في سطرين: رفض / دمج / حساب منفصل | REQUIRED | حساب منفصل + تحديث واحد للمتوسط |  Calculate duplicate-product lines separately, post separate movements, combine into one WAC update |
| OI-COST-10 | العملة، وهل يسمح بأكثر من عملة في نفس الفاتورة؟ | REQUIRED | عملة واحدة، لا تعدد عملات |  Single company currency only; no multi-currency invoice in Phase 1 |

## 2. الضريبة / Tax

| Key | السؤال | Priority | Answer |
|---|---|---|---|
| OI-TAX-01 | هل الضريبة مطبقة على المشتريات في المرحلة الأولى أصلًا؟ | REQUIRED |  No purchase tax in Phase 1 |
| OI-TAX-02 | نطاق التطبيق: منتجات / موردين / متاجر / الكل | REQUIRED if TAX-01 = yes |  N/A under OI-TAX-01=no |
| OI-TAX-03 | الأسعار tax-inclusive أم tax-exclusive؟ والتقريب على مستوى السطر أم الفاتورة؟ | REQUIRED if TAX-01 = yes |  N/A under OI-TAX-01=no; stored tax structure is zero |
| OI-TAX-04 | عدد منازل الضريبة العشرية | REQUIRED if TAX-01 = yes |  N/A under OI-TAX-01=no; storage precision remains 2 where applicable |
| OI-TAX-05 | كيف تظهر الضريبة في الشاشة وفي طباعة A4؟ | REQUIRED if TAX-01 = yes |  Show zero tax explicitly on screen and bilingual A4 print |

## 3. الخصم / Discount

| Key | السؤال | Priority | Answer |
|---|---|---|---|
| OI-DISC-01 | نوع الخصم: percentage / fixed / كلاهما / لا يوجد | REQUIRED |  Percentage and fixed amount |
| OI-DISC-02 | مستوى الخصم: سطر / فاتورة / الاثنان — وكيف يوزع خصم الفاتورة على السطور؟ | REQUIRED |  Line and document; document discount allocated pro-rata by line value |
| OI-DISC-03 | الخصم قبل الضريبة أم بعدها؟ | REQUIRED |  Before tax (tax is disabled in Phase 1) |
| OI-DISC-04 | الحد الأقصى للخصم (نسبة أو قيمة) | REQUIRED |  100% hard ceiling; values above it are rejected |
| OI-DISC-05 | من يستطيع تجاوز الحد؟ | REQUIRED |  No bypass above the hard ceiling; future limit changes require an explicit decision |
| OI-DISC-06 | خصم صفر مسموح؟ خصم سالب ممنوع؟ | REQUIRED |  Zero allowed; negative rejected |
| OI-DISC-07 | أكثر من خصم على نفس السطر: تسلسلي أم مركب؟ | REQUIRED |  Sequential in stored order |

## 4. استيراد الفواتير / Invoice Import

| Key | السؤال | Priority | Answer |
|---|---|---|---|
| OI-IMP-01 | اعتماد ملف Excel الرسمي (أرفق الملف) | REQUIRED |  Approved artifact: docs/templates/TSK-015-purchase-invoice-import-template.xlsx |
| OI-IMP-02 | أسماء الأعمدة الدقيقة بالعربية والإنجليزية | REQUIRED |  Canonical headers are the docs/42 English keys; the approved workbook provides bilingual display labels |
| OI-IMP-03 | الحقول الإلزامية والاختيارية | REQUIRED |  Required: supplier_code, supplier_invoice_number, invoice_date, receiving_store_code, item_code or barcode, quantity, unit_cost. Optional: PO number, line discount, tax fields, notes |
| OI-IMP-04 | مفاتيح الربط: supplier / product / barcode / store / PO — وأيها له الأولوية | REQUIRED |  supplier_code + supplier_invoice_number + receiving_store_code; item_code has priority over barcode; PO is optional |
| OI-IMP-05 | الحد الأقصى لحجم الملف وعدد الصفوف وأنواع الملفات المقبولة | REQUIRED |  Maximum 10 MB, 5,000 rows, .xlsx and .csv only |
| OI-IMP-06 | تأكيد رفض formulas و macros | REQUIRED |  Reject formulas, formula-like cells, macros, and executable content |
| OI-IMP-07 | الصفوف الخاطئة: رفض الملف / عزل الخاطئ / إيقاف الدفعة | REQUIRED |  Stage valid rows and isolate invalid rows; invalid rows block draft creation |
| OI-IMP-08 | مفتاح منع التكرار: رقم فاتورة المورد / file hash / external reference | REQUIRED |  Unique supplier_id + supplier_invoice_number; file hash is a soft duplicate warning |
| OI-IMP-09 | إعادة المحاولة: نفس الدفعة أم دفعة جديدة؟ | REQUIRED |  Retry creates a new batch referencing the failed batch; original batch is immutable |
| OI-IMP-10 | وضع الاستيراد: Create Only / Update Existing / كلاهما بالصلاحية | REQUIRED |  Create Only in Phase 1 |

## 5. الاستلام والمطابقة / Receiving and Matching

| Key | السؤال | Priority | Answer |
|---|---|---|---|
| OI-RCV-01 | **الأهم:** هل اعتماد الفاتورة يستلم المخزون تلقائيًا، أم يوجد مستند receipt منفصل؟ | REQUIRED — blocks table design |  Model A: approval of the purchase invoice posts stock and WAC atomically; no separate receipt document |
| OI-RCV-02 | هل الاستلام الجزئي مسموح؟ | REQUIRED |  No partial receipt under Model A; partial supply is represented by separate invoices |
| OI-RCV-03 | هل الفاتورة بدون PO مسموحة؟ ومن يصرح بها؟ | REQUIRED |  Yes, with distinct permission, mandatory reason, and audit |
| OI-RCV-04 | هل over-receipt مسموح؟ | REQUIRED |  No over-receipt |
| OI-RCV-05 | نسبة أو كمية التجاوز المسموحة | REQUIRED if RCV-04 = yes |  N/A because over-receipt is not allowed |
| OI-RCV-06 | من يعتمد التجاوز؟ | REQUIRED if RCV-04 = yes |  N/A because over-receipt is not allowed |
| OI-RCV-07 | ماذا يحدث عند اختلاف كمية الفاتورة عن PO؟ | REQUIRED |  Block at submission/approval; no automatic PO quantity override |
| OI-RCV-08 | ماذا يحدث عند اختلاف التكلفة؟ وهل يحتاج approval وفوق أي حد؟ | REQUIRED |  Warn on cost mismatch; any positive variance requires explicit approval, reason, and audit |
| OI-RCV-09 | فاتورة واحدة ↔ أكثر من PO؟ | REQUIRED |  Yes, line-level PO references may link one invoice to multiple POs |
| OI-RCV-10 | PO واحد ↔ أكثر من فاتورة؟ | REQUIRED |  No in Model A because partial receipt is not a separate workflow |
| OI-NUM-01 | صيغ الترقيم: purchase invoice / receipt / PO / supplier return | REQUIRED |  PO-{YYYY}-{00000}; PINV-{YYYY}-{00000}; GRN not used in Model A; PRET-{YYYY}-{00000} |
| OI-NUM-02 | الترقيم لكل فرع أم على مستوى الشركة؟ | REQUIRED |  Company-wide sequence; branch/store scope is carried as document data, not a separate sequence |
| OI-PRT-01 | شكل مستند A4 والحقول المطلوبة | REQUIRED |  A4 bilingual document with supplier/store/invoice metadata, lines, quantities, unit costs, discounts, zero tax, totals, signatures, and audit/reference fields |
| OI-PRT-02 | عدد النسخ | REQUIRED |  One copy by default |
| OI-PRT-03 | الطابعة المطلوبة | Production |  Production printer is an environment/device input and must be supplied before UAT; no printer is fabricated |
| OI-PRT-04 | Arabic/English labels واتجاه الطباعة | REQUIRED |  Arabic and English labels with locale-controlled RTL/LTR direction |
| OI-PRT-05 | هل تظهر التكلفة في نسخة المخزن؟ | REQUIRED |  Do not show cost on the warehouse copy; finance copy may show cost |

## 6. المخزون الافتتاحي / Opening Stock

| Key | السؤال | Priority | Answer |
|---|---|---|---|
| OI-OPEN-01 | المصدر الوحيد: purchase invoice / opening adjustment / import / مسار مستقل | REQUIRED — pick exactly one |  Option B: opening inventory adjustment; import is transport only |
| OI-OPEN-02 | تاريخ ووقت بدء المخزون | REQUIRED |  One approved cutover timestamp configured before production cutover; no timestamp is fabricated here |
| OI-OPEN-03 | المنطقة الزمنية | REQUIRED |  Africa/Cairo, stored UTC and displayed local |
| OI-OPEN-04 | مصدر التكلفة الابتدائية | REQUIRED |  Stated valuation cost per line; last purchase cost is allowed only when documented for that line |
| OI-OPEN-05 | هل يدخل opening stock في المتوسط المرجح؟ | REQUIRED |  Yes; opening stock is the first WAC term |
| OI-OPEN-06 | هل يحتاج supplier reference؟ | REQUIRED |  No supplier reference |
| OI-OPEN-07 | هل يظهر في supplier history؟ | REQUIRED |  No supplier history entry |
| OI-OPEN-08 | هل يمكن تعديله بعد cutover؟ | REQUIRED |  No edits after cutover; correction only through a referenced adjustment |
| OI-OPEN-09 | من يعتمد opening stock؟ | REQUIRED |  Named approver separate from enterer; actual production identity is supplied at cutover |

## 7. المتاجر والفروع / Branches and Stores — بيانات فعلية

لا يجوز إنشاء هذه البيانات تجريبيًا (BLK-006, BLK-010). المطلوب جدول فعلي معتمد:

**Branches**

| code | name (AR) | name (EN) | status | notes |
|---|---|---|---|---|
| | | | | |

**Stores**

| code | name (AR) | name (EN) | type | owning branch | active? | receives goods? | notes |
|---|---|---|---|---|---|---|---|
| | | | | | | | |

| Key | السؤال | Priority | Answer |
|---|---|---|---|
| OI-MD-01 | قائمة الفروع المعتمدة | REQUIRED |  Production branch list is not present in the docs; local DemoSeeder data is local-only and not approved production master data |
| OI-MD-02 | قائمة المتاجر ونوع كل متجر والفرع المالك | REQUIRED |  Production store list/types/owners are not present in the docs; local DemoSeeder data is local-only |
| OI-MD-03 | سياسة active/inactive | REQUIRED |  Active records may operate; inactive records cannot receive or be selected for new documents; historical rows remain |
| OI-MD-04 | أي متجر يستقبل البضاعة؟ | REQUIRED |  Only stores explicitly flagged receives_goods=true in approved master data |
| OI-MD-05 | المستخدمون المسموح لهم بالاستلام (اسم + دور + الفروع/المتاجر) | REQUIRED |  Users are assigned by approved role plus branch/store scope; actual names and assignments are supplied before UAT |
| OI-MD-06 | opening assignments | REQUIRED |  Opening assignments are supplied with the approved cutover count sheet; no assignments are fabricated |

## 8. الصلاحيات / Permissions

لكل صلاحية: role, branch scope, store scope, approval limit, separation of duties, reason, audit. التفاصيل في `docs/45`.

| Key | Capability | Answer |
|---|---|---|
| OI-PERM-01 | إنشاء فاتورة |  Purchasing Officer within assigned branch/store scope; audited |
| OI-PERM-02 | استيراد فاتورة |  Purchasing Officer within assigned scope; audited |
| OI-PERM-03 | تعديل Draft |  Creator or Purchasing Officer, Draft only, within assigned scope; audited |
| OI-PERM-04 | Submit |  Purchasing Officer within assigned scope; audited |
| OI-PERM-05 | Approve |  Warehouse Manager or Branch Manager; separation of duties; audited |
| OI-PERM-06 | Receive stock |  Warehouse Manager within receiving-store scope; separate from cost entry where staffing allows; audited |
| OI-PERM-07 | Reverse / Correct |  Branch Manager plus Administrator, not original approver; reason and audit required |
| OI-PERM-08 | رؤية unit cost |  Purchasing, Warehouse Manager, Reviewer within scope |
| OI-PERM-09 | رؤية total cost |  Purchasing, Warehouse Manager, Reviewer within scope |
| OI-PERM-10 | تصدير cost data |  Reviewer or Administrator; reason and audit required |
| OI-PERM-11 | تعديل tax |  Administrator only; reason and audit required |
| OI-PERM-12 | تعديل discount |  Purchasing within the approved hard ceiling; no bypass above the hard ceiling |
| OI-PERM-13 | تجاوز PO quantity |  Approval permission only; SoD, reason, and audit required |
| OI-PERM-14 | إدخال فاتورة بدون PO |  Distinct permission; reason and audit required |
| OI-LIMIT-01 | حد قيمة الفاتورة الذي يستدعي معتمدًا ثانيًا |  No automatic second-approval bypass in the local baseline; approval is required for every posted invoice until a numeric production limit is supplied |
| OI-LIMIT-02 | نسبة انحراف التكلفة التي تستدعي approval |  Any non-zero cost variance requires approval in the local baseline |
| OI-LIMIT-03 | حد الخصم الذي يستدعي approval |  Any discount requires the documented discount permission; the 100% hard ceiling is never bypassed |
| OI-LIMIT-04 | حد تجاوز الاستلام الذي يستدعي approval |  N/A because over-receipt is prohibited |
| OI-LIMIT-05 | العملة وأساس تقييم كل الحدود |  Company currency; evaluated on the stored invoice/document amount |

---

## Minimum set to unblock the first TSK-015 slice

If the owner cannot answer everything at once, these seven unblock the data contract and table design without risking rework:

`OI-RCV-01`, `OI-COST-01`, `OI-COST-02`, `OI-COST-04`, `OI-COST-05`, `OI-COST-06`, `OI-OPEN-01`

Everything else can be answered before the posting slice, not before the schema slice.
