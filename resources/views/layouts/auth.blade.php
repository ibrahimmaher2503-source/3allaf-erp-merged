@props(['loginPage' => false])

<x-layouts::auth.simple :title="$title ?? null" :login-page="$loginPage">
    {{ $slot }}
</x-layouts::auth.simple>
