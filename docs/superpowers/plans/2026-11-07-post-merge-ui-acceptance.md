# Post-Merge UI Acceptance and Full-System Test Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox syntax for tracking.

**Goal:** تنفيذ اختبار قبول شامل بعد الدمج على الفرع merge/3allaf-plus-rajeh من خلال واجهة المستخدم الفعلية، يمر على كل شاشة ورابط وزر وعملية تجارية أساسية، ويثبت النتائج المالية والمخزنية والصلاحيات بالأدلة ثم ينتج قرارًا واضحًا.

**Architecture:** طبقة Playwright فوق Laravel الحالي، مع MariaDB/MySQL مخصصة وقابلة لإعادة البناء. المتصفح وحده ينفذ تسجيل الدخول والتنقل والتعبئة والضغط والطباعة والتنزيل والتحقق؛ يسمح Artisan/SQL فقط بتهيئة قاعدة الاختبار وقراءة التحقق بعد العملية. كل وحدة تنتج سجل خطوات وصورًا وtrace عند الفشل وملف نتائج قابلًا للمقارنة.

**Tech Stack:** PHP 8.4، Laravel 13، Livewire 4، Blade/Flux/Tailwind، MariaDB/MySQL على 127.0.0.1:3306، Playwright الموجود في المشروع، Node/npm، PHPUnit كمساعد فقط.

**Spec:** C:/Users/N/.codex/attachments/37734400-dbe7-4525-b55b-f6c10eb03a95/Pasted text.txt، وقرارات الدمج في MERGE_REPORT.md.

## Global Constraints

- العمل على فرع جانبي فقط؛ لا تعديل أو push إلى main أو merge/3allaf-plus-rajeh.
- SQLite ممنوع؛ كل القواعد والاختبارات على MariaDB/MySQL.
- استخدم قاعدة باسم qa_rajh_m1_ui_acceptance_20261007 أو اسم يطابق qa_rajh_m1_*_20261007.
- لا تنفذ عملية تجارية عبر API أو Livewire internals أو Tinker؛ كل العمليات التجارية من المتصفح.
- لا تعتبر الصفحة الناجحة دليلًا على نجاح العملية؛ يجب الضغط، رؤية الرسالة، ثم التحقق من النتيجة الظاهرة وقراءة تحقق مالية/مخزنية.
- كل اختبار يسجل ID، الدور، اللغة/الاتجاه، الشاشة، الخطوات، المتوقع، الفعلي، screenshot، ومرجع DB عند الحاجة.
- عند الفشل احفظ trace وscreenshot وHTML وconsole/network logs ولا تكمل اختبارات تعتمد عليه.
- كل زر حساس يختبر نجاحًا ورفضًا/صلاحية/تحقق، مع عدم وجود أثر جزئي عند الرفض.
- كل شاشة مطلوبة تختبر بالعربية RTL والإنجليزية LTR وعلى desktop/mobile؛ POS يختبر لوحة المفاتيح.
- أي إصلاح يسبقه finding واختبار UI فاشل؛ كل إصلاح commit مستقل.
- لا تدّع نجاح اختبار لم يُنفذ فعليًا.

## Review Focus

- الكمية والوحدة: 2.500000 في البيع والشراء والتحويل والجرد، مع حفظ entered/base والتكلفة؛ Tasks 5 و6 و8.
- المال والـ ledgers: الخصم والضريبة وlanded cost والدفع الجزئي وحد الائتمان والعكس؛ Tasks 5 و8 و9.
- الصلاحيات والنطاق: خارج الشركة/الفرع/المتجر، proposer يعتمد سعره، أو role بلا permission؛ Tasks 2 و7 و11.
- الحالة والتكرار: double-click وrefresh وback/forward وإعادة الاستلام الجزئي؛ Tasks 5 و6 و12.
- الواجهة: loading/empty/error/success/disabled/validation/confirmation/denied وRTL/LTR/mobile؛ Tasks 3 و12.

---

### Task 1: تجهيز البيئة وPlaywright

