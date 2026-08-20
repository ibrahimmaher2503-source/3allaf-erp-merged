<?php

declare(strict_types=1);

namespace Tests\Feature\Reporting;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

final class ReportDashboardRenderTest extends TestCase
{
    use PlatformFixtures;
    use DatabaseTransactions;

    public function test_dashboard_report_renders_product_filter_labels(): void
    {
        $this->seedCanonicalAuthorization();
        $this->actingAs($this->administrator('report-render-contract'));

        $this->get(route('reports.index'))
            ->assertOk()
            ->assertSee('All product types')
            ->assertSee('All product statuses');
    }
}
