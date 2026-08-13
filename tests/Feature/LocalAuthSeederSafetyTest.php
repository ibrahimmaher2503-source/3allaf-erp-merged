<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\LocalAuthSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class LocalAuthSeederSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_auth_seeder_runs_only_in_local_environment(): void
    {
        $this->setEnvironment('local');

        try {
            app(LocalAuthSeeder::class)->run();
        } finally {
            $this->setEnvironment('testing');
        }

        $this->assertDatabaseHas('users', ['email' => 'local.system-administrator@example.test']);
    }

    public function test_local_auth_seeder_refuses_to_run_in_production_environment(): void
    {
        $this->setEnvironment('production');

        try {
            app(LocalAuthSeeder::class)->run();
            $this->fail('The local authentication seeder ran in the production environment.');
        } catch (LogicException) {
            $this->addToAssertionCount(1);
        } finally {
            $this->setEnvironment('testing');
        }

        $this->assertDatabaseMissing('users', ['email' => 'local.system-administrator@example.test']);
    }

    public function test_database_seeder_creates_the_baseline_administrator_but_not_local_auth_accounts_in_production(): void
    {
        $this->setEnvironment('production');

        try {
            app(DatabaseSeeder::class)->run();
        } finally {
            $this->setEnvironment('testing');
        }

        $this->assertDatabaseMissing('users', ['email' => 'local.system-administrator@example.test']);
        $this->assertDatabaseHas('users', ['username' => 'admin', 'email' => 'admin@instaparty.online']);
        $this->assertSame(1, User::query()->where('is_super_admin', true)->count());
        $this->assertDatabaseHas('roles', ['code' => 'system-administrator']);
    }

    private function setEnvironment(string $environment): void
    {
        $this->app->detectEnvironment(fn (): string => $environment);
    }
}
