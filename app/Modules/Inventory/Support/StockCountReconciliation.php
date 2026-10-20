<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Support;

use App\Modules\Inventory\Models\StockCount;
use App\Modules\Inventory\Models\StockCountLine;
use App\Modules\Inventory\Models\StockMovement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class StockCountReconciliation
{
    /** @param Collection<int, StockCountLine> $lines
     * @return array<int, array{movement_quantity_after_reference:string,movement_after_completion:string,expected_quantity:string,normalized_closing_quantity:string,variance_quantity:string}>
     */
    public function calculateMany(StockCount $count, Collection $lines): array
    {
        $modern = $lines->filter(fn (StockCountLine $line): bool => $line->is_counted
            && $line->reference_movement_id !== null && $line->counted_movement_id !== null);
        $sums = DB::table('stock_count_lines as count_lines')
            ->leftJoin('stock_movements as movements', function ($join): void {
                $join->on('movements.product_id', '=', 'count_lines.product_id')
                    ->on('movements.store_id', '=', 'count_lines.store_id')
                    ->whereColumn('movements.id', '>', 'count_lines.reference_movement_id');
            })
            ->whereIn('count_lines.id', $modern->pluck('id')->all())
            ->selectRaw('count_lines.id, COALESCE(SUM(CASE WHEN movements.id <= count_lines.counted_movement_id THEN movements.quantity ELSE 0 END), 0) AS to_count, COALESCE(SUM(CASE WHEN movements.id > count_lines.counted_movement_id THEN movements.quantity ELSE 0 END), 0) AS after_count')
            ->groupBy('count_lines.id')->get()->keyBy('id');
        $results = [];
        foreach ($lines as $line) {
            if (! $line->is_counted) {
                continue;
            }
            if ($line->reference_movement_id === null || $line->counted_movement_id === null) {
                $results[$line->id] = $this->calculate($count, $line);

                continue;
            }
            $sum = $sums->get($line->id);
            $results[$line->id] = $this->fromTotals($line, (string) ($sum->to_count ?? '0'), (string) ($sum->after_count ?? '0'));
        }

        return $results;
    }

    /** @return array{movement_quantity_after_reference:string,movement_after_completion:string,expected_quantity:string,normalized_closing_quantity:string,variance_quantity:string} */
    public function calculate(StockCount $count, StockCountLine $line): array
    {
        $countedAt = $line->counted_at ?? $line->completed_at;
        if (! $line->is_counted || $countedAt === null) {
            throw new InvalidArgumentException(__('A physical count and count time are required for reconciliation.'));
        }

        $movements = StockMovement::query()->where('product_id', $line->product_id)->where('store_id', $line->store_id);
        if ($line->reference_movement_id !== null && $line->counted_movement_id !== null) {
            $toCount = (string) (clone $movements)->where('id', '>', $line->reference_movement_id)
                ->where('id', '<=', $line->counted_movement_id)->sum('quantity');
            $afterCount = (string) (clone $movements)->where('id', '>', $line->counted_movement_id)->sum('quantity');
        } else {
            $toCount = (string) (clone $movements)->where('posted_at', '>', $count->reference_at)
                ->where('posted_at', '<=', $countedAt)->sum('quantity');
            $afterCount = (string) (clone $movements)->where('posted_at', '>', $countedAt)->sum('quantity');
        }

        return $this->fromTotals($line, $toCount, $afterCount);
    }

    /** @return array{movement_quantity_after_reference:string,movement_after_completion:string,expected_quantity:string,normalized_closing_quantity:string,variance_quantity:string} */
    private function fromTotals(StockCountLine $line, string $toCount, string $afterCount): array
    {
        $expectedAtCount = bcadd((string) $line->reference_on_hand, $toCount, 6);
        $expectedClosing = bcadd($expectedAtCount, $afterCount, 6);
        $normalizedClosing = bcadd((string) $line->counted_quantity, $afterCount, 6);

        return [
            'movement_quantity_after_reference' => bcadd($toCount, $afterCount, 6),
            'movement_after_completion' => $afterCount,
            'expected_quantity' => $expectedClosing,
            'normalized_closing_quantity' => $normalizedClosing,
            'variance_quantity' => bcsub($normalizedClosing, $expectedClosing, 6),
        ];
    }
}
