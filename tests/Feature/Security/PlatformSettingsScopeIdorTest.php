<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use App\Modules\Platform\Actions\SaveBranchSellingStoreMappingAction;
use App\Modules\Platform\Actions\SaveLocalSettingsAction;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\DocumentSequence;
use App\Modules\Platform\Models\PrinterConfiguration;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

/**
 * Horizontal-scope regression for Settings, printer previews, and selling-store mappings.
 */
final class PlatformSettingsScopeIdorTest extends TestCase
{
    use DatabaseTransactions;
    use PlatformFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCanonicalAuthorization();
    }

    public function test_a_scoped_settings_actor_only_sees_sequences_and_printers_for_their_branch(): void
    {
        [$actor, $branchA, $branchB] = $this->scopedActorAndBranches();
        $this->sequence($branchA, 'scope-visible-a');
        $sequenceB = $this->sequence($branchB, 'scope-visible-b');
        $printerA = $this->printer($branchA, 'Scope visible printer A');
        $printerB = $this->printer($branchB, 'Scope visible printer B');

        $this->actingAs($actor);
        Livewire::test('platform::admin.settings')
            ->set('activeTab', 'sequences')
            ->assertSee('Scope Visible A')
            ->assertDontSee('Scope Visible B')
            ->set('activeTab', 'printers')
            ->assertSee($printerA->name)
            ->assertDontSee($printerB->name);
    }

    public function test_a_scoped_settings_actor_cannot_submit_changes_for_another_branchs_sequence(): void
    {
        [$actor, $branchA, $branchB] = $this->scopedActorAndBranches();
        $foreignSequence = $this->sequence($branchB, 'scope-edit-b');
        $this->actingAs($actor);

        $this->expectException(ModelNotFoundException::class);
        app(SaveLocalSettingsAction::class)->saveDocumentSequence([
            'document_type' => $foreignSequence->document_type,
            'scope_type' => 'branch',
            'scope_id' => $branchA->id,
            'prefix' => 'FORGED-',
            'padding_length' => 4,
            'next_value' => 1,
            'reset_rule' => 'never',
            'status' => 'active',
        ], $foreignSequence->id);
    }

    public function test_a_scoped_settings_actor_cannot_submit_changes_for_another_branchs_printer(): void
    {
        [$actor, $branchA, $branchB] = $this->scopedActorAndBranches();
        $foreignPrinter = $this->printer($branchB, 'Scope edit printer B');
        $this->actingAs($actor);

        $this->expectException(ModelNotFoundException::class);
        app(SaveLocalSettingsAction::class)->savePrinterConfiguration($this->printerData('Forged printer'), $foreignPrinter->id, [
            'scope_type' => 'branch', 'branch_id' => $branchA->id,
        ]);
    }

    public function test_a_scoped_settings_actor_cannot_open_another_branchs_sequence_editor(): void
    {
        [$actor, , $branchB] = $this->scopedActorAndBranches();
        $foreignSequence = $this->sequence($branchB, 'scope-editor-b');
        $this->actingAs($actor);

        $this->expectException(ModelNotFoundException::class);
        Livewire::test('platform::admin.settings')->call('editDocumentSequence', $foreignSequence->id);
    }

    public function test_a_scoped_settings_actor_cannot_open_another_branchs_printer_editor(): void
    {
        [$actor, , $branchB] = $this->scopedActorAndBranches();
        $foreignPrinter = $this->printer($branchB, 'Scope printer editor B');
        $this->actingAs($actor);

        $this->expectException(ModelNotFoundException::class);
        Livewire::test('platform::admin.settings')->call('editPrinter', $foreignPrinter->id);
    }

    public function test_a_scoped_settings_actor_cannot_forge_another_branch_or_store_when_creating_a_printer_or_open_its_preview(): void
    {
        [$actor, $branchA, $branchB] = $this->scopedActorAndBranches();
        $foreignStore = $this->store($branchB, 'SCOPE-PRINTER-B');
        $foreignPrinter = $this->printer($branchB, 'Scope preview printer B');
        $this->actingAs($actor);

        try {
            app(SaveLocalSettingsAction::class)->savePrinterConfiguration($this->printerData('Forged branch printer'), null, [
                'scope_type' => 'branch', 'branch_id' => $branchB->id,
            ]);
            self::fail('A scoped actor created a printer for another branch.');
        } catch (ValidationException) {
            self::addToAssertionCount(1);
        }

        try {
            app(SaveLocalSettingsAction::class)->savePrinterConfiguration($this->printerData('Forged store printer'), null, [
                'scope_type' => 'store', 'branch_id' => $branchB->id, 'store_id' => $foreignStore->id,
            ]);
            self::fail('A scoped actor created a printer for another store.');
        } catch (ValidationException) {
            self::addToAssertionCount(1);
        }

        $this->get(route('admin.settings.printer-preview', $foreignPrinter))->assertNotFound();
    }

    public function test_a_scoped_actor_cannot_change_a_foreign_branch_selling_store_mapping(): void
    {
        [$actor, $branchA, $branchB] = $this->scopedActorAndBranches();
        $foreignStore = $this->store($branchB, 'SCOPE-MAPPING-B');
        $this->actingAs($actor);

        $this->expectException(ModelNotFoundException::class);
        app(SaveBranchSellingStoreMappingAction::class)->execute($branchB->id, $foreignStore->id, 'Forged cross-branch mapping.');
    }

    public function test_a_scoped_actor_cannot_open_a_foreign_branch_selling_store_mapping_history(): void
    {
        [$actor, , $branchB] = $this->scopedActorAndBranches();
        $this->actingAs($actor);

        $this->expectException(ModelNotFoundException::class);
        Livewire::test('platform::admin.branches')->call('openHistoryModal', $branchB->id);
    }

    public function test_document_sequence_approval_carries_the_selected_branch_scope(): void
    {
        [$actor, $branchA] = $this->scopedActorAndBranches();
        $this->actingAs($actor);

        Livewire::test('platform::admin.settings')
            ->set('documentSequenceForm', [
                'id' => null,
                'document_type' => 'scope-approval',
                'scope_type' => 'branch',
                'scope_id' => $branchA->id,
                'prefix' => 'SCOPE-',
                'suffix' => '',
                'padding_length' => 4,
                'next_value' => 1,
                'reset_rule' => 'never',
                'status' => 'active',
                'policy_notes' => 'Branch-scope approval.',
            ])
            ->call('saveDocumentSequence')
            ->assertHasNoErrors();

        self::assertSame($branchA->id, ApprovalRecord::query()->sole()->branch_id);
    }

    /** @return array{0: User, 1: Branch, 2: Branch} */
    private function scopedActorAndBranches(): array
    {
        $branchA = $this->branch('SCOPE-A');
        $branchB = $this->branch('SCOPE-B');

        return [$this->userWith('scope-settings-actor', ['system-administrator'], branchIds: [$branchA->id]), $branchA, $branchB];
    }

    private function sequence(Branch $branch, string $type): DocumentSequence
    {
        return DocumentSequence::query()->create([
            'document_type' => $type, 'scope_type' => 'branch', 'scope_id' => $branch->id,
            'prefix' => 'S-', 'padding_length' => 4, 'next_value' => 1,
            'reset_rule' => 'never', 'status' => 'active', 'lock_version' => 1,
        ]);
    }

    private function printer(Branch $branch, string $name): PrinterConfiguration
    {
        return PrinterConfiguration::query()->create([
            ...$this->printerData($name), 'branch_id' => $branch->id,
        ]);
    }

    /** @return array<string, mixed> */
    private function printerData(string $name): array
    {
        return [
            'name' => $name, 'printer_type' => 'thermal', 'paper_size' => '80mm',
            'template_name' => 'receipt_80', 'connection_type' => 'browser', 'port' => null,
            'is_default' => false, 'status' => 'active', 'notes' => 'Test fixture.',
        ];
    }
}
