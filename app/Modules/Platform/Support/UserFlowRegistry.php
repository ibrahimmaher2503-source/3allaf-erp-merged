<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support;

final class UserFlowRegistry
{
    /** @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        $flows = [
            'FLW-UI-01' => self::flow(
                ['ar' => 'تخصيص المظهر', 'en' => 'Customize Interface'],
                ['ar' => 'مستخدم مصادق عليه', 'en' => 'Authenticated user'],
                ['UI-SYS-001'],
                ['appearance.edit'],
                [
                    ['ar' => 'فتح أداة تخصيص المظهر من الشاشات المتاحة.', 'en' => 'Open the appearance customizer from available screens.'],
                    ['ar' => 'اختر المظهر (فاتح/داكن/نظام) واللون المميز وكثافة الجداول.', 'en' => 'Choose appearance (light/dark/system), accent color, and table density.'],
                    ['ar' => 'احفظ التفضيلات للتحقق من استمرارها عبر الجلسات.', 'en' => 'Save preferences and verify persistence across user sessions.'],
                    ['ar' => 'استخدم خيار إعادة الإعدادات الافتراضية عند الحاجة.', 'en' => 'Use reset option to restore system defaults when needed.'],
                ],
                'UI-SYS-001',
            ),
            'FLW-HELP-01' => self::flow(
                ['ar' => 'فتح دليل الصفحة', 'en' => 'Open Contextual Page Guide'],
                ['ar' => 'مستخدم مصادق عليه', 'en' => 'Authenticated user'],
                ['UI-SYS-001', 'UI-CAT-001', 'UI-CAT-004'],
                [],
                [
                    ['ar' => 'افتح أي شاشة مسجلة في النظام.', 'en' => 'Open any registered application screen.'],
                    ['ar' => 'يحدد النظام كود الشاشة والسياق المصرح به تلقائياً.', 'en' => 'System automatically resolves Screen ID and safe context.'],
                    ['ar' => 'افتح درج دليل الصفحة لمراجعة الخطوات والحقول الإرشادية.', 'en' => 'Open Page Guide drawer to review steps and field guidance.'],
                    ['ar' => 'انتقل إلى الدليل الكامل أو تدفق المستخدم المرتبط عند الحاجة.', 'en' => 'Navigate to the full guide or related user flow when needed.'],
                ],
                'FLW-HELP-02',
            ),
            'FLW-HELP-02' => self::flow(
                ['ar' => 'تشغيل الجولة الإرشادية', 'en' => 'Run Guided Tutorial'],
                ['ar' => 'مستخدم مصادق عليه', 'en' => 'Authenticated user'],
                ['UI-SYS-001'],
                [],
                [
                    ['ar' => 'افتح أداة الدليل واضغط بدء الجولة التفاعلية.', 'en' => 'Open the guide tool and click Start Interactive Tour.'],
                    ['ar' => 'يتنقل المشغل بين العناصر المتاحة في الشاشة الحالية.', 'en' => 'Tour steps highlight visible target elements on current screen.'],
                    ['ar' => 'يتم التجاوز الآمن للعناصر غير المتوفرة على الشاشة.', 'en' => 'Missing or unavailable targets are safely skipped.'],
                    ['ar' => 'أنه الجولة أو اغلقها لإعادة تركيز المتصفح إلى مكانه الأصلي.', 'en' => 'Finish or exit tour to restore focus to original element.'],
                ],
                null,
            ),
            'FLW-CAT-01' => self::flow(
                ['ar' => 'إدارة المنتج', 'en' => 'Manage a Product'],
                ['ar' => 'مستخدم الكتالوج المصرح له', 'en' => 'Authorized catalog user'],
                ['UI-CAT-001', 'UI-CAT-002', 'UI-CAT-003'],
                ['products_categories_brands.view'],
                [
                    ['ar' => 'افتح قائمة المنتجات المسجلة.', 'en' => 'Open the registered product list.'],
                    ['ar' => 'ابحث بالباركود أو كود المنتج ضمن نطاق الصلاحيات.', 'en' => 'Search by barcode or item code within authorized scope.'],
                    ['ar' => 'افتح بطاقة التفاصيل أو نموذج الإنشاء والتعديل المصرح به.', 'en' => 'Open product details card or authorized create/edit form.'],
                    ['ar' => 'احفظ التغييرات فقط بعد نجاح التحقق وقواعد المجال.', 'en' => 'Save changes only after validation and domain rules succeed.'],
                ],
                null,
            ),
            'FLW-CAT-02' => self::flow(
                ['ar' => 'استيراد المنتجات المرحلي', 'en' => 'Stage Product Import'],
                ['ar' => 'مستخدم الاستيراد المصرح له', 'en' => 'Authorized import user'],
                ['UI-CAT-004'],
                ['products_categories_brands.create', 'products_categories_brands.approve'],
                [
                    ['ar' => 'ارفع ملف الاستيراد بالقالب المعتمد مع تحديد نمط المعالجة.', 'en' => 'Upload import spreadsheet in approved template with chosen mode.'],
                    ['ar' => 'راجع نتائج مرحلة التحقق والصفوف الصالحة والمرفوضة.', 'en' => 'Review validation staging results, valid rows, and rejects.'],
                    ['ar' => 'صحح الصفوف المرفوضة أو نزّل تقرير الأخطاء قبل الاعتماد.', 'en' => 'Fix rejected rows or download error report before approval.'],
                    ['ar' => 'اعتمد الدفعة عندما تكون جميع الصفوف صالحة فقط.', 'en' => 'Approve batch only when all rows are valid.'],
                ],
                null,
            ),
            'FLW-ADM-01' => self::flow(
                ['ar' => 'إدارة الفروع', 'en' => 'Manage Branches'],
                ['ar' => 'مسؤول النظام المصرح له', 'en' => 'Authorized administrator'],
                ['UI-ADM-003'],
                ['branches_stores.view'],
                [
                    ['ar' => 'مراجعة قائمة الفروع المعتمدة وحالتها.', 'en' => 'Review the approved branch list and status.'],
                    ['ar' => 'إضافة فرع جديد أو تعديل بيانات الفرع عند توفر الصلاحية.', 'en' => 'Create new branch or edit details when authorized.'],
                    ['ar' => 'حفظ التغييرات بعد نجاح التحقق من كود الفرع والتوقيت.', 'en' => 'Save changes after validation of branch code and timezone.'],
                ],
                null,
            ),
            'FLW-ADM-02' => self::flow(
                ['ar' => 'إدارة المتاجر والربط', 'en' => 'Manage Stores and Mapping'],
                ['ar' => 'مسؤول النظام المصرح له', 'en' => 'Authorized administrator'],
                ['UI-ADM-004'],
                ['branches_stores.view'],
                [
                    ['ar' => 'مراجعة قائمة مواقع التخزين المسجلة.', 'en' => 'Review registered stock locations.'],
                    ['ar' => 'تحديد المتجر البيعي الرئيسي المربوط بكل فرع.', 'en' => 'Set primary selling store mapping per branch.'],
                    ['ar' => 'حفظ الربط بعد التحقق من عدم وجود تضارب تشغيلي.', 'en' => 'Save mapping after verifying no operational conflicts exist.'],
                ],
                null,
            ),
            'FLW-ADM-03' => self::flow(
                ['ar' => 'إدارة الأدراج', 'en' => 'Manage Cash Drawers'],
                ['ar' => 'مسؤول النظام المصرح له', 'en' => 'Authorized administrator'],
                ['UI-ADM-005'],
                ['drawers_payments_tax_numbering_printers.view'],
                [
                    ['ar' => 'مراجعة تعينات أدراج النقدية للفرع والمتجر.', 'en' => 'Review drawer assignments per branch and store.'],
                    ['ar' => 'إضافة درج أو تعديل التعيين مع عدم وجود وردية مفتوحة.', 'en' => 'Add drawer or edit assignment ensuring no open shift conflict.'],
                    ['ar' => 'حفظ وتأكيد الحالة المعتمدة للدرج.', 'en' => 'Save and confirm the approved drawer assignment.'],
                ],
                null,
            ),
            'FLW-ADM-04' => self::flow(
                ['ar' => 'مراجعة التفويض', 'en' => 'Review Authorization'],
                ['ar' => 'مسؤول النظام أو المراجع المصرح له', 'en' => 'Authorized administrator or reviewer'],
                ['UI-ADM-010', 'UI-ADM-011', 'UI-ADM-012'],
                ['users_roles_permissions.view'],
                [
                    ['ar' => 'فتح شاشات إدارة المستخدمين والأدوار والصلاحيات.', 'en' => 'Open user, role, and permission administration screens.'],
                    ['ar' => 'مراجعة نطاق الصلاحيات الخادمية ومصفوفة الوصول.', 'en' => 'Review server authority scope and permission matrix.'],
                    ['ar' => 'حفظ التعديلات المصرح بها مع منع قفل المسؤول الأخير.', 'en' => 'Save authorized edits while preventing last-admin lockout.'],
                ],
                null,
            ),
            'FLW-ADM-05' => self::flow(
                ['ar' => 'مراجعة إعدادات الشركة', 'en' => 'Review Company Settings'],
                ['ar' => 'مسؤول النظام أو المراجع المصرح له', 'en' => 'Authorized administrator or reviewer'],
                ['UI-ADM-002'],
                ['company_settings.view'],
                [
                    ['ar' => 'مراجعة إعدادات الهوية والضرائب والترقيم والدفع والطابعات.', 'en' => 'Review company identity, tax, numbering, payment, and printer settings.'],
                    ['ar' => 'تعديل الحقول المسموح بها وفق السياسة التشغيلية.', 'en' => 'Edit permitted fields according to operational policy.'],
                    ['ar' => 'حفظ الإعدادات وتدوين أحداث التغيير في سجل التدقيق.', 'en' => 'Save settings and record change events in audit log.'],
                ],
                null,
            ),
            'FLW-SYS-01' => self::flow(
                ['ar' => 'مراجعة سجل التدقيق', 'en' => 'Review Audit Logs'],
                ['ar' => 'المراجع المصرح له', 'en' => 'Authorized reviewer'],
                ['UI-SYS-003'],
                ['audit_logs.view'],
                [
                    ['ar' => 'تطبيق تصفية السجلات حسب المستخدم أو الحدث أو التاريخ.', 'en' => 'Apply log filters by user, event, or date range.'],
                    ['ar' => 'مراجعة الأحداث المعروضة ومقارنة البيانات قبل التعديل وبعده.', 'en' => 'Review displayed events and compare before/after values.'],
                    ['ar' => 'استخدام معرف التتبع المرجعي لدعم المراجعة.', 'en' => 'Use correlation tracking reference for review support.'],
                ],
                null,
            ),
            'FLW-SYS-02' => self::flow(
                ['ar' => 'فحص صحة النظام', 'en' => 'Check System Health'],
                ['ar' => 'مستخدم الدعم المصرح له', 'en' => 'Authorized support user'],
                ['UI-SYS-004'],
                ['audit_logs.view'],
                [
                    ['ar' => 'فتح شاشة فحوصات صحة النظام المحلية.', 'en' => 'Open local system health checks screen.'],
                    ['ar' => 'تحديث عرض الفحوصات الآمنة دون إفشاء الأسرار.', 'en' => 'Refresh safe health status view without exposing secrets.'],
                    ['ar' => 'رفع معرف التتبع المرجعي في حالة وجود مكون متأثر.', 'en' => 'Escalate correlation reference if a component is degraded.'],
                ],
                null,
            ),
            'FLW-SYS-03' => self::flow(
                ['ar' => 'مراجعة تطبيق النظام', 'en' => 'Review System App'],
                ['ar' => 'مستخدم العمليات المصرح له', 'en' => 'Authorized operations user'],
                ['UI-SYS-002'],
                ['dashboard_reports.view'],
                [
                    ['ar' => 'مراجعة حالة جاهزية تثبيت تطبيق النظام PWA.', 'en' => 'Review system app PWA installation readiness.'],
                    ['ar' => 'متابعة مؤشر الاتصال المباشر بالشبكة.', 'en' => 'Monitor live network connectivity status.'],
                    ['ar' => 'تأكد من تطبيق سياسة التخزين المؤقت المحمي.', 'en' => 'Ensure enforcement of protected cache policy.'],
                ],
                null,
            ),
        ];

        foreach ($flows as $flowId => &$flow) {
            $flow['flow_id'] = $flowId;
        }
        unset($flow);

        return $flows;
    }

    public static function find(string $flowId): ?array
    {
        return self::all()[$flowId] ?? null;
    }

    private static function flow(array|string $title, array|string $actor, array $screens, array $permissions, array $steps, ?string $next): array
    {
        $titleArray = is_array($title) ? $title : ['ar' => $title, 'en' => $title];
        $actorArray = is_array($actor) ? $actor : ['ar' => $actor, 'en' => $actor];

        return [
            'flow_id' => '',
            'title' => $titleArray,
            'actor' => $actorArray,
            'preconditions' => [
                'ar' => 'المستخدم مصادق عليه والشاشة متاحة ضمن نطاقه.',
                'en' => 'The user is authenticated and the screen is available within scope.',
            ],
            'trigger' => [
                'ar' => 'فتح الشاشة المسجلة.',
                'en' => 'Open the registered screen.',
            ],
            'steps' => array_map(function (array|string $body, int $index): array {
                $bodyArray = is_array($body) ? $body : ['ar' => $body, 'en' => $body];
                return [
                    'number' => $index + 1,
                    'body' => $bodyArray,
                ];
            }, $steps, array_keys($steps)),
            'alternate_paths' => [
                'ar' => 'قد تختلف الخطوات المتاحة حسب الصلاحيات وحالة السجل.',
                'en' => 'Available steps can differ by permission and record state.',
            ],
            'failure_paths' => [
                'ar' => 'اعرض رسالة التحقق أو المنع، ثم صحح السبب أو اطلب المساعدة.',
                'en' => 'Read the validation or denial message, then correct the cause or request help.',
            ],
            'required_permissions' => $permissions,
            'audit_expectations' => [
                'ar' => 'الإجراءات المؤثرة تسجل وفق سياسة التدقيق الحالية.',
                'en' => 'Mutating actions are audited according to the current audit policy.',
            ],
            'source_screen_ids' => $screens,
            'destination_screen_ids' => $screens,
            'completion_condition' => [
                'ar' => 'اكتملت الخطوة المنشورة وظهرت نتيجتها الآمنة.',
                'en' => 'The published step completed and its safe result is visible.',
            ],
            'next_recommended_flow' => $next,
        ];
    }
}
