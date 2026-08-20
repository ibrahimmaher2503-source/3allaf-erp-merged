<?php

declare(strict_types=1);

namespace Tests\Feature\ClientFeedback;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Platform\Models\CashDrawer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

final class DestructiveActionConfirmationUiTest extends TestCase
{
    use PlatformFixtures;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCanonicalAuthorization();
        $this->actingAs($this->administrator('destructive-confirmation-ui'));
    }

    public function test_destructive_actions_render_an_inline_native_confirmation_guard(): void
    {
        $branch = $this->branch('CONFIRM-UI');
        $store = $this->store($branch, 'CONFIRM-STORE', 'warehouse');

        $category = Category::query()->create([
            'code' => 'CONFIRM-CATEGORY',
            'name_ar' => 'تصنيف التأكيد',
            'name_en' => 'Confirmation Category',
            'status' => 'active',
            'sort_order' => 0,
        ]);
        $supplier = Supplier::query()->create([
            'code' => 'CONFIRM-SUPPLIER',
            'name_ar' => 'مورد التأكيد',
            'name_en' => 'Confirmation Supplier',
            'status' => 'active',
        ]);
        $drawer = CashDrawer::query()->create([
            'company_id' => $branch->company_id,
            'branch_id' => $branch->id,
            'store_id' => $store->id,
            'code' => 'CONFIRM-DRAWER',
            'name_ar' => 'درج التأكيد',
            'name_en' => 'Confirmation Drawer',
            'status' => 'active',
        ]);

        $this->assertNativeConfirmationGuard(
            Livewire::test('catalog::categories')->html(),
            'CONFIRM-CATEGORY',
            'toggleCategoryStatus('.$category->id.')',
        );
        $this->assertNativeConfirmationGuard(
            Livewire::test('catalog::suppliers')->html(),
            'CONFIRM-SUPPLIER',
            'toggleSupplierStatus('.$supplier->id.')',
        );
        $this->assertNativeConfirmationGuard(
            Livewire::test('platform::admin.branches')->html(),
            'CONFIRM-UI',
            'toggleBranchStatus('.$branch->id.')',
        );
        $this->assertNativeConfirmationGuard(
            Livewire::test('platform::admin.drawers')->html(),
            'CONFIRM-DRAWER',
            'toggleDrawerStatus('.$drawer->id.', &#039;inactive&#039;)',
        );
    }

    public function test_opening_and_closing_archive_review_modal_does_not_submit_status_change_or_approval(): void
    {
        $branch = $this->branch('ARCHIVE-CONFIRM');
        $store = $this->store($branch, 'ARCHIVE-CONFIRM-STORE', 'warehouse');

        $this->assertArchiveWasNotSubmitted($store->id);

        $component = Livewire::test('platform::admin.stores')
            ->call('openArchiveModal', $store->id)
            ->assertSet('showArchiveModal', true)
            ->assertSee('Request archive approval')
            ->assertSee('ARCHIVE-CONFIRM-STORE');

        $this->assertArchiveWasNotSubmitted($store->id);

        $component
            ->set('showArchiveModal', false)
            ->assertSet('showArchiveModal', false);

        $this->assertArchiveWasNotSubmitted($store->id);
    }

    private function assertNativeConfirmationGuard(string $html, string $fixtureCode, string $wireAction): void
    {
        self::assertMatchesRegularExpression(
            '/'.preg_quote($fixtureCode, '/').'.*?wire:click="'.preg_quote($wireAction, '/').'".*?onclick="[^\"]*window\\.confirm/s',
            $html,
        );
    }

    private function assertArchiveWasNotSubmitted(int $storeId): void
    {
        $this->assertDatabaseHas('stores', ['id' => $storeId, 'status' => 'active']);
        $this->assertDatabaseMissing('approval_records', [
            'source_type' => 'platform_settings',
            'source_id' => (string) $storeId,
            'requested_action' => 'store_archive',
            'approval_state' => 'pending',
        ]);
    }
}
