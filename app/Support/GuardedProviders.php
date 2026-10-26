<?php

namespace App\Support;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

/**
 * Registers and boots service providers that come with an extension (an
 * addon, or a module found by its pnlcs.json) so that a failing one is
 * logged and skipped and the panel keeps working.
 *
 * That is why they are not handed to $app->register(): during a real boot
 * Laravel would run their boot() later, in its own walk over the providers,
 * where no error of theirs can be caught. They are registered and booted
 * here instead, each step on its own.
 */
final class GuardedProviders
{
    /**
     * @param  array<string, string>  $candidates  extension label => provider class
     */
    public static function boot(Application $app, array $candidates): void
    {
        $providers = [];
        foreach ($candidates as $label => $class) {
            try {
                if (! is_subclass_of($class, ServiceProvider::class)) {
                    Log::warning("{$label}: {$class} is not a service provider, skipped.");

                    continue;
                }
                $provider = new $class($app);
                $provider->register();
                $providers[$label] = $provider;
            } catch (\Throwable $e) {
                self::skip($label, $e);
            }
        }

        foreach ($providers as $label => $provider) {
            try {
                $provider->callBootingCallbacks();
                if (method_exists($provider, 'boot')) {
                    $app->call([$provider, 'boot']);
                }
                $provider->callBootedCallbacks();
            } catch (\Throwable $e) {
                self::skip($label, $e);
            }
        }
    }

    private static function skip(string $label, \Throwable $e): void
    {
        Log::error("{$label}: service provider failed to load, skipped — ".$e->getMessage(), [
            'exception' => $e,
        ]);
    }
}
