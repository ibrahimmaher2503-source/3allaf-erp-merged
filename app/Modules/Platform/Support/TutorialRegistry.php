<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support;

final class TutorialRegistry
{
    /** @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        return [
            'UI-SYS-001' => self::guide(
                ['ar' => 'لوحة التحكم', 'en' => 'Dashboard'],
                ['ar' => 'تعرض لوحة التحكم مؤشرات العمل والتنبيهات المسموح بها لنطاقك.', 'en' => 'The dashboard presents the work indicators and alerts allowed for your scope.'],
                ['dashboard_reports.view'],
                'FLW-HELP-01',
                ['dashboard'],
                self::steps('dashboard'),
                self::fields('dashboard'),
                'dashboard',
            ),
            'UI-ADM-002' => self::guide(
                ['ar' => 'إعدادات الشركة', 'en' => 'Company Settings'],
                ['ar' => 'تراجع هوية الشركة وإعداداتها العامة المسموح بها.', 'en' => 'Review the company identity and permitted general settings.'],
                ['company_settings.view', 'company_settings.edit'],
                'FLW-ADM-05',
                ['admin.settings'],
                self::steps('admin-settings'),
                self::fields('admin-settings'),
                'admin-settings',
            ),
            'UI-ADM-003' => self::guide(
                ['ar' => 'الفروع', 'en' => 'Branches'],
                ['ar' => 'تدير قائمة الفروع وحالتها.', 'en' => 'Manage the branch list and status.'],
                ['branches_stores.view', 'branches_stores.create', 'branches_stores.edit'],
                'FLW-ADM-01',
                ['admin.branches'],
                self::steps('branches'),
                self::fields('branches'),
                'branches',
            ),
            'UI-ADM-004' => self::guide(
                ['ar' => 'المتاجر والربط', 'en' => 'Stores & Mapping'],
                ['ar' => 'تدير مواقع التخزين وربطها بالفروع.', 'en' => 'Manage stock locations and their branch mapping.'],
                ['branches_stores.view', 'branches_stores.create', 'branches_stores.edit'],
                'FLW-ADM-02',
                ['admin.stores'],
                self::steps('stores'),
                self::fields('stores'),
                'stores',
            ),
            'UI-ADM-005' => self::guide(
                ['ar' => 'أدراج النقدية', 'en' => 'Cash Drawers'],
                ['ar' => 'تراجع تعيينات الأدراج وحالتها.', 'en' => 'Review drawer assignments and status.'],
                ['drawers_payments_tax_numbering_printers.view', 'drawers_payments_tax_numbering_printers.edit'],
                'FLW-ADM-03',
                ['admin.cash-drawers'],
                self::steps('drawers'),
                self::fields('drawers'),
                'drawers',
            ),
            'UI-ADM-010' => self::guide(
                ['ar' => 'المستخدمون', 'en' => 'Users'],
                ['ar' => 'تدير المستخدمين والنطاقات المعتمدة.', 'en' => 'Manage users and approved scopes.'],
                ['users_roles_permissions.view', 'users_roles_permissions.create', 'users_roles_permissions.edit'],
                'FLW-ADM-04',
                ['admin.authorization-baseline'],
                self::steps('users'),
                self::fields('users'),
                'users',
            ),
            'UI-ADM-011' => self::guide(
                ['ar' => 'الأدوار', 'en' => 'Roles'],
                ['ar' => 'تراجع الأدوار القياسية وملخص التعيينات.', 'en' => 'Review canonical roles and assignment summaries.'],
                ['users_roles_permissions.view', 'users_roles_permissions.edit'],
                'FLW-ADM-04',
                ['admin.authorization-baseline'],
                self::steps('roles'),
                self::fields('roles'),
                'roles',
            ),
            'UI-ADM-012' => self::guide(
                ['ar' => 'الصلاحيات', 'en' => 'Permissions'],
                ['ar' => 'تراجع مصفوفة الصلاحيات ضمن نطاق الإدارة المعتمد.', 'en' => 'Review the permission matrix within the approved administration scope.'],
                ['users_roles_permissions.view', 'users_roles_permissions.edit'],
                'FLW-ADM-04',
                ['admin.authorization-baseline'],
                self::steps('permissions'),
                self::fields('permissions'),
                'permissions',
            ),
            'UI-SYS-003' => self::guide(
                ['ar' => 'سجل التدقيق', 'en' => 'Audit Logs'],
                ['ar' => 'تراجع الأحداث المسموح بها للتتبع والدعم.', 'en' => 'Review permitted events for traceability and support.'],
                ['audit_logs.view'],
                'FLW-SYS-01',
                ['admin.audit'],
                self::steps('audit'),
                self::fields('audit'),
                'audit',
            ),
            'UI-SYS-004' => self::guide(
                ['ar' => 'صحة النظام', 'en' => 'System Health'],
                ['ar' => 'تعرض فحوصات صحية محلية آمنة دون أسرار.', 'en' => 'Shows safe local health checks without secrets.'],
                ['audit_logs.view'],
                'FLW-SYS-02',
                ['system.health'],
                self::steps('health'),
                self::fields('health'),
                'health',
            ),
            'UI-CAT-001' => self::guide(
                ['ar' => 'قائمة المنتجات', 'en' => 'Product List'],
                ['ar' => 'تستعرض المنتجات والبيانات المسموح بها ضمن نطاقك.', 'en' => 'Browse products and permitted data within your scope.'],
                ['products_categories_brands.view', 'products_categories_brands.create', 'products_categories_brands.edit'],
                'FLW-CAT-01',
                ['catalog.products'],
                self::steps('products'),
                self::fields('products'),
                'products',
            ),
            'UI-CAT-002' => self::guide(
                ['ar' => 'تفاصيل المنتج', 'en' => 'Product Details'],
                ['ar' => 'تعرض تفاصيل المنتج والتبويبات المسموح بها.', 'en' => 'Shows product details and permitted tabs.'],
                ['products_categories_brands.view'],
                'FLW-CAT-01',
                ['catalog.products.show'],
                self::steps('product-detail'),
                self::fields('product-detail'),
                'product-detail',
            ),
            'UI-CAT-003' => self::guide(
                ['ar' => 'إنشاء وتعديل المنتج', 'en' => 'Product Create/Edit'],
                ['ar' => 'تحرر بيانات المنتج وفق الصلاحيات وقواعد المجال.', 'en' => 'Edit product data according to permissions and domain rules.'],
                ['products_categories_brands.create', 'products_categories_brands.edit'],
                'FLW-CAT-01',
                ['catalog.products.create', 'catalog.products.edit'],
                self::steps('product-form'),
                self::fields('product-form'),
                'product-form',
            ),
            'UI-CAT-004' => self::guide(
                ['ar' => 'استيراد المنتجات', 'en' => 'Product Import'],
                ['ar' => 'ترفع ملفاً وتراجعه ثم تعتمد الصفوف الصحيحة فقط.', 'en' => 'Upload, review, and approve only valid rows.'],
                ['products_categories_brands.view', 'products_categories_brands.create', 'products_categories_brands.edit', 'products_categories_brands.approve', 'products_categories_brands.export'],
                'FLW-CAT-02',
                ['catalog.products.import'],
                self::steps('product-import'),
                self::fields('product-import'),
                'product-import',
            ),
            'UI-CAT-006' => self::guide(
                ['ar' => 'التصنيفات', 'en' => 'Categories'],
                ['ar' => 'تدير شجرة التصنيفات وحالتها.', 'en' => 'Manage the category tree and status.'],
                ['products_categories_brands.view', 'products_categories_brands.create', 'products_categories_brands.edit'],
                'FLW-CAT-01',
                ['catalog.categories'],
                self::steps('categories'),
                self::fields('categories'),
                'categories',
            ),
            'UI-CAT-007' => self::guide(
                ['ar' => 'العلامات التجارية', 'en' => 'Brands'],
                ['ar' => 'تدير بيانات العلامات التجارية.', 'en' => 'Manage brand master data.'],
                ['products_categories_brands.view', 'products_categories_brands.create', 'products_categories_brands.edit'],
                'FLW-CAT-01',
                ['catalog.brands'],
                self::steps('brands'),
                self::fields('brands'),
                'brands',
            ),
            'UI-SYS-002' => self::guide(
                ['ar' => 'تطبيق النظام', 'en' => 'System App Shell'],
                ['ar' => 'تراجع حالة قابلية التثبيت والتحديث الآمن.', 'en' => 'Review installability and safe update status.'],
                ['dashboard_reports.view'],
                'FLW-SYS-03',
                ['system.app'],
                self::steps('system-app'),
                self::fields('system-app'),
                'system-app',
            ),
        ];
    }

    /** @return array<string, mixed>|null */
    public static function forRoute(?string $routeName): ?array
    {
        foreach (self::all() as $screenId => $guide) {
            if (in_array($routeName, $guide['route_names'], true)) {
                return [...$guide, 'screen_id' => $screenId];
            }
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    public static function find(string $screenId): ?array
    {
        $guide = self::all()[$screenId] ?? null;
        return $guide ? [...$guide, 'screen_id' => $screenId] : null;
    }

    /** @return list<string> */
    public static function screenIds(): array
    {
        return array_keys(self::all());
    }

    private static function actionLabel(string $permission, string $key = '', ?string $route = null): array
    {
        if ($key === 'product-import' || $route === 'catalog.products.import') {
            return match ($permission) {
                'products_categories_brands.view' => ['ar' => 'عرض ومراجعة صفحة استيراد المنتجات', 'en' => 'Review and view product import page'],
                'products_categories_brands.create' => ['ar' => 'رفع ومرحلة دفعة استيراد المنتجات', 'en' => 'Stage and upload an import batch'],
                'products_categories_brands.edit' => ['ar' => 'مراجعة نتائج التحقق للصفوف المرحلة', 'en' => 'Review staged validation results'],
                'products_categories_brands.approve' => ['ar' => 'اعتماد دفعة استيراد المنتجات الصالحة', 'en' => 'Approve valid import batch'],
                'products_categories_brands.export' => ['ar' => 'تنزيل تقرير أخطاء الاستيراد', 'en' => 'Download import error report'],
                default => ['ar' => 'إجراء مسموح به للاستيراد', 'en' => 'Permitted import action'],
            };
        }

        if ($key === 'products' || $route === 'catalog.products') {
            return match ($permission) {
                'products_categories_brands.view' => ['ar' => 'عرض قائمة المنتجات', 'en' => 'Browse product catalog'],
                'products_categories_brands.create' => ['ar' => 'إضافة منتج جديد', 'en' => 'Create new product'],
                'products_categories_brands.edit' => ['ar' => 'تعديل بيانات المنتج', 'en' => 'Edit product data'],
                default => ['ar' => 'إجراء مسموح به للمنتجات', 'en' => 'Permitted product action'],
            };
        }

        if ($key === 'product-detail' || $route === 'catalog.products.show') {
            return match ($permission) {
                'products_categories_brands.view' => ['ar' => 'عرض تفاصيل وسجل المنتج', 'en' => 'View product details and history'],
                default => ['ar' => 'إجراء مسموح به للمنتج', 'en' => 'Permitted product action'],
            };
        }

        if ($key === 'product-form' || in_array($route, ['catalog.products.create', 'catalog.products.edit'], true)) {
            return match ($permission) {
                'products_categories_brands.create' => ['ar' => 'إنشاء وحفظ سجل منتج جديد', 'en' => 'Create new product record'],
                'products_categories_brands.edit' => ['ar' => 'تعديل وحفظ بيانات المنتج', 'en' => 'Update product record'],
                default => ['ar' => 'إجراء مسموح به لنموذج المنتج', 'en' => 'Permitted product form action'],
            };
        }

        if ($key === 'categories' || $route === 'catalog.categories') {
            return match ($permission) {
                'products_categories_brands.view' => ['ar' => 'عرض شجرة وهيكل التصنيفات', 'en' => 'View category tree and list'],
                'products_categories_brands.create' => ['ar' => 'إضافة تصنيف جديد', 'en' => 'Create new category'],
                'products_categories_brands.edit' => ['ar' => 'تعديل بيانات والتسلسل الهرمي للتصنيف', 'en' => 'Edit category details'],
                default => ['ar' => 'إجراء مسموح به للتصنيفات', 'en' => 'Permitted category action'],
            };
        }

        if ($key === 'brands' || $route === 'catalog.brands') {
            return match ($permission) {
                'products_categories_brands.view' => ['ar' => 'عرض قائمة العلامات التجارية', 'en' => 'View brand list'],
                'products_categories_brands.create' => ['ar' => 'إضافة علامة تجارية جديدة', 'en' => 'Create new brand'],
                'products_categories_brands.edit' => ['ar' => 'تعديل بيانات العلامة التجارية', 'en' => 'Edit brand details'],
                default => ['ar' => 'إجراء مسموح به للعلامات التجارية', 'en' => 'Permitted brand action'],
            };
        }

        if ($key === 'branches' || $route === 'admin.branches') {
            return match ($permission) {
                'branches_stores.view' => ['ar' => 'عرض قائمة الفروع', 'en' => 'View branch list'],
                'branches_stores.create' => ['ar' => 'إضافة فرع جديد', 'en' => 'Create new branch'],
                'branches_stores.edit' => ['ar' => 'تعديل بيانات الفرع', 'en' => 'Edit branch details'],
                default => ['ar' => 'إجراء مسموح به للفروع', 'en' => 'Permitted branch action'],
            };
        }

        if ($key === 'stores' || $route === 'admin.stores') {
            return match ($permission) {
                'branches_stores.view' => ['ar' => 'عرض مواقع التخزين والمتاجر', 'en' => 'View stores and stock locations'],
                'branches_stores.create' => ['ar' => 'إضافة موقع تخزين جديد', 'en' => 'Create new store location'],
                'branches_stores.edit' => ['ar' => 'تعديل بيانات المتجر والربط بالفرع', 'en' => 'Edit store mapping and details'],
                default => ['ar' => 'إجراء مسموح به للمتاجر', 'en' => 'Permitted store action'],
            };
        }

        if ($key === 'users') {
            return match ($permission) {
                'users_roles_permissions.view' => ['ar' => 'عرض قائمة المستخدمين والنطاقات', 'en' => 'View users and scopes'],
                'users_roles_permissions.create' => ['ar' => 'إضافة حساب مستخدم جديد', 'en' => 'Create new user account'],
                'users_roles_permissions.edit' => ['ar' => 'تعديل أدوار ونطاقات المستخدم', 'en' => 'Edit user roles and scopes'],
                default => ['ar' => 'إجراء مسموح به للمستخدمين', 'en' => 'Permitted user action'],
            };
        }

        if ($key === 'roles') {
            return match ($permission) {
                'users_roles_permissions.view' => ['ar' => 'عرض الأدوار القياسية والتعيينات', 'en' => 'View canonical roles and assignments'],
                'users_roles_permissions.edit' => ['ar' => 'تعديل مصفوفة صلاحيات الأدوار', 'en' => 'Edit role permission mappings'],
                default => ['ar' => 'إجراء مسموح به للأدوار', 'en' => 'Permitted role action'],
            };
        }

        if ($key === 'permissions') {
            return match ($permission) {
                'users_roles_permissions.view' => ['ar' => 'عرض مصفوفة الصلاحيات وسياسات الحماية', 'en' => 'View permission matrix and policy guards'],
                'users_roles_permissions.edit' => ['ar' => 'مراجعة وتعديل مصفوفة الوصول', 'en' => 'Review and adjust permission matrix'],
                default => ['ar' => 'إجراء مسموح به للصلاحيات', 'en' => 'Permitted permission action'],
            };
        }

        return match ($permission) {
            'dashboard_reports.view' => ['ar' => 'عرض مؤشرات لوحة التحكم والتقارير', 'en' => 'View dashboard indicators and reports'],
            'company_settings.view' => ['ar' => 'عرض إعدادات الشركة الهيكلية', 'en' => 'View company structural settings'],
            'company_settings.edit' => ['ar' => 'تعديل إعدادات الشركة والهوية', 'en' => 'Edit company settings and identity'],
            'drawers_payments_tax_numbering_printers.view' => ['ar' => 'عرض تعيينات الأدراج والإعدادات التشغيلية', 'en' => 'View drawer assignments and operational settings'],
            'drawers_payments_tax_numbering_printers.edit' => ['ar' => 'تعديل تعيينات أدراج النقدية والإعدادات', 'en' => 'Edit cash drawer assignments and settings'],
            'audit_logs.view' => ['ar' => 'عرض سجلات التدقيق وصحة النظام', 'en' => 'View audit logs and system health'],
            default => ['ar' => 'إجراء مسموح به', 'en' => 'Permitted action'],
        };
    }

    private static function guide(array $title, array $purpose, array $permissions, string $flow, array $routes, array $steps, array $fields, string $key = ''): array
    {
        return [
            'route_names' => $routes,
            'title' => $title,
            'purpose' => $purpose,
            'when_to_use' => ['ar' => 'استخدم هذه الشاشة عندما تكون مهمتك الحالية مرتبطة بهذا السجل أو الإجراء.', 'en' => 'Use this screen when your current task relates to this record or operation.'],
            'permissions' => $permissions,
            'approved_actions' => array_map(fn (string $permission): array => ['key' => $permission, 'label' => self::actionLabel($permission, $key, $routes[0] ?? null), 'required_permission' => $permission], $permissions),
            'stories' => ['US-046'],
            'flows' => [$flow],
            'acceptance_criteria' => ['AC-UI-08', 'AC-UI-09', 'AC-UI-12'],
            'sections' => [
                'steps' => $steps,
                'fields' => $fields,
                'notes' => ['ar' => 'اعتمد فقط ما يظهر ضمن نطاقك الحالي. لا تعرض أو تدخل بيانات خارج المهمة الحالية.', 'en' => 'Use only what is visible within your current scope. Do not expose or enter data outside the current task.'],
                'warnings' => ['ar' => 'الإجراءات غير المتاحة تبقى محكومة بالخادم والصلاحيات.', 'en' => 'Unavailable actions remain enforced by the server and permissions.'],
                'errors' => ['ar' => 'راجع رسالة التحقق الظاهرة وأصلح الحقل المطلوب قبل المتابعة.', 'en' => 'Review the validation message and fix the required field before continuing.'],
                'next_step' => ['ar' => 'انتقل إلى الخطوة التالية في دليل التدفق المرتبط.', 'en' => 'Continue with the next step in the related flow.'],
                'faq' => ['ar' => 'لا يوجد سؤال شائع منشور إضافي لهذه الشاشة.', 'en' => 'No additional published FAQ is available for this screen.'],
            ],
            'tour_steps' => array_map(fn (array $step): array => $step, $steps),
            'updated_at' => '2026-08-04',
            'version' => '1.0',
        ];
    }

    private static function steps(string $key): array
    {
        $items = match ($key) {
            'dashboard' => [
                ['title' => ['ar' => 'رأس لوحة التحكم التشغيلية', 'en' => 'Workspace Operations Header'], 'body' => ['ar' => 'استعرض اسم مساحة العمل ومؤشر حالة البنية التحتية الأساسية.', 'en' => 'Overview workspace title and system foundation progress indicators.'], 'selector' => '[data-guide="dashboard-header"]'],
                ['title' => ['ar' => 'حالة الجاهزية الأساسية', 'en' => 'Foundation Readiness Status'], 'body' => ['ar' => 'تابع حالة المكونات الأساسية المعتمدة في البيئة التشغيلية.', 'en' => 'Monitor the readiness status of verified building blocks in the system.'], 'selector' => '[data-guide="dashboard-foundation"]'],
                ['title' => ['ar' => 'قائمة المكونات النشطة', 'en' => 'Verified Core Building Blocks'], 'body' => ['ar' => 'افحص الجاهزية للهيكل التنفيذي والمصادقة واتجاهات اللغة.', 'en' => 'Inspect app shell, authentication, and language direction status.'], 'selector' => '[data-guide="dashboard-foundation-list"]'],
                ['title' => ['ar' => 'استكمال إعداد المنصة', 'en' => 'Continue Platform Setup'], 'body' => ['ar' => 'قسم التوجيه إلى إعدادات الحساب ومراحل البنية التحتية القادمة.', 'en' => 'Section guiding to account settings and active milestone setups.'], 'selector' => '[data-guide="dashboard-setup-section"]'],
                ['title' => ['ar' => 'فتح إعدادات الحساب', 'en' => 'Open Account Settings'], 'body' => ['ar' => 'زر الانتقال المباشر لإدارة الملف الشخصي والأمان والوثائق.', 'en' => 'Direct navigation button to manage user profile and security.'], 'selector' => '[data-guide="dashboard-profile-action"]'],
            ],
            'system-app' => [
                ['title' => ['ar' => 'رأس تطبيق النظام وشاشة الجاهزية', 'en' => 'PWA Shell & Status Header'], 'body' => ['ar' => 'استعراض حالة تطبيق النظام PWA ومؤشر الاتصال الحي بالشبكة.', 'en' => 'Overview PWA app shell status and live network online/offline badge.'], 'selector' => '[data-guide="system-app-header"]'],
                ['title' => ['ar' => 'بطاقة حالة الاتصال بالشبكة', 'en' => 'Connectivity Status Card'], 'body' => ['ar' => 'متابعة مؤشرات الاتصال بالشبكة وفق المعايير القياسية للمتصفح.', 'en' => 'Monitor browser-standard online and offline network status.'], 'selector' => '[data-guide="system-app-connectivity"]'],
                ['title' => ['ar' => 'بطاقة سياسة التخزين المؤقت المحمي', 'en' => 'Cache Policy Security Card'], 'body' => ['ar' => 'التأكد من عدم تخزين أي استجابات حساسة أو محمية بالصلاحيات أوفلاين.', 'en' => 'Verify no sensitive or authenticated data is cached offline.'], 'selector' => '[data-guide="system-app-cache"]'],
                ['title' => ['ar' => 'بطاقة جاهزية القالب القابل للتثبيت', 'en' => 'Installable PWA Shell Card'], 'body' => ['ar' => 'مراجعة ملف البيانات الوصفية ومُصادق الخدمة الساكن لتسريع التصفح.', 'en' => 'Review PWA manifest and static service worker for fast navigation.'], 'selector' => '[data-guide="system-app-installable"]'],
                ['title' => ['ar' => 'بطاقة اللغة واتجاه واجهة المستخدم', 'en' => 'Current Locale & Direction Card'], 'body' => ['ar' => 'مراجعة كود اللغة الحالي واتجاه المستند المعين (RTL أو LTR).', 'en' => 'Inspect active application locale code and layout direction (RTL/LTR).'], 'selector' => '[data-guide="system-app-locale"]'],
            ],
            'audit' => [
                ['title' => ['ar' => 'رأس سجل التدقيق التشغيلي', 'en' => 'Audit Log Header'], 'body' => ['ar' => 'سجل تتبع الحركات التاريخية المحمي والمحفوظ دون إمكانية للحذف.', 'en' => 'Append-only operational history tracking system activity securely.'], 'selector' => '[data-guide="audit-header"]'],
                ['title' => ['ar' => 'خيارات تصفية السجل المتقدمة', 'en' => 'Audit Search & Filter Toolbar'], 'body' => ['ar' => 'تصفية الأحداث حسب التصنيف، الحدث، المستخدم، الفرع، المتجر، أو الفترة.', 'en' => 'Filter events by category, event type, actor, branch, store, or date range.'], 'selector' => '[data-guide="audit-filters"]'],
                ['title' => ['ar' => 'جدول أحداث سجل التدقيق', 'en' => 'Audit Events Registry Table'], 'body' => ['ar' => 'عرض تاريخ ووقت الحركة، اسم الحدث، منفذ العملية، والفرع/المتجر.', 'en' => 'Inspect timestamp, event name, executing actor, source type, and scope.'], 'selector' => '[data-guide="audit-table"]'],
                ['title' => ['ar' => 'معاينة تفاصيل الحركة المحمية', 'en' => 'View Audit Event Details Action'], 'body' => ['ar' => 'فتح تفاصيل الحركة لمقارنة القيم قبل وبعد التعديل ومعرف الطلب.', 'en' => 'Open modal comparing protected before/after record states and correlation ID.'], 'selector' => '[data-guide="audit-view-action"], [data-guide="audit-table"]'],
                ['title' => ['ar' => 'تصفح صفحات سجل التدقيق', 'en' => 'Audit Events Log Pagination'], 'body' => ['ar' => 'التنقل في صفحات سجل الأحداث بكفاءة ودون تحميل زائد.', 'en' => 'Browse paginated audit log entries without performance impact.'], 'selector' => '[data-guide="audit-pagination"], [data-guide="audit-filters"]'],
            ],
            'health' => [
                ['title' => ['ar' => 'رأس مراقبة صحة النظام', 'en' => 'System Health Header'], 'body' => ['ar' => 'استعراض المؤشرات الصحية المباشرة للبيئة المحلية ومعرف الطلب.', 'en' => 'Monitor local system readiness status, PHP/Laravel versions, and request ID.'], 'selector' => '[data-guide="health-header"]'],
                ['title' => ['ar' => 'زر تحديث حالة الجاهزية', 'en' => 'Refresh Health Status Action'], 'body' => ['ar' => 'إعادة الفحص الآمن لجميع المكونات التشغيلية وتحديث النتائج.', 'en' => 'Re-run system health checks to fetch current operational status.'], 'selector' => '[data-guide="health-refresh-action"]'],
                ['title' => ['ar' => 'مؤشر الحالة العامة للنظام', 'en' => 'Overall Operational Health Banner'], 'body' => ['ar' => 'تنبيه مباشر يوضح هل المنصة تعمل بكفاءة كاملة أم بها أي تعثر.', 'en' => 'Live callout indicating operational, degraded, or critical platform status.'], 'selector' => '[data-guide="health-banner"]'],
                ['title' => ['ar' => 'بطاقات فحوصات المكونات الرئيسية', 'en' => 'Component Health Cards Grid'], 'body' => ['ar' => 'فحص قاعدة البيانات (SQLite)، نظام الملفات، الذاكرة المؤقتة، والبيئة.', 'en' => 'Inspect Database, Storage, Cache, and Application environment statuses.'], 'selector' => '[data-guide="health-grid"]'],
                ['title' => ['ar' => 'جدول البيانات التشغيلية الوصفية', 'en' => 'Platform Overview & Metadata Table'], 'body' => ['ar' => 'مراجعة اسم التطبيق، اللغة المعتمدة، ومعرف التتبع الحالي.', 'en' => 'View platform metadata properties including app locale and check timestamp.'], 'selector' => '[data-guide="health-table"]'],
            ],
            'admin-settings' => [
                ['title' => ['ar' => 'رأس إعدادات النظام القياسية', 'en' => 'System Settings Baseline Header'], 'body' => ['ar' => 'مراجعة الهوية المحلية للشركة وسياسات النظام وسجل التغييرات.', 'en' => 'Review company identity baseline, system policies, and audit log links.'], 'selector' => '[data-guide="settings-header"]'],
                ['title' => ['ar' => 'تبويبات أقسام الإعدادات', 'en' => 'Settings Navigation Tabs'], 'body' => ['ar' => 'التنقل بين بيانات الشركة، وسائل الدفع، الضرائب، الترقيم، والطابعات.', 'en' => 'Switch between Company, Payment Methods, Tax, Sequences, Printers, and Audit tabs.'], 'selector' => '[data-guide="settings-tabs"]'],
                ['title' => ['ar' => 'بطاقة البيانات الأساسية للشركة', 'en' => 'Company Master Information Card'], 'body' => ['ar' => 'إدخال ومراجعة كود الشركة، الاسم التجاري، الرقم الضريبي، والسجل التجاري.', 'en' => 'Enter and review company code, legal name, tax number, and commercial registration.'], 'selector' => '[data-guide="settings-company-card"]'],
                ['title' => ['ar' => 'سياسات اللغات والعملات والتوقيت', 'en' => 'Localization & Currency Policy Card'], 'body' => ['ar' => 'تحديد العملة الأساسية، المنطقة الزمنية، واللغة الافتراضية للنظام.', 'en' => 'Configure system base currency, timezone, and default application locale.'], 'selector' => '[data-guide="settings-localization-card"]'],
                ['title' => ['ar' => 'حفظ البيانات الأساسية للشركة', 'en' => 'Save Company Baseline Action'], 'body' => ['ar' => 'اعتماد وتدقيق تغييرات بيانات الشركة وكتابتها في سجل التدقيق.', 'en' => 'Save company identity updates and record an audited configuration event.'], 'selector' => '[data-guide="settings-save-button"]'],
            ],
            'branches' => [
                ['title' => ['ar' => 'رأس إدارة الفروع التجارية', 'en' => 'Branch Masters Header'], 'body' => ['ar' => 'استعراض وإدارة مواقع الفروع التجارية وتعيينات متاجر نقاط البيع.', 'en' => 'Manage commercial branch locations and POS selling store assignments.'], 'selector' => '[data-guide="branches-header"]'],
                ['title' => ['ar' => 'زر إضافة فرع جديد', 'en' => 'Add Branch Action Button'], 'body' => ['ar' => 'فتح نموذج إنشاء فرع جديد مخصص لنطاق الشركة.', 'en' => 'Open modal form to create a new branch with unique code and bilingual names.'], 'selector' => '[data-guide="branches-add-action"]'],
                ['title' => ['ar' => 'شريط البحث والتصفية', 'en' => 'Search & Status Filters'], 'body' => ['ar' => 'البحث المباشر بكود أو اسم الفرع والتصفية حسب الحالة التشغيلية.', 'en' => 'Filter branches by active status or search by code and bilingual name.'], 'selector' => '[data-guide="branches-filters"]'],
                ['title' => ['ar' => 'جدول الفروع المعتمدة والربط', 'en' => 'Branch Directory Table & POS Mapping'], 'body' => ['ar' => 'استعراض بيانات الفروع والمنطقة الزمنية والمتجر البيعي المربوط.', 'en' => 'Inspect branch codes, names, store counts, POS selling stores, and actions.'], 'selector' => '[data-guide="branches-table"], [data-guide="branches-empty"]'],
                ['title' => ['ar' => 'التنقل بين صفحات الفروع', 'en' => 'Branch List Pagination'], 'body' => ['ar' => 'التنقل بين الصفحات لاستعراض بقية سجلات الفروع دون بطء.', 'en' => 'Navigate between pages of registered branches efficiently.'], 'selector' => '[data-guide="branches-pagination"], [data-guide="branches-filters"]'],
            ],
            'stores' => [
                ['title' => ['ar' => 'رأس سجل المتاجر والمستودعات', 'en' => 'Store Masters & Mapping Header'], 'body' => ['ar' => 'إدارة المتاجر البيعية والمستودعات والمتاجر الخاصة وتعيينات الفروع.', 'en' => 'Manage physical and logical stores (selling, warehouse, party, damaged, transit).'], 'selector' => '[data-guide="stores-header"]'],
                ['title' => ['ar' => 'زر إضافة متجر جديد', 'en' => 'Add Store Action Button'], 'body' => ['ar' => 'فتح نافذة إضافة متجر جديد وتحديد نوعه والفرع التابع له.', 'en' => 'Open form modal to add a store location and define its operational type.'], 'selector' => '[data-guide="stores-add-action"]'],
                ['title' => ['ar' => 'تصفية المتاجر حسب الفرع والنوع', 'en' => 'Search, Branch & Type Filters'], 'body' => ['ar' => 'تصفية نتائج المتاجر حسب كود الفرع، نوع المتجر، أو الحالة التشغيلية.', 'en' => 'Filter stores by branch context, store type, and active status.'], 'selector' => '[data-guide="stores-filters"]'],
                ['title' => ['ar' => 'جدول المتاجر وسياسة المخزون', 'en' => 'Store Directory Table & Stock Policy'], 'body' => ['ar' => 'استعراض أكواد المتاجر، النمط التشغيلي، ربط نقاط البيع، وسياسة المخزون السالب.', 'en' => 'View store codes, types, branch mapping, negative stock policy, and row actions.'], 'selector' => '[data-guide="stores-table"], [data-guide="stores-empty"]'],
                ['title' => ['ar' => 'تصفح صفحات المتاجر', 'en' => 'Store List Pagination'], 'body' => ['ar' => 'التنقل بين صفحات سجلات المتاجر المسجلة.', 'en' => 'Browse paginated store records cleanly.'], 'selector' => '[data-guide="stores-pagination"], [data-guide="stores-filters"]'],
            ],
            'drawers' => [
                ['title' => ['ar' => 'رأس سجل أدراج النقدية', 'en' => 'Cash Drawer Masters Header'], 'body' => ['ar' => 'تهيئة وتخصيص أدراج النقدية الفيزيائية للفروع والمتاجر البيعية.', 'en' => 'Configure branch-scoped cash drawers and default POS cashier assignments.'], 'selector' => '[data-guide="drawers-header"]'],
                ['title' => ['ar' => 'زر إضافة درج نقدية', 'en' => 'Add Cash Drawer Action'], 'body' => ['ar' => 'إنشاء تسجيل جديد لدرج نقدية وتعيينه للفرع والمتجر المناسب.', 'en' => 'Create a cash drawer record linked to a branch and optional store.'], 'selector' => '[data-guide="drawers-add-action"]'],
                ['title' => ['ar' => 'تصفية الأدراج حسب الفرع والحالة', 'en' => 'Search & Branch Drawer Filters'], 'body' => ['ar' => 'البحث في أكواد الأدراج والتصفية حسب الفرع التابع أو حالة الصيانة.', 'en' => 'Filter cash drawers by code, branch location, or operational status.'], 'selector' => '[data-guide="drawers-filters"]'],
                ['title' => ['ar' => 'جدول أدراج النقدية والتعيينات', 'en' => 'Cash Drawer Registry Table'], 'body' => ['ar' => 'عرض كود الدرج، الفرع والارتباط بالمتجر، والكاشير الموكل إليه.', 'en' => 'Inspect drawer codes, assigned branches, stores, cashiers, and status badges.'], 'selector' => '[data-guide="drawers-table"]'],
                ['title' => ['ar' => 'التنقل في قائمة الأدراج', 'en' => 'Cash Drawer Pagination'], 'body' => ['ar' => 'التنقل عبر صفحات قائمة أدراج النقدية المسجلة.', 'en' => 'Browse paginated cash drawer records safely.'], 'selector' => '[data-guide="drawers-pagination"], [data-guide="drawers-filters"]'],
            ],
            'users' => [
                ['title' => ['ar' => 'رأس إدارة الصلاحيات الأساسية', 'en' => 'Authorization Baseline Header'], 'body' => ['ar' => 'إدارة أدوار المستخدمين ونطاقات الوصول على مستوى الفروع والمتاجر.', 'en' => 'Manage platform user roles, permissions, and branch/store scopes.'], 'selector' => '[data-guide="auth-header"]'],
                ['title' => ['ar' => 'بطاقة إحصاء المستخدمين المسجلين', 'en' => 'Registered Users Stat Card'], 'body' => ['ar' => 'عرض الإجمالي الكلي للمستخدمين النشطين والمسجلين في النظام.', 'en' => 'View total registered users available for role and scope management.'], 'selector' => '[data-guide="auth-users-card"]'],
                ['title' => ['ar' => 'البحث الفوري عن المستخدمين', 'en' => 'Search Users Input Filter'], 'body' => ['ar' => 'البحث عن مستخدم معين بالاسم أو البريد الإلكتروني المعتمد.', 'en' => 'Filter user inventory instantly by name or email address.'], 'selector' => '[data-guide="auth-users-search"]'],
                ['title' => ['ar' => 'جدول حسابات المستخدمين والأدوار', 'en' => 'Users Access Inventory Table'], 'body' => ['ar' => 'استعراض أسماء المستخدمين والبريد، الأدوار المسندة، وحالة التحقيق.', 'en' => 'Inspect user accounts, email verification status, assigned roles, and actions.'], 'selector' => '[data-guide="auth-users-table"]'],
                ['title' => ['ar' => 'إدارة صلاحيات ونطاق المستخدم', 'en' => 'Manage User Authorization Action'], 'body' => ['ar' => 'فتح نافذة تعيين الأدوار والنطاقات المسموح بها للمستخدم المحدد.', 'en' => 'Open modal to assign canonical roles and branch/store scopes to a user.'], 'selector' => '[data-guide="auth-users-manage-action"], [data-guide="auth-users-table"]'],
            ],
            'roles' => [
                ['title' => ['ar' => 'عنوان سياسات التفويض القياسية', 'en' => 'Authorization Policy Title'], 'body' => ['ar' => 'نظام أدوار موحد يدعم التحكم الدقيق بالصلاحيات والتدقيق الشامل.', 'en' => 'Role-based access control baseline supporting full action auditability.'], 'selector' => '[data-guide="auth-header"]'],
                ['title' => ['ar' => 'بطاقة الأدوار القياسية المعتمدة', 'en' => 'Canonical Roles Catalog Card'], 'body' => ['ar' => 'عرض عدد الأدوار الأساسية الجاهزة (مدير، كاشير، مشتريات، إلخ).', 'en' => 'View total count of canonical role definitions in the baseline.'], 'selector' => '[data-guide="auth-roles-card"]'],
                ['title' => ['ar' => 'ملخص سياسة الأمان والتحقق', 'en' => 'Security Policy Overview Section'], 'body' => ['ar' => 'مراجعة آليات تطبيق الصلاحيات بالخادم وقواعد الحماية المعتمدة.', 'en' => 'Review role policy enforcement, scope protection, and server gate rules.'], 'selector' => '[data-guide="auth-overview"]'],
                ['title' => ['ar' => 'جدول توزيع الأدوار على الحسابات', 'en' => 'User Role Assignment Directory'], 'body' => ['ar' => 'متابعة توزيع الأدوار القياسية على الحسابات الفردية لضمان الأمان.', 'en' => 'Monitor canonical role assignments across individual user accounts.'], 'selector' => '[data-guide="auth-users-table"]'],
                ['title' => ['ar' => 'بطاقة تعيينات نطاقات الوصول', 'en' => 'Scope Assignments Summary Card'], 'body' => ['ar' => 'استعراض إجمالي قيود الوصول المطبقة على الفروع والمتاجر.', 'en' => 'Inspect total active branch and store scope restriction records.'], 'selector' => '[data-guide="auth-scopes-card"]'],
            ],
            'permissions' => [
                ['title' => ['ar' => 'رأس مصفوفة الصلاحيات المعتمدة', 'en' => 'Permissions Baseline Header'], 'body' => ['ar' => 'مراجعة قائمة الصلاحيات الخادمية المسجلة لحماية موارد المنصة.', 'en' => 'Review registered server capability permissions guarding platform resources.'], 'selector' => '[data-guide="auth-header"]'],
                ['title' => ['ar' => 'بطاقة إحصاء الصلاحيات القياسية', 'en' => 'Canonical Permissions Matrix Card'], 'body' => ['ar' => 'عرض عدد الصلاحيات المودعة للتحكم بالإجراءات التشغيلية.', 'en' => 'View count of seeded canonical permissions protecting system modules.'], 'selector' => '[data-guide="auth-permissions-card"]'],
                ['title' => ['ar' => 'قيود حماية العمليات بالخادم', 'en' => 'Server Authorization Policy Banner'], 'body' => ['ar' => 'التأكد من خضوع كافة الحركات والطلبات لسياسات الخادم (Policies/Gates).', 'en' => 'Verify that all sensitive application actions require server policy checks.'], 'selector' => '[data-guide="auth-overview"]'],
                ['title' => ['ar' => 'مراجعة صلاحيات المستخدمين', 'en' => 'User Permissions Verification List'], 'body' => ['ar' => 'مراجعة نتائج توزيع الصلاحيات الفعلية على مستخدمي النظام.', 'en' => 'Verify permissions effective distribution across authenticated users.'], 'selector' => '[data-guide="auth-users-table"]'],
                ['title' => ['ar' => 'حدود نطاقات الفروع والمتاجر', 'en' => 'Branch & Store Scope Guard Card'], 'body' => ['ar' => 'تأكيد حظر وصول المستخدم لبث البيانات خارج نطاق فرعه أو متجره.', 'en' => 'Confirm user access isolation strictly enforced by assigned scope.'], 'selector' => '[data-guide="auth-scopes-card"]'],
            ],
            'products' => [
                ['title' => ['ar' => 'رأس سجل المنتجات القياسي', 'en' => 'Product Masters Directory Header'], 'body' => ['ar' => 'إدارة بطاقات المنتجات الأساسية، الباركودات، والتصنيفات في مكان واحد.', 'en' => 'Browse product master records, reportable attributes, and barcodes.'], 'selector' => '[data-guide="products-header"]'],
                ['title' => ['ar' => 'زر إضافة منتج جديد', 'en' => 'Add Product Action Button'], 'body' => ['ar' => 'فتح نموذج إنشاء بطاقة منتج جديدة بكود ثابت وأسماء ثنائية اللغة.', 'en' => 'Open modal to create a new product identity record in the catalog.'], 'selector' => '[data-guide="products-add-action"]'],
                ['title' => ['ar' => 'بطاقة التصفية والبحث المتقدم', 'en' => 'Catalog Search & Filter Controls'], 'body' => ['ar' => 'البحث بالرمز أو الباركود وتصفية المنتجات حسب التصنيف، العلامة، أو النوع.', 'en' => 'Filter product catalog by code, barcode, category, brand, type, or gender.'], 'selector' => '[data-guide="products-filters"]'],
                ['title' => ['ar' => 'جدول المنتجات المعتمدة', 'en' => 'Product Masters Registry Table'], 'body' => ['ar' => 'عرض كود المنتج الثابت، الاسم ثنائي اللغة، النوع، الباركود، والحالة.', 'en' => 'Inspect item codes, bilingual names, product types, primary barcodes, and status.'], 'selector' => '[data-guide="products-table"], [data-guide="products-empty"]'],
                ['title' => ['ar' => 'تصفح قائمة المنتجات', 'en' => 'Product Catalog Pagination'], 'body' => ['ar' => 'التنقل المريح بين صفحات سجلات المنتجات المعتمدة.', 'en' => 'Browse paginated product records smoothly.'], 'selector' => '[data-guide="products-pagination"], [data-guide="products-filters"]'],
            ],
            'product-detail' => [
                ['title' => ['ar' => 'رأس تفاصيل المنتج', 'en' => 'Product Detail Summary Header'], 'body' => ['ar' => 'عرض العنوان الرئيسي للمنتج، زر العودة للقائمة، وزر التعديل المصرح.', 'en' => 'Display product title, navigation back link, and permitted edit actions.'], 'selector' => '[data-guide="product-detail-header"]'],
                ['title' => ['ar' => 'بطاقة هوية المنتج والصورة الرئيسية', 'en' => 'Product Identity & Hero Media Card'], 'body' => ['ar' => 'استعراض الصورة الرئيسية، الكود الثابت، الحالة، التصنيف، والعلامة.', 'en' => 'Inspect primary image, item code, active status, category, and brand.'], 'selector' => '[data-guide="product-detail-hero"]'],
                ['title' => ['ar' => 'الوصف والأنقاط الرئيسية ثنائية اللغة', 'en' => 'Bilingual Descriptions Panel'], 'body' => ['ar' => 'مراجعة النص الوصفي والنقاط الترويجية بالعربية والإنجليزية.', 'en' => 'Review detailed product descriptions and key selling points in AR & EN.'], 'selector' => '[data-guide="product-detail-descriptions"]'],
                ['title' => ['ar' => 'الخصائص الفيزيائية والتصنيفية', 'en' => 'Reportable Physical Attributes Panel'], 'body' => ['ar' => 'استعراض اللون، الحجم، الشخصية، العمر الموجه، الأبعاد، والوزن.', 'en' => 'Inspect colour, size, character, target age, gender, dimensions, and weight.'], 'selector' => '[data-guide="product-detail-attributes"]'],
                ['title' => ['ar' => 'شريط الباركود والملفات المحمية', 'en' => 'Barcodes & Protected Media Sidebar'], 'body' => ['ar' => 'مراجعة الباركودات المسجلة ومعاينة الصور المحمية بالصلاحيات.', 'en' => 'Review assigned barcodes list and preview scope-authorized product images.'], 'selector' => '[data-guide="product-detail-media"]'],
            ],
            'product-form' => [
                ['title' => ['ar' => 'رأس نموذج بطاقة المنتج', 'en' => 'Product Form Header'], 'body' => ['ar' => 'نموذج موحد لإنشاء وتعديل بيانات هوية المنتج والمحتوى.', 'en' => 'Focused header for creating or updating product card information.'], 'selector' => '[data-guide="product-form-header"]'],
                ['title' => ['ar' => 'قسم الهوية الأساسية الثابتة', 'en' => 'Basic Immutable Identity Section'], 'body' => ['ar' => 'إدخال الكود الثابت غير القابل للتعديل لاحقاً، والأسماء والوصف.', 'en' => 'Enter immutable item code, model number, bilingual names, and descriptions.'], 'selector' => '[data-guide="product-form-identity"]'],
                ['title' => ['ar' => 'قسم التصنيف ونوع المنتج', 'en' => 'Classification & Product Type Section'], 'body' => ['ar' => 'اختيار التصنيف النشط، العلامة التجارية، ونوع المنتج (قياسي/مركب/خدمي).', 'en' => 'Select active category, brand, product type, and operational status.'], 'selector' => '[data-guide="product-form-classification"]'],
                ['title' => ['ar' => 'قسم المواصفات والخصائص', 'en' => 'Physical Attributes & Keywords Section'], 'body' => ['ar' => 'إدخال خصائص البحث مثل العمر، الجنس، اللون، الحجم، الأبعاد، والكلمات المفتاحية.', 'en' => 'Configure UOM, target age, gender, colour, dimensions, and search keywords.'], 'selector' => '[data-guide="product-form-attributes"]'],
                ['title' => ['ar' => 'قسم رفع وإدارة الصور المحمية', 'en' => 'Protected Product Media Section'], 'body' => ['ar' => 'رفع صورة رئيسية وحتى 4 صور إضافية محمية عبر مؤسسة المرفقات.', 'en' => 'Upload and organize 1 main image and up to 4 additional protected images.'], 'selector' => '[data-guide="product-form-media"]'],
            ],
            'product-import' => [
                ['title' => ['ar' => 'رأس شاشة استيراد المنتجات', 'en' => 'Product Import Screen Header'], 'body' => ['ar' => 'معالجة واستيراد ملفات المنتجات عبر مراحل التدقيق والاعتماد المحمي.', 'en' => 'Stage, validate, review, and approve product spreadsheet batches safely.'], 'selector' => '[data-guide="import-header"]'],
                ['title' => ['ar' => 'بطاقة رفع الملفات وتجهيزها', 'en' => 'Upload & Stage Spreadsheet Card'], 'body' => ['ar' => 'اختيار ملف Excel أو CSV مطابق للأعمدة الأساسية المطلوبة دون صيغ حسابية.', 'en' => 'Select valid Excel or CSV file with required columns and no formula cells.'], 'selector' => '[data-guide="import-upload-section"]'],
                ['title' => ['ar' => 'تحديد نمط الاستيراد المعتمد', 'en' => 'Select Import Processing Mode'], 'body' => ['ar' => 'اختيار إما إنشاء جديد فقط لمنع التعديل أو تحديث المنتجات الموجودة.', 'en' => 'Choose Create Only to prevent overwrites or Update Existing for updates.'], 'selector' => '[data-guide="import-mode-select"]'],
                ['title' => ['ar' => 'زر مرحلة وفحص الملف', 'en' => 'Stage File Action Button'], 'body' => ['ar' => 'بدء فحص الهيكل والتكرارات وتدقيق البيانات مرجعياً دون كتابة في القاعدة.', 'en' => 'Process file validation and staging without writing any database records yet.'], 'selector' => '[data-guide="import-stage-button"]'],
                ['title' => ['ar' => 'جدول الدفعات المجهزة للمراجعة', 'en' => 'Staged Import Batches List'], 'body' => ['ar' => 'متابعة حالة الدفعات المرفوعة وعدد الصفوف الصالحة والمرفوضة بكل دفعة.', 'en' => 'Inspect staged batches, status badges, valid row counts, and review actions.'], 'selector' => '[data-guide="import-batches-section"]'],
                ['title' => ['ar' => 'معاينة ومراجعة صفوف الدفعة', 'en' => 'Batch Review & Row Diagnostics'], 'body' => ['ar' => 'فحص ملخص الأخطاء والتأكد من سلامة الصفوف قبل اتخاذ قرار الاعتماد.', 'en' => 'Review row status, mapped data, and error details for selected batch.'], 'selector' => '[data-guide="import-review-section"], [data-guide="import-batches-section"]'],
                ['title' => ['ar' => 'زر اعتماد الصفوف الصالحة', 'en' => 'Approve Valid Rows Action Button'], 'body' => ['ar' => 'اعتماد كتابة المنتجات المعتمدة؛ مع حظر العملية في حال وجود أي صف مرفوض.', 'en' => 'Commit valid product rows to database; server blocks if invalid rows exist.'], 'selector' => '[data-guide="import-approve-button"], [data-guide="import-batches-section"]'],
            ],
            'categories' => [
                ['title' => ['ar' => 'رأس سجل تصنيفات المنتجات', 'en' => 'Category Masters Directory Header'], 'body' => ['ar' => 'إدارة الهيكل الهرمي للتصنيفات وقواعد التبعية المعتمدة بالخادم.', 'en' => 'Maintain bounded category hierarchy with server-side dependency guards.'], 'selector' => '[data-guide="categories-header"]'],
                ['title' => ['ar' => 'زر إضافة تصنيف جديد', 'en' => 'Add Category Action Button'], 'body' => ['ar' => 'فتح نموذج إضافة تصنيف جديد وإسناده كتصنيف رئيسي أو فرعي.', 'en' => 'Open modal form to create a new root or child category record.'], 'selector' => '[data-guide="categories-add-action"]'],
                ['title' => ['ar' => 'تصفية وبحث شجرة التصنيفات', 'en' => 'Search & Status Category Filters'], 'body' => ['ar' => 'البحث بالكود أو الاسم ثنائي اللغة والتصفية حسب حالة التفعيل.', 'en' => 'Search categories by code or bilingual name and filter by status.'], 'selector' => '[data-guide="categories-filters"]'],
                ['title' => ['ar' => 'جدول شجرة التصنيفات والترتيب', 'en' => 'Category Hierarchy Table & Order'], 'body' => ['ar' => 'استعراض كود التصنيف، علاقة الأب والفرع، الترتيب التشغيلي، والحالة.', 'en' => 'Inspect category codes, parent markers, sort order, and status badges.'], 'selector' => '[data-guide="categories-table"], [data-guide="categories-empty"]'],
                ['title' => ['ar' => 'تصفح قائمة التصنيفات', 'en' => 'Category Directory Pagination'], 'body' => ['ar' => 'التنقل المنظم بين صفحات شجرة التصنيفات المسجلة.', 'en' => 'Browse paginated category records safely.'], 'selector' => '[data-guide="categories-pagination"], [data-guide="categories-filters"]'],
            ],
            'brands' => [
                ['title' => ['ar' => 'رأس سجل العلامات التجارية', 'en' => 'Brand Masters Directory Header'], 'body' => ['ar' => 'إدارة العلامات التجارية العالمية ثنائية اللغة لمنتجات الكتالوج.', 'en' => 'Maintain global bilingual brand master records for catalog products.'], 'selector' => '[data-guide="brands-header"]'],
                ['title' => ['ar' => 'زر إضافة علامة تجارية', 'en' => 'Add Brand Action Button'], 'body' => ['ar' => 'فتح نموذج إنشاء علامة تجارية جديدة بكود فريد واسم ثنائي اللغة.', 'en' => 'Open modal to create a new brand master record with unique code.'], 'selector' => '[data-guide="brands-add-action"]'],
                ['title' => ['ar' => 'تصفية والبحث في العلامات', 'en' => 'Search & Status Brand Filters'], 'body' => ['ar' => 'البحث بكود العلامة أو اسمها والتصفية حسب الحالة التشغيلية.', 'en' => 'Search brands by code or bilingual name and filter active vs inactive.'], 'selector' => '[data-guide="brands-filters"]'],
                ['title' => ['ar' => 'جدول سجل العلامات التجارية', 'en' => 'Brand Masters Directory Table'], 'body' => ['ar' => 'عرض كود العلامة، الأسماء ثنائية اللغة، عدد المنتجات المربوطة، والحالة.', 'en' => 'View brand codes, bilingual names, assigned products count, and status.'], 'selector' => '[data-guide="brands-table"], [data-guide="brands-empty"]'],
                ['title' => ['ar' => 'تصفح قائمة العلامات التجارية', 'en' => 'Brand Directory Pagination'], 'body' => ['ar' => 'التنقل المريح بين صفحات سجلات العلامات التجارية المسجلة.', 'en' => 'Browse paginated brand records smoothly.'], 'selector' => '[data-guide="brands-pagination"], [data-guide="brands-filters"]'],
            ],
        };

        return array_map(fn (array $step, int $index): array => [
            'key' => 'step-'.($index + 1),
            'selector' => $step['selector'],
            'title' => $step['title'],
            'body' => $step['body'],
        ], $items, array_keys($items));
    }

    private static function fields(string $key): array
    {
        $fields = match ($key) {
            'admin-settings' => [
                ['title' => ['ar' => 'بيانات هوية الشركة', 'en' => 'Company Identity Fields'], 'body' => ['ar' => 'تشمل الاسم الرسمي والاسم التجاري والرقم الضريبي والسجل التجاري والعملة والتوقيت.', 'en' => 'Includes legal name, trade name, tax registration, CR number, currency, and timezone.']],
                ['title' => ['ar' => 'إعدادات الدفع والضرائب والترقيم', 'en' => 'Payment, Tax & Numbering Fields'], 'body' => ['ar' => 'تحدد وسائل الدفع المتاحة، نسبة الضريبة، وتسلسلات أرقام المستندات الرسمية.', 'en' => 'Defines payment methods, effective tax rate, and official document number patterns.']],
            ],
            'branches' => [
                ['title' => ['ar' => 'كود الفرع والاسم', 'en' => 'Branch Code & Name'], 'body' => ['ar' => 'الكود الفريد للفرع والاسم ثنائي اللغة (عربي/إنجليزي) المعروض في المستندات.', 'en' => 'Unique branch code and bilingual names displayed on official documents.']],
                ['title' => ['ar' => 'المنطقة والتوقيت والحالة', 'en' => 'Region, Timezone & Status'], 'body' => ['ar' => 'تحدد النطاق الجغرافي والمنطقة الزمنية المعتمدة وحالة تفعيل الفرع.', 'en' => 'Specifies geographic region, approved timezone, and branch active status.']],
            ],
            'stores' => [
                ['title' => ['ar' => 'كود المتجر ونوعه', 'en' => 'Store Code & Type'], 'body' => ['ar' => 'كود موقع التخزين ونوعه (مستودع رئيسي / متجر بيعي / موقع فرعي).', 'en' => 'Stock location code and type (primary warehouse / selling store / sub-location).']],
                ['title' => ['ar' => 'ربط الفرع والتأشير البيعي', 'en' => 'Branch Association & Selling Flag'], 'body' => ['ar' => 'تحدد الفرع المالكي للمتجر وتأشير المتجر البيعي الرئيسي للفرع.', 'en' => 'Defines owning branch and primary selling store mapping for retail transactions.']],
            ],
            'drawers' => [
                ['title' => ['ar' => 'كود الدرج والاسم', 'en' => 'Drawer Code & Name'], 'body' => ['ar' => 'المعرف الفريد لدرج النقدية الفيزيائي في موقع البيع.', 'en' => 'Unique identifier for the physical cash drawer at point of sale.']],
                ['title' => ['ar' => 'الموقع والوردية النشطة', 'en' => 'Location & Active Shift'], 'body' => ['ar' => 'الفرع والمتجر المربوط به الدرج وحالة الوردية المفتوحة حالياً.', 'en' => 'Mapped branch and store along with current open shift assignment state.']],
            ],
            'users' => [
                ['title' => ['ar' => 'هوية المستخدم والاتصال', 'en' => 'User Identity & Contact'], 'body' => ['ar' => 'الاسم الكامل والبريد الإلكتروني المعتمد لتسجيل الدخول والإشعارات.', 'en' => 'Full name and approved email address used for login and notifications.']],
                ['title' => ['ar' => 'الأدوار والنطاقات المعتمدة', 'en' => 'Assigned Roles & Scopes'], 'body' => ['ar' => 'الأدوار القياسية المسندة ونطاق الفروع والمتاجر المصرح بها للمستخدم.', 'en' => 'Assigned canonical roles and permitted branch/store operational scopes.']],
            ],
            'roles' => [
                ['title' => ['ar' => 'كود واسم الدور', 'en' => 'Role Code & Name'], 'body' => ['ar' => 'الرمز القياسي والدور الوظيفي المعتمد في الهيكل التنظيمي.', 'en' => 'Standard code and job role name within the organizational matrix.']],
                ['title' => ['ar' => 'نطاق الصلاحيات المسندة', 'en' => 'Mapped Permission Scope'], 'body' => ['ar' => 'ملخص الوظائف والإجراءات المسموح بها لمستخدمي هذا الدور.', 'en' => 'Summary of functional actions and capabilities granted to role users.']],
            ],
            'permissions' => [
                ['title' => ['ar' => 'مفتاح الصلاحية والوحدة', 'en' => 'Permission Key & Module'], 'body' => ['ar' => 'رمز الصلاحية الخادمي والوحدة التشغيلية التابعة لها.', 'en' => 'Server permission identifier key and parent system module.']],
                ['title' => ['ar' => 'مستوى الحساسية والحماية', 'en' => 'Sensitivity & Guard Level'], 'body' => ['ar' => 'درجة خطورة الصلاحية والسياسة الخادمية المطبقة عليها.', 'en' => 'Risk level of the capability and associated server policy guard.']],
            ],
            'audit' => [
                ['title' => ['ar' => 'بيانات الحدث والمستخدم', 'en' => 'Event & User Info'], 'body' => ['ar' => 'اسم الحدث، اسم المستخدم المنفذ، تاريخ ووقت التنفيذ، ومعرف الطلب.', 'en' => 'Event name, executing user, timestamp, and request correlation ID.']],
                ['title' => ['ar' => 'القيم السابقة والجديدة', 'en' => 'Before & After Values'], 'body' => ['ar' => 'مقارنة دقيقة للتغييرات في الحقول المعدلة دون كشف الأسرار.', 'en' => 'Detailed diff of modified fields without exposing system secrets.']],
            ],
            'health' => [
                ['title' => ['ar' => 'اسم المكون والحالة', 'en' => 'Component Name & Status'], 'body' => ['ar' => 'اسم فحص الجاهزية والحالة الحالية (سليم / متأثر / متوقف).', 'en' => 'Check identifier name and active health state (healthy / degraded / down).']],
                ['title' => ['ar' => 'رسالة التتقرير ومعرف التتبع', 'en' => 'Report Message & Trace ID'], 'body' => ['ar' => 'تفاصيل الفحص الآمن ومعرف الارتباط المرجعي للتتبع.', 'en' => 'Safe diagnostic check message and correlation tracking reference.']],
            ],
            'products' => [
                ['title' => ['ar' => 'كود المنتج والباركود', 'en' => 'Item Code & Barcode'], 'body' => ['ar' => 'الكود الثابت والباركود الرئيسي المستخدم للبحث والمسح.', 'en' => 'Immutable item code and primary barcode used for lookup and scanning.']],
                ['title' => ['ar' => 'الأسماء والتصنيف والعلامة', 'en' => 'Names, Category & Brand'], 'body' => ['ar' => 'الاسم العربي والإنجليزي والتصنيف التابع له والعلامة التجارية.', 'en' => 'Bilingual names, assigned category hierarchy, and product brand.']],
            ],
            'product-detail' => [
                ['title' => ['ar' => 'بيانات الهوية والمستندات', 'en' => 'Identity & Media Details'], 'body' => ['ar' => 'كود المنتج، الأسماء ثنائية اللغة، الباركودات، والصور المحمية.', 'en' => 'Item code, bilingual names, assigned barcodes, and protected media.']],
                ['title' => ['ar' => 'الموردون وسجل التدقيق', 'en' => 'Suppliers & Audit Trail'], 'body' => ['ar' => 'المورد المفضل، التكاليف المحمية حسب الصلاحية، وسجل الأحداث.', 'en' => 'Preferred supplier, scope-protected costs, and audit event timeline.']],
            ],
            'product-form' => [
                ['title' => ['ar' => 'الحقول الأساسية والنوع', 'en' => 'Master Fields & Type'], 'body' => ['ar' => 'الكود الثابت، الأسماء بالعربية والإنجليزية، ونوع المنتج.', 'en' => 'Immutable code, bilingual names, and product composition type.']],
                ['title' => ['ar' => 'التصنيفات والباركود', 'en' => 'Categories & Barcode'], 'body' => ['ar' => 'التصنيف، العلامة التجارية، المورد المفضل، والباركود الفريد.', 'en' => 'Category, brand, preferred supplier, and unique item barcode.']],
            ],
            'product-import' => [
                ['title' => ['ar' => 'ملف الاستيراد ونمط التنفيذ', 'en' => 'Import File & Execution Mode'], 'body' => ['ar' => 'ملف Excel/CSV ونمط المعالجة (إنشاء جديد فقط / تحديث الموجود).', 'en' => 'Spreadsheet file and execution mode (Create Only / Update Existing).']],
                ['title' => ['ar' => 'حالة الدفعة والصفوف', 'en' => 'Batch & Row Validation Status'], 'body' => ['ar' => 'حالة الدفعة، إجمالي الصفوف، الصفوف الصالحة، والصفوف المرفوضة.', 'en' => 'Batch state, total row count, valid rows count, and rejected rows count.']],
            ],
            'categories' => [
                ['title' => ['ar' => 'كود التصنيف والاسم', 'en' => 'Category Code & Name'], 'body' => ['ar' => 'الكود المرجعي والتسمية ثنائية اللغة للتصنيف.', 'en' => 'Reference code and bilingual category names.']],
                ['title' => ['ar' => 'التصنيف الأب والحالة', 'en' => 'Parent Category & Status'], 'body' => ['ar' => 'التصنيف الأب الأعلى في الهيكل وحالة التفعيل التشغيلية.', 'en' => 'Higher-level parent category and active operational status.']],
            ],
            'brands' => [
                ['title' => ['ar' => 'كود العلامة التجارية والاسم', 'en' => 'Brand Code & Name'], 'body' => ['ar' => 'الكود الفريد للعلامة التجارية والاسم ثنائي اللغة.', 'en' => 'Unique brand identifier code and bilingual brand name.']],
                ['title' => ['ar' => 'حالة التفعيل', 'en' => 'Active Status'], 'body' => ['ar' => 'حالة تفعيل العلامة وإتاحيتها للاستخدام في المنتجات.', 'en' => 'Brand operational activation status for catalog assignment.']],
            ],
            'dashboard', 'system-app' => [
                ['title' => ['ar' => 'حقول النظرة العامة', 'en' => 'Overview Fields'], 'body' => ['ar' => 'تعرض ملخصات حالة النظام والمؤشرات المتاحة لنطاقك الحالي.', 'en' => 'Displays system status summaries and indicators available for your current scope.']],
            ],
            default => [
                ['title' => ['ar' => 'حقول الشاشة', 'en' => 'Screen Fields'], 'body' => ['ar' => 'تظهر الحقول المعروضة وفق صلاحيتك وحالة السجل.', 'en' => 'Fields appear according to your permission and record state.']],
            ],
        };

        return array_map(fn (array $field, int $index): array => [
            'key' => 'field-'.($index + 1),
            'title' => $field['title'],
            'body' => $field['body'],
        ], $fields, array_keys($fields));
    }
}
