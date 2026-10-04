<?php

namespace App\Providers;

use App\Services\AddonManager;
use App\Services\HookManager;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
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
        \App\Events\ClientLoggedIn::class    => ['UserLogin', 'ClientLogin'],
        \App\Events\OrderPlaced::class       => ['OrderPlaced', 'AfterShoppingCartCheckout'],
        \App\Events\InvoiceCreated::class    => ['InvoiceCreated', 'InvoiceCreation'],
        \App\Events\InvoicePaid::class       => ['InvoicePaid'],
        \App\Events\TicketOpened::class      => ['TicketOpened', 'TicketOpen'],
        \App\Events\TicketReplied::class     => ['TicketReplied'],
        \App\Events\TicketClosed::class      => ['TicketClosed', 'TicketClose'],
        \App\Events\ServiceActivated::class  => ['ServiceActivated'],
        \App\Events\ServiceSuspended::class  => ['ServiceSuspended'],
        \App\Events\ServiceUnsuspended::class => ['ServiceUnsuspended'],
        \App\Events\ServiceTerminated::class => ['ServiceTerminated'],
        // WHMCS names beside our own, so WHMCS hooks run unchanged.
        \App\Events\DomainRegistered::class => ['DomainRegistered', 'AfterRegistrarRegistration'],
        \App\Events\DomainRenewed::class => ['DomainRenewed', 'AfterRegistrarRenewal'],
        \App\Events\DomainTransferStarted::class => ['DomainTransferStarted', 'AfterRegistrarTransfer'],
        \App\Events\DomainExpired::class => ['DomainExpired'],
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

        // Addons: only while active, checked by the addon's own name - the key
        // activate() stores - not its folder name. isActive() already answers
        // "no" without a database (installer), so this only fails when the
        // addon folders cannot be read at all.
        if (File::isDirectory("{$base}/Addons")) {
            try {
                $hookFiles = $this->app->make(AddonManager::class)->activeHookFiles();
            } catch (\Throwable $e) {
                Log::error('Addon hook files not loaded, addon discovery failed: '.$e->getMessage());
                $hookFiles = [];
            }
            foreach ($hookFiles as $hookFile) {
                $this->requireHookFile($hookFile);
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
