<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';require __DIR__.'/fixture.php';
use App\Support\DataExchange\MasterDataDocument;
use Illuminate\Support\Facades\{Auth,Gate,Route};
use App\Modules\Platform\Models\{Role,Permission};
$f=qaFixture();$route=Route::getRoutes()->getByName('catalog.data-exchange.export');
qaAssert($route!==null,'actual product export route registered');$closure=$route->getAction('uses');$documents=app(MasterDataDocument::class);
$role=Role::firstOrCreate(['code'=>'qa-exporter'],['name_ar'=>'تصدير اختبار','name_en'=>'QA Exporter','status'=>'active']);
$role->permissions()->sync(Permission::whereIn('code',['products_categories_brands.export','products_categories_brands.view'])->pluck('id'));$f['user']->roles()->sync([$role->id]);
foreach (['Admin'=>$f['admin'],'Cost-disabled exporter'=>$f['user']] as $name=>$actor) {
    Auth::login($actor);qaAssert(Gate::allows('products_categories_brands.export'),$name.' export authorization');
    $canViewCost=Gate::allows('products_categories_brands.cost_view');qaAssert($canViewCost===($name==='Admin'),$name.' independent cost permission');
    app()->setLocale('en');$response=$closure('products','xlsx',$documents);$path=$response->getFile()->getPathname();$zip=new ZipArchive;qaAssert($zip->open($path)===true,$name.' XLSX container');
    $xml='';for($j=0;$j<$zip->numFiles;$j++) if(str_ends_with($zip->getNameIndex($j),'.xml')) $xml.=$zip->getFromIndex($j);$zip->close();unlink($path);
    qaAssert(str_contains($xml,'Unit cost')===$canViewCost,$name.' XLSX cost header boundary');
    foreach (['en','ar'] as $locale) {
        app()->setLocale($locale);$pdf=$closure('products','pdf',$documents)->getContent();qaAssert(str_starts_with($pdf,'%PDF-') && str_contains($pdf,'%%EOF'),$name.' '.$locale.' actual PDF output');
    }
}
Auth::login($f['admin']);
$sale=App\Modules\Retail\Models\Sale::where('status','approved')->firstOrFail()->load(['store.company','branch','customer','lines','payments.paymentMethod']);
foreach (['en','ar'] as $locale) {app()->setLocale($locale);foreach(['58mm','80mm'] as $paper) {
    $html=view('pages.sales.thermal',['sale'=>$sale,'receiptProfile'=>['paper_size'=>$paper,'printer'=>null,'fallback_message'=>'']])->render();
    qaAssert(str_contains($html,$sale->document_number) && str_contains($html,(string)$sale->payable_total) && str_contains($html,'size:'.$paper),$locale.' '.$paper.' receipt uses immutable sale totals');
}}
echo "PASS actual XLSX/PDF cost boundary and bilingual 58/80mm receipt rendering; physical printing remains on-site\n";
