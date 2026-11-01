<?php

declare(strict_types=1);

namespace App\Modules\Retail\Actions;

use App\Models\User;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Store;
use App\Modules\Retail\Enums\ShiftState;
use App\Modules\Retail\Models\PosShift;
use App\Modules\Retail\Support\DecimalMoney;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Open a shift (`docs/32` §6).
 *
 * Exclusivity is the point of this action: at most one active shift per drawer
 * and per cashier. Both are checked under a row lock on the drawer so two
 * concurrent opens cannot both pass (§16).
 */
final class OpenShiftAction
{
    private const INVALID_DRAWER_CONFIGURATION = 'The selected cash drawer has an invalid company, branch, or store configuration. Ask an administrator to correct the drawer before opening a shift.';

    public function execute(User $cashier, CashDrawer $drawer, string $openingFloat, string $idempotencyKey): PosShift
    {
        abort_unless($cashier->can('shifts_cash_movements.create'), 403);

        try {
            return $this->attempt($cashier, $drawer, $openingFloat, $idempotencyKey);
        } catch (UniqueConstraintViolationException $e) {
            $existing = PosShift::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                return $this->assertReplaySafe($existing, $cashier, $drawer, $openingFloat);
            }

            if (PosShift::query()->where('cashier_id', $cashier->id)->where('status', ShiftState::Open->value)->exists()) {
                throw new InvalidArgumentException(__('This cashier already has an active shift.'), previous: $e);
            }
            if (PosShift::query()->where('cash_drawer_id', $drawer->getKey())->where('status', ShiftState::Open->value)->exists()) {
                throw new InvalidArgumentException($this->occupiedDrawerMessage($drawer), previous: $e);
            }

            throw $e;
        }
    }

    private function occupiedDrawerMessage(CashDrawer $drawer): string
    {
        $shift = PosShift::query()->open()->where('cash_drawer_id', $drawer->id)->with('cashier:id,name')->first();
        if ($shift === null) {
            return __('This drawer already has an active shift.');
        }

        return __('Drawer :drawer is in use by :cashier on shift #:shift since :time.', [
            'drawer' => $drawer->code, 'cashier' => $shift->cashier?->name ?? __('Another cashier'),
            'shift' => $shift->id, 'time' => $shift->opened_at?->format('H:i') ?? '—',
        ]);
    }

    private function attempt(User $cashier, CashDrawer $drawer, string $openingFloat, string $idempotencyKey): PosShift
    {
        return DB::transaction(function () use ($cashier, $drawer, $openingFloat, $idempotencyKey): PosShift {
            $existing = PosShift::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                return $this->assertReplaySafe($existing, $cashier, $drawer, $openingFloat);
            }

            $requestedDrawerId = (int) $drawer->getKey();
            $requestedDrawer = CashDrawer::query()->find($requestedDrawerId);
            if ($requestedDrawer === null) {
                throw new InvalidArgumentException(__(self::INVALID_DRAWER_CONFIGURATION));
            }
            abort_unless($requestedDrawer->visibleTo($cashier)->whereKey($requestedDrawerId)->exists(), 403);

            // Authorization edits lock the user before replacing branch/store scopes.
            // Hold a compatible current user lock before taking the master-data locks below.
            $lockedCashier = User::query()->sharedLock()->find($cashier->id);
            abort_unless($lockedCashier !== null && $lockedCashier->status === 'active', 403);
            $cashier = $lockedCashier;

            $requestedBranchId = (int) $requestedDrawer->getAttribute('branch_id');
            $requestedStoreId = (int) $requestedDrawer->getAttribute('store_id');
            $branch = $requestedBranchId > 0
                ? Branch::query()->sharedLock()->find($requestedBranchId)
                : null;
            $store = $requestedStoreId > 0
                ? Store::query()->lockForUpdate()->find($requestedStoreId)
                : null;

            // Match master-data ordering before the drawer so a store change
            // cannot deadlock with the shift insert's branch foreign key.
            // Revalidate after all locks so concurrent reassignment cannot
            // open against a different, unlocked outlet.
            $drawer = CashDrawer::query()->lockForUpdate()->find($requestedDrawerId);
            if ($drawer === null || $branch === null || $store === null
                || (int) $drawer->getAttribute('branch_id') !== $requestedBranchId
                || (int) $drawer->getAttribute('store_id') !== $requestedStoreId) {
                throw new InvalidArgumentException(__(self::INVALID_DRAWER_CONFIGURATION));
            }

            $this->assertVisibleToCurrentScope($cashier, $requestedBranchId, $requestedStoreId);

            if ($drawer->getAttribute('status') !== 'active') {
                throw new InvalidArgumentException(__('This cash drawer is not active.'));
            }

            $float = $this->money($openingFloat);
            if (bccomp($float, '0', 2) < 0) {
                throw new InvalidArgumentException(__('The opening float cannot be negative.'));
            }

            $companyId = (int) $drawer->getAttribute('company_id');
            $branchId = (int) $drawer->getAttribute('branch_id');
            $storeId = (int) $drawer->getAttribute('store_id');
            $company = $companyId > 0 ? Company::query()->find($companyId) : null;
            if ($company === null
                || (int) $branch->getAttribute('id') !== $branchId
                || (int) $store->getAttribute('id') !== $storeId
                || (int) $branch->company_id !== $companyId
                || (int) $store->company_id !== $companyId
                || (int) $store->branch_id !== $branchId
                || $store->getAttribute('status') !== 'active'
                || $store->getAttribute('type') !== 'selling') {
                throw new InvalidArgumentException(__(self::INVALID_DRAWER_CONFIGURATION));
            }
            $currency = strtoupper(trim((string) $company->getAttribute('currency_code')));
            if (! preg_match('/^[A-Z]{3}$/', $currency) || $currency === 'TBD') {
                throw new InvalidArgumentException(__('The drawer company must have an approved three-letter currency before a shift can open.'));
            }

            if (PosShift::query()->where('cash_drawer_id', $drawer->getKey())->whereIn('status', [ShiftState::Open->value])->exists()) {
                throw new InvalidArgumentException($this->occupiedDrawerMessage($drawer));
            }

            if (PosShift::query()->where('cashier_id', $cashier->id)->where('status', ShiftState::Open->value)->exists()) {
                throw new InvalidArgumentException(__('This cashier already has an active shift.'));
            }

            abort_unless($drawer->eligibleCashiers()->whereKey($cashier->id)->exists()
                || ((int) $drawer->assigned_user_id === (int) $cashier->id)
                || (! $drawer->eligibleCashiers()->exists() && $drawer->assigned_user_id === null), 403);

            $pendingCount = PosShift::query()
                ->where('cashier_id', $cashier->id)
                ->whereIn('status', [ShiftState::ClosingSubmitted->value, ShiftState::VarianceReview->value])
                ->lockForUpdate()
                ->count();
            if ($pendingCount >= 2) {
                throw new InvalidArgumentException(__('You have two previous shifts awaiting approval. Resolve at least one pending close before opening another shift.'));
            }

            $shift = PosShift::query()->create([
                'branch_id' => $drawer->getAttribute('branch_id'),
                'store_id' => $drawer->getAttribute('store_id'),
                'cash_drawer_id' => $drawer->getKey(),
                'cashier_id' => $cashier->id,
                'opened_by' => $cashier->id,
                'status' => ShiftState::Open->value,
                'opening_cash' => $float,
                'currency_code' => $currency,
                'company_name_ar_snapshot' => $company->name_ar,
                'company_name_en_snapshot' => $company->name_en,
                'branch_code_snapshot' => $branch->code,
                'branch_name_ar_snapshot' => $branch->name_ar,
                'branch_name_en_snapshot' => $branch->name_en,
                'store_code_snapshot' => $store->code,
                'store_name_ar_snapshot' => $store->name_ar,
                'store_name_en_snapshot' => $store->name_en,
                'cash_drawer_code_snapshot' => $drawer->code,
                'cash_drawer_name_ar_snapshot' => $drawer->name_ar,
                'cash_drawer_name_en_snapshot' => $drawer->name_en,
                'idempotency_key' => $idempotencyKey,
                'opened_at' => now(),
                'lock_version' => 1,
            ]);

            DB::table('active_pos_shift_assignments')->insert([
                'shift_id' => $shift->getKey(),
                'cashier_id' => $cashier->id,
                'cash_drawer_id' => $drawer->getKey(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            app(RecordAuditEvent::class)->execute(
                category: 'retail',
                event: 'open_shift',
                source: $shift,
                before: [],
                after: $shift->only(['status', 'opening_cash', 'cash_drawer_id', 'cashier_id']),
                branchId: $shift->getAttribute('branch_id'),
                storeId: $shift->getAttribute('store_id'),
                metadata: ['opening_float' => $float],
            );

            return $shift;
        });
    }

    /**
     * Recheck branch/store access with current locking reads after master-data locks.
     * The shared user-row lock is acquired before the branch/store/drawer locks to
     * serialize with SaveUserAuthorizationAction without reversing drawer/user FK order.
     */
    private function assertVisibleToCurrentScope(User $cashier, int $branchId, int $storeId): void
    {
        if ($cashier->canBypassApproval()) {
            abort_unless($cashier->canAccessBranch($branchId) && $cashier->canAccessStore($storeId), 403);

            return;
        }

        if ($cashier->hasGlobalApplicationAccess()) {
            return;
        }

        $branchScope = $cashier->branchScopes()
            ->where('branch_id', $branchId)
            ->where('status', 'active')
            ->sharedLock()
            ->first();
        if ($branchScope !== null) {
            return;
        }

        $storeScope = $cashier->storeScopes()
            ->where('store_id', $storeId)
            ->where('status', 'active')
            ->sharedLock()
            ->first();

        abort_unless($storeScope !== null, 403);
    }

    private function replay(string $idempotencyKey, User $cashier, CashDrawer $drawer, string $openingFloat): PosShift
    {
        $existing = PosShift::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing === null) {
            throw new InvalidArgumentException(__('This idempotency key was already used with a different request payload.'));
        }

        return $this->assertReplaySafe($existing, $cashier, $drawer, $openingFloat);
    }

    private function assertReplaySafe(PosShift $existing, User $cashier, CashDrawer $drawer, string $openingFloat): PosShift
    {
        $float = $this->money($openingFloat);
        $replaySafe = (int) $existing->getAttribute('cashier_id') === $cashier->id
            && (int) $existing->getAttribute('cash_drawer_id') === (int) $drawer->getKey()
            && (int) $existing->getAttribute('branch_id') === (int) $drawer->getAttribute('branch_id')
            && (int) $existing->getAttribute('store_id') === (int) $drawer->getAttribute('store_id')
            && bccomp((string) $existing->getAttribute('opening_cash'), $float, 2) === 0;

        if (! $replaySafe) {
            throw new InvalidArgumentException(__('This idempotency key was already used with a different request payload.'));
        }

        return $existing;
    }

    /** @return numeric-string */
    private function money(string $value): string
    {
        $value = trim($value);
        if ($value === '' || ! preg_match('/^-?\d+(?:\.\d+)?$/', $value)) {
            throw new InvalidArgumentException(__('The opening float must be a valid number.'));
        }

        return DecimalMoney::round($value, 2, __('The opening float must be a valid number.'));
    }
}
