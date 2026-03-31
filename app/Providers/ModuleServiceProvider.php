<?php

namespace App\Providers;

use App\Services\Module\ModuleRegistry;
use Illuminate\Support\ServiceProvider;

class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ModuleRegistry::class, function () {
            $registry = new ModuleRegistry();

            // Built-in server modules
            $registry->registerServer('custom', \Modules\Servers\Custom\CustomModule::class);

            // Built-in gateway modules
            $registry->registerGateway('banktransfer', \Modules\Gateways\BankTransfer\BankTransferModule::class);
            $registry->registerGateway('stripe', \Modules\Gateways\Stripe\StripeModule::class);
            $registry->registerGateway('paypal', \Modules\Gateways\PayPal\PayPalModule::class);
            $registry->registerGateway('authorize', \Modules\Gateways\AuthorizeNet\AuthorizeNetModule::class);

            // Panelica hosting panel module
            if (class_exists(\Modules\Servers\Panelica\PanelicaModule::class)) {
                $registry->registerServer('panelica', \Modules\Servers\Panelica\PanelicaModule::class);
            }

            // cPanel/WHM module
            if (class_exists(\Modules\Servers\CPanel\CPanelModule::class)) {
                $registry->registerServer('cpanel', \Modules\Servers\CPanel\CPanelModule::class);
            }

            // Plesk Obsidian module
            $registry->registerServer('plesk', \Modules\Servers\Plesk\PleskModule::class);

            // DirectAdmin module
            $registry->registerServer('directadmin', \Modules\Servers\DirectAdmin\DirectAdminModule::class);

            return $registry;
        });
    }
}
