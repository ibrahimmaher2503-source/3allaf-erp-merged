<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Retail\Actions\RetailReturnAction;
use App\Modules\Retail\Models\RetailReturn;
use Illuminate\Validation\ValidationException;
use Tests\Support\Module7TestCase;

final class Module7ReturnHistoryApprovalTest extends Module7TestCase
{
    public function test_returnable_quantity_context_matches_server_side_reservations(): void
    {
        $this->actingAs($this->cashier);
        $data = $this->returnData($this->sale1, quantity: 3);
        $data['reason'] = 'Initial quantity reservation';
        $this->createReturn($this->sale1, $data, $this->cashier);

        $response = $this->get(route('returns.index', ['source_sale_id' => $this->sale1->id]));
        $response
            ->assertOk()
            ->assertSee('Sold quantity')
            ->assertSee('Previously returned')
            ->assertSee('reserved quantity')
            ->assertSee('Remaining returnable quantity');

        $dom = new \DOMDocument;
        @$dom->loadHTML((string) $response->getContent());
        $xpath = new \DOMXPath($dom);
        $lineId = (int) $this->sale1->lines->sole()->id;
        $lineSummary = $xpath->query('//*[@data-sale-line="'.$lineId.'"]')?->item(0);
        self::assertInstanceOf(\DOMElement::class, $lineSummary);
        self::assertSame('5.000000', $lineSummary->getAttribute('data-sold'));
        self::assertSame('3.000000', $lineSummary->getAttribute('data-returned'));
        self::assertSame('2.000000', $lineSummary->getAttribute('data-remaining'));

        $excess = $this->returnData($this->sale1, quantity: 3);
        $this->assertValidationError(
            fn () => $this->createReturn($this->sale1, $excess, $this->cashier),
            'lines.0.quantity',
        );
    }

    public function test_requester_cannot_approve_or_complete_own_submitted_return(): void
    {
        $this->grantPermissions($this->cashier, ['returns.approve', 'returns.complete']);
        self::assertNotSame($this->cashier->id, $this->reviewer->id);

        $data = $this->returnData($this->sale1, inspection: 'damaged', disposition: 'restock');
        $data['reason'] = 'Damaged package on return';
        $data['lines'][0]['return_store_id'] = $this->store->id;
        $data['lines'][0]['inspection_notes'] = 'Seal was broken at intake';
        $this->actingAs($this->cashier);
        $return = $this->createReturn($this->sale1, $data, $this->cashier);
        $action = app(RetailReturnAction::class);
        $submitted = $action->submit($this->cashier, $return);

        $this->assertValidationError(fn () => $action->approve($this->cashier, $submitted), 'approval');
        $this->assertValidationError(fn () => $action->complete($this->cashier, $submitted, 'before-review-'.$return->id), 'status');
        self::assertSame('submitted', $submitted->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'category' => 'retail',
            'event' => 'retail_return_submitted',
            'actor_id' => $this->cashier->id,
            'source_type' => RetailReturn::class,
            'source_id' => (string) $return->id,
        ]);

        $this->actingAs($this->reviewer)
            ->get(route('returns.show', $return))
            ->assertOk()
            ->assertSee($this->sale1->document_number)
            ->assertSee('Damaged package on return')
            ->assertSee('Damaged')
            ->assertSee('Quarantine')
            ->assertSee('Seal was broken at intake');

        $approved = $action->approve($this->reviewer, $submitted->fresh());
        self::assertSame('approved', $approved->status);
        self::assertSame($this->reviewer->id, $approved->approved_by);
        self::assertNotNull($approved->approved_at);
        $this->assertDatabaseHas('audit_logs', [
            'category' => 'retail',
            'event' => 'retail_return_approved',
            'actor_id' => $this->reviewer->id,
            'source_type' => RetailReturn::class,
            'source_id' => (string) $return->id,
        ]);
    }

    public function test_reviewer_rejection_persists_reason_state_and_audit_event(): void
    {
        $data = $this->returnData($this->sale1);
        $data['reason'] = 'Customer reports an issue';
        $this->actingAs($this->cashier);
        $return = $this->createReturn($this->sale1, $data, $this->cashier);
        $action = app(RetailReturnAction::class);
        $submitted = $action->submit($this->cashier, $return);

        $this->actingAs($this->reviewer);
        $rejected = $action->reject($this->reviewer, $submitted, 'Return proof does not match the sale');

        self::assertSame('rejected', $rejected->status);
        self::assertSame('Return proof does not match the sale', $rejected->rejection_reason);
        $this->assertDatabaseHas('audit_logs', [
            'category' => 'retail',
            'event' => 'retail_return_rejected',
            'actor_id' => $this->reviewer->id,
            'source_type' => RetailReturn::class,
            'source_id' => (string) $return->id,
            'reason_text' => 'Return proof does not match the sale',
        ]);
    }

    public function test_return_detail_keeps_the_original_sale_line_name_after_product_rename(): void
    {
        $this->actingAs($this->cashier);
        $data = $this->returnData($this->sale1);
        $return = $this->createReturn($this->sale1, $data, $this->cashier);
        $returnLine = $return->lines->sole();
        $sourceLine = $this->sale1->lines()->whereKey($returnLine->sale_line_id)->firstOrFail();
        $originalName = $sourceLine->name_en;
        $originalUnitValue = $returnLine->unit_value;
        $renamed = 'Product renamed after the historical sale';

        $this->productA->update(['name_en' => $renamed]);

        self::assertSame($originalName, $sourceLine->fresh()->name_en);
        self::assertSame($originalUnitValue, $returnLine->fresh()->unit_value);
        $historicalReturn = $return->fresh();
        self::assertSame($this->sale1->id, $historicalReturn->source_sale_id);
        self::assertSame($this->sale1->branch_id, $historicalReturn->branch_id);
        self::assertSame($this->sale1->store_id, $historicalReturn->store_id);
        self::assertSame($this->sale1->customer_id, $historicalReturn->customer_id);
        self::assertSame($sourceLine->id, $returnLine->fresh()->sale_line_id);

        $this->actingAs($this->reviewer)
            ->get(route('returns.show', $return))
            ->assertOk()
            ->assertSee($originalName)
            ->assertDontSee($renamed);
    }

    /** @param callable(): mixed $operation */
    private function assertValidationError(callable $operation, string $field): void
    {
        try {
            $operation();
            self::fail('Expected the operation to fail validation for '.$field.'.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey($field, $exception->errors());
        }
    }
}
