<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/* The address is what a customer wants first after installing; it gets its own
   line rather than sitting behind a disclosure with the passwords. */
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('dynamic_translations')->where('language', 'en')->where('group', 'client')->where('key', 'hosting.containers.open_app')->exists()) {
            DB::table('dynamic_translations')->insert(['language' => 'en', 'group' => 'client', 'key' => 'hosting.containers.open_app', 'value' => 'Open app', 'is_auto_translated' => false, 'is_reviewed' => true, 'created_at' => now(), 'updated_at' => now()]);
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
        DB::table('dynamic_translations')->where('language', 'en')->where('group', 'client')->where('key', 'hosting.containers.open_app')->delete();
    }
};
