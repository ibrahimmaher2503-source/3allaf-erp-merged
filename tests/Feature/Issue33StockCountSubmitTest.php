<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\StockCount;
use App\Modules\Platform\Models\ApprovalRecord;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

final class Issue33StockCountSubmitTest extends TestCase
{
    use DatabaseTransactions;

    public function test_submit_explains_uncounted_scope_without_changing_the_session_in_english_and_arabic(): void
    {
        require_once base_path('scripts/qa/fixture.php');
        $fixture = qaFixture();
        $this->actingAs($fixture['admin'])->withoutVite();
        $product = Product::factory()->create(['category_id' => $fixture['category']->id]);
        $count = StockCount::query()->create([
            'count_number' => 'QA-COUNT-'.Str::random(12),
            'count_type' => 'full',
            'scope_type' => 'full',
            'branch_id' => $fixture['branch']->id,
            'store_id' => $fixture['store']->id,
            'status' => 'in_progress',
            'reference_at' => now()->subMinute(),
            'opened_at' => now()->subMinute(),
            'created_by' => $fixture['admin']->id,
            'manager_id' => $fixture['admin']->id,
            'idempotency_key' => (string) Str::uuid(),
            'lock_version' => 2,
        ]);
        $count->locations()->create(['store_id' => $fixture['store']->id, 'snapshot_at' => now()->subMinute()]);
        $count->lines()->create([
            'store_id' => $fixture['store']->id,
            'product_id' => $product->id,
            'reference_on_hand' => '4',
            'is_counted' => false,
        ]);

        $message = 'Complete a count for every scoped product/location, or explicitly confirm zero, before submitting.';
        $this->post(route('inventory.counts.submit', $count))
            ->assertSessionHas('error', $message);

        $this->withSession(['locale' => 'ar-EG'])
            ->post(route('inventory.counts.submit', $count))
            ->assertSessionHas('error', 'كمّل عدّ كل منتج في كل موقع داخل نطاق الجرد، أو أكّد إن كميته صفر بشكل صريح، قبل الإرسال.');

        self::assertSame('in_progress', $count->fresh()->status);
        self::assertNull($count->fresh()->submitted_at);
        self::assertFalse(ApprovalRecord::query()->where('source_type', 'stock_counts')->where('source_id', (string) $count->id)->exists());
    }
}
