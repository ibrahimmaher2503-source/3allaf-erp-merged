<?php
declare(strict_types=1);
$root=realpath('/home/rajeh/laravel');$expected='/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.22-qa-phase2-3821af5';
if($root!==$expected)throw new RuntimeException('Wrong active root');
require $root.'/vendor/autoload.php';$app=require $root.'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$mode=$argv[1]??'health';
if($mode==='health'){
 $identity=parse_ini_file($root.'/RELEASE_IDENTITY');
 if($identity['commit']!=='3821af553daca932e36c8a49ab78f911e1cf2379'||App\Support\ApplicationVersion::RELEASE!=='0.1.22-qa-phase2'||$app->isDownForMaintenance())throw new RuntimeException('Identity/version/maintenance mismatch');
 foreach(['.env'=>'/home/rajeh/shared/toyjoy/.env','storage'=>'/home/rajeh/shared/toyjoy/storage','public/storage'=>'/home/rajeh/shared/toyjoy/storage/app/public'] as $p=>$target)if(!is_link($root.'/'.$p)||realpath($root.'/'.$p)!==$target)throw new RuntimeException('Runtime link mismatch');
 $failed=Illuminate\Support\Facades\DB::table('failed_jobs')->count();if($failed!==0)throw new RuntimeException('Nonzero failed jobs');
 $schedules=collect($app->make(Illuminate\Console\Scheduling\Schedule::class)->events())->map(fn($e)=>$e->command)->all();foreach(['platform:backup:run','backup:clean','backup:monitor'] as $n)if(!collect($schedules)->contains(fn($s)=>str_contains($s,$n)))throw new RuntimeException('Missing schedule '.$n);
 $products=App\Modules\Catalog\Models\Product::query()->limit(5)->get(['id','average_cost']);
 $costs=$products->every(fn($p)=>$p->average_cost===null||preg_match('/^\d+(?:\.\d+)?$/',(string)$p->average_cost));if(!$costs)throw new RuntimeException('Invalid persisted cost');
 echo json_encode(['result'=>'PASS','release'=>$root,'identity'=>$identity,'version'=>App\Support\ApplicationVersion::RELEASE,'maintenance'=>'OFF','failed_jobs'=>$failed,'queued_jobs'=>Illuminate\Support\Facades\DB::table('jobs')->count(),'schedules_registered'=>true,'persisted_product_costs_read'=>count($products),'product_save_and_approval_mutations'=>'Preserved isolated MariaDB QA; not performed against production','financial_runtime_hashes'=>['pos'=>hash_file('sha256',$root.'/app/Modules/Retail/Services/PosCalculationService.php'),'product'=>hash_file('sha256',$root.'/app/Modules/Catalog/Actions/SaveProductAction.php')]],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;exit;
}
$id=(int)$argv[2];$uri=$argv[3];$expectedCode=(int)$argv[4];
config(['session.driver'=>'array','cache.default'=>'array']);
// Enforce read-only SQL for the entire authenticated render. No cookies,
// password hashes, persisted sessions, grants or business records are written.
Illuminate\Support\Facades\DB::statement('SET TRANSACTION READ ONLY');Illuminate\Support\Facades\DB::beginTransaction();
try{
 $user=App\Models\User::query()->select(['id','name','email','status','is_super_admin','email_verified_at'])->findOrFail($id);
 if($user->status!=='active')throw new RuntimeException('Inactive smoke identity');
 Illuminate\Support\Facades\Auth::guard('web')->setUser($user);
 $request=Illuminate\Http\Request::create('https://rajeh.xyz'.$uri,'GET',[],[],[],['HTTPS'=>'on','HTTP_ACCEPT'=>'text/html']);$request->setUserResolver(fn()=>$user);
 $kernel=$app->make(Illuminate\Contracts\Http\Kernel::class);$response=$kernel->handle($request);$status=$response->getStatusCode();
 $r=['route'=>$uri,'actor_id'=>$id,'status'=>$status,'expected'=>$expectedCode,'bytes'=>strlen($response->getContent()),'read_only_transaction'=>true,'result'=>$status===$expectedCode?'PASS':'FAIL'];echo json_encode($r,JSON_UNESCAPED_SLASHES).PHP_EOL;
 if($status!==$expectedCode){fwrite(STDERR,'Unexpected smoke route response'.PHP_EOL);exit(1);}
}finally{Illuminate\Support\Facades\DB::rollBack();}
