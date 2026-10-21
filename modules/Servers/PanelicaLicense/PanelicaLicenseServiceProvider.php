<?php

namespace Modules\Servers\PanelicaLicense;

use Illuminate\Support\ServiceProvider;
use Modules\Servers\PanelicaLicense\Console\SyncLicensesCommand;

class PanelicaLicenseServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes.php');

        if ($this->app->runningInConsole()) {
            $this->commands([SyncLicensesCommand::class]);
        }
    }
}
