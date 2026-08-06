@props([
    'headers' => [],
    'rows' => [],
    'emptyMessage' => 'No records found.',
])

<div {{ $attributes->merge(['class' => 'app-table-frame']) }}>
    @if (count($rows) > 0)
        <table class="min-w-full text-sm">
            <thead>
                <tr>
                    @foreach ($headers as $key => $label)
                        <th scope="col" class="whitespace-nowrap px-4 py-3">{{ $label }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
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
