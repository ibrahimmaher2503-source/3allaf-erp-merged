<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MariaDB/MySQL unique indexes permit multiple NULL values. The existing
        // products_item_code_unique index remains in place and continues to
        // protect every allocated/imported non-NULL product identity.
        Schema::table('products', function (Blueprint $table): void {
            $table->string('item_code', 50)->nullable()->change();
        });
    }

    public function down(): void
    {
        throw new \RuntimeException(
            'Migration 000116 is intentionally irreversible: product drafts may contain NULL item codes. Restore Hotfix41 application files while retaining this nullable schema and all data.',
        );
    }
};
