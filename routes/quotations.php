<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Customer\Actions\CreateCustomerAction;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Support\PhoneNormalizer;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\Governorate;
use App\Modules\Platform\Models\Store;
use App\Modules\Quotation\Actions\ChangeQuotationStatusAction;
use App\Modules\Quotation\Actions\CreateQuotationAction;
use App\Modules\Quotation\Actions\ShareQuotationAction;
use App\Modules\Quotation\Actions\UpdateQuotationAction;
use App\Modules\Quotation\Models\Quotation;
use App\Modules\Quotation\Models\QuotationShareLink;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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

Route::get('quotations/{quotation}/pdf', function (Request $request, Quotation $quotation) {
    /** @var User $user */ $user = $request->user();
    Gate::forUser($user)->authorize('quotations.print');
    $quotation = Quotation::query()->visibleTo($user)->where('activity_type', 'retail')->with(['customer', 'branch', 'store.company', 'lines'])->findOrFail($quotation->id);
    app(RecordAuditEvent::class)->execute('quotations', 'quotation_pdf_exported', $quotation, branchId: $quotation->branch_id, storeId: $quotation->store_id, metadata: ['non_posting' => true]);
    $options = new Options;
    $options->set('isRemoteEnabled', false);
    $pdf = new Dompdf($options);
    $pdf->loadHtml(view('pages.quotations.print', compact('quotation'))->render(), 'UTF-8');
    $pdf->setPaper('a4');
    $pdf->render();

    return response($pdf->output(), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="quotation-'.$quotation->quotation_number.'.pdf"']);
})->middleware(['auth', 'verified', 'can:quotations.print'])->name('quotations.pdf');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('quotations/{quotation}', function (Request $request, Quotation $quotation) {
        /** @var User $user */ $user = $request->user();
        $quotation = Quotation::query()->visibleTo($user)->where('activity_type', 'retail')->with(['customer', 'branch', 'store', 'lines'])->findOrFail($quotation->id);

        return view('pages.quotations.show', compact('quotation'));
    })->middleware('can:quotations.view')->whereNumber('quotation')->name('quotations.show');
    Route::get('quotations', function (Request $request) {
        /** @var User $user */ $user = $request->user();
        abort_unless($user->can('quotations.view'), 403);
        $quotations = Quotation::query()->visibleTo($user)->with(['customer', 'branch', 'store', 'lines', 'shareLinks'])->where('activity_type', 'retail')->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))->latest('id')->paginate(20)->withQueryString();
        $customers = Customer::query()->visibleTo($user)->where('status', 'active')->orderBy('name_en')->limit(300)->get();
        $stores = Store::query()->visibleTo($user)->where('status', 'active')->where('type', 'selling')->with('branch')->orderBy('name_en')->get();
        $governorates = Governorate::query()->active()->orderBy('sort_order')->get(['id', 'name_ar', 'name_en']);
        $products = Product::query()->sellable()->orderBy('name_en')->limit(500)->get(['id', 'item_code', 'name_ar', 'name_en']);

        return view('pages.quotations.index', compact('quotations', 'customers', 'stores', 'products', 'governorates'));
    })->middleware('can:quotations.view')->name('quotations.index');
    Route::get('quotations/create', function (Request $request) {
        /** @var User $user */ $user = $request->user();
        $customers = Customer::query()->visibleTo($user)->where('status', 'active')->orderBy('name_en')->limit(300)->get();
        $stores = Store::query()->visibleTo($user)->where('status', 'active')->where('type', 'selling')->with('branch')->orderBy('name_en')->get();
        $governorates = Governorate::query()->active()->orderBy('sort_order')->get(['id', 'name_ar', 'name_en']);
        $products = Product::query()->sellable()->orderBy('name_en')->limit(500)->get(['id', 'item_code', 'name_ar', 'name_en']);

        return view('pages.quotations.index', ['creating' => true, 'customers' => $customers, 'stores' => $stores, 'governorates' => $governorates, 'products' => $products]);
    })->middleware('can:quotations.create')->name('quotations.create');
    Route::get('quotations/customers/search', function (Request $request) {
        /** @var User $user */ $user = $request->user();
        $store = Store::query()->visibleTo($user)->where('status', 'active')->where('type', 'selling')->findOrFail($request->integer('store_id'));
        $term = mb_substr(trim((string) $request->query('q')), 0, 100);
        if ($term === '') {
            return response()->json(['data' => []]);
        }
        $escaped = '%'.addcslashes($term, '%_\\').'%';
        $digits = preg_replace('/[^0-9]+/', '', $term) ?: '';
        $customers = Customer::query()->visibleFrom($user, (int) $store->branch_id, (int) $store->id)
            ->where('status', 'active')
            ->whereHas('scopes', fn ($scope) => $scope->where('branch_id', $store->branch_id)->orWhere('store_id', $store->id))
            ->where(function ($query) use ($escaped, $digits): void {
                $query->where('name_ar', 'like', $escaped)->orWhere('name_en', 'like', $escaped);
                if ($digits !== '') {
                    $query->orWhere('phone_normalized', 'like', '%'.$digits.'%');
                }
            })->orderBy('name_en')->limit(15)->get(['id', 'name_ar', 'name_en', 'phone_display']);

        return response()->json(['data' => $customers->map(fn (Customer $customer): array => [
            'id' => $customer->id, 'name' => str_starts_with(app()->getLocale(), 'ar') ? $customer->name_ar : $customer->name_en,
            'phone' => $customer->phone_display,
        ])->values()]);
    })->middleware(['can:quotations.view', 'can:customers.view'])->name('quotations.customers.search');
    Route::post('quotations/customers', function (Request $request, CreateCustomerAction $action) {
        /** @var User $user */ $user = $request->user();
        Gate::forUser($user)->authorize('customers.create');
        $data = $request->validate([
            'store_id' => ['required', 'integer'], 'idempotency_key' => ['required', 'uuid'],
            'phone' => ['required', 'string', 'max:64', PhoneNormalizer::validationRule()],
            'first_name_ar' => ['required', 'string', 'max:190'], 'last_name_ar' => ['required', 'string', 'max:190'],
            'governorate_id' => ['required', 'integer'],
        ]);
        $store = Store::query()->visibleTo($user)->where('status', 'active')->where('type', 'selling')->findOrFail($data['store_id']);
        try {
            $customer = $action->execute($user, $store, $data, cityOptional: true, allowWithoutConsent: true);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['phone' => $exception->getMessage()]);
        }

        return response()->json(['id' => $customer->id, 'name' => $customer->name_ar, 'phone' => $customer->phone_display]);
    })->middleware(['can:quotations.create', 'can:customers.create'])->name('quotations.customers.store');
    Route::post('quotations', function (Request $request, CreateQuotationAction $action) {
        /** @var User $user */ $user = $request->user();
        $store = Store::query()->visibleTo($user)->whereKey((int) $request->input('store_id'))->where('status', 'active')->where('type', 'selling')->firstOrFail();
        $request->merge(['branch_id' => $store->branch_id]);
        $data = $request->validate(['activity_type' => ['required', Rule::in(['retail'])], 'customer_id' => ['required', 'integer'], 'branch_id' => ['required', 'integer'], 'store_id' => ['required', 'integer'], 'quotation_date' => ['nullable', 'date'], 'valid_until' => ['required', 'date', 'after_or_equal:today'], 'discount_type' => ['nullable', Rule::in(['fixed', 'percent'])], 'discount_value' => ['nullable', 'numeric', 'min:0'], 'tax_rate' => ['nullable', 'numeric', 'between:0,100'], 'terms' => ['nullable', 'string', 'max:4000'], 'notes' => ['nullable', 'string', 'max:4000'], 'idempotency_key' => ['required', 'uuid'], 'lines' => ['required', 'array', 'min:1'], 'lines.*.line_type' => ['required', Rule::in(['product', 'manual'])], 'lines.*.product_id' => ['nullable', 'integer'], 'lines.*.source_reference' => ['nullable', 'string', 'max:190'], 'lines.*.description_ar' => ['required', 'string', 'max:190'], 'lines.*.description_en' => ['required', 'string', 'max:190'], 'lines.*.quantity' => ['required', 'numeric', 'gt:0'], 'lines.*.unit_price' => ['required', 'numeric', 'min:0']]);
        $action->execute($user, $data);

        return to_route('quotations.index')->with('success', __('Quotation saved.'));
    })->middleware('can:quotations.create')->name('quotations.store');
    Route::match(['post', 'put'], 'quotations/{quotation}', function (Request $request, Quotation $quotation, UpdateQuotationAction $action) {
        /** @var User $user */ $user = $request->user();
        $quotation = Quotation::query()->visibleTo($user)->whereKey($quotation->id)->firstOrFail();
        $data = $request->validate(['customer_id' => ['required', 'integer'], 'quotation_date' => ['nullable', 'date'], 'valid_until' => ['required', 'date', 'after_or_equal:today'], 'discount_type' => ['nullable', Rule::in(['fixed', 'percent'])], 'discount_value' => ['nullable', 'numeric', 'min:0'], 'tax_rate' => ['nullable', 'numeric', 'between:0,100'], 'terms' => ['nullable', 'string', 'max:4000'], 'notes' => ['nullable', 'string', 'max:4000'], 'lines' => ['sometimes', 'array', 'min:1'], 'lines.*.id' => ['nullable', 'integer', 'distinct'], 'lines.*.line_type' => ['required', Rule::in(['product', 'manual'])], 'lines.*.product_id' => ['nullable', 'integer'], 'lines.*.source_reference' => ['nullable', 'string', 'max:190'], 'lines.*.description_ar' => ['required', 'string', 'max:190'], 'lines.*.description_en' => ['required', 'string', 'max:190'], 'lines.*.quantity' => ['required', 'numeric', 'gt:0'], 'lines.*.unit_price' => ['required', 'numeric', 'min:0'], 'removed_line_ids' => ['sometimes', 'array'], 'removed_line_ids.*' => ['required', 'integer', 'distinct']]);
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
