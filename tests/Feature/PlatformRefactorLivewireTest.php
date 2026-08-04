<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\LocalDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PlatformRefactorLivewireTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->detectEnvironment(fn (): string => 'local');

        try {
            $this->seed();
        } finally {
            $this->app->detectEnvironment(fn (): string => 'testing');
        }

        $this->actingAs(User::query()->where('username', 'demo-admin')->firstOrFail());
    }

    public function test_every_moved_platform_route_renders_for_an_authenticated_administrator(): void
    {
        foreach ([
            '/admin/settings',
            '/admin/branches',
            '/admin/stores',
            '/admin/cash-drawers',
            '/admin/authorization-baseline',
            '/admin/system/health',
            '/admin/system/ui-showcase',
            '/system/app',
        ] as $path) {
            $this->get($path)->assertOk();
        }
    }

    public function test_moved_livewire_components_hydrate_with_the_platform_namespace(): void
    {
        foreach ([
            'platform::admin.settings',
            'platform::admin.branches',
            'platform::admin.stores',
            'platform::admin.drawers',
            'platform::admin.authorization-baseline',
            'platform::system.health',
            'platform::system.ui-showcase',
        ] as $component) {
            Livewire::test($component)->assertOk();
        }
    }

    public function test_branch_form_validates_and_rerenders_after_a_platform_namespace_action(): void
    {
        Livewire::test('platform::admin.branches')
            ->call('openCreateBranchModal')
            ->assertSet('showBranchModal', true)
            ->call('saveBranch')
            ->assertHasErrors([
                'branchForm.code' => 'required',
                'branchForm.name_ar' => 'required',
                'branchForm.name_en' => 'required',
            ])
            ->assertSet('showBranchModal', true);
    }

    public function test_ui_showcase_rehydrates_after_an_interaction(): void
    {
        Livewire::test('platform::system.ui-showcase')
            ->call('toggleLoading')
            ->assertSet('isSimulatingLoading', true)
            ->call('toggleLoading')
            ->assertSet('isSimulatingLoading', false);
    }
}
