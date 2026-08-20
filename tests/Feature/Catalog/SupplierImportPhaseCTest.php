<?php

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Actions\StageSupplierImportAction;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Catalog\Models\SupplierImportBatch;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Support\PlatformFixtures;

final class SupplierImportPhaseCTest extends TestCase
{
    use DatabaseTransactions;
    use PlatformFixtures;

    public function test_supplier_import_stages_validates_and_approves_without_self_approval(): void
    {
        $requester = $this->administrator('supplier-import-requester');
        $reviewer = $this->administrator('supplier-import-reviewer');
        Storage::disk('local')->put('imports/suppliers-phase-c.csv', "code,name_ar,name_en,contact_name,email,phone,tax_number,payment_terms,address,status,supplier_group_ar,supplier_group_en\nSUP-C-001,مورد,Vendor C,,c@example.test,+201000000000,,Net 30,,active,,\n");
        $this->actingAs($requester);
        $batch = app(StageSupplierImportAction::class)->stage('imports/suppliers-phase-c.csv', 'suppliers-phase-c.csv', 'create_only', $requester->id);
        self::assertSame(StageSupplierImportAction::templateHeaders(), array_slice(StageSupplierImportAction::templateHeaders(), 0));
        self::assertSame(StageSupplierImportAction::templateHeaders(), $batch->headers);
        $batch = app(StageSupplierImportAction::class)->applyMapping($batch, array_combine($batch->headers, $batch->headers));
        self::assertSame('ready_for_review', $batch->status);
        $this->expectExceptionMessage('requester cannot approve');
        app(StageSupplierImportAction::class)->approve($batch);
        $this->actingAs($reviewer);
        app(StageSupplierImportAction::class)->approve($batch);
        self::assertDatabaseHas('suppliers', ['code' => 'SUP-C-001']);
    }

    public function test_supplier_import_rejects_a_file_with_noncanonical_headers(): void
    {
        $requester = $this->administrator('supplier-import-header-requester');
        Storage::disk('local')->put('imports/suppliers-bad-header.csv', "name_ar,code,name_en\nمورد,SUP-H-001,Vendor\n");

        $this->actingAs($requester);
        $this->expectExceptionMessage('headers');

        app(StageSupplierImportAction::class)->stage(
            'imports/suppliers-bad-header.csv',
            'suppliers-bad-header.csv',
            'create_only',
            $requester->id,
        );
    }

    public function test_supplier_import_rejects_formula_like_cell_values(): void
    {
        $requester = $this->administrator('supplier-import-formula-requester');
        Storage::disk('local')->put('imports/suppliers-formula.csv', "code,name_ar,name_en,contact_name,email,phone,tax_number,payment_terms,address,status,supplier_group_ar,supplier_group_en\nSUP-F-001,=CONCAT(\"مورد\"),Vendor,,,,,,active,,\n");

        $this->actingAs($requester);
        $this->expectExceptionMessage('Formula');

        app(StageSupplierImportAction::class)->stage(
            'imports/suppliers-formula.csv',
            'suppliers-formula.csv',
            'create_only',
            $requester->id,
        );
    }

    public function test_supplier_import_review_controls_follow_maker_checker_visibility(): void
    {
        $requester = $this->administrator('supplier-import-ui-requester');
        $reviewer = $this->administrator('supplier-import-ui-reviewer');
        $batch = SupplierImportBatch::query()->create([
            'created_by' => $requester->id,
            'original_filename' => 'review-pending.xlsx',
            'storage_path' => 'imports/review-pending.xlsx',
            'sha256' => str_repeat('a', 64),
            'mode' => 'create_only',
            'status' => 'mapping_required',
            'headers' => StageSupplierImportAction::templateHeaders(),
        ]);

        $this->actingAs($reviewer);
        $this->get(route('catalog.suppliers.import'))
            ->assertOk()
            ->assertSee('review-pending.xlsx');
        Livewire::test('catalog::supplier-import')
            ->set('selectedBatchId', $batch->id)
            ->assertDontSee('Validate rows');

        $this->actingAs($requester);
        $this->get(route('catalog.suppliers.import.template'))
            ->assertOk()
            ->assertDownload('supplier-import-template.xlsx');
        Livewire::test('catalog::supplier-import')
            ->set('selectedBatchId', $batch->id)
            ->assertSee('Validate rows');

        $batch->update(['status' => 'ready_for_review']);
        Livewire::test('catalog::supplier-import')
            ->set('selectedBatchId', $batch->id)
            ->assertDontSee('Approve and import');
        $this->actingAs($reviewer);
        Livewire::test('catalog::supplier-import')
            ->set('selectedBatchId', $batch->id)
            ->assertSee('Approve and import');
    }
}
