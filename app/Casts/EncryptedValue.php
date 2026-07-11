<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Encrypts a value at rest, but reads gracefully: a value that is not (yet)
 * encrypted — e.g. a legacy row written before this cast existed — is returned
 * as-is instead of throwing. This lets us encrypt sensitive settings
 * (gateway/registrar secrets) without a hard cutover, so live payments never
 * break on a decrypt error. New writes are always encrypted.
 */
class EncryptedValue implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }
        try {
            return Crypt::decryptString($value);
        } catch (DecryptException $e) {
            return $value; // legacy plaintext
        }
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return [$key => null];
        }
        return [$key => Crypt::encryptString((string) $value)];
    }
}
