<?php

declare(strict_types=1);

namespace Tests\Feature\Remediation;

use App\Models\User;
use Database\Seeders\ProductionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DocumentedRouteContractsTest extends TestCase
{
    use RefreshDatabase;

    public function test_documented_profile_and_settings_urls_redirect_to_the_authorized_tab(): void
    {
        $this->seed(ProductionSeeder::class);
        $this->actingAs(User::query()->where('username', 'admin')->firstOrFail());

        $this->get('/profile')->assertRedirect('/settings/profile');

        foreach ([
            '/admin/company' => '/admin/settings?tab=company',
            '/admin/payment-methods' => '/admin/settings?tab=payments',
            '/admin/tax-settings' => '/admin/settings?tab=tax',
            '/admin/document-sequences' => '/admin/settings?tab=sequences',
            '/admin/printers' => '/admin/settings?tab=printers',
        ] as $source => $target) {
            $this->get($source)->assertRedirect($target);
        }
    }
}
