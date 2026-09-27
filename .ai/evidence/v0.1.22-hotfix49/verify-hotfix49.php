<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Catalog\Actions\AddBarcodeAction;
use App\Modules\Catalog\Actions\ReserveProductIdentityAction;
use App\Modules\Catalog\Actions\SaveProductAction;
use App\Modules\Catalog\Exceptions\ImmutableItemCodeChangeException;
use App\Modules\Catalog\Models\Barcode;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\GlobalProductCodeSequence;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Role;
use App\Support\ApplicationVersion;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

$root = dirname(__DIR__, 3);
$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (! $condition) {
        throw new RuntimeException($message);
    }
};
$productAttributes = static fn (Category $category, string $code, string $token): array => [
    'item_code' => $code,
    'name_ar' => 'منتج تحقق '.$token,
    'name_en' => 'HF49 Product '.$token,
    'model_number' => 'HF49-MODEL-'.$token,
    'product_type' => 'standard',
    'category_id' => $category->id,
    'status' => 'active',
    'barcode_mode' => 'none',
    'average_cost' => '1',
    'sale_price' => '2',
    'lock_version' => 0,
];
$saveAttributes = static fn (Category $category, Supplier $supplier, string $token, string $barcodeType = 'local'): array => [
    'item_code' => '',
    'name_ar' => 'منتج مولد '.$token,
    'name_en' => 'Generated Product '.$token,
    'model_number' => 'HF49-GEN-'.$token,
    'product_type' => 'standard',
    'category_id' => $category->id,
    'preferred_supplier_id' => $supplier->id,
    'barcode_registration_type' => $barcodeType,
    'status' => 'active',
    'average_cost' => '1',
    'sale_price' => '2',
];
$gtin13 = static function (string $twelveDigits): string {
    $sum = 0;
    for ($index = 0; $index < 12; $index++) {
        $sum += ((int) $twelveDigits[$index]) * ($index % 2 === 0 ? 1 : 3);
    }

    return $twelveDigits.(string) ((10 - ($sum % 10)) % 10);
};

