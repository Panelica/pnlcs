<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Short labels for the runtime tool shortcuts (Laravel / Node.js / Python) on
 * service rows. These are product names, identical in every language, so a
 * single English row is enough - every locale renders the same word.
 */
return new class extends Migration
{
    private function rows(): array
    {
        return [
            'hosting.tools.laravel' => 'Laravel',
            'hosting.tools.nodejs' => 'Node.js',
            'hosting.tools.python' => 'Python',
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
