<?php

use App\Modules\Platform\Actions\SaveRoleAction;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Support\PermissionPresentation;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Role Permissions')] class extends Component
{
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

    public function savePermissions(SaveRoleAction $action): void
    {
        $actor = auth()->user();
        if (! ($actor instanceof User && SaveRoleAction::canManagePermissions($actor, $this->role))) {
            Gate::authorize('users_roles_permissions.edit');
        }
        $validated = $this->validate(['permissionIds' => ['array'], 'permissionIds.*' => ['integer', 'exists:permissions,id']]);

        try {
            $action->syncPermissions($this->role, $validated['permissionIds']);
            $this->role->refresh();
            Flux::toast(variant: 'success', text: __('Role permissions saved successfully.'));
        } catch (\Throwable $exception) {
            $this->addError('permissionIds', \App\Support\UserSafeError::message($exception));
            Flux::toast(variant: 'danger', text: \App\Support\UserSafeError::message($exception));
        }
    }

    public function toggleSection(string $module): void
    {
        $actor = auth()->user();
        $canManageAll = $actor instanceof User && SaveRoleAction::canManagePermissions($actor, $this->role);
        $canEdit = $canManageAll || (Gate::allows('users_roles_permissions.edit') && ! SaveRoleAction::isCanonical($this->role));

        if (! $canEdit) {
            abort(403);
        }

        $query = Permission::query()
            ->where('module', $module)
            ->where('status', 'active');

        if (! $canManageAll) {
            $query->where('sensitivity', '!=', 'sensitive');
        }

        $assignableIds = $query->pluck('id')->map(fn ($id): int => (int) $id)->all();
        abort_if($assignableIds === [], 404);

        $selectedIds = array_values(array_unique(array_map('intval', $this->permissionIds)));
        $allSelected = array_diff($assignableIds, $selectedIds) === [];

        $this->permissionIds = $allSelected
            ? array_values(array_diff($selectedIds, $assignableIds))
            : array_values(array_unique([...$selectedIds, ...$assignableIds]));
    }

    public function render(): mixed
    {
        $actor = auth()->user();
        $canManageAll = $actor instanceof User && SaveRoleAction::canManagePermissions($actor, $this->role);
        $canEdit = $canManageAll || (Gate::allows('users_roles_permissions.edit') && ! SaveRoleAction::isCanonical($this->role));
        $allPermissions = Permission::query()->orderBy('module')->orderBy('action')->get();
        $locale = app()->getLocale();
        $search = mb_strtolower(trim($this->search));
        $permissions = $allPermissions
            ->when($this->moduleFilter !== 'all', fn ($items) => $items->where('module', $this->moduleFilter))
            ->when($search !== '', function ($items) use ($locale, $search) {
                return $items->filter(function (Permission $permission) use ($locale, $search): bool {
                    $section = PermissionPresentation::sectionDetails($permission->module, $locale);
                    $presentation = PermissionPresentation::permission($permission, $locale);

                    return str_contains(mb_strtolower(implode(' ', [
                        $section['name'],
                        $section['description'],
                        $presentation['label'],
                        $presentation['description'],
                    ])), $search);
                });
            });

        $selectedLookup = array_fill_keys(array_map('intval', $this->permissionIds), true);
        $assignableGroups = $canEdit
            ? $allPermissions
                ->filter(fn (Permission $permission): bool => $permission->status === 'active' && ($canManageAll || $permission->sensitivity !== 'sensitive'))
                ->groupBy('module')
            : collect();
        $sectionStates = $permissions->pluck('module')->unique()->mapWithKeys(function (string $module) use ($assignableGroups, $selectedLookup): array {
            $ids = $assignableGroups->get($module, collect())->pluck('id')->map(fn ($id): int => (int) $id);
            $selectedCount = $ids->filter(fn (int $id): bool => isset($selectedLookup[$id]))->count();
            $total = $ids->count();

            return [$module => [
                'checked' => $total > 0 && $selectedCount === $total,
                'disabled' => $total === 0,
                'assignableIds' => $ids->values()->all(),
            ]];
        });

        return view('platform.admin.role-permissions', [
            'permissions' => $permissions,
            'permissionGroups' => $permissions->groupBy('module'),
            'modules' => $allPermissions->pluck('module')->unique()->values(),
            'sectionCounts' => $allPermissions->groupBy('module')->map->count(),
            'sectionStates' => $sectionStates,
            'canonical' => SaveRoleAction::isCanonical($this->role),
            'canEdit' => $canEdit,
            'canAssignSensitive' => $canManageAll,
        ]);
    }
}; ?>

@php
    $isArabic = str_starts_with(app()->getLocale(), 'ar');
    $roleName = $isArabic ? $role->name_ar : $role->name_en;
    $locale = app()->getLocale();