try {
    require $root.'/vendor/autoload.php';
    $app = require $root.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();

    $assert(ApplicationVersion::RELEASE === '0.1.22-hotfix49', 'Hotfix49 version mismatch.');
    $database = (string) DB::connection()->getDatabaseName();
    $assert($database === 'toyjoy_hf49_verify_2aa6628094c5', 'Focused verification refuses any database except the recorded Hotfix49 database.');

    $component = file_get_contents($root.'/resources/views/components/product-line-lookup.blade.php');
    $orderForm = file_get_contents($root.'/resources/views/purchasing/partials/order-form.blade.php');
    $orders = file_get_contents($root.'/resources/views/purchasing/orders.blade.php');
    $invoices = file_get_contents($root.'/resources/views/purchasing/invoices.blade.php');
    $productForm = file_get_contents($root.'/resources/views/catalog/product-form.blade.php');
    $identitySource = file_get_contents($root.'/app/Modules/Catalog/Actions/ReserveProductIdentityAction.php');

    foreach ([$component, $orderForm, $orders, $invoices, $productForm] as $view) {
        $app->make('blade.compiler')->compileString($view);
    }
    $assert(str_contains($component, "'labelVisuallyHidden' => false") && str_contains($component, "'sr-only'") && str_contains($component, "'mt-1'") && str_contains($component, 'aria-label="{{ $label }}"'), 'Product lookup does not retain a screen-reader label and aligned input offset.');
    $assert(substr_count($orderForm, ':label-visually-hidden="true"') === 1 && str_contains($orderForm, '<span>{{ __(\'Product\') }}</span>'), 'Purchase-order create/edit rows do not retain one column heading with hidden row labels.');
    $assert(str_contains($orders, "routeIs('purchasing.orders.create', 'purchasing.orders.edit')") && str_contains($orders, "@include('purchasing.partials.order-form'"), 'Purchase-order create and edit do not share the corrected full-page row form.');
    $assert(substr_count($invoices, ':label-visually-hidden="true"') === 1 && str_contains($invoices, '<span>{{ __(\'Product\') }}</span>') && str_contains($invoices, '$this->editingInvoiceId'), 'Purchase-invoice create/edit rows do not retain one heading with hidden row labels.');
    $assert(str_contains($component, "dir=\"{{ str_starts_with(app()->getLocale(), 'ar') ? 'rtl' : 'ltr' }}\"") && str_contains($component, "'text-right' : 'text-left'"), 'Product selector lost locale-aware RTL/LTR behavior.');
    $assert(str_contains($identitySource, 'lockForUpdate()') && str_contains($identitySource, 'self::SEQUENCE_NAME_PREFIX.$prefix') && ! str_contains($identitySource, '->max('), 'Product-code allocation is not using the locked prefix sequence without MAX.');
    $assert(str_contains($productForm, 'first four digits of the selected supplier code') && str_contains($invoices, 'quickProductLocalPreviewError'), 'Affected preview/help/error paths were not updated.');

    DB::beginTransaction();
    try {
        $token = strtoupper(substr(str_replace('-', '', (string) Str::uuid()), 0, 10));
        $company = Company::query()->where('status', 'active')->firstOrFail();
        $role = Role::query()->where('code', 'system-administrator')->where('status', 'active')->firstOrFail();
        $actor = User::query()->create([
            'name' => 'HF49 focused verifier',
            'username' => 'hf49-'.$token,
            'email' => 'hf49-'.$token.'@example.test',
            'password' => Hash::make(Str::password(24)),
            'status' => 'active',
        ]);
        $actor->forceFill(['email_verified_at' => now()])->save();
        $actor->roles()->attach($role->id);
        Auth::login($actor);
        request()->merge(['company_id' => $company->id]);

        $category = Category::query()->create([
            'code' => 'HF49-CAT-'.$token,
            'name_ar' => 'فئة تحقق',
            'name_en' => 'HF49 Category',
            'status' => 'active',
            'sort_order' => 999,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
        $supplierOne = Supplier::query()->create(['code' => '0123456-'.$token, 'name_ar' => 'مورد أول', 'name_en' => 'First Supplier', 'settlement_method' => 'cash', 'status' => 'active', 'lock_version' => 0, 'created_by' => $actor->id, 'updated_by' => $actor->id]);
        $supplierTwo = Supplier::query()->create(['code' => '0123999-'.$token, 'name_ar' => 'مورد ثان', 'name_en' => 'Second Supplier', 'settlement_method' => 'cash', 'status' => 'active', 'lock_version' => 0, 'created_by' => $actor->id, 'updated_by' => $actor->id]);
        $supplierCollision = Supplier::query()->create(['code' => '0456000-'.$token, 'name_ar' => 'مورد تصادم', 'name_en' => 'Collision Supplier', 'settlement_method' => 'cash', 'status' => 'active', 'lock_version' => 0, 'created_by' => $actor->id, 'updated_by' => $actor->id]);
        $supplierInvalid = Supplier::query()->create(['code' => 'ABCD1234-'.$token, 'name_ar' => 'مورد غير صالح', 'name_en' => 'Valid English Supplier Name', 'settlement_method' => 'cash', 'status' => 'active', 'lock_version' => 0, 'created_by' => $actor->id, 'updated_by' => $actor->id]);

        $legacy = Product::query()->create($productAttributes($category, 'ALP0000001-'.$token, 'LEGACY-'.$token));
        $legacyCode = $legacy->item_code;
        $collision = Product::query()->create($productAttributes($category, '0456-000001', 'COLLISION-'.$token));

        $reserve = app(ReserveProductIdentityAction::class);
        $save = app(SaveProductAction::class);
        $assert($reserve->preview($supplierOne->id) === '0123-000001', 'Leading-zero preview is not 0123-000001.');
        $first = $save->execute($saveAttributes($category, $supplierOne, 'ONE-'.$token));
        $assert($first->item_code === '0123-000001', 'First leading-zero code was not allocated exactly.');
        $assert($first->barcodes()->where('barcode', '0123-000001')->where('source', 'local')->exists(), 'Generated local identity was not preserved as its existing primary barcode behavior.');

        $assert($reserve->preview($supplierTwo->id) === '0123-000002', 'A supplier sharing prefix 0123 did not see the shared next serial.');
        $second = $save->execute($saveAttributes($category, $supplierTwo, 'TWO-'.$token));
        $assert($second->item_code === '0123-000002' && $first->item_code !== $second->item_code, 'Shared-prefix allocations are not unique and sequential.');
        $sequence = GlobalProductCodeSequence::query()->where('sequence_name', 'product_identity:0123')->firstOrFail();
        $assert($sequence->next_number === 3, 'Shared-prefix sequence did not advance atomically to 3.');

        $assert($reserve->preview($supplierCollision->id) === '0456-000002', 'Existing product-code collision was not skipped by preview.');
        $afterCollision = $save->execute($saveAttributes($category, $supplierCollision, 'COLLISION-'.$token));
        $assert($afterCollision->item_code === '0456-000002' && $collision->fresh()->item_code === '0456-000001', 'Existing product code was changed or reused during collision handling.');

        try {
            $reserve->preview($supplierInvalid->id);
            throw new RuntimeException('Invalid supplier prefix unexpectedly produced a preview.');
        } catch (InvalidArgumentException $expected) {
            $assert(str_contains($expected->getMessage(), 'must start with four digits'), 'Invalid supplier prefix did not return the clear validation error.');
        }
        try {
            $reserve->reserve(null, null);
            throw new RuntimeException('Missing supplier unexpectedly generated a code.');
        } catch (InvalidArgumentException $expected) {
            $assert(str_contains($expected->getMessage(), 'active preferred supplier is required'), 'Missing supplier did not fail before generation.');
        }

        $draft = $save->execute([
            'item_code' => '', 'name_ar' => 'مسودة', 'name_en' => '', 'model_number' => '', 'product_type' => 'standard',
            'category_id' => $category->id, 'barcode_registration_type' => 'local', 'status' => 'active', '_save_as_draft' => true,
        ]);
        $assert($draft->status === 'inactive' && $draft->item_code === null && ! $draft->barcodes()->exists(), 'Inactive draft behavior changed.');

        try {
            $save->execute([
                ...$saveAttributes($category, $supplierOne, 'IMMUTABLE-'.$token),
                'item_code' => '9999-999999',
            ], $first->id, $first->lock_version);
            throw new RuntimeException('Final generated code was mutable.');
        } catch (ImmutableItemCodeChangeException) {
            $assert($first->fresh()->item_code === '0123-000001', 'Final-code immutability did not preserve the saved code.');
        }

        $international = $save->execute($saveAttributes($category, $supplierOne, 'INT-'.$token, 'international'));
        $gtin = $gtin13('622123456789');
        app(AddBarcodeAction::class)->addSupplierBarcode($international->id, $gtin);
        $assert($international->barcodes()->where('barcode', $gtin)->where('source', 'international')->exists(), 'International barcode behavior was not preserved.');
        $assert($legacy->fresh()->item_code === $legacyCode, 'An existing saved product code was renumbered.');

        echo "HOTFIX49_FOCUSED_VERIFICATION=PASS assertions={$assertions} database={$database} row_alignment=pass leading_zero=pass shared_prefix=pass collision=pass existing_codes=preserved\n";
    } finally {
        DB::rollBack();
        Auth::logout();
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'HOTFIX49_FOCUSED_VERIFICATION=FAIL '.$exception->getMessage()."\n");
    exit(1);
}
