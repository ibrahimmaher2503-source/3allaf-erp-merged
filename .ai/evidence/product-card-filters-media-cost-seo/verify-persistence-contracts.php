<?php

use App\Modules\Catalog\Support\ProductPricingContract;

if (getenv('APP_ENV') !== 'local') { throw new RuntimeException('This focused verifier requires explicit APP_ENV=local and an isolated worktree.'); }
$root = dirname(__DIR__, 3);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$app->instance('translation.loader', new Illuminate\Translation\FileLoader($app['files'], $app->langPath()));
$app->forgetInstance('translator');

$checks = 0;
$assert = function (bool $ok, string $message) use (&$checks): void {
    if (! $ok) throw new RuntimeException($message);
    $checks++;
};
$save = file_get_contents($root.'/app/Modules/Catalog/Actions/SaveProductAction.php');
$start = strpos($save, '$pricingData = $data;');
$end = strpos($save, "if (\$product !== null && ! array_key_exists('product_type', \$data))", $start);
// Execute the real action's bounded cost preparation/validation block. These
// in-memory inputs verify its contract; they do not emulate a database save.
$costBlock = str_replace('ProductPricingContract::class', '\\App\\Modules\\Catalog\\Support\\ProductPricingContract::class', substr($save, $start, $end - $start));
foreach ([
    [[], '12.5000', true, '12.5000', false],
    [['average_cost' => ''], '12.5000', true, '12.5000', false],
    [['average_cost' => '20.2500'], '12.5000', true, '20.2500', true],
    [['average_cost' => '99'], '12.5000', false, '12.5000', false],
    [['average_cost' => ''], '0.0000', true, '0.0000', false],
] as [$input, $saved, $canView, $expected, $hasCostMutation]) {
    $data = $input + ['sale_price' => '30.00'];
    $product = (object) ['average_cost' => $saved];
    $actor = new class($canView) {
        public function __construct(private bool $allowed) {}
        public function hasPermission(string $permission): bool { return $this->allowed; }
    };
    $saveAsDraft = false;
    eval($costBlock);
    $assert($pricing['average_cost'] === $expected && array_key_exists('average_cost', $data) === $hasCostMutation, 'Saved/changed cost contract failed.');
}
$data = ['average_cost' => '', 'sale_price' => '30.00'];
$product = (object) ['average_cost' => null];
$rejected = false;
try { eval($costBlock); } catch (InvalidArgumentException) { $rejected = true; }
$assert($rejected, 'A genuinely missing final cost was accepted.');

// The following checks are source contracts, not DB save/reload evidence.
$source = file_get_contents($root.'/resources/views/catalog/product-form.blade.php');
$markup = substr($source, strpos($source, '}; ?>') + 5);
foreach (['meta_title_ar', 'meta_title_en', 'meta_description_ar', 'meta_description_en'] as $field) {
    $assert(! str_contains($source, "'productForm.{$field}'") && ! str_contains($markup, 'wire:model="productForm.'.$field.'"'), 'Hidden SEO field still enters the form payload.');
    $assert(str_contains($save, "if (! array_key_exists(\$optionalField, \$data))"), 'Existing optional SEO values are no longer preserved on omitted fields.');
}
foreach (['short_description_ar', 'short_description_en', 'full_description_ar', 'full_description_en'] as $field) {
    $assert(str_contains($markup, 'wire:model="productForm.'.$field.'"'), 'A descriptive field was removed.');
}
$assert(str_contains($markup, '$filterGroups') && str_contains($markup, 'productForm.filter_value_ids') && ! str_contains($source, '$lookups'), 'Configured filters are not replacing disconnected lookups.');
$assert(str_contains($save, '$product->filterValues()->sync($ids)') && ! str_contains($save, 'familyOptionValues()') && ! str_contains($save, 'familyOptionGroups()'), 'Descriptive save still mutates the variant matrix.');
$variants = file_get_contents($root.'/resources/views/catalog/product-variations.blade.php');
$assert(! str_contains($variants, "__('Product Filters')") && str_contains($source, '$product?->has_variations'), 'Filters still appear in the ordinary product Variations area.');
$import = file_get_contents($root.'/app/Modules/Catalog/Actions/ImportProductCardsWorkbookAction.php');
$assert(str_contains($import, "'filter_value_ids' =>") && ! str_contains($import, 'familyOptionValues()->sync'), 'Workbook filters still configure SKU options.');
foreach (['en', 'ar', 'ar-EG'] as $locale) json_decode(file_get_contents($root.'/lang/'.$locale.'.json'), true, 512, JSON_THROW_ON_ERROR);
echo 'PASS: '.$checks.' focused saved-cost runtime and filter/SEO source-contract checks. Actual database save/reload and migration checks remain unexecuted.'.PHP_EOL;
