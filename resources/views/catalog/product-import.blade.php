<?php

use App\Models\User;
use App\Modules\Catalog\Actions\SaveProductAction;
use App\Modules\Catalog\Actions\StageProductImportAction;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\ProductImportBatch;
use App\Modules\Platform\Actions\RevokeAttachment;
use App\Modules\Platform\Actions\StoreAttachment;
use App\Modules\Platform\Models\Attachment;
use App\Support\UserSafeError;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Product Import')] class extends Component
{
    use WithFileUploads;

    public mixed $importFile = null;

    public string $mode = 'create_only';

    /** @var array<string, string> */
    public array $columnMapping = [];

    #[Url(as: 'batch')]
    public ?int $selectedBatchId = null;

    public function mount(): void
    {
        abort_unless(Gate::allows('products_categories_brands.create') || Gate::allows('products_categories_brands.edit'), 403);
        if (! Gate::allows('products_categories_brands.create')) {
            $this->mode = 'update_existing';
        }
    }

    public function stage(StageProductImportAction $action): void
    {
        Gate::authorize($this->mode === 'update_existing' ? 'products_categories_brands.edit' : 'products_categories_brands.create');
        $this->validate([
            'importFile' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
            'mode' => ['required', 'in:create_only,update_existing'],
        ], [
            'importFile.mimes' => __('Only the current company-specific XLSX template is supported.'),
        ]);

        try {
            $attachment = app(StoreAttachment::class)->execute($this->importFile, 'import_source');
            try {
                $batch = $action->stage($attachment, $this->importFile->getClientOriginalName(), $this->mode, auth()->id());
            } catch (Throwable $exception) {
                app(RevokeAttachment::class)->execute(
                    $attachment,
                    __('The product import could not be staged.'),
                    fn (User $user, Attachment $candidate): bool => $candidate->uploaded_by === $user->id && $candidate->source_type === null,
                );
                throw $exception;
            }
            $this->selectedBatchId = $batch->id;
            $this->columnMapping = $this->defaultColumnMapping($batch->headers ?? []);
            $this->importFile = null;
            Flux::toast(variant: 'success', text: __('File staged. Review all rows before approval.'));
        } catch (Throwable $exception) {
            UserSafeError::message($exception);
            $message = __('The workbook could not be staged. Download the current company-specific XLSX template, keep its Instructions and Data Entry sheets unchanged, and retry.');
            $this->addError('importFile', $message);
            Flux::toast(variant: 'danger', text: $message);
        }
    }

    public function mapColumns(StageProductImportAction $action): void
    {
        abort_unless($this->selectedBatchId !== null, 404);
        $batch = ProductImportBatch::query()->where('created_by', auth()->id())->findOrFail($this->selectedBatchId);
        Gate::authorize($batch->mode === 'update_existing' ? 'products_categories_brands.edit' : 'products_categories_brands.create');

        try {
            $action->applyMapping($batch, $this->columnMapping);
            Flux::toast(variant: 'success', text: __('Columns mapped and rows validated. Review rejected rows before approving valid rows.'));
        } catch (InvalidArgumentException $exception) {
            $this->addError('columnMapping', $exception->getMessage());
        } catch (Throwable $exception) {
            Flux::toast(variant: 'danger', text: UserSafeError::message($exception));
        }
    }

    public function approve(StageProductImportAction $action): void
    {
        Gate::authorize('products_categories_brands.approve');
        $batch = ProductImportBatch::query()->findOrFail($this->selectedBatchId);

        try {
            $action->approve($batch, app(SaveProductAction::class));
            Flux::toast(variant: 'success', text: __('Import approved and valid rows were written.'));
        } catch (Throwable $exception) {
            Flux::toast(variant: 'danger', text: UserSafeError::message($exception));
        }
    }

    public function cancelBatch(StageProductImportAction $action): void
    {
        $batch = ProductImportBatch::query()->where('created_by', auth()->id())->findOrFail($this->selectedBatchId);
        Gate::authorize($batch->mode === 'update_existing' ? 'products_categories_brands.edit' : 'products_categories_brands.create');

        try {
            $action->cancel($batch);
            Flux::toast(variant: 'success', text: __('Import cancelled. You can upload the same file again.'));
        } catch (Throwable $exception) {
            Flux::toast(variant: 'danger', text: UserSafeError::message($exception));
        }
    }

    public function selectBatch(int $batchId): void
    {
        Gate::authorize('products_categories_brands.view');
        $canReview = Gate::allows('products_categories_brands.approve');
        $batch = ProductImportBatch::query()->where(function ($query) use ($canReview): void {
            $query->where('created_by', auth()->id());
            if ($canReview) {
                $query->orWhere('status', 'ready_for_review');
            }
        })->findOrFail($batchId);
        $this->selectedBatchId = $batch->id;
        $this->columnMapping = $batch->created_by === auth()->id() && $batch->status === 'mapping_required'
            ? $this->defaultColumnMapping($batch->headers ?? [])
            : [];
    }

    public function render()
    {
        $canReview = Gate::allows('products_categories_brands.approve');
        $batches = ProductImportBatch::query()
            ->where(function ($query) use ($canReview): void {
                $query->where('created_by', auth()->id());
                if ($canReview) {
                    $query->orWhere('status', 'ready_for_review');
                }
            })
            ->latest()
            ->paginate(10, pageName: 'imports');

        $selectedBatch = $this->selectedBatchId
            ? ProductImportBatch::query()->where(function ($query) use ($canReview): void {
                $query->where('created_by', auth()->id());
                if ($canReview) {
                    $query->orWhere('status', 'ready_for_review');
                }
            })->with(['rows' => fn ($query) => $query->orderBy('row_number')->limit(50)])->find($this->selectedBatchId)
            : null;

        abort_if($this->selectedBatchId !== null && $selectedBatch === null, 404);

        $sourceAttachment = $selectedBatch === null ? null : Attachment::query()
            ->where('source_type', ProductImportBatch::class)
            ->where('source_id', (string) $selectedBatch->id)
            ->where('purpose', 'import_source')
            ->first();

        return view('catalog.product-import', [
            'batches' => $batches,
            'selectedBatch' => $selectedBatch,
            'sourceAttachment' => $sourceAttachment,
            'supportedFields' => StageProductImportAction::supportedFields(),
        ]);
    }

    /** @param array<int, string> $headers @return array<string, string> */
    private function defaultColumnMapping(array $headers): array
    {
        $fields = StageProductImportAction::supportedFields();
        $mapping = [];
        foreach ($headers as $header) {
            $mapping[$header] = in_array($header, $fields, true) ? $header : '';
        }

        return $mapping;
    }
};
?>

