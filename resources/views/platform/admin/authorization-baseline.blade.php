<?php

use App\Models\User;
use App\Modules\Platform\Actions\SaveUserAction;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Models\UserBranchScope;
use App\Modules\Platform\Models\UserStoreScope;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Authorization Baseline')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public ?int $editingUserId = null;

    public bool $authorizationModalOpen = false;

    public array $userForm = [
        'name' => '',
        'email' => '',
        'password' => '',
        'status' => 'active',
    ];

    public array $roleIds = [];

    public array $branchIds = [];

    public array $storeIds = [];

    public function mount(): void
    {
        Gate::authorize('users_roles_permissions.view');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function editAuthorization(int $userId): void
    {
        Gate::authorize('users_roles_permissions.edit');

        $user = User::findOrFail($userId);

        $this->editingUserId = $user->id;
        $this->authorizationModalOpen = true;
        $this->userForm = [
            'name' => $user->name,
            'email' => $user->email,
            'password' => '',
            'status' => $user->status ?: 'active',
        ];
        $this->roleIds = $user->roles()->pluck('roles.id')->all();
        $this->branchIds = $user->branchScopes()->pluck('branch_id')->all();
        $this->storeIds = $user->storeScopes()->pluck('store_id')->all();
    }

    public function openCreateUserModal(): void
    {
        Gate::authorize('users_roles_permissions.create');
        $this->editingUserId = null;
        $this->authorizationModalOpen = true;
        $this->userForm = ['name' => '', 'email' => '', 'password' => '', 'status' => 'active'];
        $this->roleIds = [];
        $this->branchIds = [];
        $this->storeIds = [];
        $this->resetValidation();
    }

    public function saveAuthorization(SaveUserAction $action): void
    {
        Gate::authorize('users_roles_permissions.edit');

        $validated = $this->validate([
            'editingUserId' => ['nullable', 'exists:users,id'],
            'userForm.name' => ['required', 'string', 'max:255'],
            'userForm.email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->editingUserId)],
            'userForm.password' => [$this->editingUserId === null ? 'required' : 'nullable', 'string', 'min:8', 'max:255'],
            'userForm.status' => ['required', Rule::in(['active', 'inactive'])],
            'roleIds' => ['array'],
            'roleIds.*' => ['integer', 'exists:roles,id'],
            'branchIds' => ['array'],
            'branchIds.*' => ['integer', 'exists:branches,id'],
            'storeIds' => ['array'],
            'storeIds.*' => ['integer', 'exists:stores,id'],
        ]);

        $action->execute(
            $validated['userForm'],
            array_map('intval', $validated['roleIds']),
            array_map('intval', $validated['branchIds']),
            array_map('intval', $validated['storeIds']),
            $this->editingUserId === null ? null : User::findOrFail($this->editingUserId),
        );

        $this->closeAuthorization();
    }

    public function closeAuthorization(): void
    {
        $this->authorizationModalOpen = false;
        $this->editingUserId = null;
        $this->roleIds = [];
        $this->branchIds = [];
        $this->storeIds = [];
        $this->userForm = ['name' => '', 'email' => '', 'password' => '', 'status' => 'active'];
    }

    public function render(): mixed
    {
        return view('platform.admin.authorization-baseline', [
            'users' => User::query()
                ->with('roles:id,name_ar,name_en')
                ->when($this->search !== '', fn ($query) => $query->where(function ($query): void {
                    $query->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('email', 'like', '%'.$this->search.'%');
                }))
                ->latest()
                ->paginate(15),
            'roleCount' => Role::query()->where('status', 'active')->count(),
            'permissionCount' => Permission::query()->count(),
            'scopeAssignmentCount' => UserBranchScope::query()->count() + UserStoreScope::query()->count(),
            'roles' => $this->authorizationModalOpen ? Role::query()->where('status', 'active')->orderBy('name_en')->get(['id', 'name_ar', 'name_en']) : [],
            'branches' => $this->authorizationModalOpen ? Branch::query()->where('status', 'active')->orderBy('name_en')->get(['id', 'name_ar', 'name_en']) : [],
            'stores' => $this->authorizationModalOpen ? Store::query()->where('status', 'active')->orderBy('name_en')->get(['id', 'name_ar', 'name_en', 'branch_id']) : [],
        ]);
    }
}; ?>

