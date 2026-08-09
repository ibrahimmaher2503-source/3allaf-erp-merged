<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Customer\Actions\SaveCustomerPolicySettingAction;
use App\Modules\Customer\Models\CustomerPolicySettingVersion;
use App\Modules\Customer\Models\PartyWalletLedger;
use App\Modules\Customer\Models\ProductWalletLedger;
use App\Modules\Customer\Support\CustomerPolicySettingRegistry;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Models\TaxSetting;
use App\Modules\Platform\Actions\DeliverAttachment;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Actions\StoreAttachment;
use App\Modules\Platform\Data\AttachmentSourceReference;
use App\Modules\Platform\Models\Attachment;
use App\Modules\Pricing\Services\EffectivePriceResolver;
use App\Modules\Pricing\Services\OpenPricePolicy;
use App\Modules\Retail\Actions\SavePosFinancialSettingAction;
use App\Modules\Retail\Actions\OpenShiftAction;
use App\Modules\Retail\Actions\RecordCashMovementAction;
use App\Modules\Retail\Actions\RetailSaleAction;
use App\Modules\Retail\Actions\ReviewShiftVarianceAction;
use App\Modules\Retail\Actions\SubmitBlindShiftCloseAction;
use App\Modules\Retail\Enums\ShiftState;
use App\Modules\Retail\Models\CashMovement;
use App\Modules\Retail\Models\PosShift;
use App\Modules\Retail\Models\Sale;
use App\Modules\Retail\Models\SalePayment;
use App\Modules\Retail\Models\PosFinancialSettingVersion;
use App\Modules\Retail\Services\DiscountPolicy;
use App\Modules\Retail\Services\PosCalculationService;
use App\Modules\Retail\Support\DecimalMoney;
use App\Modules\Retail\Support\PosFinancialSettingRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('pos/returns-readiness', function (Request $request) {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user->can('returns_exchanges_gift_instruments.view'), 403);

        return view('pages.returns.readiness', [
            'title' => __('Returns and Exchanges Readiness'),
            'description' => __('Review source, condition, approval, settlement, and print prerequisites without creating a return, refund, exchange, or stock movement.'),
            'items' => [
                ['title' => __('Source reference'), 'body' => __('Original invoice or price-free Gift Receipt requirement and no-reference exception policy remain PENDING.')],
                ['title' => __('Eligibility window'), 'body' => __('Return window, quantity, duplicate/excess checks, and out-of-window exception rules remain PENDING.')],
                ['title' => __('Condition and disposition'), 'body' => __('Sellable, non-saleable, damaged, and manager-review outcomes remain PENDING; no stock movement is posted.')],
                ['title' => __('Approval and settlement'), 'body' => __('Approval/SoD, cash/original-method refund, Gift Card settlement, and exchange difference rules remain PENDING.')],
                ['title' => __('Audit and print'), 'body' => __('Immutable source history, numbering, privacy, evidence, and return/exchange output format remain PENDING.')],
            ],
        ]);
    })->name('returns.readiness');

    Route::get('party/readiness', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('party_bookings_invoices.view'), 403);

        return view('pages.party.readiness', [
            'title' => __('Party Booking and Working Invoice Readiness'),
            'description' => __('Review party-only stores, services, schedule, privacy, cancellation, pricing, and final-close prerequisites without creating a booking, customer, child, invoice, payment, or final receipt.'),
            'items' => [
                ['title' => __('Party store scope'), 'body' => __('Party stores and operational context remain separate from retail products and supplier returns.')],
                ['title' => __('Services and packages'), 'body' => __('Service/package catalog, rental assets, consumables, and planned lines remain owner-configurable.')],
                ['title' => __('Schedule and location'), 'body' => __('Date/time/location, timezone, conflict, reschedule, and contact rules remain PENDING.')],
                ['title' => __('Customer, child, and privacy'), 'body' => __('Required contact, child, consent, privacy, and notes fields remain PENDING; no record is created.')],
                ['title' => __('Cancellation and responsibility'), 'body' => __('Cancellation, reschedule, assigned responsibility, and operational ownership rules remain PENDING.')],
                ['title' => __('Working invoice and final close'), 'body' => __('Editable-before-close, immutable-after-close, pricing, deposit, payment-on-account, and checklist rules remain PENDING.')],
            ],
        ]);
    })->middleware('can:party_bookings_invoices.view')->name('party.readiness');

    Route::get('reports-readiness', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('dashboard_reports.view'), 403);

        return view('pages.reports.readiness', [
            'title' => __('Dashboards and Reconciled Reports Readiness'),
            'cards' => [
                ['title' => __('Source lineage and scope'), 'body' => __('Every figure must identify its source, snapshot, branch/store/user scope, and authorization before rendering.')],
                ['title' => __('Filters and KPI formulas'), 'body' => __('Date, comparison, status, activity, and domain filters plus KPI formulas remain pending; no aggregation runs here.')],
                ['title' => __('Reconciliation and freshness'), 'body' => __('Precision, currency, historical snapshots, freshness, and cache rules require approved source contracts.')],
                ['title' => __('Operational alerts'), 'body' => __('Triggers, severity, owner, due time, acknowledgement, resolution, source link, and deduplication remain pending.')],
                ['title' => __('Pagination and drilldown'), 'body' => __('Detail views must be bounded, indexed, scope-filtered, and separately authorized before drilldown exists.')],
                ['title' => __('Export boundary'), 'body' => __('PDF/Excel/export artifacts require permission, bounded scope, audit, and approved source values; none are generated.')],
            ],
        ]);
    })->middleware('can:dashboard_reports.view')->name('reports.readiness');

    Route::get('alerts-readiness', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('dashboard_reports.view'), 403);

        return view('pages.alerts.readiness', [
            'title' => __('Operational Alerts and Exception Queue Readiness'),
            'cards' => [
                ['title' => __('Alert triggers and source eligibility'), 'body' => __('Low/zero stock, unpriced, price approval, transfer, count, invoice, shift, party, balance, asset, and job triggers require approved source contracts; no alert is evaluated.')],
                ['title' => __('Severity and owner role'), 'body' => __('Severity, escalation, due time, and responsible owner role remain PENDING; no priority or assignment is applied.')],
                ['title' => __('Scope and safe navigation'), 'body' => __('Branch, store, role, user, source link, and detail authorization remain PENDING; no cross-scope navigation is exposed.')],
                ['title' => __('Acknowledgement and resolution'), 'body' => __('Acknowledged, resolved, dismissed, reopened, and immutable history states remain PENDING; no state mutation is enabled.')],
                ['title' => __('Suppression and deduplication'), 'body' => __('Duplicate, stale, suppression, retry, and source-missing behavior remain PENDING; no duplicate alert is created.')],
                ['title' => __('Notification and exception queue'), 'body' => __('In-app/email delivery, pagination, filters, empty/error states, and role-safe queue navigation remain PENDING; no notification is sent.')],
            ],
        ]);
    })->middleware('can:dashboard_reports.view')->name('alerts.readiness');

    Route::get('exports-audit-readiness', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('dashboard_reports.view'), 403);

        return view('pages.exports.audit-readiness', [
            'title' => __('Export Center and Audit Views Readiness'),
            'cards' => [
                ['title' => __('Formats and templates'), 'body' => __('PDF, Excel, CSV eligibility, localized headers, templates, page breaks, and output dimensions remain PENDING; no artifact is generated.')],
                ['title' => __('Limits and queueing'), 'body' => __('Row, file-size, timeout, pagination, queue, retry, and failed-job limits remain PENDING; no export job is queued.')],
                ['title' => __('Retention and protected storage'), 'body' => __('Expiry, deletion, private storage, short-lived links, and artifact ownership remain PENDING; no file is stored or offered.')],
                ['title' => __('Redaction and formula safety'), 'body' => __('Sensitive fields, costs, margins, payments, customer data, and spreadsheet formula injection rules remain PENDING.')],
                ['title' => __('Reauthorization and audit export'), 'body' => __('Generation/download permissions, scope, actor, filters, outcome, correlation ID, and export audit events remain PENDING.')],
                ['title' => __('Audit filters and immutable history'), 'body' => __('Date, actor, category, source, branch/store, action, result, reason, pagination, redaction, and append-only history remain PENDING.')],
            ],
        ]);
    })->middleware('can:dashboard_reports.view')->name('exports.audit.readiness');

    Route::get('master-data-migration-readiness', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('company_settings.view'), 403);

        return view('pages.master-data.migration-readiness', [
            'title' => __('Master Data Import and Cutover Readiness'),
            'cards' => [
                ['title' => __('Approved source artifacts'), 'body' => __('Signed owner-approved lists or files are required for company, branches, stores, users, catalog, suppliers, prices, and opening stock; no production source is inferred.')],
                ['title' => __('Dependency load order'), 'body' => __('Company, branches, stores, selling mappings, drawers, users/scopes, categories, brands, products, barcodes, suppliers, prices, and opening stock must follow the documented dependency order.')],
                ['title' => __('Create-only staging'), 'body' => __('Migration imports remain create-only. Update mode, destructive replacement, and automatic merge are disabled until a deliberate correction policy exists.')],
                ['title' => __('File safety and private storage'), 'body' => __('Private storage, MIME and extension checks, formula/macro rejection, filename safety, and approved size/type limits remain pending.')],
                ['title' => __('Duplicate and error disposition'), 'body' => __('Duplicate codes, barcodes, phones, and source references require an approved disposition; invalid rows must remain isolated and visible.')],
                ['title' => __('Stage validation and preview'), 'body' => __('Upload, parse, row validation, referential checks, preview, and error-file behavior remain readiness-only; no batch is persisted here.')],
                ['title' => __('Reconciliation gates'), 'body' => __('Completeness, referential integrity, uniqueness, and sampled field-by-field checks must pass before any next stage or opening-stock action.')],
                ['title' => __('Maker/checker and audit'), 'body' => __('Importer and approver separation, scoped approval, immutable evidence, and audit records remain pending.')],
                ['title' => __('Backup, cutover, and rollback'), 'body' => __('Freeze timestamp, backup/restore proof, opening-stock approval, backdating block, rollback, and post-cutover correction rules remain pending.')],
            ],
        ]);
    })->middleware('can:company_settings.view')->name('master-data.migration.readiness');

    Route::get('operations-readiness', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('audit_logs.view'), 403);

        return view('pages.operations.readiness', [
            'title' => __('Production Operations and Handover Readiness'),
            'cards' => [
                ['title' => __('Runtime and environment'), 'body' => __('Host, domain, TLS, runtime versions, debug mode, environment separation, and named ownership remain pending; local behavior is not production proof.')],
                ['title' => __('Secrets and access'), 'body' => __('Secret storage, rotation, least privilege, redaction, service accounts, and access review remain pending; no secret is displayed here.')],
                ['title' => __('Workers, scheduler, and cache'), 'body' => __('Worker supervision, scheduler heartbeat, retries, failed jobs, queue idempotency, and cache availability remain pending.')],
                ['title' => __('Storage and monitoring'), 'body' => __('Private attachment storage, capacity, retention, request IDs, error monitoring, alert recipients, and escalation remain pending.')],
                ['title' => __('Backup and restore evidence'), 'body' => __('Destination, encryption, retention, restore rehearsal in isolation, RPO/RTO, recovery owner, and reconciliation evidence remain pending; no backup is created.')],
                ['title' => __('Printers, scanners, and devices'), 'body' => __('Managed device enrollment, branch/store binding, printer/scanner models, bridges, templates, safe failure, and browser evidence remain pending.')],
                ['title' => __('Support and handover'), 'body' => __('Named support contact, escalation path, incident ownership, known issues, runbooks, and handover evidence remain pending.')],
                ['title' => __('Training and release gate'), 'body' => __('Training attendees, role-specific scenarios, completion evidence, UAT, security review, rollback, and go-live sign-off remain pending.')],
            ],
        ]);
    })->middleware('can:audit_logs.view')->name('operations.readiness');

    Route::get('uat-readiness', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('audit_logs.view'), 403);

        $isArabic = app()->getLocale() === 'ar';
        $cards = [
            ['title' => $isArabic ? 'حزمة السيناريوهات والتغطية' : 'Scenario pack and coverage', 'body' => $isArabic ? 'معرّفات السيناريوهات والمتطلبات والشروط والنتائج المتوقعة تحتاج اعتمادًا.' : 'Scenario IDs, requirements, prerequisites, and expected results need approval.'],
            ['title' => $isArabic ? 'المالكون والأدوار' : 'Owners and roles', 'body' => $isArabic ? 'مالكو الأعمال والتقنية والعيوب والقبول والتوقيع النهائي غير محددين.' : 'Business, technical, defect, acceptance, and final sign-off owners are not named.'],
            ['title' => $isArabic ? 'البيانات والأجهزة' : 'Data and devices', 'body' => $isArabic ? 'بيانات الاختبار والطابعات والماسحات والأجهزة والبيئة المعتمدة غير متاحة.' : 'Approved test data, printers, scanners, devices, and environments are unavailable.'],
            ['title' => $isArabic ? 'قاعدة الأدلة' : 'Evidence repository', 'body' => $isArabic ? 'المكان المحمي وسجلات Run ID واللقطات والمخرجات والمراجع غير معتمد.' : 'Protected location, Run IDs, screenshots, outputs, and source references are not approved.'],
            ['title' => $isArabic ? 'العيوب وإعادة الاختبار' : 'Defects and retesting', 'body' => $isArabic ? 'التصنيف والمالك وإثبات الإصلاح وإعادة اختبار الانحدار غير مسجلة.' : 'Severity, owner, fix evidence, and regression retest are not recorded.'],
            ['title' => $isArabic ? 'المطابقة' : 'Reconciliation', 'body' => $isArabic ? 'مطابقة المخزون والمدفوعات والمحافظ والتقارير والتدقيق غير منفذة.' : 'Stock, payments, wallets, reports, and audit reconciliation is not executed.'],
            ['title' => $isArabic ? 'القبول الكتابي' : 'Written acceptance', 'body' => $isArabic ? 'لا يوجد إغلاق للعيوب الحرجة أو توقيع UAT أو تفويض قبول.' : 'No critical-defect closure, UAT sign-off, or acceptance authorization exists.'],
        ];

        return view('pages.uat.readiness', ['title' => $isArabic ? 'جاهزية الاختبار اليدوي وقاعدة الأدلة' : 'Manual UAT and Evidence Readiness', 'cards' => $cards]);
    })->name('uat.readiness');

    Route::get('release-readiness', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('audit_logs.view'), 403);

        $isArabic = app()->getLocale() === 'ar';
        $cards = [
            ['title' => $isArabic ? 'نسخة الإصدار والتجميد' : 'Release version and freeze', 'body' => $isArabic ? 'رقم الإصدار ونقطة التجميد وخطة التراجع غير معتمدة.' : 'Release ID, freeze point, and rollback plan are not approved.'],
            ['title' => $isArabic ? 'بيانات القطع' : 'Cutover data', 'body' => $isArabic ? 'البيانات النهائية والمطابقة ومالك القطع غير محددة.' : 'Final data, reconciliation, and cutover owner are not defined.'],
            ['title' => $isArabic ? 'النسخ الاحتياطي والاستعادة' : 'Backup and restore', 'body' => $isArabic ? 'سجل النسخ والاستعادة المعزولة وRPO/RTO غير متاح.' : 'Backup record, isolated restore, and RPO/RTO evidence are unavailable.'],
            ['title' => $isArabic ? 'المراقبة والتنبيه' : 'Monitoring and alerts', 'body' => $isArabic ? 'المراقبة وقنوات التصعيد وملكية الدعم غير مفعلة.' : 'Monitoring, escalation channels, and support ownership are not activated.'],
            ['title' => $isArabic ? 'الأجهزة والمستخدمون' : 'Devices and users', 'body' => $isArabic ? 'المستخدمون والأجهزة والطابعات والإعدادات النهائية غير معتمدة.' : 'Final users, devices, printers, and settings are not approved.'],
            ['title' => $isArabic ? 'التدريب والدعم' : 'Training and support', 'body' => $isArabic ? 'الحضور والـrunbook ومسار الحوادث والإحالة غير موثقة.' : 'Attendance, runbook, incident path, and handover are not documented.'],
            ['title' => $isArabic ? 'الموافقة على الإطلاق' : 'Go-live approval', 'body' => $isArabic ? 'لا توجد موافقة عميل أو تفويض إطلاق أو قبول إنتاجي.' : 'No client approval, launch authorization, or production acceptance exists.'],
        ];

        return view('pages.release.readiness', ['title' => $isArabic ? 'جاهزية القطع والإطلاق والإحالة' : 'Controlled Go-Live and Handover Readiness', 'cards' => $cards]);
    })->name('release.readiness');

    Route::get('quotations-readiness', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('dashboard_reports.view'), 403);

        return view('pages.quotations.readiness', [
            'title' => __('Quotations and Proposals Readiness'),
            'description' => __('Review typed retail/party quotation fields, customer, validity, status, prices, terms, approval, numbering, print/share, and future conversion boundaries without creating a quotation or operational/financial effect.'),
            'items' => [
                ['title' => __('Typed activity and customer'), 'body' => __('Retail and party activity types remain separate; customer/source linkage and mixed-line blocking remain PENDING.')],
                ['title' => __('Validity, expiry, and status'), 'body' => __('Draft, issued, expired, cancelled, superseded, validity, and closure rules remain PENDING; no status changes are enabled.')],
                ['title' => __('Prices and terms'), 'body' => __('Price source/snapshot, terms, notes, conditions, and owner-configurable wording remain PENDING; no price is approved or rendered.')],
                ['title' => __('Approval, audit, and numbering'), 'body' => __('Approval separation, reasons, idempotency, audit, unique identity, and document sequence rules remain PENDING; no number is allocated.')],
                ['title' => __('Print and share boundary'), 'body' => __('Privacy, print, and share output rules remain PENDING; no output or attachment is generated.')],
                ['title' => __('Future conversion exclusion'), 'body' => __('A quotation may retain a future source reference only; Phase 1 conversion to sale, party invoice, inventory, wallet, payment, or financial effect is blocked.')],
            ],
        ]);
    })->middleware('can:dashboard_reports.view')->name('quotations.readiness');

    Route::get('party/final-close-readiness', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('party_bookings_invoices.view'), 403);

        return view('pages.party.final-close-readiness', [
            'title' => __('Party Final Close and Settlement Readiness'),
            'description' => __('Review booking, operating, return, payment, Party Wallet, invoice freeze, credit, receipt, approval, numbering, and close prerequisites without creating a final invoice, receipt, wallet entry, or settlement.'),
            'items' => [
                ['title' => __('Final readiness checklist'), 'body' => __('Booking, operating order, consumables, rental return/inspection, payment, and outstanding-operation checks remain PENDING; no close is enabled.')],
                ['title' => __('Working invoice and freeze'), 'body' => __('Editable-before-close, immutable-after-close, controlled corrections, and no mixed retail lines remain PENDING; no invoice is frozen.')],
                ['title' => __('Payment reconciliation and residual'), 'body' => __('Multiple payments on account, evidence, duplicates, residual, underpayment, overpayment, and reconciliation rules remain PENDING; no amount is calculated.')],
                ['title' => __('Party Wallet and credit separation'), 'body' => __('Party Wallet-only settlement, Product Wallet exclusion, credit enablement, and explicit source linkage remain PENDING; no wallet entry is created.')],
                ['title' => __('Final invoice and receipt'), 'body' => __('Immutable final invoice, exact receipt wording, privacy, numbering, reprint, and correction references remain PENDING; no document is generated.')],
                ['title' => __('Approval, idempotency, audit, and print'), 'body' => __('Close approval/SoD, double-close prevention, retry/concurrency, audit, document sequence, and print rules remain PENDING.')],
            ],
        ]);
    })->middleware('can:party_bookings_invoices.view')->name('party.final-close.readiness');

    Route::get('party/asset-events-readiness', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('party_bookings_invoices.view'), 403);

        return view('pages.party.asset-events-readiness', [
            'title' => __('Asset Damage, Loss, Maintenance and Depreciation Readiness'),
            'description' => __('Review source-linked damage, loss, maintenance, assessment, responsibility, evidence, cost privacy, approval, depreciation, and correction prerequisites without creating an event or changing an asset state.'),
            'items' => [
                ['title' => __('Damage and loss event'), 'body' => __('Asset, party/source, reason, assessment, responsible user, final status, and evidence rules remain PENDING; no event is created.')],
                ['title' => __('Maintenance lifecycle'), 'body' => __('Maintenance reason, owner, inspection, release, final state, and evidence rules remain PENDING; no maintenance event is recorded.')],
                ['title' => __('Assessment and responsibility'), 'body' => __('Owner-configurable checklist, assessment method, party/source, actor, reviewer, and scope rules remain PENDING.')],
                ['title' => __('Evidence and privacy'), 'body' => __('Attachment purpose, source reference, access, retention, privacy, and cost visibility rules remain PENDING; no file is uploaded.')],
                ['title' => __('Approval and cost boundary'), 'body' => __('Optional cost impact, approval limits, SoD, and finance separation remain PENDING; no amount is calculated or posted.')],
                ['title' => __('Depreciation and correction'), 'body' => __('Operational-only depreciation method/amount, immutable history, and referenced correction rules remain PENDING; no ledger posting occurs.')],
            ],
        ]);
    })->middleware('can:party_bookings_invoices.view')->name('party.asset-events.readiness');

    Route::get('party/assets-readiness', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('party_bookings_invoices.view'), 403);

        return view('pages.party.assets-readiness', [
            'title' => __('Rental Assets and Calendar Readiness'),
            'description' => __('Review unique asset identity, consumable separation, availability states, reservations, checkout, return, condition, approval, audit, and print prerequisites without creating an asset or calendar allocation.'),
            'items' => [
                ['title' => __('Rental asset identity'), 'body' => __('Unique code, name, category, location, status, condition, and immutable history remain PENDING; no asset is created.')],
                ['title' => __('Asset and consumable separation'), 'body' => __('Unique rental assets remain separate from consumables and retail products; no mixed resource is created.')],
                ['title' => __('Availability and lifecycle states'), 'body' => __('Available, reserved, checked out, inspection, damaged, maintenance, retired, and lost states remain PENDING.')],
                ['title' => __('Reservation interval and concurrency'), 'body' => __('Party source, timezone, buffer, overlap lock, retry, cancellation, reschedule, and conflict rules remain PENDING; no reservation is created.')],
                ['title' => __('Checkout, return, and condition'), 'body' => __('Pre/post condition, location, inspector, responsible user, missing/damaged status, and evidence rules remain PENDING.')],
                ['title' => __('Approval, audit, cost privacy, and print'), 'body' => __('State-transition authorization, immutable history, cost redaction, calendar, reservation, checkout, return, and print rules remain PENDING.')],
            ],
        ]);
    })->middleware('can:party_bookings_invoices.view')->name('party.assets.readiness');

    Route::get('party/operating-readiness', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('party_bookings_invoices.view'), 403);

        return view('pages.party.operating-readiness', [
            'title' => __('Party Operating Orders and Consumables Readiness'),
            'description' => __('Review Party-only operating-order, party-store, consumable, issue, return, reconciliation, approval, audit, and print prerequisites without creating stock or operating movements.'),
            'items' => [
                ['title' => __('Operating-order lifecycle'), 'body' => __('Draft, release, execute, complete, immutable history, and source-link rules remain PENDING; no order is created.')],
                ['title' => __('Party store and resource scope'), 'body' => __('Party-only store, services, rental resources, responsibilities, and source scope remain PENDING; no stock is reserved.')],
                ['title' => __('Consumables and UOM'), 'body' => __('Catalog, unit, fraction, availability, and party-store mapping rules remain PENDING; no quantity is rendered.')],
                ['title' => __('Issue and actual consumption'), 'body' => __('Issue, actual, controlled additions/removals, operator evidence, and completion rules remain PENDING; no issue is posted.')],
                ['title' => __('Unused return movement'), 'body' => __('Eligible unused return, referenced movement, condition, approval, and excess handling remain PENDING; no return is posted.')],
                ['title' => __('Reconciliation, approval, audit, and print'), 'body' => __('Source/balance reconciliation, concurrency, SoD, idempotency, immutable history, privacy, and print rules remain PENDING.')],
            ],
        ]);
    })->middleware('can:party_bookings_invoices.view')->name('party.operating.readiness');

    Route::get('party/payments-readiness', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('party_bookings_invoices.view'), 403);

        return view('pages.party.payments-readiness', [
            'title' => __('Party Payments and Balance Readiness'),
            'description' => __('Review Party-only payment methods, deposits, evidence, idempotency, receipt wording, balance visibility, and Party Wallet boundaries without posting money or creating a receipt.'),
            'items' => [
                ['title' => __('Party payment methods'), 'body' => __('Allowed source, method, actor, and scope rules remain PENDING; no payment is posted.')],
                ['title' => __('Deposit and payment on account'), 'body' => __('Multiple/partial payment, deposit, source invoice, and exact receipt-label rules remain PENDING.')],
                ['title' => __('Evidence and privacy'), 'body' => __('Evidence, attachment, source reference, privacy, and retention rules remain PENDING; no file is uploaded.')],
                ['title' => __('Idempotency and reversal'), 'body' => __('Duplicate, retry, concurrent, cancellation, reversal, and audit rules remain PENDING.')],
                ['title' => __('Overpayment and balance'), 'body' => __('Underpayment, overpayment, residual, credit, and source-linked balance rules remain PENDING; no amount is calculated.')],
                ['title' => __('Receipt and Party Wallet settlement'), 'body' => __('Numbering/reprint, approval/SoD, Party Wallet-only settlement, and Product Wallet exclusion remain PENDING.')],
            ],
        ]);
    })->middleware('can:party_bookings_invoices.view')->name('party.payments.readiness');

    Route::get('gift-receipts', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('returns_exchanges_gift_instruments.view'), 403);

        return view('pages.gift-instruments.readiness', [
            'kind' => 'gift-receipts',
            'title' => __('Gift Receipts'),
            'description' => __('Price-free source-reference readiness; issue, validate, reprint, and use remain disabled.'),
            'boundary' => __('Gift Receipt policy values are configurable from Settings, but no source sale lines or receipt references are loaded until eligibility, privacy, numbering, and format are approved.'),
            'items' => [
                ['title' => __('Source eligibility'), 'detail' => __('Eligible approved-sale lines, return context, and source linkage remain PENDING.')],
                ['title' => __('Price-free output'), 'detail' => __('A Gift Receipt must exclude unit price, discount, tax, total, and any price-inference field.')],
                ['title' => __('Reprint and privacy'), 'detail' => __('Reprint reason, authorization, privacy scope, and immutable history remain PENDING.')],
                ['title' => __('Format and numbering'), 'detail' => __('Reference format, numbering, and print template remain PENDING; no artifact is generated.')],
            ],
            'emptyTitle' => __('No Gift Receipt references yet'),
            'emptyBody' => __('The empty state is intentional. Add owner-approved policy values first; do not create a Gift Receipt from this screen.'),
        ]);
    })->middleware('can:returns_exchanges_gift_instruments.view')->name('gift.receipts');

    Route::get('gift-cards', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('returns_exchanges_gift_instruments.view'), 403);

        return view('pages.gift-instruments.readiness', [
            'kind' => 'gift-cards',
            'title' => __('Gift Cards'),
            'description' => __('Gift Card ledger readiness; issue, balance, redeem, void, and expiry remain disabled.'),
            'boundary' => __('Gift Card policy values are configurable from Settings, but no identifier, balance, holder, payment, ledger entry, or redemption is loaded until source and validity policies are approved.'),
            'items' => [
                ['title' => __('Unique identifier'), 'detail' => __('Identifier format, uniqueness, concurrency, and source reference remain PENDING.')],
                ['title' => __('Validity and expiry'), 'detail' => __('Validity period and expired-use blocking remain PENDING; no card is active.')],
                ['title' => __('Holder and privacy'), 'detail' => __('Holder/reference purpose and role-safe visibility remain PENDING; no holder data is loaded.')],
                ['title' => __('Redemption and void'), 'detail' => __('Partial/full use, overuse, void reason, approval, and immutable ledger rules remain PENDING.')],
            ],
            'emptyTitle' => __('No Gift Cards yet'),
            'emptyBody' => __('The empty state is intentional. Add owner-approved policy values first; do not issue or redeem a Gift Card from this screen.'),
        ]);
    })->middleware('can:returns_exchanges_gift_instruments.view')->name('gift.cards');

    Route::get('wallets/product', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('product_wallet.view'), 403);

        return view('pages.wallets.ledger', [
            'title' => app()->getLocale() === 'ar' ? 'محفظة المنتجات' : 'Product Wallet',
            'description' => app()->getLocale() === 'ar' ? 'سجل مستقل للقيود المرتبطة بالمنتج، مع إبقاء المصدر والسياسة والصلاحية خارج نطاق هذه الشريحة.' : 'A separate ledger for product-linked entries while source, policy, and authorization remain outside this slice.',
            'ledgerTable' => 'product_wallet_ledger',
            'entries' => ProductWalletLedger::query()->latestFirst()->paginate(20),
            'otherRoute' => 'wallets.party',
            'otherPermission' => 'party_wallet.view',
            'otherLabel' => app()->getLocale() === 'ar' ? 'فتح Party Wallet' : 'Open Party Wallet',
            'guidePrefix' => 'product-wallet',
        ]);
    })->middleware('can:product_wallet.view')->name('wallets.product');

    Route::get('wallets/party', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('party_wallet.view'), 403);

        return view('pages.wallets.ledger', [
            'title' => app()->getLocale() === 'ar' ? 'محفظة الأطراف' : 'Party Wallet',
            'description' => app()->getLocale() === 'ar' ? 'سجل مستقل للقيود المرتبطة بالطرف، مع إبقاء المصدر والسياسة والصلاحية خارج نطاق هذه الشريحة.' : 'A separate ledger for party-linked entries while source, policy, and authorization remain outside this slice.',
            'ledgerTable' => 'party_wallet_ledger',
            'entries' => PartyWalletLedger::query()->latestFirst()->paginate(20),
            'otherRoute' => 'wallets.product',
            'otherPermission' => 'product_wallet.view',
            'otherLabel' => app()->getLocale() === 'ar' ? 'فتح Product Wallet' : 'Open Product Wallet',
            'guidePrefix' => 'party-wallet',
        ]);
    })->middleware('can:party_wallet.view')->name('wallets.party');

    Route::get('pos', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('pos_sales.view'), 403);
        $store = Store::query()->visibleTo($user)->where('type', 'selling')->where('status', 'active')->with('company')->orderBy('id')->first();
        $shift = $store !== null ? PosShift::query()->open()->where('store_id', $store->id)->where('cashier_id', $user->id)->with('cashDrawer')->latest('opened_at')->first() : null;
        /** @var array<int, array{product_id: int, quantity: numeric-string}> $sessionCart */
        $sessionCart = $request->session()->get('pos.cart', []);
        $cart = collect($sessionCart);
        $productIds = $cart->pluck('product_id')->map(fn ($id): int => (int) $id)->all();
        $cartProducts = Product::query()->whereIn('id', $productIds)->get()->keyBy('id');
        $availableProducts = Product::query()->active()->orderBy('item_code')->limit(24)->get();
        $priceMap = $store === null ? collect() : $availableProducts->mapWithKeys(fn (Product $product): array => [$product->id => app(EffectivePriceResolver::class)->resolve($product->id, $store->id)]);
        $suspendedCount = Sale::query()->visibleTo($user)->where('status', 'suspended')->count();
        $paymentMethods = PaymentMethod::query()->where('status', 'active')->orderBy('code')->get();
        $cashMethod = $paymentMethods->first(fn (PaymentMethod $method): bool => $method->isCash());
        $electronicMethods = $paymentMethods->reject(fn (PaymentMethod $method): bool => $cashMethod !== null && $method->is($cashMethod))->values();

        $previewLines = [];
        foreach ($cart as $cartLine) {
            $product = $cartProducts->get((int) $cartLine['product_id']);
            $price = $product && $store ? app(EffectivePriceResolver::class)->resolve($product->id, $store->id) : null;
            if ($product && $price) {
                $previewLines[] = [
                    'product' => $product,
                    'quantity' => (string) $cartLine['quantity'],
                    'unit_price' => (string) ($cartLine['open_price_amount'] ?? $price->amount),
                    'discount_amount' => (string) ($cartLine['discount_amount'] ?? '0.00'),
                    'price' => $price,
                    'cart' => $cartLine,
                ];
            }
        }
        $taxApplicable = (bool) $request->session()->get('pos.tax_applicable', false);
        $effectiveTaxes = TaxSetting::query()
            ->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('effective_from')->orWhere('effective_from', '<=', now()))
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>', now()))
            ->get();
        $taxSetting = $effectiveTaxes->count() === 1 && filled($effectiveTaxes->first()?->rate) ? $effectiveTaxes->first() : null;
        $previewError = null;
        try {
            $preview = $previewLines === [] ? null : app(PosCalculationService::class)->calculate(
                array_map(static fn (array $line): array => [
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'discount_amount' => $line['discount_amount'],
                ], $previewLines),
                '0.00',
                $taxApplicable
                    ? ['applicable' => true, 'rate' => $taxSetting?->rate, 'inclusive' => (bool) ($taxSetting?->is_tax_inclusive ?? false)]
                    : ['applicable' => false],
            );
        } catch (InvalidArgumentException $exception) {
            $preview = null;
            $previewError = $exception->getMessage();
        }

        $checkoutToken = $request->session()->get('pos.checkout_token');
        if (! is_string($checkoutToken) || $checkoutToken === '') {
            $checkoutToken = (string) Str::uuid();
            $request->session()->put('pos.checkout_token', $checkoutToken);
        }

        $cashDenomination = PosFinancialSettingRegistry::numericValue(PosFinancialSettingRegistry::CASH_ROUNDING_DENOMINATION);

        return view('pages.pos.index', compact(
            'store', 'shift', 'cart', 'cartProducts', 'availableProducts', 'priceMap', 'suspendedCount',
            'paymentMethods', 'cashMethod', 'electronicMethods', 'previewLines', 'preview', 'previewError', 'checkoutToken',
            'cashDenomination', 'taxApplicable', 'taxSetting',
        ));
    })->middleware('can:pos_sales.view')->name('pos');

    // TSK-025 - real shift/cash workflow (DEC-066, docs/32). Replaces the
    // previous read-only readiness boundary.
    Route::get('pos/shift', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('shifts_cash_movements.view'), 403);

        $shift = PosShift::query()->visibleTo($user)->active()
            ->where('cashier_id', $user->id)
            ->with(['cashDrawer', 'cashMovements'])
            ->latest('opened_at')
            ->first();

        $openToken = (string) $request->session()->get('pos.shift.open_token', '');
        if ($openToken === '') {
            $openToken = (string) Str::uuid();
            $request->session()->put('pos.shift.open_token', $openToken);
        }
        $movementToken = null;
        $closeToken = null;
        if ($shift !== null) {
            $movementTokenKey = 'pos.shift.movement_token.'.$shift->getKey();
            $closeTokenKey = 'pos.shift.close_token.'.$shift->getKey().'.'.$shift->getAttribute('recount_count');
            $movementToken = (string) $request->session()->get($movementTokenKey, '');
            $closeToken = (string) $request->session()->get($closeTokenKey, '');
            if ($movementToken === '') {
                $movementToken = (string) Str::uuid();
                $request->session()->put($movementTokenKey, $movementToken);
            }
            if ($closeToken === '') {
                $closeToken = (string) Str::uuid();
                $request->session()->put($closeTokenKey, $closeToken);
            }
        }

        // docs/32 §10 - expected totals must not reach a cashier session
        // before submission, so this view is given no expected figures at all.
        return view('pages.pos.shift', [
            'shift' => $shift,
            'drawers' => CashDrawer::query()->visibleTo($user)->where('status', 'active')->orderBy('code')->get(),
            'methods' => PaymentMethod::query()->where('status', 'active')->orderBy('code')->get()->reject->isCash()->values(),
            'movementTypes' => CashMovement::TYPES,
            'openToken' => $openToken,
            'movementToken' => $movementToken,
            'closeToken' => $closeToken,
        ]);
    })->middleware('can:shifts_cash_movements.view')->name('pos.shift');

    Route::post('pos/shift/open', function (Request $request, OpenShiftAction $action) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('shifts_cash_movements.create'), 403);

        $validated = $request->validate([
            'cash_drawer_id' => ['required', 'integer', 'exists:cash_drawers,id'],
            'opening_float' => ['required', 'numeric', 'gte:0'],
            'idempotency_key' => ['required', 'uuid'],
        ]);

        try {
            $action->execute(
                $user,
                CashDrawer::query()->findOrFail($validated['cash_drawer_id']),
                (string) $validated['opening_float'],
                'SHIFT-OPEN:'.$validated['idempotency_key'],
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['cash_drawer_id' => $e->getMessage()]);
        }

        $request->session()->forget('pos.shift.open_token');

        return redirect()->route('pos.shift')->with('success', __('Shift opened.'));
    })->middleware('can:shifts_cash_movements.create')->name('pos.shift.open');

    Route::post('pos/shift/{shift}/cash-movement', function (Request $request, PosShift $shift, RecordCashMovementAction $action) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('shifts_cash_movements.create'), 403);
        abort_unless(PosShift::query()->visibleTo($user)->whereKey($shift->getKey())->exists(), 403);

        $validated = $request->validate([
            'movement_type' => ['required', 'string', Rule::in(CashMovement::TYPES)],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'max:500'],
            'reference' => ['nullable', 'string', 'max:190'],
            'idempotency_key' => ['required', 'uuid'],
        ]);

        try {
            $action->execute(
                $user,
                $shift,
                $validated['movement_type'],
                (string) $validated['amount'],
                $validated['reason'],
                'CASH-MOVE:'.$validated['idempotency_key'],
                $validated['reference'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        $request->session()->forget('pos.shift.movement_token.'.$shift->getKey());

        return redirect()->route('pos.shift')->with('success', __('Cash movement recorded.'));
    })->middleware('can:shifts_cash_movements.create')->name('pos.shift.cash-movement');

    Route::post('pos/shift/{shift}/blind-close', function (Request $request, PosShift $shift, SubmitBlindShiftCloseAction $action) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('shifts_cash_movements.submit'), 403);
        abort_unless(PosShift::query()->visibleTo($user)->whereKey($shift->getKey())->exists(), 403);

        $validated = $request->validate([
            'actual_cash' => ['required', 'numeric', 'gte:0'],
            'actual_by_method' => ['nullable', 'array'],
            'actual_by_method.*' => ['nullable', 'numeric', 'gte:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['required', 'uuid'],
        ]);

        /** @var array<string, mixed> $submitted */
        $submitted = $validated['actual_by_method'] ?? [];
        $actualByMethod = [];
        foreach ($submitted as $code => $value) {
            if ($value !== null && $value !== '') {
                $actualByMethod[(string) $code] = (string) $value;
            }
        }

        try {
            $action->execute(
                $user,
                $shift,
                (string) $validated['actual_cash'],
                $actualByMethod,
                'SHIFT-CLOSE:'.$validated['idempotency_key'],
                $validated['notes'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['actual_cash' => $e->getMessage()]);
        }

        // The cashier returns to a screen that deliberately shows no expected
        // or variance figure (docs/32 §15 redaction).
        $request->session()->forget('pos.shift.close_token.'.$shift->getKey().'.'.$shift->getAttribute('recount_count'));

        return redirect()->route('pos.shift')->with('success', __('Count submitted for review.'));
    })->middleware('can:shifts_cash_movements.submit')->name('pos.shift.blind-close');

    Route::get('pos/shift-variance', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        // Expected vs actual is manager/reviewer-only (docs/32 §13).
        abort_unless($user->can('shifts_cash_movements.approve'), 403);

        $shifts = PosShift::query()->visibleTo($user)
            ->whereIn('status', [ShiftState::ClosingSubmitted->value, ShiftState::VarianceReview->value])
            ->with(['cashDrawer', 'cashier', 'closingSubmissions'])
            ->latest('submitted_at')
            ->paginate(20);

        return view('pages.pos.shift-variance', compact('shifts'));
    })->middleware('can:shifts_cash_movements.approve')->name('pos.shift-variance');

    Route::get('pos/offline-readiness', function (Request $request) {
        abort_unless($request->user()?->can('pos_sales.view'), 403);

        return view('pages.pos.offline-readiness');
    })->middleware('can:pos_sales.view')->name('pos.offline-readiness');

    Route::get('customers/loyalty-readiness', function (Request $request) {
        abort_unless($request->user()?->can('pos_sales.view'), 403);

        $definitions = CustomerPolicySettingRegistry::all();
        $latest = CustomerPolicySettingVersion::query()
            ->whereIn('key', array_keys($definitions))
            ->orderByDesc('version')
            ->get()
            ->groupBy('key')
            ->map(static fn ($versions) => $versions->first());

        $decisionSettings = collect($definitions)->mapWithKeys(static fn (array $definition, string $key): array => [
            $key => [
                ...$definition,
                'record' => $latest->get($key),
            ],
        ]);

        return view('pages.customers.loyalty-readiness', compact('decisionSettings'));
    })->middleware('can:pos_sales.view')->name('customers.loyalty-readiness');

    Route::get('admin/settings/customer-loyalty', function (Request $request) {
        abort_unless($request->user()?->can('company_settings.view'), 403);

        $definitions = CustomerPolicySettingRegistry::all();
        $latest = CustomerPolicySettingVersion::query()
            ->whereIn('key', array_keys($definitions))
            ->orderByDesc('version')
            ->get()
            ->groupBy('key')
            ->map(static fn ($versions) => $versions->first());

        $settings = collect($definitions)->mapWithKeys(static fn (array $definition, string $key): array => [
            $key => [
                ...$definition,
                'record' => $latest->get($key),
            ],
        ]);

        return view('pages.admin.customer-loyalty-settings', compact('settings'));
    })->middleware('can:company_settings.view')->name('admin.settings.customer-loyalty');

    Route::post('admin/settings/customer-loyalty', function (Request $request, SaveCustomerPolicySettingAction $action) {
        abort_unless($request->user()?->can('company_settings.edit'), 403);

        $data = $request->validate([
            'key' => ['required', 'string', 'max:120'],
            'value' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $action->execute($data['key'], $data['value'] ?? null, $data['notes'] ?? null);

        return to_route('admin.settings.customer-loyalty')->with('status', 'Customer policy setting version saved locally; owner approval is still required.');
    })->middleware('can:company_settings.edit')->name('admin.settings.customer-loyalty.save');

    Route::get('pos/financial-readiness', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('company_settings.view'), 403);

        $latest = PosFinancialSettingVersion::query()
            ->whereIn('key', array_keys(PosFinancialSettingRegistry::all()))
            ->orderByDesc('version')
            ->get()
            ->groupBy('key')
            ->map(fn ($versions) => $versions->first());

        return view('pages.pos.financial-readiness', [
            'activePaymentMethods' => PaymentMethod::query()->where('status', 'active')->count(),
            'activeTaxSettings' => TaxSetting::query()->where('status', 'active')->count(),
            'definitions' => PosFinancialSettingRegistry::all(),
            'latestSettings' => $latest,
        ]);
    })->middleware('can:company_settings.view')->name('pos.financial-readiness');

    Route::post('admin/settings/pos-financial', function (Request $request, SavePosFinancialSettingAction $action) {
        abort_unless($request->user()?->can('company_settings.edit'), 403);
        $data = $request->validate([
            'key' => ['required', 'string', Rule::in(array_keys(PosFinancialSettingRegistry::all()))],
            'value' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $action->execute($data['key'], $data['value'] ?? null, $data['notes'] ?? null);

        return back()->with('success', __('A new POS financial setting version was saved.'));
    })->middleware('can:company_settings.edit')->name('admin.settings.pos-financial.save');

    Route::post('pos/cart/add', function (Request $request) {
        abort_unless($request->user()?->can('pos_sales.create'), 403);
        $data = $request->validate(['product_id' => ['required', 'integer', 'exists:products,id'], 'quantity' => ['required', 'numeric', 'min:0.000001', 'max:999999']]);
        /** @var array<int, array{product_id: int, quantity: numeric-string}> $sessionCart */
        $sessionCart = $request->session()->get('pos.cart', []);
        $cart = collect($sessionCart);
        /** @var int $productId */
        $productId = (int) $data['product_id'];
        /** @var numeric-string $quantity */
        $quantity = (string) $data['quantity'];
        $key = $cart->search(fn (array $line): bool => (int) $line['product_id'] === $productId);
        if ($key === false) {
            $cart->push(['product_id' => $productId, 'quantity' => $quantity]);
        } else {
            /** @var numeric-string $existingQuantity */
            $existingQuantity = $cart[$key]['quantity'];
            $cart[$key]['quantity'] = bcadd($existingQuantity, $quantity, 6);
        }
        $request->session()->put('pos.cart', $cart->values()->all());

        return back()->with('success', __('Product added to cart.'));
    })->middleware('can:pos_sales.create')->name('pos.cart.add');

    Route::post('pos/cart/remove', function (Request $request) {
        abort_unless($request->user()?->can('pos_sales.create'), 403);
        $data = $request->validate(['product_id' => ['required', 'integer']]);
        /** @var array<int, array{product_id: int, quantity: numeric-string}> $sessionCart */
        $sessionCart = $request->session()->get('pos.cart', []);
        $cart = collect($sessionCart)->reject(fn (array $line): bool => (int) $line['product_id'] === (int) $data['product_id']);
        $request->session()->put('pos.cart', $cart->values()->all());

        return back();
    })->middleware('can:pos_sales.create')->name('pos.cart.remove');

    Route::post('pos/cart/discount', function (Request $request, DiscountPolicy $policy) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('pos_sales.apply_discount'), 403);
        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'discount_type' => ['required', Rule::in([DiscountPolicy::TYPE_LINE, DiscountPolicy::TYPE_CUSTOMER_GROUP])],
            'discount_amount' => ['required', 'numeric', 'gt:0'],
            'reason' => ['nullable', 'string', 'max:500'],
            'expected_revision' => ['required', 'integer', 'min:0'],
        ]);

        /** @var array<int, array<string, mixed>> $sessionCart */
        $sessionCart = $request->session()->get('pos.cart', []);
        $cart = collect($sessionCart);
        $index = $cart->search(fn (array $line): bool => (int) $line['product_id'] === (int) $data['product_id']);
        abort_if($index === false, 404);
        $line = $cart[$index];
        if ((int) ($line['discount_revision'] ?? 0) !== (int) $data['expected_revision']) {
            return back()->withErrors(['discount' => __('The discount changed in another request. Review the current value and try again.')]);
        }

        $store = Store::query()->visibleTo($user)->where('type', 'selling')->where('status', 'active')->firstOrFail();
        $price = app(EffectivePriceResolver::class)->resolve((int) $line['product_id'], $store->id);
        abort_if($price === null, 422, __('The product has no effective price.'));
        $unitPrice = (string) ($line['open_price_amount'] ?? $price->amount);
        $gross = DecimalMoney::round(bcmul((string) $line['quantity'], $unitPrice, 8));
        $existingType = filled($line['discount_type'] ?? null) ? (string) $line['discount_type'] : null;

        try {
            $resolved = $policy->buildLineDiscount(
                actor: $user,
                discountAmount: DecimalMoney::round((string) $data['discount_amount']),
                baseAmount: $gross,
                newType: (string) $data['discount_type'],
                existingType: $existingType,
                reason: $data['reason'] ?? null,
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['discount' => $exception->getMessage()]);
        }

        $before = [
            'type' => $existingType,
            'amount' => (string) ($line['discount_amount'] ?? '0.00'),
            'revision' => (int) ($line['discount_revision'] ?? 0),
        ];
        $line['discount_type'] = $resolved['discount_type'];
        $line['discount_amount'] = DecimalMoney::round($resolved['discount_amount']);
        $line['discount_reason'] = $data['reason'] ?? null;
        $line['discount_replaces'] = $resolved['replaces'] ?? $existingType;
        $line['discount_previous_type'] = $existingType;
        $line['discount_previous_amount'] = $before['amount'];
        $line['discount_revision'] = $before['revision'] + 1;
        $cart[$index] = $line;
        $request->session()->put('pos.cart', $cart->values()->all());

        app(RecordAuditEvent::class)->execute(
            category: 'retail',
            event: $existingType === null ? 'pos_cart_discount_applied' : 'pos_cart_discount_replaced',
            before: $before,
            after: ['type' => $line['discount_type'], 'amount' => $line['discount_amount'], 'revision' => $line['discount_revision']],
            branchId: (int) $store->branch_id,
            storeId: (int) $store->id,
            reasonText: $data['reason'] ?? null,
            metadata: ['product_id' => (int) $line['product_id'], 'actor_id' => $user->id],
        );

        return back()->with('success', $existingType === null ? __('Discount applied.') : __('The previous discount was replaced.'));
    })->middleware('can:pos_sales.apply_discount')->name('pos.cart.discount');

    Route::post('pos/cart/open-price', function (Request $request, OpenPricePolicy $policy) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('pos_sales.open_price'), 403);
        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'max:500'],
            'expected_revision' => ['required', 'integer', 'min:0'],
        ]);

        /** @var array<int, array<string, mixed>> $sessionCart */
        $sessionCart = $request->session()->get('pos.cart', []);
        $cart = collect($sessionCart);
        $index = $cart->search(fn (array $line): bool => (int) $line['product_id'] === (int) $data['product_id']);
        abort_if($index === false, 404);
        $line = $cart[$index];
        if ((int) ($line['pricing_revision'] ?? 0) !== (int) $data['expected_revision']) {
            return back()->withErrors(['open_price' => __('The open price changed in another request. Review it and try again.')]);
        }

        $store = Store::query()->visibleTo($user)->where('type', 'selling')->where('status', 'active')->firstOrFail();
        $price = app(EffectivePriceResolver::class)->resolve((int) $line['product_id'], $store->id);
        abort_if($price === null || ! $price->open_price_allowed, 422, __('Open price is not enabled for this product.'));
        $reference = (string) ($price->reference_amount ?? $price->amount);
        $policy->validateOrThrow(
            referenceAmount: $reference,
            requestedAmount: (string) $data['amount'],
            minimum: $price->open_price_minimum === null ? null : (string) $price->open_price_minimum,
            maximum: $price->open_price_maximum === null ? null : (string) $price->open_price_maximum,
            hasPermission: true,
            reason: (string) $data['reason'],
        );

        $before = ['amount' => $line['open_price_amount'] ?? null, 'revision' => (int) ($line['pricing_revision'] ?? 0)];
        $line['open_price_amount'] = DecimalMoney::normalize((string) $data['amount'], 4);
        $line['open_price_reason'] = trim((string) $data['reason']);
        $line['pricing_revision'] = $before['revision'] + 1;
        $cart[$index] = $line;
        $request->session()->put('pos.cart', $cart->values()->all());

        app(RecordAuditEvent::class)->execute(
            category: 'pricing',
            event: 'pos_cart_open_price_set',
            before: $before,
            after: ['reference' => $reference, 'amount' => $line['open_price_amount'], 'minimum' => $price->open_price_minimum, 'maximum' => $price->open_price_maximum, 'revision' => $line['pricing_revision']],
            branchId: (int) $store->branch_id,
            storeId: (int) $store->id,
            reasonText: (string) $data['reason'],
            metadata: ['product_id' => (int) $line['product_id'], 'actor_id' => $user->id],
        );

        return back()->with('success', __('Open price applied to the basket line.'));
    })->middleware('can:pos_sales.open_price')->name('pos.cart.open-price');

    Route::post('pos/cart/tax', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('pos_sales.apply_tax'), 403);
        $store = Store::query()->visibleTo($user)->where('type', 'selling')->where('status', 'active')->firstOrFail();
        $data = $request->validate(['tax_applicable' => ['required', 'boolean']]);
        $applicable = (bool) $data['tax_applicable'];
        $setting = null;
        if ($applicable) {
            $effective = TaxSetting::query()
                ->where('status', 'active')
                ->where(fn ($query) => $query->whereNull('effective_from')->orWhere('effective_from', '<=', now()))
                ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>', now()))
                ->get();
            if ($effective->count() !== 1 || blank($effective->first()?->rate)) {
                return back()->withErrors(['tax' => __('Tax cannot be enabled until exactly one effective policy with a configured rate exists.')]);
            }
            $setting = $effective->first();
        }

        $before = (bool) $request->session()->get('pos.tax_applicable', false);
        $request->session()->put('pos.tax_applicable', $applicable);
        app(RecordAuditEvent::class)->execute(
            category: 'retail', event: 'pos_cart_tax_selection_changed',
            before: ['tax_applicable' => $before],
            after: ['tax_applicable' => $applicable, 'tax_setting_id' => $setting?->id, 'rate_snapshot' => $setting?->rate],
            branchId: (int) $store->branch_id,
            storeId: (int) $store->id,
            reasonText: __('Invoice tax selection changed at POS.'),
            metadata: ['actor_id' => $user->id],
        );

        return back()->with('success', $applicable ? __('Tax enabled for this basket.') : __('Tax disabled for this basket.'));
    })->middleware('can:pos_sales.apply_tax')->name('pos.cart.tax');

    Route::post('pos/cart/clear', function (Request $request) {
        abort_unless($request->user()?->can('pos_sales.create'), 403);
        $request->session()->forget('pos.cart');
        $request->session()->forget('pos.tax_applicable');

        return back();
    })->middleware('can:pos_sales.create')->name('pos.cart.clear');

    Route::post('pos/suspend', function (Request $request, RetailSaleAction $action) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('pos_sales.create'), 403);
        $store = Store::query()->visibleTo($user)->where('type', 'selling')->where('status', 'active')->firstOrFail();
        /** @var array<int, array{product_id: int, quantity: numeric-string}> $cart */
        $cart = $request->session()->get('pos.cart', []);
        $token = (string) $request->session()->get('pos.checkout_token', Str::uuid());
        $sale = $action->create(
            $user,
            $store,
            $cart,
            'SUSPEND:'.$user->id.':'.$token,
            true,
            [],
            ['tax_applicable' => (bool) $request->session()->get('pos.tax_applicable', false)],
        );
        $request->session()->forget('pos.cart');
        $request->session()->forget('pos.checkout_token');
        $request->session()->forget('pos.tax_applicable');

        return redirect()->route('pos')->with('success', __('Sale suspended. Resume code: :code', ['code' => $sale->suspendedSale?->getAttribute('resume_code')]));
    })->middleware('can:pos_sales.create')->name('pos.suspend');

    Route::post('pos/checkout', function (Request $request, RetailSaleAction $action) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('pos_sales.create'), 403);
        $store = Store::query()->visibleTo($user)->where('type', 'selling')->where('status', 'active')->firstOrFail();
        /** @var array<int, array{product_id: int, quantity: numeric-string}> $cart */
        $cart = $request->session()->get('pos.cart', []);

        $validated = $request->validate([
            'checkout_token' => ['required', 'uuid'],
            'tax_applicable' => ['nullable', 'boolean'],
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.method_id' => ['nullable', 'integer', 'exists:payment_methods,id'],
            'payments.*.amount' => ['nullable', 'numeric', 'gte:0'],
            'payments.*.tendered' => ['nullable', 'numeric', 'gte:0'],
            'payments.*.evidence_reference' => ['nullable', 'string', 'max:190'],
            'payments.*.evidence' => ['nullable', 'file'],
        ]);

        $tenders = [];
        foreach ($validated['payments'] as $index => $payment) {
            if (empty($payment['method_id'])) {
                continue;
            }

            $attachmentId = null;
            if ($request->hasFile("payments.{$index}.evidence")) {
                abort_unless($user->can('pos_sales.payment_evidence_upload'), 403);
                $attachment = app(StoreAttachment::class)->execute(
                    $request->file("payments.{$index}.evidence"),
                    'payment_evidence',
                    new AttachmentSourceReference(
                        branchId: (int) $store->branch_id,
                        storeId: (int) $store->id,
                        visibility: 'private',
                    ),
                );
                $attachmentId = $attachment->id;
            }

            $tenders[] = [
                'method' => PaymentMethod::query()->findOrFail($payment['method_id']),
                'amount' => isset($payment['amount']) ? (string) $payment['amount'] : '0.00',
                'tendered' => isset($payment['tendered']) ? (string) $payment['tendered'] : null,
                'evidence_reference' => $payment['evidence_reference'] ?? null,
                'evidence_attachment_id' => $attachmentId,
            ];
        }
        if ($tenders === []) {
            return back()->withErrors(['payments' => __('Select at least one payment method.')]);
        }

        try {
            $sale = $action->create(
                $user,
                $store,
                $cart,
                'CHECKOUT:'.$user->id.':'.$validated['checkout_token'],
                false,
                $tenders,
                ['tax_applicable' => (bool) ($validated['tax_applicable'] ?? false)],
            );
        } catch (InvalidArgumentException|RuntimeException $e) {
            return back()->withErrors(['payments' => $e->getMessage()]);
        }

        $request->session()->forget('pos.cart');
        $request->session()->forget('pos.checkout_token');
        $request->session()->forget('pos.tax_applicable');

        return redirect()->route('sales.show', $sale)->with('success', __('Sale completed successfully.'));
    })->middleware('can:pos_sales.create')->name('pos.checkout');

    Route::get('pos/suspended', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('suspended_sales.view'), 403);
        $sales = Sale::query()->visibleTo($user)->with(['lines', 'suspendedSale', 'store'])->where('status', 'suspended')->latest()->paginate(20);

        return view('pages.pos.suspended', compact('sales'));
    })->middleware('can:suspended_sales.view')->name('pos.suspended');

    Route::get('pos/suspended/{sale}/resume', function (Request $request, Sale $sale, RetailSaleAction $action) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('pos_sales.create'), 403);
        abort_unless(Sale::query()->visibleTo($user)->whereKey($sale->id)->exists(), 404);

        try {
            $preview = $action->suspendedResumePreview($user, $sale);
        } catch (InvalidArgumentException $exception) {
            return redirect()->route('pos.suspended')->withErrors(['resume' => $exception->getMessage()]);
        }

        $paymentMethods = PaymentMethod::query()->where('status', 'active')->orderBy('code')->get();
        $cashMethod = $paymentMethods->first(fn (PaymentMethod $method): bool => $method->isCash());
        $electronicMethods = $paymentMethods->reject(fn (PaymentMethod $method): bool => $method->isCash())->values();
        $resumeTokenKey = 'pos.suspended.resume_token.'.$sale->id;
        $resumeToken = (string) $request->session()->get($resumeTokenKey, '');
        if ($resumeToken === '') {
            $resumeToken = (string) Str::uuid();
            $request->session()->put($resumeTokenKey, $resumeToken);
        }
        $cashDenomination = PosFinancialSettingRegistry::numericValue(PosFinancialSettingRegistry::CASH_ROUNDING_DENOMINATION);

        return view('pages.pos.resume', compact('sale', 'preview', 'cashMethod', 'electronicMethods', 'resumeToken', 'cashDenomination'));
    })->middleware('can:pos_sales.create')->name('pos.suspended.resume');

    Route::post('pos/suspended/{sale}/resume', function (Request $request, Sale $sale, RetailSaleAction $action) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('pos_sales.create'), 403);
        abort_unless(Sale::query()->visibleTo($user)->whereKey($sale->id)->exists(), 404);

        $validated = $request->validate([
            'resume_token' => ['required', 'uuid'],
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.method_id' => ['nullable', 'integer', 'exists:payment_methods,id'],
            'payments.*.amount' => ['nullable', 'numeric', 'gte:0'],
            'payments.*.tendered' => ['nullable', 'numeric', 'gte:0'],
            'payments.*.evidence_reference' => ['nullable', 'string', 'max:190'],
            'payments.*.evidence' => ['nullable', 'file'],
        ]);
        $resumeTokenKey = 'pos.suspended.resume_token.'.$sale->id;
        $expectedToken = (string) $request->session()->get($resumeTokenKey, '');
        abort_unless($expectedToken !== '' && hash_equals($expectedToken, (string) $validated['resume_token']), 419);

        $tenders = [];
        foreach ($validated['payments'] as $index => $payment) {
            if (empty($payment['method_id'])) {
                continue;
            }

            $attachmentId = null;
            if ($request->hasFile("payments.{$index}.evidence")) {
                abort_unless($user->can('pos_sales.payment_evidence_upload'), 403);
                $attachment = app(StoreAttachment::class)->execute(
                    $request->file("payments.{$index}.evidence"),
                    'payment_evidence',
                    new AttachmentSourceReference(
                        branchId: (int) $sale->branch_id,
                        storeId: (int) $sale->store_id,
                        visibility: 'private',
                    ),
                );
                $attachmentId = $attachment->id;
            }

            $tenders[] = [
                'method' => PaymentMethod::query()->findOrFail($payment['method_id']),
                'amount' => isset($payment['amount']) ? (string) $payment['amount'] : '0.00',
                'tendered' => isset($payment['tendered']) ? (string) $payment['tendered'] : null,
                'evidence_reference' => $payment['evidence_reference'] ?? null,
                'evidence_attachment_id' => $attachmentId,
            ];
        }
        if ($tenders === []) {
            return back()->withErrors(['payments' => __('Select at least one payment method.')]);
        }

        try {
            $sale = $action->finalizeSuspended($user, $sale, $tenders);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return back()->withInput()->withErrors(['payments' => $exception->getMessage()]);
        }

        $request->session()->forget($resumeTokenKey);

        return redirect()->route('sales.show', $sale)->with('success', __('Suspended sale resumed and completed.'));
    })->middleware('can:pos_sales.create')->name('pos.suspended.finalize');

    Route::get('sales', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('pos_sales.view'), 403);
        $query = Sale::query()->visibleTo($user)->with(['store', 'cashier'])->withCount(['lines', 'payments']);
        $query->when($request->filled('q'), function ($query) use ($request): void {
            $term = trim((string) $request->string('q'));
            $query->where(function ($scope) use ($term): void {
                $scope->where('document_number', 'like', "%{$term}%")
                    ->orWhere('idempotency_key', 'like', "%{$term}%");
            });
        });
        $query->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')));
        $query->when($request->filled('store_id'), fn ($query) => $query->where('store_id', $request->integer('store_id')));
        $query->when($request->filled('date_from'), fn ($query) => $query->whereDate('created_at', '>=', $request->string('date_from')));
        $query->when($request->filled('date_to'), fn ($query) => $query->whereDate('created_at', '<=', $request->string('date_to')));
        $sales = $query->latest('id')->paginate(20)->withQueryString();
        $stores = Store::query()->visibleTo($user)->orderBy('code')->get();

        return view('pages.sales.index', compact('sales', 'stores'));
    })->middleware('can:pos_sales.view')->name('sales.index');

    Route::get('sales/invoices', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('pos_sales.view'), 403);
        $query = Sale::query()->visibleTo($user)->approved()->with(['store', 'cashier'])->withCount('payments');
        $query->when($request->filled('q'), fn ($query) => $query->where('document_number', 'like', '%'.trim((string) $request->string('q')).'%'));
        $query->when($request->filled('store_id'), fn ($query) => $query->where('store_id', $request->integer('store_id')));
        $query->when($request->filled('date_from'), fn ($query) => $query->whereDate('approved_at', '>=', $request->string('date_from')));
        $query->when($request->filled('date_to'), fn ($query) => $query->whereDate('approved_at', '<=', $request->string('date_to')));
        $sales = $query->latest('approved_at')->paginate(20)->withQueryString();
        $stores = Store::query()->visibleTo($user)->orderBy('code')->get();

        return view('pages.sales.invoices', compact('sales', 'stores'));
    })->middleware('can:pos_sales.view')->name('sales.invoices');

    Route::get('payments', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('pos_sales.payment_view'), 403);
        $query = SalePayment::query()
            ->whereHas('sale', fn ($saleQuery) => $saleQuery->visibleTo($user)->approved())
            ->with(['sale.store', 'sale.cashier', 'paymentMethod', 'creator', 'evidenceAttachment']);
        $query->when($request->filled('q'), fn ($query) => $query->whereHas('sale', fn ($saleQuery) => $saleQuery->where('document_number', 'like', '%'.trim((string) $request->string('q')).'%')));
        $query->when($request->filled('method_id'), fn ($query) => $query->where('payment_method_id', $request->integer('method_id')));
        $query->when($request->filled('date_from'), fn ($query) => $query->whereDate('created_at', '>=', $request->string('date_from')));
        $query->when($request->filled('date_to'), fn ($query) => $query->whereDate('created_at', '<=', $request->string('date_to')));
        $payments = $query->latest('id')->paginate(20)->withQueryString();
        $methods = PaymentMethod::query()->orderBy('code')->get();

        return view('pages.payments.index', compact('payments', 'methods'));
    })->middleware('can:pos_sales.payment_view')->name('payments.index');

    Route::get('payment-evidence', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('pos_sales.payment_evidence_view'), 403);
        $query = SalePayment::query()
            ->whereNotNull('evidence_attachment_id')
            ->whereHas('sale', fn ($saleQuery) => $saleQuery->visibleTo($user)->approved())
            ->with(['sale.store', 'paymentMethod', 'creator', 'evidenceAttachment']);
        $query->when($request->filled('q'), fn ($query) => $query->whereHas('sale', fn ($saleQuery) => $saleQuery->where('document_number', 'like', '%'.trim((string) $request->string('q')).'%')));
        $query->when($request->filled('method_id'), fn ($query) => $query->where('payment_method_id', $request->integer('method_id')));
        $evidencePayments = $query->latest('id')->paginate(20)->withQueryString();
        $methods = PaymentMethod::query()->where('requires_evidence', true)->orderBy('code')->get();

        return view('pages.payments.evidence', compact('evidencePayments', 'methods'));
    })->middleware('can:pos_sales.payment_evidence_view')->name('payments.evidence');

    Route::get('payment-evidence/{attachment}', function (Request $request, Attachment $attachment) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('pos_sales.payment_evidence_view'), 403);

        return app(DeliverAttachment::class)->execute(
            $attachment,
            static function (User $viewer, Attachment $candidate): bool {
                if (! $viewer->can('pos_sales.payment_evidence_view')
                    || $candidate->purpose !== 'payment_evidence'
                    || $candidate->source_type !== SalePayment::class) {
                    return false;
                }

                return SalePayment::query()
                    ->whereKey((int) $candidate->source_id)
                    ->where('evidence_attachment_id', $candidate->id)
                    ->whereHas('sale', fn ($saleQuery) => $saleQuery->visibleTo($viewer)->approved())
                    ->exists();
            },
        );
    })->middleware('can:pos_sales.payment_evidence_view')->name('payments.evidence.show');

    Route::get('sales/{sale}', function (Request $request, Sale $sale) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('pos_sales.view'), 403);
        abort_unless(Sale::query()->visibleTo($user)->whereKey($sale->id)->exists(), 404);
        $sale->load(['store', 'cashier', 'lines.product', 'shift.cashDrawer', 'payments.paymentMethod', 'payments.evidenceAttachment']);

        return view('pages.sales.show', compact('sale'));
    })->middleware('can:pos_sales.view')->name('sales.show');

    Route::get('sales/{sale}/print', function (Request $request, Sale $sale) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('pos_sales.print'), 403);
        abort_unless(Sale::query()->visibleTo($user)->whereKey($sale->id)->exists(), 404);
        abort_unless($sale->status === 'approved', 422);
        $sale->load(['store.company', 'cashier', 'lines', 'payments.paymentMethod']);
        app(RecordAuditEvent::class)->execute(
            category: 'retail', event: 'sale_a4_printed', source: $sale,
            branchId: (int) $sale->branch_id, storeId: (int) $sale->store_id,
            metadata: ['format' => 'a4', 'reprint' => true],
        );

        return view('pages.sales.print', compact('sale'));
    })->middleware('can:pos_sales.print')->name('sales.print');

    Route::get('sales/{sale}/receipt/thermal', function (Request $request, Sale $sale) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('pos_sales.print'), 403);
        abort_unless(Sale::query()->visibleTo($user)->whereKey($sale->id)->where('status', 'approved')->exists(), 404);
        $sale->load(['store.company', 'cashier', 'lines', 'payments.paymentMethod']);
        app(RecordAuditEvent::class)->execute(
            category: 'retail', event: 'sale_thermal_receipt_printed', source: $sale,
            branchId: (int) $sale->branch_id, storeId: (int) $sale->store_id,
            metadata: ['format' => 'thermal', 'reprint' => true],
        );

        return view('pages.sales.thermal', compact('sale'));
    })->middleware('can:pos_sales.print')->name('sales.receipt.thermal');
});
