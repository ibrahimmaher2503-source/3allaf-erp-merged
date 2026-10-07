<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Models\User;
use App\Modules\Catalog\Models\AgeLabel;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Character;
use App\Modules\Catalog\Models\Product;
use App\Modules\Platform\Models\Company;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

final class ProductCardMetadataFormTest extends TestCase
{
    use DatabaseTransactions;

    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Gate::before(static fn (User $user, string $ability): bool => true);
        Company::factory()->create();
        $this->administrator = User::factory()->create([
            'status' => 'active',
            'is_super_admin' => true,
        ]);
    }

    public function test_product_card_creates_and_edits_descriptive_fields_and_lookup_assignments_without_cost_access(): void
    {
        $parentCategory = Category::factory()->create([
            'code' => 'QA-META-PARENT',
            'name_en' => 'QA metadata parent',
        ]);
        $subcategory = Category::factory()->create([
            'code' => 'QA-META-CHILD',
            'name_en' => 'QA metadata child',
            'parent_id' => $parentCategory->id,
        ]);
        $ageLabel = AgeLabel::query()->create([
            'code' => 'QA-META-AGE',
            'name_ar' => 'عمر اختبار',
            'name_en' => 'QA age group',
            'status' => 'active',
        ]);
        $inactiveAgeLabel = AgeLabel::query()->create([
            'code' => 'QA-META-AGE-INACTIVE',
            'name_ar' => 'فئة عمرية غير نشطة',
            'name_en' => 'Inactive QA age group',
            'status' => 'inactive',
        ]);
        $character = Character::query()->create([
            'code' => 'QA-META-CHARACTER',
            'name_ar' => 'شخصية اختبار',
            'name_en' => 'QA character',
            'status' => 'active',
        ]);

        self::assertFalse($this->administrator->hasPermission('products_categories_brands.cost_view'));

        $this->app->setLocale('ar');
        Livewire::actingAs($this->administrator)
            ->test('catalog::product-form')
            ->assertSee('اللون')
            ->assertSee('الفئات العمرية')
            ->assertSee('الشخصيات')
            ->assertSee('كلمات البحث بالعربية')
            ->assertSeeHtml('wire:model="productForm.key_points_ar"')
            ->assertSeeHtml('dir="rtl"')
            ->assertSeeHtml('dir="ltr"');
        $this->app->setLocale('en');

        $createForm = Livewire::actingAs($this->administrator)
            ->test('catalog::product-form')
            ->assertSee('QA metadata parent / QA metadata child')
            ->assertSeeHtml('wire:model="productForm.colour"')
            ->assertSeeHtml('wire:model="productForm.key_points_ar"')
            ->assertSeeHtml('wire:model="productForm.key_points_en"')
            ->assertSeeHtml('wire:model="productForm.keywords_ar"')
            ->assertSeeHtml('wire:model="productForm.keywords_en"')
            ->assertSeeHtml('wire:model="productForm.age_label_ids"')
            ->assertSeeHtml('wire:model="productForm.character_ids"')
            ->assertSee('QA age group')
            ->assertSee('QA character')
            ->assertDontSeeHtml('wire:model="productForm.average_cost"')
            ->set('productForm.category_id', (string) $subcategory->id)
            ->set('productForm.name_ar', 'بطاقة اختبار وصفية')
            ->set('productForm.name_en', 'QA metadata product')
            ->set('productForm.model_number', 'QA-META-001')
            ->set('productForm.colour', 'Coral')
            ->set('productForm.key_points_ar', 'نقطة رئيسية')
            ->set('productForm.key_points_en', 'Key point')
            ->set('productForm.keywords_ar', 'كلمة مفتاحية')
            ->set('productForm.keywords_en', 'keyword')
            ->set('productForm.age_label_ids', [(string) $ageLabel->id])
            ->set('productForm.character_ids', [(string) $character->id])
            ->set('productForm.average_cost', '999.99');
        $this->app->setLocale('ar');
        $createForm->set('productForm.age_label_ids', [(string) $inactiveAgeLabel->id])->call('saveDraft');
        $createForm->assertHasErrors(['productForm.age_label_ids.0']);
        $this->app->setLocale('en');
        $createForm
            ->set('productForm.age_label_ids', [(string) $ageLabel->id])
            ->call('saveDraft');

        $product = Product::query()->where('name_en', 'QA metadata product')->firstOrFail();

        self::assertSame($subcategory->id, $product->category_id);
        self::assertSame('Coral', $product->colour);
        self::assertSame('نقطة رئيسية', $product->key_points_ar);
        self::assertSame('Key point', $product->key_points_en);
        self::assertSame('كلمة مفتاحية', $product->keywords_ar);
        self::assertSame('keyword', $product->keywords_en);
        self::assertSame([(int) $ageLabel->id], $product->ages()->pluck('age_labels.id')->all());
        self::assertSame([(int) $character->id], $product->characters()->pluck('characters.id')->all());
        self::assertNull($product->average_cost);

        $product->forceFill(['average_cost' => '17.50', 'status' => 'inactive'])->save();

        $editForm = Livewire::actingAs($this->administrator)
            ->test('catalog::product-form', ['product' => $product->fresh()])
            ->assertSet('productForm.category_id', (string) $subcategory->id)
            ->assertSet('productForm.colour', 'Coral')
            ->assertSet('productForm.key_points_ar', 'نقطة رئيسية')
            ->assertSet('productForm.key_points_en', 'Key point')
            ->assertSet('productForm.average_cost', '')
            ->assertSet('productForm.age_label_ids', [(string) $ageLabel->id])
            ->assertSet('productForm.character_ids', [(string) $character->id])
            ->assertDontSeeHtml('wire:model="productForm.average_cost"')
            ->set('productForm.average_cost', '')
            ->set('productForm.colour', 'Emerald')
            ->set('productForm.key_points_ar', 'نقطة رئيسية معدلة')
            ->set('productForm.key_points_en', 'Edited key point')
            ->set('productForm.keywords_ar', 'كلمات معدلة')
            ->set('productForm.keywords_en', 'edited keyword')
            ->call('saveDraft');

        self::assertSame('17.50', $product->fresh()->average_cost);
        self::assertSame('Emerald', $product->fresh()->colour);
        self::assertSame('نقطة رئيسية معدلة', $product->fresh()->key_points_ar);
        self::assertSame('Edited key point', $product->fresh()->key_points_en);
        self::assertSame('كلمات معدلة', $product->fresh()->keywords_ar);
        self::assertSame('edited keyword', $product->fresh()->keywords_en);

        $editForm->set('productForm.average_cost', '888.88')->call('saveDraft');

        self::assertSame('17.50', $product->fresh()->average_cost);
        self::assertSame([(int) $ageLabel->id], $product->fresh()->ages()->pluck('age_labels.id')->all());
        self::assertSame([(int) $character->id], $product->fresh()->characters()->pluck('characters.id')->all());

        $staleVersion = (int) $product->fresh()->lock_version;
        $staleName = 'Stale edit';
        $winnerName = 'Concurrent winner';
        $editForm->set('productForm.name_en', $staleName);
        $product->forceFill([
            'name_en' => $winnerName,
            'lock_version' => $staleVersion + 1,
        ])->save();

        foreach (['en', 'ar', 'ar-EG'] as $locale) {
            $this->app->setLocale($locale);
            $editForm->call('saveDraft')
                ->assertSet('productVersion', $staleVersion)
                ->assertSet('productForm.name_en', $staleName)
                ->assertDispatched('toast-show', fn (string $event, array $parameters): bool => ($parameters['slots']['text'] ?? null) === __('This product changed in another session. Reload it before saving.'));

            self::assertSame($winnerName, $product->fresh()->name_en);
            self::assertSame($staleVersion + 1, (int) $product->fresh()->lock_version);
        }
    }
}
