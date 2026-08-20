<?php

declare(strict_types=1);

namespace Tests\Feature\ClientFeedback;

use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Models\Branch;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

/** CF-03 reproduction of ordinary Branches creation capacity and code normalization behavior. */
final class BranchCreationCapacityTest extends TestCase
{
    use DatabaseTransactions;
    use PlatformFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCanonicalAuthorization();
    }

    public function test_an_authorized_administrator_can_create_and_reload_six_distinct_branches_for_the_active_company(): void
    {
        $this->actingAs($this->administrator('cf003-six-branches'));
        $company = $this->company();

        foreach (range(1, 6) as $number) {
            $code = sprintf('CF03-%02d', $number);

            // Catches a capacity or state mutation that prevents a legitimate distinct branch from being created.
            Livewire::test('platform::admin.branches')
                ->call('openCreateBranchModal')
                ->set('branchForm', [
                    'code' => $code,
                    'name_ar' => 'فرع الاختبار '.$number,
                    'name_en' => 'Capacity Branch '.$number,
                    'phone' => '',
                    'email' => '',
                    'address' => '',
                    'timezone' => 'Africa/Cairo',
                    'status' => 'active',
                    'policy_notes' => 'CF-03 disposable capacity fixture.',
                ])
                ->call('saveBranch')
                ->assertHasNoErrors()
                ->assertSet('showBranchModal', false);

            self::assertDatabaseHas('branches', [
                'company_id' => $company->id,
                'code' => $code,
                'name_en' => 'Capacity Branch '.$number,
            ]);
        }

        self::assertSame(6, Branch::query()->where('company_id', $company->id)->whereIn('code', ['CF03-01', 'CF03-02', 'CF03-03', 'CF03-04', 'CF03-05', 'CF03-06'])->count());
        Livewire::test('platform::admin.branches')
            ->assertSee('CF03-01')
            ->assertSee('CF03-06');
    }

    public function test_a_case_and_whitespace_variant_of_an_existing_code_returns_inline_validation_without_mutation(): void
    {
        $this->actingAs($this->administrator('cf003-normalization'));
        $company = $this->company();

        Livewire::test('platform::admin.branches')
            ->call('openCreateBranchModal')
            ->set('branchForm', [
                'code' => 'ABC',
                'name_ar' => 'فرع أساس',
                'name_en' => 'ABC Branch',
                'phone' => '',
                'email' => '',
                'address' => '',
                'timezone' => 'Africa/Cairo',
                'status' => 'active',
                'policy_notes' => 'CF-03 normalization fixture.',
            ])
            ->call('saveBranch')
            ->assertHasNoErrors();

        $auditCountBeforeDuplicate = AuditLog::query()->where('event', 'create_branch')->count();

        // Intentional RED: ` abc ` normalizes to the existing ABC business identity and must be rejected inline before any raw database exception or write.
        $duplicateForm = Livewire::test('platform::admin.branches')
            ->call('openCreateBranchModal')
            ->set('branchForm', [
                'code' => ' abc ',
                'name_ar' => 'فرع مكرر',
                'name_en' => 'Duplicate ABC Branch',
                'phone' => '',
                'email' => '',
                'address' => '',
                'timezone' => 'Africa/Cairo',
                'status' => 'active',
                'policy_notes' => 'CF-03 normalization fixture.',
            ])
            ->call('saveBranch');

        $duplicateForm
            ->assertSet('branchForm.code', 'ABC')
            ->assertHasErrors(['branchForm.code' => 'unique']);

        self::assertSame(1, Branch::query()->where('company_id', $company->id)->where('code', 'ABC')->count(), 'A normalized duplicate must not create a second branch row.');
        self::assertSame($auditCountBeforeDuplicate, AuditLog::query()->where('event', 'create_branch')->count(), 'A normalized duplicate must not append an audit record.');
    }
}
