<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('dynamic_translations')
            ->where('language', 'en')
            ->where('group', 'client')
            ->where('key', 'nav.home_site')
            ->exists();

        if (! $exists) {
            DB::table('dynamic_translations')->insert([
                'language' => 'en',
                'group' => 'client',
                'key' => 'nav.home_site',
                'value' => 'Home',
                'is_auto_translated' => false,
                'is_reviewed' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('dynamic_translations')
            ->where('language', 'en')
            ->where('group', 'client')
            ->where('key', 'nav.home_site')
            ->delete();
    }
};
