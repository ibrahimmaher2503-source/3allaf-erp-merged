@props([
    'headers' => [],
    'rows' => [],
    'emptyMessage' => 'No records found.',
])

<div {{ $attributes->merge(['class' => 'overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700']) }}>
    @if (count($rows) > 0)
        <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
            <thead class="bg-zinc-50 text-left text-xs font-semibold uppercase tracking-wide text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                <tr>
                    @foreach ($headers as $key => $label)
                        <th scope="col" class="whitespace-nowrap px-4 py-3">{{ $label }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 bg-white dark:divide-zinc-800 dark:bg-zinc-900">
                @foreach ($rows as $row)
                    <tr wire:key="{{ $row['id'] ?? $loop->index }}" class="align-top">
                        @foreach ($headers as $key => $label)
                            <td class="whitespace-nowrap px-4 py-3 text-zinc-800 dark:text-zinc-100">
                                <x-data.value :value="$row[$key] ?? null" />
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="px-4 py-10 text-center text-sm text-zinc-500 dark:text-zinc-400" role="status">
            {{ $emptyMessage }}
        </div>
    @endif
</div>
