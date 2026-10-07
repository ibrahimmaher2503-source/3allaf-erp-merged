<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductImage;
use App\Modules\Platform\Models\Attachment;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Models\Store;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

final class ProductImageLimitFeedbackTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_fifth_additional_image_shows_the_translated_limit_without_storing_an_attachment(): void
    {
        app()->setLocale('ar');

        $permission = Permission::query()->firstOrCreate(['code' => 'products_categories_brands.edit'], [
            'module' => 'products_categories_brands',
            'action' => 'edit',
            'sensitivity' => 'normal',
            'status' => 'active',
        ]);
        $role = Role::query()->create([
            'code' => 'qa-product-media-'.Str::lower(Str::random(8)),
            'name_ar' => 'محرر المنتجات للاختبار',
            'name_en' => 'QA product editor',
            'status' => 'active',
        ]);
        $role->permissions()->sync([$permission->id]);

        $user = User::factory()->create([
            'username' => 'qa-product-media-'.Str::lower(Str::random(8)),
            'status' => 'active',
        ]);
        $user->roles()->sync([$role->id]);

        $company = Company::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id]);
        Store::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'status' => 'active',
        ]);
        $user->branchScopes()->create(['branch_id' => $branch->id, 'status' => 'active']);

        $product = Product::factory()->create();
        $this->createProductImage($product, $user, 'main', 0);
        for ($index = 1; $index <= 4; $index++) {
            $this->createProductImage($product, $user, 'additional', $index);
        }

        $linkedAttachmentCount = Attachment::query()
            ->where('source_type', Product::class)
            ->where('source_id', (string) $product->id)
            ->count();

        Livewire::actingAs($user)
            ->test('catalog::product-form', ['product' => $product])
            ->set('mediaRole', 'additional')
            ->set('mediaUpload', UploadedFile::fake()->create('fifth-extra.png', 5, 'image/png'))
            ->call('uploadImage')
            ->assertSee(__('A product can have at most four additional images.'))
            ->assertDontSee(__('Unable to complete the request. Please review the data and try again.'));

        self::assertSame(5, $linkedAttachmentCount);
        self::assertSame(5, Attachment::query()
            ->where('source_type', Product::class)
            ->where('source_id', (string) $product->id)
            ->count());
        self::assertSame(5, ProductImage::query()->where('product_id', $product->id)->where('status', 'active')->count());
    }

    private function createProductImage(Product $product, User $user, string $role, int $sortOrder): void
    {
        $id = (string) Str::uuid();
        $attachment = Attachment::query()->create([
            'id' => $id,
            'source_type' => Product::class,
            'source_id' => (string) $product->id,
            'purpose' => 'product_image',
            'original_filename' => $id.'.png',
            'storage_filename' => $id.'.png',
            'storage_disk' => 'local',
            'storage_path' => 'attachments/product_image/qa/'.$id.'.png',
            'mime_type' => 'image/png',
            'detected_mime_type' => 'image/png',
            'extension' => 'png',
            'size_bytes' => 5,
            'sha256' => hash('sha256', $id),
            'uploaded_by' => $user->id,
            'visibility' => 'private',
            'status' => 'active',
        ]);

        ProductImage::query()->create([
            'product_id' => $product->id,
            'attachment_id' => $attachment->id,
            'role' => $role,
            'sort_order' => $sortOrder,
            'status' => 'active',
        ]);
    }
}
