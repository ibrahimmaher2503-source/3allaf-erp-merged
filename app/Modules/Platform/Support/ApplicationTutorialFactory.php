<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support;

use LogicException;

/**
 * Bilingual contextual guides for implemented screens that do not need a
 * domain-specific tutorial factory. Every entry maps real named GET routes.
 */
final class ApplicationTutorialFactory
{
    /** @return array<string, mixed> */
    public static function make(string $screenId): array
    {
        $definitions = [
            'UI-ADM-001' => self::entry(['admin.overview'], 'مركز الإدارة', 'Administration Center', 'راجع وجهات الإدارة والإعدادات المتاحة وفق صلاحياتك.', 'Review the administration and settings destinations available to your account.', 'access-administration-center'),
            'UI-ADM-011' => self::entry(['admin.roles'], 'الأدوار', 'Roles', 'راجع الأدوار القياسية والتعيينات قبل فتح مصفوفة الصلاحيات.', 'Review canonical roles and assignments before opening the permission matrix.', 'users_roles_permissions.view'),
            'UI-ADM-012' => self::entry(['admin.role-permissions'], 'صلاحيات الدور', 'Role Permissions', 'راجع وحدات الصلاحيات والحساسية قبل حفظ أي تغيير على الدور.', 'Review permission modules and sensitivity before saving any role change.', 'users_roles_permissions.view'),
            'UI-ADM-014' => self::entry(['admin.translations'], 'محرر الترجمة', 'Translation Editor', 'راجع النصوص العربية والإنجليزية المعروفة وعدّلها دون تغيير مفاتيح النظام.', 'Review known Arabic and English wording without changing system keys.', 'company_settings.edit'),
            'UI-ADM-015' => self::entry(['admin.settings.cities'], 'المدن والمحافظات', 'Cities and Governorates', 'راجع المدن المستخدمة في بيانات العملاء والإعدادات المحلية.', 'Review the cities used by customer data and local settings.', 'company_settings.view'),
            'UI-SYS-006' => self::entry(['admin.approvals'], 'صندوق الاعتمادات', 'Approval Inbox', 'راجع المستندات المنتظرة واتخذ فقط الإجراء الذي تسمح به حالتها وصلاحياتك.', 'Review pending documents and use only actions allowed by state and permission.', 'view-approval-inbox'),
            'UI-SYS-012' => self::entry(['system.backups'], 'النسخ الاحتياطي والاستعادة', 'Backup and Restore', 'راجع جاهزية النسخ والاستعادة والأدلة المسجلة دون افتراض نجاح غير موثق.', 'Review backup and restore readiness without implying unverified success.', 'audit_logs.view'),
            'UI-SYS-013' => self::entry(['notifications.index'], 'الإشعارات', 'Notifications', 'راجع الإشعارات التشغيلية المتاحة لحسابك وانتقل إلى مصدرها عند توفره.', 'Review operational notifications available to your account and follow their source when present.'),
            'UI-SYS-014' => self::entry(['profile.edit', 'appearance.edit', 'security.edit'], 'إعدادات الحساب', 'Account Settings', 'حدّث ملفك ومظهرك وإعدادات الأمان الشخصية دون تغيير الأدوار أو النطاقات.', 'Update your profile, appearance, and personal security without changing roles or scopes.'),
            'UI-SYS-015' => self::entry(['feed-store.operations'], 'عمليات متجر الأعلاف', 'Feed Store Operations', 'استخدم نماذج التشغيل ضمن الحساب والموقع المصرح بهما وراجع الملخص قبل الحفظ.', 'Use operational forms within the authorized account and location, then review the summary before saving.'),
            'UI-RPT-003' => self::entry(['alerts.index'], 'التنبيهات التشغيلية', 'Operational Alerts', 'راجع التنبيهات حسب النطاق والفترة ثم انتقل إلى المصدر قبل اتخاذ إجراء.', 'Review alerts by scope and period, then inspect the source before acting.', 'dashboard_reports.view'),
            'UI-RPT-004' => self::entry(['exports.index'], 'مركز التصدير', 'Export Center', 'تابع طلبات التصدير الخاصة بك وحمّل النتائج المصرح بها فقط.', 'Track your export requests and download only authorized results.'),
            'UI-RPT-005' => self::entry(['reports.index', 'reports.sales', 'reports.customers', 'reports.cash', 'reports.purchasing', 'reports.inventory', 'reports.parties', 'reports.assets'], 'مركز التقارير', 'Reports Center', 'اختر التقرير ثم ثبّت الفترة والنطاق قبل تفسير الأرقام أو تصديرها.', 'Choose a report and confirm period and scope before interpreting or exporting figures.', 'dashboard_reports.view'),
            'UI-RPT-006' => self::entry(['reports.sales-summary', 'reports.sales-by-product'], 'تقارير المبيعات', 'Sales Reports', 'راجع الفترة والموقع ومصدر البيانات قبل مقارنة الملخص وتفاصيل المنتجات.', 'Confirm period, location, and source before comparing summaries and product detail.', 'dashboard_reports.view'),
            'UI-RPT-007' => self::entry(['reports.stock-movement'], 'بطاقة حركة المخزون', 'Stock Movement Card', 'تتبّع الرصيد الافتتاحي والحركات المرحلة والرصيد الجاري بترتيب زمني ثابت.', 'Trace opening balance, posted movements, and running balance in deterministic order.', 'inventory_stock_card.view'),
            'UI-RPT-008' => self::entry(['reports.inventory-valuation'], 'تقييم المخزون بتاريخ', 'Inventory Valuation As Of Date', 'راجع الكمية والتكلفة التاريخية من الحركات المرحلة حتى نهاية اليوم المحدد بتوقيت القاهرة.', 'Review historical quantity and cost from posted movements through Cairo end-of-day.', 'inventory_stock_card.cost_view'),
            'UI-CAT-005' => self::entry(['catalog.reference-import'], 'استيراد التصنيفات والعلامات', 'Category and Brand Import', 'جهّز الملف وراجعه قبل إنشاء أي سجلات مرجعية.', 'Stage and review the file before creating reference records.', 'products_categories_brands.create'),
            'UI-CAT-009' => self::entry(['catalog.product-options'], 'خيارات المنتجات', 'Product Options', 'أدر مجموعات الخيارات وقيمها المستخدمة في تكوين التنويعات.', 'Manage option groups and values used to form product variations.', 'products_categories_brands.view'),
            'UI-CAT-010' => self::entry(['catalog.lookups'], 'قوائم دليل المنتجات', 'Catalog Lookups', 'راجع القوائم المرجعية المتاحة وحالة جاهزيتها قبل الاعتماد عليها.', 'Review available catalog lookups and their readiness before relying on them.', 'products_categories_brands.view'),
            'UI-CAT-011' => self::entry(['catalog.suppliers.import'], 'استيراد الموردين', 'Supplier Import', 'ارفع ملف الموردين وراجع الصفوف المقبولة والمرفوضة قبل الاعتماد.', 'Upload the supplier file and review accepted and rejected rows before approval.', 'suppliers.create'),
            'UI-CUS-003' => self::entry(['customers.loyalty'], 'ولاء العميل', 'Customer Loyalty', 'راجع رصيد النقاط والحركات والاستحقاق قبل الاستبدال أو التسوية.', 'Review points balance, ledger, and eligibility before redemption or adjustment.', 'loyalty.view'),
            'UI-CUS-006' => self::entry(['customers.create', 'customers.show', 'customers.groups.index', 'customers.import'], 'بيانات العملاء', 'Customer Records', 'أنشئ أو راجع ملف العميل والمجموعة والاستيراد ضمن الصلاحيات الحالية.', 'Create or review customer profiles, groups, and imports within current permissions.', 'customers.view'),
            'UI-OFF-001' => self::entry(['pos.offline.queue'], 'البيع دون اتصال', 'Offline POS', 'راجع أهلية الجهاز والطابور والحدود قبل أي عملية مؤقتة دون اتصال.', 'Review device eligibility, queue state, and limits before provisional offline work.', 'offline_queue_conflicts.view'),
            'UI-OFF-002' => self::entry(['offline.conflicts.index', 'offline.conflicts.show'], 'تعارضات المزامنة', 'Sync Conflicts', 'راجع المصدر والنسخة والقرار المطلوب قبل حل تعارض المزامنة.', 'Review source, version, and required disposition before resolving a sync conflict.', 'offline_queue_conflicts.approve'),
            'UI-POS-004' => self::entry(['pos.shift'], 'إدارة الوردية', 'Shift Management', 'راجع الخزينة ورصيد الافتتاح والحركات ثم نفّذ الإغلاق الأعمى حسب الحالة.', 'Review drawer, opening float, and cash movements, then use blind close according to state.', 'shifts_cash_movements.view'),
            'UI-POS-005' => self::entry(['pos.shift-variance'], 'مراجعة فرق الوردية', 'Shift Variance Review', 'قارن المتوقع بالفعلي وراجع السبب قبل الاعتماد أو طلب إعادة العد.', 'Compare expected and actual values and review the reason before approval or recount.', 'shifts_cash_movements.approve'),
            'UI-POS-013' => self::entry(['payments.index', 'payments.evidence'], 'المدفوعات وأدلتها', 'Payments and Evidence', 'راجع طريقة الدفع والمرجع والدليل مع الحفاظ على نطاق العرض المصرح به.', 'Review payment method, reference, and evidence within the authorized visibility scope.', 'pos_sales.payment_view'),
            'UI-PRC-001' => self::entry(['pricing.index'], 'التسعير', 'Pricing', 'راجع الأسعار الحالية والمقترحة وفترات السريان قبل إرسال أي تغيير.', 'Review current and proposed prices and effective periods before submitting changes.', 'pricing_labels.view'),
            'UI-PRC-002' => self::entry(['pricing.approvals'], 'اعتمادات الأسعار', 'Price Approvals', 'قارن النسخ والفروق قبل اعتماد السعر أو رفضه.', 'Compare versions and differences before approving or rejecting a price.', 'pricing_labels.approve'),
            'UI-PRC-003' => self::entry(['pricing.labels'], 'ملصقات الأسعار', 'Price Labels', 'اختر المنتجات والقالب والطابعة المصرح بها قبل المعاينة أو الطباعة.', 'Select products, template, and authorized printer before preview or print.'),
            'UI-PRC-004' => self::entry(['pricing.lists'], 'قوائم الأسعار', 'Price Lists', 'راجع القائمة ونسبة الحساب والتعيينات قبل تفعيل أي تغيير.', 'Review list calculation and assignments before activating changes.', 'pricing_lists.view'),
            'UI-PUR-002' => self::entry(['purchasing.invoices', 'purchasing.invoices.import', 'purchasing.invoices.readiness', 'purchasing.invoices.settings', 'purchasing.history.suppliers', 'purchasing.history.costs'], 'فواتير الشراء والاستلام', 'Purchase Invoices and Receiving', 'راجع المورد والمصدر والاستلام والتوزيع والتكلفة قبل الاعتماد والترحيل.', 'Review supplier, source, receipt, distribution, and cost before approval and posting.', 'purchase_invoices_supplier_returns.view'),
            'UI-PUR-003' => self::entry(['purchasing.returns', 'purchasing.returns.settings', 'purchasing.returns.show'], 'مرتجعات الموردين', 'Supplier Returns', 'اربط المرتجع بالفاتورة والمخزون المؤهل والسبب قبل الاعتماد.', 'Link the return to its invoice, eligible stock, and reason before approval.', 'purchase_returns.view'),
            'UI-INV-012' => self::entry(['inventory.opening.index', 'inventory.opening.show'], 'المخزون الافتتاحي', 'Opening Inventory', 'راجع المسودة والتوزيع والإجماليات قبل الاعتماد أو العكس المرجعي.', 'Review draft, allocation, and totals before approval or referenced reversal.', 'inventory_stock_card.view'),
            'UI-PTY-016' => self::entry(['parties.bookings.index', 'parties.bookings.create', 'parties.bookings.show'], 'حجوزات الحفلات', 'Party Bookings', 'راجع العميل والموعد والموقع والحالة قبل التأكيد أو التعديل.', 'Review customer, date, location, and state before confirmation or change.', 'party_bookings_invoices.view'),
            'UI-PTY-017' => self::entry(['parties.calendar'], 'تقويم الحفلات', 'Party Calendar', 'راجع الفترة والموقع وتعارضات الموارد ضمن النطاق المصرح به.', 'Review period, location, and resource conflicts within the authorized scope.', 'rental_assets.view'),
            'UI-PTY-018' => self::entry(['parties.invoices.index', 'parties.invoices.show', 'parties.invoices.payments', 'parties.invoices.settle'], 'فواتير الحفلات', 'Party Invoices', 'راجع البنود والدفعات والحالة قبل التسوية أو الإغلاق النهائي.', 'Review lines, payments, and state before settlement or final close.', 'party_bookings_invoices.view'),
            'UI-PTY-019' => self::entry(['parties.orders.index', 'parties.orders.show'], 'أوامر تشغيل الحفلات', 'Party Operating Orders', 'راجع الحجز والموارد والمستهلكات والحالة قبل الإصدار أو الإكمال.', 'Review booking, resources, consumables, and state before release or completion.', 'party_operating_orders_consumables.view'),
            'UI-PTY-020' => self::entry(['party.assets.index', 'party.assets.show'], 'أصول التأجير', 'Rental Assets', 'راجع حالة الأصل وموقعه وحجزه وسجل أحداثه قبل أي انتقال.', 'Review asset state, location, reservation, and event history before a transition.', 'rental_assets.view'),
            'UI-QTN-002' => self::entry(['quotations.index'], 'عروض الأسعار', 'Quotations', 'راجع العميل والبنود والشروط والحالة قبل الطباعة أو التحويل.', 'Review customer, lines, terms, and state before print or conversion.', 'quotations.view'),
            'UI-RET-006' => self::entry(['returns.index', 'returns.show'], 'مرتجعات المبيعات', 'Sales Returns', 'تحقق من مستند البيع والبنود المؤهلة والفحص والتسوية قبل الاعتماد.', 'Verify the sale, eligible lines, inspection, and settlement before approval.'),
            'UI-POS-014' => self::entry(['gift.receipts.index', 'gift.cards.index', 'gift.cards.show'], 'إيصالات وبطاقات الهدايا', 'Gift Receipts and Cards', 'راجع المرجع والحالة والخصوصية والرصيد قبل الإصدار أو الاستخدام.', 'Review reference, state, privacy, and balance before issue or use.'),
            'UI-POS-012' => self::entry(['pos.legacy'], 'مسار الكاشير المرجعي', 'Cashier Workflow Reference', 'راجع تسلسل الوردية والبحث والسلة والدفع في المسار المرجعي القديم فقط.', 'Review shift, search, cart, and payment sequence only in the legacy reference path.', 'pos_sales.view'),
        ];

        if (! isset($definitions[$screenId])) {
            throw new LogicException("Unknown application tutorial [{$screenId}].");
        }

        return ['screen_id' => $screenId] + $definitions[$screenId];
    }

