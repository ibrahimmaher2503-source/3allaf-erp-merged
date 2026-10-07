<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\PrinterConfiguration;
use App\Modules\Platform\Models\PrintTemplate;
use App\Modules\Platform\Support\InitialSetupStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

final class InitialSetupStatusTest extends TestCase
{
    use DatabaseTransactions;

    public function test_print_template_assignments_are_incomplete_without_a_linked_template(): void
    {
        $company = Company::factory()->create();
        $administrator = User::factory()->create(['status' => 'active']);
        $administrator->forceFill(['is_super_admin' => true])->save();

        $this->actingAs($administrator);
        request()->merge(['company_id' => $company->id]);

        $printer = PrinterConfiguration::query()->create([
            'name' => 'Default thermal printer',
            'printer_type' => 'thermal',
            'paper_size' => '80mm',
            'template_name' => 'default_thermal',
            'print_template_id' => null,
            'connection_type' => 'network',
            'is_default' => true,
            'status' => 'active',
        ]);

        $printTemplateStep = static fn (): array => collect((new InitialSetupStatus)->snapshot()['steps'])->firstWhere('key', 'print-templates');

        self::assertFalse($printTemplateStep()['complete']);

        $template = PrintTemplate::query()->create([
            'company_id' => $company->id,
            'code' => 'QA-THERMAL',
            'name_ar' => 'قالب حراري للاختبار',
            'name_en' => 'Test thermal template',
            'document_type' => 'sales_invoice',
            'paper_size' => '80mm',
            'language' => 'bilingual',
            'status' => 'active',
        ]);
        $printer->update(['print_template_id' => $template->id]);
        Cache::flush();
        request()->attributes->remove('initial_setup_snapshot');

        self::assertTrue($printTemplateStep()['complete']);

        $template->update(['status' => 'inactive']);
        Cache::flush();
        request()->attributes->remove('initial_setup_snapshot');

        self::assertFalse($printTemplateStep()['complete']);
    }
}
