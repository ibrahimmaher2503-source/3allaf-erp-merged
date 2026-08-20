<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Assets\Actions\ApproveAssetEventAction;
use App\Modules\Assets\Actions\CheckoutAssetAction;
use App\Modules\Assets\Actions\CreateAssetAction;
use App\Modules\Assets\Actions\CreateAssetEventAction;
use App\Modules\Assets\Actions\InspectAssetAction;
use App\Modules\Assets\Actions\ReserveAssetAction;
use App\Modules\Assets\Actions\ReturnAssetAction;
use App\Modules\Assets\Models\AssetEvent;
use App\Modules\Assets\Models\AssetReservation;
use App\Modules\Assets\Models\RentalAsset;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('party/assets', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('rental_assets.view'), 403);
        $mode = (string) $request->string('mode', 'workspace');
        abort_unless(in_array($mode, ['workspace', 'reservations', 'returns', 'history'], true), 404);
        $assets = RentalAsset::query()->visibleTo($user)
            ->with(['store', 'reservations' => fn ($query) => $query->latest('starts_at')->limit(20), 'checkouts' => fn ($query) => $query->latest('checked_out_at')->limit(20), 'returns' => fn ($query) => $query->latest('returned_at')->limit(20), 'events' => fn ($query) => $query->latest('id')->limit(20)])
            ->withCount(['reservations', 'checkouts', 'returns', 'events'])
            ->when(! $request->filled('status') && $mode === 'reservations', fn ($q) => $q->whereIn('status', ['available', 'reserved', 'checked_out']))
            ->when(! $request->filled('status') && $mode === 'returns', fn ($q) => $q->whereIn('status', ['checked_out', 'under_inspection', 'damaged', 'under_maintenance', 'lost']))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($search) => $search->where('code', 'like', '%'.$request->string('q').'%')->orWhere('name_en', 'like', '%'.$request->string('q').'%')->orWhere('name_ar', 'like', '%'.$request->string('q').'%')))
            ->latest('id')->paginate(20)->withQueryString();
        $calendarStart = now()->startOfDay();
        $calendarEnd = now()->addDays(30)->endOfDay();
        $calendarReservations = AssetReservation::query()->with(['asset', 'store'])
            ->whereHas('asset', fn ($query) => $query->visibleTo($user))
            ->whereIn('status', ['reserved', 'fulfilled'])
            ->where('ends_at', '>=', $calendarStart)
            ->where('starts_at', '<=', $calendarEnd)
            ->orderBy('starts_at')
            ->limit(100)
            ->get();
        $historyEvents = $mode === 'history'
            ? AssetEvent::query()->with(['asset', 'store', 'responsibleUser', 'approvalRecord'])
                ->whereHas('asset', fn ($query) => $query->visibleTo($user))
                ->latest('id')->paginate(30, ['*'], 'history_page')->withQueryString()
            : null;
        $branches = Branch::query()->visibleTo($user)->where('status', 'active')->orderBy('name_en')->get();
        $stores = Store::query()->visibleTo($user)->where('status', 'active')->orderBy('name_en')->get();

        return view('pages.party.assets', compact('mode', 'assets', 'branches', 'stores', 'calendarReservations', 'calendarStart', 'calendarEnd', 'historyEvents'));
    })->middleware('can:rental_assets.view')->name('party.assets.index');

    Route::post('party/assets', function (Request $request, CreateAssetAction $action) {
        /** @var User $user */ $user = $request->user();
        $data = $request->validate(['code' => ['required', 'string', 'max:120'], 'name_ar' => ['required', 'string', 'max:190'], 'name_en' => ['required', 'string', 'max:190'], 'category' => ['nullable', 'string', 'max:120'], 'branch_id' => ['required', 'integer'], 'store_id' => ['required', 'integer'], 'location' => ['nullable', 'string', 'max:190'], 'condition' => ['required', Rule::in(['good', 'fair', 'poor'])], 'cost_value' => ['nullable', 'numeric', 'min:0'], 'cost_currency' => ['nullable', 'string', 'size:3']]);
        $asset = $action->execute($user, $data);

        return to_route('party.assets.index')->with('success', __('Rental asset created.'));
    })->middleware('can:rental_assets.create')->name('party.assets.store');

    Route::post('party/assets/{asset}/reservations', function (Request $request, RentalAsset $asset, ReserveAssetAction $action) {
        /** @var User $user */ $user = $request->user();
        $asset = RentalAsset::query()->visibleTo($user)->whereKey($asset->id)->firstOrFail();
        $data = $request->validate(['starts_at' => ['required', 'date'], 'ends_at' => ['required', 'date', 'after:starts_at'], 'timezone' => ['required', 'timezone'], 'source_reference' => ['nullable', 'string', 'max:190'], 'idempotency_key' => ['required', 'uuid']]);
        $action->execute($user, $asset, $data);

        return back()->with('success', __('Asset reservation created.'));
    })->middleware('can:rental_assets.reserve')->name('party.assets.reserve');

    Route::post('party/assets/{asset}/checkout', function (Request $request, RentalAsset $asset, CheckoutAssetAction $action) {
        /** @var User $user */ $user = $request->user();
        $asset = RentalAsset::query()->visibleTo($user)->whereKey($asset->id)->firstOrFail();
        $data = $request->validate(['reservation_id' => ['required', 'integer'], 'source_reference' => ['required', 'string', 'max:190'], 'location_after' => ['nullable', 'string', 'max:190'], 'notes' => ['nullable', 'string', 'max:2000'], 'idempotency_key' => ['required', 'uuid']]);
        $reservation = AssetReservation::query()->whereKey($data['reservation_id'])->where('asset_id', $asset->id)->firstOrFail();
        $action->execute($user, $asset, $reservation, $data);

        return back()->with('success', __('Asset checked out.'));
    })->middleware('can:rental_assets.checkout')->name('party.assets.checkout');

    Route::post('party/assets/{asset}/return', function (Request $request, RentalAsset $asset, ReturnAssetAction $action) {
        /** @var User $user */ $user = $request->user();
        $asset = RentalAsset::query()->visibleTo($user)->whereKey($asset->id)->firstOrFail();
        $data = $request->validate(['checkout_id' => ['required', 'integer'], 'location_after' => ['nullable', 'string', 'max:190'], 'condition_after' => ['required', 'string', 'max:40'], 'notes' => ['nullable', 'string', 'max:2000'], 'idempotency_key' => ['required', 'uuid']]);
        $checkout = $asset->checkouts()->whereKey($data['checkout_id'])->firstOrFail();
        $action->execute($user, $asset, $checkout, $data);

        return back()->with('success', __('Asset returned for inspection.'));
    })->middleware('can:rental_assets.return')->name('party.assets.return');

    Route::post('party/assets/{asset}/inspect', function (Request $request, RentalAsset $asset, InspectAssetAction $action) {
        /** @var User $user */ $user = $request->user();
        $asset = RentalAsset::query()->visibleTo($user)->whereKey($asset->id)->firstOrFail();
        $data = $request->validate(['return_id' => ['required', 'integer'], 'resulting_status' => ['required', Rule::in(['available', 'damaged', 'under_maintenance', 'lost'])], 'assessment' => ['required', 'string', 'max:2000']]);
        $return = $asset->returns()->whereKey($data['return_id'])->firstOrFail();
        $action->execute($user, $asset, $return, $data['resulting_status'], $data['assessment']);

        return back()->with('success', __('Asset inspection recorded.'));
    })->middleware('can:rental_assets.inspect')->name('party.assets.inspect');

    Route::post('party/assets/{asset}/events', function (Request $request, RentalAsset $asset, CreateAssetEventAction $action) {
        /** @var User $user */ $user = $request->user();
        $asset = RentalAsset::query()->visibleTo($user)->whereKey($asset->id)->firstOrFail();
        $data = $request->validate(['event_type' => ['required', Rule::in(['damage', 'loss', 'maintenance', 'depreciation'])], 'assessment' => ['required', 'string', 'max:4000'], 'party_reference' => ['nullable', 'string', 'max:190'], 'resulting_status' => ['nullable', Rule::in(['damaged', 'lost', 'under_maintenance', 'available', 'retired'])], 'cost_value' => ['nullable', 'numeric', 'min:0'], 'idempotency_key' => ['required', 'uuid']]);
        $action->execute($user, $asset, $data);

        return back()->with('success', __('Asset event submitted for approval.'));
    })->middleware('can:rental_assets.create')->name('party.assets.events.store');

    Route::post('party/asset-events/{event}/approve', function (Request $request, AssetEvent $event, ApproveAssetEventAction $action) {
        /** @var User $user */ $user = $request->user();
        $event = AssetEvent::query()->whereKey($event->id)->where(function ($q) use ($user): void {
            if (! $user->is_super_admin) {
                $q->whereIn('branch_id', $user->branchScopes()->where('status', 'active')->select('branch_id'))->orWhereIn('store_id', $user->storeScopes()->where('status', 'active')->select('store_id'));
            }
        })->firstOrFail();
        $action->execute($user, $event);

        return back()->with('success', __('Asset event approved.'));
    })->middleware('can:rental_assets.approve')->name('party.asset-events.approve');

    Route::get('party/assets/{asset}/print', function (Request $request, RentalAsset $asset) {
        /** @var User $user */ $user = $request->user();
        abort_unless($user->can('rental_assets.print'), 403);
        $asset = RentalAsset::query()->visibleTo($user)->with(['branch', 'store', 'reservations', 'checkouts', 'returns', 'events'])->whereKey($asset->id)->firstOrFail();
        app(RecordAuditEvent::class)->execute('assets', 'asset_printed', $asset, branchId: $asset->branch_id, storeId: $asset->store_id, metadata: ['format' => 'a4']);

        return view('pages.party.asset-print', compact('asset'));
    })->middleware('can:rental_assets.print')->name('party.assets.print');
});