    /** @return array<string, mixed> */
    private static function entry(array $routeNames, string $titleAr, string $titleEn, string $purposeAr, string $purposeEn, ?string $permission = null): array
    {
        $permissions = $permission ? [$permission] : [];
        $actions = $permission ? [[
            'key' => 'screen.review',
            'label' => ['ar' => 'مراجعة الشاشة والبيانات المتاحة', 'en' => 'Review the screen and available data'],
            'required_permission' => $permission,
        ]] : [];
        $step = [
            'key' => 'screen-overview',
            'selector' => '[data-guide="page-header"], [data-guide="page-guide-launcher"]',
            'title' => ['ar' => 'ابدأ من هدف الشاشة', 'en' => 'Start with the screen purpose'],
            'body' => ['ar' => $purposeAr, 'en' => $purposeEn],
        ];

        return [
            'route_names' => $routeNames,
            'title' => ['ar' => $titleAr, 'en' => $titleEn],
            'purpose' => ['ar' => $purposeAr, 'en' => $purposeEn],
            'when_to_use' => ['ar' => 'استخدم هذا الدليل قبل تنفيذ الإجراء الأساسي أو عند مراجعة نتيجة سابقة.', 'en' => 'Use this guide before the primary action or while reviewing an earlier result.'],
            'permissions' => $permissions,
            'approved_actions' => $actions,
            'stories' => ['US-046'],
            'flows' => [],
            'acceptance_criteria' => ['AC-UI-08', 'AC-UI-09', 'AC-UI-11', 'AC-UI-12', 'AC-UI-14'],
            'sections' => [
                'steps' => [$step],
                'fields' => [[
                    'key' => 'scope-and-state',
                    'title' => ['ar' => 'النطاق والحالة', 'en' => 'Scope and state'],
                    'body' => ['ar' => 'تظهر البيانات والإجراءات حسب صلاحياتك ونطاقك وحالة السجل؛ الدليل لا يمنح صلاحية إضافية.', 'en' => 'Data and actions depend on permission, scope, and record state; the guide grants no additional access.'],
                ]],
                'notes' => ['ar' => 'راجع الرسائل والحالة الحالية قبل المتابعة.', 'en' => 'Review messages and current state before continuing.'],
                'warnings' => ['ar' => 'لا تتجاوز زرًا مخفيًا أو إجراءً مرفوضًا؛ ارجع للمسؤول عند غياب الصلاحية.', 'en' => 'Do not bypass hidden or denied actions; contact an administrator when access is missing.'],
                'errors' => ['ar' => 'صحح التحقق الظاهر، ثم أعد المحاولة مرة واحدة بعد التأكد من بقاء الحالة صالحة.', 'en' => 'Correct visible validation, then retry once after confirming the state is still valid.'],
                'next_step' => ['ar' => 'انتقل إلى المصدر أو السجل المرتبط الظاهر في الشاشة.', 'en' => 'Continue to the related source or record shown on the screen.'],
                'faq' => ['ar' => 'ظهور الدليل لا يعني السماح بكل الإجراءات؛ الخادم يطبق الصلاحيات دائمًا.', 'en' => 'Seeing this guide does not authorize every action; server-side permissions always apply.'],
            ],
            'tour_steps' => [$step],
            'version' => '1.0.0',
            'updated_at' => '2026-09-20',
        ];
    }
}
