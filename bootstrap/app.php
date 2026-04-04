<?php

use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\AdminTwoFactorVerify;
use App\Http\Middleware\TwoFactorVerify;
use App\Http\Middleware\AffiliateTracking;
use App\Http\Middleware\CheckAdminPermission;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__."/../routes/web.php",
        api: __DIR__."/../routes/api.php",
        commands: __DIR__."/../routes/console.php",
        health: "/up",
        then: function () {
            Route::middleware("web")
                ->group(base_path("routes/admin.php"));
            Route::middleware("web")
                ->group(base_path("routes/client.php"));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->appendToGroup("web", AffiliateTracking::class);
        $middleware->appendToGroup("api", \App\Http\Middleware\ApiKeyAuth::class);
        $middleware->alias([
            "admin.auth" => AdminAuthenticate::class,
            "admin.2fa" => AdminTwoFactorVerify::class,
            "2fa" => TwoFactorVerify::class,
            "admin.permission" => CheckAdminPermission::class,
        ]);

        $middleware->redirectGuestsTo(function ($request) { return str_starts_with($request->path(), "client") ? route("client.login") : route("admin.login"); });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
