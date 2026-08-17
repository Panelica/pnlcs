<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/*
 * Public-site wording for the app showcase.
 *
 * The `sections` group is served from the database, not the lang files - a
 * key that exists only in lang/en/sections.php renders as its own name on the
 * front page, which is how the navbar entry first shipped reading "sections".
 */
return new class extends Migration
{
    private function rows(): array
    {
        return [
            'nav.docker_hosting' => 'Docker Apps',
            'apps.title' => 'One-click apps on your hosting',
            'apps.subtitle' => 'Install WordPress, n8n, databases and :count more with one click - each running inside your own account.',
            'apps.and_more' => 'and :count more',
            'apps.cta' => 'Browse hosting plans',
            'apps.point_isolation' => 'Real isolation: your apps, your resources',
            'apps.point_oneclick' => 'One click to install, running in seconds',
            'apps.point_included' => 'Included in your plan, no extra licence',
        ];
    }

    public function up(): void
    {
        $now = now();
        foreach ($this->rows() as $key => $value) {
            if (! DB::table('dynamic_translations')->where('language', 'en')->where('group', 'sections')->where('key', $key)->exists()) {
                DB::table('dynamic_translations')->insert(['language' => 'en', 'group' => 'sections', 'key' => $key, 'value' => $value, 'is_auto_translated' => false, 'is_reviewed' => true, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
        try {
            foreach (DB::table('dynamic_translations')->distinct()->pluck('language') as $lang) {
                Cache::forget("translations:{$lang}:sections");
            }
        } catch (\Throwable $e) {
        }
    }

    public function down(): void
    {
        DB::table('dynamic_translations')->where('language', 'en')->where('group', 'sections')->whereIn('key', array_keys($this->rows()))->delete();
    }
};
