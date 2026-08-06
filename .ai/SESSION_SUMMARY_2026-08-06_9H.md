# ملخص العمل — آخر 9 ساعات

**التاريخ المرجعي:** 2026-08-06 07:40 CEST
**المستودع:** `toy-joy-phase-1-documentation`
**Git root المؤكد:** `/home/ubuntu/projects/toy-joy-phase-1-documentation`
**الفرع:** `chore/observability-and-repo-discipline`
**النطاق الزمني:** تقريبًا من 04:40 إلى 07:40 CEST، مع تضمين آخر commits المرتبطة مباشرة بالتسليم المحلي.

> هذا الملف يلخص ما تم تنفيذه والتحقق منه فعليًا. لا يعتبر موافقة إنتاجية أو UAT نهائيًا، ولا يحول أي owner input أو blocker إلى approval.

## 1. ملخص تنفيذي

تم خلال هذه الفترة إنجاز slices محلية متتابعة في أربعة محاور:

1. **Purchase Orders وInventory Ledger foundation** مع إبقاء posting/import وstock mutation وWAC خارج النطاق.
2. **Observability وانضباط المستودع** مع hooks وفحوص ثابتة وfixture أداء disposable.
3. **Bulk Operations foundation** آمنة ومحدودة ومربوطة بإجراءات domain الحالية.
4. **Table UX وTutorial/Page Guide**، بما في ذلك فصل بيانات الـTutorial عن Registry المركزي وتحسين Full Guide بشكل كبير.

الحالة الحالية: worktree كان نظيفًا بعد آخر commit قبل بدء شريحة readiness الحالية؛ توجد الآن تغييرات محلية pending لهذه الشريحة. الفحوص الأساسية السابقة ناجحة، بينما ما زالت بعض التحققات النهائية مثل mobile acceptance وproduction readiness خارج نطاق ما تم إثباته.

## 2. Purchase Orders وLedger Foundation

تم تسليم الأساس المحلي التالي:

- إضافة approval fields وindex إلى `purchase_orders`.
- إضافة `ApprovePurchaseOrderAction` مع:
  - authorization.
  - submitted-only transition.
  - optimistic version check.
  - منع self-approval.
  - audit atomicity.
- جعل approved documents غير قابلة للتعديل ضمن boundary الحالي.
- إضافة status presentation وقيود close transition.
- فرض branch/store visibility على القائمة والتفاصيل وstore selector.
- إخفاء self-approval في الواجهة مع إبقاء backend guard authoritative.
- إضافة Demo-only seeding entrypoint قابل للعكس للتحقق المحلي.
- إضافة TSK-015 Slice A وPerformance Group A كـlocal reversible schema/diagnostic work:
  - invoice/invoice-line foundation.
  - stock movements/balances.
  - period snapshots.
  - composite indexes.
  - `consumed_cost` كحقل مستقبلي فقط.
  - `inventory:rebuild-balances` مع dry-run وtransactional apply.
- توثيق سياسات purchase cost/tax/discount، receiving، opening stock، ledger، POS rules، reporting، offline behavior، deployment، reconciliation، charts، والأداء.

**ما لم يتم اعتماده أو تنفيذه:** invoice posting/import، receipt mutation، stock posting، WAC calculation، production financial approval، أو production readiness.

## 3. Observability وانضباط المستودع

تم تنفيذ local/development safeguards:

- `Model::preventLazyLoading()` خارج production.
- query budget configurable، افتراضيًا 100 query.
- slow-query threshold وقناة `slow_queries`.
- Debugbar dev-only.
- `config/performance.php` وإعدادات logging ذات الصلة.
- `PROJECT_NAME` وenvironment configuration دون حفظ credentials.
- tracked pre-commit hook في `.githooks/pre-commit` يشغل:
  - Pint.
  - PHPStan.
  - locale-key parity.
  - staged whitespace checks.
- `scripts/setup-git-hooks.php` وربط `core.hooksPath`.
- `scripts/check-locale-keys.php`.
- locale parity ثابتة عند:
  ```text
  974 keys in ar.json and en.json
  ```
- PHPStan baseline صريح للـlegacy findings، مع تنظيف findings القديمة الخاصة بـTutorialRegistry بعد refactor.
- `scripts/seed-performance-fixture.php` تم اختباره على SQLite مؤقت خارج قاعدة Demo:
  - 50,000 products.
  - 1,000,000 stock movements.
  - تم حذف قاعدة الأداء بعد التحقق.
- `scripts/ai/run-gemini.sh` صار يتحقق من Git root وproject identity ولا يعتمد على port لتحديد المستودع.
- watcher `toy-joy-milestone-watcher` بقي paused/disabled أثناء التعديلات.

