<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Retail\Models\Exchange;
use App\Modules\Retail\Models\RetailReturn;
use App\Modules\Retail\Models\RetailReturnSettlement;
use Tests\Support\Module7TestCase;

final class Module7ReturnFormTest extends Module7TestCase
{
    public function test_invalid_source_shows_field_error_and_keeps_submitted_form_values(): void
    {
        $invalidSourceId = 2147483000;
        $reason = 'Module 7 retained reason '.str()->random(8);

        $response = $this->actingAs($this->cashier)
            ->from(route('returns.index'))
            ->post(route('returns.store'), [
                'source_sale_id' => $invalidSourceId,
                'source_gift_receipt_id' => '',
                'sale_line_id' => $this->sale1->lines()->first()->id,
                'quantity' => 7,
                'condition' => 'sellable',
                'disposition' => 'restock',
                'settlement_type' => 'cash_refund',
                'reason' => $reason,
                'idempotency_key' => (string) str()->uuid(),
            ]);

        $response->assertSessionHasErrors('source_sale_id');
        $this->get(route('returns.index'))
            ->assertSee('name="source_sale_id"', false)
            ->assertSee('value="'.$invalidSourceId.'"', false)
            ->assertSee('value="7"', false)
            ->assertSee($reason);
    }

    public function test_exchange_controls_are_disabled_while_the_exchange_section_is_hidden(): void
    {
        $response = $this->actingAs($this->cashier)->get(route('returns.index'));

        $response->assertSee("x-show=\"settlement === 'exchange'\"", false)
            ->assertSee('x-cloak', false)
            ->assertSee(':disabled', false);
    }

    public function test_non_exchange_return_ignores_malformed_exchange_lines(): void
    {
        $key = (string) str()->uuid();

        $response = $this->actingAs($this->cashier)
            ->from(route('returns.index'))
            ->post(route('returns.store'), [
                'source_sale_id' => $this->sale1->id,
                'source_gift_receipt_id' => '',
                'sale_line_id' => $this->sale1->lines()->first()->id,
                'quantity' => 1,
                'condition' => 'sellable',
                'disposition' => 'restock',
                'settlement_type' => 'original_tender',
                'reason' => 'Non-exchange return ignores replacement data.',
                'exchange_lines' => 'malformed and irrelevant for a cash refund',
                'idempotency_key' => $key,
            ]);

        $return = RetailReturn::query()->where('idempotency_key', $key)->firstOrFail();

        $response->assertRedirect(route('returns.show', $return));
        self::assertSame('original_tender', $return->settlement_type);
        self::assertFalse(Exchange::query()->where('retail_return_id', $return->id)->exists());
    }

    public function test_arabic_validation_renders_rtl_form_without_browser_validation_override(): void
    {
        $response = $this->withSession(['locale' => 'ar'])
            ->actingAs($this->cashier)
            ->from(route('returns.index'))
            ->followingRedirects()
            ->post(route('returns.store'), [
                'source_sale_id' => 2147483001,
                'source_gift_receipt_id' => '',
                'sale_line_id' => $this->sale1->lines()->first()->id,
                'quantity' => 1,
                'condition' => 'sellable',
                'disposition' => 'restock',
                'settlement_type' => 'cash_refund',
                'reason' => 'سبب اختبار باللغة العربية',
                'idempotency_key' => (string) str()->uuid(),
            ]);

        $response
            ->assertSee('الفاتورة الأصلية غير متاحة أو خارج نطاقك أو غير مرحلة.')
            ->assertSee('lang="ar"', false)
            ->assertSee('dir="rtl"', false)
            ->assertSee('novalidate', false);
    }

    public function test_exchange_return_requires_replacement_then_approves_and_posts_stock_and_difference(): void
    {
        $initialProductAStock = $this->stockOnHand($this->productA->id);
        $initialProductBStock = $this->stockOnHand($this->productB->id);
        $invalidKey = (string) str()->uuid();
        $payload = [
            'source_sale_id' => $this->sale1->id,
            'source_gift_receipt_id' => '',
            'sale_line_id' => $this->sale1->lines()->first()->id,
            'quantity' => 1,
            'condition' => 'sellable',
            'disposition' => 'restock',
            'settlement_type' => 'exchange',
            'reason' => 'Exchange one sold item for product B.',
            'idempotency_key' => $invalidKey,
        ];

        $this->actingAs($this->cashier)
            ->from(route('returns.index'))
            ->post(route('returns.store'), $payload)
            ->assertSessionHasErrors('exchange_lines');
        self::assertFalse(RetailReturn::query()->where('idempotency_key', $invalidKey)->exists());

        $validKey = (string) str()->uuid();
        $payload['idempotency_key'] = $validKey;
        $payload['exchange_lines'] = [['product_id' => $this->productB->id, 'quantity' => 1]];
        $createdResponse = $this->from(route('returns.index'))->post(route('returns.store'), $payload);
        $return = RetailReturn::query()->where('idempotency_key', $validKey)->firstOrFail();
        $exchange = $return->exchange()->with('lines')->firstOrFail();

        $createdResponse->assertRedirect(route('returns.show', $return));
        self::assertSame('56.78', (string) $exchange->replacement_value);
        self::assertSame('-66.67', (string) $exchange->difference_value);
        self::assertSame('refund', $exchange->difference_direction);
        self::assertSame('1.000000', (string) $exchange->lines->sole()->quantity);

        $this->from(route('returns.show', $return))
            ->post(route('returns.submit', $return))
            ->assertRedirect(route('returns.show', $return));
        $this->actingAs($this->reviewer)
            ->from(route('returns.show', $return))
            ->post(route('returns.approve', $return))
            ->assertRedirect(route('returns.show', $return));
        $this->from(route('returns.show', $return))
            ->post(route('returns.complete', $return), [
                'payment_method_id' => $this->paymentMethod->id,
                'idempotency_key' => (string) str()->uuid(),
            ])
            ->assertRedirect(route('returns.show', $return));

        $settlement = RetailReturnSettlement::query()->where('retail_return_id', $return->id)->sole();
        self::assertSame('completed', $return->fresh()->status);
        self::assertSame('exchange_difference', $settlement->settlement_type);
        self::assertSame('refund', $settlement->direction);
        self::assertSame('66.67', (string) $settlement->amount);
        self::assertSame(bcadd($initialProductAStock, '1', 6), $this->stockOnHand($this->productA->id));
        self::assertSame(bcsub($initialProductBStock, '1', 6), $this->stockOnHand($this->productB->id));
    }

    private function stockOnHand(int $productId): string
    {
        $onHand = StockBalance::query()
            ->where('product_id', $productId)
            ->where('store_id', $this->store->id)
            ->value('on_hand');

        return bcadd((string) ($onHand ?? '0'), '0', 6);
    }
}
