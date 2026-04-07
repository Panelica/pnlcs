<?php

namespace App\Services;

use App\Contracts\AddonModuleInterface;
use App\Models\Setting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

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
            return ['success' => false, 'message' => 'Addon not found'];
        }

        $result = $addon->activate();
        if ($result['success'] ?? false) {
            Setting::set("addon_{$name}_active", '1', 'addons');
            Setting::set("addon_{$name}_version", $addon->getVersion(), 'addons');
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
            return ['success' => false, 'message' => 'Addon not found'];
        }

        $result = $addon->deactivate();
        Setting::set("addon_{$name}_active", '0', 'addons');
        return $result;
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
}
