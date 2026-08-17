<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/* Labels for the connection details shown under an installed app. */
return new class extends Migration
{
    private function rows(): array
    {
        return [
            'hosting.containers.access_title' => 'Connection details',
            'hosting.containers.access_url' => 'Address',
        ];
    }

    public function up(): void
    {
        $now = now();
        foreach ($this->rows() as $key => $value) {
            if (! DB::table('dynamic_translations')->where('language', 'en')->where('group', 'client')->where('key', $key)->exists()) {
                DB::table('dynamic_translations')->insert(['language' => 'en', 'group' => 'client', 'key' => $key, 'value' => $value, 'is_auto_translated' => false, 'is_reviewed' => true, 'created_at' => $now, 'updated_at' => $now]);
            }
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
        DB::table('dynamic_translations')->where('language', 'en')->where('group', 'client')->whereIn('key', array_keys($this->rows()))->delete();
    }
};
