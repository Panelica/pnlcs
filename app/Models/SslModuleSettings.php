<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SslModuleSettings extends Model
{
    protected $table = 'ssl_module_settings';

    protected $fillable = ['module', 'setting', 'value'];

    /**
     * The provider's API password was kept in plain text. Encrypted from now on;
     * rows written before read back unchanged (EncryptedValue falls back to the
     * stored value) and are encrypted the next time they are saved.
     */
    protected $casts = ['value' => \App\Casts\EncryptedValue::class];

    public static function getForModule(string $module): array
    {
        return static::where('module', $module)
            ->pluck('value', 'setting')
            ->toArray();
    }

    public static function getSetting(string $module, string $setting, mixed $default = null): mixed
    {
        return static::where('module', $module)
            ->where('setting', $setting)
            ->value('value') ?? $default;
    }

    public static function setSetting(string $module, string $setting, ?string $value): void
    {
        static::updateOrCreate(
            ['module' => $module, 'setting' => $setting],
            ['value' => $value]
        );
    }
}
