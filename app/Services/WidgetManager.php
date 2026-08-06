<?php

namespace App\Services;

use App\Contracts\WidgetModuleInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class WidgetManager
{
    protected array $widgets = [];

    public function register(string $key, WidgetModuleInterface $widget): void
    {
        $this->widgets[$key] = $widget;
    }

    public function all(): Collection
    {
        return collect($this->widgets)->sortBy(fn (WidgetModuleInterface $w) => $w->getWeight());
    }

    /**
     * Whether the member of staff looking at the dashboard may see a widget.
     *
     * r126-permission: every widget declares the permission needed to view it
     * and nothing ever asked. A support-only role, refused the clients list and
     * the reports on their own screens, was shown the month's income and the
     * newest customers by name as soon as it opened the dashboard.
     */
    protected function visibleTo(WidgetModuleInterface $widget): bool
    {
        $permission = $widget->getPermission();

        if ($permission === null || $permission === '') {
            return true;
        }

        return (bool) auth('admin')->user()?->hasPermission($permission);
    }

    public function renderAll(): array
    {
        $output = [];
        foreach ($this->all() as $key => $widget) {
            if (! $this->visibleTo($widget)) {
                continue;
            }

            $ttl = $widget->getCacheTtl();
            $cacheKey = "widget:{$key}";

            $data = null;
            $dataError = null;

            if ($ttl > 0 && Cache::has($cacheKey)) {
                $data = Cache::get($cacheKey);
            } else {
                try {
                    $data = $widget->getData();
                    if ($ttl > 0) {
                        Cache::put($cacheKey, $data, $ttl);
                    }
                } catch (\Throwable $e) {
                    $dataError = $e->getMessage();
                }
            }

            if ($dataError !== null) {
                $html = '<div style="padding:16px;text-align:center;color:var(--pn-muted);font-size:12px;">Unable to load data</div>';
            } else {
                try {
                    $html = $widget->render($data);
                } catch (\Throwable $e) {
                    $html = '<div style="padding:16px;text-align:center;color:var(--pn-muted);font-size:12px;">Display error</div>';
                }
            }

            $output[$key] = ['widget' => $widget, 'html' => $html];
        }

        return $output;
    }

    public function flushCache(): void
    {
        foreach ($this->widgets as $key => $widget) {
            Cache::forget("widget:{$key}");
        }
    }
}
