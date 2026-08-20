<?php

declare(strict_types=1);

namespace Tests\Feature\Remediation;

use App\Models\User;
use Database\Seeders\ProductionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class NotificationsScreenTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_authenticated_user_can_open_the_localized_notification_empty_state(): void
    {
        $this->seed(ProductionSeeder::class);
        $this->actingAs(User::query()->where('username', 'admin')->firstOrFail());

        $this->get('/notifications')->assertOk()->assertSee('Notifications')->assertSee('No notifications');
    }
}
