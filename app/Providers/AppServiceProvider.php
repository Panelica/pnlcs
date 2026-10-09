<?php

namespace App\Providers;

use App\Models\Setting;
use App\Models\EmailTemplate;
use App\Models\Language;
use App\Observers\EmailTemplateObserver;
use App\Observers\LanguageObserver;
use App\Services\ThemeManager;
use App\Services\ReportManager;
use App\Services\WidgetManager;
use App\Services\AddonManager;
use App\View\Composers\ThemeComposer;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(\App\Contracts\MailboxClientInterface::class, \App\Services\Mail\ImapMailboxClient::class);
        // Phone verification: Twilio Verify when it is set up, otherwise codes
        // of our own sent through an SmsSender an addon has bound; neither
        // means verification is off.
        $this->app->bind(\App\Contracts\PhoneVerifier::class, function ($app) {
            $twilio = $app->make(\App\Services\Sms\TwilioVerifyClient::class);
            if ($twilio->enabled() || ! $app->bound(\App\Contracts\SmsSender::class)) {
                return $twilio;
            }

            return new \App\Services\Sms\SmsCodeVerifier($app->make(\App\Contracts\SmsSender::class));
        });
        $this->app->singleton(ThemeManager::class);
        $this->app->singleton(ReportManager::class);
        $this->app->singleton(AddonManager::class);
        $this->app->singleton(WidgetManager::class, function () {
            $m = new WidgetManager();
            $m->register("overview", new \App\Widgets\OverviewWidget());
            $m->register("billing", new \App\Widgets\BillingWidget());
            $m->register("support", new \App\Widgets\SupportWidget());
            $m->register("clients", new \App\Widgets\ClientsWidget());
            $m->register("orders", new \App\Widgets\OrdersWidget());
            $m->register("services", new \App\Widgets\ServicesWidget());
            $m->register("domains", new \App\Widgets\DomainsWidget());
            $m->register("registrar_balance", new \App\Widgets\RegistrarBalanceWidget());
            $m->register("todo", new \App\Widgets\ToDoWidget());
            $m->register("health", new \App\Widgets\HealthWidget());
            $m->register("automation", new \App\Widgets\AutomationWidget());
            $m->register("activity", new \App\Widgets\ActivityWidget());
            $m->register("staff", new \App\Widgets\StaffWidget());
            return $m;
        });
    }

    public function boot(): void
    {
        $this->useConfiguredDomainForConsoleLinks();

        // Tracking codes and the cookie consent bar (Setup > General): on every
        // client page and theme that prints these hook points, never in the
        // admin area. Nothing is added until the operator sets something.
        add_hook('ClientAreaHeadOutput', 0, fn () => \App\Support\Tracking::head());
        add_hook('ClientAreaFooterOutput', 0, fn () => \App\Support\Tracking::footer());

        // Language in the address (Setup > Languages): links made on a page,
        // or in an e-mail, in another language carry its prefix. Off by default.
        // Set on the URL generator whenever it is made, without making it here:
        // resolving it during boot would change when the container builds it.
        $format = fn ($url) => $url->formatPathUsing(fn ($path, $route = null) => \App\Support\LocaleUrl::formatPath($path, $route));
        $this->app->afterResolving('url', $format);
        if ($this->app->resolved('url')) {
            $format($this->app->make('url'));
        }
        // A queue worker lives for hours: each job reads the setting afresh,
        // so a mail sent after it is switched off carries no prefix that no
        // longer opens.
        \Illuminate\Support\Facades\Queue::before(fn () => \App\Support\LocaleUrl::forget());

        // How many times the API will let someone try.
        //
        // The admin login form allows ten attempts a minute. The API accepts
        // the same admin username and password - it is there for WHMCS-shaped
        // clients - and had no limit at all, so the form's limit could be
        // walked around by posting the guesses to any API endpoint instead.
        //
        // A caller presenting a credential is counted on that credential, so
        // one integration cannot use up another's allowance and guessing from
        // a new address cannot lock a working one out. A caller presenting
        // none is counted on its address, and gets far less room: there is no
        // honest reason to call this API anonymously more than a few times a
        // minute.
        RateLimiter::for('api', function (Request $request) {
            $credential = $request->header('X-API-Key')
                ?? $request->bearerToken()
                ?? $request->input('api_key')
                ?? $request->input('identifier');

            if ($credential) {
                return Limit::perMinute(300)->by('api-key:'.sha1((string) $credential));
            }

            return Limit::perMinute(10)->by('api-ip:'.$request->ip());
        });
        // Theme Engine: prepend active theme's view directory
        try {
            $this->app->make(ThemeManager::class)->applyViewPaths();
        } catch (\Throwable $e) {
            // Silently fail during install/migrate when DB is not ready
        }

        // Theme Composer — injects CSS variables + branding + theme assets into layouts
        View::composer([
            'admin.layouts.app',
            'client.layouts.app',
            'welcome',
            'client.auth.login',
            'client.auth.register',
            'sections.*',
            'legal.*',
            'pages.*',
        ], ThemeComposer::class);

        EmailTemplate::observe(EmailTemplateObserver::class);
        Language::observe(LanguageObserver::class);
    }

    /**
     * Address the links in mail sent from the queue or a cron.
     *
     * In a web request Laravel builds links from the request itself, which is
     * right. With no request - a queue worker, a scheduled job - it falls back
     * to the configured app URL, and in a container that URL comes from an
     * environment variable that overrides .env. On our own install that is the
     * host and port inside the network, so the "view your invoice" link in
     * customer mail pointed somewhere nobody outside the box can reach.
     *
     * The operator already tells us the address in the general settings, so
     * that is what console-generated links use. If it is not set, nothing
     * changes.
     */
    private function useConfiguredDomainForConsoleLinks(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        try {
            $domain = trim((string) Setting::get('Domain', ''));
        } catch (\Throwable $e) {
            // Install and migrate run before the table exists.
            return;
        }

        if ($domain === '') {
            return;
        }
        if (! preg_match('#^https?://#i', $domain)) {
            $domain = 'https://'.$domain;
        }
        if (! filter_var($domain, FILTER_VALIDATE_URL)) {
            return;
        }

        URL::forceRootUrl(rtrim($domain, '/'));

        // The scheme has to be forced too, not just the root. Laravel takes the
        // scheme from the request, and the console request is built from APP_URL,
        // so an operator who configures http:// still had https:// links written
        // into customer mail.
        URL::forceScheme(str_starts_with(strtolower($domain), 'https://') ? 'https' : 'http');
    }
}
