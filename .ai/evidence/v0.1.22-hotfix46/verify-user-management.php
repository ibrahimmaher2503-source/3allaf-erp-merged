<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Platform\Actions\SaveUserAction;
use App\Modules\Platform\Actions\SaveUserAuthorizationAction;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Support\UserManagementScope;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$release = $argv[1] ?? '';
$credentialsPath = $argv[2] ?? '';
$expectedCommit = $argv[3] ?? '';
$evidencePath = $argv[4] ?? '';
$assert = static function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};

try {
    $credentials = is_file($credentialsPath) ? json_decode((string) file_get_contents($credentialsPath), true, 512, JSON_THROW_ON_ERROR) : [];
    $database = (string) ($credentials['database'] ?? '');
    $assert(is_file($release.'/RELEASE_COMMIT') && preg_match('/^toyjoy_hf46_verify_[a-f0-9]{16}$/', $database) === 1, 'Invalid focused-verification target.');
    $assert(hash_equals($expectedCommit, trim((string) file_get_contents($release.'/RELEASE_COMMIT'))), 'Candidate commit mismatch.');

    foreach ([
        'DB_CONNECTION' => 'mysql',
        'DB_HOST' => 'localhost',
        'DB_DATABASE' => $database,
        'DB_USERNAME' => (string) ($credentials['username'] ?? ''),
        'DB_PASSWORD' => (string) ($credentials['password'] ?? ''),
        'DB_SOCKET' => (string) ($credentials['socket'] ?? ''),
        'CACHE_STORE' => 'array',
        'SESSION_DRIVER' => 'array',
        'QUEUE_CONNECTION' => 'sync',
        'MAIL_MAILER' => 'log',
        'APP_CONFIG_CACHE' => $release.'/bootstrap/cache/hotfix46-verification-config.php',
    ] as $name => $value) {
        putenv($name.'='.$value);
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }

    require $release.'/vendor/autoload.php';
    $app = require $release.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    $assert(DB::connection()->getDatabaseName() === $database, 'Focused verification is not isolated.');

    $owner = User::query()->whereKey(1)->where('username', 'hassan')->where('is_super_admin', true)->first();
    $affected = User::query()->whereKey(2)->where('username', 'karim')->first();
    $systemAdministrator = Role::query()->where('code', 'system-administrator')->where('status', 'active')->first();
    $assert($owner instanceof User && $affected instanceof User && $systemAdministrator instanceof Role, 'Required live-copy identities are unavailable.');

    Auth::login($owner);
    DB::beginTransaction();
    try {
        $suffix = bin2hex(random_bytes(8));
        $password = bin2hex(random_bytes(16));
        $created = app(SaveUserAction::class)->execute([
            'name' => 'Hotfix46 verification account',
            'username' => 'hf46-'.$suffix,
            'email' => 'hf46-'.$suffix.'@invalid.test',
            'password' => $password,
            'status' => 'active',
        ], [(int) $systemAdministrator->id], [], []);

        $created = $created->fresh();
        $assert($created instanceof User && $created->email_verified_at !== null, 'Admin-created account was not verified immediately.');
        $assert($created->roles()->whereKey($systemAdministrator->id)->exists(), 'Selected role was not preserved.');
        $assert($created->branchScopes()->doesntExist() && $created->storeScopes()->doesntExist(), 'Selected empty administrator scope was not preserved.');

        $createAudit = AuditLog::query()->where('event', 'create_user')->where('source_type', User::class)->where('source_id', (string) $created->id)->latest('id')->first();
        $verifyAudit = AuditLog::query()->where('event', 'verify_user_email')->where('source_type', User::class)->where('source_id', (string) $created->id)->latest('id')->first();
        $assert($createAudit?->actor_id === $owner->id && $verifyAudit?->actor_id === $owner->id, 'Creator/verifier actor was not recorded.');
        $assert(($verifyAudit?->metadata['verification_notification_sent'] ?? null) === false, 'Verification notification suppression was not recorded.');

        Auth::logout();
        $assert(Auth::attempt(['username' => $created->username, 'password' => $password]), 'Admin-created account could not log in.');
        $actor = Auth::user();
        $assert($actor instanceof User && $actor->id === $created->id, 'Authenticated identity mismatch.');

        $request = Request::create('/admin/authorization-baseline', 'GET');
        $request->setUserResolver(fn (): User => $actor);
        $response = app(EnsureEmailIsVerified::class)->handle($request, fn (): Response => new Response('', 204));
        $assert($response->getStatusCode() === 204, 'Email-verification middleware blocked the admin-created account.');

        $scope = app(UserManagementScope::class);
        $visibleUserIds = $scope->visibleUsers($actor)->orderBy('id')->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $assert(in_array(1, $visibleUserIds, true) && in_array(2, $visibleUserIds, true), 'System Administrator user-list access is incomplete.');
        $assert($scope->assignableRoles($actor)->contains('id', $systemAdministrator->id), 'System Administrator role assignment is unavailable.');

        app(SaveUserAuthorizationAction::class)->execute($affected, [(int) $systemAdministrator->id], [], [], 'active');
        $affected = $affected->fresh();
        $assert($affected instanceof User && $affected->roles()->whereKey($systemAdministrator->id)->exists(), 'Existing administrator role assignment did not persist.');
        $assert($affected->branchScopes()->doesntExist() && $affected->storeScopes()->doesntExist(), 'Existing administrator access scope changed unexpectedly.');

        $results = [
            'database' => $database,
            'transaction' => 'rolled_back',
            'admin_creation' => 'verified_without_notification',
            'creator_verifier_audit' => 'passed',
            'login' => 'passed',
            'verification_middleware' => 'passed',
            'user_list' => 'passed',
            'role_assignment' => 'passed',
            'preserved_accounts' => [1, 2],
        ];
        DB::rollBack();
    } catch (Throwable $exception) {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        throw $exception;
    }

    file_put_contents($evidencePath, json_encode($results, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n");
    echo 'HOTFIX46_FOCUSED_USER_MANAGEMENT=PASS database='.$database.' transaction=rolled_back creation=verified login=passed user_list=passed role_assignment=passed'.PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, 'HOTFIX46_FOCUSED_USER_MANAGEMENT=FAIL '.get_class($exception).' message='.$exception->getMessage().PHP_EOL);
    exit(1);
}
