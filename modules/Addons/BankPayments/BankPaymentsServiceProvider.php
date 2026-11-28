<?php

namespace Modules\Addons\BankPayments;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Modules\Addons\BankPayments\Console\RematchBankPaymentsCommand;
use Modules\Addons\BankPayments\Console\SyncBankPaymentsCommand;
use Modules\Addons\BankPayments\Services\BankMailboxClient;
use Modules\Addons\BankPayments\Services\BankMessageParserRegistry;
use Modules\Addons\BankPayments\Services\ImapBankMailboxClient;

class BankPaymentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/config/bank-payments.php', 'bank-payments');

        $this->app->bind(BankMailboxClient::class, ImapBankMailboxClient::class);
        $this->app->singleton(BankMessageParserRegistry::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes.php');
        $this->loadViewsFrom(__DIR__.'/views', 'bank-payments');
        $this->loadTranslationsFrom(__DIR__.'/lang', 'bank-payments');
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                RematchBankPaymentsCommand::class,
                SyncBankPaymentsCommand::class,
            ]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('pnlcs:bank-payments:sync')
                ->everyFiveMinutes()
                ->withoutOverlapping(10);
        });
    }
}
