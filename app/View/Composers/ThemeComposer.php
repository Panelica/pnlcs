<?php

namespace App\View\Composers;

use App\Models\Setting;
use App\Services\ThemeService;
use Illuminate\View\View;

class ThemeComposer
{
    public function compose(View $view): void
    {
        $theme = ThemeService::getActiveTheme();

        $view->with([
            'themeCssVars'  => ThemeService::generateCssVariables($theme['colors']),
            'themeColors'   => $theme['colors'],
            'themeName'     => $theme['preset'],
            'customLogo'    => Setting::get('custom_logo_path', ''),
            'customFavicon' => Setting::get('custom_favicon_path', ''),
        ]);
    }
}
