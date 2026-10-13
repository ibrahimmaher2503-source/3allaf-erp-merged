<footer class="w-full shrink-0 overflow-hidden px-2 py-2 text-center app-footer text-[clamp(0.58rem,2.35vw,0.8125rem)] leading-none text-zinc-500 dark:text-zinc-400" dir="{{ in_array(app()->getLocale(), config('app.rtl_locales'), true) ? 'rtl' : 'ltr' }}">
    <p class="whitespace-nowrap" dir="ltr">v{{ preg_replace('/^0\./', '', preg_replace('/[^0-9.].*$/', '', \App\Support\ApplicationVersion::RELEASE)) }}</p>
</footer>
