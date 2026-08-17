<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Wording for the per-container shell.
 *
 * A plain OS container (ubuntu, alma, alpine) runs `sleep infinity` and ships no
 * SSH server, so without this the customer had a running machine and no way into
 * it. Both users are offered: most images run as root already, but a hardened
 * one drops to its own user and some work needs the other.
 */
return new class extends Migration
{
    private array $rows = [
        'hosting.containers.terminal' => 'Terminal',
        'hosting.containers.terminal_default' => 'Open shell',
        'hosting.containers.terminal_root' => 'As root',
        'hosting.containers.not_your_app' => 'That app does not belong to this service.',
    ];

    public function up(): void
    {
        foreach ($this->rows as $key => $value) {
            $exists = DB::table('dynamic_translations')->where('language', 'en')
                ->where('group', 'client')->where('key', $key)->exists();
            if ($exists) {
                continue;
            }
            DB::table('dynamic_translations')->insert([
                'language' => 'en', 'group' => 'client', 'key' => $key, 'value' => $value,
                'is_auto_translated' => false, 'is_reviewed' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('dynamic_translations')->where('language', 'en')->where('group', 'client')
            ->whereIn('key', array_keys($this->rows))->delete();
    }
};
