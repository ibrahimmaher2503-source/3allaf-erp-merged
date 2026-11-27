<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Owner switch for receiving weights from the scale in POS. On by default so existing
     * stores keep today's behavior; when off, POS hides the scale receiver and stops polling.
     */
    public function up(): void
    {
        Schema::table('scale_configurations', function (Blueprint $table): void {
            $table->boolean('pos_receive_enabled')->default(true)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('scale_configurations', function (Blueprint $table): void {
            $table->dropColumn('pos_receive_enabled');
        });
    }
};