<x-app.page
    :title="__('Authorization Baseline')"
    :description="__('Manage approved roles and branch or store scopes.')"
    max-width="7xl"
    class="space-y-5"
    data-guide="auth-header"
>

    <section class="rounded-lg border border-primary/20 bg-primary-soft px-5 py-5 sm:px-6" aria-labelledby="authorization-overview-title" data-guide="auth-overview">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="max-w-3xl space-y-1">
                 <p class="text-xs font-semibold uppercase tracking-[0.08em] text-primary">{{ __('Access management') }}</p>
                <flux:heading id="authorization-overview-title" size="lg" class="text-text-primary">{{ __('Current access is role-based and scope-aware') }}</flux:heading>
                <flux:text class="text-text-muted">{{ __('Assignment changes are audited. Sensitive approvals and limits are enforced where their modules exist.') }}</flux:text>
            </div>
            <x-status.badge status="active" :label="__('Current scope enforced')" class="shrink-0" />
        </div>
    </section>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div data-guide="auth-users-card"><x-cards.stat-card :label="__('Users')" :value="$users->total()" :description="__('Searchable inventory')" icon="users" /></div>
        <div data-guide="auth-roles-card"><x-cards.stat-card :label="__('Roles')" :value="$roleCount" :description="__('Configured roles')" icon="shield-check" tone="info" /></div>
        <div data-guide="auth-permissions-card"><x-cards.stat-card :label="__('Permissions')" :value="$permissionCount" :description="__('Available permissions')" icon="key" tone="success" /></div>
        <div data-guide="auth-scopes-card"><x-cards.stat-card :label="__('Scope assignments')" :value="$scopeAssignmentCount" :description="__('Branch and store access')" icon="map-pin" tone="warning" /></div>
    </div>

    @if ($errors->any())
        <flux:callout variant="danger" title="{{ __('Authorization change could not be completed') }}">
            {{ __('Review the highlighted values. No partial authorization change was performed.') }}
        </flux:callout>
    @endif

    <x-tables.data-panel :title="__('Users')" :description="__('Review current access before opening an authorized assignment.')" data-guide="auth-users-table">
        <x-slot:toolbar>
            <x-tables.filter-bar>
                <flux:input wire:model.live.debounce.400ms="search" icon="magnifying-glass" :label="__('Search users')" :placeholder="__('Name or email')" data-guide="auth-users-search" />
                <x-slot:actions>
                    @can('users_roles_permissions.create')
                        <flux:button size="sm" variant="primary" icon="plus" wire:click="openCreateUserModal">{{ __('New user') }}</flux:button>
                    @endcan
                    <flux:badge size="sm" variant="outline" icon="user-group">{{ __('Records: :count', ['count' => $users->total()]) }}</flux:badge>
                </x-slot:actions>
            </x-tables.filter-bar>
        </x-slot:toolbar>

        @if ($users->isEmpty())
            <x-state.empty :title="__('No users found')" :description="__('No user records match this search.')" />
        @else
            <table class="data-table w-full min-w-[48rem] text-sm">
                <caption class="sr-only">{{ __('Current user authorization inventory') }}</caption>
                <thead class="bg-surface-muted/70 text-xs text-text-muted">
                    <tr>
                        <th class="px-3 py-2.5 text-start font-semibold">{{ __('User') }}</th>
                        <th class="px-3 py-2.5 text-start font-semibold">{{ __('Email') }}</th>
                        <th class="px-3 py-2.5 text-start font-semibold">{{ __('Roles') }}</th>
                        <th class="px-3 py-2.5 text-start font-semibold">{{ __('Status') }}</th>
                        <th class="px-3 py-2.5 text-start font-semibold">{{ __('Verification') }}</th>
                        <th class="px-3 py-2.5 text-end font-semibold">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $user)
                        @php
                            $roleNames = $user->roles
                                ->map(fn ($role) => app()->getLocale() === 'ar' ? $role->name_ar : $role->name_en)
                                ->filter()
                                ->join(', ');
                        @endphp
                        <tr class="data-table-row">
                            <td class="px-3 py-3"><div class="font-medium text-text-primary">{{ $user->name }}</div>@if ($user->is_super_admin)<div class="mt-1"><x-status.badge status="override" :label="__('Super Admin')" /></div>@endif</td>
                            <td class="px-3 py-3 text-text-muted">{{ $user->email }}</td>
                            <td class="px-3 py-3 text-text-muted">{{ $roleNames ?: __('No role assigned') }}</td>
                            <td class="px-3 py-3"><x-status.badge :status="$user->status === 'active' ? 'active' : 'inactive'" :label="$user->status === 'active' ? __('Active') : __('Inactive')" /></td>
                            <td class="px-3 py-3"><x-status.badge :status="$user->email_verified_at ? 'active' : 'pending'" :label="$user->email_verified_at ? __('Verified') : __('Not verified')" /></td>
                            <td class="px-3 py-3 text-end">
                                @can('users_roles_permissions.edit')
                                    <flux:button size="xs" variant="subtle" icon="pencil-square" wire:click="editAuthorization({{ $user->id }})" data-guide="auth-users-manage-action">{{ __('Manage') }}</flux:button>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <x-slot:footer>
            <div class="flex justify-end">{{ $users->links() }}</div>
        </x-slot:footer>
    </x-tables.data-panel>

    <flux:modal wire:model="authorizationModalOpen" class="max-w-2xl">
        <form wire:submit="saveAuthorization" class="space-y-5">
            <div class="flex items-start justify-between gap-3"><div><flux:heading size="lg">{{ $editingUserId === null ? __('Create user') : __('User authorization') }}</flux:heading><flux:text class="mt-1 text-text-muted">{{ __('Assign only approved roles and approved branch or store scope.') }}</flux:text></div><flux:button type="button" icon="x-mark" variant="subtle" size="sm" wire:click="closeAuthorization" aria-label="{{ __('Close') }}" /></div>

            <div class="grid gap-4 md:grid-cols-2">
                <flux:input wire:model="userForm.name" :label="__('Name')" required />
                <flux:input wire:model="userForm.email" :label="__('Email')" type="email" required />
                <flux:input wire:model="userForm.password" :label="$editingUserId === null ? __('Password') : __('New password (optional)')" type="password" :required="$editingUserId === null" autocomplete="new-password" />
                <flux:select wire:model="userForm.status" :label="__('Status')">
                    <option value="active">{{ __('Active') }}</option>
                    <option value="inactive">{{ __('Inactive') }}</option>
                </flux:select>
            </div>

            <div class="grid gap-5 md:grid-cols-3">
                <flux:checkbox.group wire:model="roleIds" :label="__('Roles')" class="space-y-2">
                    @foreach ($roles as $role)
                        <flux:checkbox value="{{ $role->id }}" :label="app()->getLocale() === 'ar' ? $role->name_ar : $role->name_en" />
                    @endforeach
                </flux:checkbox.group>
                <flux:checkbox.group wire:model="branchIds" :label="__('Branch scopes')" class="space-y-2">
                    @foreach ($branches as $branch)
                        <flux:checkbox value="{{ $branch->id }}" :label="app()->getLocale() === 'ar' ? $branch->name_ar : $branch->name_en" />
                    @endforeach
                </flux:checkbox.group>
                <flux:checkbox.group wire:model="storeIds" :label="__('Store scopes')" class="space-y-2">
                    @foreach ($stores as $store)
                        <flux:checkbox value="{{ $store->id }}" :label="app()->getLocale() === 'ar' ? $store->name_ar : $store->name_en" />
                    @endforeach
                </flux:checkbox.group>
            </div>

            <div class="flex justify-end gap-2 border-t border-border pt-4">
                <flux:button type="button" variant="subtle" wire:click="closeAuthorization">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary" icon="check" wire:loading.attr="disabled">{{ __('Save authorization') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</x-app.page>
