@php $isAr = str_starts_with(app()->getLocale(), 'ar'); $fmt = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('Y-m-d H:i') : '—'; @endphp
<x-layouts::app :title="$employee->name">
    <div class="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold tracking-wide text-cyan-700">{{ $isAr ? 'ملف الموظف' : 'Employee profile' }}</p>
                <div class="mt-2 flex flex-wrap items-center gap-3"><flux:heading size="xl">{{ $employee->name }}</flux:heading><x-status.badge :status="$employee->status" />@if($employee->is_super_admin)<flux:badge size="sm" color="rose">{{ $isAr ? 'مدير عام' : 'Super admin' }}</flux:badge>@endif</div>
                <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm text-text-muted" dir="ltr"><span>{{ $employee->username ?: '—' }}</span><span>{{ $employee->email }}</span></div>
            </div>
            <flux:button href="{{ route('admin.employees') }}" variant="subtle" icon="arrow-left">{{ $isAr ? 'رجوع للموظفين' : 'Back to employees' }}</flux:button>
        </div>

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border border-border bg-surface p-4"><div class="text-xs text-text-muted">{{ $isAr ? 'فواتير بيع معتمدة' : 'Approved sales' }}</div><div class="mt-1 text-2xl font-black tabular-nums">{{ number_format($stats['sales_count']) }}</div></div>
            <div class="rounded-xl border border-border bg-surface p-4"><div class="text-xs text-text-muted">{{ $isAr ? 'إجمالي المبيعات' : 'Sales total' }}</div><div class="mt-1 text-2xl font-black tabular-nums" dir="ltr">{{ number_format((float) $stats['sales_total'], 2) }}</div></div>
            <div class="rounded-xl border border-border bg-surface p-4"><div class="text-xs text-text-muted">{{ $isAr ? 'آخر عملية بيع' : 'Last sale' }}</div><div class="mt-1 text-lg font-bold" dir="ltr">{{ $fmt($stats['last_sale_at']) }}</div></div>
            <div class="rounded-xl border border-border bg-surface p-4"><div class="text-xs text-text-muted">{{ $isAr ? 'تاريخ إنشاء الحساب' : 'Account created' }}</div><div class="mt-1 text-lg font-bold" dir="ltr">{{ $fmt($employee->created_at) }}</div></div>
        </section>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <div class="space-y-6">
                <section class="rounded-2xl border border-border bg-surface p-5 shadow-sm">
                    <flux:heading size="lg">{{ $isAr ? 'الأدوار' : 'Roles' }}</flux:heading>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        @forelse($employee->roles as $role)
                            <div class="rounded-xl border border-border p-3"><div class="font-semibold">{{ $isAr ? $role->name_ar : $role->name_en }}</div><div class="mt-1 text-xs text-text-muted">{{ $isAr ? $role->description_ar : $role->description_en }}</div></div>
                        @empty
                            <x-state.empty :title="$isAr ? 'لا يوجد دور مسند.' : 'No role assigned.'" />
                        @endforelse
                    </div>
                </section>

                <section class="rounded-2xl border border-border bg-surface p-5 shadow-sm">
                    <flux:heading size="lg">{{ $isAr ? 'الفروع والمخازن المسموح بها' : 'Allowed branches & stores' }}</flux:heading>
                    <div class="mt-3 grid gap-4 sm:grid-cols-2">
                        <div><p class="mb-2 text-xs font-semibold text-text-muted">{{ $isAr ? 'الفروع' : 'Branches' }}</p><ul class="space-y-1 text-sm">@forelse($employee->branchScopes as $scope)<li class="flex items-center justify-between gap-2"><span>{{ $scope->branch ? ($isAr ? $scope->branch->name_ar : $scope->branch->name_en) : '—' }}</span><x-status.badge :status="$scope->status" /></li>@empty<li class="text-text-muted">{{ $employee->is_super_admin ? ($isAr ? 'كل الفروع' : 'All branches') : '—' }}</li>@endforelse</ul></div>
                        <div><p class="mb-2 text-xs font-semibold text-text-muted">{{ $isAr ? 'المخازن / المبيعات' : 'Stores' }}</p><ul class="space-y-1 text-sm">@forelse($employee->storeScopes as $scope)<li class="flex items-center justify-between gap-2"><span>{{ $scope->store ? ($isAr ? $scope->store->name_ar : $scope->store->name_en) : '—' }}</span><x-status.badge :status="$scope->status" /></li>@empty<li class="text-text-muted">{{ $employee->is_super_admin ? ($isAr ? 'كل المخازن' : 'All stores') : '—' }}</li>@endforelse</ul></div>
                    </div>
                </section>

                <section class="rounded-2xl border border-border bg-surface p-5 shadow-sm">
                    <flux:heading size="lg">{{ $isAr ? 'آخر النشاطات' : 'Recent activity' }}</flux:heading>
                    <div class="mt-3 overflow-x-auto"><table class="data-table min-w-full text-start text-sm"><thead><tr><th>{{ $isAr ? 'الوقت' : 'Time' }}</th><th>{{ $isAr ? 'القسم' : 'Category' }}</th><th>{{ $isAr ? 'الحدث' : 'Event' }}</th></tr></thead><tbody>
                        @forelse($recentActivity as $row)<tr><td class="font-mono text-xs" dir="ltr">{{ $fmt($row->created_at) }}</td><td>{{ $row->category }}</td><td>{{ $row->event }}</td></tr>@empty<tr><td colspan="3"><x-state.empty :title="$isAr ? 'لا يوجد نشاط مسجل.' : 'No recorded activity.'" /></td></tr>@endforelse
                    </tbody></table></div>
                    @can('access-activity-log')<div class="mt-3"><flux:button href="{{ route('admin.audit') }}" variant="subtle" size="sm" icon="clock">{{ $isAr ? 'سجل النشاط الكامل' : 'Full activity log' }}</flux:button></div>@endcan
                </section>
            </div>

            <aside class="space-y-6">
                <section class="rounded-2xl border border-border bg-surface p-5 shadow-sm">
                    <flux:heading size="lg">{{ $isAr ? 'الأمان' : 'Security' }}</flux:heading>
                    <dl class="mt-3 space-y-3 text-sm">
                        <div class="flex justify-between gap-3"><dt class="text-text-muted">{{ $isAr ? 'التحقق بخطوتين' : 'Two-factor' }}</dt><dd>{{ $employee->two_factor_confirmed_at ? ($isAr ? 'مفعّل' : 'On') : ($isAr ? 'غير مفعّل' : 'Off') }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-text-muted">{{ $isAr ? 'تأكيد البريد' : 'Email verified' }}</dt><dd>{{ $employee->email_verified_at ? ($isAr ? 'نعم' : 'Yes') : ($isAr ? 'لا' : 'No') }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-text-muted">{{ $isAr ? 'آخر تحديث للحساب' : 'Last updated' }}</dt><dd dir="ltr">{{ $fmt($employee->updated_at) }}</dd></div>
                    </dl>
                </section>
            </aside>
        </div>
    </div>
</x-layouts::app>
