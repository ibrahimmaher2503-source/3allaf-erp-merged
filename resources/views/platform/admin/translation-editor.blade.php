<?php

use App\Modules\Platform\Actions\SaveTranslationOverride;
use App\Modules\Platform\Models\TranslationOverride;
use App\Modules\Platform\Support\TranslationCatalog;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Translation editor')] class extends Component {
    use WithPagination;

    public string $search = '';
    public string $groupFilter = '';
    public int $perPage = 20;
    public string $editingGroup = '';
    public string $translationKey = '';
    public array $values = ['ar' => '', 'en' => ''];
    public bool $saved = false;

    public function mount(): void
    {
        Gate::authorize('company_settings.edit');
    }

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedGroupFilter(): void { $this->resetPage(); }
    public function updatedPerPage(): void { $this->perPage = in_array($this->perPage, [20, 25], true) ? $this->perPage : 20; $this->resetPage(); }

    public function edit(string $group, string $key): void
    {
        Gate::authorize('company_settings.edit');
        $entry = app(TranslationCatalog::class)->find($group, $key);
        abort_unless($entry, 404);

        $overrides = TranslationOverride::query()->where('group', $group)->where('translation_key', $key)->pluck('value', 'locale');
        $this->editingGroup = $group;
        $this->translationKey = $key;
        $this->values = ['ar' => $overrides['ar'] ?? $entry['ar'], 'en' => $overrides['en'] ?? $entry['en']];
        $this->saved = false;
        $this->resetValidation();
    }

    public function save(?string $group = null, ?string $key = null, ?string $ar = null, ?string $en = null): void
    {
        Gate::authorize('company_settings.edit');
        if ($group !== null && $key !== null && $ar !== null && $en !== null) {
            $this->editingGroup = $group;
            $this->translationKey = $key;
            $this->values = ['ar' => $ar, 'en' => $en];
        }

        $this->validate([
            'editingGroup' => ['required', 'string', 'max:120'],
            'translationKey' => ['required', 'string', 'max:255'],
            'values.ar' => ['required', 'string', 'max:4000'],
            'values.en' => ['required', 'string', 'max:4000'],
        ]);

        try {
            DB::transaction(function (): void {
                foreach (['ar', 'en'] as $locale) {
                    app(SaveTranslationOverride::class)->execute(auth()->user(), $locale, $this->editingGroup, $this->translationKey, $this->values[$locale]);
                }
            });
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }

            return;
        }

        $this->saved = true;
        $this->dispatch('translation-saved');
    }

    public function resetOverride(string $group, string $key): void
    {
        Gate::authorize('company_settings.edit');
        $entry = app(TranslationCatalog::class)->find($group, $key);
        abort_unless($entry, 404);

        DB::transaction(function () use ($group, $key, $entry): void {
            foreach (['ar', 'en'] as $locale) {
                app(SaveTranslationOverride::class)->execute(auth()->user(), $locale, $group, $key, $entry[$locale]);
            }
        });

        if ($this->editingGroup === $group && $this->translationKey === $key) {
            $this->values = ['ar' => $entry['ar'], 'en' => $entry['en']];
        }
        $this->saved = true;
    }

    public function render()
    {
        Gate::authorize('company_settings.edit');
        $catalog = collect(app(TranslationCatalog::class)->all());
        $groups = $catalog->pluck('group')->unique()->sort()->values();
        $search = mb_strtolower(trim($this->search));
        $overrides = TranslationOverride::query()->get()->keyBy(fn (TranslationOverride $override) => $override->locale.'|'.$override->group.'|'.$override->translation_key);
        $entries = $catalog->filter(fn (array $entry) => ($this->groupFilter === '' || $entry['group'] === $this->groupFilter)
            && ($search === '' || str_contains(mb_strtolower($entry['key'].' '.$entry['ar'].' '.$entry['en']), $search)))
            ->sortBy(fn (array $entry) => $entry['group'].'|'.$entry['key'])->map(function (array $entry) use ($overrides): array {
                $ar = $overrides['ar|'.$entry['group'].'|'.$entry['key']] ?? null;
                $en = $overrides['en|'.$entry['group'].'|'.$entry['key']] ?? null;
                $entry['display_ar'] = $ar?->value ?? $entry['ar'];
                $entry['display_en'] = $en?->value ?? $entry['en'];
                $entry['custom'] = $ar !== null || $en !== null;

                return $entry;
            })->values();
        $page = $this->getPage();
        $rows = new LengthAwarePaginator($entries->forPage($page, $this->perPage), $entries->count(), $this->perPage, $page);

        return view('platform.admin.translation-editor', compact('groups', 'rows'));
    }
}; ?>

