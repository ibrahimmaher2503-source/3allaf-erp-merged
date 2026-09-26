<?php

declare(strict_types=1);

use App\Http\Responses\PosAwareLoginResponse;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

$release = realpath((string) ($argv[1] ?? ''));
$userId = filter_var($argv[2] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

if ($release === false || ! is_file($release.'/bootstrap/app.php') || $userId === false) {
    fwrite(STDERR, "INVALID_ARGUMENTS\n");
    exit(2);
}

require $release.'/vendor/autoload.php';
$app = require $release.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$user = User::query()->findOrFail($userId);
$session = app('session')->driver();
$session->setId(Str::random(40));
$session->start();
Auth::guard('web')->login($user);
$session->put('_previous.url', 'https://rajeh.xyz/login');
$session->save();

$sessionId = $session->getId();
$sessionFile = rtrim((string) config('session.files'), '/').'/'.$sessionId;

if (! is_file($sessionFile)) {
    throw new RuntimeException('The focused session file was not created.');
}

$runtime = posix_getpwnam('rajeh');
if (! is_array($runtime)
    || ! chown($sessionFile, (int) $runtime['uid'])
    || ! chgrp($sessionFile, (int) $runtime['gid'])
    || ! chmod($sessionFile, 0660)) {
    throw new RuntimeException('The focused session file could not be handed to the runtime user.');
}

$cookieName = (string) config('session.cookie');
$encrypter = app('encrypter');
$cookieValue = $encrypter->encrypt(
    CookieValuePrefix::create($cookieName, $encrypter->getKey()).$sessionId,
    false,
);
$cookie = $cookieName.'='.rawurlencode($cookieValue);

/** @return array{status:int,location:?string,marker:bool} */
function requestPage(string $url, string $cookie, ?string $marker = null): array
{
    $headers = [];
    $handle = curl_init($url);
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_HTTPHEADER => ['Cookie: '.$cookie, 'Accept: text/html'],
        CURLOPT_HEADERFUNCTION => static function ($curl, string $header) use (&$headers): int {
            $length = strlen($header);
            $parts = explode(':', $header, 2);
            if (count($parts) === 2) {
                $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
            }

            return $length;
        },
    ]);
    $body = curl_exec($handle);
    $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $error = curl_error($handle);
    curl_close($handle);

    if (! is_string($body) || $error !== '') {
        throw new RuntimeException('HTTPS request failed: '.$error);
    }

    return [
        'status' => $status,
        'location' => $headers['location'] ?? null,
        'marker' => $marker !== null && str_contains($body, $marker),
    ];
}

try {
    $loginRequest = Request::create('/login', 'POST');
    $loginRequest->setUserResolver(static fn () => $user);
    $loginRequest->setLaravelSession($session);
    $loginTarget = app(PosAwareLoginResponse::class)->toResponse($loginRequest)->getTargetUrl();

    $dashboard = requestPage(
        'https://rajeh.xyz/dashboard',
        $cookie,
        $userId === 1 ? 'data-guide="dashboard-header"' : null,
    );
    $login = requestPage('https://rajeh.xyz/login', $cookie);
    $verification = requestPage('https://rajeh.xyz/email/verify', $cookie);
    $setup = requestPage(
        'https://rajeh.xyz/initial-setup',
        $cookie,
        $userId === 1 ? 'data-guide="initial-setup-header"' : null,
    );
    $scopeSetup = requestPage(
        'https://rajeh.xyz/admin/authorization-baseline?setup=true&setup_step=users-scopes',
        $cookie,
        'data-guide="auth-header"',
    );

    foreach ([
        'login_response' => ['status' => 302, 'location' => $loginTarget, 'marker' => false],
        'dashboard' => $dashboard,
        'authenticated_login' => $login,
        'verification_notice' => $verification,
        'initial_setup' => $setup,
        'user_scope_setup' => $scopeSetup,
    ] as $step => $result) {
        printf(
            "%s status=%d location=%s rendered_marker=%s\n",
            $step,
            $result['status'],
            $result['location'] ?? 'none',
            $result['marker'] ? 'yes' : 'no',
        );
    }

    $scopePath = '/admin/authorization-baseline?setup=true&setup_step=users-scopes';
    $dashboardPath = '/dashboard';
    $locationEndsWith = static fn (?string $location, string $path): bool => is_string($location)
        && str_ends_with($location, $path);

    if ($userId === 2) {
        $passed = $locationEndsWith($loginTarget, $scopePath)
            && $dashboard['status'] === 302 && $locationEndsWith($dashboard['location'], $scopePath)
            && $login['status'] === 302 && $locationEndsWith($login['location'], $scopePath)
            && $verification['status'] === 302 && $locationEndsWith($verification['location'], $dashboardPath)
            && $setup['status'] === 302 && $locationEndsWith($setup['location'], $scopePath)
            && $scopeSetup['status'] === 200 && $scopeSetup['marker'];
    } elseif ($userId === 1) {
        $passed = $locationEndsWith($loginTarget, $dashboardPath)
            && $dashboard['status'] === 200 && $dashboard['marker']
            && $login['status'] === 302 && $locationEndsWith($login['location'], $dashboardPath)
            && $verification['status'] === 302 && $locationEndsWith($verification['location'], $dashboardPath)
            && $setup['status'] === 200 && $setup['marker'];
    } else {
        $passed = false;
    }

    if (! $passed) {
        throw new RuntimeException('The authenticated navigation chain did not terminate as expected.');
    }

    printf("AUTHENTICATED_NAVIGATION=PASS user=%d/%s\n", $user->id, $user->username);
} finally {
    @unlink($sessionFile);
}
