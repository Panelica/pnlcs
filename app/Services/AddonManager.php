<?php

namespace App\Services;

use App\Contracts\AddonModuleInterface;
use App\Models\AddonSetting;
use App\Models\Setting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AddonManager
{
    protected array $addons = [];
    protected bool $discovered = false;

    /**
     * Discover all addon modules from modules/Addons/ directory.
     */
    public function discover(): self
    {
        if ($this->discovered) {
            return $this;
        }

        $basePath = base_path('modules/Addons');
        if (!File::isDirectory($basePath)) {
            $this->discovered = true;
            return $this;
        }

        foreach (File::directories($basePath) as $dir) {
            $dirName = basename($dir);
            $fqcn = "Modules\\Addons\\{$dirName}\\{$dirName}Module";
            $file = "{$dir}/{$dirName}Module.php";

            if (File::exists($file)) {
                // One addon whose module file does not load must not hide the
                // others: their hooks and service providers depend on this list.
                try {
                    require_once $file;
                    if (class_exists($fqcn)) {
                        $instance = new $fqcn();
                        if ($instance instanceof AddonModuleInterface) {
                            $this->addons[$instance->getName()] = $instance;
                        }
                    }
                } catch (\Throwable $e) {
                    Log::error("Addon {$dirName}: module file failed to load, skipped — ".$e->getMessage());
                }
            }
        }

        $this->discovered = true;
        return $this;
    }

    /**
     * Get all discovered addons.
     */
    public function all(): Collection
    {
        $this->discover();
        return collect($this->addons);
    }

    /**
     * Find an addon by name.
     */
    public function find(string $name): ?AddonModuleInterface
    {
        $this->discover();
        return $this->addons[$name] ?? null;
    }

    /**
     * Check if an addon is active.
     */
    public function isActive(string $name): bool
    {
        try {
            return Setting::get("addon_{$name}_active", '0') === '1';
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Activate an addon.
     */
    public function activate(string $name): array
    {
        $addon = $this->find($name);
        if (!$addon) {
            return ['success' => false, 'message' => __('messages.error.addon_not_found')];
        }

        // Files replaced by a newer version while the addon was off: its
        // upgrade() runs before it comes back, or the version it records
        // would skip the upgrade for good.
        $upgrade = $this->upgradeIfNewer($name, $addon);
        if ($upgrade !== null && ! ($upgrade['success'] ?? false)) {
            return $upgrade;
        }

        $result = $addon->activate();
        if ($result['success'] ?? false) {
            Setting::set("addon_{$name}_active", '1', 'addons');
            Setting::set("addon_{$name}_version", $addon->getVersion(), 'addons');
            $this->forgetCachedRoutes();
        }
        return $result;
    }

    /**
     * Active addons whose files carry a newer version than the one recorded
     * for them, keyed by name. Read only: the Extensions screen shows these
     * as waiting for `php artisan pnlcs:addons-upgrade`.
     *
     * @return array<string, array{from: string, to: string}>
     */
    public function pendingUpgrades(): array
    {
        $pending = [];
        foreach ($this->all() as $name => $addon) {
            if ($this->isActive($name) && ($from = $this->olderRecordedVersion($name, $addon)) !== null) {
                $pending[$name] = ['from' => $from, 'to' => $addon->getVersion()];
            }
        }

        return $pending;
    }

    /**
     * Run upgrade() for every active addon whose files are newer than the
     * version recorded for it. Called by `php artisan pnlcs:addons-upgrade`,
     * a step of the update sequence (docs/install/updating.md).
     *
     * @return array<string, array{success: bool, skipped: bool, message: string, from: string, to: string}> keyed by addon name; only the addons that had an upgrade due
     */
    public function runPendingUpgrades(): array
    {
        $results = [];
        foreach ($this->all() as $name => $addon) {
            if ($this->isActive($name) && ($result = $this->upgradeIfNewer($name, $addon)) !== null) {
                $results[$name] = $result;
            }
        }

        return $results;
    }

    /** The recorded version when the addon's files are newer; null when nothing is recorded or it is not older. */
    protected function olderRecordedVersion(string $name, AddonModuleInterface $addon): ?string
    {
        try {
            $recorded = (string) Setting::get("addon_{$name}_version", '');
        } catch (\Throwable) {
            return null;
        }

        return $recorded !== '' && version_compare($addon->getVersion(), $recorded, '>') ? $recorded : null;
    }

    /**
     * Call the addon's upgrade() with the recorded version when its files
     * carry a newer one, and record the new version once it succeeds.
     *
     * One upgrade of an addon at a time: the run holds a lock per addon, and a
     * second run that finds it held skips that addon instead of upgrading it
     * again. The recorded version is read again under the lock, so a run that
     * waited behind a finished upgrade finds nothing left to do.
     *
     * A failed or throwing upgrade keeps the old version, so it is tried again
     * next time; it is logged either way. Null when there is nothing to do.
     *
     * @return array{success: bool, skipped: bool, message: string, from: string, to: string}|null
     */
    protected function upgradeIfNewer(string $name, AddonModuleInterface $addon): ?array
    {
        if ($this->olderRecordedVersion($name, $addon) === null) {
            return null;
        }

        $current = $addon->getVersion();
        $lock = Cache::lock("addon-upgrade:{$name}", 600);
        if (! $lock->get()) {
            return ['success' => false, 'skipped' => true, 'message' => 'another upgrade of this addon is running', 'from' => '', 'to' => $current];
        }

        try {
            $recorded = $this->olderRecordedVersion($name, $addon);
            if ($recorded === null) {
                return null;
            }

            try {
                $result = $addon->upgrade($recorded);
            } catch (\Throwable $e) {
                $result = ['success' => false, 'message' => $e->getMessage()];
            }

            if ($result['success'] ?? false) {
                Setting::set("addon_{$name}_version", $current, 'addons');
                Log::info("Addon {$name}: upgraded from {$recorded} to {$current}");
            } else {
                Log::error("Addon {$name}: upgrade from {$recorded} to {$current} failed — ".($result['message'] ?? 'no message'));
            }

            return ['success' => (bool) ($result['success'] ?? false), 'skipped' => false, 'message' => (string) ($result['message'] ?? ''), 'from' => $recorded, 'to' => $current];
        } finally {
            $lock->release();
        }
    }

    /**
     * Deactivate an addon.
     */
    public function deactivate(string $name): array
    {
        $addon = $this->find($name);
        if (!$addon) {
            return ['success' => false, 'message' => __('messages.error.addon_not_found')];
        }

        $result = $addon->deactivate();
        Setting::set("addon_{$name}_active", '0', 'addons');
        $this->forgetCachedRoutes();
        return $result;
    }

    /**
     * Service provider classes of the active addons, keyed by addon name: an
     * addon may ship modules/Addons/<Dir>/<Dir>ServiceProvider.php (class
     * Modules\Addons\<Dir>\<Dir>ServiceProvider). The active check uses the
     * addon's own name, the key activate() stores, so a folder named
     * differently from getName() (StaffBoard / "staffboard") is still found.
     *
     * Only the file is looked for here; the class is not loaded, so a broken
     * file is reported for its own addon by AddonServiceProvider.
     *
     * @return array<string, class-string<ServiceProvider>>
     */
    public function activeProviders(): array
    {
        $providers = [];
        foreach ($this->all() as $name => $addon) {
            if (! $this->isActive($name)) {
                continue;
            }
            $dir = dirname((string) (new \ReflectionClass($addon))->getFileName());
            $folder = basename($dir);
            if (File::exists("{$dir}/{$folder}ServiceProvider.php")) {
                $providers[$name] = 'Modules\\Addons\\'.$folder.'\\'.$folder.'ServiceProvider';
            }
        }

        return $providers;
    }

    /**
     * hooks.php files of the active addons, keyed by addon name
     * (modules/Addons/<Dir>/hooks.php). Like activeProviders(), the active
     * check uses the addon's own name, the key activate() stores: a folder
     * named differently from getName() (StaffBoard / "staffboard") would
     * otherwise never count as active and its hooks would never load.
     *
     * @return array<string, string>
     */
    public function activeHookFiles(): array
    {
        $files = [];
        foreach ($this->all() as $name => $addon) {
            if (! $this->isActive($name)) {
                continue;
            }
            $file = dirname((string) (new \ReflectionClass($addon))->getFileName()).'/hooks.php';
            if (File::exists($file)) {
                $files[$name] = $file;
            }
        }

        return $files;
    }

    /**
     * Routes cached by `php artisan optimize` would keep an addon's pages after
     * it is deactivated, or miss them after it is activated.
     */
    protected function forgetCachedRoutes(): void
    {
        if (app()->routesAreCached()) {
            Artisan::call('route:clear');
        }
    }

    /**
     * The admin menu entries of the active addons (their sidebar()), shown in
     * the admin navigation's Extensions menu.
     *
     * An entry needs a label and a url; anything else is left out. An addon
     * whose sidebar() throws is logged and skipped, so one broken addon does
     * not take the admin panel down with it.
     *
     * @return array<int, array{label: string, url: string, children: array<int, array{label: string, url: string}>}>
     */
    public function getSidebarItems(): array
    {
        $valid = fn ($entry) => is_array($entry) && is_string($entry['label'] ?? null) && $entry['label'] !== ''
            && is_string($entry['url'] ?? null) && $entry['url'] !== '';

        $items = [];
        foreach ($this->all() as $name => $addon) {
            if (! $this->isActive($name)) {
                continue;
            }

            try {
                $entries = $addon->sidebar();
            } catch (\Throwable $e) {
                Log::error("Addon {$name}: sidebar() failed, its menu entries are skipped — ".$e->getMessage());

                continue;
            }

            foreach (array_filter((array) $entries, $valid) as $entry) {
                $items[] = [
                    'label' => $entry['label'],
                    'url' => $entry['url'],
                    'children' => array_values(array_map(
                        fn ($child) => ['label' => $child['label'], 'url' => $child['url']],
                        array_filter((array) ($entry['children'] ?? []), $valid),
                    )),
                ];
            }
        }

        return $items;
    }

    /**
     * The stored settings for an addon, keyed by field name.
     *
     * @return array<string, mixed>
     */
    public function settings(string $name): array
    {
        return AddonSetting::getForAddon($name);
    }

    /**
     * Persist an addon's settings (one entry per declared config field).
     *
     * @param  array<string, mixed>  $settings
     */
    public function saveSettings(string $name, array $settings): void
    {
        foreach ($settings as $key => $value) {
            AddonSetting::setSetting($name, $key, $value);
        }
    }
}
