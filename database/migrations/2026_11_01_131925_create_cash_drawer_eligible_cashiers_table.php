<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_drawer_eligible_cashiers', function (Blueprint $table): void {
            $table->foreignId('cash_drawer_id')->constrained('cash_drawers')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['cash_drawer_id', 'user_id']);
        });

        DB::table('cash_drawers')->whereNotNull('assigned_user_id')->orderBy('id')->chunkById(200, function ($drawers): void {
            foreach ($drawers as $drawer) {
                DB::table('cash_drawer_eligible_cashiers')->insertOrIgnore([
                    'cash_drawer_id' => $drawer->id,
                    'user_id' => $drawer->assigned_user_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_drawer_eligible_cashiers');
    }
};
