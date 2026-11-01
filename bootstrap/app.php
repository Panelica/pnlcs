<?php

use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\AdminTwoFactorVerify;
use App\Http\Middleware\AffiliateTracking;
use App\Http\Middleware\LocaleUrlPrefix;
use App\Http\Middleware\ApiKeyAuth;
use App\Http\Middleware\BlockBannedIp;
use App\Http\Middleware\CheckAdminPermission;
use App\Http\Middleware\MaintenanceMode;
use App\Http\Middleware\RedirectToInstaller;
use App\Http\Middleware\ResetOpcacheAfterUpdate;
use App\Http\Middleware\SetDisplayCurrency;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\TwoFactorVerify;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

// A copy that was only uploaded has no .env yet: it gets one, with a key,
// before the framework reads it, so the install wizard can open.
App\Support\FirstRunEnvironment::prepare(dirname(__DIR__));

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
        // laravel_maintenance: the maintenance bypass cookie Setup -> Updates
        // gives the administrator who starts an update. It is checked before
        // cookies are decrypted, and is signed with the maintenance secret.
        $middleware->encryptCookies(except: ['pnlcs_theme', 'laravel_maintenance']);

        // A file merged by hand on Setup -> Updates is saved exactly as typed:
        // trimming would drop its last line break and change the file.
        $middleware->trimStrings(except: ['resolved_text.*']);

        // Behind Panelica reverse proxy (Docker): trust the forwarded scheme
        // and host so asset()/route()/Vite URLs match the domain the request
        // actually arrived on, whatever it is. Without this a page served over
        // https emits http asset URLs and the browser blocks them as mixed
        // content. This is domain-agnostic: no per-domain APP_URL to maintain.
        // Every proxy is trusted for the client's address, the port, the scheme
        // and the prefix - but NOT for the host. Laravel's default set includes
        // X-Forwarded-Host, and with it anybody could decide what host this
        // application thought it was answering on, which is how a password
        // reset link ended up pointing at somebody else's server (reported
        // privately, twice, in October 2026). Mail links no longer come from
        // the request at all (App\Support\MailLink); this closes the same door
        // for every other generated URL.
        $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PROTO
            | Request::HEADER_X_FORWARDED_PREFIX
            | Request::HEADER_X_FORWARDED_AWS_ELB);
        $middleware->prepend(ResetOpcacheAfterUpdate::class);
        // Language in the address: /en/... is routed as /... with English
        // chosen (LocaleUrl). Before routing, after the proxy is trusted.
        $middleware->append(LocaleUrlPrefix::class);
        $middleware->prependToGroup('web', RedirectToInstaller::class);
        // Framing, type sniffing and referrers: see SecurityHeaders.
        $middleware->appendToGroup('web', \App\Http\Middleware\SecurityHeaders::class);
        $middleware->appendToGroup('web', AffiliateTracking::class);
        $middleware->appendToGroup('web', \App\Http\Middleware\PromoLink::class);
        $middleware->appendToGroup('web', SetLocale::class);
        // After the session has started and the locale is set: which currency
        // the page shows prices in (CustomerCurrency).
        $middleware->appendToGroup('web', SetDisplayCurrency::class);
        $middleware->appendToGroup('web', MaintenanceMode::class);
        // Counted before anything is checked, so a wrong key costs the caller
        // an attempt too.
        $middleware->throttleApi('api');
        $middleware->appendToGroup('api', ApiKeyAuth::class);
        $middleware->alias([
            // Counted per route, not one counter per user for every route.
            'throttle' => \App\Http\Middleware\ThrottleRequests::class,
            'banned.ip' => BlockBannedIp::class,
            'admin.auth' => AdminAuthenticate::class,
            'admin.2fa' => AdminTwoFactorVerify::class,
            '2fa' => TwoFactorVerify::class,
            'admin.permission' => CheckAdminPermission::class,
            'client.permission' => \App\Http\Middleware\CheckClientPermission::class,
            'client.confirm' => \App\Http\Middleware\ConfirmSensitiveAction::class,
        ]);

        $middleware->redirectGuestsTo(function ($request) {
            return str_starts_with($request->path(), 'client') ? route('client.login') : route('admin.login');
        });
    })
    ->withEvents(false)
    ->withExceptions(function (Exceptions $exceptions): void {
        // The API answers every error as {"result":"error","message":...}, the
        // shape WHMCS-compatible clients test. Three kinds came back in the
        // framework's own shape instead, without "result": a field that failed
        // validation (422), an action that does not exist (404) and the wrong
        // method (405). A client checking result saw none of them as errors.
        // The framework's "errors" list is kept alongside, for callers that
        // already read it.
        $exceptions->render(function (Illuminate\Validation\ValidationException $e, $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json(['result' => 'error', 'message' => $e->getMessage(), 'errors' => $e->errors()], $e->status);
        });
        $exceptions->render(function (Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException $e, $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'result' => 'error',
                'message' => 'This action is called with '.($e->getHeaders()['Allow'] ?? 'another method').'.',
            ], 405, $e->getHeaders());
        });
        $exceptions->render(function (NotFoundHttpException $e, $request) {
            if (! $request->is('api/*') || $e->getPrevious() instanceof ModelNotFoundException) {
                return null;
            }

            return response()->json(['result' => 'error', 'message' => 'There is no API action at this address.'], 404);
        });
        // Anything else the framework answers on the API's behalf - above all
        // 429 when the rate limit is reached - keeps its status and headers
        // (Retry-After) and gains the same shape.
        $exceptions->render(function (Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e, $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $message = $e->getMessage() !== '' ? $e->getMessage() : (Symfony\Component\HttpFoundation\Response::$statusTexts[$e->getStatusCode()] ?? 'Error');

            return response()->json(['result' => 'error', 'message' => $message], $e->getStatusCode(), $e->getHeaders());
        });

        // A hosting panel that does not answer. The customer's hosting tabs
        // (email, DNS, files, databases, ...) all ask the panel first, and an
        // unreachable panel threw past them as a 500. The customer is taken to
        // the service page with a message instead, or back to the form they
        // sent. The service page itself is left to fail as before, so this can
        // never send a request in a circle.
        //
        // A form the panel received but did not answer in time (a container
        // being deployed, a backup) may still be carried out there. Telling the
        // customer to try again could do it twice, so they are asked to look
        // first. A page that was only being read has nothing to repeat. The
        // file editor's content is not kept: the editor reads the file from
        // the panel again.
        $exceptions->render(function (Illuminate\Http\Client\ConnectionException $e, $request) {
            if ($request->expectsJson() || ! $request->routeIs('client.services.*') || $request->routeIs('client.services.show')) {
                return null;
            }
            $sent = App\Support\ConnectionFailure::requestWasSent($e);
            Illuminate\Support\Facades\Log::warning('Hosting panel unreachable', ['route' => $request->route()?->getName(), 'request_sent' => $sent, 'error' => $e->getMessage()]);
            $message = __($sent && ! $request->isMethod('GET') ? 'client.hosting.panel_no_answer_in_time' : 'client.hosting.panel_unreachable');
            $service = $request->route('service');

            if ($request->isMethod('GET') && $service) {
                return redirect()->route('client.services.show', $service)->with('error', $message);
            }

            return back()->withInput($request->except(['password', 'password_confirmation', 'content']))->with('error', $message);
        });

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
