# TSK-015 — Owner Inputs / مدخلات المالك

**Status:** Awaiting owner answers — TSK-015 implementation is blocked until every `REQUIRED` key below is answered.
**Related blockers:** BLK-008, BLK-010, BLK-012
**Related specs:** `docs/41`, `docs/42`, `docs/43`, `docs/44`, `docs/45`
**Rule:** An answered key must be transcribed into `.ai/DECISIONS.md` as an approved decision. This file collects answers; `DECISIONS.md` makes them authoritative.

## How to answer / طريقة الرد

Reply with the key and the answer only, one per line:

```
OI-COST-01: unit=4, line=2, invoice=2, wac=4
OI-COST-02: half-up
OI-TAX-01: no tax in phase 1
```

Any key left blank stays blocked. Do not answer "as you see fit" for keys marked `REQUIRED` — a wrong assumption here produces financial data that cannot be corrected later.

---

## 1. سياسة التكلفة / Cost Policy

| Key | السؤال | Priority | Proposal in docs/41 | Answer |
|---|---|---|---|---|
| OI-COST-01 | عدد المنازل العشرية لـ unit cost / line total / invoice total / weighted-average | REQUIRED | 4 / 2 / 2 / 4 | |
| OI-COST-02 | طريقة التقريب (half-up / half-even / floor / ceiling / أخرى) | REQUIRED | half-up | |
| OI-COST-03 | معادلة تكلفة الوحدة ومعادلة إجمالي السطر | REQUIRED | see docs/41 §5 | |
| OI-COST-04 | هل المتوسط المرجح لكل منتج **لكل مخزن** أم على مستوى الشركة كلها؟ | REQUIRED | per product per store | |
| OI-COST-05 | هل الضريبة تدخل ضمن تكلفة المخزون؟ | REQUIRED | — | |
| OI-COST-06 | هل الخصم يقلل تكلفة المخزون؟ | REQUIRED | — | |
| OI-COST-07 | ترتيب الحساب: quantity → cost → discount → tax → rounding | REQUIRED | see docs/41 §4 | |
| OI-COST-08 | التعامل مع: أول استلام والرصيد صفر / تكلفة صفرية / تكلفة سالبة / كمية صفرية | REQUIRED | see docs/41 §5 | |
| OI-COST-09 | نفس المنتج في سطرين: رفض / دمج / حساب منفصل | REQUIRED | حساب منفصل + تحديث واحد للمتوسط | |
| OI-COST-10 | العملة، وهل يسمح بأكثر من عملة في نفس الفاتورة؟ | REQUIRED | عملة واحدة، لا تعدد عملات | |

## 2. الضريبة / Tax

| Key | السؤال | Priority | Answer |
|---|---|---|---|
| OI-TAX-01 | هل الضريبة مطبقة على المشتريات في المرحلة الأولى أصلًا؟ | REQUIRED | |
| OI-TAX-02 | نطاق التطبيق: منتجات / موردين / متاجر / الكل | REQUIRED if TAX-01 = yes | |
| OI-TAX-03 | الأسعار tax-inclusive أم tax-exclusive؟ والتقريب على مستوى السطر أم الفاتورة؟ | REQUIRED if TAX-01 = yes | |
| OI-TAX-04 | عدد منازل الضريبة العشرية | REQUIRED if TAX-01 = yes | |
| OI-TAX-05 | كيف تظهر الضريبة في الشاشة وفي طباعة A4؟ | REQUIRED if TAX-01 = yes | |

## 3. الخصم / Discount

| Key | السؤال | Priority | Answer |
|---|---|---|---|
| OI-DISC-01 | نوع الخصم: percentage / fixed / كلاهما / لا يوجد | REQUIRED | |
| OI-DISC-02 | مستوى الخصم: سطر / فاتورة / الاثنان — وكيف يوزع خصم الفاتورة على السطور؟ | REQUIRED | |
| OI-DISC-03 | الخصم قبل الضريبة أم بعدها؟ | REQUIRED | |
| OI-DISC-04 | الحد الأقصى للخصم (نسبة أو قيمة) | REQUIRED | |
| OI-DISC-05 | من يستطيع تجاوز الحد؟ | REQUIRED | |
| OI-DISC-06 | خصم صفر مسموح؟ خصم سالب ممنوع؟ | REQUIRED | |
| OI-DISC-07 | أكثر من خصم على نفس السطر: تسلسلي أم مركب؟ | REQUIRED | |

## 4. استيراد الفواتير / Invoice Import

| Key | السؤال | Priority | Answer |
|---|---|---|---|
| OI-IMP-01 | اعتماد ملف Excel الرسمي (أرفق الملف) | REQUIRED | |
| OI-IMP-02 | أسماء الأعمدة الدقيقة بالعربية والإنجليزية | REQUIRED | |
| OI-IMP-03 | الحقول الإلزامية والاختيارية | REQUIRED | |
| OI-IMP-04 | مفاتيح الربط: supplier / product / barcode / store / PO — وأيها له الأولوية | REQUIRED | |
| OI-IMP-05 | الحد الأقصى لحجم الملف وعدد الصفوف وأنواع الملفات المقبولة | REQUIRED | |
| OI-IMP-06 | تأكيد رفض formulas و macros | REQUIRED | |
| OI-IMP-07 | الصفوف الخاطئة: رفض الملف / عزل الخاطئ / إيقاف الدفعة | REQUIRED | |
| OI-IMP-08 | مفتاح منع التكرار: رقم فاتورة المورد / file hash / external reference | REQUIRED | |
| OI-IMP-09 | إعادة المحاولة: نفس الدفعة أم دفعة جديدة؟ | REQUIRED | |
| OI-IMP-10 | وضع الاستيراد: Create Only / Update Existing / كلاهما بالصلاحية | REQUIRED | |

