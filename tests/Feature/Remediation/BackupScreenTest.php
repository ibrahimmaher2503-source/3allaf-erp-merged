<?php

declare(strict_types=1);

namespace Tests\Feature\Remediation;

use App\Models\User;
use Database\Seeders\ProductionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class BackupScreenTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_authorized_operator_sees_a_localized_backup_and_restore_screen_instead_of_raw_json(): void
    {
        $this->seed(ProductionSeeder::class);
        $this->actingAs(User::query()->where('username', 'admin')->firstOrFail());

        $this->get('/admin/system/backups')
            ->assertOk()
            ->assertSee('Backup & Restore')
            ->assertSee('Backup destination')
            ->assertDontSee('"verify_backup"');
    }
}
