<?php

use App\Auth\Http\Middleware\EnsureRegistrationInvite;
use App\Shared\Http\Middleware\HandleAppearance;
use App\Shared\Http\Middleware\HandleInertiaRequests;
use App\Shared\Http\Middleware\UseRequestRootUrl;
use App\Shared\Http\SoftFail;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Sentry\Laravel\Integration;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        __DIR__.'/../app/Auth/Console',
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            UseRequestRootUrl::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'registration.invite' => EnsureRegistrationInvite::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        Integration::handles($exceptions);

        $exceptions->render(fn (NotFoundHttpException $e, Request $request): ?RedirectResponse => SoftFail::maybeRedirectNotFound($request, $e));

        $exceptions->render(fn (AccessDeniedHttpException $e, Request $request): ?RedirectResponse => SoftFail::maybeRedirectForbidden($request));
    })->create();
