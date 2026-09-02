<?php

namespace Modules\Ksef;

use Illuminate\Support\ServiceProvider;
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
                $app->make(\App\Services\AddonManager::class),
            );
        });
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes.php');
    }
}
