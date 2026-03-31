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
            $registry->registerServer("custom", \Modules\Servers\Custom\CustomModule::class);
            $registry->registerGateway("banktransfer", \Modules\Gateways\BankTransfer\BankTransferModule::class);
            return $registry;
        });
    }
}
