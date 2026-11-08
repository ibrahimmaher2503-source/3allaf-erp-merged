@props(['size' => 'md'])
@php
    $isAr = str_starts_with(app()->getLocale(), 'ar');
    $icon = ['sm' => 'size-8', 'md' => 'size-10', 'lg' => 'size-14'][$size] ?? 'size-10';
    $text = ['sm' => 'text-lg', 'md' => 'text-2xl', 'lg' => 'text-4xl'][$size] ?? 'text-2xl';
@endphp
<span {{ $attributes->class(['inline-flex items-center gap-3 font-black text-teal-700 dark:text-teal-300']) }}>
    <x-app-logo-icon class="{{ $icon }} shrink-0" />
    <span class="{{ $text }} leading-none text-zinc-900 dark:text-white">{{ $isAr ? 'علاف' : '3allaf' }}</span>
</span>
