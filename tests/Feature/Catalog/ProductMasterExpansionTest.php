<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Actions\SaveProductAction;
use App\Modules\Catalog\Models\AgeLabel;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Character;
use App\Modules\Catalog\Models\Colour;
use App\Modules\Catalog\Models\Gender;
use App\Modules\Catalog\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

final class ProductMasterExpansionTest extends TestCase
{
    use PlatformFixtures;
    use RefreshDatabase;

    public function test_product_master_persists_expanded_fields_and_explicit_lookup_associations(): void
    {
        $this->seedCanonicalAuthorization();
        $this->actingAs($admin = $this->administrator('product-expansion'));
        $category = Category::factory()->create();
        $supplier = Supplier::query()->create(['code' => 'EXP-SUP', 'name_ar' => 'مورد', 'name_en' => 'Supplier', 'status' => 'active']);
        $age = AgeLabel::query()->create(['code' => 'A-1', 'name_ar' => '١', 'name_en' => '1', 'status' => 'active']);
        $character = Character::query()->create(['code' => 'C-1', 'name_ar' => 'دب', 'name_en' => 'Bear', 'status' => 'active']);
        $colour = Colour::query()->create(['code' => 'R', 'name_ar' => 'أحمر', 'name_en' => 'Red', 'status' => 'active']);
        $gender = Gender::query()->create(['code' => 'U', 'name_ar' => 'للجميع', 'name_en' => 'Unisex', 'status' => 'active']);

        $product = app(SaveProductAction::class)->execute([
            'item_code' => 'EXP-001', 'name_ar' => 'لعبة', 'name_en' => 'Toy', 'category_id' => $category->id,
            'average_cost' => 12.50, 'sale_price' => 19.99, 'unit_of_measure' => 'piece', 'weight' => 1.25,
            'dimension_length' => 10, 'dimension_width' => 5, 'dimension_height' => 3, 'dimension_unit' => 'cm',
            'battery_required' => true, 'battery_details' => '2 AA', 'preferred_supplier_id' => $supplier->id,
            'age_label_ids' => [$age->id], 'character_ids' => [$character->id], 'colour_ids' => [$colour->id], 'gender_ids' => [$gender->id],
        ]);

        $this->assertSame('19.99', (string) $product->fresh()->sale_price);
        $this->assertTrue((bool) $product->fresh()->battery_required);
        $this->assertSame($supplier->id, $product->preferredProductSupplier()->value('supplier_id'));
        $this->assertTrue($product->ages()->whereKey($age)->exists());
        $this->assertTrue($product->characters()->whereKey($character)->exists());
        $this->assertTrue($product->colours()->whereKey($colour)->exists());
        $this->assertTrue($product->genders()->whereKey($gender)->exists());
    }

    public function test_product_master_persists_optional_web_seo_fields_and_rejects_duplicate_slug(): void
    {
        $this->seedCanonicalAuthorization();
        $this->actingAs($this->administrator('product-web'));
        $category = Category::factory()->create();
        $data = ['item_code' => 'WEB-001', 'name_ar' => 'لعبة', 'name_en' => 'Toy', 'category_id' => $category->id,
            'short_description_ar' => 'وصف قصير', 'short_description_en' => 'Short description', 'full_description_ar' => 'تفاصيل', 'full_description_en' => 'Details',
            'meta_title_ar' => 'عنوان', 'meta_title_en' => 'Title', 'meta_description_ar' => 'وصف محرك البحث', 'meta_description_en' => 'SEO description',
            'seo_slug' => 'toy-001', 'publish_visibility' => 'catalog', 'sort_order' => 4];
        $product = app(SaveProductAction::class)->execute($data);
        $this->assertSame('toy-001', $product->seo_slug);
        $this->assertSame('catalog', $product->publish_visibility);
        $this->assertSame(4, $product->sort_order);
        $this->expectException(\InvalidArgumentException::class);
        app(SaveProductAction::class)->execute([...$data, 'item_code' => 'WEB-002']);
    }
}
