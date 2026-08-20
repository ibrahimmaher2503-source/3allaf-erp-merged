<?php

declare(strict_types=1);

namespace Tests\Feature\Remediation;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use App\Modules\Catalog\Models\Category;
use Database\Seeders\ProductionSeeder;
use Tests\TestCase;

final class ProductCreateModalTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_authorized_user_can_open_the_product_creation_form(): void
    {
        $this->seed(ProductionSeeder::class);
        $this->actingAs(\App\Models\User::query()->where('username', 'admin')->firstOrFail());
        Category::query()->create([
            'code' => 'REM-CATALOG',
            'name_ar' => 'تصنيف المعالجة',
            'name_en' => 'Remediation category',
            'status' => 'active',
            'sort_order' => 1,
        ]);

        Livewire::test('catalog::products')
            ->call('openCreateProductModal')
            ->assertSet('showProductModal', true)
            ->assertStatus(200);
    }
}
