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

    /**
     * Get all widgets sorted by weight.
     */
    public function all(): Collection
    {
        return collect($this->widgets)->sortBy(fn(WidgetModuleInterface $w) => $w->getWeight());
    }

    /**
     * Render all widgets with cached data.
     * @return array<string, array{widget: WidgetModuleInterface, html: string}>
     */
    public function renderAll(): array
    {
        $output = [];
        foreach ($this->all() as $key => $widget) {
            $ttl = $widget->getCacheTtl();
            $cacheKey = "widget:{$key}";

            if ($ttl > 0 && Cache::has($cacheKey)) {
                $data = Cache::get($cacheKey);
            } else {
                try {
                    $data = $widget->getData();
                    if ($ttl > 0) {
                        Cache::put($cacheKey, $data, $ttl);
                    }
                } catch (\Throwable $e) {
                    $data = ['error' => $e->getMessage()];
                }
            }

            try {
                $html = $widget->render($data);
            } catch (\Throwable $e) {
                $html = '<div style="color:red;padding:16px;">Widget error: ' . e($e->getMessage()) . '</div>';
            }

            $output[$key] = ['widget' => $widget, 'html' => $html];
        }
        return $output;
    }

    /**
     * Flush all widget caches.
     */
    public function flushCache(): void
    {
        foreach ($this->widgets as $key => $widget) {
            Cache::forget("widget:{$key}");
        }
    }
}
