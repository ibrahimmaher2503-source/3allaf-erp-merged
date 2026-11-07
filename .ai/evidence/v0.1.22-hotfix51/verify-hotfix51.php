<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Catalog\Actions\AddBarcodeAction;
use App\Modules\Catalog\Actions\RemoveSupplierAction;
use App\Modules\Catalog\Actions\ReserveProductIdentityAction;
use App\Modules\Catalog\Actions\SaveProductAction;
use App\Modules\Catalog\Actions\ToggleSupplierStatusAction;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductSupplier;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Role;
use App\Support\ApplicationVersion;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

$root = dirname(__DIR__, 3);
$expectedDatabase = trim((string) ($argv[1] ?? ''));
$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (! $condition) {
        throw new RuntimeException($message);
    }
};
$saveAttributes = static fn (Category $category, Supplier $supplier, string $token): array => [
    'item_code' => '',
    'name_ar' => 'منتج تحقق '.$token,
    'name_en' => 'HF51 Product '.$token,
    'model_number' => 'HF51-'.$token,
    'product_type' => 'standard',
    'category_id' => $category->id,
    'preferred_supplier_id' => $supplier->id,
    'barcode_registration_type' => 'local',
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
    $app->setLocale('en');

    $assert(ApplicationVersion::RELEASE === '0.1.22-hotfix51', 'Hotfix51 version mismatch.');
    $database = (string) DB::connection()->getDatabaseName();
    // The preserved reconciliation helper authenticates this historical prefix; the exact
    // generated database name is still passed by, and recorded in, the Hotfix51 journal.
    $assert($expectedDatabase !== '' && $database === $expectedDatabase && str_starts_with($database, 'toyjoy_hf48_restore_'), 'Focused verification refuses any database except its recorded Hotfix51 isolated restore.');

    $identitySource = file_get_contents($root.'/app/Modules/Catalog/Actions/ReserveProductIdentityAction.php');
    $productForm = file_get_contents($root.'/resources/views/catalog/product-form.blade.php');
    $invoiceForm = file_get_contents($root.'/resources/views/purchasing/invoices.blade.php');
    $supplierForm = file_get_contents($root.'/resources/views/catalog/suppliers.blade.php');
    $removeSource = file_get_contents($root.'/app/Modules/Catalog/Actions/RemoveSupplierAction.php');

    foreach ([$productForm, $invoiceForm, $supplierForm] as $view) {
        $app->make('blade.compiler')->compileString($view);
    }

    $assert(substr_count($identitySource, 'resolveSupplierPrefix($supplier)') === 2 && str_contains($identitySource, "^SUP-(\\d{4})$") && str_contains($identitySource, "^\\d{4,}$"), 'Preview and reservation do not share the strict canonical/numeric supplier-prefix resolver.');
    $assert(str_contains($identitySource, 'lockForUpdate()') && str_contains($identitySource, 'self::SEQUENCE_NAME_PREFIX.$prefix') && ! str_contains($identitySource, '->max('), 'Product-code reservation lost its locked prefix-scoped sequence.');
    $assert(str_contains($invoiceForm, 'quickProductLocalPreview') && substr_count($invoiceForm, "ReserveProductIdentityAction::class)->preview(\$supplierId)") >= 2 && str_contains($invoiceForm, 'createQuickProduct(SaveProductAction $save'), 'Invoice inline product preview/save does not reuse the Product Card resolver and save action.');
    $assert(! str_contains($productForm, 'public bool $savingDraft') && str_contains($productForm, '$this->persistProduct(false, $action, $barcodeAction)') && str_contains($productForm, '$this->persistProduct(true, $action, $barcodeAction)'), 'Save and Save Draft are not isolated by an explicit, non-client-mutable mode.');
    $assert(str_contains($productForm, 'The final save did not complete the product. No draft was created') && str_contains($productForm, '$this->rules($saveAsDraft)'), 'Final save does not retain explicit localized validation/finalization guards.');
    $assert(str_contains($productForm, "\$validated['barcode_registration_type'] === 'international' && filled(\$this->supplierBarcode)") && ! str_contains($productForm, "! \$hasActiveBarcode && \$validated['barcode_registration_type'] === 'international'"), 'Product Card finalization can still skip an entered international barcode when its generated identity barcode exists.');
    foreach (['Local product code help', 'Classification and type help', 'Optional product data help', 'Physical and merchandising attributes help', 'Protected product media help', 'Optional product content help', 'Product web and SEO help'] as $helpTitle) {
        $assert(str_contains($productForm, "__('{$helpTitle}')"), "Missing contextual-help section: {$helpTitle}.");
    }
    $assert(str_contains($supplierForm, "Gate::authorize('suppliers.logical_delete')") && str_contains($supplierForm, 'wire:confirm=') && str_contains($supplierForm, 'toggleSupplierStatus'), 'Supplier delete is missing server authorization, confirmation, or deactivation access.');
    $assert(str_contains($removeSource, "lockForUpdate()") && str_contains($removeSource, "event: 'delete_unused_supplier'") && str_contains($removeSource, "'purchase_invoices'") && str_contains($removeSource, "'supplier_import_rows'"), 'Supplier deletion does not lock, audit, and protect the required dependencies.');

    foreach (['en', 'ar', 'ar-EG'] as $locale) {
        $translations = json_decode((string) file_get_contents($root.'/lang/'.$locale.'.json'), true, 512, JSON_THROW_ON_ERROR);
        foreach (['The selected supplier code must be numeric with at least four digits or use the canonical SUP-#### format before a local product code can be generated.', 'The final save did not complete the product. No draft was created; review the highlighted fields and try again.', 'Supplier :code cannot be deleted because it is linked to :reason. Deactivate it instead; products, invoices, balances, and history are preserved.', 'Delete supplier', 'Product web and SEO help'] as $key) {
            $assert(isset($translations[$key]) && trim((string) $translations[$key]) !== '', "Missing {$locale} translation: {$key}.");
        }
    }

    DB::beginTransaction();
    try {
        $token = strtoupper(substr(str_replace('-', '', (string) Str::uuid()), 0, 10));
        $company = Company::query()->where('status', 'active')->firstOrFail();
        $role = Role::query()->where('code', 'system-administrator')->where('status', 'active')->firstOrFail();
        $actor = User::query()->create([
            'name' => 'HF51 focused verifier',
            'username' => 'hf51-'.$token,
            'email' => 'hf51-'.$token.'@example.test',
            'password' => Hash::make(Str::password(24)),
            'status' => 'active',
        ]);
        $actor->forceFill(['email_verified_at' => now()])->save();
        $actor->roles()->attach($role->id);
        Auth::login($actor);
        request()->merge(['company_id' => $company->id]);

        $category = Category::query()->create([
            'code' => 'HF51-CAT-'.$token,
            'name_ar' => 'فئة تحقق',
            'name_en' => 'HF51 Category',
            'status' => 'active',
            'sort_order' => 999,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
        $canonical = Supplier::query()->where('code', 'SUP-0002')->where('status', 'active')->firstOrFail();
        $numeric = Supplier::query()->create(['code' => '0123456', 'name_ar' => 'مورد رقمي', 'name_en' => 'Numeric Supplier', 'settlement_method' => 'cash', 'status' => 'active', 'lock_version' => 0, 'created_by' => $actor->id, 'updated_by' => $actor->id]);

        $reserve = app(ReserveProductIdentityAction::class);
        $assert($reserve->resolveSupplierPrefix($canonical) === '0002', 'SUP-0002 did not resolve to prefix 0002.');
        $assert($reserve->resolveSupplierPrefix($numeric) === '0123', '0123456 did not preserve its leading zero in prefix 0123.');
        $assert($reserve->preview($canonical->id) === '0002-000001', 'SUP-0002 preview was not exactly 0002-000001.');
        $assert($reserve->preview($numeric->id) === '0123-000001', '0123456 preview was not exactly 0123-000001.');

        $save = app(SaveProductAction::class);
        $productCount = Product::query()->count();
        $draft = $save->execute([
            'item_code' => '',
            'name_ar' => 'مسودة تحقق '.$token,
            'name_en' => '',
            'model_number' => '',
            'product_type' => 'standard',
            'category_id' => $category->id,
            'barcode_registration_type' => 'local',
            'status' => 'active',
            '_save_as_draft' => true,
        ]);
        $assert($draft->status === 'inactive' && $draft->item_code === null && ! $draft->barcodes()->exists(), 'Explicit Save Draft no longer creates an incomplete inactive draft.');
        $assert(Product::query()->count() === $productCount + 1, 'Explicit Save Draft did not create exactly one product.');

        $completed = $save->execute($saveAttributes($category, $canonical, 'COMPLETE-'.$token), $draft->id, $draft->lock_version);
        $assert($completed->id === $draft->id && Product::query()->count() === $productCount + 1, 'Completing an existing draft duplicated the product.');
        $assert($completed->item_code === '0002-000001' && $completed->status === 'active', 'Completing the existing draft did not finalize its identity and requested status.');
        $assert($completed->barcodes()->where('barcode', '0002-000001')->where('source', 'local')->exists(), 'Completed local Product Card did not retain its generated identity barcode.');

        $international = $save->execute([
            ...$saveAttributes($category, $canonical, 'INTERNATIONAL-'.$token),
            'barcode_registration_type' => 'international',
        ]);
        $internationalBarcode = $gtin13('622123456789');
        app(AddBarcodeAction::class)->addSupplierBarcode($international->id, $internationalBarcode);
        $assert($international->barcodes()->where('barcode', $internationalBarcode)->where('source', 'international')->exists(), 'Entered international barcode was not preserved alongside the generated product identity.');

        $beforeInvalidFinal = Product::query()->count();
        try {
            $save->execute([
                ...$saveAttributes($category, $canonical, 'INVALID-'.$token),
                'preferred_supplier_id' => null,
            ]);
            throw new RuntimeException('Invalid final Product Card unexpectedly persisted.');
        } catch (InvalidArgumentException $expected) {
            $assert(str_contains($expected->getMessage(), 'active preferred supplier is required'), 'Invalid final Product Card did not return the specific supplier error.');
        }
        $assert(Product::query()->count() === $beforeInvalidFinal, 'Invalid final Save silently persisted a draft.');

        $unused = Supplier::query()->create(['code' => 'HF51-UNUSED-'.$token, 'name_ar' => 'مورد غير مستخدم', 'name_en' => 'Unused Supplier', 'settlement_method' => 'cash', 'status' => 'active', 'lock_version' => 0, 'created_by' => $actor->id, 'updated_by' => $actor->id]);
        $unusedId = $unused->id;
        app(RemoveSupplierAction::class)->execute($unusedId);
        $assert(! Supplier::query()->whereKey($unusedId)->exists(), 'Authorized unused supplier was not deleted.');
        $assert(AuditLog::query()->where('event', 'delete_unused_supplier')->where('source_id', (string) $unusedId)->exists(), 'Unused supplier deletion was not audited.');

        $linked = Supplier::query()->create(['code' => 'HF51-LINKED-'.$token, 'name_ar' => 'مورد مرتبط', 'name_en' => 'Linked Supplier', 'settlement_method' => 'cash', 'status' => 'active', 'lock_version' => 0, 'created_by' => $actor->id, 'updated_by' => $actor->id]);
        ProductSupplier::query()->create(['product_id' => $completed->id, 'supplier_id' => $linked->id, 'is_preferred' => false, 'created_by' => $actor->id, 'updated_by' => $actor->id]);
        try {
            app(RemoveSupplierAction::class)->execute($linked->id);
            throw new RuntimeException('Linked supplier unexpectedly deleted.');
        } catch (ValidationException $expected) {
            $message = collect($expected->errors())->flatten()->first();
            $assert(is_string($message) && str_contains($message, $linked->code) && str_contains($message, 'Deactivate'), 'Linked supplier restriction did not explain the dependency and deactivation path.');
        }
        $assert(Supplier::query()->whereKey($linked->id)->where('status', 'active')->exists() && ProductSupplier::query()->where('supplier_id', $linked->id)->exists(), 'Linked supplier deletion attempt changed the supplier or its history.');
        $deactivated = app(ToggleSupplierStatusAction::class)->execute($linked->id);
        $assert($deactivated->status === 'inactive' && ProductSupplier::query()->where('supplier_id', $linked->id)->exists(), 'Linked supplier deactivation did not preserve its history.');

        $unauthorized = User::query()->create([
            'name' => 'HF51 unauthorized verifier',
            'username' => 'hf51-noauth-'.$token,
            'email' => 'hf51-noauth-'.$token.'@example.test',
            'password' => Hash::make(Str::password(24)),
            'status' => 'active',
        ]);
        $unauthorized->forceFill(['email_verified_at' => now()])->save();
        $protected = Supplier::query()->create(['code' => 'HF51-PROTECTED-'.$token, 'name_ar' => 'مورد محمي', 'name_en' => 'Protected Supplier', 'settlement_method' => 'cash', 'status' => 'active', 'lock_version' => 0, 'created_by' => $actor->id, 'updated_by' => $actor->id]);
        Auth::login($unauthorized);
        try {
            app(RemoveSupplierAction::class)->execute($protected->id);
            throw new RuntimeException('Unauthorized supplier deletion unexpectedly succeeded.');
        } catch (AuthorizationException) {
            $assert(Supplier::query()->whereKey($protected->id)->exists(), 'Unauthorized supplier deletion changed the record.');
        }
        Auth::login($actor);

        echo "HOTFIX51_FOCUSED_VERIFICATION=PASS assertions={$assertions} database={$database} canonical=0002-000001 numeric=0123-000001 draft_completion=pass supplier_delete=pass linked_deactivate=pass\n";
    } finally {
        DB::rollBack();
        Auth::logout();
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'HOTFIX51_FOCUSED_VERIFICATION=FAIL '.$exception->getMessage()."\n");
    exit(1);
}
