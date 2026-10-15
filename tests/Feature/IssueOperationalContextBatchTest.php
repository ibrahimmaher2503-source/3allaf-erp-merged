<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\InventoryAdjustment;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockCount;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Inventory\Support\StockMovementSourceReferences;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Support\ApprovalInboxReferences;
use App\Modules\Platform\Support\AuditLogPresentation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

final class IssueOperationalContextBatchTest extends TestCase
{
    use DatabaseTransactions;

    private array $fixture;

    protected function setUp(): void
    {
        parent::setUp();
        require_once base_path('scripts/qa/fixture.php');
        $this->fixture = qaFixture();
        $this->actingAs($this->fixture['admin'])->withoutVite();
    }

    public function test_navigation_exposes_existing_permission_gated_destinations(): void
    {
        $groups = collect(config('navigation'))->keyBy('key');
        self::assertTrue($groups['parties']['enabled']);
        self::assertContains('quotations.index', collect($groups['sales']['items'])->pluck('route'));
        self::assertContains('gift.cards.index', collect($groups['sales']['items'])->pluck('route'));
        self::assertContains('pos.suspended', collect($groups['sales']['items'])->pluck('route'));
        self::assertContains('pos.financial-readiness', collect($groups['administration']['items'])->pluck('route'));
        self::assertContains('purchasing.history.suppliers', collect($groups['purchasing']['items'])->pluck('route'));
        self::assertContains('pricing.focus', collect($groups['pricing']['items'])->pluck('route'));
    }

    public function test_count_entry_rejects_non_team_user_on_page_and_records_explicit_zero(): void
    {
        $product = Product::factory()->create(['category_id' => $this->fixture['category']->id, 'sale_price' => '25.00']);
        $count = $this->openCount();
        $count->lines()->create(['store_id' => $this->fixture['store']->id, 'product_id' => $product->id, 'reference_on_hand' => '3', 'is_counted' => false]);
        $payload = ['store_id' => $this->fixture['store']->id, 'lookup' => $product->item_code, 'quantity' => '0', 'request_id' => (string) Str::uuid(), 'device_id' => 'qa-browser', 'input_method' => 'manual'];

        $this->post(route('inventory.counts.contribute', $count), $payload)
            ->assertSessionHas('error', __('You are not on this count team. Ask the count manager to assign you.'));
        self::assertFalse($count->lines()->firstOrFail()->is_counted);

        $count->members()->create(['user_id' => $this->fixture['admin']->id, 'role' => 'counter']);
        $this->post(route('inventory.counts.contribute', $count), $payload)
            ->assertSessionHas('success', __('Product/location count marked complete at zero.'));
        self::assertTrue($count->lines()->firstOrFail()->explicit_zero);
        self::assertSame('0.000000', $count->lines()->firstOrFail()->counted_quantity);
        self::assertSame(0, $count->contributions()->count());
    }

    public function test_open_count_transfer_error_names_the_blocking_session_and_recovery(): void
    {
        $count = $this->openCount();
        $product = Product::factory()->create(['category_id' => $this->fixture['category']->id, 'sale_price' => '25.00']);
        $this->post(route('inventory.transfers.store'), [
            'source_store_id' => $this->fixture['store']->id,
            'destination_store_id' => $this->fixture['receiving']->id,
            'reason_code' => 'qa-count-lock',
            'lines' => [['product_id' => $product->id, 'quantity_requested' => 1]],
        ])->assertSessionHas('error', __('Transfer blocked by open count session :session.', ['session' => $count->count_number]))
            ->assertSessionHas('count-recovery', true);
        self::assertSame(0, StockTransfer::query()->count());
    }

    public function test_adjustment_creator_gets_specific_maker_checker_message(): void
    {
        $creator = $this->userWithPermission('inventory_stock_card.approve');
        $creator->storeScopes()->create(['store_id' => $this->fixture['store']->id, 'status' => 'active']);
        $adjustment = InventoryAdjustment::query()->create([
            'adjustment_number' => 'QA-ADJ-'.Str::random(10), 'store_id' => $this->fixture['store']->id,
            'adjustment_type' => 'entry', 'status' => 'submitted', 'reason_code' => 'qa-maker-checker',
            'created_by' => $creator->id, 'idempotency_key' => (string) Str::uuid(), 'lock_version' => 1,
        ]);
        $this->actingAs($creator)->post(route('inventory.adjustments.approve', $adjustment))
            ->assertSessionHas('error', __('The adjustment creator cannot approve the same adjustment.'));
        self::assertSame('submitted', $adjustment->fresh()->status);
    }

