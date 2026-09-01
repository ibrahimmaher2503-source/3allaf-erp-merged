<?php

use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Platform\Models\PrinterConfiguration;
use App\Modules\Platform\Models\Store;
use App\Modules\Pricing\Models\LabelQueue;
use App\Modules\Pricing\Models\PriceVersion;
use App\Modules\Catalog\Models\Product;
use App\Modules\Platform\Models\PrintTemplate;
use App\Modules\Pricing\Services\PriceListResolver;
use Illuminate\Support\HtmlString;

$router = app('router');

$router->middleware(['auth', 'verified'])->group(function () use ($router): void {
    $router->livewire('pricing', 'pricing::index')
        ->middleware('can:pricing_labels.view')
        ->name('pricing.index');

    $router->livewire('pricing/lists/manage', 'pricing::lists')
        ->middleware('can:pricing_lists.view')
        ->name('pricing.lists');

    $router->livewire('pricing/approvals', 'pricing::index')
        ->middleware('can:pricing_labels.approve')
        ->name('pricing.approvals');

    $router->get('pricing/labels', function () {
        $actor = request()->user();
        abort_unless($actor instanceof \App\Models\User, 403);
        $visibleStores = Store::query()->visibleTo($actor)->select('id');

        $search=trim((string)request('search'));
        return view('layouts.app', [
            'title' => __('Label Queue Readiness'),
            'slot' => new HtmlString(view('pricing.labels', [
                'approvedPriceCount' => PriceVersion::query()->where('state', 'approved')->whereHas('lines', fn ($query) => $query->whereIn('store_id', Store::query()->visibleTo($actor)->select('id')))->count(),
                'stockBalanceCount' => StockBalance::query()->whereIn('store_id', Store::query()->visibleTo($actor)->select('id'))->count(),
                'printerCount' => PrinterConfiguration::query()->visibleTo($actor)->where('status', 'active')->count(),
                'queues' => LabelQueue::query()->whereIn('store_id', $visibleStores)->with(['product', 'store', 'version', 'printer', 'printEvents'])->latest('id')->limit(100)->get(),
                'products' => Product::query()->sellable()->with(['barcodes'=>fn($q)=>$q->active()->orderByDesc('is_primary')])->when($search!=='',fn($q)=>$q->where(fn($s)=>$s->where('item_code','like','%'.$search.'%')->orWhere('model_number','like','%'.$search.'%')->orWhere('name_ar','like','%'.$search.'%')->orWhere('name_en','like','%'.$search.'%')->orWhereHas('barcodes',fn($b)=>$b->where('barcode',$search))))->orderBy('item_code')->limit(30)->get(),
                'stores' => Store::query()->visibleTo($actor)->where('status','active')->where('type','selling')->orderBy('code')->get(),
                'templates' => PrintTemplate::visibleTo($actor)->where('status','active')->where('document_type','barcode_label')->orderBy('code')->get(),
                'printers' => PrinterConfiguration::visibleTo($actor)->where('status','active')->with('printTemplate')->orderBy('name')->get(),
                'search' => $search,
            ])->render()),
        ]);
    })
        ->middleware('can:pricing_labels.view')
        ->name('pricing.labels');

    $router->post('pricing/labels/preview', function () {
        $actor=request()->user(); abort_unless($actor instanceof \App\Models\User,403);
        $data=request()->validate(['store_id'=>['required','integer'],'template_id'=>['required','integer'],'printer_id'=>['nullable','integer'],'selected'=>['required','array','min:1','max:100'],'selected.*'=>['integer'],'products'=>['required','array','max:100'],'products.*.id'=>['required','integer'],'products.*.barcode_id'=>['required','integer'],'products.*.copies'=>['required','integer','min:1','max:500'],'output'=>['nullable','in:html,pdf']]);
        $data['products']=collect($data['selected'])->map(fn($index)=>$data['products'][$index]??null)->filter()->values()->all();
        $store=Store::visibleTo($actor)->where('type','selling')->where('status','active')->findOrFail($data['store_id']);
        $template=PrintTemplate::visibleTo($actor)->where('document_type','barcode_label')->where('status','active')->findOrFail($data['template_id']);
        $printer=filled($data['printer_id']??null)?PrinterConfiguration::visibleTo($actor)->where('status','active')->findOrFail($data['printer_id']):null;
        if($printer && $printer->print_template_id && $printer->print_template_id!==$template->id) abort(422,__('The selected printer is assigned to another template.'));
        $settings=$template->layout_settings??[];$requiresPrice=(bool)($settings['show_price']??true);$resolver=app(PriceListResolver::class);$list=$resolver->listForOutlet($store);$labels=[];
        foreach($data['products'] as $row){$product=Product::query()->sellable()->with('barcodes')->findOrFail($row['id']);$barcode=$product->barcodes->firstWhere('id',(int)$row['barcode_id']);abort_unless($barcode&&$barcode->status==='active',422);$price=null;if($requiresPrice){try{$price=$resolver->resolve($product,$list)->finalPrice;}catch(\Throwable){throw \Illuminate\Validation\ValidationException::withMessages(['products'=>__('Base consumer price is required and must be greater than zero. Edit the Product Card price field or select a barcode-only template.')]);}}for($i=0;$i<(int)$row['copies'];$i++)$labels[]=['product'=>$product,'barcode'=>$barcode,'price'=>$price,'list'=>$list,'store'=>$store];}
        $html=view('pricing.label-print',['labels'=>$labels,'template'=>$template,'printer'=>$printer,'settings'=>$settings])->render();
        if(($data['output']??'html')==='pdf'){
            $paper=$template->paper_size==='a4'?'a4':[0,0,(float)($settings['width_mm']??50)*72/25.4,(float)($settings['height_mm']??30)*72/25.4];
            return \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper($paper,(string)($settings['orientation']??'portrait'))->download('barcode-labels.pdf');
        }
        return response($html)->header('Content-Type','text/html; charset=UTF-8');
    })->middleware('can:pricing_labels.create')->name('pricing.labels.preview');

    $router->livewire('pricing/{mode}', 'pricing::index')
        ->whereIn('mode', ['workspace', 'versions', 'unpriced', 'history'])
        ->middleware('can:pricing_labels.view')
        ->name('pricing.focus');
});
