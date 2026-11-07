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
        foreach ([
            'purchase_invoices' => ['id', 'purchase_order_id'],
            'purchase_invoice_lines' => ['id', 'purchase_invoice_id', 'purchase_order_line_id', 'product_id', 'tax_rate', 'tax_amount'],
            'purchase_order_lines' => ['id', 'purchase_order_id', 'product_id', 'tax_inclusive'],
        ] as $table => $columns) {
            if (! Schema::hasTable($table) || ! Schema::hasColumns($table, $columns)) {
                throw new RuntimeException("Cannot add the immutable tax mode snapshot: required {$table} schema is incomplete.");
            }
        }

        $ambiguous = DB::table('purchase_invoice_lines')
            ->whereNull('purchase_order_line_id')
            ->where(fn ($query) => $query->where('tax_rate', '<>', 0)->orWhere('tax_amount', '<>', 0))
            ->orderBy('id')->limit(20)->pluck('id')->all();
        if ($ambiguous !== []) {
            throw new RuntimeException('Cannot add the immutable tax mode snapshot: unlinked nonzero-tax purchase invoice lines require explicit owner mapping. Line IDs: '.implode(', ', $ambiguous));
        }

        $ambiguousLinkedSources = DB::table('purchase_invoice_lines as pil')
            ->join('purchase_invoices as pi', 'pi.id', '=', 'pil.purchase_invoice_id')
            ->leftJoin('purchase_order_lines as pol', 'pol.id', '=', 'pil.purchase_order_line_id')
            ->whereNotNull('pil.purchase_order_line_id')
            ->where(function ($query): void {
                $query->whereNull('pol.id')
                    ->orWhereNull('pi.purchase_order_id')
                    ->orWhereColumn('pol.purchase_order_id', '<>', 'pi.purchase_order_id')
                    ->orWhereColumn('pol.product_id', '<>', 'pil.product_id');
            })
            ->orderBy('pil.id')->limit(20)->pluck('pil.id')->all();
        if ($ambiguousLinkedSources !== []) {
            throw new RuntimeException('Cannot add the immutable tax mode snapshot: linked invoice lines do not resolve to their exact immutable purchase-order source. Invoice line IDs: '.implode(', ', $ambiguousLinkedSources));
        }

        if (! Schema::hasColumn('purchase_invoice_lines', 'tax_inclusive')) {
            Schema::table('purchase_invoice_lines', function (Blueprint $table): void {
                $table->boolean('tax_inclusive')->default(false)->after('tax_code');
            });
        }

        DB::table('purchase_invoice_lines as pil')
            ->join('purchase_order_lines as pol', 'pol.id', '=', 'pil.purchase_order_line_id')
            ->update(['pil.tax_inclusive' => DB::raw('pol.tax_inclusive')]);
        DB::statement('ALTER TABLE purchase_invoice_lines MODIFY tax_inclusive TINYINT(1) NOT NULL DEFAULT 0');
    }

    public function down(): void
    {
        if (! Schema::hasColumn('purchase_invoice_lines', 'tax_inclusive')) {
            return;
        }
        if (DB::table('purchase_invoice_lines')->where('tax_inclusive', true)->exists()) {
            throw new RuntimeException('Rollback refused: removing tax_inclusive would destroy immutable inclusive-tax invoice history.');
        }
        Schema::table('purchase_invoice_lines', fn (Blueprint $table) => $table->dropColumn('tax_inclusive'));
    }
};
