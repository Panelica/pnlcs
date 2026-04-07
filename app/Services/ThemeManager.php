<?php

namespace App\Services;

use App\Models\Setting;
use App\Support\ThemeInfo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use ZipArchive;

class ThemeManager
{
    private string $themesPath;

    public function __construct()
    {
        $this->themesPath = base_path('themes');
    }

    /**
     * Scan themes/ directory and return all installed themes.
     */
    public function getInstalled(): array
    {
        $themes = [];
        $activeSlug = $this->getActiveSlug();

        if (!is_dir($this->themesPath)) {
            return $themes;
        }

        $dirs = File::directories($this->themesPath);
        foreach ($dirs as $dir) {
            $jsonPath = $dir . '/theme.json';
            if (!file_exists($jsonPath)) {
                continue;
            }

            $json = json_decode(file_get_contents($jsonPath), true);
            if (!is_array($json)) {
                continue;
            }

            $slug = basename($dir);
            $themes[$slug] = ThemeInfo::fromJson($slug, $dir, $json, $slug === $activeSlug);
        }

        // Sort: active first, then alphabetical
        uasort($themes, function (ThemeInfo $a, ThemeInfo $b) {
            if ($a->isActive !== $b->isActive) {
                return $a->isActive ? -1 : 1;
            }
            return strcmp($a->name, $b->name);
        });

        return $themes;
    }

    /**
     * Get the currently active theme slug.
     */
    public function getActiveSlug(): string
    {
        return Setting::get('active_theme_slug', 'panelica');
    }

    /**
     * Get the active theme info, or null if not found.
     */
    public function getActive(): ?ThemeInfo
    {
        $slug = $this->getActiveSlug();
        $path = $this->themesPath . '/' . $slug;
        $jsonPath = $path . '/theme.json';

        if (!file_exists($jsonPath)) {
            return null;
        }

        $json = json_decode(file_get_contents($jsonPath), true);
        if (!is_array($json)) {
            return null;
        }

        return ThemeInfo::fromJson($slug, $path, $json, true);
    }

    /**
     * Activate a theme by slug.
     */
    public function activate(string $slug): bool
    {
        $path = $this->themesPath . '/' . $slug;
        if (!is_dir($path) || !file_exists($path . '/theme.json')) {
            return false;
        }

        Setting::set('active_theme_slug', $slug, 'appearance');

        // Also apply theme colors as the active color preset
        $json = json_decode(file_get_contents($path . '/theme.json'), true);
        if (isset($json['colors']) && is_array($json['colors'])) {
            // Merge theme colors with existing ThemeService colors for full 56-token coverage
            $starterColors = ThemeService::getPresets()['starter']['colors'];
            $merged = array_merge($starterColors, $json['colors']);
            Setting::set('active_theme_preset', $slug, 'appearance');
            Setting::set('active_theme', json_encode($merged), 'appearance');
        }

        ThemeService::clearCache();
        return true;
    }

    /**
     * Install a theme from uploaded ZIP file.
     */
    public function install(UploadedFile $file): array
    {
        $zip = new ZipArchive();
        $tmpPath = $file->getPathname();

        if ($zip->open($tmpPath) !== true) {
            return ['success' => false, 'message' => __('messages.theme.invalid_zip')];
        }

        // Find theme.json inside ZIP
        $themeJsonContent = null;
        $rootPrefix = '';

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (basename($name) === 'theme.json') {
                $themeJsonContent = $zip->getFromIndex($i);
                $rootPrefix = dirname($name);
                if ($rootPrefix === '.') {
                    $rootPrefix = '';
                } else {
                    $rootPrefix .= '/';
                }
                break;
            }
        }

        if (!$themeJsonContent) {
            $zip->close();
            return ['success' => false, 'message' => __('messages.theme.no_theme_json')];
        }

        $themeJson = json_decode($themeJsonContent, true);
        if (!is_array($themeJson) || empty($themeJson['slug'])) {
            $zip->close();
            return ['success' => false, 'message' => __('messages.theme.invalid_theme_json')];
        }

        $slug = preg_replace('/[^a-z0-9_-]/', '', strtolower($themeJson['slug']));
        if (empty($slug)) {
            $zip->close();
            return ['success' => false, 'message' => __('messages.theme.invalid_slug')];
        }

        // Don't overwrite built-in themes
        $themes = $this->getInstalled();
        if (isset($themes[$slug]) && $themes[$slug]->isBuiltin) {
            $zip->close();
            return ['success' => false, 'message' => __('messages.theme.cannot_overwrite_builtin')];
        }

        $destPath = $this->themesPath . '/' . $slug;

        // Extract to temp, then move
        $tmpExtract = sys_get_temp_dir() . '/pnlcs_theme_' . uniqid();
        $zip->extractTo($tmpExtract);
        $zip->close();

        // The actual files may be nested inside a folder
        $sourceDir = $tmpExtract;
        if ($rootPrefix) {
            $sourceDir = $tmpExtract . '/' . rtrim($rootPrefix, '/');
        }

        // Remove old version if exists
        if (is_dir($destPath)) {
            File::deleteDirectory($destPath);
        }

        File::moveDirectory($sourceDir, $destPath);
        File::deleteDirectory($tmpExtract);

        return ['success' => true, 'slug' => $slug, 'name' => $themeJson['name'] ?? $slug];
    }

    /**
     * Delete a theme (cannot delete active or built-in themes).
     */
    public function delete(string $slug): array
    {
        $themes = $this->getInstalled();
        if (isset($themes[$slug]) && $themes[$slug]->isBuiltin) {
            return ['success' => false, 'message' => __('messages.theme.cannot_delete_builtin')];
        }

        if ($slug === $this->getActiveSlug()) {
            return ['success' => false, 'message' => __('messages.theme.cannot_delete_active')];
        }

        $path = $this->themesPath . '/' . $slug;
        if (!is_dir($path)) {
            return ['success' => false, 'message' => __('messages.theme.not_found')];
        }

        File::deleteDirectory($path);
        return ['success' => true];
    }

    /**
     * Get the active theme's view directory (for prependLocation).
     */
    public function getViewPath(): ?string
    {
        $slug = $this->getActiveSlug();
        $viewPath = $this->themesPath . '/' . $slug . '/views';

        if (is_dir($viewPath)) {
            return $viewPath;
        }

        return null;
    }

    /**
     * Get the asset URL for the active theme.
     */
    public function getAssetUrl(): ?string
    {
        $slug = $this->getActiveSlug();
        $assetPath = $this->themesPath . '/' . $slug . '/assets';

        if (is_dir($assetPath)) {
            return '/themes/' . $slug . '/assets';
        }

        return null;
    }

    /**
     * Get dark mode colors from the active theme.
     */
    public function getDarkColors(): array
    {
        $theme = $this->getActive();
        return $theme ? $theme->dark_colors : [];
    }
}
