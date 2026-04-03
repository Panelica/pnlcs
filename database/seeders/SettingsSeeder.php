<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Services\ThemeService;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $starter = ThemeService::getPresets()['starter'];

        $seeds = [
            ['setting' => 'active_theme_preset', 'value' => 'starter',                       'group' => 'appearance'],
            ['setting' => 'active_theme',         'value' => json_encode($starter['colors']), 'group' => 'appearance'],
            ['setting' => 'custom_logo_path',     'value' => '',                              'group' => 'appearance'],
            ['setting' => 'custom_favicon_path',  'value' => '',                              'group' => 'appearance'],
        ];

        foreach ($seeds as $seed) {
            Setting::firstOrCreate(
                ['setting' => $seed['setting']],
                ['value' => $seed['value'], 'group' => $seed['group']]
            );
        }
    }
}
