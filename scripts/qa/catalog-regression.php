<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
require __DIR__.'/fixture.php';
use App\Modules\Catalog\Actions\{SaveProductAction, SaveSupplierAction};
use App\Modules\Catalog\Models\{Product, ProductOptionGroup, ProductOptionValue};
use Illuminate\Support\Facades\Auth;
$f=qaFixture();
$supplier=App\Modules\Catalog\Models\Supplier::where('code','SUP-0002')->first() ?? app(SaveSupplierAction::class)->execute(['code'=>'SUP-0002','name_ar'=>'مورد اختبار','name_en'=>'QA Supplier','status'=>'active','settlement_method'=>'cash']);
$group=ProductOptionGroup::where('code','QA-SIZE')->first() ?? ProductOptionGroup::create(['code'=>'QA-SIZE','name_ar'=>'قياس اختبار','name_en'=>'QA Size','status'=>'active','sort_order'=>0]);
$value=ProductOptionValue::where('code','QA-L')->first() ?? ProductOptionValue::create(['product_option_group_id'=>$group->id,'code'=>'QA-L','name_ar'=>'كبير','name_en'=>'Large','status'=>'active','sort_order'=>0]);
$data=['name_ar'=>'لعبة اختبار','name_en'=>'QA Toy','model_number'=>'QA-MODEL','category_id'=>$f['category']->id,'preferred_supplier_id'=>$supplier->id,'average_cost'=>'10.1200','sale_price'=>'25','filter_value_ids'=>[$value->id]];
$save=app(SaveProductAction::class);$p=$save->execute($data);
if (getenv('QA_CATALOG_FROM')!=='cost') {
qaAssert($p->item_code==='0002-000001','supplier-derived product identity');
qaAssert($p->barcodes()->count()===1,'one primary identity barcode');
qaAssert($p->filterValues()->pluck('product_option_values.id')->all()===[$value->id] && !$p->has_variations,'filters remain descriptive');
}
qaMoney($p->average_cost,'10.1200','created cost');
foreach (['blank','omitted'] as $kind) {
    $edit=$data;unset($edit['preferred_supplier_id'],$edit['filter_value_ids']);
    if ($kind==='blank') $edit['average_cost']=''; else unset($edit['average_cost']);
    $p=$save->execute($edit,$p->id,$p->lock_version);qaMoney($p->average_cost,'10.1200',$kind.' edit retains saved cost');
}
qaReject(fn()=>$save->execute($data,$p->id,-1),'stale product version',App\Modules\Catalog\Exceptions\StaleCatalogRecordException::class);
qaReject(fn()=>$save->execute([...$data,'item_code'=>'OTHER'],$p->id,$p->lock_version),'immutable item identity',App\Modules\Catalog\Exceptions\ImmutableItemCodeChangeException::class);
$before=Product::count();Auth::login($f['user']);
qaReject(fn()=>$save->execute($data),'normal user denied product creation',Illuminate\Auth\Access\AuthorizationException::class);
qaAssert(Product::count()===$before,'denied write leaves products unchanged');
Auth::login($f['admin']);
qaAssert(App\Modules\Platform\Models\AuditLog::query()->count()>0,'catalog actions emit audit evidence');
echo "PASS catalog create/edit/cost/filter/identity/denial/failure regressions\n";
