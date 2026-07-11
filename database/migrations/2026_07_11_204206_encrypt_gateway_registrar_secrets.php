<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Encrypt existing plaintext gateway/registrar secret values in place.
 * Idempotent: rows that already decrypt cleanly are skipped, so re-running is
 * safe. Uses raw DB writes so the already-ciphertext value is stored verbatim
 * (the model cast would otherwise double-encrypt).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['gateway_settings', 'registrar_settings'] as $table) {
            if (!DB::getSchemaBuilder()->hasTable($table)) {
                continue;
            }
            DB::table($table)->orderBy('id')->chunkById(200, function ($rows) use ($table) {
                foreach ($rows as $row) {
                    $v = $row->value ?? null;
                    if ($v === null || $v === '') {
                        continue;
                    }
                    // Already encrypted? leave it.
                    try {
                        Crypt::decryptString($v);
                        continue;
                    } catch (\Throwable $e) {
                        // not encrypted → encrypt now
                    }
                    DB::table($table)->where('id', $row->id)->update([
                        'value' => Crypt::encryptString((string) $v),
                    ]);
                }
            });
        }
    }

    public function down(): void
    {
        // Non-reversible on purpose: we do not want to write secrets back as
        // plaintext. Decryption still works transparently via the model cast.
    }
};
