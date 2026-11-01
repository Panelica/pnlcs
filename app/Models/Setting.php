<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model {
    protected $fillable = ["setting", "value", "group"];

    /**
     * Settings that hold a credential, kept encrypted with the app key - the
     * way gateway, addon and server credentials already are. A copy of the
     * database (a backup, a dump sent for support) no longer hands them over.
     */
    public const SECRETS = [
        'SMTPPassword', 'GoogleClientSecret', 'GithubClientSecret', 'RecaptchaSecretKey',
        'OpenAIApiKey', 'TwilioAuthToken', 'FraudLabsApiKey', 'MaxMindLicenseKey',
        // The key sent to an operator-added official rate publisher (#372).
        'OfficialRateApiKey',
    ];

    protected $casts = ["value" => \App\Casts\SecretSettingValue::class];

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
