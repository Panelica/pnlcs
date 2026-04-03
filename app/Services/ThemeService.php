<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class ThemeService
{
    private const CACHE_KEY = 'pnlcs_active_theme';
    private const CACHE_TTL = 300; // 5 minutes

    /**
     * All 56 token keys grouped by category.
     */
    public static function getTokenGroups(): array
    {
        return [
            'Colors' => [
                'primary', 'primary_light', 'primary_dark', 'accent', 'accent_dark',
                'nav_bg', 'sidebar_bg', 'sidebar_text', 'body_bg', 'card_bg',
                'border_color', 'text_color', 'muted_color', 'footer_bg',
                'hero_bg_start', 'hero_bg_mid', 'hero_bg_end',
                'welcome_accent', 'welcome_primary', 'welcome_secondary',
                'table_header_bg', 'success',
                'danger', 'warning', 'info', 'link_color', 'link_hover_color',
                'heading_color', 'card_shadow', 'input_bg', 'input_border',
                'input_focus_border', 'badge_bg', 'overlay_bg',
            ],
            'Typography' => [
                'font_family', 'font_size_base', 'font_size_sm', 'font_size_lg',
                'font_weight_normal', 'font_weight_bold', 'heading_font_family', 'line_height_base',
            ],
            'Spacing' => [
                'spacing_xs', 'spacing_sm', 'spacing_md', 'spacing_lg', 'spacing_xl', 'spacing_2xl',
            ],
            'Layout' => [
                'radius_sm', 'radius_md', 'radius_lg', 'radius_full',
                'shadow_sm', 'shadow_md', 'shadow_lg', 'container_max_width',
            ],
        ];
    }

    /**
     * Token labels for admin UI.
     */
    public static function getTokenLabels(): array
    {
        return [
            // Original 22
            'primary' => 'Primary', 'primary_light' => 'Primary Light', 'primary_dark' => 'Primary Dark',
            'accent' => 'Accent', 'accent_dark' => 'Accent Dark',
            'nav_bg' => 'Navigation BG', 'sidebar_bg' => 'Sidebar BG', 'sidebar_text' => 'Sidebar Text',
            'body_bg' => 'Body BG', 'card_bg' => 'Card BG', 'border_color' => 'Border',
            'text_color' => 'Text', 'muted_color' => 'Muted Text', 'footer_bg' => 'Footer BG',
            'hero_bg_start' => 'Hero Gradient Start', 'hero_bg_mid' => 'Hero Gradient Mid', 'hero_bg_end' => 'Hero Gradient End',
            'welcome_accent' => 'Welcome Accent', 'welcome_primary' => 'Welcome Primary', 'welcome_secondary' => 'Welcome Secondary',
            'table_header_bg' => 'Table Header BG', 'success' => 'Success',
            // New 12 colors
            'danger' => 'Danger', 'warning' => 'Warning', 'info' => 'Info',
            'link_color' => 'Link Color', 'link_hover_color' => 'Link Hover',
            'heading_color' => 'Heading Color', 'card_shadow' => 'Card Shadow Color',
            'input_bg' => 'Input BG', 'input_border' => 'Input Border',
            'input_focus_border' => 'Input Focus Border', 'badge_bg' => 'Badge BG', 'overlay_bg' => 'Overlay BG',
            // Typography
            'font_family' => 'Font Family', 'font_size_base' => 'Font Size Base', 'font_size_sm' => 'Font Size SM',
            'font_size_lg' => 'Font Size LG', 'font_weight_normal' => 'Font Weight Normal', 'font_weight_bold' => 'Font Weight Bold',
            'heading_font_family' => 'Heading Font', 'line_height_base' => 'Line Height',
            // Spacing
            'spacing_xs' => 'Spacing XS', 'spacing_sm' => 'Spacing SM', 'spacing_md' => 'Spacing MD',
            'spacing_lg' => 'Spacing LG', 'spacing_xl' => 'Spacing XL', 'spacing_2xl' => 'Spacing 2XL',
            // Layout
            'radius_sm' => 'Radius SM', 'radius_md' => 'Radius MD', 'radius_lg' => 'Radius LG', 'radius_full' => 'Radius Full',
            'shadow_sm' => 'Shadow SM', 'shadow_md' => 'Shadow MD', 'shadow_lg' => 'Shadow LG',
            'container_max_width' => 'Container Max Width',
        ];
    }

    /**
     * Keys that use color picker (hex). Non-color keys use text input.
     */
    public static function getColorKeys(): array
    {
        return self::getTokenGroups()['Colors'];
    }

    public static function getPresets(): array
    {
        return [
            'starter' => [
                'name' => 'Starter',
                'description' => 'Clean professional blue theme — the PNLCS default.',
                'colors' => self::starterColors(),
            ],
            'nightforge' => [
                'name' => 'Nightforge',
                'description' => 'Bold dark theme with orange highlights — inspired by the night.',
                'colors' => self::nightforgeColors(),
            ],
            'lumina' => [
                'name' => 'Lumina',
                'description' => 'Premium amber and indigo — elegant and refined.',
                'colors' => self::luminaColors(),
            ],
        ];
    }

    private static function starterColors(): array
    {
        return [
            // Original 22
            'primary' => '#1a4d80', 'primary_light' => '#e8f0fb', 'primary_dark' => '#143d66',
            'accent' => '#337ab7', 'accent_dark' => '#286090',
            'nav_bg' => '#1a4d80', 'sidebar_bg' => '#f6f6f6', 'sidebar_text' => '#555555',
            'body_bg' => '#f6f6f6', 'card_bg' => '#ffffff', 'border_color' => '#e2e8f0',
            'text_color' => '#333333', 'muted_color' => '#64748b', 'footer_bg' => '#1a4d80',
            'hero_bg_start' => '#0c1222', 'hero_bg_mid' => '#162447', 'hero_bg_end' => '#1a1a3e',
            'welcome_accent' => '#10b981', 'welcome_primary' => '#2563eb', 'welcome_secondary' => '#7c3aed',
            'table_header_bg' => '#1a4d80', 'success' => '#10b981',
            // New 12 colors
            'danger' => '#ef4444', 'warning' => '#f59e0b', 'info' => '#3b82f6',
            'link_color' => '#2563eb', 'link_hover_color' => '#1d4ed8',
            'heading_color' => '#1e293b', 'card_shadow' => 'rgba(0,0,0,0.08)',
            'input_bg' => '#ffffff', 'input_border' => '#d1d5db',
            'input_focus_border' => '#1a4d80', 'badge_bg' => '#e8f0fb', 'overlay_bg' => 'rgba(0,0,0,0.5)',
            // Typography
            'font_family' => "'Inter', -apple-system, BlinkMacSystemFont, sans-serif",
            'font_size_base' => '16px', 'font_size_sm' => '14px', 'font_size_lg' => '18px',
            'font_weight_normal' => '400', 'font_weight_bold' => '700',
            'heading_font_family' => "'Inter', -apple-system, BlinkMacSystemFont, sans-serif",
            'line_height_base' => '1.6',
            // Spacing
            'spacing_xs' => '4px', 'spacing_sm' => '8px', 'spacing_md' => '16px',
            'spacing_lg' => '24px', 'spacing_xl' => '32px', 'spacing_2xl' => '48px',
            // Layout
            'radius_sm' => '4px', 'radius_md' => '8px', 'radius_lg' => '16px', 'radius_full' => '9999px',
            'shadow_sm' => '0 1px 3px rgba(0,0,0,0.08)',
            'shadow_md' => '0 4px 15px rgba(0,0,0,0.1)',
            'shadow_lg' => '0 20px 60px rgba(0,0,0,0.12)',
            'container_max_width' => '1240px',
        ];
    }

    private static function nightforgeColors(): array
    {
        return [
            'primary' => '#ea580c', 'primary_light' => '#fff7ed', 'primary_dark' => '#c2410c',
            'accent' => '#f97316', 'accent_dark' => '#ea580c',
            'nav_bg' => '#1c1917', 'sidebar_bg' => '#1c1917', 'sidebar_text' => '#a8a29e',
            'body_bg' => '#f5f5f4', 'card_bg' => '#ffffff', 'border_color' => '#e7e5e4',
            'text_color' => '#292524', 'muted_color' => '#78716c', 'footer_bg' => '#1c1917',
            'hero_bg_start' => '#18181b', 'hero_bg_mid' => '#27272a', 'hero_bg_end' => '#1c1917',
            'welcome_accent' => '#ea580c', 'welcome_primary' => '#f97316', 'welcome_secondary' => '#fb923c',
            'table_header_bg' => '#292524', 'success' => '#22c55e',
            'danger' => '#dc2626', 'warning' => '#d97706', 'info' => '#0891b2',
            'link_color' => '#ea580c', 'link_hover_color' => '#c2410c',
            'heading_color' => '#1c1917', 'card_shadow' => 'rgba(0,0,0,0.1)',
            'input_bg' => '#ffffff', 'input_border' => '#d6d3d1',
            'input_focus_border' => '#ea580c', 'badge_bg' => '#fff7ed', 'overlay_bg' => 'rgba(0,0,0,0.6)',
            'font_family' => "'Inter', -apple-system, BlinkMacSystemFont, sans-serif",
            'font_size_base' => '16px', 'font_size_sm' => '14px', 'font_size_lg' => '18px',
            'font_weight_normal' => '400', 'font_weight_bold' => '700',
            'heading_font_family' => "'Inter', -apple-system, BlinkMacSystemFont, sans-serif",
            'line_height_base' => '1.6',
            'spacing_xs' => '4px', 'spacing_sm' => '8px', 'spacing_md' => '16px',
            'spacing_lg' => '24px', 'spacing_xl' => '32px', 'spacing_2xl' => '48px',
            'radius_sm' => '4px', 'radius_md' => '8px', 'radius_lg' => '16px', 'radius_full' => '9999px',
            'shadow_sm' => '0 1px 3px rgba(0,0,0,0.1)',
            'shadow_md' => '0 4px 15px rgba(0,0,0,0.12)',
            'shadow_lg' => '0 20px 60px rgba(0,0,0,0.15)',
            'container_max_width' => '1240px',
        ];
    }

    private static function luminaColors(): array
    {
        return [
            'primary' => '#b45309', 'primary_light' => '#fefce8', 'primary_dark' => '#92400e',
            'accent' => '#d97706', 'accent_dark' => '#b45309',
            'nav_bg' => '#1e1b4b', 'sidebar_bg' => '#1e1b4b', 'sidebar_text' => '#a5b4fc',
            'body_bg' => '#fafaf9', 'card_bg' => '#ffffff', 'border_color' => '#e5e7eb',
            'text_color' => '#1f2937', 'muted_color' => '#6b7280', 'footer_bg' => '#1e1b4b',
            'hero_bg_start' => '#1e1b4b', 'hero_bg_mid' => '#312e81', 'hero_bg_end' => '#1e1b4b',
            'welcome_accent' => '#d97706', 'welcome_primary' => '#b45309', 'welcome_secondary' => '#4f46e5',
            'table_header_bg' => '#312e81', 'success' => '#10b981',
            'danger' => '#ef4444', 'warning' => '#f59e0b', 'info' => '#6366f1',
            'link_color' => '#b45309', 'link_hover_color' => '#92400e',
            'heading_color' => '#1e1b4b', 'card_shadow' => 'rgba(0,0,0,0.06)',
            'input_bg' => '#ffffff', 'input_border' => '#d1d5db',
            'input_focus_border' => '#b45309', 'badge_bg' => '#fefce8', 'overlay_bg' => 'rgba(0,0,0,0.5)',
            'font_family' => "'Inter', -apple-system, BlinkMacSystemFont, sans-serif",
            'font_size_base' => '16px', 'font_size_sm' => '14px', 'font_size_lg' => '18px',
            'font_weight_normal' => '400', 'font_weight_bold' => '700',
            'heading_font_family' => "'Inter', -apple-system, BlinkMacSystemFont, sans-serif",
            'line_height_base' => '1.6',
            'spacing_xs' => '4px', 'spacing_sm' => '8px', 'spacing_md' => '16px',
            'spacing_lg' => '24px', 'spacing_xl' => '32px', 'spacing_2xl' => '48px',
            'radius_sm' => '6px', 'radius_md' => '10px', 'radius_lg' => '18px', 'radius_full' => '9999px',
            'shadow_sm' => '0 1px 3px rgba(0,0,0,0.06)',
            'shadow_md' => '0 4px 15px rgba(0,0,0,0.08)',
            'shadow_lg' => '0 20px 60px rgba(0,0,0,0.1)',
            'container_max_width' => '1240px',
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
