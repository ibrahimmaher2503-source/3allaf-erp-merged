<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Actions\SaveProductAction;
use App\Modules\Catalog\Models\AgeLabel;
use App\Modules\Catalog\Models\Character;
use App\Modules\Catalog\Models\Colour;
use App\Modules\Catalog\Models\Gender;
use App\Modules\Catalog\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

final class CatalogLookupMastersTest extends TestCase
{
    use PlatformFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCanonicalAuthorization();
    }

    public function test_dedicated_lookup_masters_store_bilingual_labels_and_status(): void
    {
        $admin = $this->administrator('catalog-lookups');
        $this->actingAs($admin);
        $age = AgeLabel::query()->create(['code' => 'AGE-3-5', 'name_ar' => '٣ إلى ٥ سنوات', 'name_en' => '3 to 5 years', 'status' => 'active', 'sort_order' => 10]);
        $character = Character::query()->create(['code' => 'BEAR', 'name_ar' => 'دب', 'name_en' => 'Bear', 'status' => 'active', 'sort_order' => 10]);
        $colour = Colour::query()->create(['code' => 'RED', 'name_ar' => 'أحمر', 'name_en' => 'Red', 'status' => 'active', 'sort_order' => 10]);
        $gender = Gender::query()->create(['code' => 'UNISEX', 'name_ar' => 'للجنسين', 'name_en' => 'Unisex', 'status' => 'active', 'sort_order' => 10]);

        $this->assertSame('3 to 5 years', $age->name_en);
        $this->assertSame('دب', $character->name_ar);
        $this->assertSame('active', $colour->status);
        $this->assertSame(10, $gender->sort_order);
        $this->assertTrue($admin->can('products_categories_brands.edit'));
    }

    public function test_active_lookup_cannot_be_deactivated_when_referenced_by_product(): void
    {
        $this->actingAs($this->administrator('catalog-lookups'));
        $category = Category::factory()->create(['code' => 'LOOKUP-CAT']);
        $age = AgeLabel::query()->create(['code' => 'AGE-REF', 'name_ar' => 'مرجع', 'name_en' => 'Referenced', 'status' => 'active', 'sort_order' => 1]);
        $product = app(SaveProductAction::class)->execute(['item_code' => 'LOOKUP-001', 'name_ar' => 'لعبة', 'name_en' => 'Toy', 'category_id' => $category->id, 'age_label_id' => $age->id, 'target_age' => 'legacy']);

        $this->assertSame($age->id, $product->age_label_id);
        $this->expectException(InvalidArgumentException::class);
        $age->update(['status' => 'inactive']);
    }
}
