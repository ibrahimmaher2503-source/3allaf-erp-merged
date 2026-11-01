<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Catalog\Models\Product;
use App\Modules\Customer\Models\Customer;
use App\Modules\Quotation\Models\Quotation;
use App\Modules\Quotation\Models\QuotationLine;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

final class Issue41QuotationEditTest extends TestCase
{
    use DatabaseTransactions;

    private array $fixture;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        require_once base_path('scripts/qa/fixture.php');
        $this->fixture = qaFixture();
        $this->product = Product::factory()->create(['category_id' => $this->fixture['category']->id, 'sale_price' => '30.00']);
        $this->product->barcodes()->create(['barcode' => 'QA-QTN-'.Str::random(12), 'source' => 'local', 'status' => 'active', 'is_primary' => true]);
        $this->actingAs($this->fixture['admin'])->withoutVite();
    }

    public function test_notes_edit_with_only_one_submitted_line_preserves_both_existing_lines(): void
    {
        $quotation = $this->quotation();
        $first = $quotation->lines()->orderBy('line_number')->firstOrFail();
        $second = $quotation->lines()->orderBy('line_number')->skip(1)->firstOrFail();

        $this->get(route('quotations.index'))
            ->assertOk()
            ->assertSee('value="'.$first->id.'"', false)
            ->assertSee('value="'.$second->id.'"', false)
            ->assertSee('Second line');

        $this->put(route('quotations.update', $quotation), [
            'customer_id' => $quotation->customer_id,
            'valid_until' => $quotation->valid_until->toDateString(),
            'notes' => 'Notes updated only',
            'lines' => [$this->linePayload($first)],
        ])->assertSessionHasNoErrors();

        $updated = $quotation->fresh('lines');
        self::assertSame('Notes updated only', $updated->notes);
        self::assertSame(2, $updated->lines->count());
        self::assertSame([$first->id, $second->id], $updated->lines->sortBy('line_number')->pluck('id')->all());
        self::assertSame('30.00', $updated->total);
    }

    public function test_line_removal_requires_an_explicit_id_and_cannot_remove_every_line(): void
    {
        $quotation = $this->quotation();
        $ids = $quotation->lines()->orderBy('line_number')->pluck('id')->all();

        $this->put(route('quotations.update', $quotation), [
            'customer_id' => $quotation->customer_id,
            'valid_until' => $quotation->valid_until->toDateString(),
            'notes' => 'Remove second intentionally',
            'removed_line_ids' => [$ids[1]],
        ])->assertSessionHasNoErrors();

        self::assertSame([$ids[0]], $quotation->fresh()->lines()->pluck('id')->all());
        self::assertSame('10.00', $quotation->fresh()->total);

        $this->put(route('quotations.update', $quotation), [
            'customer_id' => $quotation->customer_id,
            'valid_until' => $quotation->valid_until->toDateString(),
            'removed_line_ids' => [$ids[0]],
        ])->assertSessionHasErrors('lines');

        self::assertSame([$ids[0]], $quotation->fresh()->lines()->pluck('id')->all());
    }

    public function test_another_quotations_line_id_cannot_be_changed_or_removed(): void
    {
        $quotation = $this->quotation();
        $other = $this->quotation();
        $foreignLine = $other->lines()->firstOrFail();

        $this->put(route('quotations.update', $quotation), [
            'customer_id' => $quotation->customer_id,
            'valid_until' => $quotation->valid_until->toDateString(),
            'lines' => [$this->linePayload($foreignLine)],
        ])->assertSessionHasErrors('lines');

        $this->put(route('quotations.update', $quotation), [
            'customer_id' => $quotation->customer_id,
            'valid_until' => $quotation->valid_until->toDateString(),
            'removed_line_ids' => [$foreignLine->id],
        ])->assertSessionHasErrors('removed_line_ids');

        self::assertSame(2, $quotation->fresh()->lines()->count());
        self::assertSame(2, $other->fresh()->lines()->count());
    }

    private function quotation(): Quotation
    {
        $customer = Customer::factory()->create(['created_store_id' => $this->fixture['store']->id, 'created_branch_id' => $this->fixture['branch']->id]);
        $customer->scopes()->create(['branch_id' => $this->fixture['branch']->id, 'store_id' => $this->fixture['store']->id, 'created_by' => $this->fixture['admin']->id]);
        $quotation = Quotation::query()->create([
            'quotation_number' => 'QA-QTN-'.Str::random(12),
            'activity_type' => 'retail',
            'customer_id' => $customer->id,
            'branch_id' => $this->fixture['branch']->id,
            'store_id' => $this->fixture['store']->id,
            'valid_until' => now()->addDays(7)->toDateString(),
            'status' => 'draft',
            'currency_code' => 'EGP',
            'subtotal' => '30.00',
            'total' => '30.00',
            'created_by' => $this->fixture['admin']->id,
            'idempotency_key' => (string) Str::uuid(),
        ]);
        foreach ([['First line', '10.00'], ['Second line', '20.00']] as $index => [$description, $price]) {
            $quotation->lines()->create([
                'line_number' => $index + 1,
                'line_type' => 'product',
                'product_id' => $this->product->id,
                'description_ar' => 'وصف '.($index + 1),
                'description_en' => $description,
                'quantity' => '1.000000',
                'unit_price' => $price,
                'line_total' => $price,
            ]);
        }

        return $quotation;
    }

    private function linePayload(QuotationLine $line): array
    {
        return [
            'id' => $line->id,
            'line_type' => $line->line_type,
            'product_id' => $line->product_id,
            'description_ar' => $line->description_ar,
            'description_en' => $line->description_en,
            'quantity' => $line->quantity,
            'unit_price' => $line->unit_price,
        ];
    }
}
