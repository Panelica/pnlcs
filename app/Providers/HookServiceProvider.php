<?php

namespace App\Providers;

use App\Services\AddonManager;
use App\Services\HookManager;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;

class HookServiceProvider extends ServiceProvider
{
    /**
     * Laravel event → hook point names. Every event fires its own basename
     * as a hook plus a WHMCS-compatible alias where one exists, so hooks
     * written against WHMCS documentation keep working.
     *
     * @var array<class-string, string[]>
     */
    protected array $eventHookMap = [
        \App\Events\ClientCreated::class     => ['ClientCreated', 'ClientAdd'],
        \App\Events\OrderPlaced::class       => ['OrderPlaced', 'AfterShoppingCartCheckout'],
        \App\Events\InvoiceCreated::class    => ['InvoiceCreated', 'InvoiceCreation'],
        \App\Events\InvoicePaid::class       => ['InvoicePaid'],
        \App\Events\TicketOpened::class      => ['TicketOpened', 'TicketOpen'],
        \App\Events\TicketReplied::class     => ['TicketReplied'],
        \App\Events\ServiceActivated::class  => ['ServiceActivated'],
        \App\Events\ServiceSuspended::class  => ['ServiceSuspended'],
        \App\Events\ServiceTerminated::class => ['ServiceTerminated'],
    ];

    public function register(): void
    {
        $this->app->singleton(HookManager::class);
    }

    public function boot(): void
    {
        $hooks = $this->app->make(HookManager::class);

        $this->bridgeEvents();
        $this->loadProjectHooks($hooks);
        $this->loadModuleHooks();
    }

    /**
     * Fire hook points whenever the mapped Laravel events dispatch.
     * Event public properties become the hook's named parameters.
     */
    protected function bridgeEvents(): void
    {
        foreach ($this->eventHookMap as $eventClass => $hookPoints) {
            Event::listen($eventClass, function (object $event) use ($hookPoints) {
                $params = get_object_vars($event);
                $hooks = $this->app->make(HookManager::class);
                foreach ($hookPoints as $point) {
                    $hooks->run($point, $params);
                }
            });
        }
    }

    /**
     * Project-level hooks: app/Hooks/*.php — each file calls add_hook().
     */
    protected function loadProjectHooks(HookManager $hooks): void
    {
        $hooks->loadHookFilesFrom(app_path('Hooks'));
    }

    /**
     * Module hooks:
     *  - modules/{Gateways,Servers,Registrars,Ssl}/<Name>/hooks.php — always loaded
     *  - modules/Addons/<Name>/hooks.php — only when the addon is ACTIVE
     */
    protected function loadModuleHooks(): void
    {
        $base = base_path('modules');
        if (!File::isDirectory($base)) {
            return;
        }

        foreach (['Gateways', 'Servers', 'Registrars', 'Ssl'] as $type) {
            $typeDir = "{$base}/{$type}";
            if (!File::isDirectory($typeDir)) {
                continue;
            }
            foreach (File::directories($typeDir) as $moduleDir) {
                if (File::exists("{$moduleDir}/hooks.php")) {
                    $this->requireHookFile("{$moduleDir}/hooks.php");
                }
            }
        }

        // Addons: gated on active state (Setting-based; DB may be absent during install)
        $addonsDir = "{$base}/Addons";
        if (File::isDirectory($addonsDir)) {
            try {
                $addonManager = $this->app->make(AddonManager::class);
                foreach (File::directories($addonsDir) as $addonDir) {
                    $hookFile = "{$addonDir}/hooks.php";
                    if (!File::exists($hookFile)) {
                        continue;
                    }
                    $name = basename($addonDir);
                    if ($addonManager->isActive($name)) {
                        $this->requireHookFile($hookFile);
                    }
                }
            } catch (\Throwable $e) {
                // Installer / migrate context without DB — skip addon hooks silently.
            }
        }
    }

    protected function requireHookFile(string $file): void
    {
        try {
            require_once $file;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Hook file failed to load: {$file} — " . $e->getMessage());
        }
    }
}