**Files:**
- Create: docs/superpowers/plans/2026-11-07-post-merge-ui-acceptance.md
- Create: tests/Browser/support/env.ts
- Create: tests/Browser/support/auth.ts
- Create: tests/Browser/support/evidence.ts
- Create: tests/Browser/support/db-checks.ts
- Create: work/ui-acceptance/README.md
- Create: work/ui-acceptance/manifests/route-manifest.json
- Create: work/ui-acceptance/manifests/screen-manifest.json
- Modify: playwright.config.js only for baseURL/projects/trace

**Interfaces:**
- env.ts: baseURL, dbName, artifactsDir, locale, viewport.
- auth.ts: loginAs(page, role), logout(page), assertAuthenticated(page).
- evidence.ts: step(page, id, action), capture(page, id), recordFailure(testInfo).
- db-checks.ts: read-only queryScalar and ledger assertions; never creates documents.

- [ ] احفظ branch، HEAD، PHP، Laravel، DB version في work/ui-acceptance/run-<timestamp>/environment.txt؛ تأكد أن الفرع جانبي.
- [ ] أنشئ schema مخصصة فقط، اطبع اسمها قبل migrate:fresh، واضبط DB_CONNECTION=mysql وDB_HOST وDB_USERNAME=root وpassword فارغ.
- [ ] نفذ migrate:fresh --seed ثم seeders النظام والـ catalog والعملاء والموردين والتسعير والشراء والمخزون وPOS، ثم DemoFeedStoreSeeder إن كان idempotent. لا تحفظ credentials في Git.
- [ ] شغّل php artisan serve --host=127.0.0.1 --port=8000، تحقق من /login و/ up، ثم npm run build.
- [ ] احفظ php artisan route:list، واصنع manifest من routes وconfig/navigation.php وBlade screens، بما فيها screens غير الظاهرة في القائمة.
- [ ] اضبط projects: ar-desktop 1440x1000، en-desktop، ar-mobile 390x844، pos-keyboard؛ trace retain-on-failure، screenshot/video failure، listeners لـ console/pageerror.
- [ ] أضف smoke login/logout، وشغله: npx playwright test tests/Browser/smoke/login.spec.ts --project=ar-desktop. المتوقع نجاح login/logout بلا console errors.
- [ ] Commit: git add docs/superpowers/plans tests/Browser/support playwright.config.js work/ui-acceptance/README.md ثم git commit -m "test: scaffold post-merge UI acceptance harness".

---

### Task 2: authentication والصلاحيات والنطاق

