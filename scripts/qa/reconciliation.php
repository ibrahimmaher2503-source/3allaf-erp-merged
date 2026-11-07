<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';require __DIR__.'/fixture.php';
use App\Modules\Catalog\Actions\{SaveSupplierAction,SaveProductAction};
use App\Modules\Purchasing\Actions\{SavePurchaseOrderAction,SubmitPurchaseOrderAction,ApprovePurchaseOrderAction,SavePurchaseInvoiceAction,SubmitPurchaseInvoiceAction,SavePurchaseDistributionAction,ApprovePurchaseInvoiceAction};
use App\Modules\Inventory\Actions\{DispatchStockTransferAction,ReceiveStockTransferAction};
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Purchasing\Models\StockBalance;
use App\Modules\Retail\Actions\{OpenShiftAction,RetailSaleAction,RetailReturnAction,SubmitBlindShiftCloseAction};
use Illuminate\Support\Facades\{DB,Auth};
$f=qaFixture();
if (getenv('QA_RECONCILE_FROM')==='sale') {
$i=App\Modules\Purchasing\Models\PurchaseInvoice::latest('id')->firstOrFail()->load('lines.product');$p=$i->lines->first()->product;$shift=App\Modules\Retail\Models\PosShift::firstOrFail();
} else {
if (getenv('QA_RECONCILE_FROM')!=='approval') {
$s=App\Modules\Catalog\Models\Supplier::where('code','SUP-0002')->first() ?? app(SaveSupplierAction::class)->execute(['code'=>'SUP-0002','name_ar'=>'مورد اختبار','name_en'=>'QA Supplier','status'=>'active','settlement_method'=>'cash']);
$p=app(SaveProductAction::class)->execute(['name_ar'=>'لعبة اختبار','name_en'=>'QA Toy','model_number'=>'QA-MODEL','category_id'=>$f['category']->id,'preferred_supplier_id'=>$s->id,'average_cost'=>'10','sale_price'=>'25']);
$po=app(SavePurchaseOrderAction::class)->execute(['supplier_id'=>$s->id,'branch_id'=>$f['branch']->id,'store_id'=>$f['receiving']->id],[['product_id'=>$p->id,'quantity_ordered'=>'2','unit_cost'=>'10']]);
qaMoney($po->total_amount,'20','purchase order total');
$po=app(SubmitPurchaseOrderAction::class)->execute($po->id,$po->lock_version);
$po=app(ApprovePurchaseOrderAction::class)->execute($po->id,$po->lock_version);
$i=app(SavePurchaseInvoiceAction::class)->execute(['supplier_id'=>$s->id,'store_id'=>$f['receiving']->id,'purchase_order_id'=>$po->id,'invoice_date'=>today()->toDateString(),'currency_code'=>'EGP','supplier_reference'=>'QA-RECEIVE'],[['product_id'=>$p->id,'purchase_order_line_id'=>$po->lines->first()->id,'quantity'=>'2','unit_cost'=>'10','base_consumer_price'=>'25','discount_value'=>'0','tax_rate'=>'0']]);
qaMoney($i->total_amount,'20','invoice header total');qaMoney($i->lines->sum('line_total'),'20','invoice line sum');
} else { $i=App\Modules\Purchasing\Models\PurchaseInvoice::latest('id')->firstOrFail()->load('lines.product');$p=$i->lines->first()->product; }
if ($i->status==='draft') $i=app(SubmitPurchaseInvoiceAction::class)->execute($i->id,$i->lock_version);
if ($i->status!=='approved') {
$i=app(SavePurchaseDistributionAction::class)->execute($i->id,[$i->lines->first()->id=>[$f['store']->id=>'2']],$i->lock_version);
$i=app(ApprovePurchaseInvoiceAction::class)->execute($i->id,$i->lock_version);
}
qaMoney(DB::table('stock_movements')->where('product_id',$p->id)->where('movement_type','purchase_receipt')->sum('quantity'),'2','receipt movement quantity',6);
qaMoney(StockBalance::where('product_id',$p->id)->where('store_id',$f['receiving']->id)->value('on_hand'),'0','temporary receipt stock distributed',6);
$t=StockTransfer::where('purchase_invoice_id',$i->id)->firstOrFail();
if ($t->status==='approved') $t=app(DispatchStockTransferAction::class)->execute($t->id);
$t=app(ReceiveStockTransferAction::class)->execute($t->id,$t->lines->mapWithKeys(fn($line)=>[$line->id=>(string)$line->quantity_dispatched])->all(),null,null);
qaMoney(StockBalance::where('product_id',$p->id)->where('store_id',$f['store']->id)->value('on_hand'),'2','selling stock after receive',6);
qaMoney(StockBalance::where('product_id',$p->id)->where('store_id',$f['store']->id)->value('total_value'),'20','received inventory cost');
$shift=app(OpenShiftAction::class)->execute($f['admin'],$f['drawer'],'100','qa-open');
$shiftReplay=app(OpenShiftAction::class)->execute($f['admin'],$f['drawer'],'100','qa-open');qaAssert($shiftReplay->id===$shift->id,'shift idempotency');
qaReject(fn()=>app(OpenShiftAction::class)->execute($f['admin'],$f['drawer'],'100','qa-open-2'),'duplicate active shift rejected',InvalidArgumentException::class);
}
if (!App\Modules\Pricing\Models\PriceLine::where('product_id',$p->id)->where('store_id',$f['store']->id)->exists()) {
$price=app(App\Modules\Pricing\Actions\CreatePriceProposalAction::class)->execute($p,$f['store'],'PL-0000','قائمة اختبار','QA Base','25','product_card','QA',null,null,'QA approved price');
$price=app(App\Modules\Pricing\Actions\SubmitPriceProposalAction::class)->execute($price);
$price=app(App\Modules\Pricing\Actions\ApprovePriceProposalAction::class)->execute($price);qaAssert($price->state->value==='approved','price approval workflow');
}
$lines=[['product_id'=>$p->id,'quantity'=>'1']];$tenders=[['method'=>$f['method'],'amount'=>'25','tendered'=>'25']];
$sale=app(RetailSaleAction::class)->create($f['admin'],$f['store'],$lines,'qa-sale',false,$tenders);
qaAssert($sale->status==='approved','sale approved');qaMoney($sale->total,'25','sale payable');qaMoney($sale->payments->sum('amount'),'25','captured payments');qaMoney($sale->lines->sum('consumed_cost'),'10','consumed cost');
qaMoney(StockBalance::where('product_id',$p->id)->where('store_id',$f['store']->id)->value('on_hand'),'1','stock after sale',6);
$replay=app(RetailSaleAction::class)->create($f['admin'],$f['store'],$lines,'qa-sale',false,$tenders);qaAssert($replay->id===$sale->id && $replay->payments->count()===1,'sale/payment replay has no duplicates');
qaReject(fn()=>app(RetailSaleAction::class)->create($f['admin'],$f['store'],[['product_id'=>$p->id,'quantity'=>'3']],'qa-overstock',false,[['method'=>$f['method'],'amount'=>'75','tendered'=>'75']]),'insufficient stock rolls back',InvalidArgumentException::class);
qaAssert(DB::table('sales')->count()===1,'failed sale has no persisted document');
$r=app(RetailReturnAction::class);$return=$r->create($f['admin'],['source_sale_id'=>$sale->id,'settlement_type'=>'original_tender','reason'=>'QA reversal','lines'=>[['sale_line_id'=>$sale->lines->first()->id,'quantity'=>'1','condition'=>'sellable','disposition'=>'restock']]],'qa-return');
$return=$r->submit($f['admin'],$return);$return=$r->approve($f['admin'],$return);$return=$r->complete($f['admin'],$return,'qa-refund');
qaAssert($return->status==='completed','return completed');qaMoney($return->settlement_value,'25','refund total');qaMoney($return->settlements->sum('amount'),'25','refund settlement ledger');
qaMoney(StockBalance::where('product_id',$p->id)->where('store_id',$f['store']->id)->value('on_hand'),'2','stock restored',6);qaMoney(StockBalance::where('product_id',$p->id)->where('store_id',$f['store']->id)->value('total_value'),'20','inventory value restored');
qaAssert($r->complete($f['admin'],$return,'qa-refund')->id===$return->id && DB::table('retail_return_settlements')->count()===1,'refund replay no duplicate settlement');
qaReject(fn()=>$r->create($f['admin'],['source_sale_id'=>$sale->id,'reason'=>'QA excess','lines'=>[['sale_line_id'=>$sale->lines->first()->id,'quantity'=>'1']]],'qa-overreturn'),'excess return rejected',Illuminate\Validation\ValidationException::class);
Auth::login($f['user']);qaReject(fn()=>app(RetailSaleAction::class)->create($f['user'],$f['store'],$lines,'qa-denied'),'sale authorization denied',Symfony\Component\HttpKernel\Exception\HttpException::class);Auth::login($f['admin']);
$closing=app(SubmitBlindShiftCloseAction::class)->execute($f['admin'],$shift,'100',[],'qa-close');
qaAssert($shift->fresh()->status->value!=='open','shift enters closing workflow');
require __DIR__.'/db-integrity.php';
echo "PASS real application supplier/product -> PO -> invoice/receipt -> transfer -> sale/payment -> stock -> return/refund -> shift closure; ledger/cost/quantity reconciliation\n";
