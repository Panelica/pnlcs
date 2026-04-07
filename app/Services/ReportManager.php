<?php

namespace App\Services;

use App\Contracts\ReportModuleInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

class ReportManager
{
    protected array $reports = [];
    protected bool $discovered = false;

    /**
     * Discover all report modules from modules/Reports/ directory.
     */
    public function discover(): self
    {
        if ($this->discovered) {
            return $this;
        }

        $basePath = base_path('modules/Reports');
        if (!File::isDirectory($basePath)) {
            $this->discovered = true;
            return $this;
        }

        foreach (File::directories($basePath) as $dir) {
            $className = basename($dir);
            $fqcn = "Modules\\Reports\\{$className}\\{$className}Report";
            $file = "{$dir}/{$className}Report.php";

            if (File::exists($file)) {
                require_once $file;
                if (class_exists($fqcn)) {
                    $instance = new $fqcn();
                    if ($instance instanceof ReportModuleInterface) {
                        $this->reports[$instance->getSlug()] = $instance;
                    }
                }
            }
        }

        // Also load single-file reports from modules/Reports/*.php
        foreach (File::glob("{$basePath}/*Report.php") as $file) {
            $className = basename($file, '.php');
            $fqcn = "Modules\\Reports\\{$className}";

            require_once $file;
            if (class_exists($fqcn)) {
                $instance = new $fqcn();
                if ($instance instanceof ReportModuleInterface) {
                    $this->reports[$instance->getSlug()] = $instance;
                }
            }
        }

        $this->discovered = true;
        return $this;
    }

    /**
     * Get all reports grouped by category.
     */
    public function all(): Collection
    {
        $this->discover();
        return collect($this->reports)->groupBy(fn(ReportModuleInterface $r) => $r->getCategory());
    }

    /**
     * Get flat list of all reports.
     */
    public function list(): Collection
    {
        $this->discover();
        return collect($this->reports);
    }

    /**
     * Find a report by slug.
     */
    public function find(string $slug): ?ReportModuleInterface
    {
        $this->discover();
        return $this->reports[$slug] ?? null;
    }

    /**
     * Get available categories.
     */
    public function categories(): array
    {
        $this->discover();
        return collect($this->reports)
            ->map(fn(ReportModuleInterface $r) => $r->getCategory())
            ->unique()
            ->sort()
            ->values()
            ->toArray();
    }
}
