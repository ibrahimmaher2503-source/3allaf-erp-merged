<?php

namespace Tests\Feature\Testing;

use App\Modules\Inventory\Models\StockBalance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\Support\Datasets\DatasetSize;
use Tests\Support\Scenarios\PosScenario;
use Tests\TestCase;

class TestDataFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_pos_scenario_builds_the_complete_deterministic_chain(): void
    {
        fake()->seed(20260809);
        $scenario = PosScenario::ready(DatasetSize::TINY);

        $this->assertSame($scenario['store']->branch_id, $scenario['branch']->id);
        $this->assertSame($scenario['drawer']->store_id, $scenario['store']->id);
        $this->assertSame($scenario['shift']->cashier_id, $scenario['user']->id);
        $this->assertCount(1, $scenario['products']);
        $this->assertSame(1, StockBalance::query()->where('store_id', $scenario['store']->id)->count());
        $this->assertSame('approved', $scenario['version']->state->value);
    }

    public function test_dataset_profile_counts_are_bounded_and_explicit(): void
    {
        $this->assertSame(1, DatasetSize::TINY->products());
        $this->assertSame(10, DatasetSize::SMALL->products());
        $this->assertSame(100, DatasetSize::MEDIUM->products());
        $this->assertSame(10_000, DatasetSize::LARGE->products());
        $this->assertSame(2, DatasetSize::RACE->products());
    }

    public function test_generation_command_is_blocked_in_production_like_environments(): void
    {
        $this->expectException(LogicException::class);
        $this->app['env'] = 'production';

        $this->artisan('testing:data')->assertExitCode(1);
    }
}
