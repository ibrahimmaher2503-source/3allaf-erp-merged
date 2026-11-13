<div @if ($available && $auto) wire:poll.5s="receive" @endif data-pos-scale-receiver>
    @if ($available)
        <section class="border-b border-border bg-surface px-3 py-2 text-text-primary" aria-labelledby="pos-scale-heading">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 id="pos-scale-heading" class="text-xs font-extrabold">{{ __('Scale') }}</h2>
                <label class="flex items-center gap-1 text-[11px] font-semibold text-text-muted">
                    <input type="checkbox" wire:model.live="auto" class="size-3.5">
                    {{ __('Receive automatically every 5 seconds') }}
                </label>
            </div>
            <button type="button" wire:click="receive" wire:loading.attr="disabled" wire:target="receive"
                class="mt-2 flex h-11 w-full items-center justify-center gap-2 rounded bg-primary px-3 text-sm font-black text-white disabled:opacity-60">
                <flux:icon name="scale" class="size-5" />
                <span wire:loading.remove wire:target="receive">{{ __('Receive weight from scale') }}</span>
                <span wire:loading wire:target="receive">{{ __('Reading the scale…') }}</span>
            </button>
            @if ($message !== '')
                <p role="{{ $messageType === 'error' ? 'alert' : 'status' }}" @class([
                    'mt-2 rounded px-2 py-1.5 text-xs font-semibold',
                    'bg-emerald-50 text-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-100' => $messageType === 'success',
                    'bg-rose-50 text-rose-800 dark:bg-rose-950/50 dark:text-rose-100' => $messageType === 'error',
                    'bg-zinc-100 text-zinc-800 dark:bg-zinc-800 dark:text-zinc-100' => $messageType === 'info',
                ])>{{ $message }}</p>
            @endif
            @if ($notes !== [])
                <ul class="mt-2 space-y-1.5" data-scale-notes>
                    @foreach ($notes as $note)
                        <li @class([
                            'rounded border px-2 py-1.5 text-xs font-semibold leading-5',
                            'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-100' => $note['type'] === 'success',
                            'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-800 dark:bg-rose-950/50 dark:text-rose-100' => $note['type'] === 'error',
                        ])>{{ $note['text'] }}</li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endif
</div>
