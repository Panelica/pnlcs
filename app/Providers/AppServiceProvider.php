<?php

namespace App\Providers;

use App\Models\Setting;
use App\Services\Module\ModuleRegistry;
use App\Services\ThemeManager;
use App\Services\ReportManager;
use App\Services\WidgetManager;
use App\Services\AddonManager;
use App\View\Composers\ThemeComposer;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ModuleRegistry::class);
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
        $registry = $this->app->make(ModuleRegistry::class);

        // Server modules
        $registry->registerServer("custom",      \Modules\Servers\Custom\CustomModule::class);
        $registry->registerServer("panelica",    \Modules\Servers\Panelica\PanelicaModule::class);
        $registry->registerServer("cpanel",      \Modules\Servers\CPanel\CPanelModule::class);

        // Gateway modules
        $registry->registerGateway("banktransfer", \Modules\Gateways\BankTransfer\BankTransferModule::class);
        $registry->registerGateway("paypal",       \Modules\Gateways\PayPal\PayPalModule::class);
        $registry->registerGateway("stripe",       \Modules\Gateways\Stripe\StripeModule::class);
        $registry->registerGateway("authorize",    \Modules\Gateways\AuthorizeNet\AuthorizeNetModule::class);

        // Registrar modules
        $registry->registerRegistrar("manual", \Modules\Registrars\Manual\ManualRegistrar::class);
        $registry->registerRegistrar("enom",   \Modules\Registrars\Enom\EnomRegistrar::class);

        // Theme Engine: prepend active theme's view directory
        try {
            $themeManager = $this->app->make(ThemeManager::class);
            $viewPath = $themeManager->getViewPath();
            if ($viewPath) {
                $this->app['view']->prependLocation($viewPath);
            }
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
        ], ThemeComposer::class);
    }
}
