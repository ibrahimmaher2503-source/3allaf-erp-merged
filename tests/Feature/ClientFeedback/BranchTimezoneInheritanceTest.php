<?php

declare(strict_types=1);

namespace Tests\Feature\ClientFeedback;

use App\Modules\Platform\Actions\SaveBranchAction;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

/** CF-04 regression coverage for company timezone inheritance and branch overrides. */
final class BranchTimezoneInheritanceTest extends TestCase
{
    use DatabaseTransactions;
    use PlatformFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCanonicalAuthorization();
        $this->actingAs($this->administrator('cf004-timezone-admin'));
    }

    public function test_create_modal_defaults_to_the_active_company_timezone(): void
    {
        $this->activeCompanyWithTimezone('Africa/Cairo');

        Livewire::test('platform::admin.branches')
            ->call('openCreateBranchModal')
            ->assertSet('branchForm.timezone', 'Africa/Cairo');
    }

    public function test_create_form_saves_and_reloads_the_inherited_company_timezone(): void
    {
        $this->activeCompanyWithTimezone('Africa/Cairo');

        $component = Livewire::test('platform::admin.branches')
            ->call('openCreateBranchModal')
            ->set('branchForm.code', 'CF04-INHERITED')
            ->set('branchForm.name_ar', 'فرع التوقيت الموروث')
            ->set('branchForm.name_en', 'Inherited Timezone Branch')
            ->call('saveBranch')
            ->assertHasNoErrors();

        $branch = Branch::query()->where('code', 'CF04-INHERITED')->firstOrFail();

        self::assertSame('Africa/Cairo', $branch->timezone);

        $component
            ->call('openEditBranchModal', $branch->id)
            ->assertSet('branchForm.timezone', 'Africa/Cairo');
    }

    public function test_explicit_timezone_override_is_respected_when_creating_a_branch(): void
    {
        $this->activeCompanyWithTimezone('Africa/Cairo');

        $branch = app(SaveBranchAction::class)->execute([
            'code' => 'CF04-OVERRIDE',
            'name_ar' => 'فرع توقيت صريح',
            'name_en' => 'Explicit Timezone Branch',
            'timezone' => 'Asia/Riyadh',
        ]);

        self::assertSame('Asia/Riyadh', $branch->fresh()->timezone);
    }

    public function test_action_update_without_timezone_preserves_the_existing_branch_timezone(): void
    {
        $company = $this->activeCompanyWithTimezone('Africa/Cairo');
        $branch = $this->branchWithTimezone('CF04-ACTION-EDIT', 'Asia/Riyadh');

        $company->update(['timezone' => 'Europe/London']);

        app(SaveBranchAction::class)->execute([
            'code' => $branch->code,
            'name_ar' => 'فرع محدث',
            'name_en' => 'Updated Branch',
        ], $branch->id);

        self::assertSame('Asia/Riyadh', $branch->fresh()->timezone);
    }

    public function test_ui_edit_preserves_the_explicit_branch_timezone_after_the_company_default_changes(): void
    {
        $company = $this->activeCompanyWithTimezone('Africa/Cairo');
        $branch = $this->branchWithTimezone('CF04-UI-EDIT', 'Asia/Riyadh');

        $company->update(['timezone' => 'Europe/London']);

        Livewire::test('platform::admin.branches')
            ->call('openEditBranchModal', $branch->id)
            ->assertSet('branchForm.timezone', 'Asia/Riyadh')
            ->set('branchForm.name_en', 'UI Updated Branch')
            ->call('saveBranch')
            ->assertHasNoErrors();

        self::assertSame('Asia/Riyadh', $branch->fresh()->timezone);
    }

    private function activeCompanyWithTimezone(string $timezone): Company
    {
        Company::query()->update(['status' => 'inactive']);

        $company = $this->company();
        $company->update([
            'timezone' => $timezone,
            'status' => 'active',
        ]);

        return $company->fresh();
    }

    private function branchWithTimezone(string $code, string $timezone): Branch
    {
        $branch = $this->branch($code);
        $branch->update(['timezone' => $timezone]);

        return $branch->fresh();
    }
}
