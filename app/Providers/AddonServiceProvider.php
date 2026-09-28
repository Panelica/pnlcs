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
 * that throws is logged and skipped, the way a failing hook file is, so one
 * broken addon cannot take the panel down.
 */
class AddonServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        try {
            $providers = $this->app->make(AddonManager::class)->activeProviders();
        } catch (\Throwable $e) {
            return; // Installer / migrate context without a database.
        }

        foreach ($providers as $provider) {
            try {
                $this->app->register($provider);
            } catch (\Throwable $e) {
                Log::error("Addon service provider failed to load: {$provider} — ".$e->getMessage());
            }
        }
    }
}
