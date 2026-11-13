<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scale_received_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('scale_configuration_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('sequence');
            $table->dateTime('recorded_at');
            $table->unsignedInteger('plu');
            $table->decimal('weight', 10, 3);
            $table->decimal('unit_price', 12, 4);
            $table->decimal('total', 12, 2);
            // baseline = history present when reception was first used, received = added to a POS cart, rejected = refused.
            $table->string('status', 20);
            $table->string('reason', 255)->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['scale_configuration_id', 'sequence'], 'scale_received_sequence_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scale_received_records');
    }
};
