<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * SSL certificates are issued by their SSL module, not provisioned by the
 * Panelica panel. Older saves left the Panelica resource limits (res_*) and
 * Panelica plan/app keys (panelica_*) in an SSL product's config_options, so
 * the store advertised the certificate as "1 GB RAM, 1 vCPU, 5 GB". Remove
 * those keys and clear the server module the hidden select used to keep.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('products')
            ->where('type', 'ssl')
            ->chunkById(100, function ($rows) {
                foreach ($rows as $row) {
                    $config = is_string($row->config_options)
                        ? (json_decode($row->config_options, true) ?: [])
                        : ((array) $row->config_options);

                    $changed = false;
                    foreach (array_keys($config) as $key) {
                        if (str_starts_with($key, 'res_') || str_starts_with($key, 'panelica_')) {
                            unset($config[$key]);
                            $changed = true;
                        }
                    }

                    $updates = [];
                    if ($changed) {
                        $updates['config_options'] = json_encode($config);
                    }
                    if (($row->server_type ?? null) !== null) {
                        $updates['server_type'] = null;
                    }
                    if ($updates !== []) {
                        DB::table('products')->where('id', $row->id)->update($updates);
                    }
                }
            });
    }

    public function down(): void
    {
        // Irreversible: the removed keys cannot be reconstructed. Restore the
        // database backup taken before the migration if they are needed.
    }
};