    public function test_movement_and_approval_references_use_document_and_product_names(): void
    {
        $product = Product::factory()->create(['category_id' => $this->fixture['category']->id, 'sale_price' => '25.00']);
        $transfer = StockTransfer::query()->create([
            'transfer_number' => 'QA-TR-123', 'source_store_id' => $this->fixture['store']->id,
            'destination_store_id' => $this->fixture['receiving']->id, 'status' => 'draft',
            'requested_by' => $this->fixture['admin']->id, 'idempotency_key' => (string) Str::uuid(), 'lock_version' => 1,
        ]);
        $movement = new StockMovement(['source_type' => StockTransfer::class, 'source_id' => $transfer->id]);
        $movementReferences = new StockMovementSourceReferences(collect([$movement]));
        self::assertSame('Transfer QA-TR-123', $movementReferences->label($movement));
        self::assertSame(route('inventory.transfers.dispatch-page', $transfer), $movementReferences->url($movement));

        $approval = new ApprovalRecord([
            'source_type' => 'pos_discount', 'source_id' => 'cart:private-uuid:product:'.$product->id,
            'limit_context' => ['product_id' => $product->id, 'discount_amount' => '12.50'],
        ]);
        $approvalReferences = new ApprovalInboxReferences(collect([$approval]));
        self::assertStringContainsString($product->item_code, $approvalReferences->label($approval));
        self::assertStringNotContainsString('private-uuid', $approvalReferences->label($approval));
        self::assertSame('12.50', $approvalReferences->amount($approval));
    }

    public function test_alerts_and_audit_events_show_specific_context(): void
    {
        $product = Product::factory()->create(['category_id' => $this->fixture['category']->id, 'item_code' => 'QA-ALERT-CONTEXT']);
        StockBalance::query()->create([
            'product_id' => $product->id, 'store_id' => $this->fixture['store']->id,
            'on_hand' => '0', 'reserved' => '0', 'in_transit' => '0',
            'average_cost' => '0', 'total_value' => '0', 'version' => 1,
        ]);
        $this->get(route('alerts.index'))->assertOk()->assertSee('QA-ALERT-CONTEXT');
        self::assertSame('Record Stock Count Contribution', app(AuditLogPresentation::class)->event('record_stock_count_contribution'));
    }

    public function test_permission_checkbox_has_its_specific_accessible_name(): void
    {
        $view = file_get_contents(resource_path('views/platform/admin/role-permissions.blade.php'));
        self::assertStringContainsString('aria-label="{{ $permissionPresentation[\'label\'] }}"', $view);
    }

    private function openCount(): StockCount
    {
        $count = StockCount::query()->create([
            'count_number' => 'QA-COUNT-'.Str::random(10), 'count_type' => 'partial', 'scope_type' => 'partial',
            'branch_id' => $this->fixture['branch']->id, 'store_id' => $this->fixture['store']->id,
            'status' => 'in_progress', 'reference_at' => now(), 'opened_at' => now(),
            'created_by' => $this->fixture['admin']->id, 'manager_id' => $this->fixture['admin']->id,
            'idempotency_key' => (string) Str::uuid(), 'lock_version' => 2,
        ]);
        $count->locations()->create(['store_id' => $this->fixture['store']->id, 'snapshot_at' => now()]);

        return $count;
    }

    private function userWithPermission(string $code): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $role = Role::query()->create(['code' => 'qa-issue-'.Str::random(10), 'name_ar' => 'مراجع', 'name_en' => 'Reviewer', 'status' => 'active']);
        $permission = Permission::query()->firstOrCreate(['code' => $code], ['module' => 'inventory_stock_card', 'action' => 'approve', 'status' => 'active']);
        $role->permissions()->attach($permission->id);
        $user->roles()->attach($role->id);

        return $user;
    }
}
