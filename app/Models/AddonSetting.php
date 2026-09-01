<?php

namespace App\Models;

use App\Casts\EncryptedValue;
use Illuminate\Database\Eloquent\Model;

/**
 * Per-addon settings, one row per (addon, setting) pair.
 *
 * The value column is encrypted at rest — the same treatment gateway and
 * registrar secrets get — so an addon can store an API key here without it
 * sitting in plaintext. Addons declare their fields via config() and the
 * AddonController persists whatever the operator submits.
 */
class AddonSetting extends Model
{
    protected $table = 'addon_settings';

    protected $fillable = ['addon', 'setting', 'value'];

    protected $casts = ['value' => EncryptedValue::class];

    /**
     * @return array<string, mixed>
     */
    public static function getForAddon(string $addon): array
    {
        try {
            // get() (not pluck) so the EncryptedValue cast runs: the values are
            // encrypted at rest and must be decrypted before use.
            return static::where('addon', $addon)->get()
                ->pluck('value', 'setting')
                ->toArray();
        } catch (\Throwable) {
            // Table not migrated yet (install/migrate) — nothing to read.
            return [];
        }
    }

    public static function getSetting(string $addon, string $setting, mixed $default = null): mixed
    {
        $value = static::where('addon', $addon)->where('setting', $setting)->value('value');

        return $value === null || $value === '' ? $default : $value;
    }

    public static function setSetting(string $addon, string $setting, mixed $value): void
    {
        if ($value === null || $value === '') {
            static::where('addon', $addon)->where('setting', $setting)->delete();

            return;
        }

        static::updateOrCreate(
            ['addon' => $addon, 'setting' => $setting],
            ['value' => (string) $value]
        );
    }
}
