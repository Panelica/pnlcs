<?php

use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\AdminTwoFactorVerify;
use App\Http\Middleware\AffiliateTracking;
use App\Http\Middleware\ApiKeyAuth;
use App\Http\Middleware\BlockBannedIp;
use App\Http\Middleware\CheckAdminPermission;
use App\Http\Middleware\MaintenanceMode;
use App\Http\Middleware\RedirectToInstaller;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\TwoFactorVerify;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')
                ->group(base_path('routes/admin.php'));
            Route::middleware('web')
                ->group(base_path('routes/client.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The dark-mode toggle writes this cookie from JavaScript, raw. Cookie
        // encryption made the server unable to read it, so every page was born
        // light and flashed dark after a script ran - and the theme attribute
        // the server renders was a lie. Unencrypted: it holds "dark" or
        // "light", nothing worth protecting.
        $middleware->encryptCookies(except: ['pnlcs_theme']);

        // Behind Panelica reverse proxy (Docker): trust the forwarded scheme
        // and host so asset()/route()/Vite URLs match the domain the request
        // actually arrived on, whatever it is. Without this a page served over
        // https emits http asset URLs and the browser blocks them as mixed
        // content. This is domain-agnostic: no per-domain APP_URL to maintain.
        $middleware->trustProxies(at: '*');
        $middleware->prependToGroup('web', RedirectToInstaller::class);
        $middleware->appendToGroup('web', AffiliateTracking::class);
        $middleware->appendToGroup('web', SetLocale::class);
        $middleware->appendToGroup('web', MaintenanceMode::class);
        // Counted before anything is checked, so a wrong key costs the caller
        // an attempt too.
        $middleware->throttleApi('api');
        $middleware->appendToGroup('api', ApiKeyAuth::class);
        $middleware->alias([
            'banned.ip' => BlockBannedIp::class,
            'admin.auth' => AdminAuthenticate::class,
            'admin.2fa' => AdminTwoFactorVerify::class,
            '2fa' => TwoFactorVerify::class,
            'admin.permission' => CheckAdminPermission::class,
        ]);

        $middleware->redirectGuestsTo(function ($request) {
            return str_starts_with($request->path(), 'client') ? route('client.login') : route('admin.login');
        });
    })
    ->withEvents(false)
    ->withExceptions(function (Exceptions $exceptions): void {
        // Model binding failure (ör. /admin/clients/999 — client yok)
        // → İlgili listeleme sayfasına flash mesajla döndür, generic 404 yerine
        $exceptions->render(function (NotFoundHttpException $e, $request) {
            // Only intercept when the 404 is caused by route model binding (ModelNotFoundException)
            $previous = $e->getPrevious();
            if (! $previous instanceof ModelNotFoundException) {
                return null; // leave generic 404 for real missing pages
            }
            $e = $previous; // work with the original
            $model = class_basename($e->getModel());
            $message = __('admin.errors.record_not_found', ['model' => $model]);

            // Admin alanı — adminin oturumu açık, admin paneline döndür
            if ($request->is('admin/*')) {
                $segments = explode('/', trim($request->path(), '/'));
                $section = $segments[1] ?? null;
                $route = 'admin.'.$section.'.index';
                try {
                    $target = Illuminate\Support\Facades\Route::has($route) ? route($route) : route('admin.dashboard');
                } catch (Throwable $inner) {
                    $target = url('/admin');
                }

                return redirect($target)->with('error', $message);
            }

            // Client alanı — müşteri paneline döndür
            if ($request->is('client/*')) {
                $segments = explode('/', trim($request->path(), '/'));
                $section = $segments[1] ?? null;
                $route = 'client.'.$section.'.index';
                try {
                    $target = Illuminate\Support\Facades\Route::has($route) ? route($route) : route('customer.dashboard');
                } catch (Throwable $inner) {
                    $target = url('/customer');
                }

                return redirect($target)->with('error', $message);
            }

            // Public / diğer — normal 404 akışına bırak
            return null;
        });
    })->create();
