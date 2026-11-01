<?php

namespace App\Casts;

use App\Models\Setting;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * A settings row's value: encrypted at rest when the row holds a credential
 * (Setting::SECRETS), as it is as-is otherwise.
 *
 * What this encrypts carries a prefix, and only that is decrypted on read:
 * a row written before this still reads as stored, a value read without its
 * row's name (pluck()) is still recognised, and a module that encrypts its
 * own value before saving it (PanelicaLicense's partner key) gets back
 * exactly what it saved.
 */
class SecretSettingValue implements CastsAttributes
{
    public const PREFIX = 'pnlcs-enc:';

    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if (! is_string($value) || ! str_starts_with($value, self::PREFIX)) {
            return $value;
        }

        try {
            return Crypt::decryptString(substr($value, strlen(self::PREFIX)));
        } catch (DecryptException) {
            return $value;
        }
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        $name = (string) ($attributes['setting'] ?? '');

        if ($value === null || $value === '' || ! in_array($name, Setting::SECRETS, true)) {
            return [$key => $value];
        }

        return [$key => self::PREFIX.Crypt::encryptString((string) $value)];
    }
}
