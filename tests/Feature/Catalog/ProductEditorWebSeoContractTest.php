<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Models\AgeLabel;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Character;
use App\Modules\Catalog\Models\Colour;
use App\Modules\Catalog\Models\Gender;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

final class ProductEditorWebSeoContractTest extends TestCase
{
    use PlatformFixtures;
    use DatabaseTransactions;

    public function test_product_editor_exposes_web_seo_and_multi_value_lookup_controls(): void
    {
        $this->seedCanonicalAuthorization();
        $this->actingAs($this->administrator('product-editor-contract'));
        Category::factory()->create();

        foreach ([AgeLabel::class, Character::class, Colour::class, Gender::class] as $model) {
            $model::query()->create(['code' => class_basename($model), 'name_ar' => 'قيمة', 'name_en' => class_basename($model), 'status' => 'active']);
        }

        $this->get(route('catalog.products.create'))
            ->assertOk()
            ->assertSee('Product web & SEO')
            ->assertSee('productForm.short_description_ar', false)
            ->assertSee('productForm.seo_slug', false)
            ->assertSee('productForm.age_label_ids', false)
            ->assertSee('productForm.character_ids', false)
            ->assertSee('productForm.colour_ids', false)
            ->assertSee('productForm.gender_ids', false);
    }
}
