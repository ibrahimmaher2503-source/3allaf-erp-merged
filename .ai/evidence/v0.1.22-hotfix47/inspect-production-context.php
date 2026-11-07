<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Platform\Models\Store;
use Illuminate\Contracts\Console\Kernel;

$release = realpath((string) ($argv[1] ?? ''));

if ($release === false || ! is_file($release.'/bootstrap/app.php')) {
    fwrite(STDERR, "INVALID_RELEASE\n");
    exit(2);
}

require $release.'/vendor/autoload.php';
$app = require $release.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

foreach ([1 => 'hassan', 2 => 'karim'] as $id => $username) {
    $user = User::query()->whereKey($id)->where('username', $username)->firstOrFail();
    $activeStores = Store::query()->visibleTo($user)->where('status', 'active')->count();

    printf(
        "user=%d/%s active=%s verified=%s super_admin=%s active_stores=%d dashboard=%s setup=%s\n",
        $user->id,
        $user->username,
        $user->status === 'active' ? 'yes' : 'no',
        $user->hasVerifiedEmail() ? 'yes' : 'no',
        $user->is_super_admin ? 'yes' : 'no',
        $activeStores,
        $user->can('dashboard_reports.view') ? 'allowed' : 'denied',
        $user->can('company_settings.edit') ? 'allowed' : 'denied',
    );
}

printf(
    "session_driver=%s session_cookie=%s app_url=%s\n",
    (string) config('session.driver'),
    (string) config('session.cookie'),
    (string) config('app.url'),
);
