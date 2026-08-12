<?php

use App\Modules\Catalog\Actions\SaveProductOptionAction;
use App\Modules\Catalog\Models\ProductOptionGroup;
use App\Modules\Catalog\Models\ProductOptionValue;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Product Options')] class extends Component {
    public ?int $editingGroupId = null;
    public ?int $editingValueId = null;
    public ?int $valueGroupId = null;
    public array $groupForm = ['code' => '', 'name_ar' => '', 'name_en' => '', 'status' => 'active', 'sort_order' => 0];
    public array $valueForm = ['code' => '', 'name_ar' => '', 'name_en' => '', 'colour_swatch' => '', 'status' => 'active', 'sort_order' => 0];

    public function mount(): void
    {
        Gate::authorize('products_categories_brands.view');
    }

    public function editGroup(int $id): void
    {
        Gate::authorize('products_categories_brands.edit');
        $group = ProductOptionGroup::query()->findOrFail($id);
        $this->editingGroupId = $group->id;
        $this->groupForm = $group->only(['code', 'name_ar', 'name_en', 'status', 'sort_order']);
        $this->resetValidation();
    }

    public function saveGroup(SaveProductOptionAction $action): void
    {
        Gate::authorize($this->editingGroupId ? 'products_categories_brands.edit' : 'products_categories_brands.create');
        $data = $this->validate([
            'groupForm.code' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9][A-Za-z0-9_-]*$/', Rule::unique('product_option_groups', 'code')->ignore($this->editingGroupId)],
            'groupForm.name_ar' => ['required', 'string', 'max:255'],
            'groupForm.name_en' => ['required', 'string', 'max:255'],
            'groupForm.status' => ['required', Rule::in(['active', 'inactive'])],
            'groupForm.sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
        ])['groupForm'];
        try {
            $action->saveGroup($data, $this->editingGroupId);
            $this->editingGroupId = null;
            $this->groupForm = ['code' => '', 'name_ar' => '', 'name_en' => '', 'status' => 'active', 'sort_order' => 0];
            Flux::toast(variant: 'success', text: __('Option group saved successfully.'));
        } catch (\Throwable $exception) {
            $this->addError('groupForm', $exception->getMessage());
        }
    }

    public function startValue(int $groupId): void
    {
        Gate::authorize('products_categories_brands.edit');
        $this->valueGroupId = ProductOptionGroup::query()->findOrFail($groupId)->id;
        $this->editingValueId = null;
        $this->valueForm = ['code' => '', 'name_ar' => '', 'name_en' => '', 'colour_swatch' => '', 'status' => 'active', 'sort_order' => 0];
        $this->resetValidation();
    }

    public function editValue(int $id): void
    {
        Gate::authorize('products_categories_brands.edit');
        $value = ProductOptionValue::query()->findOrFail($id);
        $this->valueGroupId = $value->product_option_group_id;
        $this->editingValueId = $value->id;
        $this->valueForm = $value->only(['code', 'name_ar', 'name_en', 'colour_swatch', 'status', 'sort_order']);
        $this->valueForm['colour_swatch'] ??= '';
        $this->resetValidation();
    }

    public function saveValue(SaveProductOptionAction $action): void
    {
        Gate::authorize($this->editingValueId ? 'products_categories_brands.edit' : 'products_categories_brands.create');
        abort_if($this->valueGroupId === null, 422);
        $data = $this->validate([
            'valueForm.code' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9][A-Za-z0-9_-]*$/'],
            'valueForm.name_ar' => ['required', 'string', 'max:255'],
            'valueForm.name_en' => ['required', 'string', 'max:255'],
            'valueForm.colour_swatch' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}([0-9A-Fa-f]{2})?$/'],
            'valueForm.status' => ['required', Rule::in(['active', 'inactive'])],
            'valueForm.sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
        ])['valueForm'];
        try {
            $action->saveValue($this->valueGroupId, $data, $this->editingValueId);
            $this->editingValueId = null;
            $this->valueGroupId = null;
            Flux::toast(variant: 'success', text: __('Option value saved successfully.'));
        } catch (\Throwable $exception) {
            $this->addError('valueForm', $exception->getMessage());
        }
    }

    public function render()
    {
        return view('catalog.product-options', [
            'groups' => ProductOptionGroup::query()->with('values')->orderBy('sort_order')->orderBy('code')->get(),
        ]);
    }
}; ?>

