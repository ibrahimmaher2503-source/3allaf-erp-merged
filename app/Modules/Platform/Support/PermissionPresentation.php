<?php

namespace App\Modules\Platform\Support;

use App\Modules\Platform\Models\Permission;

final class PermissionPresentation
{
    /** @var array<string, string> */
    private const LEGACY_ACTION_ALIASES = [
        'edit' => 'update',
        'logical_delete' => 'delete',
    ];

    /** @var array<string, list<string>> */
    private const CANONICAL_MODULE_ACTIONS = [
        'audit_logs' => ['view', 'export', 'print'],
    ];

    /** @var array<string, array{ar: array{name: string, object: string, description: string}, en: array{name: string, object: string, description: string}}> | null */
    private static ?array $sectionDefinitions = null;

    /** @var array<string, array{ar: array{label: string, description: string}, en: array{label: string, description: string}}> | null */
    private static ?array $actionDefinitions = null;

    /** @return array<string, array{ar: array{name: string, object: string, description: string}, en: array{name: string, object: string, description: string}}> */
    private static function sections(): array
    {
        return self::$sectionDefinitions ??= [
        'audit_logs' => self::section('سجل التدقيق', 'سجلات التدقيق', 'مراجعة أثر التغييرات والإجراءات الحساسة وتصديره.', 'Audit logs', 'audit logs', 'Review and export the trace of changes and sensitive actions.'),
        'authorization' => self::section('إدارة الصلاحيات', 'إعدادات الصلاحيات', 'مراجعة خط أساس الأدوار والصلاحيات وإدارته.', 'Permission administration', 'permission settings', 'Review and manage the roles and permissions baseline.'),
        'branches_stores' => self::section('الفروع والمخازن ونقاط البيع', 'الفروع والمخازن ونقاط البيع', 'إدارة مواقع العمل ونطاقاتها التشغيلية.', 'Branches, warehouses, and sales outlets', 'branches, warehouses, and sales outlets', 'Manage operating locations and their scopes.'),
        'company_settings' => self::section('إعدادات الشركة', 'إعدادات الشركة', 'إدارة بيانات الشركة وسياساتها العامة.', 'Company settings', 'company settings', 'Manage company details and global policies.'),
        'customers_children' => self::section('العملاء وبيانات الأطفال', 'سجلات العملاء وبيانات الأطفال', 'إدارة ملفات العملاء والبيانات الحساسة المرتبطة بها.', 'Customers and child profiles', 'customer and child records', 'Manage customer profiles and their related sensitive data.'),
        'dashboard_reports' => self::section('لوحة التحكم والتقارير', 'لوحات التحكم والتقارير', 'عرض التقارير التشغيلية وتصديرها ضمن النطاق المصرح.', 'Dashboards and reports', 'dashboards and reports', 'View and export operational reports within the authorized scope.'),
        'drawers_payments_tax_numbering_printers' => self::section('الخزائن والمدفوعات والضرائب والترقيم والطباعة', 'إعدادات الخزائن والمدفوعات والضرائب والترقيم والطباعة', 'إدارة إعدادات التحصيل والضرائب وتسلسل المستندات والطابعات.', 'Cash drawers, payments, tax, numbering, and printing', 'cash-drawer, payment, tax, numbering, and printing settings', 'Manage collection, tax, document numbering, and printer settings.'),
        'gift_cards' => self::section('بطاقات الهدايا', 'بطاقات الهدايا', 'إصدار بطاقات الهدايا واستخدامها وضبط حالتها.', 'Gift cards', 'gift cards', 'Issue, redeem, and control the status of gift cards.'),
        'gift_receipts' => self::section('إيصالات الهدايا', 'إيصالات الهدايا', 'إصدار إيصالات الهدايا وطباعتها والتحقق منها.', 'Gift receipts', 'gift receipts', 'Issue, print, and validate gift receipts.'),
        'inventory_stock_card' => self::section('المخزون وبطاقة الصنف', 'مستندات المخزون وبطاقات الأصناف', 'مراجعة المخزون وحركاته وتكاليفه ضمن المواقع المصرح بها.', 'Inventory and stock cards', 'inventory documents and stock cards', 'Review stock, movements, and costs within authorized locations.'),
        'loyalty' => self::section('الولاء', 'حركات ونقاط الولاء', 'إدارة كسب نقاط الولاء واستبدالها وتسوياتها.', 'Loyalty', 'loyalty points and transactions', 'Manage loyalty earning, redemption, and adjustments.'),
        'offline_queue_conflicts' => self::section('العمل دون اتصال والتعارضات', 'عمليات العمل دون اتصال والتعارضات', 'مراجعة العمليات المؤجلة وحالات التعارض ومعالجتها.', 'Offline queue and conflicts', 'offline operations and conflicts', 'Review and resolve deferred operations and conflicts.'),
        'party_bookings_invoices' => self::section('حجوزات وفواتير الحفلات', 'حجوزات وفواتير الحفلات', 'إدارة حجوزات الحفلات وفواتيرها وحالاتها.', 'Party bookings and invoices', 'party bookings and invoices', 'Manage party bookings, invoices, and their states.'),
        'party_operating_orders_consumables' => self::section('أوامر تشغيل الحفلات والمستهلكات', 'أوامر تشغيل الحفلات وحركات المستهلكات', 'إدارة تنفيذ الحفلات وصرف مستهلكاتها.', 'Party operating orders and consumables', 'party operating orders and consumable movements', 'Manage party execution and consumable issues.'),
        'party_wallet' => self::section('محفظة الحفلات', 'حركات محفظة الحفلات', 'مراجعة رصيد نشاط الحفلات وتسوياته المنفصلة.', 'Party wallet', 'party-wallet transactions', 'Review and settle the separate party-activity balance.'),
        'platform' => self::section('حالة النظام والواجهة', 'صفحات حالة النظام والواجهة', 'مراجعة الحالة التشغيلية وأنماط الواجهة.', 'System status and interface', 'system-status and interface pages', 'Review operational status and interface patterns.'),
        'pos_sales' => self::section('نقطة البيع والمبيعات', 'مبيعات نقطة البيع', 'تنفيذ المبيعات والمدفوعات والاستثناءات المصرح بها.', 'Point of sale and sales', 'point-of-sale transactions', 'Process sales, payments, and authorized exceptions.'),
        'sales_invoices' => self::section('فواتير المبيعات', 'مسودات فواتير المبيعات', 'إنشاء مسودات فواتير مبيعات مستقلة عن نقطة البيع دون ترحيل المخزون.', 'Sales invoices', 'sales invoice drafts', 'Create ordinary sales invoice drafts independently of POS without posting inventory.'),
        'pricing_labels' => self::section('التسعير والملصقات', 'مقترحات الأسعار وملصقاتها', 'إدارة مقترحات الأسعار واعتمادها وطباعة الملصقات.', 'Pricing and labels', 'price proposals and labels', 'Manage price proposals, approvals, and label printing.'),
        'pricing_lists' => self::section('قوائم الأسعار', 'قوائم الأسعار', 'إدارة قوائم الأسعار وإصداراتها وتعيينها للمواقع.', 'Price lists', 'price lists', 'Manage price lists, their versions, and location assignments.'),
        'product_wallet' => self::section('محفظة المنتجات', 'حركات محفظة المنتجات', 'مراجعة رصيد نشاط التجزئة وتسوياته المنفصلة.', 'Product wallet', 'product-wallet transactions', 'Review and settle the separate retail-activity balance.'),
        'products_categories_brands' => self::section('المنتجات والتصنيفات والعلامات التجارية', 'بطاقات المنتجات والتصنيفات والعلامات التجارية', 'إدارة بيانات المنتجات وتصنيفها وهويتها.', 'Products, categories, and brands', 'product, category, and brand records', 'Manage product data, classification, and identity.'),
        'purchase_invoices' => self::section('صلاحيات فواتير الشراء', 'فواتير الشراء', 'إدارة مسودات فواتير الشراء وتكاليفها واعتمادها.', 'Purchase invoice permissions', 'purchase invoices', 'Manage purchase invoice drafts, costs, and approvals.'),
        'purchase_invoices_supplier_returns' => self::section('فواتير الشراء ومرتجعات الموردين', 'فواتير الشراء ومرتجعات الموردين', 'إدارة الاستلام المالي والمخزني ومرتجعات الموردين.', 'Purchase invoices and supplier returns', 'purchase invoices and supplier returns', 'Manage financial and stock receiving and supplier returns.'),
        'purchase_orders' => self::section('أوامر الشراء', 'أوامر الشراء', 'إدارة طلبات الشراء قبل الاستلام والفوترة.', 'Purchase orders', 'purchase orders', 'Manage purchase requests before receiving and invoicing.'),
        'purchase_returns' => self::section('مرتجعات الشراء', 'مرتجعات الشراء', 'إنشاء مرتجعات الشراء واعتمادها ضمن الحدود المصرح بها.', 'Purchase returns', 'purchase returns', 'Create and approve purchase returns within authorized limits.'),
        'quotations' => self::section('عروض الأسعار', 'عروض الأسعار', 'إعداد عروض أسعار التجزئة أو الحفلات وإصدارها ومشاركتها.', 'Quotations', 'quotations', 'Prepare, issue, and share retail or party quotations.'),
        'rental_assets' => self::section('أصول التأجير', 'أصول التأجير وحجوزاتها', 'إدارة حجز الأصول وتسليمها وفحصها وإرجاعها.', 'Rental assets', 'rental assets and reservations', 'Manage asset reservations, checkout, inspection, and return.'),
        'returns' => self::section('مرتجعات المبيعات', 'مرتجعات المبيعات', 'إنشاء مرتجعات المبيعات واعتمادها وإكمالها.', 'Sales returns', 'sales returns', 'Create, approve, and complete sales returns.'),
        'returns_exchanges_gift_instruments' => self::section('المرتجعات والاستبدالات وأدوات الهدايا', 'المرتجعات والاستبدالات وأدوات الهدايا', 'إدارة عمليات ما بعد البيع وأدوات الهدايا المرتبطة بها.', 'Returns, exchanges, and gift instruments', 'returns, exchanges, and gift instruments', 'Manage after-sales transactions and related gift instruments.'),
        'shifts_cash_movements' => self::section('الورديات وحركات النقدية', 'الورديات وحركات النقدية', 'إدارة فتح الورديات وحركات النقدية والإغلاق والفروقات.', 'Shifts and cash movements', 'shifts and cash movements', 'Manage shift opening, cash movements, closing, and variances.'),
        'stock_counts' => self::section('الجرد', 'جلسات الجرد وتسوياتها', 'تنفيذ الجرد ومراجعته وترحيل فروق المخزون.', 'Stock counts', 'stock-count sessions and reconciliations', 'Perform and review stock counts and post stock differences.'),
        'suppliers' => self::section('الموردون', 'سجلات الموردين', 'إدارة بيانات الموردين وعلاقات التوريد.', 'Suppliers', 'supplier records', 'Manage supplier data and supply relationships.'),
        'suspended_sales' => self::section('المبيعات المعلّقة', 'المبيعات المعلّقة', 'حفظ المبيعات غير المكتملة واستعادتها أو إلغاؤها.', 'Suspended sales', 'suspended sales', 'Save, resume, or cancel incomplete sales.'),
        'transfers' => self::section('تحويلات المخزون', 'تحويلات المخزون', 'إدارة إرسال المخزون واستلامه وفروق التحويل.', 'Stock transfers', 'stock transfers', 'Manage stock dispatch, receipt, and transfer differences.'),
        'users_roles_permissions' => self::section('المستخدمون والأدوار والصلاحيات', 'حسابات المستخدمين والأدوار والصلاحيات', 'إدارة الوصول إلى النظام ونطاقات المستخدمين.', 'Users, roles, and permissions', 'user accounts, roles, and permissions', 'Manage system access and user scopes.'),
        ];
    }

