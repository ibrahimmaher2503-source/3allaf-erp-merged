<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Support\AuthorizedCompanyContext;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

$root = dirname(__DIR__, 3);

try {
    require $root.'/vendor/autoload.php';
    $app = require $root.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    if (! str_contains((string) DB::connection()->getDatabaseName(), 'hf48')) {
        throw new RuntimeException('System Administrator verification requires a disposable Hotfix48 database.');
    }

    DB::beginTransaction();
    try {
        $token = strtolower(substr(str_replace('-', '', (string) Str::uuid()), 0, 12));
        $role = Role::query()->where('code', 'system-administrator')->where('status', 'active')->firstOrFail();
        $actor = User::query()->create([
            'name' => 'HF48 immediate administrator',
            'username' => 'hf48-admin-'.$token,
            'email' => 'hf48-admin-'.$token.'@example.test',
            'password' => Hash::make(Str::password(24)),
            'status' => 'active',
            'is_super_admin' => false,
        ]);
        $actor->forceFill(['email_verified_at' => now()])->save();
        $actor->roles()->attach($role->id);
        Auth::login($actor);

        if (! $actor->isSystemAdministrator() || ! $actor->hasGlobalApplicationAccess()) {
            throw new RuntimeException('A newly created active System Administrator did not receive global access immediately.');
        }
        if (! $actor->hasPermission('purchase_invoices.draft') || ! Gate::forUser($actor)->allows('purchase_invoices.draft')) {
            throw new RuntimeException('An active application permission was not granted.');
        }
        if ($actor->hasPermission('hotfix48.undefined') || Gate::forUser($actor)->allows('hotfix48.undefined')) {
            throw new RuntimeException('An undefined dotted ability was granted.');
        }
        if ($actor->canBypassApproval()) {
            throw new RuntimeException('System Administrator incorrectly bypassed maker/checker.');
        }
        if (Store::query()->visibleTo($actor)->count() !== Store::query()->count()
            || Branch::query()->visibleTo($actor)->count() !== Branch::query()->count()) {
            throw new RuntimeException('System Administrator location visibility is incomplete.');
        }
        if (app(AuthorizedCompanyContext::class)->companies($actor)->count() !== \App\Modules\Platform\Models\Company::query()->where('status', 'active')->count()) {
            throw new RuntimeException('System Administrator company visibility is incomplete.');
        }

        echo "HOTFIX48_ADMIN_VERIFICATION=PASS immediate_role=pass active_permissions=pass undefined_permission=denied global_scope=pass maker_checker=preserved production_records=none\n";
    } finally {
        DB::rollBack();
        Auth::logout();
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'HOTFIX48_ADMIN_VERIFICATION=FAIL '.$exception->getMessage()."\n");
    exit(1);
}