<div class="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6">
    <x-page-header :title="__('Product Options')" :description="__('Build a reusable bilingual option library for explicit standard-product variation families.')">
        <x-slot:actions><flux:button href="{{ route('catalog.products') }}" variant="subtle" icon="arrow-left">{{ __('Product Master') }}</flux:button></x-slot:actions>
    </x-page-header>

    @if ($errors->has('groupForm') || $errors->has('valueForm'))
        <flux:callout variant="danger" icon="exclamation-triangle">{{ $errors->first('groupForm') ?: $errors->first('valueForm') }}</flux:callout>
    @endif

    @can('products_categories_brands.create')
        <form wire:submit="saveGroup" class="grid gap-4 rounded-2xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-800 dark:bg-zinc-900 sm:grid-cols-2 lg:grid-cols-6" aria-label="{{ __('Option group editor') }}">
            <div class="lg:col-span-6"><flux:heading size="lg">{{ $editingGroupId ? __('Edit option group') : __('New option group') }}</flux:heading><flux:text class="mt-1 text-sm">{{ __('Codes are permanent. Labels and display order remain editable.') }}</flux:text></div>
            <flux:input wire:model="groupForm.code" :label="__('Code')" required :disabled="$editingGroupId !== null" />
            <flux:input wire:model="groupForm.name_ar" :label="__('Arabic label')" dir="rtl" required />
            <flux:input wire:model="groupForm.name_en" :label="__('English label')" dir="ltr" required />
            <flux:select wire:model="groupForm.status" :label="__('Status')"><flux:select.option value="active">{{ __('Active') }}</flux:select.option><flux:select.option value="inactive">{{ __('Inactive') }}</flux:select.option></flux:select>
            <flux:input wire:model="groupForm.sort_order" type="number" min="0" :label="__('Order')" />
            <div class="flex items-end"><flux:button type="submit" variant="primary" class="w-full" wire:loading.attr="disabled">{{ __('Save group') }}</flux:button></div>
            <div wire:loading class="lg:col-span-6"><div class="h-1.5 animate-pulse rounded-full bg-cyan-200 dark:bg-cyan-900"></div></div>
        </form>
    @endcan

    <section class="space-y-4" aria-labelledby="option-library-heading">
        <div><flux:heading id="option-library-heading" size="xl">{{ __('Option library') }}</flux:heading><flux:text class="mt-1">{{ __('Inactive choices remain readable in historical variation snapshots.') }}</flux:text></div>
        @forelse ($groups as $group)
            <article class="overflow-hidden rounded-2xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                <header class="flex flex-wrap items-center justify-between gap-3 border-b border-zinc-200 px-5 py-4 dark:border-zinc-800"><div><div class="flex items-center gap-2"><flux:heading size="lg">{{ app()->getLocale() === 'ar' ? $group->name_ar : $group->name_en }}</flux:heading><x-status.badge :status="$group->status" /></div><p class="mt-1 font-mono text-xs text-zinc-500">{{ $group->code }} · {{ $group->name_ar }} / {{ $group->name_en }}</p></div>@can('products_categories_brands.edit')<div class="flex gap-2"><flux:button wire:click="editGroup({{ $group->id }})" size="sm" variant="subtle">{{ __('Edit') }}</flux:button><flux:button wire:click="startValue({{ $group->id }})" size="sm" variant="primary" :disabled="$group->status !== 'active'">{{ __('Add value') }}</flux:button></div>@endcan</header>
                <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($group->values as $value)
                        <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-3"><div class="flex items-center gap-3">@if($value->colour_swatch)<span class="size-7 rounded-full border border-zinc-300" style="background-color: {{ $value->colour_swatch }}" aria-label="{{ __('Colour swatch') }} {{ $value->colour_swatch }}"></span>@endif<div><div class="font-semibold">{{ app()->getLocale() === 'ar' ? $value->name_ar : $value->name_en }} <x-status.badge :status="$value->status" /></div><div class="font-mono text-xs text-zinc-500">{{ $value->code }} · {{ $value->name_ar }} / {{ $value->name_en }}</div></div></div>@can('products_categories_brands.edit')<flux:button wire:click="editValue({{ $value->id }})" size="sm" variant="ghost">{{ __('Edit') }}</flux:button>@endcan</div>
                    @empty
                        <div class="p-5"><x-state.empty :title="__('No values in this group')" :description="__('Add at least one bilingual value before using this group in a product family.')" /></div>
                    @endforelse
                </div>
            </article>
        @empty
            <x-state.empty :title="__('No product options yet')" :description="__('Create a bilingual option group such as Colour or Size, then add its values.')" />
        @endforelse
    </section>

    @if ($valueGroupId)
        <form wire:submit="saveValue" class="fixed inset-x-0 bottom-0 z-40 border-t border-zinc-200 bg-white p-4 shadow-2xl dark:border-zinc-800 dark:bg-zinc-900" aria-label="{{ __('Option value editor') }}">
            <div class="mx-auto grid max-w-7xl gap-3 sm:grid-cols-2 lg:grid-cols-7"><flux:input wire:model="valueForm.code" :label="__('Value code')" required :disabled="$editingValueId !== null" /><flux:input wire:model="valueForm.name_ar" :label="__('Arabic label')" dir="rtl" required /><flux:input wire:model="valueForm.name_en" :label="__('English label')" dir="ltr" required /><flux:input wire:model="valueForm.colour_swatch" type="color" :label="__('Optional swatch')" /><flux:select wire:model="valueForm.status" :label="__('Status')"><flux:select.option value="active">{{ __('Active') }}</flux:select.option><flux:select.option value="inactive">{{ __('Inactive') }}</flux:select.option></flux:select><flux:input wire:model="valueForm.sort_order" type="number" min="0" :label="__('Order')" /><div class="flex items-end gap-2"><flux:button type="submit" variant="primary" class="flex-1">{{ __('Save value') }}</flux:button><flux:button type="button" wire:click="$set('valueGroupId', null)" variant="subtle">{{ __('Cancel') }}</flux:button></div></div>
        </form>
    @endif
</div>
