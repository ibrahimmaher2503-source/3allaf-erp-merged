<?php

namespace Tests\Feature\Platform;

use App\Modules\Platform\Models\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

/**
 * TSK-004 — Shared UI foundation (server-facing behavior only).
 *
 * Visual state coverage (loading, focus, contrast, print preview) stays with the
 * required manual browser review; these tests pin the server contracts the
 * shared patterns depend on.
 *
 * @group tsk-004
 */
class SharedUiFoundationTest extends TestCase
{
    use PlatformFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCanonicalAuthorization();
    }

    public function test_the_shared_state_and_table_components_exist(): void
    {
        foreach ([
            'components.state.empty',
            'components.state.error',
            'components.state.loading',
            'components.state.denied',
            'components.tables.data-panel',
            'components.tables.filter-bar',
            'components.forms.form-section',
            'components.status.badge',
            'components.status.timeline',
            'components.cards.section-card',
            'components.cards.stat-card',
            'components.page-header',
            'components.audit-panel',
            'layouts.print',
        ] as $view) {
            $this->assertTrue(view()->exists($view), "Missing shared view [{$view}].");
        }
    }

    public function test_a_shared_form_returns_inline_server_validation_errors(): void
    {
        $this->actingAs($this->administrator('tsk004-admin'));

        Livewire::test('platform::admin.branches')
            ->call('openCreateBranchModal')
            ->call('saveBranch')
            ->assertHasErrors([
                'branchForm.code' => 'required',
                'branchForm.name_ar' => 'required',
                'branchForm.name_en' => 'required',
            ])
            ->assertSet('showBranchModal', true);
    }

    public function test_field_level_rules_are_enforced_by_the_server_not_the_markup(): void
    {
        $this->actingAs($this->administrator('tsk004-rules'));

        Livewire::test('platform::admin.branches')
            ->call('openCreateBranchModal')
            ->set('branchForm.code', str_repeat('X', 31))
            ->set('branchForm.name_ar', 'اسم')
            ->set('branchForm.name_en', 'Name')
            ->set('branchForm.email', 'not-an-email')
            ->set('branchForm.status', 'unknown-status')
            ->call('saveBranch')
            ->assertHasErrors([
                'branchForm.code' => 'max',
                'branchForm.email' => 'email',
                'branchForm.status' => 'in',
            ]);

        $this->assertDatabaseCount('branches', 0);
    }

    public function test_a_shared_table_paginates_on_the_server(): void
    {
        $this->actingAs($this->administrator('tsk004-pagination'));

        for ($index = 1; $index <= 25; $index++) {
            $this->branch(sprintf('PAGE-%03d', $index));
        }

        $component = Livewire::test('platform::admin.branches');
        $component->assertSee('PAGE-001')->assertDontSee('PAGE-011');

        $component->call('gotoPage', 2)->assertSee('PAGE-011')->assertDontSee('PAGE-001');
        $component->call('gotoPage', 3)->assertSee('PAGE-021');
    }

    public function test_search_and_status_filters_narrow_the_result_set_and_reset_the_page(): void
    {
        $this->actingAs($this->administrator('tsk004-filters'));

        $this->branch('FILTER-ALPHA');
        $this->branch('FILTER-BETA', 'inactive');

        Livewire::test('platform::admin.branches')
            ->set('search', 'ALPHA')
            ->assertSee('FILTER-ALPHA')
            ->assertDontSee('FILTER-BETA')
            ->set('search', '')
            ->set('statusFilter', 'inactive')
            ->assertSee('FILTER-BETA')
            ->assertDontSee('FILTER-ALPHA')
            ->assertSet('paginators.page', 1);
    }

    public function test_an_empty_result_set_renders_the_shared_empty_state(): void
    {
        $this->actingAs($this->administrator('tsk004-empty'));

        Livewire::test('platform::admin.branches')
            ->assertSee('No Branches Configured');
    }

    public function test_a_permission_denied_action_is_refused_on_the_server(): void
    {
        $this->actingAs($this->userWith('tsk004-viewer', ['branch-manager']));

        Livewire::test('platform::admin.branches')
            ->call('openCreateBranchModal')
            ->assertForbidden();

        Livewire::test('platform::admin.branches')
            ->call('deleteBranch', 1)
            ->assertForbidden();
    }

    public function test_a_repeated_identical_submission_does_not_create_a_duplicate_record(): void
    {
        $this->actingAs($this->administrator('tsk004-duplicate'));

        $component = Livewire::test('platform::admin.branches')
            ->call('openCreateBranchModal')
            ->set('branchForm.code', 'DUP-001')
            ->set('branchForm.name_ar', 'فرع مكرر')
            ->set('branchForm.name_en', 'Duplicate Branch')
            ->call('saveBranch');

        $component->assertSet('showBranchModal', false);
        $this->assertDatabaseCount('branches', 1);

        // Replaying the same create submission must be rejected by the unique rule.
        $component->call('openCreateBranchModal')
            ->set('branchForm.code', 'DUP-001')
            ->set('branchForm.name_ar', 'فرع مكرر')
            ->set('branchForm.name_en', 'Duplicate Branch')
            ->call('saveBranch')
            ->assertHasErrors(['branchForm.code' => 'unique']);

        $this->assertDatabaseCount('branches', 1);
        $this->assertSame(1, Branch::query()->where('code', 'DUP-001')->count());
    }

    public function test_the_ui_pattern_showcase_renders_every_shared_state_for_an_authorized_user(): void
    {
        $this->actingAs($this->administrator('tsk004-showcase'));

        $this->get('/admin/system/ui-showcase')->assertOk();

        Livewire::test('platform::system.ui-showcase')
            ->assertOk()
            ->call('toggleLoading')
            ->assertSet('isSimulatingLoading', true)
            ->call('toggleLoading')
            ->assertSet('isSimulatingLoading', false);
    }

    public function test_the_showcase_is_denied_without_the_dashboard_permission(): void
    {
        $this->actingAs($this->userWith('tsk004-denied'));

        $this->get('/admin/system/ui-showcase')->assertForbidden();
    }

    public function test_no_dedicated_print_route_is_implemented_yet(): void
    {
        // Recorded coverage fact for TSK-004: `layouts/print` exists as a shared
        // base layout, but no route renders a printable document today.
        $printRoutes = collect(\Illuminate\Support\Facades\Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_contains($route->uri(), 'print'));

        $this->assertTrue($printRoutes->isEmpty());
        $this->assertTrue(view()->exists('layouts.print'));
    }
}
