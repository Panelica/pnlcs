<?php

namespace Modules\Ksef;

use App\Services\AddonManager;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Modules\Ksef\Console\ImportKsefInvoicesCommand;
use Modules\Ksef\Support\InvoiceXmlBuilder;
use Modules\Ksef\Support\XadesSigner;

class KsefServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/config/ksef.php', 'ksef');

        $this->app->singleton(InvoiceXmlBuilder::class);
        $this->app->singleton(XadesSigner::class);

        $this->app->singleton(KsefClient::class, fn ($app) => new KsefClient(
            $app->make(InvoiceXmlBuilder::class),
            $app->make(XadesSigner::class),
        ));

        $this->app->singleton(KsefService::class, function ($app) {
            return new KsefService(
                $app->make(KsefClient::class),
                $app->make(AddonManager::class),
            );
        });
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes.php');

        if ($this->app->runningInConsole()) {
            $this->commands([ImportKsefInvoicesCommand::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            // The task only exists while the addon is switched on. The command
            // also checks this itself; keeping it out of the schedule entirely
            // avoids a permanently failing row on the automation screen.
            if (! $this->app->make(AddonManager::class)->isActive('ksef')) {
                return;
            }

            $schedule->command('pnlcs:ksef-import-invoices')
                ->everyFourHours()
                ->withoutOverlapping(20);
        });
    }
}