    /** @return array<string, array{ar: array{label: string, description: string}, en: array{label: string, description: string}}> */
    private static function actions(): array
    {
        return self::$actionDefinitions ??= [
        'draft' => self::action('حفظ مسودة :object', 'إنشاء مسودة :object وتعديلها دون اعتمادها.', 'Save :object drafts', 'Create and edit :object drafts without approval.'),
        'distribute' => self::action('توزيع استلام :object', 'تحديد وجهات استلام :object.', 'Distribute :object receiving', 'Select receiving destinations for :object.'),
        'view_cost' => self::action('عرض تكلفة الشراء', 'عرض تكلفة الوحدة في فواتير الشراء.', 'View purchase cost', 'View unit costs in purchase invoices.'),
        'change_cost' => self::action('تعديل تكلفة وحدة الشراء', 'إدخال تكلفة الوحدة وتعديلها في مسودة فاتورة الشراء.', 'Edit purchase unit cost', 'Enter and edit unit costs in a purchase invoice draft.'),
        'change_list0_price' => self::action('تعديل سعر قائمة المستهلك الأساسية 0', 'تعديل سعر البيع المستقل عن تكلفة الشراء في مسودة الفاتورة.', 'Edit Base Consumer Price List 0', 'Edit the sales price separately from purchase cost in the invoice draft.'),
        'print_labels' => self::action('طباعة ملصقات :object', 'طباعة ملصقات المنتجات المرتبطة بـ :object.', 'Print :object labels', 'Print product labels associated with :object.'),
        'adjust' => self::action('تعديل رصيد :object', 'إجراء تسوية مصرح بها على :object.', 'Adjust :object', 'Make an authorized adjustment to :object.'),
        'apply_discount' => self::action('تطبيق خصم على المبيعات', 'تطبيق خصم على عملية بيع ضمن السياسة المحددة.', 'Apply sales discounts', 'Apply a discount to a sale within the configured policy.'),
        'apply_tax' => self::action('تطبيق الضريبة على المبيعات', 'تطبيق الضريبة على عملية بيع وفق الإعدادات المعتمدة.', 'Apply sales tax', 'Apply tax to a sale using the approved settings.'),
        'approve' => self::action('اعتماد :object', 'اعتماد :object بعد المراجعة المطلوبة.', 'Approve :object', 'Approve :object after the required review.'),
        'approve_over_limit' => self::action('اعتماد مرتجعات شراء فوق الحد', 'اعتماد مرتجع شراء يتجاوز الحد التشغيلي العادي.', 'Approve purchase returns over the limit', 'Approve a purchase return above the normal operating limit.'),
        'assign' => self::action('تعيين :object', 'ربط :object بالمواقع أو النطاقات المصرح بها.', 'Assign :object', 'Assign :object to authorized locations or scopes.'),
        'book_quantity_view' => self::action('عرض الكمية الدفترية أثناء الجرد', 'إظهار كمية النظام للمشارك في الجرد عندما تسمح السياسة.', 'View book quantity during counts', 'Show the system quantity to a stock-count participant when policy allows.'),
        'cancel' => self::action('إلغاء :object', 'إلغاء :object وفق حالتها وقواعد الحماية.', 'Cancel :object', 'Cancel :object when its state and protection rules allow it.'),
        'checkout' => self::action('تسليم أصول التأجير', 'تسجيل خروج الأصل المحجوز وتسليمه للعميل.', 'Check out rental assets', 'Record the checkout and handover of a reserved asset.'),
        'close' => self::action('إغلاق جلسات الجرد', 'إغلاق جلسة الجرد ومنع إدخال عدّات إضافية.', 'Close stock counts', 'Close a stock-count session and stop further entries.'),
        'complete' => self::action('إكمال مرتجعات المبيعات', 'إكمال أثر مرتجع البيع بعد اعتماد مخرجاته.', 'Complete sales returns', 'Complete a sales return after its outcome is approved.'),
        'correct' => self::action('تصحيح عدّات الجرد', 'تصحيح العدّات المسموح بها قبل اعتماد التسوية.', 'Correct stock counts', 'Correct permitted count entries before reconciliation approval.'),
        'cost_edit' => self::action('تعديل تكلفة أصول التأجير', 'تعديل بيانات تكلفة الأصل ضمن صلاحيات التكلفة.', 'Edit rental-asset costs', 'Edit asset cost data within cost permissions.'),
        'cost_view' => self::action('عرض تكاليف :object', 'عرض بيانات التكلفة الحساسة في :object.', 'View :object costs', 'View sensitive cost information for :object.'),
        'create' => self::action('إنشاء :object', 'إنشاء سجل جديد ضمن :object.', 'Create :object', 'Create a new record within :object.'),
        'customer_create' => self::action('إنشاء ملفات العملاء', 'إضافة ملف عميل جديد وفق ضوابط الموافقة والخصوصية.', 'Create customer profiles', 'Add a customer profile under consent and privacy controls.'),
        'customer_edit' => self::action('تعديل ملفات العملاء', 'تحديث بيانات العميل المسموح بتعديلها.', 'Edit customer profiles', 'Update customer information that is allowed to change.'),
        'customer_export' => self::action('تصدير بيانات العملاء', 'تصدير بيانات العملاء ضمن النطاق والغرض المصرح بهما.', 'Export customer data', 'Export customer data within the authorized scope and purpose.'),
        'customer_merge' => self::action('دمج ملفات العملاء', 'دمج ملفات العملاء المكررة مع حفظ الأثر التاريخي.', 'Merge customer profiles', 'Merge duplicate customer profiles while preserving history.'),
        'customer_sensitive' => self::action('عرض بيانات العملاء الحساسة', 'عرض حقول العملاء الحساسة للغرض المصرح به فقط.', 'View sensitive customer data', 'View sensitive customer fields only for an authorized purpose.'),
        'customer_view' => self::action('عرض ملفات العملاء', 'عرض بيانات العملاء ضمن النطاق والغرض المصرح بهما.', 'View customer profiles', 'View customer data within the authorized scope and purpose.'),
        'difference' => self::action('معالجة فروق التحويل', 'تسجيل قرار معالجة فرق الكمية في تحويل المخزون.', 'Resolve transfer differences', 'Record the disposition of a stock-transfer quantity difference.'),
        'discount_approve' => self::action('اعتماد خصومات المبيعات', 'اعتماد خصم يتطلب مراجعة إدارية.', 'Approve sales discounts', 'Approve a sales discount that requires management review.'),
        'dispatch' => self::action('إرسال تحويلات المخزون', 'اعتماد خروج كمية التحويل من الموقع المصدر.', 'Dispatch stock transfers', 'Confirm stock leaving the transfer source.'),
        'earn' => self::action('إضافة نقاط الولاء', 'إضافة نقاط الولاء من معاملة مؤهلة.', 'Earn loyalty points', 'Add loyalty points from an eligible transaction.'),
        'edit' => self::action('تعديل :object', 'تعديل :object قبل الوصول إلى حالة محمية.', 'Edit :object', 'Edit :object before it reaches a protected state.'),
        'expire' => self::action('إنهاء صلاحية :object', 'تسجيل انتهاء صلاحية :object وفق السياسة.', 'Expire :object', 'Mark :object as expired according to policy.'),
        'export' => self::action('تصدير :object', 'تصدير :object ضمن النطاق والحقول المصرح بها.', 'Export :object', 'Export :object within authorized scope and fields.'),
        'export_pdf' => self::action('تصدير التقارير بصيغة PDF', 'إنشاء نسخة PDF من التقرير ضمن النطاق المصرح.', 'Export reports as PDF', 'Create a PDF report within the authorized scope.'),
        'export_xlsx' => self::action('تصدير التقارير بصيغة Excel', 'إنشاء ملف Excel من التقرير ضمن النطاق المصرح.', 'Export reports as Excel', 'Create an Excel report within the authorized scope.'),
        'inspect' => self::action('فحص أصول التأجير', 'تسجيل فحص حالة الأصل عند التسليم أو الإرجاع.', 'Inspect rental assets', 'Record an asset condition inspection at checkout or return.'),
        'issue' => self::action('إصدار :object', 'إصدار :object وربطها بالمعاملة الأصلية.', 'Issue :object', 'Issue :object and link it to the source transaction.'),
        'logical_delete' => self::action('أرشفة :object', 'إخفاء سجل مسموح بأرشفته دون حذف تاريخه.', 'Archive :object', 'Archive an eligible record without deleting its history.'),
        'manage' => self::action('إدارة :object', 'تغيير إعدادات :object ضمن الحماية الإدارية.', 'Manage :object', 'Change :object within administrative safeguards.'),
        'open' => self::action('فتح جلسات الجرد', 'بدء جلسة الجرد والسماح بإدخال العدّات.', 'Open stock counts', 'Start a stock-count session and allow count entries.'),
        'open_price' => self::action('إدخال سعر بيع مفتوح', 'إدخال سعر بيع يدوي ضمن الحدود والسبب المطلوبين.', 'Enter open sales prices', 'Enter a manual sales price within required bounds and reason.'),
        'open_price_approve' => self::action('اعتماد سعر البيع المفتوح', 'اعتماد سعر مفتوح يتطلب مراجعة إدارية.', 'Approve open sales prices', 'Approve an open price that requires management review.'),
        'override' => self::action('تجاوز ضوابط :object', 'استخدام استثناء مصرح به مع السبب والتدقيق المطلوبين.', 'Override :object controls', 'Use an authorized exception with the required reason and audit.'),
        'participate' => self::action('المشاركة في الجرد', 'إدخال العدّات في جلسة جرد معيّنة للمستخدم.', 'Participate in stock counts', 'Enter counts in a stock-count session assigned to the user.'),
        'payment_create' => self::action('تسجيل مدفوعات المبيعات', 'إضافة دفعة إلى عملية البيع بوسيلة معتمدة.', 'Record sales payments', 'Add a payment to a sale using an approved method.'),
        'payment_evidence_upload' => self::action('رفع إثباتات الدفع', 'إرفاق إثبات دفع محمي بعملية البيع.', 'Upload payment evidence', 'Attach protected payment evidence to a sale.'),
        'payment_evidence_view' => self::action('عرض إثباتات الدفع', 'عرض ملفات إثبات الدفع المحمية للمبيعات.', 'View payment evidence', 'View protected payment-evidence files for sales.'),
        'payment_view' => self::action('عرض مدفوعات المبيعات', 'عرض تفاصيل المدفوعات المرتبطة بالمبيعات.', 'View sales payments', 'View payment details attached to sales.'),
        'post_adjustment' => self::action('ترحيل تسوية الجرد', 'ترحيل حركة المخزون الناتجة عن فرق جرد معتمد.', 'Post stock-count adjustments', 'Post the stock movement created by an approved count difference.'),
        'preferred_change' => self::action('تغيير المورد المفضل', 'تغيير علاقة المورد المفضل للمنتج مع تسجيل الأثر.', 'Change preferred suppliers', 'Change a product preferred-supplier relationship with an audit trail.'),
        'print' => self::action('طباعة :object', 'طباعة :object بالتنسيق والنطاق المصرح بهما.', 'Print :object', 'Print :object in an authorized format and scope.'),
        'receive' => self::action('استلام تحويلات المخزون', 'تأكيد الكمية المستلمة في موقع وجهة التحويل.', 'Receive stock transfers', 'Confirm quantities received at the transfer destination.'),
        'reconcile' => self::action('تسوية فروق الجرد', 'مراجعة فروق الجرد واعتماد نتيجة التسوية.', 'Reconcile stock counts', 'Review count differences and approve the reconciliation result.'),
        'redeem' => self::action('استبدال :object', 'استخدام الرصيد أو القيمة المتاحة ضمن القيود المعتمدة.', 'Redeem :object', 'Use available value within the approved restrictions.'),
        'reject' => self::action('رفض :object', 'رفض :object المقدمة مع تسجيل سبب القرار.', 'Reject :object', 'Reject submitted :object and record the decision reason.'),
        'reprint' => self::action('إعادة طباعة :object', 'إعادة طباعة مستند سبق إصداره مع تسجيل العملية.', 'Reprint :object', 'Print an issued document again with an audit trail.'),
        'request_recount' => self::action('طلب إعادة الجرد', 'إعادة بنود الجرد إلى فريق العدّ للمراجعة.', 'Request a recount', 'Return stock-count lines to the counting team for review.'),
        'reserve' => self::action('حجز أصول التأجير', 'حجز أصل متاح لفترة وموقع محددين.', 'Reserve rental assets', 'Reserve an available asset for a defined time and location.'),
        'return' => self::action('استلام أصول التأجير المرتجعة', 'تسجيل رجوع الأصل من العميل للمعاينة.', 'Receive returned rental assets', 'Record an asset returned by the customer for inspection.'),
        'reverse' => self::action('عكس :object', 'إنشاء إجراء عكسي مرجعي بدلاً من تعديل السجل المعتمد.', 'Reverse :object', 'Create a referenced reversal instead of editing an approved record.'),
        'settle' => self::action('تسوية :object', 'تسجيل تسوية مالية مصرح بها في المحفظة.', 'Settle :object', 'Record an authorized financial settlement in the wallet.'),
        'share' => self::action('مشاركة عروض الأسعار', 'إنشاء وسيلة مشاركة آمنة لعرض السعر.', 'Share quotations', 'Create a safe way to share a quotation.'),
        'status' => self::action('تغيير حالة أصول التأجير', 'تحديث الحالة التشغيلية لأصل التأجير.', 'Change rental-asset status', 'Update the operating status of a rental asset.'),
        'submit' => self::action('إرسال :object للمراجعة', 'نقل :object من المسودة إلى مسار المراجعة.', 'Submit :object for review', 'Move :object from draft into the review workflow.'),
        'validate' => self::action('التحقق من :object', 'التحقق من صلاحية المستند دون استخدامه أو تغييره.', 'Validate :object', 'Check whether a document is valid without using or changing it.'),
        'view' => self::action('عرض :object', 'عرض :object ضمن نطاق المستخدم المصرح به.', 'View :object', 'View :object within the user authorized scope.'),
        'update' => self::action('تحديث :object', 'تحديث :object قبل الوصول إلى حالة محمية.', 'Update :object', 'Update :object before it reaches a protected state.'),
        'delete' => self::action('حذف :object', 'حذف :object وفق سياسة الحذف المنطقي وحفظ أثره التاريخي.', 'Delete :object', 'Delete :object using the permitted logical-delete policy while preserving its history.'),
        'view_patterns' => self::action('عرض نماذج واجهة النظام', 'مراجعة المكونات والأنماط المرئية المعتمدة للواجهة.', 'View interface patterns', 'Review approved visual components and interface patterns.'),
        'void' => self::action('إبطال :object', 'إبطال قيمة غير مستخدمة مع حفظ سجلها التاريخي.', 'Void :object', 'Void unused value while preserving its history.'),
        ];
    }

