<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\StockCount;
use App\Modules\Inventory\Support\ResolveStockCountProduct;
use App\Support\ProductQuantity;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Ramsey\Uuid\Uuid;
use Throwable;

final class ImportStockCountWorkbookAction
{
    public const HEADERS = ['product_identifier', 'actual_quantity'];

    private const MAX_ROWS = 2000;

    /** @return array{total:int, imported:int, rejected:int, errors:list<array{row:int,identifier:string,reason:string}>} */
    public function execute(int $countId, int $storeId, UploadedFile $file): array
    {
        Gate::authorize('stock_counts.edit');
        Gate::authorize('stock_counts.participate');
        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);
        $count = StockCount::query()->findOrFail($countId);
        abort_unless($count->locations()->where('store_id', $storeId)->exists()
            && $count->members()->where('user_id', $actor->id)->exists(), 404);
        app(AssertInventoryStoreScope::class)->execute($storeId);
        if ($count->status !== 'in_progress') {
            throw new InvalidArgumentException(__('Count input is closed for this session.'));
        }

        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, ['xlsx', 'csv'], true)) {
            throw new InvalidArgumentException(__('Upload an .xlsx or .csv stocktake file.'));
        }

        $reader = $extension === 'xlsx' ? new XlsxReader : new CsvReader;
        $rows = [];
        $number = 0;
        try {
            $reader->open($file->getRealPath());
            $sheets = $reader->getSheetIterator();
            $sheet = $sheets->current();
            foreach ($sheets as $candidate) {
                if (strcasecmp((string) $candidate->getName(), 'Data Entry') === 0) {
                    $sheet = $candidate;

                    break;
                }
            }
            foreach ($sheet->getRowIterator() as $row) {
                $number++;
                $cells = $row->getCells();
                $values = array_map(static fn ($cell): string => trim((string) ($cell->getValue() ?? '')), $cells);
                if ($number === 1) {
                    $values[0] = ltrim($values[0] ?? '', "\xEF\xBB\xBF");
                    if (array_map('strtolower', $values) !== self::HEADERS) {
                        throw new InvalidArgumentException(__('The stocktake file must contain exactly product_identifier and actual_quantity columns.'));
                    }

                    continue;
                }
                if ($number > self::MAX_ROWS + 1) {
                    throw new InvalidArgumentException(__('The stocktake import is limited to 2,000 rows.'));
                }
                if (count($values) > 2 && collect(array_slice($values, 2))->contains(static fn ($value): bool => $value !== '')) {
                    throw new InvalidArgumentException(__('The stocktake file contains unexpected columns.'));
                }
                if (($values[0] ?? '') === '' && ($values[1] ?? '') === '') {
                    continue;
                }
                $rows[] = [
                    'number' => $number,
                    'identifier' => $values[0] ?? '',
                    'quantity' => $values[1] ?? '',
                    'formula' => collect($cells)->contains(static fn ($cell): bool => $cell instanceof FormulaCell
                        || str_starts_with(trim((string) ($cell->getValue() ?? '')), '=')),
                ];
            }
        } catch (InvalidArgumentException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new InvalidArgumentException(__('The stocktake spreadsheet could not be read. Check its format and try again.'), previous: $exception);
        } finally {
            $reader->close();
        }

        if ($number === 0 || $rows === []) {
            throw new InvalidArgumentException(__('The stocktake file has no count rows.'));
        }

        $companyId = (int) $count->store()->value('company_id');
        $restrictedScope = $count->count_type === 'full' || in_array($count->scope_type, ['category', 'supplier'], true);
        $scopedProductIds = $restrictedScope
            ? $count->lines()->where('store_id', $storeId)->pluck('product_id')->flip()
            : collect();
        $accepted = [];
        $errors = [];
        $seen = [];
        foreach ($rows as $row) {
            $identifier = $row['identifier'];
            $reason = null;
            if ($row['formula']) {
                $reason = __('Spreadsheet formulas are not allowed.');
            } elseif ($identifier === '' || mb_strlen($identifier) > 255) {
                $reason = __('Enter a valid product code or barcode.');
            } elseif (! preg_match('/^\d+(?:\.0+)?$/', $row['quantity'])) {
                $reason = __('Product quantities must be whole numbers and zero or greater.');
            } else {
                try {
                    ProductQuantity::normalize($row['quantity'], 'actual_quantity', true);
                } catch (ValidationException $exception) {
                    $reason = collect($exception->errors())->flatten()->first() ?? __('Invalid quantity.');
                }
            }

            $product = null;
            if ($reason === null) {
                $base = Product::query()->sellable()->availableToCompany($companyId, storeId: $storeId);
                $product = (clone $base)->where('item_code', $identifier)->first();
                if ($product === null) {
                    $matches = (clone $base)->whereHas('barcodes', fn ($barcodes) => $barcodes->where('barcode', $identifier)->where('status', 'active'))->limit(2)->get();
                    $product = $matches->count() === 1 ? $matches->first() : null;
                    if ($matches->count() > 1) {
                        $reason = __('The barcode matches more than one product. Use its unique product code.');
                    }
                }
                if ($product === null && $reason === null) {
                    $reason = __('Unknown or unavailable product for this company and warehouse.');
                }
                if ($product !== null && (int) app(ResolveStockCountProduct::class)->resolve((string) $product->item_code)->id !== (int) $product->id) {
                    $reason = __('The product code is ambiguous. Use a unique product code.');
                }
                if ($product !== null && isset($seen[$product->id])) {
                    $reason = __('Duplicate product in this file; the first row is retained.');
                }
                if ($product !== null && $restrictedScope && ! $scopedProductIds->has($product->id)) {
                    $reason = __('This product is outside the count-session scope.');
                }
            }

            if ($reason !== null) {
                $errors[] = ['row' => $row['number'], 'identifier' => $identifier, 'reason' => $reason];

                continue;
            }
            $seen[$product->id] = true;
            $accepted[] = ['product' => $product, 'quantity' => $row['quantity']];
        }

        $digest = hash_file('sha256', $file->getRealPath());
        DB::transaction(function () use ($accepted, $countId, $storeId, $companyId, $digest): void {
            foreach ($accepted as $item) {
                $product = $item['product'];
                abort_unless(Product::query()->sellable()->availableToCompany($companyId, storeId: $storeId)
                    ->whereKey($product->id)->exists(), 403);
                $requestId = Uuid::uuid5(Uuid::NAMESPACE_URL, "stocktake-import:{$countId}:{$storeId}:{$product->id}:{$digest}")->toString();
                $line = app(SetStockCountQuantityAction::class)->execute($countId, $storeId, (string) $product->item_code,
                    (string) $item['quantity'], $requestId, 'stocktake-excel', null);
                if ((int) $line->product_id !== (int) $product->id) {
                    throw new InvalidArgumentException(__('The product identifier changed during import. Please retry with a fresh file.'));
                }
            }
        });

        return ['total' => count($rows), 'imported' => count($accepted), 'rejected' => count($errors), 'errors' => $errors];
    }
}
