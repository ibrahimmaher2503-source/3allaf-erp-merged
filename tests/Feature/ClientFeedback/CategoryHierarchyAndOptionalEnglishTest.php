<?php

declare(strict_types=1);

namespace Tests\Feature\ClientFeedback;

use App\Modules\Catalog\Actions\SaveCategoryAction;
use App\Modules\Catalog\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

/**
 * Req 36–37: category authoring and deterministic hierarchical display.
 *
 * This focused contract intentionally runs in the disposable MariaDB client
 * feedback profile only; it must not be pointed at SQLite or Production data.
 */
final class CategoryHierarchyAndOptionalEnglishTest extends TestCase
{
    use PlatformFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCanonicalAuthorization();
        $this->actingAs($this->administrator('category-remediation-admin'));
    }

    public function test_english_name_is_optional_for_generic_category_creation(): void
    {
        Livewire::test('catalog::categories')
            ->call('openCreateCategoryModal')
            ->set('categoryForm.code', 'ARABIC-ONLY')
            ->set('categoryForm.name_ar', 'ألعاب أطفال')
            ->set('categoryForm.name_en', '')
            ->set('categoryForm.parent_id', '')
            ->set('categoryForm.status', 'active')
            ->set('categoryForm.sort_order', 10)
            ->call('saveCategory')
            ->assertHasNoErrors()
            ->assertSet('showCategoryModal', false);

        $this->assertDatabaseHas('categories', [
            'code' => 'ARABIC-ONLY',
            'name_ar' => 'ألعاب أطفال',
            'name_en' => '',
        ]);
    }

    public function test_category_rows_render_roots_and_children_in_sibling_order(): void
    {
        $action = app(SaveCategoryAction::class);

        $rootA = $action->execute([
            'code' => 'ROOT-A', 'name_ar' => 'جذر أ', 'name_en' => 'Root A',
            'parent_id' => null, 'status' => 'active', 'sort_order' => 10,
        ]);
        $action->execute([
            'code' => 'ROOT-B', 'name_ar' => 'جذر ب', 'name_en' => 'Root B',
            'parent_id' => null, 'status' => 'active', 'sort_order' => 20,
        ]);
        $action->execute([
            'code' => 'CHILD-A', 'name_ar' => 'طفل أ', 'name_en' => 'Child A',
            'parent_id' => $rootA->id, 'status' => 'active', 'sort_order' => 1,
        ]);

        Livewire::test('catalog::categories')
            ->assertSeeInOrder(['ROOT-A', 'CHILD-A', 'ROOT-B']);

        self::assertSame(
            ['CHILD-A'],
            Category::query()->whereKey($rootA->id)->firstOrFail()->children()->pluck('code')->all(),
        );
    }
}