**Files:**
- Create: tests/Browser/fixtures/roles.ts
- Create: tests/Browser/smoke/authentication.spec.ts
- Create: tests/Browser/smoke/authorization-matrix.spec.ts
- Create: work/ui-acceptance/manifests/role-matrix.md
- Read: database/seeders/LocalAuthSeeder.php، DemoFeedStoreSeeder.php، config/navigation.php، routes/*.php

**Interfaces:** roles تشمل system-administrator وbranch-manager وcashier وpurchasing-officer وwarehouse-manager وpricing-officer وstock-counter وaccountant-reviewer وأي role مفعّل. assertDenied يقبل 403 أو رسالة authorization مع تحقق أن السجل لم يتغير.

- [ ] اكتب لكل role permissions وbranch/store scope والشاشات والأزرار والعمليات.
- [ ] اختبر login صحيح، password خاطئ، user غير موثق، logout، session expiry إن أمكن، بالعربية والإنجليزية.
- [ ] افتح كل top-level navigation، ثم route مباشر لنفس الشاشة، وتحقق من server authorization بعد إعادة التحميل.
- [ ] استخدم super admin scoped، super admin بلا scope، ومستخدم scope مختلف؛ تحقق من الشركة/الفرع/المتجر والرفض خارج النطاق.
- [ ] شغّل npx playwright test tests/Browser/smoke/authentication.spec.ts tests/Browser/smoke/authorization-matrix.spec.ts --project=ar-desktop --project=en-desktop.
- [ ] Commit: git add tests/Browser/fixtures tests/Browser/smoke work/ui-acceptance/manifests/role-matrix.md ثم git commit -m "test: cover browser authentication and authorization matrix".

---

### Task 3: shell والروابط وكل الأزرار والحالات العامة

**Files:**
- Create: tests/Browser/smoke/app-shell.spec.ts
- Create: tests/Browser/smoke/navigation-links.spec.ts
- Create: tests/Browser/support/ui-assertions.ts
- Create: work/ui-acceptance/manifests/button-inventory.csv
- Verify: resources/views/layouts، resources/views/components، config/navigation.php

**Interfaces:** assertShell(page, locale, viewport)، assertButtonInventory(page, screenId). استخدم role/name/label ثم data-testid ثم selector موثق.

- [ ] اختبر dashboard وlogo/brand وcompany/store وlocale switch وsidebar وuser menu وnotifications وbreadcrumbs وtitle.
- [ ] بدّل RTL/LTR من UI ثم reload وتحقق من dir والاتجاه والعناوين.
- [ ] لكل screen سجل كل link/button: navigate، modal، submit، export، print، delete/reverse، toggle؛ اربطه باختبار فعلي.
- [ ] اختبر empty/loading/validation/success/error/disabled/confirmation/unsaved-change/pagination/filter reset وdouble-click.
- [ ] اختبر 1440x1000 و390x844 في RTL/LTR، وسجل overflow أو عنصرًا خارج viewport.
- [ ] شغّل npx playwright test tests/Browser/smoke --project=ar-desktop --project=ar-mobile --project=en-desktop.
- [ ] Commit: git add tests/Browser/smoke tests/Browser/support/ui-assertions.ts work/ui-acceptance/manifests/button-inventory.csv ثم git commit -m "test: cover shared shell and navigation UI".

---

### Task 4: catalog والمنتجات والموردون والعملاء

**Files:**
- Create: tests/Browser/catalog/catalog-products.spec.ts
- Create: tests/Browser/catalog/catalog-reference-data.spec.ts
- Create: tests/Browser/catalog/suppliers.spec.ts
- Create: tests/Browser/customers/customers.spec.ts
- Create: tests/Browser/catalog/import-export.spec.ts
- Verify: resources/views/catalog، resources/views/pages/customers، routes/catalog.php، routes/customers.php

**Interfaces:** product fixture بوحدة kg ووحدة bag بمعامل 25 وسعر موجب وcustomer price؛ supplier credit terms؛ customer credit limit؛ كل helper يقرأ ID الظاهر بعد الحفظ.

- [ ] المنتجات: list/search/filter/sort/pagination/detail/edit/create، barcode/category/brand/unit/conversion/fractional quantity/prices، تعديل وحذف الوحدة المستخدمة، exact 2.500000.
- [ ] validation: duplicate SKU/barcode، zero/negative price، invalid conversion، required fields.
- [ ] categories/brands/options: create/edit/search/disable/delete المرتبط مع confirmation.
- [ ] suppliers: create/edit/history/export/import template/upload صالح وinvalid/rejections/approve، ومنع cross-company.
- [ ] customers/groups/consents/loyalty/wallets: create/edit/duplicate phone/email/export/import/merge والصلاحيات وفصل product wallet عن party wallet.
- [ ] Run: npx playwright test tests/Browser/catalog tests/Browser/customers --project=ar-desktop.
- [ ] Commit: git add tests/Browser/catalog tests/Browser/customers ثم git commit -m "test: cover catalog suppliers and customers through UI".

---

### Task 5: دورة المشتريات والاستلام والتكلفة

**Files:**
- Create: tests/Browser/purchasing/purchase-orders.spec.ts
- Create: tests/Browser/purchasing/purchase-invoices.spec.ts
- Create: tests/Browser/purchasing/purchase-receiving.spec.ts
- Create: tests/Browser/purchasing/purchase-returns.spec.ts
- Create: tests/Browser/purchasing/purchasing-permissions.spec.ts
- Verify: app/Modules/Purchasing/Actions، app/Modules/Purchasing/Services، resources/views/purchasing

**Interfaces:** createBagPurchaseOrder، createPurchaseInvoiceFromOrder، assertPurchaseLedger(invoiceId) للتحقق من total وsupplier balance وinventory quantity/value وallocated charge.

- [ ] PO overview/search/filter/create؛ أدخل 10 bag، conversion 25، discount، submit/approve، وتحقق من entered/base والpreview.
- [ ] invoice from PO بوحدة غير الأساسية؛ أضف freight؛ تحقق من conversion للسعر والخصم وtotal وpayable.
- [ ] استلام سطرين جزئيًا مع السطر الثاني صفر؛ Save draft ثم Approve/Finalize؛ تحقق من stock وinventory_unit_cost وتوزيع freight وعدم التكرار بعد reload.
- [ ] ارفض over-receive، negative/invalid، missing store/product، duplicate submit؛ اعكس غير المدفوع؛ ادفع المورد ثم حاول reverse ويجب الرفض.
- [ ] اختبر proposer لا يعتمد، supplier history، supplier return بكمية كسرية وprint.
- [ ] Run: npx playwright test tests/Browser/purchasing --project=ar-desktop --project=en-desktop.
- [ ] Commit: git add tests/Browser/purchasing ثم git commit -m "test: cover purchasing receiving and landed cost UI".

---

### Task 6: المخزون والتحويلات والجرد والاستيراد

**Files:**
- Create: tests/Browser/inventory/inventory-overview.spec.ts
- Create: tests/Browser/inventory/transfers.spec.ts
- Create: tests/Browser/inventory/stocktake.spec.ts
- Create: tests/Browser/inventory/adjustments.spec.ts
- Create: tests/Browser/inventory/opening-stock.spec.ts
- Create: tests/Browser/inventory/inventory-permissions.spec.ts
- Verify: routes/inventory.php، resources/views/inventory، app/Modules/Inventory/Actions/ReceiveStockTransferAction.php، app/Support/ProductQuantity.php

**Interfaces:** assertStockCard(product, store, expectedQty, expectedValue)، createTransfer(source, destinations, quantity)، assertReceiptIdempotent(transferId).

- [ ] inventory overview/balances/stock card/movements: filters/search/sort/pagination/export/detail، exact decimals وunit labels.
- [ ] transfer واحد: create 2.5، save/submit/approve/dispatch، receiving draft/finalize، partial على مرتين، refresh/double-click، no duplicate/negative transit.
- [ ] multi-destination: وجهتان كل منهما 2.5، draft مستقل لكل وجهة، finalize، allocation and source/in-transit assertions، reject outside allocation.
- [ ] stocktake: create/list/workspace/entry/review/report، manual 1.235000، save/review/submit/approve/post، Excel صالح وinvalid/missing/duplicate/decimal separator.
- [ ] adjustments/opening: addition/deduction fractional، reason required/invalid، approval، no post before approval.
- [ ] Run: npx playwright test tests/Browser/inventory --project=ar-desktop --project=ar-mobile.
- [ ] Commit: git add tests/Browser/inventory ثم git commit -m "test: cover inventory transfers stocktake and adjustments UI".

---

### Task 7: pricing وقوائم الأسعار والاعتماد والباركود

**Files:**
- Create: tests/Browser/pricing/price-lists.spec.ts
- Create: tests/Browser/pricing/price-approvals.spec.ts
- Create: tests/Browser/pricing/labels.spec.ts
- Create: tests/Browser/pricing/pricing-permissions.spec.ts
- Verify: app/Modules/Pricing/Services/EffectivePriceResolver.php، resources/views/pricing، routes/pricing.php

**Interfaces:** createPriceProposal(product, unit, customer/list, price, minimum)، assertEffectivePrice(customer, product, unit, expected, source).

- [ ] lists/create/edit/assign/matrix؛ unit/customer/list prices؛ minimum price؛ expire؛ precedence customer override ثم list ثم default حسب النظام.
- [ ] proposer ينشئ ثم يحاول approve/reject ويُرفض؛ approver آخر ينجح؛ تحقق status/history/audit/effective price.
- [ ] labels search/select/generate/preview/print/download، missing barcode، bulk/pagination.
- [ ] Run: npx playwright test tests/Browser/pricing --project=ar-desktop.
- [ ] Commit: git add tests/Browser/pricing ثم git commit -m "test: cover pricing approval and label UI".

---

### Task 8: POS والمبيعات والدفع الآجل والخصم والمرتجعات

**Files:**
- Create: tests/Browser/pos/pos-sales.spec.ts
- Create: tests/Browser/pos/pos-credit.spec.ts
- Create: tests/Browser/pos/pos-returns.spec.ts
- Create: tests/Browser/pos/pos-shift.spec.ts
- Create: tests/Browser/pos/pos-shortcuts.spec.ts
- Create: tests/Browser/sales/sales-history.spec.ts
- Verify: app/Livewire/Pos، resources/views/livewire/pos، app/Modules/Retail/Actions، routes/retail.php

**Interfaces:** openShiftAsCashier، addProductBySearch، addProductByBarcode، selectUnit، setCustomer، setPaymentMode، completeSale، assertSaleTotals، assertCustomerBalance. كل action من browser UI.

- [ ] POS readiness: قبل shift، open shift بمبلغ opening، branch/store/drawer، cash movement، transfer، close/blind close، mismatch/treasury rejection.
- [ ] cash sale: search/barcode، unit، 2.5، edit/remove، availability بالوحدة المختارة، price/minimum guard، cash/mixed payment/change، receipt/print/history.
- [ ] credit sale: عميل credit، partial payment، remaining account، credit limit، over-limit rejection، ineligible customer، payment mode change ثم submit، refresh.
- [ ] invoice discount + company tax: subtotal/discount/tax/grand total في checkout وreceipt/report/ledger.
- [ ] returns: invoice history، fractional return، refund/AR split، approval/verification/reason، over-return rejection، inventory and balance.
- [ ] suspended/resume/cancel، barcode، Enter/Escape/Tab، touch، double submit، connectivity indicator إن كان ظاهرًا.
- [ ] Run: npx playwright test tests/Browser/pos tests/Browser/sales --project=ar-desktop --project=pos-keyboard --project=ar-mobile.
- [ ] Commit: git add tests/Browser/pos tests/Browser/sales ثم git commit -m "test: cover POS sales credit payments and returns UI".

---

### Task 9: customer receipts وsupplier payments وcash/treasury/expenses

**Files:**
- Create: tests/Browser/finance/customer-receipts.spec.ts
- Create: tests/Browser/finance/supplier-payments.spec.ts
- Create: tests/Browser/finance/expenses-cash.spec.ts
- Create: tests/Browser/finance/shift-treasury.spec.ts
- Create: tests/Browser/finance/ledger-reconciliation.spec.ts
- Verify: routes/feed-store.php، resources/views/feed-store/operations.blade.php، resources/views/pages/pos/cash-movement.blade.php

**Interfaces:** assertCashAccountBalance، assertPartyBalance، readOnlyLedgerSnapshot؛ لا POST مباشر.

- [ ] customer receipt: partial/full/unapplied، method/account، submit/approve، AR/cash، duplicate rejection.
- [ ] supplier payment: partial/full، AP/cash، cash بدون account، non-cash مع account، scope/currency mismatch.
- [ ] expenses/cash accounts/batches: create/edit/approval، required/invalid/negative/zero، export.
- [ ] shift cash in/out/transfer/close/blind close/mismatch/retry؛ immutable approved docs وعدم duplicate movement.
- [ ] بعد كل flow طابق UI balances وledger وcash snapshots.
- [ ] Run: npx playwright test tests/Browser/finance --project=ar-desktop.
- [ ] Commit: git add tests/Browser/finance ثم git commit -m "test: cover customer supplier and treasury UI flows".

---

### Task 10: التقارير والتصدير والطباعة

**Files:**
- Create: tests/Browser/reports/sales-reports.spec.ts
- Create: tests/Browser/reports/inventory-reports.spec.ts
- Create: tests/Browser/reports/exports-print.spec.ts
- Create: tests/Browser/reports/report-permissions.spec.ts
- Verify: routes/reporting.php، resources/views/pages/reports، resources/views/exports/report-pdf.blade.php

**Interfaces:** assertReportFiltersPersist، downloadAndValidate، assertPrintPreview.

- [ ] افتح sales summary، sales by product، stock movement، valuation، reports center، R1، R2؛ اختبر date/store/product/customer filters وreset/sort/pagination/drilldown.
- [ ] نفذ كل export XLSX/CSV/PDF الظاهر؛ تحقق download وfilename وheaders وnon-empty وscope.
- [ ] اطبع purchase/sales/return/quotation/report؛ تحقق branding 3allaf، RTL/LTR، totals، page breaks، translations.
- [ ] Run: npx playwright test tests/Browser/reports --project=ar-desktop --project=en-desktop.
- [ ] Commit: git add tests/Browser/reports ثم git commit -m "test: cover reports exports and print UI".

---

### Task 11: الإدارة والإعدادات والموافقات والأمان والخصائص الاختيارية

**Files:**
- Create: tests/Browser/admin/platform-settings.spec.ts
- Create: tests/Browser/admin/users-roles.spec.ts
- Create: tests/Browser/admin/approvals-audit.spec.ts
- Create: tests/Browser/admin/settings-security.spec.ts
- Create: tests/Browser/optional/quotations-gift-cards-party.spec.ts
- Verify: resources/views/platform، resources/views/pages/settings، routes/platform.php، routes/settings.php، routes/quotations.php، routes/returns-gifts.php

**Interfaces:** assertAuditEntry(action, actor, entity)، assertApprovalInboxItem(type, status). optional modules تكون N/A فقط مع دليل config/navigation.

- [ ] initial setup/company/tax/stores/branches/drawers/printers: edit/save/reload، انعكاس setting في POS/print، validation/protected setting.
- [ ] users/roles: create/edit/status/scope، disabled login، role permission change وأثره بعد fresh session.
- [ ] approval inbox/audit/notifications: approve/reject من UI، actor/time/before-after، no self approval، search/export.
- [ ] profile/password/2FA/passkey UI إن مفعّل، logout-all، duplicate submit.
- [ ] quotations/gift cards/party فقط إن enabled؛ وإلا سجّل N/A.
- [ ] Run: npx playwright test tests/Browser/admin tests/Browser/optional --project=ar-desktop.
- [ ] Commit: git add tests/Browser/admin tests/Browser/optional ثم git commit -m "test: cover administration approvals and settings UI".

---

### Task 12: الرحلات العشرة الخاصة بالدمج

**Files:**
- Create: tests/Browser/journeys/merge-regressions.spec.ts
- Create: tests/Browser/journeys/merge-decimal-regressions.spec.ts
- Create: work/ui-acceptance/journeys/README.md

**Interfaces:** كل journey يسجل ID، seed snapshot، actors، document IDs من UI، totals الظاهرة، وDB read-only reconciliation؛ لا يتجاوز UI.

- [ ] نفذ: credit sale مع partial payment وحد ائتمان.
- [ ] نفذ: bag purchase مع conversion وdiscount.
- [ ] نفذ: invoice بوحدة مختلفة + freight + partial receive + zero line.
- [ ] نفذ: reverse paid purchase ويجب الرفض.
- [ ] نفذ: multi-destination transfer بكمية 2.5 لكل وجهة.
- [ ] نفذ: partial transfer مرتين بدون duplicate أو negative transit.
- [ ] نفذ: manual/import fractional stocktake.
- [ ] نفذ: fractional credit return مع refund/AR split.
- [ ] نفذ: invoice discount + company tax.
- [ ] نفذ: self price approval rejection ثم approval من مستخدم آخر.
- [ ] Run: npx playwright test tests/Browser/journeys --project=ar-desktop --workers=1. يجب أن تمر الرحلات العشر في seed run نظيف.
- [ ] Commit: git add tests/Browser/journeys work/ui-acceptance/journeys ثم git commit -m "test: execute post-merge business journeys through UI".

---

### Task 13: إغلاق inventory الخاص بكل شاشة وزر

**Files:**
- Create: tests/Browser/generated/screen-smoke.spec.ts
- Create: tests/Browser/generated/action-inventory.spec.ts
- Modify: work/ui-acceptance/manifests/button-inventory.csv
- Create: work/ui-acceptance/manifests/gaps.csv

**Interfaces:** screen-smoke يقرأ screen manifest؛ action-inventory يشغل action واحدًا لكل عنصر بfixture disposable؛ لا تنفذ destructive action على shared fixture.

- [ ] قارن route-list وnavigation وBlade links/forms؛ لكل route اكتب screen ID وrole وparams وseed requirement وdestructive flag وtitle.
- [ ] زر كل شاشة يحصل على PASS/FAIL/N/A؛ PASS يتطلب click والنتيجة، لا مجرد وجود element.
- [ ] زر الحذف/العكس/الاعتماد يستخدم disposable record ويختبر confirmation وcancel ثم تنفيذًا واحدًا.
- [ ] كل route 200/render/title/no exception؛ سجّل 404/500/Livewire/translation/console errors.
- [ ] أي عنصر بلا accessible name finding UI مستقل.
- [ ] Run: npx playwright test tests/Browser/generated --project=ar-desktop --workers=1.
- [ ] Commit: git add tests/Browser/generated work/ui-acceptance/manifests ثم git commit -m "test: close screen and button inventory gaps".

---

### Task 14: مقارنة baseline والتصنيف والتقرير والقرار

**Files:**
- Create: work/ui-acceptance/scripts/collect-results.ps1
- Create: work/ui-acceptance/scripts/classify-failures.ps1
- Modify: REVIEW_REPORT.md
- Create: work/ui-acceptance/final/COMMAND_RESULTS.md
- Create: work/ui-acceptance/final/FAILURE_CLASSIFICATION.csv
- Create: work/ui-acceptance/final/UI_COVERAGE.csv
- Create: work/ui-acceptance/final/DECISIONS_REQUIRED.md

**Interfaces:** المقارنة بين merged وfeed baseline 41adf62 وRajeh baseline 4e23d1d7 بنفس MariaDB major ونفس seed قدر الإمكان. التصنيفات: MERGE_BUG، PRE_EXISTING_FEED، PRE_EXISTING_RAJeh، INTENTIONAL_RULE_CONFLICT، FIXTURE_OR_ENVIRONMENT، UI_ONLY، UNCLASSIFIED. heuristic وحده غير كافٍ.

- [ ] أعد migrate:fresh، seed، DemoFeedStoreSeeder، rollback، route:list، view:cache، php -l للـ compiled views، phpstan مع 1G، Pint، Unit/Feature كمساعد، npm run build، وfull browser run. سجل الأرقام الفعلية.
- [ ] شغّل نفس smoke على worktrees 41adf62 و4e23d1d7، وافصل artifacts لكل branch.
- [ ] لكل failure: اقرأ trace/screenshot/console/network/Laravel log/DB snapshot، أعد الخطوة مرة، افحص diff وparent result، ثم اكتب root cause/severity/route/button/user impact/fix أو decision.
- [ ] حدّث REVIEW_REPORT.md بفرع/commit/environment/scope، UI counts، command counts، findings file/line/severity/type/evidence/fix، baseline comparison، tests الجديدة، decisions، blockers.
- [ ] GO فقط مع جميع الشاشات والأزرار والرحلات PASS/N/A المبرر، لا P0/P1، ledger reconciliation، RTL/LTR/desktop/mobile. NO-GO لأي P0/P1 أو missing reconciliation أو action غير قابل للتنفيذ. CONDITIONAL يحتاج موافقة المالك على pre-existing/intentional conflicts.
- [ ] شغّل git diff --check، git status --short، وnpx playwright test tests/Browser --project=ar-desktop --workers=1؛ تأكد أن التقرير لا يذكر نتيجة لم تُشغّل.
- [ ] Commit: git add REVIEW_REPORT.md work/ui-acceptance/final work/ui-acceptance/manifests ثم git commit -m "docs: record full post-merge UI acceptance results".

---

## Self-Review

- تغطي الخطة مراجعة diff وMERGE_REPORT، MariaDB commands، كل السيناريوهات العشرة، كل modules/routes/screens، كل الأزرار، الصلاحيات، UI states، RTL/LTR/mobile، reports/print، baseline comparison، والتقرير النهائي.
- كل task يحتوي files وinterfaces وخطوات قابلة للتحقق وcommand متوقع؛ لا يعتمد على اختبار كود بديل عن UI.
- Review Focus مربوط بمهام محددة.
- حدود واضحة: لا production، لا push/deploy، لا أجهزة فعلية أو barcode hardware بدون تفويض وبيئة منفصلة.
