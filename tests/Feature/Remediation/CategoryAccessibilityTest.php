<?php

declare(strict_types=1);

namespace Tests\Feature\Remediation;

use App\Models\User;
use Database\Seeders\ProductionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class CategoryAccessibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_fields_have_stable_associated_labels_and_human_validation_names(): void
    {
        $this->seed(ProductionSeeder::class);
        $this->actingAs(User::query()->where('username', 'admin')->firstOrFail());

        Livewire::test('catalog::categories')
            ->call('openCreateCategoryModal')
            ->assertSee('id="category-code"', false)
            ->assertSee('for="category-code"', false)
            ->call('saveCategory')
            ->assertSee('The Category code field is required.');
    }
}
