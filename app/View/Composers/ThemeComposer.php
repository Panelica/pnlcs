<?php

namespace App\View\Composers;

use App\Models\Setting;
use App\Services\ThemeManager;
use App\Services\ThemeService;
use Illuminate\View\View;

class ThemeComposer
{
    public function compose(View $view): void
    {
        $theme = ThemeService::getActiveTheme();

        // White-label settings
        $brandName = Setting::get('whitelabel_company_name', '');
        $brandUrl = Setting::get('whitelabel_company_url', '');
        $brandEmail = Setting::get('whitelabel_support_email', '');
        $brandCopyright = Setting::get('whitelabel_copyright', '');

        // Dark mode
        $darkModeEnabled = (bool) Setting::get('dark_mode_enabled', false);

        // Theme asset URL (from ThemeManager)
        $activeThemeAssets = null;
        try {
            $themeManager = app(ThemeManager::class);
            $activeThemeAssets = $themeManager->getAssetUrl();
        } catch (\Throwable $e) {
            // Silently fail
        }

        $view->with([
            'themeCssVars'       => ThemeService::generateCssVariables($theme['colors']),
            'themeColors'        => $theme['colors'],
            'themeName'          => $theme['preset'],
            'customLogo'         => Setting::get('custom_logo_path', ''),
            'customFavicon'      => Setting::get('custom_favicon_path', ''),
            // White-label
            'brandName'          => $brandName ?: null,
            'brandUrl'           => $brandUrl ?: null,
            'brandEmail'         => $brandEmail ?: null,
            'brandCopyright'     => $brandCopyright ?: null,
            // Dark mode
            'darkModeEnabled'    => $darkModeEnabled,
            // Theme assets
            'activeThemeAssets'  => $activeThemeAssets,
        ]);
    }
}
