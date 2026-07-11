<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Hash existing plaintext API credential secrets (SHA-256). Idempotent: rows
 * whose secret already looks like a 64-char hex digest are skipped. Existing
 * clients keep their plaintext secret; only its digest is stored server-side.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('api_credentials')) {
            return;
        }
        DB::table('api_credentials')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                $sec = $row->secret ?? '';
                if ($sec === '' || preg_match('/^[a-f0-9]{64}$/', $sec)) {
                    continue;
                }
                DB::table('api_credentials')->where('id', $row->id)->update([
                    'secret' => hash('sha256', $sec),
                ]);
            }
        });
    }

    public function down(): void
    {
        // Irreversible: we cannot recover plaintext secrets from their hashes.
    }
};
