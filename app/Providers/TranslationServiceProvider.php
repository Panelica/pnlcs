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

    /**
     * The framework's own lang directory first, then the application's, as
     * Laravel's provider does. Given only the application's, every validation
     * rule without a line in lang/<locale>/validation.php - 97 of the
     * framework's 107, among them "in", "date", "integer" and "exists" -
     * showed its raw key ("validation.in") to the person filling the form and
     * to API callers.
     */
    protected function registerLoader(): void
    {
        $this->app->singleton('translation.loader', function ($app) {
            $framework = dirname((new \ReflectionClass(BaseTranslationServiceProvider::class))->getFileName()).'/lang';

            return new DbTranslationLoader(
                $app['files'],
                [$framework, $app['path.lang']]
            );
        });
    }
}
