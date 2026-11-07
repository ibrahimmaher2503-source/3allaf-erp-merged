<x-app.page title="مرتجع مورد جديد" description="أنشئ مرتجعًا مرتبطًا بفاتورة شراء أو من المخزون الحالي للمورد." breadcrumbs="المشتريات والموردون" max-width="7xl" class="space-y-6">
    <x-slot:actions>
        <flux:button href="{{ route('purchasing.returns') }}" variant="subtle" icon="arrow-right">العودة إلى مرتجعات الموردين</flux:button>
    </x-slot:actions>

    @unless($hasReasonCatalog)
        <flux:callout variant="danger" icon="exclamation-triangle">لا توجد أسباب مرتجع نشطة. أضف سببًا من إعدادات المرتجعات قبل الحفظ.</flux:callout>
    @endunless

    <form wire:submit="saveDraft" class="space-y-6" data-product-line-editor>
        <flux:card class="space-y-5">
            <flux:heading size="lg">بيانات المستند</flux:heading>
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <div><flux:select wire:model.live="selectedSupplierId" label="المورد" required>
                    <flux:select.option value="">اختر المورد</flux:select.option>
                    @foreach($suppliers as $supplier)<flux:select.option value="{{ $supplier->id }}">{{ $supplier->name_ar ?: $supplier->name_en }}</flux:select.option>@endforeach
                </flux:select><flux:error name="selectedSupplierId" /></div>
                <div><flux:select wire:model.live="selectedStoreId" label="مخزن الإرجاع" required>
                    <flux:select.option value="">اختر المخزن</flux:select.option>
                    @foreach($returnStores as $store)<flux:select.option value="{{ $store->id }}">{{ $store->name_ar ?: $store->name_en }}</flux:select.option>@endforeach
                </flux:select><flux:error name="selectedStoreId" /></div>
                <div><flux:select wire:model.live="selectedInvoiceId" label="فاتورة المشتريات (اختياري)" :disabled="$selectedSupplierId === null">
                    <flux:select.option value="">بدون فاتورة شراء</flux:select.option>
                    @foreach($sourceInvoices->where('supplier_id', $selectedSupplierId) as $invoice)<flux:select.option value="{{ $invoice->id }}">{{ $invoice->invoice_number ?: '#'.$invoice->id }}</flux:select.option>@endforeach
                </flux:select><flux:error name="selectedInvoiceId" /></div>
                <div><flux:input type="date" value="{{ now()->toDateString() }}" label="التاريخ" readonly /></div>
                <div><flux:select wire:model.live="selectedReasonId" label="سبب المرتجع" required>
                    <flux:select.option value="">اختر السبب</flux:select.option>
                    @foreach($reasons as $reason)<flux:select.option value="{{ $reason->id }}">{{ $reason->label_ar ?: $reason->label_en }}</flux:select.option>@endforeach
                </flux:select><flux:error name="selectedReasonId" /></div>
            </div>
            <flux:callout variant="info">{{ $selectedInvoiceId ? 'مصدر التكلفة: فاتورة الشراء المحددة' : 'مصدر التكلفة: التكلفة الحالية للصنف في بطاقة الصنف' }}. تُحفظ تكلفة الوحدة على سطر المرتجع.</flux:callout>
            <flux:textarea wire:model="returnNotes" :label="$reasons->firstWhere('id', $selectedReasonId)?->requiresNotes() ? 'تفاصيل سبب المرتجع (مطلوبة)' : 'ملاحظات / مرجع اختياري'" :required="$reasons->firstWhere('id', $selectedReasonId)?->requiresNotes() ?? false" rows="2" /><flux:error name="returnNotes" />
        </flux:card>

        <flux:card class="space-y-5">
            <div class="flex flex-wrap items-center justify-between gap-3"><flux:heading size="lg">الأصناف</flux:heading><flux:text size="sm">اختر الصنف ثم أدخل كمية المرتجع.</flux:text></div>
            <x-product-line-lookup wire:model="returnProductEntry" label="المنتج" :supplier-return="true" :supplier-id="$selectedSupplierId" :supplier-only="$selectedSupplierId !== null" :purchase-invoice-id="$selectedInvoiceId" :disabled="$selectedStoreId === null" data-supplier-return-search x-on:product-selected="$wire.selectReturnProduct($event.detail.id)" />
            <flux:error name="returnProductEntry" />
            @if($returnLines === [])
                <flux:callout variant="info">لم تُضف أصناف بعد. اختر مخزن الإرجاع والمورد أو اختر صنفًا ذا مورد واحد.</flux:callout>
            @else
                <div class="space-y-3 md:hidden">
                    @foreach($returnLines as $index => $line)
                        <div wire:key="return-mobile-line-{{ $line['purchase_invoice_line_id'] ?: ($line['product_id'] ?? $index) }}" class="space-y-3 rounded-xl border p-4">
                            <div class="font-semibold">{{ $line['product'] }}</div>
                            <div class="grid grid-cols-2 gap-3 text-sm"><div><div class="text-text-muted">الكمية المتاحة</div><div dir="ltr">{{ $line['available'] }}</div></div><div><div class="text-text-muted">تكلفة الوحدة</div><div dir="ltr">{{ $line['unit_cost'] }}</div></div></div>
                            <flux:input wire:model.live="returnLines.{{ $index }}.quantity" type="number" min="1" step="1" label="الكمية المرتجعة" /><flux:error name="returnLines.{{ $index }}.quantity" />
                            <div class="flex items-center justify-between"><span>إجمالي المرتجع: <strong dir="ltr">{{ is_numeric($line['quantity']) ? bcmul((string) $line['quantity'], (string) $line['unit_cost'], 4) : '0.0000' }}</strong></span><flux:button type="button" size="sm" variant="danger" wire:click="removeReturnLine({{ $index }})">حذف</flux:button></div>
                        </div>
                    @endforeach
                </div>
                <div class="hidden overflow-x-auto md:block"><table class="w-full min-w-[680px] text-sm"><thead><tr class="border-b text-start"><th class="p-3 text-start">المنتج</th><th class="p-3">الكمية المتاحة</th><th class="p-3">الكمية المرتجعة</th><th class="p-3">تكلفة الوحدة</th><th class="p-3">إجمالي المرتجع</th><th class="p-3">الإجراء</th></tr></thead><tbody>
                    @foreach($returnLines as $index => $line)
                        <tr wire:key="return-create-line-{{ $line['purchase_invoice_line_id'] ?: $line['product_id'] ?? $index }}" class="border-b">
                            <td class="p-3 font-medium">{{ $line['product'] }}</td>
                            <td class="p-3 text-center" dir="ltr">{{ $line['available'] }}</td>
                            <td class="p-3"><flux:input wire:model.live="returnLines.{{ $index }}.quantity" type="number" min="1" step="1" aria-label="الكمية المرتجعة" /><flux:error name="returnLines.{{ $index }}.quantity" /></td>
                            <td class="p-3 text-center" dir="ltr">{{ $line['unit_cost'] }}</td>
                            <td class="p-3 text-center" dir="ltr">{{ is_numeric($line['quantity']) ? bcmul((string) $line['quantity'], (string) $line['unit_cost'], 4) : '0.0000' }}</td>
                            <td class="p-3"><flux:button type="button" size="sm" variant="danger" wire:click="removeReturnLine({{ $index }})">حذف</flux:button></td>
                        </tr>
                    @endforeach
                </tbody></table></div>
            @endif
            <flux:error name="returnLines" />
        </flux:card>

        <flux:card class="flex flex-wrap items-center justify-between gap-4">
            <div><flux:text>إجمالي المرتجع</flux:text><flux:heading size="xl" dir="ltr">{{ collect($returnLines)->reduce(fn($sum, $line) => bcadd($sum, is_numeric($line['quantity']) ? bcmul((string) $line['quantity'], (string) $line['unit_cost'], 4) : '0', 4), '0.0000') }}</flux:heading></div>
            <flux:button type="submit" variant="primary" :disabled="!$hasReasonCatalog || $returnLines === []" wire:loading.attr="disabled" wire:target="saveDraft">حفظ مسودة المرتجع</flux:button>
        </flux:card>
    </form>
</x-app.page>