<x-app.page
    :title="__('Product Import')"
    :description="__('Stage, map, validate, review, and approve product spreadsheet rows without writing invalid data.')"
    max-width="7xl"
    class="space-y-6"
    data-guide="import-header"
>
    <x-slot:actions>
        <flux:button href="{{ route('catalog.products') }}" variant="subtle" icon="arrow-left" wire:navigate>{{ __('Back to products') }}</flux:button>
        @can('products_categories_brands.create')
            <flux:button href="{{ route('catalog.products.create') }}" variant="primary" icon="plus" wire:navigate>{{ __('Manual entry') }}</flux:button>
        @endcan
    </x-slot:actions>

    <x-context-help :title="__('Two ways to add products')" :label="__('Open contextual help')">
        <div class="space-y-3">
            <p>{{ __('Manual entry is best for one product. Excel import is for a reviewed batch: upload, validate, review errors, then approve. Nothing is written to products during staging.') }}</p>
            <div class="flex flex-wrap gap-2">
                @can('products_categories_brands.create')
                    <flux:button href="{{ route('catalog.products.create') }}" variant="subtle" icon="plus" wire:navigate>{{ __('Use manual entry') }}</flux:button>
                @endcan
                <flux:button href="{{ route('catalog.products.import.staged-template.xlsx') }}" variant="subtle" icon="arrow-down-tray">{{ __('Download spreadsheet template') }}</flux:button>
            </div>
        </div>
    </x-context-help>

    @if (! Category::query()->active()->exists())
        <flux:callout variant="warning" icon="exclamation-triangle" title="{{ __('Import needs an active category') }}">
            <div class="space-y-2">
                <p>{{ __('Every imported row needs a category_code that matches an active category. Create the category hierarchy before staging a product batch.') }}</p>
                @can('products_categories_brands.create')
                    <flux:button href="{{ route('catalog.categories') }}" variant="subtle" icon="arrow-top-right-on-square" wire:navigate>{{ __('Configure categories') }}</flux:button>
                @endcan
            </div>
        </flux:callout>
    @endif

    <flux:card class="space-y-5 rounded-2xl border border-cyan-200/20 bg-slate-900/30 p-4 sm:p-6" data-guide="import-upload-section">
        <div class="space-y-2">
            <div class="flex flex-wrap items-center gap-2">
                <flux:heading size="lg">{{ str_starts_with(app()->getLocale(), 'ar') ? 'استيراد المنتجات' : __('Excel import — 1. Upload and stage') }}</flux:heading>
                <span class="rounded-full bg-cyan-400/15 px-2.5 py-1 text-xs font-medium text-cyan-200">{{ str_starts_with(app()->getLocale(), 'ar') ? 'الخطوة ١' : 'Step 1' }}</span>
            </div>
            @if (str_starts_with(app()->getLocale(), 'ar'))
                <flux:text>ارفع قالب Excel الحالي الخاص بالشركة لتجهيز المنتجات ومراجعتها قبل اعتمادها وحفظها.</flux:text>
                <div class="grid gap-3 pt-2 md:grid-cols-2">
                    <div class="rounded-xl border border-cyan-200/15 bg-slate-950/20 p-4">
                        <p class="text-sm font-semibold text-white">الأعمدة المطلوبة</p>
                        <p class="mt-1 text-sm text-text-muted">يجب أن يحتوي الملف على:</p>
                        <div class="mt-3 flex flex-wrap gap-2" dir="ltr">
                            @foreach (['name_ar', 'name_en', 'category_code'] as $column)
                                <code class="rounded-md bg-slate-950/60 px-2 py-1 text-xs text-cyan-200">{{ $column }}</code>
                            @endforeach
                        </div>
                    </div>
                    <div class="rounded-xl border border-white/10 bg-slate-950/20 p-4">
                        <p class="text-sm font-semibold text-white">بيانات اختيارية</p>
                        <p class="mt-1 text-sm leading-6 text-text-muted">يمكن إضافة العلامة التجارية والمورد والفئة العمرية والشخصية واللون والجنس. تُراجع الأسعار والأبعاد والوزن والبطارية قبل الاعتماد.</p>
                    </div>
                </div>
            @else
                <flux:text>{{ __('Required columns: name_ar, name_en, category_code. item_code is optional for new products when preferred_supplier_code resolves to an active supplier. Other optional lookup codes include brand, age_codes, character_codes, colour_codes, and gender_codes (comma-separated). Prices, dimensions, weight, and battery fields are validated before approval; formula cells are rejected.') }}</flux:text>
            @endif
        </div>

        <form wire:submit="stage" class="grid gap-4 md:grid-cols-3">
            <div class="md:col-span-2 space-y-2">
                <flux:label>{{ __('Company-specific Excel template (.xlsx)') }}</flux:label>
                <flux:input type="file" wire:model="importFile" accept=".xlsx" required />
                @error('importFile') <flux:text class="text-red-600">{{ $message }}</flux:text> @enderror
            </div>
            <div class="space-y-2" data-guide="import-mode-select">
                <flux:label>{{ __('Import mode') }}</flux:label>
                <flux:select wire:model="mode">
                    @can('products_categories_brands.create')
                        <flux:select.option value="create_only">{{ __('Create Only') }}</flux:select.option>
                    @else
                        <flux:select.option value="create_only" disabled>{{ __('Create Only (create permission required)') }}</flux:select.option>
                    @endcan
                    @can('products_categories_brands.edit')
                        <flux:select.option value="update_existing">{{ __('Update Existing') }}</flux:select.option>
                    @else
                        <flux:select.option value="update_existing" disabled>{{ __('Update Existing (edit permission required)') }}</flux:select.option>
                    @endcan
                </flux:select>
                @cannot('products_categories_brands.edit')
                    <flux:text class="text-xs text-text-muted">{{ __('Update Existing requires product edit permission. Create Only is available for your role.') }}</flux:text>
                @endcannot
            </div>
            <div class="md:col-span-3 flex items-center justify-between gap-3">
                    <flux:text>{{ __('Nothing is saved until the import is confirmed.') }}</flux:text>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="stage,importFile" data-guide="import-stage-button"><span wire:loading.remove wire:target="stage">{{ __('Stage file') }}</span><span wire:loading wire:target="stage">{{ __('Staging...') }}</span></flux:button>
            </div>
        </form>
    </flux:card>

    <flux:card data-guide="import-batches-section">
        <flux:heading size="lg">{{ __('2. Staged batches') }}</flux:heading>
        <div class="mt-4">
            @if ($batches->isEmpty())
                <x-state.empty :title="__('No staged product imports yet')" :description="__('No import batch exists in your authorized scope. Upload a spreadsheet above, or use Manual entry for one product.')" icon="arrow-up-tray">
                    @can('products_categories_brands.create')
                        <flux:button href="#import-upload-section" variant="primary" icon="arrow-up-tray">{{ __('Upload a spreadsheet') }}</flux:button>
                    @endcan
                </x-state.empty>
            @else
                <div class="overflow-x-auto">
                <flux:table aria-label="{{ __('Staged product imports') }}">
                <flux:table.columns>
                    <flux:table.column>{{ __('File') }}</flux:table.column>
                    <flux:table.column>{{ __('Mode') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column>{{ __('Rows') }}</flux:table.column>
                    <flux:table.column>{{ __('Action') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($batches as $batch)
                        <flux:table.row :key="$batch->id">
                            <flux:table.cell>{{ $batch->original_filename }}</flux:table.cell>
                            <flux:table.cell>{{ $batch->mode === 'create_only' ? __('Create Only') : __('Update Existing') }}</flux:table.cell>
                            <flux:table.cell><x-status.badge :status="$batch->status" /></flux:table.cell>
                            <flux:table.cell>{{ $batch->valid_rows }} / {{ $batch->total_rows }} {{ __('valid') }}</flux:table.cell>
                            <flux:table.cell><flux:button size="sm" variant="subtle" wire:click="selectBatch({{ $batch->id }})">{{ __('Review') }}</flux:button></flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
                </div>
                <div class="mt-4">{{ $batches->links() }}</div>
            @endif
        </div>
    </flux:card>

    @if ($selectedBatch)
        <flux:card class="space-y-4" data-guide="import-review-section">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <flux:heading size="lg">{{ __('3. Review batch') }}: {{ $selectedBatch->original_filename }}</flux:heading>
                    <flux:text>{{ __('Valid') }}: {{ $selectedBatch->valid_rows }} · {{ __('Rejected') }}: {{ $selectedBatch->invalid_rows }}</flux:text>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    @if($sourceAttachment?->status->isDeliverable())
                        <flux:button href="{{ route('catalog.products.import.source', [$selectedBatch, $sourceAttachment]) }}" variant="subtle" icon="arrow-down-tray">{{ __('Download source') }}</flux:button>
                    @endif
                    @can('products_categories_brands.export')
                        @if ($selectedBatch->invalid_rows > 0)
                            <flux:button href="{{ route('catalog.products.import.errors', $selectedBatch) }}" variant="subtle" icon="arrow-down-tray">{{ __('Download errors') }}</flux:button>
                        @endif
                    @endcan
                    @if (in_array($selectedBatch->status, ['staging', 'mapping_required', 'ready_for_review'], true))
                        <x-actions.button semantic="cancel" :label="__('Cancel batch')" variant="danger" wire:click="cancelBatch" wire:loading.attr="disabled" wire:target="cancelBatch">{{ __('Cancel batch') }}</x-actions.button>
                    @endif
                    @can('products_categories_brands.approve')
                        <flux:button variant="primary" wire:click="approve" wire:loading.attr="disabled" wire:target="approve" :disabled="$selectedBatch->status !== 'ready_for_review' || $selectedBatch->valid_rows < 1" data-guide="import-approve-button"><span wire:loading.remove wire:target="approve">{{ __('Approve valid rows') }}</span><span wire:loading wire:target="approve">{{ __('Approving...') }}</span></flux:button>
                    @endcan
                </div>
            </div>

            @cannot('products_categories_brands.approve')
                @if ($selectedBatch->status === 'ready_for_review')
                    <flux:callout variant="info" icon="lock-closed">{{ __('This batch is ready for review, but approval requires an authorized catalog approver.') }}</flux:callout>
                @endif
            @endcannot

            @if ($selectedBatch->status === 'mapping_required' && $selectedBatch->created_by === auth()->id())
                <div class="space-y-4 rounded-xl border border-cyan-200/20 p-4" data-guide="import-column-mapping">
                    <div>
                        <flux:heading size="md">{{ __('4. Map columns and validate rows') }}</flux:heading>
                        <flux:text>{{ __('Confirm which product field each source column represents. Unmapped source columns are ignored; required fields must be mapped once.') }}</flux:text>
                    </div>
                    <form wire:submit="mapColumns" class="space-y-4">
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($selectedBatch->headers ?? [] as $sourceHeader)
                                @if ($sourceHeader !== '')
                                    <div class="space-y-2 rounded-lg border border-white/10 p-3" wire:key="mapping-{{ $sourceHeader }}">
                                        <flux:label>{{ __('Source column') }}: <code dir="ltr">{{ $sourceHeader }}</code></flux:label>
                                        <flux:select wire:model="columnMapping.{{ $sourceHeader }}">
                                            <flux:select.option value="">{{ __('Do not import this column') }}</flux:select.option>
                                            @foreach ($supportedFields as $field)
                                                <flux:select.option value="{{ $field }}">{{ $field }}</flux:select.option>
                                            @endforeach
                                        </flux:select>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                        @error('columnMapping') <flux:text class="text-red-600">{{ $message }}</flux:text> @enderror
                        <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="mapColumns">{{ __('Map columns and validate rows') }}</flux:button>
                    </form>
                </div>
            @endif

            <div class="overflow-x-auto">
                <flux:table aria-label="{{ __('Import row review') }}">
                    <flux:table.columns>
                        <flux:table.column>#</flux:table.column>
                        <flux:table.column>{{ __('Item code') }}</flux:table.column>
                        <flux:table.column>{{ __('Arabic name') }}</flux:table.column>
                        <flux:table.column>{{ __('English name') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                        <flux:table.column>{{ __('Errors') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($selectedBatch->rows as $row)
                            <flux:table.row :key="$row->id">
                                <flux:table.cell>{{ $row->row_number }}</flux:table.cell>
                                <flux:table.cell>{{ data_get($row->mapped_data, 'item_code') }}</flux:table.cell>
                                <flux:table.cell>{{ data_get($row->mapped_data, 'name_ar') }}</flux:table.cell>
                                <flux:table.cell>{{ data_get($row->mapped_data, 'name_en') }}</flux:table.cell>
                                <flux:table.cell><x-status.badge :status="$row->status" /></flux:table.cell>
                                <flux:table.cell>{{ implode(' ', $row->errors ?? []) }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
            @if ($selectedBatch->invalid_rows > 0)
                <flux:callout variant="warning">{{ __('Rejected rows remain unchanged and are available in the error download. You can approve the valid rows independently.') }}</flux:callout>
            @endif
        </flux:card>
    @endif
</x-app.page>
