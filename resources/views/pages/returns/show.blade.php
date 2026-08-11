<x-layouts::app :title="__('Return :number', ['number' => $return->return_number ?: $return->id])">
    <x-app.page :title="__('Return :number', ['number' => $return->return_number ?: $return->id])" :description="__('Source-linked document. The original sale remains immutable.')" max-width="5xl">
        @if (session('success'))
            <flux:callout variant="success">{{ session('success') }}</flux:callout>
        @endif

        @if ($errors->any())
            <flux:callout variant="danger">{{ $errors->first() }}</flux:callout>
        @endif

        <div class="grid gap-4 sm:grid-cols-3">
            <flux:card>
                <p class="text-sm text-zinc-500">{{ __('Status') }}</p>
                <p class="mt-1 font-semibold">{{ ucfirst($return->status) }}</p>
            </flux:card>
            <flux:card>
                <p class="text-sm text-zinc-500">{{ __('Source') }}</p>
                <p class="mt-1 font-semibold">{{ $return->sourceSale?->document_number ?: $return->sourceGiftReceipt?->reference }}</p>
            </flux:card>
            <flux:card>
                <p class="text-sm text-zinc-500">{{ __('Settlement') }}</p>
                <p class="mt-1 font-semibold">{{ number_format((float) $return->settlement_value, 2) }} {{ $return->currency_code }}</p>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ __(ucwords(str_replace('_', ' ', $return->settlement_type))) }}</p>
                @foreach ($return->settlements as $settlement)
                    @if ($settlement->giftCard)
                        <a class="mt-2 inline-flex text-sm font-medium text-teal-700 underline" href="{{ route('gift.cards.show', $settlement->giftCard) }}">
                            {{ __('Gift Card') }} {{ $settlement->giftCard->identifier }}
                        </a>
                    @endif
                @endforeach
            </flux:card>
        </div>

        <flux:card class="mt-6 overflow-hidden p-0">
            <div class="overflow-x-auto">
                <table class="data-table min-w-[900px] w-full text-sm">
                    <thead><tr><th>{{ __('Item') }}</th><th>{{ __('Quantity') }}</th><th>{{ __('Condition') }}</th><th>{{ __('Disposition') }}</th><th>{{ __('Inspection notes') }}</th><th>{{ __('Eligible value') }}</th></tr></thead>
                    <tbody>
                        @foreach ($return->lines as $line)
                            <tr class="border-t border-zinc-100 dark:border-zinc-800">
                                <td>{{ app()->getLocale() === 'ar' ? $line->product?->name_ar : $line->product?->name_en }}</td>
                                <td>{{ $line->quantity }}</td>
                                <td>{{ ucfirst(str_replace('_', ' ', $line->condition)) }}</td>
                                <td>{{ ucfirst(str_replace('_', ' ', $line->disposition)) }}</td>
                                <td class="max-w-xs whitespace-pre-wrap">{{ $line->inspection_notes ?: __('No notes recorded') }}</td>
                                <td>{{ number_format((float) $line->eligible_value, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </flux:card>

        <div class="mt-6 flex flex-wrap gap-3">
            @if ($return->status === 'draft')
                <form method="POST" action="{{ route('returns.submit', $return) }}">
                    @csrf
                    <flux:button type="submit" variant="primary">{{ __('Submit for approval') }}</flux:button>
                </form>
            @endif

            @if ($return->status === 'submitted')
                @can('returns.approve')
                    <form method="POST" action="{{ route('returns.approve', $return) }}">
                        @csrf
                        <flux:button type="submit" variant="primary">{{ __('Approve') }}</flux:button>
                    </form>
                @endcan
            @endif

            @if ($return->status === 'approved')
                <form method="POST" action="{{ route('returns.complete', $return) }}" class="grid w-full gap-3 rounded-xl border border-zinc-200 p-4 sm:grid-cols-3">
                    @csrf
                    <input type="hidden" name="idempotency_key" value="return-complete:{{ $return->id }}">

                    @if (in_array($return->settlement_type, ['cash_refund', 'exchange'], true))
                        <flux:select name="payment_method_id" label="{{ __('Settlement payment method') }}">
                            <option value="">{{ __('Select method if required') }}</option>
                            @foreach ($paymentMethods as $method)
                                <option value="{{ $method->id }}">{{ app()->getLocale() === 'ar' ? $method->name_ar : $method->name_en }} ({{ $method->code }})</option>
                            @endforeach
                        </flux:select>
                    @endif

                    @if ($return->settlement_type === 'original_tender')
                        <flux:select name="original_payment_id" label="{{ __('Original payment to reverse') }}" required>
                            <option value="">{{ __('Select original payment') }}</option>
                            @foreach (($return->sourceSale?->payments ?? collect()) as $payment)
                                <option value="{{ $payment->id }}">{{ $payment->paymentMethod?->name_en ?: $payment->method_code }} · {{ number_format((float) $payment->amount, 2) }}</option>
                            @endforeach
                        </flux:select>
                    @endif

                    <div class="flex items-end">
                        <flux:button type="submit" variant="primary">{{ __('Complete settlement and stock movement') }}</flux:button>
                    </div>
                </form>
            @endif

            <a class="inline-flex items-center rounded-lg border px-3 py-2 text-sm" href="{{ route('returns.print', $return) }}">{{ __('Print') }}</a>
        </div>
    </x-app.page>
</x-layouts::app>
