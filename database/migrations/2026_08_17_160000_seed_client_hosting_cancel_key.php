<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/*
 * The install box gained a Cancel button after the catalogue keys had already
 * been seeded, and seeding only inserts what is missing - so the key needs its
 * own migration or the button renders as a raw translation key.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('dynamic_translations')->where('language', 'en')->where('group', 'client')->where('key', 'hosting.containers.cancel')->exists()) {
            DB::table('dynamic_translations')->insert(['language' => 'en', 'group' => 'client', 'key' => 'hosting.containers.cancel', 'value' => 'Cancel', 'is_auto_translated' => false, 'is_reviewed' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
        try {
            foreach (DB::table('dynamic_translations')->distinct()->pluck('language') as $lang) {
                Cache::forget("translations:{$lang}:client");
            }
        } catch (\Throwable $e) {
        }
    }

    public function down(): void
    {
        DB::table('dynamic_translations')->where('language', 'en')->where('group', 'client')->where('key', 'hosting.containers.cancel')->delete();
    }
};
