@props(['groups' => null])
@php($groups ??= app(\App\Modules\Platform\Support\ApplicationNavigation::class)->for(auth()->user(), app()->getLocale()))

<nav {{ $attributes->class('app-navigation') }} aria-label="{{ app()->getLocale() === 'ar' ? 'التنقل الرئيسي' : 'Primary navigation' }}">
    @foreach ($groups as $group)
        <a class="app-navigation__rail-link {{ $group['active'] ? 'is-active' : '' }}" href="{{ $group['items'][0]['url'] }}" title="{{ $group['label'] }}" aria-label="{{ $group['label'] }}" wire:navigate><flux:icon :name="$group['icon']" class="size-5" /></a>
        <details class="app-navigation__group" @if($group['active']) open @endif>
            <summary><flux:icon :name="$group['icon']" class="size-5" /><span>{{ $group['label'] }}</span><flux:icon.chevron-down class="app-navigation__chevron size-4" /></summary>
            <div class="app-navigation__items">@foreach ($group['items'] as $item)<a href="{{ $item['url'] }}" class="app-navigation__item {{ $item['active'] ? 'is-active' : '' }}" @if($item['active']) aria-current="page" @endif wire:navigate><flux:icon :name="$item['icon']" class="size-5" /><span>{{ $item['label'] }}</span></a>@endforeach</div>
        </details>
    @endforeach
</nav>
