<?php

namespace App\Services;

use App\Contracts\AdminMenuProvider;
use App\Enums\AdminMenuLocation;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Throwable;

class AdminMenuRegistry
{
    public function __construct(private AddonManager $addons) {}

    /**
     * @return array<string, array<int, array{label: string, url: string, children: array<int, array{label: string, url: string}>}>>
     */
    public function all(): array
    {
        $items = [];
        $seen = [];

        foreach ($this->addons->all() as $name => $addon) {
            if (! $this->addons->isActive($name)) {
                continue;
            }

            try {
                $entries = $addon instanceof AdminMenuProvider
                    ? $addon->adminMenu()
                    : $this->legacyEntries($addon->sidebar());
            } catch (Throwable $e) {
                Log::error("Addon {$name}: menu entries failed, the addon is skipped — ".$e->getMessage());

                continue;
            }

            foreach ((array) $entries as $entry) {
                $item = $this->item($entry, $name);
                if ($item === null) {
                    continue;
                }

                $location = $item['location'];
                unset($item['location']);

                $key = $location.'|'.$item['url'];
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;

                $items[$location][] = $item;
            }
        }

        foreach ($items as &$entries) {
            usort($entries, fn ($a, $b) => [$a['order'], $a['label']] <=> [$b['order'], $b['label']]);

            foreach ($entries as &$entry) {
                unset($entry['order']);
            }
            unset($entry);
        }
        unset($entries);

        return $items;
    }

    /**
     * @param  array<int, mixed>  $entries
     * @return array<int, mixed>
     */
    private function legacyEntries(array $entries): array
    {
        return array_map(
            fn ($entry) => is_array($entry)
                ? ['location' => AdminMenuLocation::Extensions->value] + $entry
                : $entry,
            $entries,
        );
    }

    /**
     * @return array{location: string, label: string, url: string, children: array<int, array{label: string, url: string}>, order: int}|null
     */
    private function item(mixed $entry, string $addon): ?array
    {
        if (! is_array($entry)) {
            return null;
        }

        $location = $entry['location'] ?? null;
        if (! is_string($location) || AdminMenuLocation::tryFrom($location) === null) {
            return null;
        }

        $label = $entry['label'] ?? null;
        if (! is_string($label) || $label === '') {
            return null;
        }

        if (! $this->can($entry['permission'] ?? null)) {
            return null;
        }

        $url = $this->url($entry, $addon);
        if ($url === null) {
            return null;
        }

        $children = [];
        foreach ((array) ($entry['children'] ?? []) as $child) {
            if (! is_array($child)) {
                continue;
            }

            $childLabel = $child['label'] ?? null;
            if (! is_string($childLabel) || $childLabel === '' || ! $this->can($child['permission'] ?? null)) {
                continue;
            }

            $childUrl = $this->url($child, $addon);
            if ($childUrl !== null) {
                $children[] = ['label' => $childLabel, 'url' => $childUrl];
            }
        }

        return [
            'location' => $location,
            'label' => $label,
            'url' => $url,
            'children' => $children,
            'order' => (int) ($entry['order'] ?? 100),
        ];
    }

    private function can(?string $permission): bool
    {
        if ($permission === null || $permission === '') {
            return true;
        }

        return auth('admin')->user()?->role?->hasPermission($permission) ?? false;
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function url(array $entry, string $addon): ?string
    {
        $route = $entry['route'] ?? null;

        if (is_string($route) && $route !== '') {
            try {
                if (! Route::has($route)) {
                    Log::warning("Addon {$addon}: menu route {$route} is not registered, the entry is skipped");

                    return null;
                }

                return route($route, (array) ($entry['parameters'] ?? []));
            } catch (Throwable $e) {
                Log::warning("Addon {$addon}: menu route {$route} cannot be built — ".$e->getMessage());

                return null;
            }
        }

        $url = $entry['url'] ?? null;

        return is_string($url) && $url !== '' ? $url : null;
    }
}
