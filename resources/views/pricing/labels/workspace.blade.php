<flux:card
    class="space-y-5"
    data-label-workspace
    x-data="labelWorkspace(@js($initial), @js(route('pricing.labels.search')))"
    x-init="init()"
>
    <div class="flex items-center gap-2">
        <flux:heading size="lg">{{ __('Find and select products') }}</flux:heading>
        <x-context-help :title="__('Find and select products')" :label="__('Open contextual help')"><p>{{ __('Nothing is loaded until you search. Selected products and print settings remain while you continue searching.') }}</p></x-context-help>
    </div>

    @include('pricing.labels.search')
    @include('pricing.labels.selected-products')
</flux:card>
