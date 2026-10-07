<?php

use App\Models\User;
use App\Modules\Platform\Actions\AuditLogValueRedactor;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Support\AuditLogPresentation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Audit Logs')] class extends Component
{
    use \Livewire\WithPagination;

    public string $search = '';

    public string $category = '';

    public string $event = '';

    public string $actorId = '';

    public string $branchId = '';

    public string $storeId = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    #[Url]
    public string $mode = 'all';

    public ?int $selectedAuditId = null;

    public function mount(): void
    {
        Gate::authorize('access-activity-log');
        abort_unless(in_array($this->mode, ['all', 'override', 'print'], true), 404);
    }

    public function hydrate(): void
    {
        Gate::authorize('access-activity-log');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedCategory(): void
    {
        $this->resetPage();
    }

    public function updatedEvent(): void
    {
        $this->resetPage();
    }

    public function updatedActorId(): void
    {
        $this->resetPage();
    }

    public function updatedBranchId(): void
    {
        $this->resetPage();
    }

    public function updatedStoreId(): void
    {
        $this->resetPage();
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    public function showAudit(int $auditId): void
    {
        Gate::authorize('access-activity-log');

        $auditLog = AuditLog::query()->findOrFail($auditId);

        Gate::authorize('view', $auditLog);
        $this->selectedAuditId = $auditLog->id;
    }

    public function closeAudit(): void
    {
        $this->selectedAuditId = null;
    }

    #[Computed]
    public function screen(): array
    {
        Gate::authorize('access-activity-log');
        $user = auth()->user();
        $baseQuery = AuditLog::query()->visibleTo($user);
        $logs = $this->applyFilters(clone $baseQuery)
            ->with('actor:id,name')
            ->latest('id')
            ->paginate(20);

        $selectedAudit = $this->selectedAuditId === null
            ? null
            : AuditLog::query()->visibleTo($user)->find($this->selectedAuditId);

        if ($selectedAudit !== null) {
            Gate::authorize('view', $selectedAudit);
        }

        $redactor = app(AuditLogValueRedactor::class);
        $presentation = app(AuditLogPresentation::class);

        return [
            'logs' => $logs,
            'categories' => (clone $baseQuery)->distinct()->orderBy('category')->pluck('category'),
            'events' => (clone $baseQuery)->distinct()->orderBy('event')->pluck('event'),
            'actors' => User::query()
                ->whereIn('id', (clone $baseQuery)->whereNotNull('actor_id')->select('actor_id'))
                ->orderBy('name')
                ->get(['id', 'name']),
            'branches' => Branch::query()->visibleTo($user)->orderBy('code')->get(['id', 'code', 'name_ar', 'name_en']),
            'stores' => Store::query()->visibleTo($user)->orderBy('code')->get(['id', 'branch_id', 'code', 'name_ar', 'name_en']),
            'selectedAudit' => $selectedAudit,
            'selectedBeforeValues' => $selectedAudit === null ? null : $redactor->redactForViewer($selectedAudit->before_values, $user),
            'selectedAfterValues' => $selectedAudit === null ? null : $redactor->redactForViewer($selectedAudit->after_values, $user),
            'selectedDeviceFingerprint' => $selectedAudit === null ? null : $presentation->fingerprint($selectedAudit->metadata['device_hash'] ?? null),
            'selectedSessionFingerprint' => $selectedAudit === null ? null : $presentation->fingerprint($selectedAudit->metadata['session_hash'] ?? null),
            'canExport' => Gate::allows('export', AuditLog::class),
            'exportConfigured' => filter_var(config('audit.export_max_rows'), FILTER_VALIDATE_INT) !== false && (int) config('audit.export_max_rows') > 0,
            'presentation' => $presentation,
            'exportUrl' => route('admin.audit.export', array_filter([
                'mode' => $this->mode === 'all' ? null : $this->mode,
                'search' => $this->search,
                'category' => $this->category,
                'event' => $this->event,
                'actor_id' => $this->actorId,
                'branch_id' => $this->branchId,
                'store_id' => $this->storeId,
                'date_from' => $this->dateFrom,
                'date_to' => $this->dateTo,
            ], fn ($value) => $value !== '')),
        ];
    }

    public function render()
    {
        Gate::authorize('access-activity-log');

        return view('platform.system.audit-log');
    }

    private function applyFilters(Builder $query): Builder
    {
        return $query
            ->when($this->mode === 'print', fn (Builder $query) => $query->where('event', 'like', '%print%'))
            ->when($this->mode === 'override', fn (Builder $query) => $query->where(function (Builder $mode): void {
                $mode->where('event', 'like', '%override%')->orWhereJsonContains('metadata->override', true);
            }))
            ->when($this->search !== '', function (Builder $query): void {
                $search = '%'.trim($this->search).'%';
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery->where('event', 'like', $search)
                        ->orWhere('source_type', 'like', $search)
                        ->orWhere('source_id', 'like', $search)
                        ->orWhere('request_id', 'like', $search)
                        ->orWhere('actor_name', 'like', $search);
                });
            })
            ->when($this->category !== '', fn (Builder $query) => $query->where('category', $this->category))
            ->when($this->event !== '', fn (Builder $query) => $query->where('event', $this->event))
            ->when($this->actorId !== '', fn (Builder $query) => $query->where('actor_id', (int) $this->actorId))
            ->when($this->branchId !== '', fn (Builder $query) => $query->where('branch_id', (int) $this->branchId))
            ->when($this->storeId !== '', fn (Builder $query) => $query->where('store_id', (int) $this->storeId))
            ->when(preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->dateFrom) === 1, fn (Builder $query) => $query->whereDate('created_at', '>=', $this->dateFrom))
            ->when(preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->dateTo) === 1, fn (Builder $query) => $query->whereDate('created_at', '<=', $this->dateTo));
    }
}; ?>

