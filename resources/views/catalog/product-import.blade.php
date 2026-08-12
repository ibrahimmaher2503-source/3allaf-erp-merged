<?php

use App\Modules\Catalog\Actions\StageProductImportAction;
use App\Modules\Catalog\Models\ProductImportBatch;
use App\Modules\Platform\Actions\StoreAttachment;
use App\Modules\Platform\Actions\RevokeAttachment;
use App\Modules\Platform\Models\Attachment;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Product Import')] class extends Component {
    use WithFileUploads;

    public mixed $importFile = null;
    public string $mode = 'create_only';
    public ?int $selectedBatchId = null;

    public function mount(): void
    {
        Gate::authorize('products_categories_brands.create');
    }

    public function stage(StageProductImportAction $action): void
    {
        Gate::authorize($this->mode === 'update_existing' ? 'products_categories_brands.edit' : 'products_categories_brands.create');
        $this->validate([
            'importFile' => ['required', 'file', 'mimes:xlsx,csv,ods', 'max:10240'],
            'mode' => ['required', 'in:create_only,update_existing'],
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
            $this->importFile = null;
            Flux::toast(variant: 'success', text: __('File staged. Review all rows before approval.'));
        } catch (Throwable $exception) {
            $this->addError('importFile', $exception->getMessage());
            Flux::toast(variant: 'danger', text: $exception->getMessage());
        }
    }

    public function approve(StageProductImportAction $action): void
    {
        Gate::authorize('products_categories_brands.approve');
        $batch = ProductImportBatch::query()->findOrFail($this->selectedBatchId);

        try {
            $action->approve($batch, app(\App\Modules\Catalog\Actions\SaveProductAction::class));
            Flux::toast(variant: 'success', text: __('Import approved and valid rows were written.'));
        } catch (Throwable $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());
        }
    }

    public function cancelBatch(StageProductImportAction $action): void
    {
        Gate::authorize('products_categories_brands.create');
        $batch = ProductImportBatch::query()->where('created_by', auth()->id())->findOrFail($this->selectedBatchId);

        try {
            $action->cancel($batch);
            Flux::toast(variant: 'success', text: __('Import cancelled. You can upload the same file again.'));
        } catch (Throwable $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());
        }
    }

    public function selectBatch(int $batchId): void
    {
        Gate::authorize('products_categories_brands.view');
        $this->selectedBatchId = $batchId;
    }

    public function render()
    {
        $batches = ProductImportBatch::query()
            ->where('created_by', auth()->id())
            ->latest()
            ->paginate(10, pageName: 'imports');

        $selectedBatch = $this->selectedBatchId
            ? ProductImportBatch::query()->where('created_by', auth()->id())->with(['rows' => fn ($query) => $query->orderBy('row_number')->limit(50)])->find($this->selectedBatchId)
            : null;

        $sourceAttachment = $selectedBatch === null ? null : Attachment::query()
            ->where('source_type', ProductImportBatch::class)
            ->where('source_id', (string) $selectedBatch->id)
            ->where('purpose', 'import_source')
            ->first();

        return view('catalog.product-import', compact('batches', 'selectedBatch', 'sourceAttachment'));
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
    </x-slot:actions>

    <flux:card class="space-y-5" data-guide="import-upload-section">
        <div>
            <flux:heading size="lg">{{ __('1. Upload and stage') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Required columns: item_code, name_ar, name_en, category_code. Formula cells are rejected.') }}</flux:text>
        </div>

        <form wire:submit="stage" class="grid gap-4 md:grid-cols-3">
            <div class="md:col-span-2 space-y-2">
                <flux:label>{{ __('Excel or CSV file') }}</flux:label>
                <flux:input type="file" wire:model="importFile" accept=".xlsx,.csv,.ods" />
                @error('importFile') <flux:text class="text-red-600">{{ $message }}</flux:text> @enderror
            </div>
            <div class="space-y-2" data-guide="import-mode-select">
                <flux:label>{{ __('Import mode') }}</flux:label>
                <flux:select wire:model="mode">
                    <flux:select.option value="create_only">{{ __('Create Only') }}</flux:select.option>
                    <flux:select.option value="update_existing">{{ __('Update Existing') }}</flux:select.option>
                </flux:select>
            </div>
            <div class="md:col-span-3 flex items-center justify-between gap-3">
                    <flux:text>{{ __('Nothing is saved until the import is confirmed.') }}</flux:text>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" data-guide="import-stage-button">{{ __('Stage file') }}</flux:button>
            </div>
        </form>
    </flux:card>

    <flux:card data-guide="import-batches-section">
        <flux:heading size="lg">{{ __('2. Staged batches') }}</flux:heading>
        <div class="mt-4 overflow-x-auto">
            <flux:table aria-label="{{ __('Staged product imports') }}">
                <flux:table.columns>
                    <flux:table.column>{{ __('File') }}</flux:table.column>
                    <flux:table.column>{{ __('Mode') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column>{{ __('Rows') }}</flux:table.column>
                    <flux:table.column>{{ __('Action') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($batches as $batch)
                        <flux:table.row :key="$batch->id">
                            <flux:table.cell>{{ $batch->original_filename }}</flux:table.cell>
                            <flux:table.cell>{{ $batch->mode === 'create_only' ? __('Create Only') : __('Update Existing') }}</flux:table.cell>
                            <flux:table.cell><x-status.badge :status="$batch->status" /></flux:table.cell>
                            <flux:table.cell>{{ $batch->valid_rows }} / {{ $batch->total_rows }} {{ __('valid') }}</flux:table.cell>
                            <flux:table.cell><flux:button size="sm" variant="subtle" wire:click="selectBatch({{ $batch->id }})">{{ __('Review') }}</flux:button></flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row><flux:table.cell colspan="5">{{ __('No staged imports yet.') }}</flux:table.cell></flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
        <div class="mt-4">{{ $batches->links() }}</div>
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
                    @if (in_array($selectedBatch->status, ['staging', 'ready_for_review'], true))
                        <flux:button variant="danger" wire:click="cancelBatch">{{ __('Cancel batch') }}</flux:button>
                    @endif
                    @can('products_categories_brands.approve')
                        <flux:button variant="primary" wire:click="approve" :disabled="$selectedBatch->status !== 'ready_for_review' || $selectedBatch->invalid_rows > 0" data-guide="import-approve-button">{{ __('Approve valid rows') }}</flux:button>
                    @endcan
                </div>
            </div>

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
                <flux:callout variant="warning">{{ __('Approval is blocked while rejected rows remain. Invalid rows never write to products.') }}</flux:callout>
            @endif
        </flux:card>
    @endif
</x-app.page>