@endphp

<x-app.page :title="$isArabic ? 'صلاحيات الدور' : 'Role permissions'" :description="$isArabic ? 'راجع الصلاحيات الممنوحة لدور '.$roleName.'.' : 'Review the permissions assigned to '.$roleName.'.'" :badge="$roleName" badge-color="primary" max-width="7xl" class="space-y-5">
    <x-slot:actions><x-context-help :title="__('Roles and permissions help')" :label="__('Open roles and permissions help')"><ul><li>{{ __('Permissions define actions. Roles collect those permissions for assignment to users.') }}</li><li>{{ __('Branch, warehouse, and point-of-sale scope is assigned on the user access screen and continues to restrict role permissions.') }}</li><li>{{ __('Sensitive and canonical permission safeguards remain enforced by the server.') }}</li></ul></x-context-help><flux:button variant="subtle" icon="arrow-left" :href="route('admin.roles')" wire:navigate>{{ $isArabic ? 'العودة للأدوار' : 'Back to roles' }}</flux:button></x-slot:actions>

    @if ($canonical && ! $canEdit)
        <div class="flex">
            <flux:badge size="sm" color="zinc" icon="lock-closed">{{ app()->isLocale('ar-EG') ? 'دور أساسي، قراءة بس' : ($isArabic ? 'دور أساسي، قراءة فقط' : 'Canonical role, read-only') }}</flux:badge>
        </div>
    @elseif ($canonical)
        <div class="flex">
            <flux:badge size="sm" color="amber" icon="shield-check">{{ $isArabic ? 'دور أساسي، صلاحياته قابلة للإدارة' : 'Canonical role, permissions manageable' }}</flux:badge>
        </div>
    @elseif (! $canEdit)
        <p class="flex items-center gap-2 text-sm text-text-muted"><flux:icon name="eye" class="size-4" />{{ app()->isLocale('ar-EG') ? 'يمكنك مراجعة الصلاحيات، لكن لا تملك صلاحية تعديلها.' : ($isArabic ? 'يمكنك مراجعة الصلاحيات، لكن لا تملك صلاحية تعديلها.' : 'You can review these permissions, but cannot change them.') }}</p>
    @endif

    <form wire:submit="savePermissions" class="space-y-4">
        <x-tables.data-panel :title="$isArabic ? 'صلاحيات الدور' : 'Permissions'" :description="$canAssignSensitive ? (app()->isLocale('ar-EG') ? 'اختار أي صلاحيات نشطة للدور ده.' : ($isArabic ? 'اختر أي صلاحيات نشطة لهذا الدور.' : 'Choose any active permissions for this role.')) : (app()->isLocale('ar-EG') ? 'اختار الصلاحيات النشطة غير الحساسة المسموح بها للدور ده.' : ($isArabic ? 'اختر الصلاحيات النشطة غير الحساسة المسموح بها لهذا الدور.' : 'Choose the active, non-sensitive permissions allowed for this role.'))">
            <x-slot:toolbar>
                <x-tables.filter-bar>
                    <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :label="app()->isLocale('ar-EG') ? 'دوّر في الصلاحيات' : ($isArabic ? 'بحث في الصلاحيات' : 'Search permissions')" :placeholder="$isArabic ? 'ابحث باسم الصلاحية أو القسم' : 'Permission or section name'" />
                    <flux:select wire:model.live="moduleFilter" :label="$isArabic ? 'القسم' : 'Section'" class="sm:w-72">
                        <option value="all">{{ $isArabic ? 'كل الأقسام' : 'All sections' }}</option>
                        @foreach ($modules as $module)
                            <option value="{{ $module }}">{{ PermissionPresentation::sectionDetails($module, $locale)['name'] }}</option>
                        @endforeach
                    </flux:select>
                </x-tables.filter-bar>
            </x-slot:toolbar>

            @if ($permissions->isEmpty())
                <x-state.empty :title="app()->isLocale('ar-EG') ? 'مفيش صلاحيات' : ($isArabic ? 'لا توجد صلاحيات' : 'No permissions found')" :description="app()->isLocale('ar-EG') ? 'غيّر البحث أو الوحدة لعرض الصلاحيات المتاحة.' : ($isArabic ? 'غيّر البحث أو الوحدة لعرض الصلاحيات المتاحة.' : 'Change the search or module to review the available permissions.')" icon="key" />
            @else
                <div class="space-y-3">
                    @foreach ($permissionGroups as $module => $modulePermissions)
                        @php
                            $section = PermissionPresentation::sectionDetails($module, $locale);
                            $sectionState = $sectionStates[$module];
                        @endphp
                        <details data-permission-section="{{ $module }}" class="rounded-xl border border-border bg-surface" @if ($loop->first) open @endif>
                            <summary class="flex cursor-pointer list-none flex-col items-stretch justify-between gap-3 p-4 marker:content-none sm:flex-row sm:items-center">
                                <div class="min-w-0">
                                    <h2 class="font-semibold text-text-primary">{{ $section['name'] }}</h2>
                                    <p class="mt-1 text-sm text-text-muted">{{ $section['description'] }}</p>
                                </div>
                                <div class="flex shrink-0 items-center justify-between gap-3 sm:justify-end">
                                    <label class="flex items-center gap-2 text-sm font-medium text-text-primary" x-data x-on:click.stop>
                                        <input
                                            type="checkbox"
                                            class="size-4 rounded border-border text-primary focus:ring-primary disabled:cursor-not-allowed disabled:opacity-50"
                                            wire:click.stop="toggleSection('{{ $module }}')"
                                            x-effect="$el.indeterminate = @js($sectionState['assignableIds']).some((id) => $wire.permissionIds.map(Number).includes(id)) && ! @js($sectionState['assignableIds']).every((id) => $wire.permissionIds.map(Number).includes(id))"
                                            x-bind:checked="@js($sectionState['assignableIds']).length > 0 && @js($sectionState['assignableIds']).every((id) => $wire.permissionIds.map(Number).includes(id))"
                                            x-bind:aria-checked="$el.indeterminate ? 'mixed' : ($el.checked ? 'true' : 'false')"
                                            @checked($sectionState['checked'])
                                            @disabled($sectionState['disabled'])
                                        >
                                        <span>{{ $isArabic ? 'تحديد الكل' : 'All' }}</span>
                                    </label>
                                    <flux:badge size="sm" color="zinc">{{ $sectionCounts[$module] }}</flux:badge>
                                </div>
                            </summary>
                            <div class="grid gap-3 border-t border-border p-3 md:grid-cols-2 lg:grid-cols-3">
                                @foreach ($modulePermissions as $permission)
                                    @php
                                        $editable = $canEdit && $permission->status === 'active' && ($canAssignSensitive || $permission->sensitivity !== 'sensitive');
                                        $permissionPresentation = PermissionPresentation::permission($permission, $locale);
                                    @endphp
                                    <article wire:key="role-permission-{{ $role->id }}-{{ $permission->id }}" class="rounded-lg border border-border p-4 transition-colors hover:border-cyan-400/60">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <h3 class="text-base font-medium">{{ $permissionPresentation['label'] }}</h3>
                                                <p class="mt-1 text-sm leading-6 text-text-muted">{{ $permissionPresentation['description'] }}</p>
                                            </div>
                                            <label class="flex shrink-0 items-center gap-2 text-sm font-medium text-text-primary">
                                                <input
                                                    type="checkbox"
                                                    aria-label="{{ $permissionPresentation['label'] }}"
                                                    class="size-4 rounded border-border text-primary focus:ring-primary disabled:cursor-not-allowed disabled:opacity-50"
                                                    wire:key="role-permission-checkbox-{{ $role->id }}-{{ $permission->id }}"
                                                    wire:model.live="permissionIds"
                                                    value="{{ $permission->id }}"
                                                    @checked(in_array((int) $permission->id, array_map('intval', $permissionIds), true))
                                                    @disabled(! $editable)
                                                >
                                                <span>{{ $isArabic ? 'تحديد' : 'Select' }}</span>
                                            </label>
                                        </div>
                                        <div class="mt-3 flex flex-wrap items-center gap-2">
                                            @if ($permission->sensitivity === 'sensitive')
                                                <flux:badge size="sm" color="amber">{{ $isArabic ? 'حساسة' : 'Sensitive' }}</flux:badge>
                                            @else
                                                <flux:badge size="sm" variant="outline">{{ $isArabic ? 'عادية' : 'Standard' }}</flux:badge>
                                            @endif
                                            @if ($permission->status !== 'active')
                                                <flux:badge size="sm" color="zinc">{{ app()->isLocale('ar-EG') ? 'مش نشطة' : ($isArabic ? 'غير نشطة' : 'Inactive') }}</flux:badge>
                                            @endif
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        </details>
                    @endforeach
                </div>
            @endif
        </x-tables.data-panel>

        @error('permissionIds')<flux:callout variant="danger">{{ $message }}</flux:callout>@enderror
        @if ($canEdit)<div class="flex justify-end"><flux:button type="submit" variant="primary" icon="check" wire:loading.attr="disabled" wire:target="savePermissions">{{ app()->isLocale('ar-EG') ? 'احفظ الصلاحيات' : ($isArabic ? 'حفظ الصلاحيات' : 'Save permissions') }}</flux:button></div>@endif
    </form>
</x-app.page>
