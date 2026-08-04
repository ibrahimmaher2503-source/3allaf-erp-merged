<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\LocalDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class LocalDemoSeederSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_demo_seeder_runs_only_in_local_environment(): void
    {
        $this->setEnvironment('local');

        try {
            app(LocalDemoSeeder::class)->run();
        } finally {
            $this->setEnvironment('testing');
        }

        $this->assertDatabaseHas('users', ['email' => 'demo.admin@toyjoy.local']);
    }

    public function test_local_demo_seeder_refuses_to_run_in_production_environment(): void
    {
        $this->setEnvironment('production');

        try {
            app(LocalDemoSeeder::class)->run();
            $this->fail('The local demo seeder ran in the production environment.');
        } catch (LogicException) {
            $this->addToAssertionCount(1);
        } finally {
            $this->setEnvironment('testing');
        }

        $this->assertDatabaseMissing('users', ['email' => 'demo.admin@toyjoy.local']);
    }

    public function test_database_seeder_does_not_create_the_local_demo_administrator_in_production(): void
    {
        $this->setEnvironment('production');

        try {
            app(DatabaseSeeder::class)->run();
        } finally {
            $this->setEnvironment('testing');
        }

        $this->assertDatabaseMissing('users', ['email' => 'demo.admin@toyjoy.local']);
        $this->assertSame(0, User::query()->where('is_super_admin', true)->count());
        $this->assertDatabaseHas('roles', ['code' => 'system-administrator']);
    }

    private function setEnvironment(string $environment): void
    {
        $this->app->detectEnvironment(fn (): string => $environment);
    }
}
