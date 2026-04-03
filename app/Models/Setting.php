<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model {
    protected $fillable = ["setting", "value", "group"];

    public static function get(string $key, $default = null) {
        $setting = static::where("setting", $key)->first();
        return $setting ? $setting->value : $default;
    }

    public static function set(string $key, $value, string $group = "general"): void {
        static::updateOrCreate(["setting" => $key], ["value" => $value, "group" => $group]);
    }

    public static function getThemeColors(): array
    {
        $json = static::get('active_theme');
        if ($json) {
            $colors = json_decode($json, true);
            if (is_array($colors) && !empty($colors)) {
                return $colors;
            }
        }
        return self::getDefaultColors();
    }

    public static function getDefaultColors(): array
    {
        return \App\Services\ThemeService::getPresets()['starter']['colors'];
    }
}
