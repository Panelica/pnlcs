<?php

namespace App\Providers;

use App\Services\AddonManager;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

/*
 * An active addon may bring its own Laravel service provider:
 * modules/Addons/<Name>/<Name>ServiceProvider.php, class
 * Modules\Addons\<Name>\<Name>ServiceProvider. Through it the addon uses the
 * framework's own means to add client-area or public pages (routes), views,
 * migrations and scheduled tasks - what the bundled Ksef module does, but
 * without an entry in bootstrap/providers.php, so an operator installs such
 * an addon by copying its folder and activating it.
 *
 * Addons without the file, and inactive addons, change nothing. A provider
 * that fails - its file does not load, or its register() or boot() throws -
 * is logged with the addon's name and skipped, the way a failing hook file
 * is: the panel and every other addon keep working.
 *
 * That is why the providers are not handed to $app->register(): during a
 * real boot Laravel would run their boot() later, in its own walk over the
 * providers, where no error of theirs can be caught here. They are
 * registered and booted below instead, each step on its own.
 */
class AddonServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        try {
            $candidates = $this->app->make(AddonManager::class)->activeProviders();
        } catch (\Throwable $e) {
            // The addon folders could not be read at all (discovery itself failed).
            Log::error('Addon service providers not loaded, addon discovery failed: '.$e->getMessage());

            return;
        }

        $providers = [];
        foreach ($candidates as $name => $class) {
            try {
                if (! is_subclass_of($class, ServiceProvider::class)) {
                    Log::warning("Addon {$name}: {$class} is not a service provider, skipped.");

                    continue;
                }
                $provider = new $class($this->app);
                $provider->register();
                $providers[$name] = $provider;
            } catch (\Throwable $e) {
                $this->skip($name, $e);
            }
        }

        foreach ($providers as $name => $provider) {
            try {
                $provider->callBootingCallbacks();
                if (method_exists($provider, 'boot')) {
                    $this->app->call([$provider, 'boot']);
                }
                $provider->callBootedCallbacks();
            } catch (\Throwable $e) {
                $this->skip($name, $e);
            }
        }
    }

    private function skip(string $name, \Throwable $e): void
    {
        Log::error("Addon {$name}: service provider failed to load, skipped — ".$e->getMessage(), [
            'exception' => $e,
        ]);
    }
}
