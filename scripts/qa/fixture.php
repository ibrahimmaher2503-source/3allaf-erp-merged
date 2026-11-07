<?php
declare(strict_types=1);
use App\Models\User;
use App\Modules\Platform\Models\{Company, Branch, Store, CashDrawer, PaymentMethod};
use App\Modules\Catalog\Models\Category;
use Illuminate\Support\Facades\{Auth, DB};
function qaAssert(bool $ok, string $label): void {
    if (!$ok) throw new RuntimeException('FAIL '.$label);
    echo 'PASS '.$label."\n";
}
function qaMoney(mixed $actual, string $expected, string $label, int $scale=4): void {
    qaAssert(bccomp((string)$actual,$expected,$scale)===0,$label.' = '.(string)$actual);
}
function qaReject(callable $call, string $label, string $class): void {
    try {$call();} catch (Throwable $e) {if (!$e instanceof $class) throw $e; echo 'PASS '.$label."\n";return;}
    throw new RuntimeException('FAIL expected rejection: '.$label);
}
function qaFixture(): array {
    App\Modules\Platform\Models\Permission::firstOrCreate(['code'=>'products_categories_brands.cost_view'],['module'=>'products_categories_brands','action'=>'cost_view','sensitivity'=>'sensitive','status'=>'active']);
    if ($company=Company::where('code','QA')->first()) {
        $branch=Branch::where('code','QA-BR')->firstOrFail();$receiving=Store::where('code','QA-REC')->firstOrFail();$store=Store::where('code','QA-SELL')->firstOrFail();
        $admin=User::where('username','qa-admin')->firstOrFail();$user=User::where('username','qa-user')->firstOrFail();$drawer=CashDrawer::where('code','QA-TILL')->firstOrFail();$method=PaymentMethod::where('code','QA-CASH')->firstOrFail();$category=Category::where('code','QA-CAT')->firstOrFail();Auth::login($admin);
        $base=app(App\Modules\Pricing\Actions\EnsureBasePriceList::class)->execute($company);$store->update(['price_list_id'=>$base->id]);
    return compact('company','branch','receiving','store','admin','user','drawer','method','category');
    }
    app(Database\Seeders\ProductionSeeder::class)->seedAuthorizationOnly();
    $company=Company::create(['code'=>'QA','name_ar'=>'اختبار الجودة','name_en'=>'QA','status'=>'active','currency_code'=>'EGP','timezone'=>'Africa/Cairo']);
    $branch=Branch::create(['company_id'=>$company->id,'code'=>'QA-BR','name_ar'=>'فرع اختبار','name_en'=>'QA Branch','status'=>'active','timezone'=>'Africa/Cairo']);
    $receiving=Store::create(['company_id'=>$company->id,'branch_id'=>$branch->id,'code'=>'QA-REC','name_ar'=>'استلام اختبار','name_en'=>'QA Receiving','type'=>'warehouse','status'=>'active','allows_negative_stock'=>false]);
    $store=Store::create(['company_id'=>$company->id,'branch_id'=>$branch->id,'code'=>'QA-SELL','name_ar'=>'بيع اختبار','name_en'=>'QA Selling','type'=>'selling','status'=>'active','allows_negative_stock'=>false]);
    $admin=User::create(['name'=>'QA Admin','username'=>'qa-admin','email'=>'qa-admin@example.test','password'=>bin2hex(random_bytes(20)),'status'=>'active','email_verified_at'=>now()]);
    $admin->forceFill(['is_super_admin'=>true])->save();
    $user=User::create(['name'=>'QA User','username'=>'qa-user','email'=>'qa-user@example.test','password'=>bin2hex(random_bytes(20)),'status'=>'active','email_verified_at'=>now()]);
    $drawer=CashDrawer::create(['company_id'=>$company->id,'branch_id'=>$branch->id,'store_id'=>$store->id,'code'=>'QA-TILL','name_ar'=>'خزينة اختبار','name_en'=>'QA Till','status'=>'active','assigned_user_id'=>$admin->id]);
    $method=PaymentMethod::create(['code'=>'QA-CASH','name_ar'=>'نقد اختبار','name_en'=>'QA Cash','type'=>'cash','status'=>'active','requires_evidence'=>false,'offline_eligible'=>false]);
    DB::table('pos_financial_setting_versions')->insertOrIgnore(['key'=>'pos.cash_rounding_denomination','value'=>'5','version'=>1,'value_type'=>'number','created_by'=>$admin->id,'created_at'=>now(),'updated_at'=>now()]);
    Auth::login($admin);
    $category=Category::create(['code'=>'QA-CAT','name_ar'=>'ألعاب اختبار','name_en'=>'QA Toys','status'=>'active','sort_order'=>0]);
    $base=app(App\Modules\Pricing\Actions\EnsureBasePriceList::class)->execute($company);$store->update(['price_list_id'=>$base->id]);
    return compact('company','branch','receiving','store','admin','user','drawer','method','category');
}
