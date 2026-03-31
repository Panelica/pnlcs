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
}