    /** @return array{name: string, description: string} */
    public static function sectionDetails(string $module, string $locale): array
    {
        $language = self::language($locale);
        $section = self::sections()[$module][$language] ?? null;

        if ($section === null) {
            return $language === 'ar'
                ? ['name' => 'صلاحيات النظام', 'description' => 'صلاحيات أعمال إضافية ضمن النظام.']
                : ['name' => 'System permissions', 'description' => 'Additional business permissions within the system.'];
        }

        return ['name' => $section['name'], 'description' => $section['description']];
    }

    /** @return array{label: string, description: string} */
    public static function permission(Permission $permission, string $locale): array
    {
        $language = self::language($locale);
        $section = self::sections()[$permission->module][$language] ?? null;
        $action = self::actions()[self::canonicalAction($permission->action)][$language] ?? null;

        if ($section === null || $action === null) {
            return $language === 'ar'
                ? ['label' => 'صلاحية أعمال إضافية', 'description' => 'تنفيذ إجراء مصرح به ضمن النظام.']
                : ['label' => 'Additional business permission', 'description' => 'Perform an authorized action within the system.'];
        }

        return [
            'label' => str_replace(':object', $section['object'], $action['label']),
            'description' => str_replace(':object', $section['object'], $action['description']),
        ];
    }

