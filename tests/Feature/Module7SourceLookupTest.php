<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Platform\Support\ApplicationNavigation;
use App\Modules\Retail\Actions\RetailReturnAction;
use App\Modules\Retail\Actions\RetailSaleAction;
use App\Modules\Retail\Models\GiftReceipt;
use App\Modules\Retail\Models\RetailReturn;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\Module7TestCase;

final class Module7SourceLookupTest extends Module7TestCase
{
    public function test_returns_search_filters_visible_sale_by_invoice_or_customer_phone_and_date(): void
    {
        $response = $this->actingAs($this->cashier)
            ->get(route('returns.index', [
                'source_search' => $this->sale1->document_number,
                'source_date' => $this->sale1->approved_at->toDateString(),
            ]))
            ->assertOk();
        $viewData = $response->getOriginalContent()->getData();
        self::assertContains((int) $this->sale1->id, $this->paginatorIds($viewData['sales']));
        self::assertNotContains((int) $this->sale2->id, $this->paginatorIds($viewData['sales']));
        self::assertSame(20, $viewData['sales']->perPage());

        $phone = $this->customer->phone_display;
        self::assertNotEmpty($phone);
        $phoneResponse = $this->get(route('returns.index', ['source_search' => $phone]))->assertOk();
        self::assertContains((int) $this->sale1->id, $this->paginatorIds($phoneResponse->getOriginalContent()->getData()['sales']));

        $wrongDate = $this->get(route('returns.index', [
            'source_search' => $this->sale1->document_number,
            'source_date' => '1900-01-01',
        ]))->assertOk();
        self::assertSame([], $this->paginatorIds($wrongDate->getOriginalContent()->getData()['sales']));
        self::assertSame([], $this->returnLineOptionValues($wrongDate->getContent()));
    }

    public function test_returns_page_renders_lines_only_for_the_selected_source(): void
    {
        $firstLine = $this->sale1->lines()->firstOrFail();
        $otherLine = $this->sale2->lines()->firstOrFail();

        $response = $this->actingAs($this->cashier)
            ->get(route('returns.index', ['source_sale_id' => $this->sale1->id]))
            ->assertOk();

        $lineOptions = $this->returnLineOptionValues($response->getContent());
        self::assertContains((int) $firstLine->id, $lineOptions);
        self::assertNotContains((int) $otherLine->id, $lineOptions);
    }

    public function test_returns_search_can_find_active_gift_receipt_but_not_an_inactive_one(): void
    {
        $line = $this->giftReceipt->lines()->firstOrFail();

        $response = $this->actingAs($this->cashier)
            ->get(route('returns.index', [
                'source_search' => $this->giftReceipt->reference,
                'source_gift_receipt_id' => $this->giftReceipt->id,
            ]))
            ->assertOk()
            ->assertSee($this->giftReceipt->reference);
        self::assertContains((int) $line->sale_line_id, $this->returnLineOptionValues($response->getContent()));

        $inactiveReceipt = $this->inactiveGiftReceipt();
        $inactiveResponse = $this->get(route('returns.index', ['source_search' => $inactiveReceipt->reference]))
            ->assertOk();
        self::assertSame([], $this->paginatorIds($inactiveResponse->getOriginalContent()->getData()['receipts']));
        self::assertSame([], $this->returnLineOptionValues($inactiveResponse->getContent()));
    }

    public function test_return_submission_rejects_inactive_or_unknown_gift_receipt(): void
    {
        $inactiveReceipt = $this->inactiveGiftReceipt();
        $line = $this->giftReceipt->lines()->firstOrFail();
        $existingReturns = RetailReturn::query()->count();

        foreach ([$inactiveReceipt->id, $inactiveReceipt->id + 999] as $receiptId) {
            $this->actingAs($this->cashier)
                ->from(route('returns.index'))
                ->post(route('returns.store'), [
                    'idempotency_key' => (string) Str::uuid(),
                    'source_gift_receipt_id' => $receiptId,
                    'sale_line_id' => $line->sale_line_id,
                    'quantity' => 1,
                    'condition' => 'sellable',
                    'disposition' => 'restock',
                    'settlement_type' => 'cash_refund',
                    'reason' => 'Module 7 source lookup validation',
                ])
                ->assertRedirect(route('returns.index'))
                ->assertSessionHasErrors('source_gift_receipt_id');
        }

        self::assertSame($existingReturns, RetailReturn::query()->count());
    }

