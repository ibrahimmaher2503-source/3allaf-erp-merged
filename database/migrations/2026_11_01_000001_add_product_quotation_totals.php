<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table): void {
            $table->date('quotation_date')->nullable()->after('store_id');
            $table->string('discount_type', 20)->nullable()->after('subtotal');
            $table->decimal('discount_value', 14, 4)->default(0)->after('discount_type');
            $table->decimal('discount_amount', 14, 2)->default(0)->after('discount_value');
            $table->decimal('tax_rate', 7, 4)->default(0)->after('discount_amount');
            $table->decimal('tax_amount', 14, 2)->default(0)->after('tax_rate');
            $table->timestamp('cancelled_at')->nullable()->after('total');
            $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable()->after('cancelled_by');
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn(['quotation_date', 'discount_type', 'discount_value', 'discount_amount', 'tax_rate', 'tax_amount', 'cancelled_at', 'cancellation_reason']);
        });
    }
};
