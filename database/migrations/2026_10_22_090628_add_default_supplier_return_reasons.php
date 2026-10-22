<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $reasons = [
            'STALE_STOCK' => ['مرتجع أصناف راكدة', 'Slow-moving stock'],
            'CONSIGNMENT' => ['مرتجع أصناف أمانات', 'Consignment stock'],
            'DAMAGED' => ['مرتجع أصناف تالفة', 'Damaged items'],
            'DEFECTIVE' => ['مرتجع أصناف معيبة', 'Defective items'],
            'SPEC_MISMATCH' => ['مرتجع أصناف غير مطابقة للمواصفات', 'Specification mismatch'],
            'ORDER_MISMATCH' => ['مرتجع أصناف غير مطابقة لأمر الشراء', 'Purchase order mismatch'],
            'WRONG_DELIVERY' => ['مرتجع أصناف تم توريدها بالخطأ', 'Delivered in error'],
            'EXCESS_QUANTITY' => ['مرتجع كميات زائدة عن المطلوب', 'Excess quantity'],
            'QUALITY_REJECTED' => ['مرتجع بسبب رفض الجودة', 'Quality rejection'],
            'EXPIRED' => ['مرتجع أصناف منتهية الصلاحية', 'Expired items'],
            'NEAR_EXPIRY' => ['مرتجع أصناف قاربت على انتهاء الصلاحية', 'Near-expiry items'],
            'PACKAGING_DAMAGE' => ['مرتجع بسبب تلف العبوة أو التغليف', 'Packaging damage'],
            'VARIANT_ERROR' => ['مرتجع بسبب خطأ في المقاس أو اللون أو الموديل أو الصنف', 'Wrong size, color, model, or item'],
            'ORDER_CANCELLED' => ['مرتجع بسبب إلغاء الطلب أو جزء منه', 'Order cancellation'],
            'SUPPLIER_RECALL' => ['مرتجع بسبب استدعاء المنتج من المورد', 'Supplier product recall'],
            'COMMERCIAL_AGREEMENT' => ['مرتجع بسبب اتفاق تجاري مع المورد', 'Commercial agreement with supplier'],
            'DELIVERY_CORRECTION' => ['مرتجع لتصحيح توريد خاطئ', 'Incorrect delivery correction'],
            'OTHER' => ['أخرى', 'Other'],
        ];

        foreach ($reasons as $code => [$arabic, $english]) {
            if (DB::table('supplier_return_reasons')->where('code', $code)->orWhere('label_ar', $arabic)->exists()) {
                continue;
            }

            DB::table('supplier_return_reasons')->insertOrIgnore([
                'code' => $code,
                'label_ar' => $arabic,
                'label_en' => $english,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        /** Catalog entries may be referenced by historical returns; rollback preserves them. */
    }
};
