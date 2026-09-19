<x-layouts.app :title="__('Catalog lookup masters')">
    <div class="mx-auto max-w-6xl space-y-6 p-6" dir="{{ str_starts_with(app()->getLocale(), 'ar') ? 'rtl' : 'ltr' }}">
        <div class="flex items-center justify-between gap-4">
            <div class="flex items-center gap-2"><flux:heading size="xl">{{ __('Catalog lookup masters') }}</flux:heading><x-context-help :title="__('Catalog lookup masters')" :label="__('Open contextual help')"><p>{{ __('Maintain bilingual age labels, characters, colours, and genders used by product cards.') }}</p></x-context-help></div>
            <div class="flex gap-2"><flux:button href="{{ route('catalog.reference-import') }}" variant="subtle">{{ __('Excel import') }}</flux:button><flux:button href="{{ route('catalog.products.create') }}" variant="primary">{{ __('Create product') }}</flux:button></div>
        </div>
        <flux:card class="p-6"><div class="flex items-center gap-2"><flux:text>{{ __('No lookup values are available yet.') }}</flux:text><x-context-help :title="__('No lookup values are available yet.')" :label="__('Open contextual help')"><p>{{ __('Create them through the approved catalog setup workflow.') }}</p></x-context-help></div></flux:card>
    </div>
</x-layouts.app>
