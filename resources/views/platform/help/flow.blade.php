@php($locale = app()->getLocale())
@php($text = fn ($value): string => is_array($value) ? ($value[$locale] ?? $value['en'] ?? $value['ar'] ?? '') : (string) $value)

<x-layouts::app :title="$text($flow['title'])">
    <x-app.page
        :title="$text($flow['title'])"
        :description="$text($flow['actor'])"
        :eyebrow="$flow['flow_id']"
        max-width="4xl"
    >
        <section class="rounded-xl border border-border bg-surface p-5 shadow-card">
            <flux:heading size="lg">{{ $locale === 'ar' ? 'الشروط المسبقة' : 'Preconditions' }}</flux:heading>
            <p class="mt-3 text-sm text-text-muted">{{ $text($flow['preconditions']) }}</p>
        </section>
        <section class="rounded-xl border border-border bg-surface p-5 shadow-card">
            <flux:heading size="lg">{{ $locale === 'ar' ? 'الخطوات' : 'Steps' }}</flux:heading>
            <ol class="mt-4 space-y-4">
                @foreach($flow['steps'] as $step)
                    <li class="flex gap-3">
                        <span class="size-7 shrink-0 rounded-full bg-primary-soft text-center leading-7 text-primary">{{ $step['number'] }}</span>
                        <p class="text-sm">{{ $text($step['body']) }}</p>
                    </li>
                @endforeach
            </ol>
        </section>
        <div class="grid gap-5 md:grid-cols-2">
            <section class="rounded-xl border border-border bg-surface p-5">
                <flux:heading size="lg">{{ $locale === 'ar' ? 'المسارات البديلة' : 'Alternate paths' }}</flux:heading>
                <p class="mt-3 text-sm text-text-muted">{{ $text($flow['alternate_paths']) }}</p>
            </section>
            <section class="rounded-xl border border-border bg-surface p-5">
                <flux:heading size="lg">{{ $locale === 'ar' ? 'مسارات الفشل' : 'Failure paths' }}</flux:heading>
                <p class="mt-3 text-sm text-text-muted">{{ $text($flow['failure_paths']) }}</p>
            </section>
        </div>
        <a class="platform-assistant__secondary" href="{{ url()->previous() }}">{{ $locale === 'ar' ? 'العودة' : 'Back' }}</a>
    </x-app.page>
</x-layouts::app>
