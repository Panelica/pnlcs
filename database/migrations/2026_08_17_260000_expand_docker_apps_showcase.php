<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/*
 * The showcase was a small strip sitting after the footer, where nobody saw it.
 * It now leads with the argument, shows more of the catalogue, and explains how
 * it works in three steps - and it sits among the plans rather than below the
 * end of the page.
 */
return new class extends Migration
{
    private function rows(): array
    {
        return [
            'apps.eyebrow' => 'Included with every plan',
            'apps.title' => 'Run the apps you want, on hosting that keeps them apart',
            'apps.subtitle' => 'WordPress, n8n, databases, dashboards - :count applications, installed in one click and running inside your own account limits.',
            'apps.featured' => 'Popular choice',
            'apps.cta' => 'See the plans',
            'apps.cta_learn' => 'How it works',
            'apps.and_more' => '+ :count more in the catalogue',

            'apps.point_isolation_t' => 'Real isolation, not shared roulette',
            'apps.point_isolation' => 'Every app runs in your own kernel-enforced slice. Nobody else\'s traffic can eat your memory, and yours cannot escape into theirs.',
            'apps.point_oneclick_t' => 'One click, then it is yours',
            'apps.point_oneclick' => 'Pick an app, give it a name, and point one of your domains at it. No compose files, no server to rent, no SSH.',
            'apps.point_included_t' => 'No licence, no per-app fee',
            'apps.point_included' => 'The catalogue comes with the plan. Run one app or fill the plan - the price is the resources, not the number of apps.',

            'apps.step1_t' => 'Choose a plan',
            'apps.step1' => 'Plans differ by memory, CPU and disk. That is your budget for everything you run.',
            'apps.step2_t' => 'Install what you need',
            'apps.step2' => 'Search the catalogue, check what each app needs, and install it from your control panel.',
            'apps.step3_t' => 'Put it on your domain',
            'apps.step3' => 'Point one of your domains at the app and it is live, with certificates handled for you.',
        ];
    }

    public function up(): void
    {
        $now = now();
        foreach ($this->rows() as $key => $value) {
            $row = DB::table('dynamic_translations')->where('language', 'en')->where('group', 'sections')->where('key', $key);
            $row->exists()
                ? $row->update(['value' => $value, 'updated_at' => $now])
                : DB::table('dynamic_translations')->insert(['language' => 'en', 'group' => 'sections', 'key' => $key, 'value' => $value, 'is_auto_translated' => false, 'is_reviewed' => true, 'created_at' => $now, 'updated_at' => $now]);
        }

        // Below the footer is not a place anyone reads. Put it with the plans,
        // and show a fuller slice of the catalogue.
        DB::table('homepage_sections')->where('slug', 'docker-apps')->update(['sort_order' => 5, 'is_enabled' => true]);
        DB::table('homepage_sections')->where('slug', 'infrastructure')->update(['sort_order' => 6]);
        DB::table('homepage_sections')->where('slug', 'stats')->update(['sort_order' => 7]);
        DB::table('homepage_sections')->where('slug', 'vps-plans')->update(['sort_order' => 8]);
        DB::table('homepage_content')->where('section_slug', 'docker-apps')->where('content_key', 'limit')
            ->update(['content_value' => '24']);

        try {
            foreach (DB::table('dynamic_translations')->distinct()->pluck('language') as $lang) {
                Cache::forget("translations:{$lang}:sections");
            }
        } catch (\Throwable $e) {
        }
    }

    public function down(): void
    {
        DB::table('homepage_sections')->where('slug', 'docker-apps')->update(['sort_order' => 12]);
    }
};
