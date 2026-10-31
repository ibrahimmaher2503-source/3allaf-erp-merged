<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Actions;

use App\Modules\Catalog\Models\Product;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Reader\XLSX\Reader;
use Throwable;

final class PreviewPurchaseOrderWorkbook
{
    public const HEADERS = ['product_code', 'product_name', 'quantity', 'unit_cost', 'notes'];

    private const MAX_ROWS = 500;

    /** @return list<array{row:int,identifier:string,name:string,quantity:string,unit_cost:string,notes:string,product_id:?int,product_name:string,error:?string}> */
    public function execute(UploadedFile $file, int $companyId): array
    {
        if (strtolower($file->getClientOriginalExtension()) !== 'xlsx' || $file->getSize() > 2097152) {
            throw new InvalidArgumentException(__('Upload an .xlsx workbook no larger than 2 MB.'));
        }

        $reader = new Reader;
        $rows = [];
        try {
            $reader->open($file->getRealPath());
            $sheets = $reader->getSheetIterator();
            $sheet = $sheets->current();
            foreach ($sheets as $candidate) {
                if ($candidate->getName() === 'Data Entry') {
                    $sheet = $candidate;

                    break;
                }
            }
            if ($sheet !== null) {
                $rowNumber = 0;
                foreach ($sheet->getRowIterator() as $row) {
                    $rowNumber++;
                    $cells = $row->getCells();
                    $values = array_map(static fn ($cell): string => trim((string) ($cell->getValue() ?? '')), $cells);
                    if ($rowNumber === 1) {
                        if (array_map('strtolower', array_slice($values, 0, 5)) !== self::HEADERS) {
                            throw new InvalidArgumentException(__('The purchase order workbook has incorrect columns. Download the official template.'));
                        }

                        continue;
                    }
                    if ($rowNumber > self::MAX_ROWS + 1) {
                        throw new InvalidArgumentException(__('The purchase order workbook is limited to 500 data rows.'));
                    }
                    if (count($values) > 5 && collect(array_slice($values, 5))->contains(fn ($value): bool => $value !== '')) {
                        throw new InvalidArgumentException(__('The purchase order workbook has unexpected columns.'));
                    }
                    if (collect($values)->every(fn ($value): bool => $value === '')) {
                        continue;
                    }
                    $rows[] = [
                        'row' => (int) $rowNumber,
                        'identifier' => $values[0] ?? '',
                        'name' => $values[1] ?? '',
                        'quantity' => $values[2] ?? '',
                        'unit_cost' => $values[3] ?? '',
                        'notes' => $values[4] ?? '',
                        'formula' => collect($cells)->contains(fn ($cell): bool => $cell instanceof FormulaCell || str_starts_with(trim((string) ($cell->getValue() ?? '')), '=')),
                    ];
                }

            }
        } catch (InvalidArgumentException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new InvalidArgumentException(__('The purchase order workbook could not be read. Check its format and try again.'), previous: $exception);
        } finally {
            $reader->close();
        }

        if ($rows === []) {
            throw new InvalidArgumentException(__('The purchase order workbook has no product rows.'));
        }

        $identifiers = array_values(array_unique(array_column($rows, 'identifier')));
        $products = Product::query()->sellable()->availableToCompany($companyId)
            ->where(fn ($query) => $query->whereIn('item_code', $identifiers)
                ->orWhereHas('barcodes', fn ($barcodes) => $barcodes->whereIn('barcode', $identifiers)->where('status', 'active')))
            ->with(['barcodes' => fn ($barcodes) => $barcodes->whereIn('barcode', $identifiers)->where('status', 'active')])
            ->get();
        $seen = [];

        return array_map(static function (array $row) use ($products, &$seen): array {
            $identifier = $row['identifier'];
            $matches = $products->filter(fn (Product $product): bool => $product->item_code === $identifier || $product->barcodes->contains('barcode', $identifier));
            $product = $matches->count() === 1 ? $matches->first() : null;
            $error = match (true) {
                $row['formula'] => __('Spreadsheet formulas are not allowed.'),
                $identifier === '' || mb_strlen($identifier) > 255 => __('Enter a product code or barcode.'),
                $matches->count() > 1 => __('This code matches more than one product.'),
                $product === null => __('Product not found.'),
                ! preg_match('/^[1-9][0-9]*$/D', $row['quantity']) || (float) $row['quantity'] > 999999 => __('Quantity must be a positive whole number.'),
                ! preg_match('/^\d+(?:\.\d{1,4})?$/D', $row['unit_cost']) || (float) $row['unit_cost'] > 999999999 => __('Unit cost must be a non-negative amount with at most four decimals.'),
                isset($seen[$product->id]) => __('Duplicate product in workbook.'),
                mb_strlen($row['notes']) > 1000 => __('Notes are too long.'),
                default => null,
            };
            if ($product !== null) {
                $seen[$product->id] = true;
            }

            return [
                'row' => $row['row'], 'identifier' => $identifier, 'name' => $row['name'],
                'quantity' => $row['quantity'], 'unit_cost' => $row['unit_cost'], 'notes' => $row['notes'],
                'product_id' => $product?->id, 'product_name' => $product?->name_ar ?? '', 'error' => $error,
            ];
        }, $rows);
    }
}
