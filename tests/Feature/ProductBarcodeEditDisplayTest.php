<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Barcode;
use App\Modules\Catalog\Models\Product;
use App\Modules\Platform\Models\Company;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

final class ProductBarcodeEditDisplayTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Gate::before(static fn (User $user, string $ability): bool => true);
        Company::factory()->create();
    }

    public function test_edit_shows_every_saved_active_barcode_instead_of_an_empty_international_entry(): void
    {
        $actor = User::factory()->create(['status' => 'active', 'is_super_admin' => true]);
        $product = Product::factory()->create(['barcode_registration_type' => 'international']);

        Barcode::query()->create([
            'product_id' => $product->id,
            'barcode' => '6291234500907',
            'source' => 'supplier',
            'status' => 'active',
            'is_primary' => true,
        ]);
        Barcode::query()->create([
            'product_id' => $product->id,
            'barcode' => '6291234500914',
            'source' => 'supplier',
            'status' => 'active',
        ]);
        Barcode::query()->create([
            'product_id' => $product->id,
            'barcode' => '6291234599999',
            'source' => 'supplier',
            'status' => 'inactive',
        ]);

        $response = $this->actingAs($actor)->get(route('catalog.products.edit', ['product' => $product]));
        $response->assertOk();
        $html = (string) $response->getContent();

        self::assertTrue(str_contains($html, '6291234500907'), 'The saved primary barcode should be visible on the edit form.');
        self::assertTrue(str_contains($html, '6291234500914'), 'Every saved active barcode should be visible on the edit form.');
        self::assertFalse(str_contains($html, '6291234599999'), 'Inactive barcodes should not be presented as current identities.');
        self::assertFalse(str_contains($html, 'wire:model="supplierBarcode"'), 'An existing active barcode should replace the empty create-only field.');
        self::assertTrue(str_contains($html, route('catalog.products.show', ['product' => $product])), 'The form should keep its link to barcode management.');
    }

    public function test_create_and_edit_without_saved_active_barcode_keep_the_international_entry(): void
    {
        $actor = User::factory()->create(['status' => 'active', 'is_super_admin' => true]);
        $createResponse = $this->actingAs($actor)->get(route('catalog.products.create'));
        $createResponse->assertOk();
        self::assertTrue(str_contains((string) $createResponse->getContent(), 'wire:model="supplierBarcode"'), 'The create form should retain international barcode entry.');

        $product = Product::factory()->create(['barcode_registration_type' => 'international']);
        Barcode::query()->create([
            'product_id' => $product->id,
            'barcode' => '6291234599999',
            'source' => 'supplier',
            'status' => 'inactive',
        ]);
        $editResponse = $this->get(route('catalog.products.edit', ['product' => $product]));
        $editResponse->assertOk();
        $editHtml = (string) $editResponse->getContent();
        self::assertTrue(str_contains($editHtml, 'wire:model="supplierBarcode"'), 'An incomplete product should retain entry so its first barcode can be assigned.');
        self::assertFalse(str_contains($editHtml, '6291234599999'), 'An inactive barcode should not suppress the first active barcode entry.');
    }
}
