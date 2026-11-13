<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ERP-side definition of the weight/price barcode printed on scale labels
     * (prefix, item-code digits, embedded value, decimals). Saved in ERP only;
     * the device BAR upload protocol is not verified.
     */
    public function up(): void
    {
        Schema::table('scale_configurations', function (Blueprint $table): void {
            $table->json('barcode_settings')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('scale_configurations', function (Blueprint $table): void {
            $table->dropColumn('barcode_settings');
        });
    }
};
