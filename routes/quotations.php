<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Customer\Models\Customer;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\Store;
use App\Modules\Quotation\Actions\ChangeQuotationStatusAction;
use App\Modules\Quotation\Actions\CreateQuotationAction;
use App\Modules\Quotation\Actions\ShareQuotationAction;
use App\Modules\Quotation\Actions\UpdateQuotationAction;
use App\Modules\Quotation\Models\Quotation;
use App\Modules\Quotation\Models\QuotationShareLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;

Route::get('quotations/{quotation}/print', function (Request $request, Quotation $quotation) {
    /** @var User|null $user */ $user = $request->user();
    if ($request->filled('share')) {
        abort_unless($request->hasValidSignature(), 403);
        $link = QuotationShareLink::query()->whereKey($request->integer('share'))->where('quotation_id', $quotation->id)->where('token_hash', hash('sha256', (string) $request->string('token')))->firstOrFail();
        abort_if($link->revoked_at !== null || $link->expires_at->isPast(), 410);
    } else {
        abort_unless($user instanceof User, 403);
        Gate::forUser($user)->authorize('quotations.print');
        $quotation = Quotation::query()->visibleTo($user)->whereKey($quotation->id)->firstOrFail();
    }
    $quotation->load(['customer', 'branch', 'store', 'lines']);
    if ($user instanceof User) {
        app(RecordAuditEvent::class)->execute('quotations', 'quotation_printed', $quotation, branchId: $quotation->branch_id, storeId: $quotation->store_id, metadata: ['format' => 'a4', 'non_posting' => true]);
    }

    return view('pages.quotations.print', compact('quotation'));
})->name('quotations.print');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('quotations', function (Request $request) {
        /** @var User $user */ $user = $request->user();
        abort_unless($user->can('quotations.view'), 403);
        $quotations = Quotation::query()->visibleTo($user)->with(['customer', 'store', 'lines', 'shareLinks'])->when($request->filled('activity_type'), fn ($q) => $q->where('activity_type', $request->string('activity_type')))->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))->latest('id')->paginate(20)->withQueryString();
        $customers = Customer::query()->visibleTo($user)->where('status', 'active')->orderBy('name_en')->limit(300)->get();
        $stores = Store::query()->visibleTo($user)->where('status', 'active')->with('branch')->orderBy('name_en')->get();
        $products = Product::query()->sellable()->orderBy('name_en')->limit(500)->get(['id', 'item_code', 'name_ar', 'name_en']);

        return view('pages.quotations.index', compact('quotations', 'customers', 'stores', 'products'));
    })->middleware('can:quotations.view')->name('quotations.index');
    Route::post('quotations', function (Request $request, CreateQuotationAction $action) {
        /** @var User $user */ $user = $request->user();
        $store = Store::query()->visibleTo($user)->whereKey((int) $request->input('store_id'))->where('status', 'active')->firstOrFail();
        $request->merge(['branch_id' => $store->branch_id]);
        $data = $request->validate(['activity_type' => ['required', Rule::in(['retail', 'party'])], 'customer_id' => ['nullable', 'integer'], 'branch_id' => ['required', 'integer'], 'store_id' => ['required', 'integer'], 'valid_until' => ['required', 'date', 'after_or_equal:today'], 'terms' => ['nullable', 'string', 'max:4000'], 'notes' => ['nullable', 'string', 'max:4000'], 'idempotency_key' => ['required', 'uuid'], 'lines' => ['required', 'array', 'min:1'], 'lines.*.line_type' => ['required', 'string'], 'lines.*.product_id' => ['nullable', 'integer'], 'lines.*.description_ar' => ['required', 'string', 'max:190'], 'lines.*.description_en' => ['required', 'string', 'max:190'], 'lines.*.quantity' => ['required', 'numeric', 'gt:0'], 'lines.*.unit_price' => ['required', 'numeric', 'min:0']]);
        $action->execute($user, $data);

        return to_route('quotations.index')->with('success', __('Quotation created as a non-posting draft.'));
    })->middleware('can:quotations.create')->name('quotations.store');
    Route::match(['post', 'put'], 'quotations/{quotation}', function (Request $request, Quotation $quotation, UpdateQuotationAction $action) {
        /** @var User $user */ $user = $request->user();
        $quotation = Quotation::query()->visibleTo($user)->whereKey($quotation->id)->firstOrFail();
        $data = $request->validate(['customer_id' => ['nullable', 'integer'], 'valid_until' => ['required', 'date', 'after_or_equal:today'], 'terms' => ['nullable', 'string', 'max:4000'], 'notes' => ['nullable', 'string', 'max:4000'], 'lines' => ['sometimes', 'array', 'min:1'], 'lines.*.id' => ['nullable', 'integer', 'distinct'], 'lines.*.line_type' => ['required', 'string'], 'lines.*.product_id' => ['nullable', 'integer'], 'lines.*.description_ar' => ['required', 'string', 'max:190'], 'lines.*.description_en' => ['required', 'string', 'max:190'], 'lines.*.quantity' => ['required', 'numeric', 'gt:0'], 'lines.*.unit_price' => ['required', 'numeric', 'min:0'], 'removed_line_ids' => ['sometimes', 'array'], 'removed_line_ids.*' => ['required', 'integer', 'distinct']]);
        $action->execute($user, $quotation, $data);

        return back()->with('success', __('Quotation updated without posting any effect.'));
    })->middleware('can:quotations.view')->name('quotations.update');
    Route::post('quotations/{quotation}/status', function (Request $request, Quotation $quotation, ChangeQuotationStatusAction $action) {
        /** @var User $user */ $user = $request->user();
        $quotation = Quotation::query()->visibleTo($user)->whereKey($quotation->id)->firstOrFail();
        $data = $request->validate(['status' => ['required', 'string'], 'reason' => ['nullable', 'string', 'max:2000']]);
        $action->execute($user, $quotation, $data['status'], $data['reason'] ?? null);

        return back()->with('success', __('Quotation status updated.'));
    })->middleware('can:quotations.view')->name('quotations.status');
    Route::post('quotations/{quotation}/share', function (Request $request, Quotation $quotation, ShareQuotationAction $action) {
        /** @var User $user */ $user = $request->user();
        $quotation = Quotation::query()->visibleTo($user)->whereKey($quotation->id)->firstOrFail();

        return back()->with('share_url', $action->execute($user, $quotation));
    })->middleware('can:quotations.share')->name('quotations.share');
    Route::post('quotations/{quotation}/share/{link}/revoke', function (Request $request, Quotation $quotation, QuotationShareLink $link) {
        /** @var User $user */ $user = $request->user();
        Gate::forUser($user)->authorize('quotations.share');
        abort_unless($link->quotation_id === $quotation->id, 404);
        $link->update(['revoked_at' => now()]);
        app(RecordAuditEvent::class)->execute('quotations', 'quotation_share_revoked', $quotation, branchId: $quotation->branch_id, storeId: $quotation->store_id, metadata: ['share_link_id' => $link->id, 'non_posting' => true]);

        return back()->with('success', __('Quotation share link revoked.'));
    })->middleware('can:quotations.share')->name('quotations.share.revoke');
});
