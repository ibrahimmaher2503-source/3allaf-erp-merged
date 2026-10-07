<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Actions\SaveInventoryAdjustmentAction;
use App\Modules\Inventory\Models\InventoryAdjustment;
use App\Modules\Platform\Actions\AllocateDocumentNumber;
use App\Modules\Platform\Actions\ExportAuditLogs;
use App\Modules\Platform\Actions\OverrideDocumentSequenceCounter;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\DocumentSequence;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Store;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

final class Module1AuditPresentationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_audit_detail_update_returns_only_its_livewire_island(): void
    {
        $this->grantPermissions('audit_logs.view');
        $this->actingAs($this->superAdmin());

        $event = app(RecordAuditEvent::class)->execute('qa', 'qa_audit_island_response');
        $screen = Livewire::test('platform::system.audit-log');
        $initialHtmlBytes = strlen($screen->html());
        $screen->assertSeeHtml('wire:island="audit-details"');

        $screen->update(calls: [[
            'method' => 'showAudit',
            'params' => [$event->id],
            'path' => '',
            'metadata' => ['island' => ['name' => 'audit-details', 'mode' => 'morph']],
        ]])->assertSet('selectedAuditId', $event->id);

        $fragments = $screen->effects['islandFragments'] ?? [];
        self::assertNotEmpty($fragments);
        $fragment = implode('', $fragments);
        self::assertStringContainsString('Audit event details', $fragment);
        self::assertStringNotContainsString('data-guide="audit-header"', $fragment);
        self::assertLessThan($initialHtmlBytes, strlen($fragment));
    }

    public function test_branch_inventory_and_numbering_events_are_scoped_and_render_with_specific_labels(): void
    {
        app()->setLocale('en');
        $this->grantPermissions('audit_logs.view', 'inventory_stock_card.create');
        $actor = $this->superAdmin();
        $this->actingAs($actor);

        $company = Company::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'code' => 'AUDIT-5']);
        $store = Store::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id, 'type' => 'warehouse']);
        $product = Product::factory()->create([
            'product_type' => 'service',
            'model_number' => 'AUDIT-SERVICE',
            'sale_price' => '10.00',
        ]);

        $this->setAuditRequestContext('qa-device-identifier', 'qa-session-identifier', 'QA Audit Browser/1.0');

        $adjustment = app(SaveInventoryAdjustmentAction::class)->execute(
            ['store_id' => $store->id, 'adjustment_type' => 'entry', 'reason_code' => 'qa_audit'],
            [['product_id' => $product->id, 'quantity_delta' => '1', 'unit_cost' => null]],
        );

        $allocation = AuditLog::query()->where('event', 'document_number_allocated')->where('source_type', DocumentSequence::class)->firstOrFail();
        $inventoryAudit = AuditLog::query()->where('event', 'create_inventory_adjustment')->where('source_type', InventoryAdjustment::class)->where('source_id', (string) $adjustment->id)->firstOrFail();

        self::assertSame($branch->id, $allocation->branch_id);
        self::assertSame($branch->id, $inventoryAudit->branch_id);
        self::assertSame($store->id, $inventoryAudit->store_id);

        $branchViewer = User::factory()->create();
        $branchViewer->forceFill(['status' => 'active'])->save();
        $branchViewer->branchScopes()->create(['branch_id' => $branch->id, 'status' => 'active']);
        self::assertTrue(AuditLog::query()->visibleTo($branchViewer)->whereKey($inventoryAudit->id)->exists());

        $storeViewer = User::factory()->create();
        $storeViewer->forceFill(['status' => 'active'])->save();
        $storeViewer->storeScopes()->create(['store_id' => $store->id, 'status' => 'active']);
        self::assertTrue(AuditLog::query()->visibleTo($storeViewer)->whereKey($inventoryAudit->id)->exists());

        $otherBranch = Branch::factory()->create(['company_id' => $company->id, 'code' => 'AUDIT-OTHER']);
        $otherBranchAudit = app(RecordAuditEvent::class)->execute(
            'numbering',
            'document_number_allocated',
            DocumentSequence::class,
            branchId: $otherBranch->id,
        );
        self::assertFalse(AuditLog::query()->visibleTo($branchViewer)->whereKey($otherBranchAudit->id)->exists());

        $screen = Livewire::test('platform::system.audit-log')
            ->assertSee('Document number allocated')
            ->assertSee('Inventory adjustment draft created')
            ->assertSee('Document sequence')
            ->assertSee('Inventory adjustment')
            ->assertSee('Branch #'.$branch->id)
            ->assertSee('Store #'.$store->id)
            ->assertDontSee('Other audit event')
            ->assertDontSee('Other source record');

        $screen->call('showAudit', $allocation->id)->assertSee('Numbering');
    }

    public function test_branch_sequence_override_event_keeps_the_sequence_branch_scope(): void
    {
        $this->grantPermissions('drawers_payments_tax_numbering_printers.override');
        $this->actingAs($this->superAdmin());

        $branch = Branch::factory()->create();
        $sequence = DocumentSequence::query()->create([
            'document_type' => 'test_sequence',
            'scope_type' => 'branch',
            'scope_id' => $branch->id,
            'prefix' => 'TST-',
            'padding_length' => 6,
            'next_value' => 1,
            'reset_rule' => 'never',
            'status' => 'active',
            'lock_version' => 1,
        ]);

        app(OverrideDocumentSequenceCounter::class)->execute($sequence, 2, 1, 'QA audit scope check');

        $event = AuditLog::query()->where('event', 'document_sequence_counter_overridden')->firstOrFail();
        self::assertSame($branch->id, $event->branch_id);

        $companySequence = DocumentSequence::query()->create([
            'document_type' => 'qa_company_sequence',
            'scope_type' => 'company',
            'scope_id' => null,
            'prefix' => 'GLOBAL-',
            'padding_length' => 6,
            'next_value' => 1,
            'reset_rule' => 'never',
            'status' => 'active',
            'lock_version' => 1,
        ]);
        app(OverrideDocumentSequenceCounter::class)->execute($companySequence, 2, 1, 'QA global scope check');

        $globalEvent = AuditLog::query()->where('event', 'document_sequence_counter_overridden')->where('source_id', (string) $companySequence->id)->firstOrFail();
        self::assertNull($globalEvent->branch_id);
    }

    public function test_audit_details_show_short_device_and_session_fingerprints_without_raw_context(): void
    {
        app()->setLocale('en');
        $this->grantPermissions('audit_logs.view', 'drawers_payments_tax_numbering_printers.override');
        $this->actingAs($this->superAdmin());
        $branch = Branch::factory()->create();
        $deviceId = 'qa-device-identifier';
        $sessionId = 'qa-session-identifier';
        $userAgent = 'QA Audit Browser/1.0';
        $this->setAuditRequestContext($deviceId, $sessionId, $userAgent);
        self::assertSame($deviceId, request()->header('X-Device-ID'));
        $sessionId = request()->session()->getId();

        app(AllocateDocumentNumber::class)->executeForBranch('qa_audit', $branch->id);

        $event = AuditLog::query()->where('event', 'document_number_allocated')->firstOrFail();
        $deviceHash = $event->metadata['device_hash'];
        $sessionHash = $event->metadata['session_hash'];

        self::assertSame(hash('sha256', $deviceId), $deviceHash);
        self::assertSame(hash('sha256', $sessionId), $sessionHash);

        Livewire::test('platform::system.audit-log')
            ->call('showAudit', $event->id)
            ->assertSee('Device fingerprint')
            ->assertSee('Session fingerprint')
            ->assertSee(substr($deviceHash, 0, 12))
            ->assertSee(substr($sessionHash, 0, 12))
            ->assertDontSee($deviceId)
            ->assertDontSee($sessionId)
            ->assertDontSee($deviceHash)
            ->assertDontSee($sessionHash)
            ->assertDontSee($userAgent);
    }

    public function test_audit_export_stays_blocked_when_no_approved_row_limit_is_configured(): void
    {
        $this->grantPermissions('audit_logs.export');
        $actor = $this->superAdmin();
        $this->actingAs($actor);
        config(['audit.export_max_rows' => null]);

        try {
            app(ExportAuditLogs::class)->execute([]);
            self::fail('Audit export was allowed without a configured row limit.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('export', $exception->errors());
        }

        self::assertDatabaseMissing('audit_logs', ['event' => 'audit_log_exported', 'actor_id' => $actor->id]);
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['status' => 'active', 'is_super_admin' => true])->save();

        return $user;
    }

    private function grantPermissions(string ...$codes): void
    {
        foreach ($codes as $code) {
            Permission::query()->firstOrCreate(['code' => $code], [
                'module' => str_contains($code, '.') ? strstr($code, '.', true) : 'qa',
                'action' => str_contains($code, '.') ? substr(strstr($code, '.'), 1) : 'view',
                'status' => 'active',
            ]);
        }
    }

    private function setAuditRequestContext(string $deviceId, string $sessionId, string $userAgent): void
    {
        $request = request();
        $request->headers->set('X-Device-ID', $deviceId);
        $request->headers->set('User-Agent', $userAgent);
        $session = app('session')->driver();
        $session->setId($sessionId);
        $request->setLaravelSession($session);
    }
}