<x-app.page :title="__('Translation editor')" :description="__('Change approved Arabic and English system wording without editing language files.')" max-width="7xl" class="space-y-5">
    <x-tables.data-panel :title="__('System translations')" :description="__('Search a known translation key, update both languages, or reset to the shipped wording.')">
        <x-slot:toolbar>
            <div class="grid gap-3 md:grid-cols-3">
                <flux:input wire:model.live.debounce.300ms="search" :label="__('Search')" icon="magnifying-glass" :placeholder="__('Key or text')" />
                <flux:select wire:model.live="groupFilter" :label="__('Translation file')"><option value="">{{ __('All files') }}</option>@foreach($groups as $group)<option value="{{ $group }}">{{ $group === '*' ? __('JSON translations') : $group }}</option>@endforeach</flux:select>
                <flux:select wire:model.live="perPage" :label="__('Rows per page')"><option value="20">20</option><option value="25">25</option></flux:select>
            </div>
        </x-slot:toolbar>

        @if ($saved)<flux:callout variant="success" icon="check-circle" wire:loading.remove>{{ __('Translation changes saved.') }}</flux:callout>@endif
        <div wire:loading class="py-3 text-sm text-text-muted">{{ __('Loading translations…') }}</div>
        @if ($rows->isEmpty())
            <x-state.empty :title="__('No translations found')" :description="__('Try another search or translation file.')" icon="language" />
        @else
            <div class="hidden md:block"><flux:table><flux:table.columns><flux:table.column>{{ __('File') }}</flux:table.column><flux:table.column>{{ __('Key') }}</flux:table.column><flux:table.column>{{ __('Arabic') }}</flux:table.column><flux:table.column>{{ __('English') }}</flux:table.column><flux:table.column>{{ __('Actions') }}</flux:table.column></flux:table.columns><flux:table.rows>
                @foreach($rows as $entry)<flux:table.row key="translation-{{ md5($entry['group'].'|'.$entry['key']) }}"><flux:table.cell>{{ $entry['group'] }}</flux:table.cell><flux:table.cell class="font-mono text-xs">{{ $entry['key'] }} @if($entry['custom'])<flux:badge size="sm">{{ __('Custom') }}</flux:badge>@endif</flux:table.cell><flux:table.cell>{{ $entry['display_ar'] }}</flux:table.cell><flux:table.cell>{{ $entry['display_en'] }}</flux:table.cell><flux:table.cell class="flex gap-2"><flux:button size="xs" wire:click="edit(@js($entry['group']), @js($entry['key']))">{{ __('Edit') }}</flux:button><flux:button size="xs" variant="subtle" wire:click="resetOverride(@js($entry['group']), @js($entry['key']))" wire:confirm="{{ __('Reset both Arabic and English values to the shipped wording?') }}">{{ __('Reset') }}</flux:button></flux:table.cell></flux:table.row>@endforeach
            </flux:table.rows></flux:table></div>
            <div class="space-y-3 md:hidden">@foreach($rows as $entry)<x-cards.section-card :title="$entry['key']"><p class="text-xs text-text-muted">{{ $entry['group'] }} @if($entry['custom']) · {{ __('Custom') }} @endif</p><p dir="rtl">{{ $entry['display_ar'] }}</p><p dir="ltr">{{ $entry['display_en'] }}</p><div class="mt-3 flex gap-2"><flux:button size="sm" wire:click="edit(@js($entry['group']), @js($entry['key']))">{{ __('Edit') }}</flux:button><flux:button size="sm" variant="subtle" wire:click="resetOverride(@js($entry['group']), @js($entry['key']))" wire:confirm="{{ __('Reset both Arabic and English values to the shipped wording?') }}">{{ __('Reset') }}</flux:button></div></x-cards.section-card>@endforeach</div>
        @endif
        <x-slot:footer>{{ $rows->links() }}</x-slot:footer>
    </x-tables.data-panel>

    @if($translationKey !== '')
        <x-cards.section-card :title="__('Edit translation')" :description="$editingGroup.' · '.$translationKey" class="space-y-4">
            <flux:textarea wire:model.blur="values.ar" dir="rtl" :label="__('Arabic')" :invalid="$errors->has('values.ar')" />
            @error('values.ar')<flux:text class="text-red-600">{{ $message }}</flux:text>@enderror
            <flux:textarea wire:model.blur="values.en" dir="ltr" :label="__('English')" :invalid="$errors->has('values.en')" />
            @error('values.en')<flux:text class="text-red-600">{{ $message }}</flux:text>@enderror
            @error('translationKey')<flux:text class="text-red-600">{{ $message }}</flux:text>@enderror
            <div class="flex flex-wrap gap-3"><flux:button wire:click="save" wire:loading.attr="disabled" wire:dirty.attr="data-dirty">{{ __('Save') }}</flux:button><flux:button variant="subtle" wire:click="resetOverride(@js($editingGroup), @js($translationKey))" wire:confirm="{{ __('Reset both Arabic and English values to the shipped wording?') }}" wire:loading.attr="disabled">{{ __('Reset to base') }}</flux:button></div>
        </x-cards.section-card>
    @endif
</x-app.page>
