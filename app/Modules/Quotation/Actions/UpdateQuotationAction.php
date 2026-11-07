<?php

declare(strict_types=1);

namespace App\Modules\Quotation\Actions;

use App\Models\User;
use App\Modules\Customer\Models\Customer;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Quotation\Models\Quotation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class UpdateQuotationAction
{
    /** @param array<string, mixed> $data */
    public function execute(User $user, Quotation $quotation, array $data): Quotation
    {
        Gate::forUser($user)->authorize('quotations.edit');
        abort_unless($user->canAccessBranch($quotation->branch_id) || $user->canAccessStore($quotation->store_id), 403);
        $submittedLines = array_values((array) ($data['lines'] ?? []));
        $validatedLines = app(CreateQuotationAction::class)->validateLines($quotation->activity_type, $submittedLines);
        if (array_key_exists('customer_id', $data) && blank($data['customer_id'])) {
            throw ValidationException::withMessages(['customer_id' => __('Customer is required.')]);
        }
        if (filled($data['customer_id'] ?? null)) {
            abort_unless(Customer::query()->whereKey($data['customer_id'])->whereHas('scopes', fn ($scope) => $scope->where('branch_id', $quotation->branch_id)->orWhere('store_id', $quotation->store_id))->exists(), 404);
        }

        return DB::transaction(function () use ($user, $quotation, $data, $submittedLines, $validatedLines): Quotation {
            $locked = Quotation::query()->whereKey($quotation->id)->lockForUpdate()->firstOrFail();
            if (! in_array($locked->status, ['draft', 'issued'], true)) {
                throw ValidationException::withMessages(['quotation' => __('Only active quotations can be edited.')]);
            }
            $before = $locked->only(['customer_id', 'valid_until', 'terms', 'notes', 'subtotal', 'total']);
            $existingLines = $locked->lines()->orderBy('line_number')->orderBy('id')->lockForUpdate()->get();
            $existingById = $existingLines->keyBy('id');
            $removedIds = array_map('intval', (array) ($data['removed_line_ids'] ?? []));
            foreach ($removedIds as $removedId) {
                if (! $existingById->has($removedId)) {
                    throw ValidationException::withMessages(['removed_line_ids' => __('The selected quotation line is unavailable.')]);
                }
            }
            $removedLookup = array_fill_keys($removedIds, true);
            $remainingLines = $existingLines->reject(fn ($line): bool => isset($removedLookup[$line->id]))->values();
            $seenIds = [];
            foreach ($submittedLines as $index => $submittedLine) {
                $lineId = (int) ($submittedLine['id'] ?? 0);
                if ($lineId === 0) {
                    $remainingLines->push($locked->lines()->make($validatedLines[$index]));

                    continue;
                }
                if (! $existingById->has($lineId) || isset($seenIds[$lineId])) {
                    throw ValidationException::withMessages(['lines' => __('The selected quotation line is unavailable.')]);
                }
                $seenIds[$lineId] = true;
                if (! isset($removedLookup[$lineId])) {
                    $existingById->get($lineId)->fill($validatedLines[$index]);
                }
            }
            if ($remainingLines->isEmpty()) {
                throw ValidationException::withMessages(['lines' => __('At least one compatible quotation line is required.')]);
            }

            if ($removedIds !== []) {
                $locked->lines()->whereKey($removedIds)->delete();
            }
            $subtotal = 0.0;
            foreach ($remainingLines as $index => $line) {
                $lineTotal = round((float) $line['quantity'] * (float) $line['unit_price'], 2);
                $subtotal += $lineTotal;
                $line->fill(['line_number' => $index + 1, 'line_total' => $lineTotal])->save();
            }
            $locked->mutate([
                'customer_id' => array_key_exists('customer_id', $data) ? $data['customer_id'] : $locked->customer_id,
                'quotation_date' => $data['quotation_date'] ?? $locked->quotation_date,
                'valid_until' => $data['valid_until'],
                'terms' => array_key_exists('terms', $data) ? $data['terms'] : $locked->terms,
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $locked->notes,
                'subtotal' => $subtotal,
                ...app(CalculateQuotationTotals::class)->execute($subtotal, [
                    'discount_type' => $data['discount_type'] ?? $locked->discount_type,
                    'discount_value' => $data['discount_value'] ?? $locked->discount_value,
                    'tax_rate' => $data['tax_rate'] ?? $locked->tax_rate,
                ]),
                'updated_by' => $user->id,
                'lock_version' => $locked->lock_version + 1,
            ]);
            app(RecordAuditEvent::class)->execute('quotations', 'quotation_updated', $locked, before: $before, after: $locked->only(['customer_id', 'valid_until', 'terms', 'notes', 'subtotal', 'total']), branchId: $locked->branch_id, storeId: $locked->store_id, metadata: ['line_count' => $remainingLines->count(), 'non_posting' => true]);

            return $locked->fresh('lines');
        }, 5);
    }
}
