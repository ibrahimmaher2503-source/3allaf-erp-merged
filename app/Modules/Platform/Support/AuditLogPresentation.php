<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support;

use Illuminate\Support\Str;

final class AuditLogPresentation
{
    /** @var array<string,string> */
    private const EVENTS = [
        'pos_open_order_created' => 'Open sale order created',
        'unverified_retail_return_complete' => 'Return without invoice completed',
        'create_store' => 'Store created',
        'update_store' => 'Store updated',
        'delete_store' => 'Store archived',
        'toggle_store_status' => 'Store status changed',
        'map_branch_selling_store' => 'Branch point of sale mapped',
        'create_branch' => 'Branch created',
        'update_branch' => 'Branch updated',
        'delete_branch' => 'Branch archived',
        'toggle_branch_status' => 'Branch status changed',
        'create_payment_method' => 'Payment method created',
        'update_payment_method' => 'Payment method updated',
        'update_local_settings' => 'Local settings updated',
        'create_cash_drawer' => 'Cash drawer created',
        'update_cash_drawer' => 'Cash drawer updated',
        'delete_cash_drawer' => 'Cash drawer archived',
        'toggle_cash_drawer_status' => 'Cash drawer status changed',
        'update_user_authorization' => 'User authorization updated',
        'update_role_permissions' => 'Role permissions updated',
        'create_user' => 'User created',
        'verify_user_email' => 'User email verified',
        'create_product_card' => 'Product card created',
        'update_product_card' => 'Product card updated',
        'change_product_type' => 'Product type changed',
        'toggle_product_status' => 'Product status changed',
        'create_product_barcode' => 'Product barcode added',
        'create_workspace_barcode' => 'Product barcode added',
        'create_international_barcode' => 'Product barcode added',
        'allocate_local_barcode' => 'Product barcode added',
        'deactivate_barcode' => 'Product barcode deactivated',
        'update_product_filters' => 'Product filters updated',
        'create_product' => 'Product created',
        'update_product' => 'Product updated',
        'toggle_product_status' => 'Product status changed',
        'create_category' => 'Category created',
        'update_category' => 'Category updated',
        'create_brand' => 'Brand created',
        'update_brand' => 'Brand updated',
        'create_supplier' => 'Supplier created',
        'update_supplier' => 'Supplier updated',
        'activate_supplier' => 'Supplier activated',
        'deactivate_supplier' => 'Supplier deactivated',
        'approval_requested' => 'Approval requested',
        'approval_approved' => 'Approval approved',
        'approval_rejected' => 'Approval rejected',
        'approval_cancelled' => 'Approval cancelled',
        'approval_withdrawn' => 'Approval withdrawn',
        'approval_expired' => 'Approval expired',
        'attachment_stored' => 'Attachment stored',
        'attachment_linked' => 'Attachment linked',
        'attachment_accessed' => 'Attachment accessed',
        'attachment_revoked' => 'Attachment revoked',
        'finalize_sale' => 'Sale finalized',
        'open_shift' => 'Shift opened',
        'submit_shift_close' => 'Shift close submitted',
        'cash_movement' => 'Cash movement recorded',
        'price_proposal_created' => 'Price proposal created',
        'price_proposal_submitted' => 'Price proposal submitted',
        'price_version_approved' => 'Price version approved',
        'price_version_superseded' => 'Price version superseded',
        'price_version_rejected' => 'Price version rejected',
        'create_document_sequence' => 'Document sequence created',
        'update_document_sequence' => 'Document sequence updated',
        'branch_document_sequence_initialized' => 'Branch document sequence initialized',
        'document_number_allocated' => 'Document number allocated',
        'document_sequence_counter_overridden' => 'Document sequence counter overridden',
        'create_inventory_adjustment' => 'Inventory adjustment draft created',
        'update_inventory_adjustment' => 'Inventory adjustment draft updated',
        'audit_log_exported' => 'Audit log exported',
    ];

    /** @var array<string,string> */
    private const SOURCES = [
        'Store' => 'Store',
        'BranchSellingStore' => 'Branch point of sale mapping',
        'PaymentMethod' => 'Payment method',
        'Company' => 'Company',
        'Branch' => 'Branch',
        'CashDrawer' => 'Cash drawer',
        'User' => 'User',
        'Role' => 'Role',
        'Product' => 'Product',
        'Barcode' => 'Barcode',
        'Category' => 'Category',
        'Brand' => 'Brand',
        'Supplier' => 'Supplier',
        'AuditLog' => 'Audit log',
        'ApprovalRecord' => 'Approval record',
        'PrinterConfiguration' => 'Printer profile',
        'DocumentSequence' => 'Document sequence',
        'InventoryAdjustment' => 'Inventory adjustment',
        'TaxSetting' => 'Tax setting',
    ];

    public function event(string $event): string
    {
        $title = __(self::EVENTS[$event] ?? Str::headline(str_replace('_', ' ', $event)));
        if (str_starts_with(app()->getLocale(), 'ar') && preg_match('/[A-Za-z]/', $title)) {
            $parts = preg_split('/[_\s]+/', strtolower($event));
            $verbs = ['create' => 'إنشاء', 'created' => 'إنشاء', 'update' => 'تعديل', 'updated' => 'تعديل', 'complete' => 'إكمال', 'completed' => 'إكمال', 'approve' => 'اعتماد', 'approved' => 'اعتماد', 'reject' => 'رفض', 'rejected' => 'رفض', 'print' => 'طباعة', 'printed' => 'طباعة', 'open' => 'فتح', 'opened' => 'فتح', 'close' => 'إغلاق', 'closed' => 'إغلاق', 'cancel' => 'إلغاء', 'cancelled' => 'إلغاء'];
            $nouns = ['pos' => 'نقطة البيع', 'sale' => 'البيع', 'retail' => 'البيع', 'return' => 'المرتجع', 'shift' => 'الوردية', 'cash' => 'النقدية', 'drawer' => 'الدرج', 'product' => 'المنتج', 'quotation' => 'عرض السعر', 'customer' => 'العميل', 'transfer' => 'التحويل', 'stock' => 'المخزون', 'inventory' => 'المخزون', 'order' => 'الطلب', 'gift' => 'الهدية', 'card' => 'البطاقة', 'payment' => 'الدفع', 'user' => 'المستخدم', 'login' => 'تسجيل الدخول', 'logout' => 'تسجيل الخروج'];
            $words = array_values(array_unique(array_filter(array_map(fn ($part) => $verbs[$part] ?? $nouns[$part] ?? null, $parts))));

            return $words === [] ? __('Activity recorded') : implode(' ', $words);
        }

        return $title;
    }

    public function source(?string $sourceType): string
    {
        $name = class_basename((string) $sourceType);

        return __(self::SOURCES[$name] ?? Str::headline($name));
    }

    public function category(string $category): string
    {
        return __(match ($category) {
            'master_data' => 'Master data',
            'platform' => 'Platform',
            'security' => 'Security',
            'authorization' => 'Authorization',
            'inventory' => 'Inventory',
            'numbering' => 'Numbering',
            'purchasing' => 'Purchasing',
            'pricing' => 'Pricing',
            'retail' => 'Retail',
            'customer' => 'Customers',
            'party' => 'Parties',
            default => 'Other audit category',
        });
    }

    public function fingerprint(mixed $value): ?string
    {
        if (! is_string($value) || preg_match('/\A[a-f0-9]{64}\z/i', $value) !== 1) {
            return null;
        }

        return substr($value, 0, 12);
    }
}
