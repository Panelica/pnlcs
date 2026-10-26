<?php

namespace App\Providers;

use App\Services\AddonManager;
use App\Support\GuardedProviders;
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
 * The guarded register-and-boot is App\Support\GuardedProviders, shared
 * with modules that bring a provider through their pnlcs.json.
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

        $labelled = [];
        foreach ($candidates as $name => $class) {
            $labelled["Addon {$name}"] = $class;
        }
        GuardedProviders::boot($this->app, $labelled);
    }
}
