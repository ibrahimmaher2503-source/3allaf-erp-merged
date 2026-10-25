<?php

declare(strict_types=1);

namespace Tests\Feature\QA;

use App\Models\User;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Platform\Models\Role;
use App\Modules\Retail\Actions\SubmitBlindShiftCloseAction;
use App\Modules\Retail\Models\PosShift;
use App\Modules\Retail\Models\Sale;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CommercialBlindCloseTest extends TestCase
{
    use DatabaseTransactions;

    public function test_ordinary_cashier_sees_only_counted_cash_and_cannot_infer_expected_from_prints(): void
    {
        require_once base_path('scripts/qa/fixture.php');
        $fixture = qaFixture();
        $cashier = User::factory()->create(['status' => 'active', 'is_super_admin' => false]);
        $cashier->roles()->sync([Role::query()->where('code', 'cashier')->firstOrFail()->id]);
        $cashier->branchScopes()->create(['branch_id' => $fixture['branch']->id, 'status' => 'active']);
        $cashier->storeScopes()->create(['store_id' => $fixture['store']->id, 'status' => 'active']);
        $drawer = CashDrawer::factory()->create([
            'company_id' => $fixture['company']->id, 'branch_id' => $fixture['branch']->id,
            'store_id' => $fixture['store']->id, 'assigned_user_id' => $cashier->id, 'status' => 'active',
        ]);
        $shift = PosShift::query()->create([
            'branch_id' => $fixture['branch']->id, 'store_id' => $fixture['store']->id,
            'cash_drawer_id' => $drawer->id, 'cashier_id' => $cashier->id, 'opened_by' => $cashier->id,
            'status' => 'open', 'opening_cash' => '125', 'currency_code' => 'EGP',
            'idempotency_key' => (string) Str::uuid(), 'opened_at' => now(),
        ]);
        DB::table('active_pos_shift_assignments')->insert(['shift_id' => $shift->id, 'cashier_id' => $cashier->id, 'cash_drawer_id' => $drawer->id]);
        $card = PaymentMethod::query()->firstOrCreate(['code' => 'QA-BLIND-CARD'], [
            'name_ar' => 'بطاقة', 'name_en' => 'Card', 'type' => 'card', 'status' => 'active',
            'requires_evidence' => false, 'offline_eligible' => false,
        ]);
        $sale = Sale::query()->create([
            'branch_id' => $fixture['branch']->id, 'store_id' => $fixture['store']->id,
            'cash_drawer_id' => $drawer->id, 'shift_id' => $shift->id, 'cashier_id' => $cashier->id,
            'document_number' => 'QA-BLIND-'.Str::random(8), 'status' => 'approved',
            'idempotency_key' => (string) Str::uuid(), 'subtotal' => '64', 'total' => '64',
            'paid_total' => '64', 'payable_total' => '64', 'currency_code' => 'EGP', 'approved_at' => now(),
        ]);
        $sale->payments()->create([
            'payment_method_id' => $card->id, 'method_code' => $card->code, 'method_type' => 'card',
            'amount' => '64', 'tendered_amount' => '64', 'change_amount' => '0',
            'idempotency_key' => (string) Str::uuid(), 'created_by' => $cashier->id,
        ]);
        $this->actingAs($cashier)->withoutVite();
        $this->get(route('pos.shift'))->assertOk()
            ->assertSee('Count physical cash')
            ->assertDontSee('Opening float')
            ->assertDontSee('Expected cash')
            ->assertDontSee('Variance');

        app(SubmitBlindShiftCloseAction::class)->execute($cashier, $shift, '100', [], (string) Str::uuid());
        $submission = $shift->fresh()->closingSubmissions()->latest('id')->firstOrFail();
        self::assertSame('125.00', (string) $submission->expected_cash);
        self::assertSame('100.00', (string) $submission->actual_cash);
        self::assertSame('64.00', $submission->expected_by_method[$card->code]);
        $this->get(route('pos.shift.print.thermal', $shift))->assertOk()
            ->assertSee('Cash physically counted by cashier')
            ->assertDontSee('System expected cash')
            ->assertDontSee('Opening float')
            ->assertDontSee('Cash variance')
            ->assertDontSee('Overall total');
        $this->get(route('pos.shift.print.a4', $shift))->assertOk()
            ->assertDontSee('Opening float')
            ->assertDontSee('Cash movements')
            ->assertDontSee('Overall total');
    }
}
