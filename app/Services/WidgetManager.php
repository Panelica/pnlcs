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
        return collect($this->widgets)->sortBy(fn(WidgetModuleInterface $w) => $w->getWeight());
    }

    public function renderAll(): array
    {
        $output = [];
        foreach ($this->all() as $key => $widget) {
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
