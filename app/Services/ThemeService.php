<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class ThemeService
{
    private const CACHE_KEY = 'pnlcs_active_theme';
    private const CACHE_TTL = 300; // 5 minutes

    public static function getPresets(): array
    {
        return [
            'starter' => [
                'name' => 'Starter',
                'description' => 'Clean professional blue theme — the PNLCS default.',
                'colors' => [
                    'primary'            => '#1a4d80',
                    'primary_light'      => '#e8f0fb',
                    'primary_dark'       => '#143d66',
                    'accent'             => '#337ab7',
                    'accent_dark'        => '#286090',
                    'nav_bg'             => '#1a4d80',
                    'sidebar_bg'         => '#f6f6f6',
                    'sidebar_text'       => '#555555',
                    'body_bg'            => '#f6f6f6',
                    'card_bg'            => '#ffffff',
                    'border_color'       => '#e2e8f0',
                    'text_color'         => '#333333',
                    'muted_color'        => '#64748b',
                    'footer_bg'          => '#1a4d80',
                    'hero_bg_start'      => '#0c1222',
                    'hero_bg_mid'        => '#162447',
                    'hero_bg_end'        => '#1a1a3e',
                    'welcome_accent'     => '#10b981',
                    'welcome_primary'    => '#2563eb',
                    'welcome_secondary'  => '#7c3aed',
                    'table_header_bg'    => '#1a4d80',
                    'success'            => '#10b981',
                ],
            ],
            'nightforge' => [
                'name' => 'Nightforge',
                'description' => 'Bold dark theme with orange highlights — inspired by the night.',
                'colors' => [
                    'primary'            => '#ea580c',
                    'primary_light'      => '#fff7ed',
                    'primary_dark'       => '#c2410c',
                    'accent'             => '#f97316',
                    'accent_dark'        => '#ea580c',
                    'nav_bg'             => '#1c1917',
                    'sidebar_bg'         => '#1c1917',
                    'sidebar_text'       => '#a8a29e',
                    'body_bg'            => '#f5f5f4',
                    'card_bg'            => '#ffffff',
                    'border_color'       => '#e7e5e4',
                    'text_color'         => '#292524',
                    'muted_color'        => '#78716c',
                    'footer_bg'          => '#1c1917',
                    'hero_bg_start'      => '#18181b',
                    'hero_bg_mid'        => '#27272a',
                    'hero_bg_end'        => '#1c1917',
                    'welcome_accent'     => '#ea580c',
                    'welcome_primary'    => '#f97316',
                    'welcome_secondary'  => '#fb923c',
                    'table_header_bg'    => '#292524',
                    'success'            => '#22c55e',
                ],
            ],
            'lumina' => [
                'name' => 'Lumina',
                'description' => 'Premium amber and indigo — elegant and refined.',
                'colors' => [
                    'primary'            => '#b45309',
                    'primary_light'      => '#fefce8',
                    'primary_dark'       => '#92400e',
                    'accent'             => '#d97706',
                    'accent_dark'        => '#b45309',
                    'nav_bg'             => '#1e1b4b',
                    'sidebar_bg'         => '#1e1b4b',
                    'sidebar_text'       => '#a5b4fc',
                    'body_bg'            => '#fafaf9',
                    'card_bg'            => '#ffffff',
                    'border_color'       => '#e5e7eb',
                    'text_color'         => '#1f2937',
                    'muted_color'        => '#6b7280',
                    'footer_bg'          => '#1e1b4b',
                    'hero_bg_start'      => '#1e1b4b',
                    'hero_bg_mid'        => '#312e81',
                    'hero_bg_end'        => '#1e1b4b',
                    'welcome_accent'     => '#d97706',
                    'welcome_primary'    => '#b45309',
                    'welcome_secondary'  => '#4f46e5',
                    'table_header_bg'    => '#312e81',
                    'success'            => '#10b981',
                ],
            ],
        ];
    }

    public static function getActiveTheme(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            $presetName = Setting::get('active_theme_preset', 'starter');
            $customJson = Setting::get('active_theme');

            if ($customJson) {
                $colors = json_decode($customJson, true);
                if (is_array($colors) && !empty($colors)) {
                    return [
                        'preset' => $presetName,
                        'colors' => $colors,
                    ];
                }
            }

            $presets = self::getPresets();
            $colors = $presets[$presetName]['colors'] ?? $presets['starter']['colors'];

            return [
                'preset' => $presetName,
                'colors' => $colors,
            ];
        });
    }

    public static function generateCssVariables(array $colors): string
    {
        $vars = [];
        foreach ($colors as $key => $value) {
            $cssKey = str_replace('_', '-', $key);
            $vars[] = "    --theme-{$cssKey}: {$value};";
        }

        return ":root {\n" . implode("\n", $vars) . "\n}";
    }

    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
