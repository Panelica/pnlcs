<?php

namespace App\Providers;

use App\Models\Setting;
use App\Services\Module\ModuleRegistry;
use App\Services\ThemeManager;
use App\View\Composers\ThemeComposer;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ModuleRegistry::class);
        $this->app->singleton(ThemeManager::class);
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
