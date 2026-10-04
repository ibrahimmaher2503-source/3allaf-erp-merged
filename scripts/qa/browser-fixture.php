<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';require __DIR__.'/fixture.php';
use App\Models\User;
use App\Modules\Platform\Models\Role;
use Illuminate\Support\Facades\DB;
$f=qaFixture();$accounts=[];
foreach (['Admin'=>$f['admin'],'User'=>$f['user'],'Cashier'=>null] as $name=>$user) {
    if ($user===null) {
        $user=User::where('username','qa-cashier')->first() ?? User::create(['name'=>'QA Cashier','username'=>'qa-cashier','email'=>'qa-cashier@example.test','password'=>bin2hex(random_bytes(20)),'status'=>'active','email_verified_at'=>now()]);
        $user->roles()->sync([Role::where('code','cashier')->firstOrFail()->id]);
        foreach (['user_branch_scopes'=>['branch_id'=>$f['branch']->id],'user_store_scopes'=>['store_id'=>$f['store']->id]] as $table=>$scope) DB::table($table)->updateOrInsert(['user_id'=>$user->id,...$scope],[...$scope,'user_id'=>$user->id,'status'=>'active','created_at'=>now(),'updated_at'=>now()]);
    }
    $password=bin2hex(random_bytes(20));$user->password=$password;$user->save();
    $accounts[]=['role'=>$name,'username'=>$user->username,'password'=>$password];
}
file_put_contents($argv[1],json_encode($accounts,JSON_THROW_ON_ERROR));chmod($argv[1],0600);
echo "PASS three disposable QA accounts; no existing identity used\n";
