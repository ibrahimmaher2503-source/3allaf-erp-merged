<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Platform\Models\Store;
use InvalidArgumentException;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Reader\Common\Creator\ReaderFactory;

final class ImportOpeningInventoryWorkbookAction
{
    public const HEADERS = ['product_barcode_or_code', 'inventory_location_code', 'opening_quantity'];

    /** @return array{document:\App\Modules\Inventory\Models\OpeningInventoryDocument|null,total:int,accepted:int,rejected:int,rejections:list<array<int|string,mixed>>} */
    public function execute(string $path, ?string $notes = null): array
    {
        if (! is_file($path) || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'xlsx') throw new InvalidArgumentException(__('Upload the approved XLSX Opening Inventory template.'));
        $reader = ReaderFactory::createFromFile($path); $reader->open($path);
        $rows = []; $rejections = []; $number = 0; $seen = [];
        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $number++; $cells = $row->getCells(); $values = array_map(fn ($cell) => trim((string) $cell->getValue()), $cells);
                    if ($number === 1) { if (array_slice($values, 0, 3) !== self::HEADERS) throw new InvalidArgumentException(__('The spreadsheet headers do not match the Opening Inventory template.')); continue; }
                    if ($number > 5001) throw new InvalidArgumentException(__('The import is limited to 5,000 data rows.'));
                    if (collect($values)->every(fn ($value) => $value === '')) continue;
                    $source = array_combine(self::HEADERS, array_pad(array_slice($values, 0, 3), 3, ''));
                    $errors = [];
                    if (collect($cells)->contains(fn ($cell) => $cell instanceof FormulaCell || str_starts_with(trim((string) $cell->getValue()), '='))) $errors[] = __('Spreadsheet formulas are not allowed.');
                    $product = Product::query()->where('item_code', $source['product_barcode_or_code'])->orWhereHas('barcodes', fn ($q) => $q->where('barcode', $source['product_barcode_or_code'])->where('status', 'active'))->first();
                    $store = Store::query()->visibleTo(auth()->user())->where('code', $source['inventory_location_code'])->where('status', 'active')->whereIn('type', ['warehouse', 'selling'])->first();
                    if (! $product) $errors[] = __('Unknown product barcode or code.');
                    if (! $store) $errors[] = __('Unknown, inactive, or non-stock inventory location code.');
                    if (! preg_match('/^\d+$/', $source['opening_quantity']) || bccomp($source['opening_quantity'], '0', 0) <= 0) $errors[] = __('Product quantities must be whole numbers.');
                    if ($product && ($product->product_type === 'service' || $product->isFamily())) $errors[] = __('Service products and variation families cannot receive opening inventory.');
                    if ($product && $product->average_cost === null) $errors[] = __('The Product Card does not have a valid unit cost.');
                    if ($product && $store && StockMovement::query()->where('product_id', $product->id)->where('store_id', $store->id)->exists()) $errors[] = __('This product and location already have inventory movements.');
                    if ($product && preg_match('/\.\d*[1-9]/', $source['opening_quantity'])) $errors[] = __('Product quantities must be whole numbers.');
                    $key = ($product?->id ?? 'x').':'.($store?->id ?? 'x'); if (isset($seen[$key])) $errors[] = __('Duplicate product and inventory location in this workbook.'); $seen[$key] = true;
                    if ($errors) { $rejections[] = [$number, ...array_values($source), implode(' | ', array_unique($errors))]; continue; }
                    $rows[] = ['product_id' => $product->id, 'store_id' => $store->id, 'quantity' => $source['opening_quantity']];
                }
                break;
            }
        } finally { $reader->close(); }
        $document = $rows ? app(SaveOpeningInventoryDraftAction::class)->execute($rows, notes: $notes) : null;
        return ['document' => $document, 'total' => count($rows) + count($rejections), 'accepted' => count($rows), 'rejected' => count($rejections), 'rejections' => $rejections];
    }
}
