<?php

namespace Modules\CompanyLookup;

use Illuminate\Support\ServiceProvider;

class CompanyLookupServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/config/company_lookup.php', 'company_lookup');

        $this->app->singleton(GusCompanyProvider::class, function () {
            $settings = CompanyLookupSettings::resolve();

            return new GusCompanyProvider(
                (string) $settings['gus']['endpoint'],
                $settings['gus']['key'] ? (string) $settings['gus']['key'] : null,
                (int) $settings['http']['connect_timeout'],
                (int) $settings['http']['request_timeout'],
            );
        });

        $this->app->singleton(MfVatProvider::class, function () {
            $settings = CompanyLookupSettings::resolve();

            return new MfVatProvider(
                (string) $settings['mf']['endpoint'],
                (int) $settings['http']['connect_timeout'],
                (int) $settings['http']['request_timeout'],
            );
        });

        $this->app->singleton(CeidgCompanyProvider::class, function () {
            $settings = CompanyLookupSettings::resolve();

            return new CeidgCompanyProvider(
                (string) $settings['ceidg']['endpoint'],
                $settings['ceidg']['key'] ? (string) $settings['ceidg']['key'] : null,
                (int) $settings['http']['connect_timeout'],
                (int) $settings['http']['request_timeout'],
            );
        });

        $this->app->singleton(OpenbrisCompanyProvider::class, function () {
            $settings = CompanyLookupSettings::resolve();

            return new OpenbrisCompanyProvider(
                (string) $settings['openbris']['endpoint'],
                $settings['openbris']['key'] ? (string) $settings['openbris']['key'] : null,
                (int) $settings['http']['connect_timeout'],
                (int) $settings['http']['request_timeout'],
            );
        });

        $this->app->singleton(DataNormalizer::class);

        $this->app->singleton(CompanyLookupService::class, function ($app) {
            $settings = CompanyLookupSettings::resolve();

            return new CompanyLookupService(
                $app->make(GusCompanyProvider::class),
                $app->make(MfVatProvider::class),
                $app->make(CeidgCompanyProvider::class),
                $app->make(OpenbrisCompanyProvider::class),
                $app->make(DataNormalizer::class),
                (int) $settings['cache_ttl'],
            );
        });
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes.php');
    }
}
