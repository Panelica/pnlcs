<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Treat a domain's EPP/auth code like a credential: encrypted at rest. The
 * column also widens to text because the encrypted payload is longer than the
 * plaintext code. Existing plaintext values are re-encrypted in place;
 * re-running is safe because already-encrypted rows are skipped.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('domains', function (Blueprint $table) {
            $table->text('epp_code')->nullable()->change();
        });

        if (! DB::getSchemaBuilder()->hasTable('domains')) {
            return;
        }

        DB::table('domains')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                $v = $row->epp_code ?? null;
                if ($v === null || $v === '') {
                    continue;
                }
                try {
                    Crypt::decryptString($v);
                    continue; // already encrypted
                } catch (\Throwable) {
                    // plaintext -> encrypt now
                }
                DB::table('domains')->where('id', $row->id)->update([
                    'epp_code' => Crypt::encryptString((string) $v),
                ]);
            }
        });
    }

    public function down(): void {
        Schema::table('domains', function (Blueprint $table) {
            $table->string('epp_code')->nullable()->change();
        });
    }
};
