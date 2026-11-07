<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_ui_preferences', function (Blueprint $table): void {
            $table->string('menu_variant', 1)->default('a');
        });
    }

    public function down(): void
    {
        Schema::table('user_ui_preferences', function (Blueprint $table): void {
            $table->dropColumn('menu_variant');
        });
    }
};
