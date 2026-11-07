<x-layouts::app :title="__('Gift Cards')">
    @php($issuingStores = $stores->where('type', 'selling')->values())
    <x-app.page :title="__('Gift Cards')" :description="__('Issue, redeem, and close cards through an append-only balance ledger.')" max-width="7xl">
        @if(session('success'))
            <flux:callout variant="success">{{ session('success') }}</flux:callout>
        @endif
        @if(isset($errors) && $errors->any())
            <flux:callout variant="danger">{{ $errors->first() }}</flux:callout>
        @endif
        <x-context-help :title="__('Gift Cards')" :label="__('Open contextual help')"><p>{{ __('Every issue, redemption, void, and expiry writes an immutable ledger entry. Balance is checked under a database row lock.') }}</p></x-context-help>

        @can('gift_cards.issue')
            <flux:card class="mt-6">
                <flux:heading size="lg">{{ str_starts_with(app()->getLocale(), 'ar') ? 'إنشاء بطاقة هدايا للبيع' : 'Create Gift Card for sale' }}</flux:heading>
                <form method="POST" action="{{ route('gift.cards.store') }}" class="mt-4 grid gap-4 sm:grid-cols-3">
                    @csrf
                    <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                    <flux:input name="amount" type="number" min="0.01" step="0.01" label="{{ __('Value') }}" required />
                    <flux:select name="store_id" label="{{ str_starts_with(app()->getLocale(), 'ar') ? 'مخزن الإصدار' : 'Issuing warehouse' }}" required>
                        @if($stores->count() !== 1)<flux:select.option value="">{{ str_starts_with(app()->getLocale(), 'ar') ? 'اختر مخزن الإصدار' : 'Choose issuing warehouse' }}</flux:select.option>@endif
                        @foreach($stores as $store)<flux:select.option value="{{ $store->id }}" :selected="(string) old('store_id', $stores->count() === 1 ? $store->id : '') === (string) $store->id">{{ \App\Support\HumanName::name($store->branch) }} — {{ \App\Support\HumanName::name($store) }}</flux:select.option>@endforeach
                    </flux:select>
                    <flux:input name="valid_until" type="date" label="{{ __('Expires on (optional)') }}" />
                    <div class="sm:col-span-3"><flux:button type="submit" variant="primary">{{ str_starts_with(app()->getLocale(), 'ar') ? 'إنشاء البطاقة غير المباعة' : 'Create unsold card' }}</flux:button></div>
                </form>
            </flux:card>
        @endcan

        <flux:card class="mt-6 overflow-hidden p-0">
            <div class="overflow-x-auto">
                <table class="data-table min-w-[840px] w-full text-sm">
                    <thead><tr><th>{{ __('Identifier') }}</th><th>{{ __('Status') }}</th><th>{{ __('Issued') }}</th><th>{{ __('Balance') }}</th><th>{{ __('Store') }}</th><th>{{ __('Actions') }}</th></tr></thead>
                    <tbody>
                        @forelse($cards as $card)
                            <tr class="border-t border-zinc-100 align-top dark:border-zinc-800">
                                <td class="font-mono font-semibold">{{ $card->identifier }}</td><td><x-status.badge :status="$card->status" :label="$card->status === 'unsold' ? (str_starts_with(app()->getLocale(), 'ar') ? 'غير مباعة' : 'Unsold') : null" /></td>
                                <td>{{ number_format((float) $card->issued_value, 2) }} {{ $card->currency_code }}</td><td class="font-semibold">{{ number_format((float) $card->balance, 2) }} {{ $card->currency_code }}</td><td>{{ $card->store?->name_en ?: $card->store_id }}</td>
                                <td class="flex flex-wrap gap-3">
                                    <x-actions.button semantic="history" :label="__('History')" href="{{ route('gift.cards.show', $card) }}">{{ __('History') }}</x-actions.button>
                                    @can('gift_cards.print')<x-actions.button semantic="print" :label="__('Print')" href="{{ route('gift.cards.print', $card) }}">{{ __('Print') }}</x-actions.button>@endcan
                                    @can('gift_cards.void')
                                        @if(!in_array($card->status, ['voided', 'expired', 'fully_used'], true))
                                            <details><summary class="cursor-pointer text-sm text-red-700 underline">{{ __('Void') }}</summary><form method="POST" action="{{ route('gift.cards.void', $card) }}" class="mt-2 flex gap-2">@csrf<input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}"><input class="w-40 rounded border-zinc-300" name="reason" placeholder="{{ __('Reason') }}" required><button class="text-sm text-red-700 underline" type="submit">{{ __('Confirm') }}</button></form></details>
                                        @endif
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><x-state.empty :title="__('No Gift Cards yet')" :description="str_starts_with(app()->getLocale(), 'ar') ? 'أنشئ بطاقة ثم بعها من نقطة البيع لتفعيل رصيدها.' : 'Create a card, then sell it at POS to activate its balance.'" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-zinc-200 p-4 dark:border-zinc-700">{{ $cards->links() }}</div>
        </flux:card>
    </x-app.page>
</x-layouts::app>
