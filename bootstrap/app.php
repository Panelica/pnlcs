<?php

use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\CheckAdminPermission;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__."/../routes/web.php",
        commands: __DIR__."/../routes/console.php",
        health: "/up",
        then: function () {
            Route::middleware("web")
                ->group(base_path("routes/admin.php"));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            "admin.auth" => AdminAuthenticate::class,
            "admin.permission" => CheckAdminPermission::class,
        ]);

        $middleware->redirectGuestsTo(fn ($request) => route("admin.login"));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
