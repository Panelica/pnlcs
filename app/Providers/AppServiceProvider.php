<?php

namespace App\Providers;

use App\Services\Module\ModuleRegistry;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ModuleRegistry::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $registry = $this->app->make(ModuleRegistry::class);
        $registry->registerServer('custom',   \Modules\Servers\Custom\CustomModule::class);
        $registry->registerServer('panelica', \Modules\Servers\Panelica\PanelicaModule::class);
        $registry->registerServer('cpanel',   \Modules\Servers\CPanel\CPanelModule::class);
        $registry->registerGateway('banktransfer', \Modules\Gateways\BankTransfer\BankTransferModule::class);
        $registry->registerGateway('paypal',      \Modules\Gateways\PayPal\PayPalModule::class);
        $registry->registerGateway('stripe',      \Modules\Gateways\Stripe\StripeModule::class);
        $registry->registerGateway('authorize',   \Modules\Gateways\AuthorizeNet\AuthorizeNetModule::class);
    }
}
