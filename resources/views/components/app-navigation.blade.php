@props(['groups' => null])
@php($groups ??= app(\App\Modules\Platform\Support\ApplicationNavigation::class)->for(auth()->user(), app()->getLocale()))

<nav aria-label="{{ app()->getLocale() === 'ar' ? 'التنقل الرئيسي' : 'Primary navigation' }}" class="app-navigation" data-flux-sidebar-nav>
    @foreach ($groups as $group)
        <flux:sidebar.group
            :heading="$group['label']"
            :icon="$group['icon']"
            expandable
            :expanded="$group['active']"
            data-sidebar-expandable
            class="sidebar-nav-group"
        >
            @foreach ($group['items'] as $item)
                <flux:sidebar.item :icon="$item['icon']" :href="$item['url']" :current="$item['active']" :tooltip="$item['label']" tooltip:position="{{ app()->getLocale() === 'ar' ? 'left' : 'right' }}" wire:navigate>
                    {{ $item['label'] }}
                </flux:sidebar.item>
            @endforeach
        </flux:sidebar.group>
    @endforeach
</nav>
