<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\PrinterConfiguration;
use App\Modules\Platform\Support\InitialSetupStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
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

        PrinterConfiguration::query()->create([
            'name' => 'Default thermal printer',
            'printer_type' => 'thermal',
            'paper_size' => '80mm',
            'template_name' => 'default_thermal',
            'print_template_id' => null,
            'connection_type' => 'network',
            'is_default' => true,
            'status' => 'active',
        ]);

        $step = collect(app(InitialSetupStatus::class)->snapshot()['steps'])->firstWhere('key', 'print-templates');

        self::assertFalse($step['complete']);
    }
}
