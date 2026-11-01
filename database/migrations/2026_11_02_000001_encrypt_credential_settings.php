<?php

use App\Casts\SecretSettingValue;
use App\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Encrypt the credential settings already stored in plain text
 * (Setting::SECRETS). New writes are encrypted by the model; this brings the
 * rows written before it along. A value already carrying the prefix is left
 * alone, so running it twice changes nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('settings')->whereIn('setting', Setting::SECRETS)->get(['id', 'value']) as $row) {
            if (! is_string($row->value) || $row->value === '' || str_starts_with($row->value, SecretSettingValue::PREFIX)) {
                continue;
            }
            DB::table('settings')->where('id', $row->id)->update(['value' => SecretSettingValue::PREFIX.Crypt::encryptString($row->value)]);
        }
    }

    public function down(): void
    {
        foreach (DB::table('settings')->whereIn('setting', Setting::SECRETS)->get(['id', 'value']) as $row) {
            if (! is_string($row->value) || ! str_starts_with($row->value, SecretSettingValue::PREFIX)) {
                continue;
            }
            try {
                $plain = Crypt::decryptString(substr($row->value, strlen(SecretSettingValue::PREFIX)));
            } catch (DecryptException) {
                continue;
            }
            DB::table('settings')->where('id', $row->id)->update(['value' => $plain]);
        }
    }
};
