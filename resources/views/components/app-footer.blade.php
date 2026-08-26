<footer class="w-full shrink-0 overflow-hidden px-2 py-2 text-center text-[clamp(0.55rem,2.2vw,0.75rem)] leading-none text-zinc-500 dark:text-zinc-400" dir="{{ in_array(app()->getLocale(), config('app.rtl_locales'), true) ? 'rtl' : 'ltr' }}">
    <p class="whitespace-nowrap">
        @if (app()->getLocale() === 'ar')
            نظام راجح بإصدار v{{ \App\Support\ApplicationVersion::RELEASE }} — برمجة وتنفيذ <a class="font-medium underline underline-offset-2 hover:text-zinc-700 dark:hover:text-zinc-200" href="https://s-eg.com" target="_blank" rel="noopener noreferrer">إنتلجنت سوليوشنز</a>
        @else
            Rajeh System v{{ \App\Support\ApplicationVersion::RELEASE }} — Developed and implemented by <a class="font-medium underline underline-offset-2 hover:text-zinc-700 dark:hover:text-zinc-200" href="https://s-eg.com" target="_blank" rel="noopener noreferrer">Intelligent Solutions</a>
        @endif
    </p>
</footer>
