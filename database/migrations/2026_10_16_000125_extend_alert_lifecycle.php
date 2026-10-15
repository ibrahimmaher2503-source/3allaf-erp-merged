<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alerts', function (Blueprint $table): void {
            $table->foreignId('assigned_to')->nullable()->after('store_id')->constrained('users')->nullOnDelete();
            $table->timestamp('due_at')->nullable()->after('created_at');
            $table->index(['assigned_to', 'status'], 'alert_owner_status');
        });
    }

    public function down(): void
    {
        Schema::table('alerts', function (Blueprint $table): void {
            $table->dropIndex('alert_owner_status');
            $table->dropForeign(['assigned_to']);
            $table->dropColumn(['assigned_to', 'due_at']);
        });
    }
};
