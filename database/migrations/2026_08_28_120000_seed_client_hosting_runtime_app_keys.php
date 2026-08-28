<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Client-area strings for the runtime application tabs (Laravel / Node.js /
 * Python). The service page never showed these apps; the tabs list the
 * account's own apps read-only. Seeds English only, like the container keys
 * before it — the other locales fall back to English at runtime until the
 * i18n flow translates them.
 */
return new class extends Migration
{
    private function rows(): array
    {
        return [
            'hosting.laravel.title'      => 'Laravel Apps',
            'hosting.laravel.subtitle'   => 'Your Laravel applications on this hosting.',
            'hosting.nodejs.title'       => 'Node.js Apps',
            'hosting.nodejs.subtitle'    => 'Your Node.js applications on this hosting.',
            'hosting.python.title'       => 'Python Apps',
            'hosting.python.subtitle'    => 'Your Python applications on this hosting.',
            'hosting.runtime.domain'     => 'Domain',
            'hosting.runtime.version'    => 'Version',
            'hosting.runtime.open'       => 'Open',
            'hosting.runtime.empty_title' => 'No applications yet',
            'hosting.runtime.empty_hint' => 'Create and deploy applications from your hosting control panel.',
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
