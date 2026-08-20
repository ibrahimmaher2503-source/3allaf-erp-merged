<?php

namespace Tests\Feature\ClientFeedback;

use App\Modules\Platform\Actions\SaveLocalSettingsAction;
use App\Modules\Platform\Models\PrinterConfiguration;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Store;
use Illuminate\Validation\ValidationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

class PrinterProfileSafetyTest extends TestCase
{
    use PlatformFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCanonicalAuthorization();
    }

    public function test_printer_profile_rejects_incompatible_paper_size(): void
    {
        $this->actingAs($this->administrator('cf-printer-safety'));

        $this->expectException(ValidationException::class);

        app(SaveLocalSettingsAction::class)->savePrinterConfiguration([
            'name' => 'Thermal mismatch',
            'printer_type' => 'thermal',
            'paper_size' => 'a4',
            'template_name' => 'default_thermal',
            'connection_type' => 'browser', 'port' => null,
            'status' => 'active',
        ]);
    }

    public function test_saving_default_printer_clears_previous_default_and_never_leaves_inactive_default(): void
    {
        $this->actingAs($this->administrator('cf-printer-default'));

        $first = app(SaveLocalSettingsAction::class)->savePrinterConfiguration([
            'name' => 'First printer', 'printer_type' => 'thermal', 'paper_size' => '80mm',
            'template_name' => 'receipt_80', 'connection_type' => 'browser', 'port' => null, 'is_default' => true, 'status' => 'active',
        ]);
        $second = app(SaveLocalSettingsAction::class)->savePrinterConfiguration([
            'name' => 'Second printer', 'printer_type' => 'a4', 'paper_size' => 'a4',
            'template_name' => 'invoice_a4', 'connection_type' => 'browser', 'port' => null, 'is_default' => true, 'status' => 'active',
        ]);

        self::assertFalse((bool) $first->fresh()->is_default);
        self::assertTrue((bool) $second->fresh()->is_default);

        app(SaveLocalSettingsAction::class)->savePrinterConfiguration([
            'name' => 'Second printer', 'printer_type' => 'a4', 'paper_size' => 'a4',
            'template_name' => 'invoice_a4', 'connection_type' => 'browser', 'port' => null, 'is_default' => true, 'status' => 'inactive',
        ], $second->id);

        self::assertFalse((bool) $second->fresh()->is_default);
        self::assertSame(0, PrinterConfiguration::query()->where('is_default', true)->count());
    }

    public function test_default_isolated_by_scope_and_store_must_belong_to_selected_branch(): void
    {
        $this->actingAs($this->administrator('cf-printer-scope'));
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create(['company_id' => $branchA->company_id]);
        $storeB = Store::factory()->create(['company_id' => $branchB->company_id, 'branch_id' => $branchB->id]);

        $global = app(SaveLocalSettingsAction::class)->savePrinterConfiguration($this->printer('global'), null);
        $branchPrinter = app(SaveLocalSettingsAction::class)->savePrinterConfiguration($this->printer('branch'), null, ['scope_type' => 'branch', 'branch_id' => $branchA->id]);
        $branchBPrinter = app(SaveLocalSettingsAction::class)->savePrinterConfiguration($this->printer('branch-b'), null, ['scope_type' => 'branch', 'branch_id' => $branchB->id]);

        self::assertTrue((bool) $global->fresh()->is_default);
        self::assertTrue((bool) $branchPrinter->fresh()->is_default);
        self::assertTrue((bool) $branchBPrinter->fresh()->is_default);

        $this->expectException(ValidationException::class);
        app(SaveLocalSettingsAction::class)->savePrinterConfiguration($this->printer('wrong-store'), null, ['scope_type' => 'store', 'branch_id' => $branchA->id, 'store_id' => $storeB->id]);
    }

    public function test_runtime_helper_prefers_location_then_branch_then_global_without_cross_branch_fallback(): void
    {
        $this->actingAs($this->administrator('cf-printer-runtime'));
        $branch = Branch::factory()->create();
        $store = Store::factory()->create(['company_id' => $branch->company_id, 'branch_id' => $branch->id]);
        app(SaveLocalSettingsAction::class)->savePrinterConfiguration($this->printer('global-runtime'));
        app(SaveLocalSettingsAction::class)->savePrinterConfiguration($this->printer('branch-runtime'), null, ['scope_type' => 'branch', 'branch_id' => $branch->id]);
        app(SaveLocalSettingsAction::class)->savePrinterConfiguration($this->printer('store-runtime'), null, ['scope_type' => 'store', 'branch_id' => $branch->id, 'store_id' => $store->id]);

        self::assertSame('store-runtime', PrinterConfiguration::resolveForScope($branch->id, $store->id)?->name);
        self::assertSame('branch-runtime', PrinterConfiguration::resolveForScope($branch->id, null)?->name);
        self::assertSame('global-runtime', PrinterConfiguration::resolveForScope(null, null)?->name);
        self::assertNull(PrinterConfiguration::resolveForScope(null, $store->id));
    }

    /** @return array<string, mixed> */
    private function printer(string $name): array
    {
        return ['name' => $name, 'printer_type' => 'thermal', 'paper_size' => '80mm', 'template_name' => 'receipt_80', 'connection_type' => 'browser', 'port' => null, 'is_default' => true, 'status' => 'active'];
    }
}
