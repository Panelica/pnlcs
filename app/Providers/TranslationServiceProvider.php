<?php

namespace App\Providers;

use App\Translation\DbTranslationLoader;
use Illuminate\Translation\TranslationServiceProvider as BaseTranslationServiceProvider;

class TranslationServiceProvider extends BaseTranslationServiceProvider
{
    public function register(): void
    {
        $this->registerLoader();

        $this->app->singleton('translator', function ($app) {
            $loader = $app['translation.loader'];
            $locale = $app->getLocale();

            $trans = new \Illuminate\Translation\Translator($loader, $locale);
            $trans->setFallback($app->getFallbackLocale());

            return $trans;
        });
    }

    protected function registerLoader(): void
    {
        $this->app->singleton('translation.loader', function ($app) {
            return new DbTranslationLoader(
                $app['files'],
                $app['path.lang']
            );
        });
    }
}
