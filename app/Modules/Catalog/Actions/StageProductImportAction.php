<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductImportBatch;
use App\Modules\Catalog\Models\ProductImportRow;
use App\Modules\Platform\Actions\RecordAuditEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Reader\Common\Creator\ReaderFactory;
use Throwable;

class StageProductImportAction
{
    private const REQUIRED_HEADERS = ['item_code', 'name_ar', 'name_en', 'category_code'];

    private const ALLOWED_HEADERS = [
        'item_code', 'name_ar', 'name_en', 'description_ar', 'description_en', 'model_number',
        'product_type', 'unit_of_measure', 'category_code', 'brand_code', 'status', 'colour', 'size',
        'character', 'fractional_quantity', 'keywords_ar', 'keywords_en', 'key_points_ar', 'key_points_en',
    ];

    public function stage(string $storagePath, string $originalFilename, string $mode, int $userId): ProductImportBatch
    {
        if (! in_array($mode, ['create_only', 'update_existing'], true)) {
            throw new InvalidArgumentException(__('The selected import mode is not supported.'));
        }

        Gate::authorize($mode === 'update_existing' ? 'products_categories_brands.edit' : 'products_categories_brands.create');

        $absolutePath = Storage::disk('local')->path($storagePath);
        if (! is_file($absolutePath)) {
            throw new InvalidArgumentException(__('The staged import file could not be found.'));
        }

        $hash = hash_file('sha256', $absolutePath);
        if (! is_string($hash)) {
            throw new InvalidArgumentException(__('The import file could not be fingerprinted.'));
        }

        if (ProductImportBatch::query()->where('created_by', $userId)->where('sha256', $hash)->where('status', '!=', 'cancelled')->exists()) {
            throw new InvalidArgumentException(__('This import file was already staged by this user.'));
        }

        $reader = ReaderFactory::createFromFile($absolutePath);
        $reader->open($absolutePath);

        try {
            $headers = null;
            $rows = [];
            $rowNumber = 0;
            $seenCodes = [];
            $categoryCodes = Category::query()->where('status', 'active')->pluck('id', 'code')->mapWithKeys(fn ($id, $code) => [strtoupper($code) => $id])->all();
            $brandCodes = Brand::query()->where('status', 'active')->pluck('id', 'code')->mapWithKeys(fn ($id, $code) => [strtoupper($code) => $id])->all();

            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $rowNumber++;
                    $values = array_map(static fn ($cell): mixed => $cell->getValue(), $row->getCells());

                    if ($rowNumber === 1) {
                        $headers = array_map(fn (mixed $value): string => $this->normalizeHeader($value), $values);
                        $this->assertHeaders($headers);
                        continue;
                    }

                    if ($rowNumber > 5001) {
                        throw new InvalidArgumentException(__('The import is limited to 5,000 data rows.'));
                    }

                    if ($this->isBlankRow($values)) {
                        continue;
                    }

                    $raw = [];
                    foreach ($headers as $index => $header) {
                        if ($header !== '') {
                            $raw[$header] = $values[$index] ?? null;
                        }
                    }

                    $errors = $this->formulaErrors($row->getCells());
                    $mapped = $this->mapRow($raw, $mode, $categoryCodes, $brandCodes, $seenCodes, $errors);
                    $status = $mapped['errors'] === [] ? 'valid' : 'invalid';
                    $rows[] = new ProductImportRow([
                        'row_number' => $rowNumber,
                        'raw_data' => $raw,
                        'mapped_data' => $mapped['data'],
                        'errors' => $mapped['errors'],
                        'status' => $status,
                    ]);

                    if (count($rows) >= 250) {
                        $this->persistRows($rows, $userId, $originalFilename, $storagePath, $hash, $mode, $headers);
                        $rows = [];
                    }
                }
                break;
            }

            $batch = $this->persistRows($rows, $userId, $originalFilename, $storagePath, $hash, $mode, $headers ?? []);
            $batch->loadCount(['rows as total_rows_count']);
            $batch->update([
                'total_rows' => $batch->rows()->count(),
                'valid_rows' => $batch->rows()->where('status', 'valid')->count(),
                'invalid_rows' => $batch->rows()->where('status', 'invalid')->count(),
                'status' => 'ready_for_review',
            ]);