## 4. Bulk Operations

تم إنشاء foundation مشتركة بدل إضافة mutations غير معتمدة:

- `app/Support/Bulk/WithBulkSelection.php`:
  - selected IDs normalization.
  - positive integer validation.
  - selection limit افتراضي 100.
  - empty/over-limit validation.
  - iteration وclear/reset.
  - reset عند pagination/search changes.
- `resources/views/components/tables/bulk-actions.blade.php`:
  - current-page select all.
  - clear selection.
  - selected count.
  - limit indicator.
  - confirmation attributes.
  - تنبيه أن cross-page processing يحتاج queued processing.
- تم الدمج في:
  - Products.
  - Categories.
  - Brands.
  - Suppliers.
  - Branches.
  - Stores.
- status bulk actions تعيد استخدام domain actions الحالية:
  - `SaveProductAction`.
  - `SaveCategoryAction`.
  - `SaveBrandAction`.
  - `ToggleSupplierStatusAction`.
  - `SaveBranchAction`.
  - `SaveStoreAction`.
- تم منع إضافة bulk delete/archive أو PO/import/authorization mutations بدون business approval.
- تم تحديث `.ai/BULK_OPERATIONS.md` ليشمل الموارد الحالية والفجوات:
  - queue.
  - audit envelope.
  - idempotency.
  - concurrency.
  - product import.
  - settings resources.
- القرار المرتبط موثق في `.ai/DECISIONS.md` تحت DEC-048.

**الفجوات المفتوحة:** لا يوجد generic queued executor أو generic bulk audit envelope حاليًا، وcross-page processing لا يدّعي وجوده.

## 5. Table UX وQuery Refactor

تم تحسين الجداول shared-first:

- نقل filtering/pagination/eager loading من Blade إلى `render()` في:
  - `branches.blade.php`.
  - `stores.blade.php`.
- إضافة `app-table-frame` للجداول المشتركة.
- توحيد table surface بخلفية opaque semantic في light/dark themes.
- تحسين header/row separators/hover/focus states.
- دعم Flux table DOM attributes الفعلية.
- تحسين Product Masters:
  - `catalog-resource-table`.
  - minimum widths.
  - `table-layout: auto`.
  - safe wrapping.
  - internal horizontal overflow.
- الحفاظ على عدم توسع page body؛ overflow بقي داخل إطار الجدول.
- تمت مراجعة Products وBranches وStores بصريًا على desktop، مع light وdark appearance.
- لم تتم إضافة package جديد مثل Excel/Horizon/Pusher/Sanctum/Spatie Permission؛ capability gap غير مثبت حاليًا.

## 6. Tutorial Registry وFull Guide

### 6.1 Tutorial Registry

تم تحويل الـTutorial إلى بنية أسهل للتوسعة:

```text
app/Modules/Platform/Support/TutorialRegistry.php
app/Modules/Platform/Tutorials/*.php
```

بدل ملف Registry مركزي كبير، أصبح لكل شاشة ملف مستقل يحتوي:

- Screen ID.
- route names.
- localized title/purpose/when-to-use.
- approved actions.
- localized steps.
- stable selectors.
- fields.
- notes/warnings/errors/FAQ.
- flows وacceptance references.
- version/update metadata.

الـRegistry يحتفظ مركزيًا بـ:

- discovery.
- identity validation.
- route lookup.
- Screen ID lookup.
- safe fallback.

تم توثيق طريقة إضافة شاشة جديدة في:

```text
docs/57-tutorial-content-authoring.md
```

وتحديث:

```text
docs/40-contextual-page-guide-specification.md
```

تم تسجيل 19 tutorial definitions و18 route names، مع الحفاظ على shared authorization screen IDs.

### 6.2 إرشادات الجداول والـBulk داخل Tutorial

أضيفت خطوات Tutorial فعلية للشاشات التالية:

- Products.
- Categories.
- Brands.
- Suppliers.
- Branches.
- Stores.

وتغطي:

- table filters.
- table/pagination.
- horizontal table behavior.
- current-page selection.
- selection limit.
- safe bulk operations.
- status confirmation.
- role-aware approved actions.

تم استخدام target حقيقي للـbulk region:

```html
role="region"
aria-label="Bulk operations"
```

### 6.3 Full Guide

تم توسيع `resources/views/platform/help/screen.blade.php` من دليل مختصر إلى دليل عملي كامل يعرض:

