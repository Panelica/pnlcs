<?php

use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\AdminTwoFactorVerify;
use App\Http\Middleware\TwoFactorVerify;
use App\Http\Middleware\AffiliateTracking;
use App\Http\Middleware\CheckAdminPermission;
use App\Http\Middleware\SetLocale;
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
        $middleware->prependToGroup("web", \App\Http\Middleware\RedirectToInstaller::class);
        $middleware->appendToGroup("web", AffiliateTracking::class);
        $middleware->appendToGroup("web", SetLocale::class);
        $middleware->appendToGroup("api", \App\Http\Middleware\ApiKeyAuth::class);
        $middleware->alias([
            "admin.auth" => AdminAuthenticate::class,
            "admin.2fa" => AdminTwoFactorVerify::class,
            "2fa" => TwoFactorVerify::class,
            "admin.permission" => CheckAdminPermission::class,
        ]);

        $middleware->redirectGuestsTo(function ($request) { return str_starts_with($request->path(), "client") ? route("client.login") : route("admin.login"); });
    })
    ->withEvents(false)
    ->withExceptions(function (Exceptions $exceptions): void {
        // Model binding failure (ör. /admin/clients/999 — client yok)
        // → İlgili listeleme sayfasına flash mesajla döndür, generic 404 yerine
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, $request) {
            // Only intercept when the 404 is caused by route model binding (ModelNotFoundException)
            $previous = $e->getPrevious();
            if (!$previous instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                return null; // leave generic 404 for real missing pages
            }
            $e = $previous; // work with the original
            $model = class_basename($e->getModel());
            $message = __('admin.errors.record_not_found', ['model' => $model]);

            // Admin alanı — adminin oturumu açık, admin paneline döndür
            if ($request->is('admin/*')) {
                $segments = explode('/', trim($request->path(), '/'));
                $section = $segments[1] ?? null;
                $route = 'admin.' . $section . '.index';
                try {
                    $target = \Illuminate\Support\Facades\Route::has($route) ? route($route) : route('admin.dashboard');
                } catch (\Throwable $inner) {
                    $target = url('/admin');
                }
                return redirect($target)->with('error', $message);
            }

            // Client alanı — müşteri paneline döndür
            if ($request->is('client/*')) {
                $segments = explode('/', trim($request->path(), '/'));
                $section = $segments[1] ?? null;
                $route = 'client.' . $section . '.index';
                try {
                    $target = \Illuminate\Support\Facades\Route::has($route) ? route($route) : route('customer.dashboard');
                } catch (\Throwable $inner) {
                    $target = url('/customer');
                }
                return redirect($target)->with('error', $message);
            }

            // Public / diğer — normal 404 akışına bırak
            return null;
        });
    })->create();
