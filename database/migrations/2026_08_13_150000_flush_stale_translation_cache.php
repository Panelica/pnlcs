<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Surface freshly-seeded translations immediately after an update.
 *
 * DbTranslationLoader caches each translation group as `translations:{locale}:
 * {group}` for an hour. The Docker update.sh rebuilds config/route/view caches
 * but not this application cache, so keys inserted by a translation-seed
 * migration during an update stay hidden behind the stale group cache for up to
 * an hour - the UI shows raw keys like `client.hosting.files.title` until it
 * expires. Running after the hosting seed migrations (later timestamp), this
 * forgets every cached translation group so the new keys appear at once.
 *
 * Any future migration that seeds translations must do the same at the end of
 * its up(); this one only clears what already exists at its own run time.
 */
return new class extends Migration
{
    public function up(): void
    {
        try {
            $pairs = DB::table('dynamic_translations')
                ->select('language', 'group')->distinct()->get();
            foreach ($pairs as $p) {
                Cache::forget("translations:{$p->language}:{$p->group}");
            }
        } catch (\Throwable $e) {
            // Cache store or table not ready during install - nothing to flush.
        }
    }

    public function down(): void
    {
        // Cache-only side effect; nothing to reverse.
    }
};