- overview.
- purpose وcorrect use.
- role-aware available actions.
- ordered usage steps.
- fields and interface elements.
- operating notes.
- warnings.
- errors and recovery.
- FAQ مستقل.
- related user workflows.
- scope disclaimer.
- quick navigation sidebar.
- print guide وprint-friendly CSS.
- version/update metadata.
- CTA للعودة إلى المهمة.

تم تحديث `PageGuideContext` ليعيد route names وpermission-filtered permissions داخل safe context، مع بقاء permission keys غير معروضة للمستخدم.

تم تحديث `DashboardAssistantController` ليقوم بـ:

- فلترة permissions/actions قبل العرض.
- تمرير related flow summaries sanitized فقط.
- عدم تمرير models أو private paths أو secrets أو exception payloads.

## 7. Browser وVisual Verification

تم التحقق من:

- Page Guide على Products.
- Interactive Tour من البداية حتى خطوة `Safe Bulk Operations`.
- وجود bulk region الحقيقية في DOM.
- Full Guide باللغة الإنجليزية.
- Full Guide باللغة العربية.
- RTL layout.
- Hero/cards/sidebar/FAQ/related flows.
- Light appearance.
- Dark appearance للجداول والـbulk toolbar في المراجعة السابقة.
- console JavaScript errors: لا توجد errors مؤثرة؛ آخر فحص Full Guide كان `0` errors.

تم استخدام Demo Auth محلي فقط أثناء التحقق، دون حفظ credentials في repository أو إظهارها في هذا الملخص.

**ما لم يكتمل:** mobile viewport acceptance matrix الكاملة، واختبار كل شاشة/كل دور/كل transition في الجولة.

## 8. الفحوص الناجحة

الفحوص التي نجحت خلال الفترة:

```text
Pint: PASS
PHPStan: PASS
Locale parity: PASS (974/974)
Blade view cache: PASS
Vite build: PASS
Route/config diagnostics: PASS
git diff --check: PASS
Pre-commit hook: PASS
```

ظهر فقط warning اختياري معروف من Vite متعلق بحزمة `fontaine`، ولم يمنع build.

## 9. Commits خلال الفترة

```text
b714608 feat: deliver purchase order and ledger foundation slices
fcf18f1 chore: enforce observability and repository discipline
9d47121 feat: add safe bulk selection foundation
e924ea8 docs: complete bulk operations inventory
b647880 refactor: move table queries into render
1843315 feat: unify resource table surfaces
df57f90 refactor: make tutorial content extensible
982b880 feat: expand full page guide experience
```

آخر commit متعلق بالـFull Guide:

```text
982b880 feat: expand full page guide experience
```

آخر commit متعلق بتوسعة Tutorial data:

```text
df57f90 refactor: make tutorial content extensible
```

## 10. القرارات المؤجلة والـBlockers

- TSK-014 ما زال In Progress حسب handoff الحالي.
- TSK-015 Slice A محلي وقابل للعكس، ولا يمثل financial posting أو production readiness.
- mobile browser verification ما زالت مطلوبة.
- لا توجد queued bulk workloads فعلية حاليًا، لذلك لا تتم إضافة Horizon قبل وجود requirement وعقد queue/retry/idempotency.
- لا توجد API/mobile/broadcasting requirements معتمدة، لذلك Sanctum/Pusher خارج النطاق.
- OpenSpout هو مسار الاستيراد الحالي؛ لا تتم إضافة Maatwebsite Excel دون capability decision.
- bulk delete/archive وPO/import/authorization bulk mutations تحتاج owner/business semantics واضحة.
- لا يوجد push ناجح إلى remote بسبب عدم توفر authentication؛ لا يتم تخزين أو طلب credentials داخل الملفات.
- لا يتم claim production readiness أو official UAT من هذه الجلسة.

## 11. الحالة الحالية والإجراء التالي

**الحالة الحالية:**

- Git root صحيح.
- الفرع الصحيح مستخدم.
- آخر commits موجودة محليًا.
- worktree كان نظيفًا بعد آخر commit قبل بدء شريحة readiness الحالية؛ تغييرات هذه الشريحة pending verification/commit.
- watcher متوقف.
- Tutorial وFull Guide محسنان ومتحقق منهما جزئيًا عبر browser.

**الإجراء التالي المقترح:**

1. إكمال verification وcommit لشريحة TSK-015 read-only readiness الحالية.
2. إضافة mobile viewport browser evidence للـFull Guide والجداول.
3. مراجعة كل registered screen للتأكد من اكتمال `errors/FAQ/approved_actions` وعدم وجود نصوص placeholder.
4. تنفيذ Full Guide/flow verification لكل role متاح بدون إدخال credentials حقيقية.
5. إبقاء production gates وowner inputs unresolved حتى تأتي موافقات صريحة.
