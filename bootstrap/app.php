<?php

use App\Http\Middleware\RevokeInactiveUserSession;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\SetRequestId;
use App\Models\User;
use App\Modules\Platform\Support\AuthenticatedLanding;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(SetRequestId::class);
        // Locale is a non-sensitive display preference. It must be readable
        // before route middleware so unmatched safe-error pages preserve RTL.
        $middleware->encryptCookies(['locale']);
        // Route middleware is not reached for an unmatched route, so locale
        // selection also runs globally from the non-sensitive locale cookie.
        $middleware->append(SetLocale::class);
        $middleware->redirectUsersTo(static function (Request $request): string {
            $user = $request->user();

            return $user instanceof User
                ? app(AuthenticatedLanding::class)->destination($user)
                : route('dashboard');
        });
        $middleware->web(append: [
            SetLocale::class,
            RevokeInactiveUserSession::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
