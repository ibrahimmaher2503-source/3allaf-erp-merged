<?php

declare(strict_types=1);

namespace App\Modules\Retail\Support;

use App\Models\User;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Retail\Data\PosContext;
use App\Modules\Retail\Models\PosShift;

final class PosContextResolver
{
    /**
     * The POS screen resolves the same context from several Livewire components and
     * routes in one request; the result is remembered for the current request only.
     */
    public function resolve(User $cashier): PosContext
    {
        if (app()->runningInConsole() || ! app()->bound('request')) {
            return $this->resolveFresh($cashier);
        }
        $key = 'pos.context.'.$cashier->id;
        $request = request();
        if (! $request->attributes->has($key)) {
            $request->attributes->set($key, $this->resolveFresh($cashier));
        }

        return $request->attributes->get($key);
    }

    private function resolveFresh(User $cashier): PosContext
    {
        $shift = PosShift::query()
            ->select('pos_shifts.*')
            ->join('active_pos_shift_assignments', function ($join): void {
                $join->on('active_pos_shift_assignments.shift_id', '=', 'pos_shifts.id')
                    ->on('active_pos_shift_assignments.cash_drawer_id', '=', 'pos_shifts.cash_drawer_id');
            })
            ->open()
            ->where('pos_shifts.cashier_id', $cashier->id)
            ->where('active_pos_shift_assignments.cashier_id', $cashier->id)
            ->with(['branch', 'store.company', 'cashDrawer'])
            ->first();

        if ($shift === null) {
            $openShift = PosShift::query()->open()->where('cashier_id', $cashier->id)->first();
            if ($openShift !== null) {
                return new PosContext(null, null, null, null, __('Your active shift needs administrator review before POS can be used. Open shift management to see it.'));
            }

            $drawerIds = CashDrawer::query()->visibleTo($cashier)->pluck('id');
            if (PosShift::query()->open()->whereIn('cash_drawer_id', $drawerIds)->exists()) {
                return new PosContext(null, null, null, null, __('A cash drawer in your sales outlet is assigned to another cashier. Open shift management to see the blocking shift.'));
            }

            return new PosContext(null, null, null, null, __('POS is disabled until you open an assigned cashier shift.'));
        }

        $branch = $shift->branch;
        $store = $shift->store;
        $drawer = $shift->cashDrawer;
        if (
            $branch === null
            || $store === null
            || $drawer === null
            || $branch->status !== 'active'
            || $store->status !== 'active'
            || $store->type !== 'selling'
            || $drawer->status !== 'active'
            || (int) $store->branch_id !== (int) $branch->id
            || (int) $drawer->branch_id !== (int) $branch->id
            || (int) $drawer->store_id !== (int) $store->id
            || ! $cashier->canAccessStore((int) $store->id)
        ) {
            return new PosContext(null, null, null, null, __('POS is disabled because the active shift assignment is no longer available to this cashier.'));
        }

        // The open shift's explicit selling outlet is the only stock source.
        // Historical branch-wide primary mappings are deliberately ignored.
        return new PosContext($branch, $store, $drawer, $shift);
    }
}
