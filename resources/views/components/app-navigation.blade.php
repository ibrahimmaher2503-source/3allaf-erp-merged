@props(['groups' => null])
@php($groups ??= app(\App\Modules\Platform\Support\ApplicationNavigation::class)->for(auth()->user(), app()->getLocale()))

<nav {{ $attributes->class('app-navigation') }} aria-label="{{ app()->getLocale() === 'ar' ? 'التنقل الرئيسي' : 'Primary navigation' }}">
    @foreach ($groups as $group)
        @php($activeItem = collect($group['items'])->firstWhere('active', true))
        <a class="app-navigation__rail-link {{ $group['active'] ? 'is-active' : '' }}" href="{{ $activeItem['url'] ?? $group['items'][0]['url'] }}" title="{{ $group['label'] }}" aria-label="{{ $group['label'] }}" @if($group['active']) aria-current="page" @endif wire:navigate><flux:icon :name="$group['icon']" class="size-5" /></a>
        <details class="app-navigation__group {{ $group['active'] ? 'is-active' : '' }}" data-navigation-group="{{ $group['key'] }}" @if($group['active']) data-active-group open @endif>
            <summary @if($group['active']) aria-current="true" @endif><flux:icon :name="$group['icon']" class="size-5" /><span>{{ $group['label'] }}</span><flux:icon.chevron-down class="app-navigation__chevron size-4" /></summary>
            <div class="app-navigation__items">@foreach ($group['items'] as $item)<a href="{{ $item['url'] }}" class="app-navigation__item {{ $item['active'] ? 'is-active' : '' }}" @if($item['active']) aria-current="page" @endif wire:navigate><flux:icon :name="$item['icon']" class="size-5" /><span>{{ $item['label'] }}</span></a>@endforeach</div>
        </details>
    @endforeach
</nav>
