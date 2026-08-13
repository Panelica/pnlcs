<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** phpMyAdmin button label for the Databases tab. Seeds + flushes cache. */
return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('dynamic_translations')
            ->where('language', 'en')->where('group', 'client')->where('key', 'hosting.databases.phpmyadmin')->exists();
        if (! $exists) {
            DB::table('dynamic_translations')->insert([
                'language' => 'en', 'group' => 'client', 'key' => 'hosting.databases.phpmyadmin', 'value' => 'phpMyAdmin',
                'is_auto_translated' => false, 'is_reviewed' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
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
        DB::table('dynamic_translations')
            ->where('language', 'en')->where('group', 'client')->where('key', 'hosting.databases.phpmyadmin')->delete();
    }
};
