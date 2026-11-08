@php $isAr = str_starts_with(app()->getLocale(), 'ar'); @endphp
<x-layouts::app :title="$isAr ? 'الموظفون' : 'Employees'">
    <div class="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6">
        <x-page-header :title="$isAr ? 'الموظفون والمستخدمون' : 'Employees & users'" :description="$isAr ? 'كل من له حساب دخول على النظام: دوره وفروعه وحالته. اضغط على الموظف لعرض التفاصيل.' : 'Everyone with a sign-in: their roles, branches and status. Open an employee for details.'">
            <x-slot:actions>
                @can('manage-global-role-catalog')<flux:button href="{{ route('admin.roles') }}" variant="subtle" icon="shield-check">{{ $isAr ? 'الأدوار والصلاحيات' : 'Roles & permissions' }}</flux:button>@endcan
                @can('branches_stores.view')<flux:button href="{{ route('admin.branches') }}" variant="subtle" icon="building-storefront">{{ $isAr ? 'الفروع' : 'Branches' }}</flux:button>@endcan
            </x-slot:actions>
        </x-page-header>

        <section class="grid gap-3 sm:grid-cols-3" aria-label="{{ $isAr ? 'ملخص الموظفين' : 'Employee summary' }}">
            <div class="rounded-xl border border-border bg-surface p-4"><div class="text-xs text-text-muted">{{ $isAr ? 'إجمالي الموظفين' : 'Total employees' }}</div><div class="mt-1 text-2xl font-black tabular-nums">{{ $totals['all'] }}</div></div>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-900 dark:bg-emerald-950/20"><div class="text-xs text-text-muted">{{ $isAr ? 'نشطون' : 'Active' }}</div><div class="mt-1 text-2xl font-black tabular-nums">{{ $totals['active'] }}</div></div>
            <div class="rounded-xl border border-border bg-surface p-4"><div class="text-xs text-text-muted">{{ $isAr ? 'غير نشطين / موقوفون' : 'Inactive / disabled' }}</div><div class="mt-1 text-2xl font-black tabular-nums">{{ $totals['inactive'] }}</div></div>
        </section>

        <section class="internal-toolbar">
            <form method="GET" action="{{ route('admin.employees') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 lg:items-end">
                <div><label for="emp-q" class="mb-1 block text-sm font-semibold">{{ $isAr ? 'الاسم أو اسم المستخدم أو البريد' : 'Name, username or email' }}</label><input id="emp-q" name="q" value="{{ $term }}" dir="auto" autocomplete="off" class="block h-11 w-full rounded-xl border border-border bg-surface px-4 text-sm shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"></div>
                <div><label for="emp-role" class="mb-1 block text-sm font-semibold">{{ $isAr ? 'الدور' : 'Role' }}</label><select id="emp-role" name="role_id" class="block h-11 w-full rounded-xl border border-border bg-surface px-3 text-sm"><option value="">{{ $isAr ? 'كل الأدوار' : 'All roles' }}</option>@foreach($roles as $role)<option value="{{ $role->id }}" @selected($roleId === $role->id)>{{ $isAr ? $role->name_ar : $role->name_en }}</option>@endforeach</select></div>
                <div><label for="emp-status" class="mb-1 block text-sm font-semibold">{{ $isAr ? 'الحالة' : 'Status' }}</label><select id="emp-status" name="status" class="block h-11 w-full rounded-xl border border-border bg-surface px-3 text-sm"><option value="">{{ $isAr ? 'كل الحالات' : 'All statuses' }}</option><option value="active" @selected($status==='active')>{{ $isAr ? 'نشط' : 'Active' }}</option><option value="disabled" @selected($status==='disabled')>{{ $isAr ? 'موقوف' : 'Disabled' }}</option><option value="inactive" @selected($status==='inactive')>{{ $isAr ? 'غير نشط' : 'Inactive' }}</option></select></div>
                <div class="flex gap-2"><flux:button type="submit" variant="primary" icon="magnifying-glass">{{ __('Search') }}</flux:button>@if($term !== '' || $status !== '' || $roleId > 0)<flux:button href="{{ route('admin.employees') }}" variant="ghost">{{ __('Reset') }}</flux:button>@endif</div>
            </form>
        </section>

        <x-tables.data-panel :title="$isAr ? 'قائمة الموظفين' : 'Employee list'">
            <x-slot:actions><flux:badge size="sm" color="zinc">{{ $employees->total() }} {{ __('records') }}</flux:badge></x-slot:actions>
            <table class="data-table responsive-resource-table min-w-full text-start text-sm">
                <thead><tr><th scope="col">{{ $isAr ? 'الموظف' : 'Employee' }}</th><th scope="col">{{ $isAr ? 'الدور' : 'Roles' }}</th><th scope="col">{{ $isAr ? 'الفروع' : 'Branches' }}</th><th scope="col">{{ $isAr ? 'التحقق بخطوتين' : '2FA' }}</th><th scope="col">{{ __('Status') }}</th><th scope="col" class="text-end">{{ __('Action') }}</th></tr></thead>
                <tbody>
                    @forelse($employees as $employee)
                        <tr>
                            <td data-primary><div class="font-semibold text-text-primary">{{ $employee->name }}</div><div class="mt-1 text-xs text-text-muted" dir="ltr">{{ $employee->username ?: '—' }} · {{ $employee->email }}</div></td>
                            <td data-label="{{ $isAr ? 'الدور' : 'Roles' }}"><div class="flex flex-wrap gap-1">@forelse($employee->roles as $role)<flux:badge size="sm" color="cyan">{{ $isAr ? $role->name_ar : $role->name_en }}</flux:badge>@empty<span class="text-text-muted">—</span>@endforelse @if($employee->is_super_admin)<flux:badge size="sm" color="rose">{{ $isAr ? 'مدير عام' : 'Super admin' }}</flux:badge>@endif</div></td>
                            <td data-label="{{ $isAr ? 'الفروع' : 'Branches' }}">@php $branches = $employee->branchScopes->where('status', 'active')->map(fn ($scope) => $scope->branch ? ($isAr ? $scope->branch->name_ar : $scope->branch->name_en) : null)->filter(); @endphp {{ $branches->isEmpty() ? ($employee->is_super_admin ? ($isAr ? 'كل الفروع' : 'All branches') : '—') : $branches->implode('، ') }}</td>
                            <td data-label="{{ $isAr ? 'التحقق بخطوتين' : '2FA' }}">@if($employee->two_factor_confirmed_at)<flux:badge size="sm" color="green">{{ $isAr ? 'مفعّل' : 'On' }}</flux:badge>@else<span class="text-text-muted">{{ $isAr ? 'غير مفعّل' : 'Off' }}</span>@endif</td>
                            <td data-label="{{ __('Status') }}"><x-status.badge :status="$employee->status" /></td>
                            <td data-label="{{ __('Action') }}" class="text-end"><x-actions.button semantic="view" :label="$isAr ? 'عرض التفاصيل' : 'View details'" :href="route('admin.employees.show', $employee->id)">{{ $isAr ? 'عرض التفاصيل' : 'View details' }}</x-actions.button></td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-state.empty :title="$isAr ? 'لا يوجد موظفون مطابقون.' : 'No matching employees.'" /></td></tr>
                    @endforelse
                </tbody>
            </table>
            <x-slot:footer><x-tables.pagination :paginator="$employees" /></x-slot:footer>
        </x-tables.data-panel>
    </div>
</x-layouts::app>