## 5. الاستلام والمطابقة / Receiving and Matching

| Key | السؤال | Priority | Answer |
|---|---|---|---|
| OI-RCV-01 | **الأهم:** هل اعتماد الفاتورة يستلم المخزون تلقائيًا، أم يوجد مستند receipt منفصل؟ | REQUIRED — blocks table design | |
| OI-RCV-02 | هل الاستلام الجزئي مسموح؟ | REQUIRED | |
| OI-RCV-03 | هل الفاتورة بدون PO مسموحة؟ ومن يصرح بها؟ | REQUIRED | |
| OI-RCV-04 | هل over-receipt مسموح؟ | REQUIRED | |
| OI-RCV-05 | نسبة أو كمية التجاوز المسموحة | REQUIRED if RCV-04 = yes | |
| OI-RCV-06 | من يعتمد التجاوز؟ | REQUIRED if RCV-04 = yes | |
| OI-RCV-07 | ماذا يحدث عند اختلاف كمية الفاتورة عن PO؟ | REQUIRED | |
| OI-RCV-08 | ماذا يحدث عند اختلاف التكلفة؟ وهل يحتاج approval وفوق أي حد؟ | REQUIRED | |
| OI-RCV-09 | فاتورة واحدة ↔ أكثر من PO؟ | REQUIRED | |
| OI-RCV-10 | PO واحد ↔ أكثر من فاتورة؟ | REQUIRED | |
| OI-NUM-01 | صيغ الترقيم: purchase invoice / receipt / PO / supplier return | REQUIRED | |
| OI-NUM-02 | الترقيم لكل فرع أم على مستوى الشركة؟ | REQUIRED | |
| OI-PRT-01 | شكل مستند A4 والحقول المطلوبة | REQUIRED | |
| OI-PRT-02 | عدد النسخ | REQUIRED | |
| OI-PRT-03 | الطابعة المطلوبة | Production | |
| OI-PRT-04 | Arabic/English labels واتجاه الطباعة | REQUIRED | |
| OI-PRT-05 | هل تظهر التكلفة في نسخة المخزن؟ | REQUIRED | |

## 6. المخزون الافتتاحي / Opening Stock

| Key | السؤال | Priority | Answer |
|---|---|---|---|
| OI-OPEN-01 | المصدر الوحيد: purchase invoice / opening adjustment / import / مسار مستقل | REQUIRED — pick exactly one | |
| OI-OPEN-02 | تاريخ ووقت بدء المخزون | REQUIRED | |
| OI-OPEN-03 | المنطقة الزمنية | REQUIRED | |
| OI-OPEN-04 | مصدر التكلفة الابتدائية | REQUIRED | |
| OI-OPEN-05 | هل يدخل opening stock في المتوسط المرجح؟ | REQUIRED | |
| OI-OPEN-06 | هل يحتاج supplier reference؟ | REQUIRED | |
| OI-OPEN-07 | هل يظهر في supplier history؟ | REQUIRED | |
| OI-OPEN-08 | هل يمكن تعديله بعد cutover؟ | REQUIRED | |
| OI-OPEN-09 | من يعتمد opening stock؟ | REQUIRED | |

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
| OI-MD-01 | قائمة الفروع المعتمدة | REQUIRED | |
| OI-MD-02 | قائمة المتاجر ونوع كل متجر والفرع المالك | REQUIRED | |
| OI-MD-03 | سياسة active/inactive | REQUIRED | |
| OI-MD-04 | أي متجر يستقبل البضاعة؟ | REQUIRED | |
| OI-MD-05 | المستخدمون المسموح لهم بالاستلام (اسم + دور + الفروع/المتاجر) | REQUIRED | |
| OI-MD-06 | opening assignments | REQUIRED | |

## 8. الصلاحيات / Permissions

لكل صلاحية: role, branch scope, store scope, approval limit, separation of duties, reason, audit. التفاصيل في `docs/45`.

| Key | Capability | Answer |
|---|---|---|
| OI-PERM-01 | إنشاء فاتورة | |
| OI-PERM-02 | استيراد فاتورة | |
| OI-PERM-03 | تعديل Draft | |
| OI-PERM-04 | Submit | |
| OI-PERM-05 | Approve | |
| OI-PERM-06 | Receive stock | |
| OI-PERM-07 | Reverse / Correct | |
| OI-PERM-08 | رؤية unit cost | |
| OI-PERM-09 | رؤية total cost | |
| OI-PERM-10 | تصدير cost data | |
| OI-PERM-11 | تعديل tax | |
| OI-PERM-12 | تعديل discount | |
| OI-PERM-13 | تجاوز PO quantity | |
| OI-PERM-14 | إدخال فاتورة بدون PO | |
| OI-LIMIT-01 | حد قيمة الفاتورة الذي يستدعي معتمدًا ثانيًا | |
| OI-LIMIT-02 | نسبة انحراف التكلفة التي تستدعي approval | |
| OI-LIMIT-03 | حد الخصم الذي يستدعي approval | |
| OI-LIMIT-04 | حد تجاوز الاستلام الذي يستدعي approval | |
| OI-LIMIT-05 | العملة وأساس تقييم كل الحدود | |

---

## Minimum set to unblock the first TSK-015 slice

If the owner cannot answer everything at once, these seven unblock the data contract and table design without risking rework:

`OI-RCV-01`, `OI-COST-01`, `OI-COST-02`, `OI-COST-04`, `OI-COST-05`, `OI-COST-06`, `OI-OPEN-01`

Everything else can be answered before the posting slice, not before the schema slice.