    public function test_active_gift_receipt_is_single_use_and_only_allows_its_source_sale_lines(): void
    {
        $action = app(RetailReturnAction::class);
        $giftLine = $this->giftReceipt->lines()->firstOrFail();
        $foreignSaleLine = $this->sale2->lines()->firstOrFail();

        $foreignData = $this->returnData($this->sale1, quantity: 1, settlement: 'original_tender');
        $foreignData['source_sale_id'] = null;
        $foreignData['source_gift_receipt_id'] = $this->giftReceipt->id;
        $foreignData['lines'][0]['sale_line_id'] = $foreignSaleLine->id;
        try {
            $action->create($this->cashier, $foreignData, (string) Str::uuid());
            self::fail('A Gift Receipt must reject a line from a different sale.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('lines.0', $exception->errors());
        }

        $overQuantityData = $this->returnData($this->sale1, quantity: 6, settlement: 'original_tender');
        $overQuantityData['source_sale_id'] = null;
        $overQuantityData['source_gift_receipt_id'] = $this->giftReceipt->id;
        try {
            $action->create($this->cashier, $overQuantityData, (string) Str::uuid());
            self::fail('A Gift Receipt must reject quantities above its source sale line.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('lines.0.quantity', $exception->errors());
        }

        $data = $this->returnData($this->sale1, quantity: 1, settlement: 'original_tender');
        $data['source_sale_id'] = null;
        $data['source_gift_receipt_id'] = $this->giftReceipt->id;
        $return = $action->create($this->cashier, $data, (string) Str::uuid());

        self::assertSame($this->sale1->id, $return->source_sale_id);
        self::assertSame($this->giftReceipt->id, $return->source_gift_receipt_id);
        self::assertSame($giftLine->sale_line_id, $return->lines->sole()->sale_line_id);

        $submitted = $action->submit($this->cashier, $return);
        $approved = $action->approve($this->reviewer, $submitted);
        $completed = $action->complete($this->reviewer, $approved, 'module7-gift-return-'.$return->id);

        self::assertSame('completed', $completed->status);
        self::assertSame('used', $this->giftReceipt->fresh()->status);
        self::assertSame($return->id, $this->giftReceipt->fresh()->used_return_id);

        try {
            $action->create($this->cashier, $data, (string) Str::uuid());
            self::fail('A completed Gift Receipt cannot be used for a second return.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('source', $exception->errors());
        }
    }

    public function test_gift_receipt_issue_search_filters_sales_and_explicit_id_loads_only_that_sales_lines(): void
    {
        $issuer = $this->userWithPermissions(['gift_receipts.issue', 'gift_receipts.view']);
        $selectedLine = $this->sale2->lines()->firstOrFail();
        $unselectedLine = $this->sale1->lines()->firstOrFail();

        $response = $this->actingAs($issuer)
            ->get(route('gift.receipts.index', [
                'sale_search' => $this->sale2->document_number,
                'sale_date' => $this->sale2->approved_at->toDateString(),
                'sale_id' => $this->sale2->id,
            ]))->assertOk();

        $viewData = $response->getOriginalContent()->getData();
        self::assertContains((int) $this->sale2->id, $this->paginatorIds($viewData['sales']));
        self::assertSame((int) $this->sale2->id, (int) $viewData['selectedSale']->id);

        $giftLineValues = $this->giftReceiptLineInputValues($response->getContent());
        self::assertContains((int) $selectedLine->id, $giftLineValues);
        self::assertNotContains((int) $unselectedLine->id, $giftLineValues);

        $independentSelection = $this->get(route('gift.receipts.index', [
            'sale_search' => 'no-matching-sale',
            'sale_date' => '1900-01-01',
            'sale_id' => $this->sale2->id,
        ]))->assertOk();
        $selectionData = $independentSelection->getOriginalContent()->getData();
        self::assertSame((int) $this->sale2->id, (int) $selectionData['selectedSale']->id);
        self::assertContains((int) $selectedLine->id, $this->giftReceiptLineInputValues($independentSelection->getContent()));
    }

    public function test_gift_receipt_issue_rejects_a_nonapproved_sale_selection(): void
    {
        $issuer = $this->userWithPermissions(['gift_receipts.issue', 'gift_receipts.view']);
        $unapprovedSale = app(RetailSaleAction::class)->create(
            $this->cashier,
            $this->store,
            [['product_id' => $this->productB->id, 'quantity' => '1']],
            (string) Str::uuid(),
            suspend: true,
        );

        $this->actingAs($issuer)
            ->get(route('gift.receipts.index', ['sale_id' => $unapprovedSale->id]))
            ->assertOk()
            ->assertDontSee('name="sale_line_ids[]"', false);
    }

    public function test_gift_receipt_print_output_contains_no_price_or_payment_fields(): void
    {
        $actor = collect([$this->cashier, $this->reviewer])
            ->first(fn (User $user): bool => $user->canAny(['gift_receipts.print', 'gift_receipts.reprint']));
        self::assertNotNull($actor, 'The fixtures need a user permitted to print a Gift Receipt.');

        $response = $this->actingAs($actor)
            ->get(route('gift.receipts.print', $this->giftReceipt));

        $response->assertOk()
            ->assertSee($this->giftReceipt->reference)
            ->assertDontSee('unit_price')
            ->assertDontSee('discount_amount')
            ->assertDontSee('tax_amount')
            ->assertDontSee('payment_method');

        self::assertStringNotContainsString(
            number_format((float) $this->sale1->payable_total, 2, '.', ''),
            $response->getContent(),
        );
    }

    public function test_source_lookup_results_are_bounded_and_store_scoped(): void
    {
        $outOfScope = User::factory()->create(['status' => 'active']);
        $this->grantPermissions($outOfScope, ['returns.view', 'gift_receipts.view', 'gift_receipts.issue']);

        $returns = $this->actingAs($outOfScope)->get(route('returns.index'))->assertOk();
        $returnData = $returns->getOriginalContent()->getData();
        self::assertSame(20, $returnData['sales']->perPage());
        self::assertSame(0, $returnData['sales']->total());
        self::assertSame(0, $returnData['receipts']->total());
        $returns->assertDontSee($this->sale1->document_number)->assertDontSee($this->giftReceipt->reference);

        $giftReceipts = $this->get(route('gift.receipts.index'))->assertOk();
        $giftData = $giftReceipts->getOriginalContent()->getData();
        self::assertSame(20, $giftData['sales']->perPage());
        self::assertSame(0, $giftData['sales']->total());
        $giftReceipts->assertDontSee($this->sale1->document_number);
    }

    public function test_navigation_shows_returns_and_gift_receipts_for_either_supported_view_permission(): void
    {
        $combinedViewer = $this->userWithPermissions(['returns_exchanges_gift_instruments.view']);
        $giftViewer = $this->userWithPermissions(['gift_receipts.view']);

        foreach ([$this->cashier, $this->reviewer, $combinedViewer, $giftViewer] as $user) {
            $routes = collect(app(ApplicationNavigation::class)->for($user, 'en'))
                ->flatMap(static fn (array $group): array => $group['items'] ?? [])
                ->pluck('route')
                ->all();

            self::assertSame(
                $user->canAny(['returns.view', 'returns_exchanges_gift_instruments.view']),
                in_array('returns.index', $routes, true),
            );
            self::assertSame(
                $user->canAny(['gift_receipts.view', 'returns_exchanges_gift_instruments.view']),
                in_array('gift.receipts.index', $routes, true),
            );
        }
    }

    /** @return list<int> */
    private function returnLineOptionValues(string $html): array
    {
        if (preg_match('/<select\\b(?=[^>]*name="sale_line_id")[^>]*>(.*?)<\\/select>/s', $html, $select) !== 1) {
            return [];
        }

        preg_match_all('/<option\\b[^>]*value="(\\d+)"[^>]*>/', $select[1], $options);

        return array_map('intval', $options[1]);
    }

    /** @return list<int> */
    private function giftReceiptLineInputValues(string $html): array
    {
        preg_match_all('/<input\\b[^>]*>/s', $html, $inputs);
        $values = [];

        foreach ($inputs[0] as $input) {
            if (str_contains($input, 'name="sale_line_ids[]"')
                && preg_match('/\\bvalue="(\\d+)"/', $input, $value) === 1) {
                $values[] = (int) $value[1];
            }
        }

        return $values;
    }

    /** @param LengthAwarePaginator<int, mixed> $paginator @return list<int> */
    private function paginatorIds(LengthAwarePaginator $paginator): array
    {
        return array_map('intval', $paginator->getCollection()->modelKeys());
    }

    private function inactiveGiftReceipt(): GiftReceipt
    {
        return GiftReceipt::query()->create([
            'sale_id' => $this->sale1->id,
            'branch_id' => $this->sale1->branch_id,
            'store_id' => $this->sale1->store_id,
            'issued_by' => $this->cashier->id,
            'reference' => 'M7-INACTIVE-'.Str::upper(Str::random(10)),
            'status' => 'used',
            'idempotency_key' => (string) Str::uuid(),
            'lock_version' => 1,
        ]);
    }
}
