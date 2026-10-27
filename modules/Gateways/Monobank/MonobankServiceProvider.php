<?php

declare(strict_types=1);

namespace Modules\Gateways\Monobank;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

/**
 * Registered by PNLCS because pnlcs.json names it ("provider"). Everything the gateway needs lives in this
 * folder, so a PNLCS update never has to touch core files: routes, migrations and the reconciliation job.
 * A failing provider is logged and skipped by PNLCS; it cannot take other modules down.
 */
final class MonobankServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Run by `php artisan migrate` (a step of every PNLCS update). Additive and guarded.
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        // Skipped by Laravel when routes are cached: run `php artisan optimize` after installing/updating.
        $this->loadRoutesFrom(__DIR__.'/routes.php');

        // Needs PNLCS's normal scheduler cron line (`* * * * * php artisan schedule:run`).
        $this->app->booted(function (): void {
            $schedule = $this->app->make(Schedule::class);
            $schedule->call(function (): void {
                $registry = app(\App\Services\Module\ModuleRegistry::class);
                $module = $registry->getGatewayModule('monobank');
                if (!$module instanceof MonobankModule) {
                    return;
                }
                // Keep reconciling after the gateway is switched off: paid-but-unconfirmed sessions must still resolve.
                // Only skip when there is no token at all (never configured).
                if (!$module->hasToken()) {
                    return;
                }
                $module->reconcilePending(20);   // payments whose webhook was lost
                $module->reconcileOpenRefunds(); // refunds that were never confirmed
            })->name('monobank-reconcile')->everyFiveMinutes()->withoutOverlapping(10);
        });
    }
}
