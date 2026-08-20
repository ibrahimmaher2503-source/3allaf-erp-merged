<?php

declare(strict_types=1);

namespace Tests\Feature\ClientFeedback\Diagnostics;

use App\Modules\Platform\Actions\SaveBranchAction;
use App\Modules\Platform\Models\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

/**
 * DI-001 reproduction evidence only. This intentional RED file is excluded from phpunit.cr003.xml
 * while the scoped-authorization defect awaits its own authorized remediation.
 */
final class BranchScopeIsolationReproductionTest extends TestCase
{
    use PlatformFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCanonicalAuthorization();
    }

    public function test_a_branch_a_scoped_editor_cannot_hydrate_branch_b_through_a_forged_livewire_action(): void
    {
        $branchA = $this->branch('DI001-SCOPE-A');
        $branchB = $this->branch('DI001-SCOPE-B');
        $this->actingAs($this->userWith('di001-scoped-editor', ['system-administrator'], false, [$branchA->id]));

        // Intentional RED: the component must deny a forged Branch B hydration outside Branch A scope.
        Livewire::test('platform::admin.branches')
            ->call('openEditBranchModal', $branchB->id)
            ->assertForbidden();
    }

    public function test_a_branch_a_scoped_editor_cannot_save_branch_b_through_a_forged_write_action(): void
    {
        $branchA = $this->branch('DI001-SAVE-A');
        $branchB = $this->branch('DI001-SAVE-B');
        $this->actingAs($this->userWith('di001-scoped-writer', ['system-administrator'], false, [$branchA->id]));

        // Intentional RED: the real action must reject the cross-scope write before any mutation.
        app(SaveBranchAction::class)->execute([
            'code' => 'DI001-FORGED-B',
            'name_ar' => 'فرع ب مزوّر',
            'name_en' => 'Forged Branch B',
            'timezone' => 'Africa/Cairo',
            'status' => 'active',
        ], $branchB->id);

        self::fail('A Branch A-scoped editor saved Branch B through the direct branch write action.');
    }
}
