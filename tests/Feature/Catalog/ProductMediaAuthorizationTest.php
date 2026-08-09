<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductImage;
use App\Modules\Platform\Enums\AttachmentState;
use App\Modules\Platform\Models\Attachment;
use App\Modules\Platform\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

/** AC-XCUT-04/10 and NFR-03: product-media route IDOR boundary. */
final class ProductMediaAuthorizationTest extends TestCase
{
    use PlatformFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCanonicalAuthorization();
        Storage::fake('local');
    }

    public function test_a_catalog_viewer_cannot_use_another_products_id_with_a_valid_attachment(): void
    {
        [$first, $second] = $this->products();
        $attachment = $this->attachmentFor($first, 'foreign-product.png');
        ProductImage::query()->create([
            'product_id' => $first->id,
            'attachment_id' => $attachment->id,
            'role' => 'main',
            'sort_order' => 0,
            'status' => 'active',
        ]);

        $this->actingAs($this->userWith('media-idor-viewer', ['cashier']));

        $response = $this->get(route('catalog.products.media', ['product' => $second, 'attachment' => $attachment]));

        $response->assertForbidden();
        $response->assertDontSee($attachment->storage_path, false);
        $this->assertSame(0, AuditLog::query()->where('event', 'attachment_accessed')->count());
    }

    public function test_an_active_product_media_attachment_is_streamed_without_exposing_its_storage_path(): void
    {
        [$product] = $this->products();
        $attachment = $this->attachmentFor($product, '../../private/secret.png');
        ProductImage::query()->create([
            'product_id' => $product->id,
            'attachment_id' => $attachment->id,
            'role' => 'main',
            'sort_order' => 0,
            'status' => 'active',
        ]);
        $this->actingAs($this->userWith('media-authorized-viewer', ['cashier']));

        $response = $this->get(route('catalog.products.media', ['product' => $product, 'attachment' => $attachment]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/png');
        $this->assertStringNotContainsString('..', (string) $response->headers->get('Content-Disposition'));
        $body = $response->getContent();
        $this->assertStringNotContainsString($attachment->storage_path, $body === false ? '' : $body);
        $this->assertSame(1, AuditLog::query()->where('event', 'attachment_accessed')->count());
    }

    /** @return array{Product, Product} */
    private function products(): array
    {
        $category = Category::query()->create([
            'code' => 'MEDIA-CAT',
            'name_ar' => 'اختبار',
            'name_en' => 'Media test',
            'status' => 'active',
        ]);

        return [
            Product::query()->create(['item_code' => 'MEDIA-ONE', 'name_ar' => 'الأول', 'name_en' => 'First', 'category_id' => $category->id, 'status' => 'active']),
            Product::query()->create(['item_code' => 'MEDIA-TWO', 'name_ar' => 'الثاني', 'name_en' => 'Second', 'category_id' => $category->id, 'status' => 'active']),
        ];
    }

    private function attachmentFor(Product $product, string $filename): Attachment
    {
        $path = 'attachments/product_image/2026/08/'.Str::uuid().'.png';
        Storage::disk('local')->put($path, 'image-bytes');

        return Attachment::query()->create([
            'id' => (string) Str::uuid(),
            'source_type' => Product::class,
            'source_id' => (string) $product->id,
            'purpose' => 'product_image',
            'original_filename' => $filename,
            'storage_filename' => basename($path),
            'storage_disk' => 'local',
            'storage_path' => $path,
            'mime_type' => 'image/png',
            'detected_mime_type' => 'image/png',
            'extension' => 'png',
            'size_bytes' => 11,
            'sha256' => hash('sha256', 'image-bytes'),
            'uploaded_by' => User::query()->first()?->id,
            'visibility' => 'private',
            'status' => AttachmentState::Active,
            'request_id' => 'MEDIA-REQUEST-0001',
        ]);
    }
}