<x-app.page
    :title="$mode === 'override' ? __('Override log') : ($mode === 'print' ? __('Print log') : __('Audit logs'))"
    :description="__('Append-only, permission-scoped operational history with protected before and after values.')"
    max-width="7xl"
    class="space-y-5"
    data-guide="audit-header"
>
    @island(name: 'audit-table')
    @if($this->screen['canExport'])
        <div class="flex flex-wrap items-center justify-end gap-3">
            @if($this->screen['exportConfigured'])
                <flux:button :href="$this->screen['exportUrl']" icon="arrow-down-tray">{{ __('Export filtered audit log') }}</flux:button>
            @else
                <flux:callout class="min-w-0 w-full" variant="warning" icon="exclamation-triangle">{{ __('Audit export is unavailable until an administrator approves and configures a positive export row limit.') }}</flux:callout>
            @endif
        </div>
    @endif
    <x-tables.data-panel :title="__('Audit events')" :description="__('Filter visible history by event, source, scope, actor, or request ID.')" data-guide="audit-table">
        <x-slot:toolbar>
            <x-tables.filter-bar data-guide="audit-filters">
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <flux:input wire:model.live.debounce.400ms="search" :label="__('Search')" icon="magnifying-glass" :placeholder="__('Event, source, actor, or request ID')" />
                    <flux:select wire:model.live="category" :label="__('Category')">
                        <option value="">{{ __('All categories') }}</option>
                        @foreach ($this->screen['categories'] as $option)
                            <option value="{{ $option }}">{{ $this->screen['presentation']->category($option) }}</option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model.live="event" :label="__('Event')">
                        <option value="">{{ __('All events') }}</option>
                        @foreach ($this->screen['events'] as $option)
                            <option value="{{ $option }}">{{ $this->screen['presentation']->event($option) }}</option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model.live="actorId" :label="__('Actor')">
                        <option value="">{{ __('All actors') }}</option>
                        @foreach ($this->screen['actors'] as $actor)
                            <option value="{{ $actor->id }}">{{ $actor->name }}</option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model.live="branchId" :label="__('Branch')">
                        <option value="">{{ __('All visible branches') }}</option>
                        @foreach ($this->screen['branches'] as $branch)
                            <option value="{{ $branch->id }}">{{ $branch->code }} - {{ str_starts_with(app()->getLocale(), 'ar') ? $branch->name_ar : $branch->name_en }}</option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model.live="storeId" :label="__('Store')">
                        <option value="">{{ __('All visible stores') }}</option>
                        @foreach ($this->screen['stores'] as $store)
                            <option value="{{ $store->id }}">{{ $store->code }} - {{ str_starts_with(app()->getLocale(), 'ar') ? $store->name_ar : $store->name_en }}</option>
                        @endforeach
                    </flux:select>
                    <flux:input wire:model.live="dateFrom" type="text" inputmode="numeric" placeholder="YYYY-MM-DD" dir="ltr" :label="__('From date')" />
                    <flux:input wire:model.live="dateTo" type="text" inputmode="numeric" placeholder="YYYY-MM-DD" dir="ltr" :label="__('To date')" />
                </div>
            </x-tables.filter-bar>
        </x-slot:toolbar>

        @if ($this->screen['logs']->isEmpty())
            <x-state.empty :title="__('No audit events found')" :description="__('No visible events match the current filters.')" icon="clock" />
        @else
        <div class="hidden max-w-full overflow-x-auto sm:block">
            <flux:table class="min-w-[64rem]" aria-label="{{ __('Audit events') }}">
                <flux:table.columns>
                    <flux:table.column>{{ __('Time') }}</flux:table.column>
                    <flux:table.column>{{ __('Event') }}</flux:table.column>
                    <flux:table.column>{{ __('Actor') }}</flux:table.column>
                    <flux:table.column>{{ __('Source') }}</flux:table.column>
                    <flux:table.column>{{ __('Scope') }}</flux:table.column>
                    <flux:table.column>{{ __('Request ID') }}</flux:table.column>
                    <flux:table.column>{{ __('Actions') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->screen['logs'] as $log)
                        <flux:table.row key="audit-{{ $log->id }}" class="data-table-row">
                            <flux:table.cell class="whitespace-nowrap font-mono text-xs">{{ $log->created_at->format('Y-m-d H:i:s') }}</flux:table.cell>
                            <flux:table.cell><flux:badge size="sm" color="zinc">{{ $this->screen['presentation']->event($log->event) }}</flux:badge></flux:table.cell>
                            <flux:table.cell>{{ $log->actor_name ?? __('System') }}</flux:table.cell>
                            <flux:table.cell><span>{{ $this->screen['presentation']->source($log->source_type) }}</span>@if($log->source_id)<span class="ms-1 font-mono text-xs text-text-muted" dir="ltr">#{{ $log->source_id }}</span>@endif</flux:table.cell>
                            <flux:table.cell class="font-mono text-xs">
                                @if ($log->branch_id)
                                    <span>{{ __('Branch').' #'.$log->branch_id }}</span>
                                @endif
                                @if ($log->store_id)
                                    <span class="ms-2">{{ __('Store').' #'.$log->store_id }}</span>
                                @endif
                                @if (! $log->branch_id && ! $log->store_id)
                                    {{ __('Global') }}
                                @endif
                            </flux:table.cell>
                            <flux:table.cell class="max-w-40 truncate font-mono text-xs" title="{{ $log->request_id }}">{{ $log->request_id }}</flux:table.cell>
                            <flux:table.cell><x-actions.button semantic="view" :label="__('View')" size="xs" wire:click="showAudit({{ $log->id }})" wire:island="audit-details" data-guide="audit-view-action">{{ __('View') }}</x-actions.button></flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>

        <div class="space-y-3 sm:hidden">
            @foreach ($this->screen['logs'] as $log)
                <x-cards.section-card class="space-y-3" :title="$this->screen['presentation']->event($log->event)">
                    <div class="flex items-center justify-between gap-3">
                        <flux:badge size="sm" color="zinc">{{ $this->screen['presentation']->event($log->event) }}</flux:badge>
                        <span class="whitespace-nowrap font-mono text-xs text-text-muted">{{ $log->created_at->format('Y-m-d H:i') }}</span>
                    </div>
                    <dl class="grid gap-2 text-sm">
                        <div><dt class="text-text-muted">{{ __('Actor') }}</dt><dd>{{ $log->actor_name ?? __('System') }}</dd></div>
                        <div><dt class="text-text-muted">{{ __('Source') }}</dt><dd>{{ $this->screen['presentation']->source($log->source_type) }}@if($log->source_id)<span class="ms-1 font-mono text-xs" dir="ltr">#{{ $log->source_id }}</span>@endif</dd></div>
                        <div><dt class="text-text-muted">{{ __('Scope') }}</dt><dd>
                            @if ($log->branch_id)
                                <span>{{ __('Branch').' #'.$log->branch_id }}</span>
                            @endif
                            @if ($log->store_id)
                                <span class="ms-2">{{ __('Store').' #'.$log->store_id }}</span>
                            @endif
                            @if (! $log->branch_id && ! $log->store_id)
                                {{ __('Global') }}
                            @endif
                        </dd></div>
                    </dl>
                    <x-actions.button semantic="view" :label="__('View')" wire:click="showAudit({{ $log->id }})" wire:island="audit-details">{{ __('View') }}</x-actions.button>
                </x-cards.section-card>
            @endforeach
        </div>
        @endif

        <x-slot:footer>
            <div class="flex items-center justify-between gap-3 sm:hidden">
                @if ($this->screen['logs']->onFirstPage())
                    <flux:button size="sm" variant="subtle" disabled>{{ __('Previous') }}</flux:button>
                @else
                    <flux:button size="sm" variant="subtle" wire:click="previousPage">{{ __('Previous') }}</flux:button>
                @endif

                <span class="text-sm text-text-muted">{{ __('Page :page', ['page' => $this->screen['logs']->currentPage()]) }}</span>

                @if ($this->screen['logs']->hasMorePages())
                    <flux:button size="sm" variant="subtle" wire:click="nextPage">{{ __('Next') }}</flux:button>
                @else
                    <flux:button size="sm" variant="subtle" disabled>{{ __('Next') }}</flux:button>
                @endif
            </div>
            <div class="hidden sm:block" data-guide="audit-pagination">{{ $this->screen['logs']->links() }}</div>
        </x-slot:footer>
    </x-tables.data-panel>
    @endisland

    @island(name: 'audit-details', always: true)
    <flux:modal wire:model="selectedAuditId" class="max-w-3xl">
        @if ($this->screen['selectedAudit'])
            <div class="space-y-5">
                <div class="flex items-start justify-between gap-3">
                    <div><flux:heading size="lg">{{ __('Audit event details') }}</flux:heading><flux:text class="mt-1 text-text-muted">{{ $this->screen['selectedAudit']->event_id }}</flux:text></div>
                    <flux:button type="button" icon="x-mark" variant="subtle" size="sm" wire:click="closeAudit" aria-label="{{ __('Close') }}" />
                </div>
                <x-audit-panel :created-by-name="$this->screen['selectedAudit']->actor_name" :created-at="$this->screen['selectedAudit']->created_at->format('Y-m-d H:i:s')" :request-id="$this->screen['selectedAudit']->request_id">
                    <div class="grid gap-3 text-sm sm:grid-cols-2">
                        <div><span class="text-text-muted">{{ __('Event') }}</span><p>{{ $this->screen['presentation']->event($this->screen['selectedAudit']->event) }}</p></div>
                        <div><span class="text-text-muted">{{ __('Category') }}</span><p>{{ $this->screen['presentation']->category($this->screen['selectedAudit']->category) }}</p></div>
                        <div><span class="text-text-muted">{{ __('Source') }}</span><p>{{ $this->screen['presentation']->source($this->screen['selectedAudit']->source_type) }}</p></div>
                        <div><span class="text-text-muted">{{ __('Source record ID') }}</span><p class="font-mono text-xs" dir="ltr">{{ $this->screen['selectedAudit']->source_id ?: '—' }}</p></div>
                        @if ($this->screen['selectedDeviceFingerprint'])
                            <div><span class="text-text-muted">{{ __('Device fingerprint') }}</span><p class="font-mono text-xs" dir="ltr">{{ $this->screen['selectedDeviceFingerprint'] }}</p></div>
                        @endif
                        @if ($this->screen['selectedSessionFingerprint'])
                            <div><span class="text-text-muted">{{ __('Session fingerprint') }}</span><p class="font-mono text-xs" dir="ltr">{{ $this->screen['selectedSessionFingerprint'] }}</p></div>
                        @endif
                        @if ($this->screen['selectedAudit']->reason_code || $this->screen['selectedAudit']->reason_text)
                            <div class="sm:col-span-2"><span class="text-text-muted">{{ __('Reason') }}</span><p>{{ trim(($this->screen['selectedAudit']->reason_code ? $this->screen['selectedAudit']->reason_code.': ' : '').$this->screen['selectedAudit']->reason_text) }}</p></div>
                        @endif
                    </div>
                </x-audit-panel>
                <div class="grid gap-4 lg:grid-cols-2">
                    <x-cards.section-card :title="__('Before')"><pre class="overflow-x-auto text-xs">{{ json_encode($this->screen['selectedBeforeValues'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></x-cards.section-card>
                    <x-cards.section-card :title="__('After')"><pre class="overflow-x-auto text-xs">{{ json_encode($this->screen['selectedAfterValues'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></x-cards.section-card>
                </div>
            </div>
        @endif
    </flux:modal>
    @endisland
</x-app.page>
