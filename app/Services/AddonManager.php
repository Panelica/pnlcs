<?php

namespace App\Services;

use App\Contracts\AddonModuleInterface;
use App\Models\AddonSetting;
use App\Models\Setting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
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
                require_once $file;
                if (class_exists($fqcn)) {
                    $instance = new $fqcn();
                    if ($instance instanceof AddonModuleInterface) {
                        $this->addons[$instance->getName()] = $instance;
                    }
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

        $result = $addon->activate();
        if ($result['success'] ?? false) {
            Setting::set("addon_{$name}_active", '1', 'addons');
            Setting::set("addon_{$name}_version", $addon->getVersion(), 'addons');
            $this->forgetCachedRoutes();
        }
        return $result;
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
     * Service provider classes of the active addons: an addon may ship
     * modules/Addons/<Dir>/<Dir>ServiceProvider.php (class
     * Modules\Addons\<Dir>\<Dir>ServiceProvider). The active check uses the
     * addon's own name, the key activate() stores, so a folder named
     * differently from getName() (StaffBoard / "staffboard") is still found.
     *
     * @return list<class-string<ServiceProvider>>
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
            $class = 'Modules\\Addons\\'.$folder.'\\'.$folder.'ServiceProvider';
            if (File::exists("{$dir}/{$folder}ServiceProvider.php")
                && class_exists($class)
                && is_subclass_of($class, ServiceProvider::class)) {
                $providers[] = $class;
            }
        }

        return $providers;
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
     * Get sidebar items from all active addons.
     */
    public function getSidebarItems(): array
    {
        $items = [];
        foreach ($this->all() as $name => $addon) {
            if ($this->isActive($name)) {
                $items = array_merge($items, $addon->sidebar());
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
