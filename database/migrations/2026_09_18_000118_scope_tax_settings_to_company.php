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
        foreach (['companies', 'tax_settings', 'products', 'product_store_assignments', 'sales', 'stores'] as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException("Cannot scope tax settings: required {$table} table is missing.");
            }
        }
        if (! Schema::hasColumns('products', ['tax_setting_id']) || ! Schema::hasColumns('product_store_assignments', ['product_id', 'company_id']) || ! Schema::hasColumns('sales', ['tax_setting_id', 'store_id']) || ! Schema::hasColumns('stores', ['id', 'company_id'])) {
            throw new RuntimeException('Cannot scope tax settings: required tax ownership references are incomplete.');
        }

        $companyIds = DB::table('companies')->orderBy('id')->pluck('id')->map(fn ($id): int => (int) $id);
        if (DB::table('tax_settings')->exists() && $companyIds->isEmpty()) {
            throw new RuntimeException('Cannot scope tax settings: no company exists.');
        }

        $plan = [];
        foreach (DB::table('tax_settings')->orderBy('id')->get(['id', ...(Schema::hasColumn('tax_settings', 'company_id') ? ['company_id'] : [])]) as $tax) {
            $related = DB::table('products')
                ->join('product_store_assignments', 'product_store_assignments.product_id', '=', 'products.id')
                ->where('products.tax_setting_id', $tax->id)
                ->distinct()->orderBy('product_store_assignments.company_id')
                ->pluck('product_store_assignments.company_id')
                ->merge(DB::table('sales')->join('stores', 'stores.id', '=', 'sales.store_id')->where('sales.tax_setting_id', $tax->id)->pluck('stores.company_id'))
                ->map(fn ($id): int => (int) $id)->unique()->sort()->values();
            $existingCompany = isset($tax->company_id) && $tax->company_id !== null ? (int) $tax->company_id : null;
            if ($existingCompany !== null && ! $companyIds->contains($existingCompany)) {
                throw new RuntimeException("Cannot scope tax setting {$tax->id}: existing company ownership does not reference a known company.");
            }
            if ($related->count() > 1) {
                throw new RuntimeException("Cannot scope tax setting {$tax->id}: referenced products belong to multiple companies (".$related->implode(', ').').');
            }
            if ($existingCompany !== null && $related->isNotEmpty() && $related->first() !== $existingCompany) {
                throw new RuntimeException("Cannot scope tax setting {$tax->id}: existing company ownership contradicts its references.");
            }
            $derived = $existingCompany ?? $related->first() ?? ($companyIds->count() === 1 ? $companyIds->first() : null);
            if ($derived === null) {
                throw new RuntimeException("Cannot scope tax setting {$tax->id}: no unambiguous company relationship exists.");
            }
            $plan[(int) $tax->id] = $derived;
        }

        if (! Schema::hasColumn('tax_settings', 'company_id')) {
            Schema::table('tax_settings', fn (Blueprint $table) => $table->unsignedBigInteger('company_id')->nullable()->after('id'));
        }
        foreach ($plan as $taxId => $companyId) {
            DB::table('tax_settings')->where('id', $taxId)->update(['company_id' => $companyId]);
        }
        if (DB::table('tax_settings')->whereNull('company_id')->exists()) {
            throw new RuntimeException('Cannot finish tax scoping: NULL company ownership remains.');
        }

        $globalCodeIndex = $this->uniqueIndexForColumns('tax_settings', ['code']);
        if ($globalCodeIndex !== null) {
            Schema::table('tax_settings', fn (Blueprint $table) => $table->dropUnique($globalCodeIndex));
        }
        if (! $this->indexExists('tax_settings', 'tax_settings_company_code_unique')) {
            Schema::table('tax_settings', fn (Blueprint $table) => $table->unique(['company_id', 'code'], 'tax_settings_company_code_unique'));
        }
        if (! $this->indexExists('tax_settings', 'tax_settings_company_status_default_index')) {
            Schema::table('tax_settings', fn (Blueprint $table) => $table->index(['company_id', 'status', 'is_default'], 'tax_settings_company_status_default_index'));
        }
        if (! $this->foreignKeyExists('tax_settings', 'tax_settings_company_id_foreign')) {
            Schema::table('tax_settings', fn (Blueprint $table) => $table->foreign('company_id', 'tax_settings_company_id_foreign')->references('id')->on('companies')->restrictOnDelete());
        }
        DB::statement('ALTER TABLE tax_settings MODIFY company_id BIGINT UNSIGNED NOT NULL');
    }

    public function down(): void
    {
        if (! Schema::hasColumn('tax_settings', 'company_id')) {
            if ($this->uniqueIndexForColumns('tax_settings', ['code']) === null) {
                Schema::table('tax_settings', fn (Blueprint $table) => $table->unique('code'));
            }
            return;
        }
        if (DB::table('tax_settings')->exists()) {
            throw new RuntimeException('Rollback refused: removing company_id would destroy tax-rule tenant ownership.');
        }
        Schema::table('tax_settings', function (Blueprint $table): void {
            if ($this->foreignKeyExists('tax_settings', 'tax_settings_company_id_foreign')) $table->dropForeign('tax_settings_company_id_foreign');
            if ($this->indexExists('tax_settings', 'tax_settings_company_status_default_index')) $table->dropIndex('tax_settings_company_status_default_index');
            if ($this->indexExists('tax_settings', 'tax_settings_company_code_unique')) $table->dropUnique('tax_settings_company_code_unique');
            $table->dropColumn('company_id');
            $table->unique('code');
        });
    }

    private function indexExists(string $table, string $name): bool
    {
        return DB::table('information_schema.statistics')->where('table_schema', DB::getDatabaseName())->where('table_name', $table)->where('index_name', $name)->exists();
    }

    private function uniqueIndexForColumns(string $table, array $columns): ?string
    {
        foreach (Schema::getIndexes($table) as $index) {
            if (($index['unique'] ?? false) && ($index['columns'] ?? []) === $columns) {
                return (string) $index['name'];
            }
        }

        return null;
    }

    private function foreignKeyExists(string $table, string $name): bool
    {
        return DB::table('information_schema.table_constraints')->where('constraint_schema', DB::getDatabaseName())->where('table_name', $table)->where('constraint_name', $name)->where('constraint_type', 'FOREIGN KEY')->exists();
    }
};
