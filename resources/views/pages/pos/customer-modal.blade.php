<dialog id="pos-new-customer"
    @if($errors->hasAny(['phone','first_name_ar','last_name_ar','governorate_id','city_id','email','invoice_delivery_method','customer'])) x-data x-init="$nextTick(() => $el.showModal())" @endif
    class="m-auto h-[min(82vh,46rem)] max-h-[82vh] w-[min(96vw,74rem)] max-w-none overflow-hidden border border-slate-300 bg-white p-0 text-start shadow-2xl backdrop:bg-slate-950/70"
    aria-labelledby="pos-customer-modal-title">
    <form method="POST" action="{{ route('pos.customer.create') }}" class="flex h-full min-h-0 flex-col"
        x-data="{ governorate: @js((string) old('governorate_id', '')), submitting: false }"
        x-on:submit="if (submitting) { $event.preventDefault() } else { submitting = true }">
        @csrf
        <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">
        <header class="relative flex h-10 shrink-0 items-center border-b border-slate-300 bg-slate-50 px-4">
            <h2 id="pos-customer-modal-title" class="text-sm font-black">{{ __('Add New Customer') }}</h2>
            <button type="button" onclick="this.closest('dialog').close()" class="absolute right-3 top-1.5 flex size-7 items-center justify-center rounded-full border-2 border-white bg-slate-950 text-white shadow" aria-label="{{ __('Close') }}"><flux:icon name="x-mark" class="size-4" /></button>
        </header>
        @error('customer')<p role="alert" class="mx-3 mt-2 bg-rose-50 p-2 text-xs font-bold text-rose-800">{{ $message }}</p>@enderror
        <div class="grid min-h-0 flex-1 overflow-y-auto bg-white md:grid-cols-2" data-pos-customer-fields>
            <section class="space-y-2 border-b border-slate-200 p-3 md:border-b-0 md:border-e" aria-label="{{ __('Customer details') }}">
                <h3 class="border-b border-slate-200 bg-slate-50 px-2 py-1 text-xs font-bold">{{ __('Customer details') }}</h3>
                <div class="grid grid-cols-2 gap-x-2 gap-y-2">
                    <label class="col-span-2 text-[11px] font-semibold">{{ __('First name (Arabic)') }} *<input name="first_name_ar" value="{{ old('first_name_ar') }}" required dir="rtl" class="mt-0.5 h-8 w-full border border-slate-300 px-2 text-xs"></label>
                    @error('first_name_ar')<p class="col-span-2 text-xs text-rose-700">{{ $message }}</p>@enderror
                    <label class="col-span-2 text-[11px] font-semibold">{{ __('Last name (Arabic)') }} *<input name="last_name_ar" value="{{ old('last_name_ar') }}" required dir="rtl" class="mt-0.5 h-8 w-full border border-slate-300 px-2 text-xs"></label>
                    @error('last_name_ar')<p class="col-span-2 text-xs text-rose-700">{{ $message }}</p>@enderror
                    <label class="text-[11px] font-semibold">{{ __('Phone') }} *<input name="phone" value="{{ old('phone') }}" required inputmode="tel" dir="ltr" class="mt-0.5 h-8 w-full border border-slate-300 px-2 text-xs"></label>
                    <label class="text-[11px] font-semibold">{{ __('Secondary phone') }}<input name="secondary_phone" value="{{ old('secondary_phone') }}" inputmode="tel" dir="ltr" class="mt-0.5 h-8 w-full border border-slate-300 px-2 text-xs"></label>
                    @error('phone')<p class="col-span-2 text-xs text-rose-700">{{ $message }}</p>@enderror
                    @error('secondary_phone')<p class="col-span-2 text-xs text-rose-700">{{ $message }}</p>@enderror
                    <label class="text-[11px] font-semibold">{{ __('First name (English, optional)') }}<input name="first_name_en" value="{{ old('first_name_en') }}" dir="ltr" class="mt-0.5 h-8 w-full border border-slate-300 px-2 text-xs"></label>
                    <label class="text-[11px] font-semibold">{{ __('Last name (English, optional)') }}<input name="last_name_en" value="{{ old('last_name_en') }}" dir="ltr" class="mt-0.5 h-8 w-full border border-slate-300 px-2 text-xs"></label>
                    <label class="col-span-2 text-[11px] font-semibold">{{ __('Governorate') }} *<select name="governorate_id" x-model="governorate" x-on:change="$el.form.elements.city_id.value=''" required class="mt-0.5 h-8 w-full border border-slate-300 px-2 text-xs"><option value="">{{ __('Select governorate') }}</option>@foreach($governorates as $governorate)<option value="{{ $governorate->id }}" @selected(old('governorate_id')==$governorate->id)>{{ str_starts_with(app()->getLocale(), 'ar') ? $governorate->name_ar : $governorate->name_en }}</option>@endforeach</select></label>
                    @error('governorate_id')<p class="col-span-2 text-xs text-rose-700">{{ $message }}</p>@enderror
                    <label class="col-span-2 text-[11px] font-semibold">{{ __('City / locality') }} ({{ __('Optional') }})<select name="city_id" class="mt-0.5 h-8 w-full border border-slate-300 px-2 text-xs"><option value="">{{ __('Select city / locality') }}</option>@foreach($cities as $city)<option value="{{ $city->id }}" x-bind:disabled="governorate !== @js((string) $city->governorate_id)" x-bind:hidden="governorate !== @js((string) $city->governorate_id)" @selected(old('city_id')==$city->id)>{{ str_starts_with(app()->getLocale(), 'ar') ? $city->name_ar : $city->name_en }}</option>@endforeach</select></label>
                    @error('city_id')<p class="col-span-2 text-xs text-rose-700">{{ $message }}</p>@enderror
                    <label class="col-span-2 text-[11px] font-semibold">{{ __('Arabic address') }}<input name="address_ar" value="{{ old('address_ar') }}" dir="rtl" class="mt-0.5 h-8 w-full border border-slate-300 px-2 text-xs"></label>
                </div>
            </section>
            <section class="space-y-2 p-3" aria-label="{{ __('Account details') }}">
                <h3 class="border-b border-slate-200 bg-slate-50 px-2 py-1 text-xs font-bold">{{ __('Account details') }}</h3>
                <div class="grid grid-cols-2 gap-2">
                    <label class="text-[11px] font-semibold">{{ __('Customer code') }}<input readonly value="{{ __('Automatic after saving') }}" class="mt-0.5 h-8 w-full border border-slate-300 bg-slate-50 px-2 text-xs text-slate-500"></label>
                    <label class="text-[11px] font-semibold">{{ __('Invoice delivery') }}<select name="invoice_delivery_method" class="mt-0.5 h-8 w-full border border-slate-300 px-2 text-xs"><option value="print" @selected(old('invoice_delivery_method', 'print')==='print')>{{ __('Print') }}</option><option value="email" @selected(old('invoice_delivery_method')==='email')>{{ __('Send by email') }}</option></select></label>
                    @error('invoice_delivery_method')<p class="col-span-2 text-xs text-rose-700">{{ $message }}</p>@enderror
                    <label class="col-span-2 text-[11px] font-semibold">{{ __('Email') }}<input name="email" type="email" value="{{ old('email') }}" dir="ltr" class="mt-0.5 h-8 w-full border border-slate-300 px-2 text-xs"></label>
                    @error('email')<p class="col-span-2 text-xs text-rose-700">{{ $message }}</p>@enderror
                    <label class="col-span-2 text-[11px] font-semibold">{{ __('Customer group') }}<select name="customer_group_id" class="mt-0.5 h-8 w-full border border-slate-300 px-2 text-xs"><option value="">{{ __('No group assigned') }}</option>@foreach($customerGroups as $group)<option value="{{ $group->id }}" @selected(old('customer_group_id')==$group->id)>{{ str_starts_with(app()->getLocale(), 'ar') ? $group->name_ar : $group->name_en }}</option>@endforeach</select></label>
                    @error('customer_group_id')<p class="col-span-2 text-xs text-rose-700">{{ $message }}</p>@enderror
                    @unless(in_array('retail', $customerPurposes, true))<label class="col-span-2 text-[11px] font-semibold">{{ __('Consent purpose') }} *<select name="consent_purpose" required class="mt-0.5 h-8 w-full border border-slate-300 px-2 text-xs"><option value="">{{ __('Choose purpose') }}</option>@foreach($customerPurposes as $purpose)<option value="{{ $purpose }}" @selected(old('consent_purpose')===$purpose)>{{ __($purpose) }}</option>@endforeach</select></label>@endunless
                    <label class="col-span-2 text-[11px] font-semibold">{{ __('English address') }}<input name="address_en" value="{{ old('address_en') }}" dir="ltr" class="mt-0.5 h-8 w-full border border-slate-300 px-2 text-xs"></label>
                    <p class="col-span-2 border-t border-slate-200 pt-2 text-[11px] text-slate-500">{{ __('Email delivery without an address falls back to print.') }}</p>
                </div>
            </section>
        </div>
        <footer class="grid h-11 shrink-0 grid-cols-2 border-t border-slate-300 text-sm font-black"><button type="button" onclick="this.closest('dialog').close()" class="bg-slate-100">{{ __('Cancel') }}</button><button type="submit" x-bind:disabled="submitting" class="bg-green-600 text-white disabled:opacity-50">{{ __('Save') }}</button></footer>
    </form>
</dialog>
