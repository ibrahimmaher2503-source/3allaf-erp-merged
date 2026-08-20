<?php

use App\Modules\Platform\Actions\SaveRoleAction;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Role Permissions')] class extends Component
{
    use WithPagination;

    public Role $role;
    public array $permissionIds = [];
    public string $search = '';
    public string $moduleFilter = 'all';

    public function mount(Role $role): void
    {
        Gate::authorize('users_roles_permissions.view');
        $this->role = $role;
        $this->permissionIds = $role->permissions()->pluck('permissions.id')->map(fn ($id): int => (int) $id)->all();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingModuleFilter(): void
    {
        $this->resetPage();
    }

    public function savePermissions(SaveRoleAction $action): void
    {
        Gate::authorize('users_roles_permissions.edit');
        $validated = $this->validate(['permissionIds' => ['array'], 'permissionIds.*' => ['integer', 'exists:permissions,id']]);

        try {
            $action->syncPermissions($this->role, $validated['permissionIds']);
            $this->role->refresh();
            Flux::toast(variant: 'success', text: __('Role permissions saved successfully.'));
        } catch (\Throwable $exception) {
            $this->addError('permissionIds', $exception->getMessage());
            Flux::toast(variant: 'danger', text: $exception->getMessage());
        }
    }

    public function render(): mixed
    {
        $permissions = Permission::query()
            ->when($this->search !== '', fn ($query) => $query->where(fn ($query) => $query->where('code', 'like', '%'.trim($this->search).'%')->orWhere('module', 'like', '%'.trim($this->search).'%')->orWhere('action', 'like', '%'.trim($this->search).'%')))
            ->when($this->moduleFilter !== 'all', fn ($query) => $query->where('module', $this->moduleFilter))
            ->orderBy('module')->orderBy('action')->paginate(50);

        return view('platform.admin.role-permissions', [
            'permissions' => $permissions,
            'modules' => Permission::query()->distinct()->orderBy('module')->pluck('module'),
            'canonical' => SaveRoleAction::isCanonical($this->role),
            'canEdit' => Gate::allows('users_roles_permissions.edit') && ! SaveRoleAction::isCanonical($this->role),
        ]);
    }
}; ?>

<x-app.page :title="__('Role permissions')" :description="__('Review the permission matrix for :role.', ['role' => app()->getLocale() === 'ar' ? $role->name_ar : $role->name_en])" max-width="7xl" class="space-y-5">
    <x-slot:actions><flux:button variant="subtle" icon="arrow-left" :href="route('admin.roles')" wire:navigate>{{ __('Back to roles') }}</flux:button></x-slot:actions>

    @if ($canonical)
        <flux:callout variant="warning" icon="lock-closed" title="{{ __('Canonical role') }}">{{ __('This canonical role is read-only. Its grants remain protected by the approved authorization baseline.') }}</flux:callout>
    @elseif (! $canEdit)
        <flux:callout variant="info" icon="eye">{{ __('You can review this matrix, but your account cannot change role permissions.') }}</flux:callout>
    @else
        <flux:callout variant="info" icon="information-circle">{{ __('Sensitive permissions require owner-approved canonical role grants.') }}</flux:callout>
    @endif

    <form wire:submit="savePermissions" class="space-y-4">
        <x-tables.data-panel :title="__('Permissions')" :description="__('Only active, non-sensitive permissions can be assigned to a local role in this screen.')">
            <x-slot:toolbar>
                <x-tables.filter-bar>
                    <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :label="__('Search permissions')" :placeholder="__('Code, module, or action')" />
                    <flux:select wire:model.live="moduleFilter" :label="__('Module')" class="sm:w-56"><option value="all">{{ __('All modules') }}</option>@foreach ($modules as $module)<option value="{{ $module }}">{{ $module }}</option>@endforeach</flux:select>
                </x-tables.filter-bar>
            </x-slot:toolbar>

            @if ($permissions->isEmpty())
                <x-state.empty :title="__('No permissions found')" :description="__('Change the filter to review the available permission catalog.')" icon="key" />
            @else
                <div class="overflow-x-auto"><table class="data-table min-w-[52rem] w-full text-sm"><thead><tr><th class="px-3 py-2.5 text-start">{{ __('Permission') }}</th><th class="px-3 py-2.5 text-start">{{ __('Module') }}</th><th class="px-3 py-2.5 text-start">{{ __('Action') }}</th><th class="px-3 py-2.5 text-start">{{ __('Sensitivity') }}</th><th class="px-3 py-2.5 text-end">{{ __('Granted') }}</th></tr></thead><tbody>
                    @foreach ($permissions as $permission)
                        @php($editable = $canEdit && $permission->status === 'active' && $permission->sensitivity !== 'sensitive')
                        <tr class="data-table-row"><td class="px-3 py-3 font-mono text-xs">{{ $permission->code }}</td><td class="px-3 py-3">{{ $permission->module }}</td><td class="px-3 py-3">{{ $permission->action }}</td><td class="px-3 py-3">@if ($permission->sensitivity === 'sensitive')<flux:badge size="sm" color="amber">{{ __('Sensitive') }}</flux:badge>@else<flux:badge size="sm" variant="outline">{{ __('Standard') }}</flux:badge>@endif</td><td class="px-3 py-3 text-end"><flux:checkbox wire:model="permissionIds" value="{{ $permission->id }}" :disabled="! $editable" :label="__('Granted')" /></td></tr>
                    @endforeach
                </tbody></table></div>
            @endif
            <x-slot:footer><div class="flex justify-end">{{ $permissions->links() }}</div></x-slot:footer>
        </x-tables.data-panel>

        @error('permissionIds')<flux:callout variant="danger">{{ $message }}</flux:callout>@enderror
        @if ($canEdit)<div class="flex justify-end"><flux:button type="submit" variant="primary" icon="check" wire:loading.attr="disabled" wire:target="savePermissions">{{ __('Save permissions') }}</flux:button></div>@endif
    </form>
</x-app.page>
