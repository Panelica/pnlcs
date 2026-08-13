<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The modern email UI added a Delete action with its own label. Seed the key,
 * then flush the cached translation groups so it appears immediately after an
 * update (see 2026_08_13_150000_flush_stale_translation_cache for why).
 */
return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('dynamic_translations')
            ->where('language', 'en')->where('group', 'client')->where('key', 'hosting.email.delete')
            ->exists();
        if (! $exists) {
            DB::table('dynamic_translations')->insert([
                'language' => 'en', 'group' => 'client', 'key' => 'hosting.email.delete', 'value' => 'Delete',
                'is_auto_translated' => false, 'is_reviewed' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // Durable pattern: a translation-seeding migration clears the cached
        // groups it may have changed, so the update path never shows raw keys.
        try {
            foreach (DB::table('dynamic_translations')->distinct()->pluck('language') as $lang) {
                Cache::forget("translations:{$lang}:client");
            }
        } catch (\Throwable $e) {
            // Cache not ready during install - nothing to flush.
        }
    }

    public function down(): void
    {
        DB::table('dynamic_translations')
            ->where('language', 'en')->where('group', 'client')->where('key', 'hosting.email.delete')
            ->delete();
    }
};
