<?php

declare(strict_types=1);

namespace Tests\Feature\ClientFeedback;

use App\Modules\Platform\Models\AuditLog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

/** CF-02 regression for the canonical Branch identity shown by linked Stores. */
final class BranchSourceOfTruthTest extends TestCase
{
    use PlatformFixtures;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCanonicalAuthorization();
    }

    public function test_an_authorized_branch_identity_edit_persists_and_linked_stores_reload_it(): void
    {
        $this->actingAs($this->administrator('cr003-branch-a-editor'));
        $branch = $this->branch('CR003-A');
        $store = $this->store($branch, 'CR003-A-SELL');

        $identity = [
            'code' => 'CR003-A-RELOADED',
            'name_ar' => 'فرع القاهرة المحدّث',
            'name_en' => 'Cairo Branch Reloaded',
            'phone' => '+201001234567',
            'email' => 'branch-a@toyjoy.test',
            'address' => 'A Street, Cairo',
            'timezone' => 'Africa/Cairo',
            'status' => 'active',
            'policy_notes' => 'CR-003 legitimate edit fixture.',
        ];

        // Catches a production mutation that writes a detached branch copy or fails to persist a branch identity field.
        Livewire::test('platform::admin.branches')
            ->call('openEditBranchModal', $branch->id)
            ->set('branchForm', $identity)
            ->call('saveBranch')
            ->assertHasNoErrors()
            ->assertSet('showBranchModal', false);

        $persisted = $branch->fresh();
        foreach (['code', 'name_ar', 'name_en'] as $field) {
            self::assertSame($identity[$field], $persisted->getAttribute($field), "Branch A [{$field}] must persist on the canonical branch row.");
        }

        self::assertSame($identity['code'], $store->fresh()->branch->code, 'The Store representation must reload Branch A from its relation.');
        self::assertSame($identity['name_ar'], $store->fresh()->branch->name_ar, 'The Store Arabic representation must reload Branch A from its relation.');
        self::assertSame($identity['name_en'], $store->fresh()->branch->name_en, 'The Store English representation must reload Branch A from its relation.');

        Livewire::test('platform::admin.branches')
            ->assertSee($identity['code'])
            ->assertSee($identity['name_ar'])
            ->assertSee($identity['name_en']);
        Livewire::test('platform::admin.stores')
            ->assertSee($identity['code'])
            ->assertSee($identity['name_en']);

        $audit = AuditLog::query()
            ->where('event', 'update_branch')
            ->where('source_id', (string) $branch->id)
            ->sole();
        self::assertSame((string) $branch->id, $audit->source_id, 'The persisted canonical Branch row must be the audited source.');
        self::assertSame($branch->id, $audit->branch_id, 'The branch identity audit must carry Branch A scope.');
    }

}