    public static function canonicalAction(string $action): string
    {
        return self::LEGACY_ACTION_ALIASES[$action] ?? $action;
    }

    /** @return list<string> */
    public static function canonicalActionsForModule(string $module, array $fallback): array
    {
        return self::CANONICAL_MODULE_ACTIONS[$module] ?? $fallback;
    }

    /** @return list<string> */
    public static function knownModules(): array
    {
        return array_keys(self::sections());
    }

    /** @return list<string> */
    public static function knownActions(): array
    {
        return array_keys(self::actions());
    }

    /** @return array{ar: array{name: string, object: string, description: string}, en: array{name: string, object: string, description: string}} */
    private static function section(string $arName, string $arObject, string $arDescription, string $enName, string $enObject, string $enDescription): array
    {
        return [
            'ar' => ['name' => $arName, 'object' => $arObject, 'description' => $arDescription],
            'en' => ['name' => $enName, 'object' => $enObject, 'description' => $enDescription],
        ];
    }

    /** @return array{ar: array{label: string, description: string}, en: array{label: string, description: string}} */
    private static function action(string $arLabel, string $arDescription, string $enLabel, string $enDescription): array
    {
        return [
            'ar' => ['label' => $arLabel, 'description' => $arDescription],
            'en' => ['label' => $enLabel, 'description' => $enDescription],
        ];
    }

    private static function language(string $locale): string
    {
        return str_starts_with($locale, 'ar') ? 'ar' : 'en';
    }
}