            app(RecordAuditEvent::class)->execute(
                category: 'catalog_import',
                event: 'stage_product_import',
                source: $batch,
                after: $batch->only(['id', 'original_filename', 'mode', 'total_rows', 'valid_rows', 'invalid_rows']),
            );

            return $batch->fresh();
        } catch (Throwable $exception) {
            ProductImportBatch::query()
                ->where('created_by', $userId)
                ->where('sha256', $hash)
                ->where('status', 'staging')
                ->get()
                ->each(fn (ProductImportBatch $batch): bool => (bool) $batch->delete());
            Storage::disk('local')->delete($storagePath);
            throw $exception;
        } finally {
            $reader->close();
        }
    }

    public function approve(ProductImportBatch $batch, SaveProductAction $saveProduct): ProductImportBatch
    {
        Gate::authorize('products_categories_brands.approve');

        return DB::transaction(function () use ($batch, $saveProduct): ProductImportBatch {
            $batch = ProductImportBatch::query()->lockForUpdate()->findOrFail($batch->id);
            if ($batch->status !== 'ready_for_review' || $batch->invalid_rows > 0) {
                throw new InvalidArgumentException(__('Only a ready import with no rejected rows can be approved.'));
            }

            foreach ($batch->rows()->where('status', 'valid')->lockForUpdate()->get() as $row) {
                $data = $row->mapped_data ?? [];
                $product = Product::query()->where('item_code', $data['item_code'])->first();
                $saved = $saveProduct->execute($data, $product?->id, $product?->lock_version);
                $row->update(['status' => $product ? 'updated' : 'created', 'product_id' => $saved->id]);
            }

            $batch->update(['status' => 'completed', 'approved_at' => now()]);
            app(RecordAuditEvent::class)->execute(
                category: 'catalog_import',
                event: 'approve_product_import',
                source: $batch,
                after: $batch->only(['id', 'status', 'total_rows', 'valid_rows']),
            );

            return $batch->fresh();
        });
    }

    public function cancel(ProductImportBatch $batch): ProductImportBatch
    {
        Gate::authorize('products_categories_brands.create');
        abort_unless($batch->created_by === auth()->id(), 404);

        if (! in_array($batch->status, ['staging', 'ready_for_review'], true)) {
            throw new InvalidArgumentException(__('Only a staged or reviewable import can be cancelled.'));
        }

        $batch->update(['status' => 'cancelled']);
        app(RecordAuditEvent::class)->execute(
            category: 'catalog_import',
            event: 'cancel_product_import',
            source: $batch,
            after: ['status' => 'cancelled'],
        );

        return $batch->fresh();
    }

    /** @param array<int, mixed> $cells */
    private function formulaErrors(array $cells): array
    {
        foreach ($cells as $cell) {
            if ($cell instanceof FormulaCell || (is_string($cell->getValue()) && str_starts_with(trim($cell->getValue()), '='))) {
                return [__('Formula cells are not accepted in product imports.')];
            }
        }

        return [];
    }

    /** @param array<string, mixed> $raw @param array<string, int> $categoryCodes @param array<string, int> $brandCodes @param array<string, bool> $seenCodes @param array<int, string> $initialErrors */
    private function mapRow(array $raw, string $mode, array $categoryCodes, array $brandCodes, array &$seenCodes, array $initialErrors): array
    {
        $errors = $initialErrors;
        $itemCode = strtoupper(trim((string) ($raw['item_code'] ?? '')));
        $categoryCode = strtoupper(trim((string) ($raw['category_code'] ?? '')));
        $brandCode = strtoupper(trim((string) ($raw['brand_code'] ?? '')));
        $type = strtolower(trim((string) ($raw['product_type'] ?? 'standard')));
        $status = strtolower(trim((string) ($raw['status'] ?? 'active')));

        if ($itemCode === '') $errors[] = __('Item code is required.');
        if (isset($seenCodes[$itemCode])) $errors[] = __('The item code is duplicated in this batch.');
        if ($itemCode !== '') $seenCodes[$itemCode] = true;
        if (trim((string) ($raw['name_ar'] ?? '')) === '') $errors[] = __('Arabic product name is required.');
        if (trim((string) ($raw['name_en'] ?? '')) === '') $errors[] = __('English product name is required.');
        if (! isset($categoryCodes[$categoryCode])) $errors[] = __('The category code is missing or inactive.');
        if ($brandCode !== '' && ! isset($brandCodes[$brandCode])) $errors[] = __('The brand code is missing or inactive.');
        if (! in_array($type, ['standard', 'composite', 'service'], true)) $errors[] = __('The product type is not supported.');
        if (! in_array($status, ['active', 'inactive'], true)) $errors[] = __('The product status is not supported.');
        if ($mode === 'create_only' && $itemCode !== '' && Product::query()->where('item_code', $itemCode)->exists()) $errors[] = __('The item code already exists; Create Only does not update existing products.');

        $data = [
            'item_code' => $itemCode,
            'name_ar' => trim((string) ($raw['name_ar'] ?? '')),
            'name_en' => trim((string) ($raw['name_en'] ?? '')),
            'description_ar' => $this->nullableString($raw['description_ar'] ?? null),
            'description_en' => $this->nullableString($raw['description_en'] ?? null),
            'model_number' => $this->nullableString($raw['model_number'] ?? null),
            'product_type' => $type,
            'unit_of_measure' => $this->nullableString($raw['unit_of_measure'] ?? null),
            'category_id' => $categoryCodes[$categoryCode] ?? null,
            'brand_id' => $brandCodes[$brandCode] ?? null,
            'status' => $status,
            'colour' => $this->nullableString($raw['colour'] ?? null),
            'size' => $this->nullableString($raw['size'] ?? null),
            'character' => $this->nullableString($raw['character'] ?? null),
            'fractional_quantity' => $this->booleanValue($raw['fractional_quantity'] ?? false),
            'keywords_ar' => $this->nullableString($raw['keywords_ar'] ?? null),
            'keywords_en' => $this->nullableString($raw['keywords_en'] ?? null),
            'key_points_ar' => $this->nullableString($raw['key_points_ar'] ?? null),
            'key_points_en' => $this->nullableString($raw['key_points_en'] ?? null),
        ];

        return ['data' => $data, 'errors' => array_values(array_unique($errors))];
    }

    private function normalizeHeader(mixed $value): string
    {
        $header = strtolower(trim((string) $value));
        $header = preg_replace('/[^a-z0-9_]+/', '_', $header) ?? '';
        return trim($header, '_');
    }

    /** @param array<int, string> $headers */
    private function assertHeaders(array $headers): void
    {
        $unknown = array_diff(array_filter($headers), self::ALLOWED_HEADERS);
        $missing = array_diff(self::REQUIRED_HEADERS, $headers);
        if ($unknown !== []) throw new InvalidArgumentException(__('Unsupported import columns: :columns', ['columns' => implode(', ', $unknown)]));
        if ($missing !== []) throw new InvalidArgumentException(__('Required import columns are missing: :columns', ['columns' => implode(', ', $missing)]));
    }

    /** @param array<int, mixed> $values */
    private function isBlankRow(array $values): bool
    {
        return collect($values)->every(fn (mixed $value): bool => trim((string) $value) === '');
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : $value;
    }

    private function booleanValue(mixed $value): bool
    {
        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'y', 'نعم'], true);
    }

    /** @param array<int, ProductImportRow> $rows */
    private function persistRows(array $rows, int $userId, string $filename, string $path, string $hash, string $mode, array $headers): ProductImportBatch
    {
        $batch = ProductImportBatch::query()->firstOrCreate(
            ['created_by' => $userId, 'sha256' => $hash],
            [
                'original_filename' => $filename,
                'storage_path' => $path,
                'mime_type' => mime_content_type(Storage::disk('local')->path($path)) ?: null,
                'size_bytes' => Storage::disk('local')->size($path),
                'mode' => $mode,
                'status' => 'staging',
                'headers' => $headers,
            ],
        );

        if ($batch->wasRecentlyCreated === false && $batch->status === 'cancelled' && $rows !== []) {
            $batch->rows()->delete();
            $batch->update([
                'original_filename' => $filename,
                'storage_path' => $path,
                'mime_type' => mime_content_type(Storage::disk('local')->path($path)) ?: null,
                'size_bytes' => Storage::disk('local')->size($path),
                'mode' => $mode,
                'status' => 'staging',
                'headers' => $headers,
                'total_rows' => 0,
                'valid_rows' => 0,
                'invalid_rows' => 0,
                'approved_at' => null,
            ]);
        }

        foreach ($rows as $row) {
            $row->product_import_batch_id = $batch->id;
            $row->save();
        }

        return $batch;
    }
}
